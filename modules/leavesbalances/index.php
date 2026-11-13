<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

/**
 * LeavesBalances - custom module (per fiscal year, per leave type, linked to employee)
 */
require_login();
require_once __DIR__ . '/../../includes/helpers.php';
$module = 'leavesbalances';
$isAr = lang() === 'ar';
$u = current_user();
$canAdd = user_can($module, 'add');
$canEdit = user_can($module, 'edit');
$canDel = user_can($module, 'del');

$employees = fetchAll("SELECT id, empfullname, empfullnamear FROM " . table('employeesmain') . " WHERE omitted=0 ORDER BY empfullname ASC");
$fyears    = fetchAll("SELECT id, fstartdate, fenddate FROM " . table('fiscalyears') . " WHERE omitted=0 ORDER BY fstartdate DESC");
$leavetypes = fetchAll("SELECT id, leavetypename, leavetypenamear FROM " . table('leavestypes') . " WHERE omitted=0 ORDER BY leavetypename ASC");

$action = $_GET['a'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$row = $id ? fetchRow("SELECT * FROM " . table('leavesbalances') . " WHERE id=? LIMIT 1", [$id]) : null;

// ---------- EXPORT ----------
if ($action === 'export') {
    require_permission($module, 'view');
    $fmt = $_GET['fmt'] ?? 'csv';
    $search = trim($_GET['search'] ?? '');
    $where = "b.omitted = 0";
    $params = [];
    if ($search !== '') {
        $norm = arabic_normalize($search);
        $where .= " AND (b.empfullname LIKE ? OR b.empfullnamear LIKE ? OR b.leavetypename LIKE ? OR b.leavetypenamear LIKE ?)";
        $params = ['%'.$search.'%', '%'.$norm.'%', '%'.$search.'%', '%'.$norm.'%'];
    }
    $rows = fetchAll("SELECT b.empfullname AS 'Employee', b.empfullnamear AS 'Employee (Arabic)',
                             b.leavetypename AS 'Leave Type', b.leavetypenamear AS 'Leave Type (Arabic)',
                             b.fstartdate AS 'Fiscal Year Start', b.fenddate AS 'Fiscal Year End',
                             b.carryoverbalance AS 'Carryover', b.initialbalance AS 'Initial',
                             b.usedbalance AS 'Used', b.remainingbalance AS 'Remaining'
                      FROM " . table('leavesbalances') . " b
                      WHERE $where ORDER BY b.id DESC", $params);
    if ($rows === false) { set_flash('error', 'Database not available.'); header('Location: ?module=home'); exit; }
    if (empty($rows)) { set_flash('error', 'No data to export.'); header('Location: ?module=leavesbalances'); exit; }
    $headers = array_keys($rows[0]);
    $dataRows = [];
    foreach ($rows as $r) { $dataRows[] = array_values($r); }
    do_export($fmt, 'leavesbalances_export', $isAr ? 'تقرير أرصدة الإجازات' : 'Leave Balances Report', $headers, $dataRows);
    exit;
}

// ---------- ADD ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    require_permission($module, 'add');
    $empid = (int)($_POST['empid'] ?? 0);
    $fyid = (int)($_POST['fiscalyearid'] ?? 0);
    $ltid = (int)($_POST['leavetypeid'] ?? 0);
    $carry = (int)($_POST['carryoverbalance'] ?? 0);
    $initial = (int)($_POST['initialbalance'] ?? 0);
    $used = (int)($_POST['usedbalance'] ?? 0);
    $remaining = $carry + $initial - $used;
    $e = fetchRow("SELECT empfullname, empfullnamear FROM " . table('employeesmain') . " WHERE id=? LIMIT 1", [$empid]);
    $f = fetchRow("SELECT fstartdate, fenddate FROM " . table('fiscalyears') . " WHERE id=? LIMIT 1", [$fyid]);
    $lt = fetchRow("SELECT id, leavetypename, leavetypenamear FROM " . table('leavestypes') . " WHERE id=? LIMIT 1", [$ltid]);
    $data = [
        'empid' => $empid,
        'empfullname' => $e['empfullname'] ?? '',
        'empfullnamear' => $e['empfullnamear'] ?? '',
        'leavetypeid' => $ltid,
        'leavetypename' => $lt['leavetypename'] ?? '',
        'leavetypenamear' => $lt['leavetypenamear'] ?? '',
        'fiscalyearid' => $fyid,
        'fstartdate' => $f['fstartdate'] ?? '',
        'fenddate' => $f['fenddate'] ?? '',
        'carryoverbalance' => $carry,
        'initialbalance' => $initial,
        'usedbalance' => $used,
        'remainingbalance' => $remaining,
    ];
    if ($empid === 0 || $fyid === 0 || $ltid === 0) {
        set_flash('error', 'Employee, Fiscal Year, and Leave Type are required.');
    } else {
        // Check for duplicate balance for same emp + fiscal year + leave type
        $dup = fetchRow("SELECT id FROM " . table('leavesbalances') . " WHERE empid=? AND fiscalyearid=? AND leavetypeid=? AND omitted=0 LIMIT 1", [$empid, $fyid, $ltid]);
        if ($dup) {
            set_flash('error', 'A leave balance already exists for this employee, fiscal year, and leave type.');
            header('Location: ?module=leavesbalances&a=add'); exit;
        } else {
            $now = date('Y-m-d H:i:s');
            $an = $u['username_ar'] ?? $u['username'];
            $cols = []; $vals = []; $params = [];
            $cols[] = '`rguid`'; $vals[] = '?'; $params[] = bin2hex(random_bytes(16));
            foreach ($data as $k => $v) { $cols[] = '`'.$k.'`'; $vals[] = '?'; $params[] = $v; }
            $cols[] = '`dtadded`';   $vals[] = '?'; $params[] = $now;
            $cols[] = '`addedbyid`'; $vals[] = '?'; $params[] = $u['id'];
            $cols[] = '`addedby`';  $vals[] = '?'; $params[] = $u['username'];
            $cols[] = '`addedbyname`';   $vals[] = '?'; $params[] = $an;
            $cols[] = '`addedbynamear`'; $vals[] = '?'; $params[] = $an;
            $cols[] = '`lastedit`';   $vals[] = '?'; $params[] = $now;
            $cols[] = '`editedbyid`'; $vals[] = '?'; $params[] = $u['id'];
            $cols[] = '`editedby`';   $vals[] = '?'; $params[] = $u['username'];
            $cols[] = '`editedbyname`';   $vals[] = '?'; $params[] = $an;
            $cols[] = '`editedbynamear`'; $vals[] = '?'; $params[] = $an;
            $cols[] = '`omitted`';    $vals[] = '?'; $params[] = 0;
            q("INSERT INTO " . table('leavesbalances') . " (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")", $params);
            set_flash('success', 'Leave balance added.');
            header('Location: ?module=leavesbalances'); exit;
        }
    }
    // repopulate row for form on error
    $row = $data;
}

// ---------- EDIT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $id) {
    require_permission($module, 'edit');
    $empid = (int)($_POST['empid'] ?? 0);
    $fyid = (int)($_POST['fiscalyearid'] ?? 0);
    $ltid = (int)($_POST['leavetypeid'] ?? 0);
    $carry = (int)($_POST['carryoverbalance'] ?? 0);
    $initial = (int)($_POST['initialbalance'] ?? 0);
    $used = (int)($_POST['usedbalance'] ?? 0);
    $remaining = $carry + $initial - $used;
    $e = fetchRow("SELECT empfullname, empfullnamear FROM " . table('employeesmain') . " WHERE id=? LIMIT 1", [$empid]);
    $f = fetchRow("SELECT fstartdate, fenddate FROM " . table('fiscalyears') . " WHERE id=? LIMIT 1", [$fyid]);
    $lt = fetchRow("SELECT id, leavetypename, leavetypenamear FROM " . table('leavestypes') . " WHERE id=? LIMIT 1", [$ltid]);
    $data = [
        'empid' => $empid,
        'empfullname' => $e['empfullname'] ?? '',
        'empfullnamear' => $e['empfullnamear'] ?? '',
        'leavetypeid' => $ltid,
        'leavetypename' => $lt['leavetypename'] ?? '',
        'leavetypenamear' => $lt['leavetypenamear'] ?? '',
        'fiscalyearid' => $fyid,
        'fstartdate' => $f['fstartdate'] ?? '',
        'fenddate' => $f['fenddate'] ?? '',
        'carryoverbalance' => $carry,
        'initialbalance' => $initial,
        'usedbalance' => $used,
        'remainingbalance' => $remaining,
    ];
    if ($empid === 0 || $fyid === 0 || $ltid === 0) {
        set_flash('error', 'Employee, Fiscal Year, and Leave Type are required.');
    } else {
        // Check for duplicate (excluding current record)
        $dup = fetchRow("SELECT id FROM " . table('leavesbalances') . " WHERE empid=? AND fiscalyearid=? AND leavetypeid=? AND id != ? AND omitted=0 LIMIT 1", [$empid, $fyid, $ltid, $id]);
        if ($dup) {
            set_flash('error', 'A leave balance already exists for this employee, fiscal year, and leave type.');
            header('Location: ?module=leavesbalances&a=edit&id=' . $id); exit;
        }
        $now = date('Y-m-d H:i:s');
        $an = $u['username_ar'] ?? $u['username'];
        shadow_log('leavesbalances', $id, $row, 'update');
        $sets = []; $params = [];
        foreach ($data as $k => $v) { $sets[] = '`'.$k.'`=?'; $params[] = $v; }
        $sets[] = '`lastedit`=?';   $params[] = $now;
        $sets[] = '`editedbyid`=?'; $params[] = $u['id'];
        $sets[] = '`editedby`=?';   $params[] = $u['username'];
        $sets[] = '`editedbyname`=?';   $params[] = $an;
        $sets[] = '`editedbynamear`=?'; $params[] = $an;
        $params[] = $id;
        q("UPDATE " . table('leavesbalances') . " SET " . implode(',', $sets) . " WHERE id=?", $params);
        set_flash('success', 'Leave balance updated.');
        header('Location: ?module=leavesbalances'); exit;
    }
    $row = array_merge($row ?? [], $data);
}

// ---------- DELETE ----------
if ($action === 'del' && $id) {
    require_permission($module, 'del');
    $row = fetchRow("SELECT * FROM " . table('leavesbalances') . " WHERE id=? LIMIT 1", [$id]);
    if (isset($_GET['confirm']) && $_GET['confirm'] === '1') {
        if ($row) shadow_log('leavesbalances', $id, $row, 'omit');
        q("UPDATE " . table('leavesbalances') . " SET omitted=1 WHERE id=?", [$id]);
        set_flash('success', 'Leave balance omitted.');
        header('Location: ?module=leavesbalances'); exit;
    }
    echo '<div class="alert alert-warning">
            <h4>Omit leave balance?</h4>
            <a href="?module=leavesbalances&a=del&id='.$id.'&confirm=1" class="btn btn-danger">Yes, Omit</a>
            <a href="?module=leavesbalances" class="btn btn-secondary">Cancel</a>
          </div>';
    return;
}

// ---------- ADD FORM ----------
if ($action === 'add') {
    require_permission($module, 'add');
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-plus-lg me-2"></i>'.($isAr ? 'إضافة رصيد إجازة' : 'Add Leave Balance').'</h2>
            <a href="?module=leavesbalances" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
          </div>';
    echo '<form method="post" action="?module=leavesbalances&a=add">';
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'الموظف' : 'Employee').'</label>'
        .'<select name="empid" class="form-select required"><option value="">-- Select --</option>';
    foreach ($employees as $e) {
        $label = $isAr ? $e['empfullnamear'] : $e['empfullname'];
        echo '<option value="'.$e['id'].'">'.htmlspecialchars($label).'</option>';
    }
    echo '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'نوع الإجازة' : 'Leave Type').'</label>'
        .'<select name="leavetypeid" class="form-select required"><option value="">-- Select --</option>';
    foreach ($leavetypes as $lt) {
        $sel = (isset($row['leavetypeid']) && $row['leavetypeid'] == $lt['id']) ? ' selected' : '';
        $label = $isAr ? $lt['leavetypenamear'] : $lt['leavetypename'];
        echo '<option value="'.$lt['id'].'"'.$sel.'>'.htmlspecialchars($label).'</option>';
    }
    echo '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'السنة المالية' : 'Fiscal Year').'</label>'
        .'<select name="fiscalyearid" class="form-select required"><option value="">-- Select --</option>';
    foreach ($fyears as $f) {
        echo '<option value="'.$f['id'].'">'.htmlspecialchars($f['fstartdate'].' to '.$f['fenddate']).'</option>';
    }
    echo '</select></div>';
    echo field_input(['name'=>'carryoverbalance','label'=>'Carryover Balance','label_ar'=>'الرصيد المحول','type'=>'number','required'=>true], $row['carryoverbalance'] ?? 0);
    echo field_input(['name'=>'initialbalance','label'=>'Initial Balance','label_ar'=>'الرصيد الابتدائي','type'=>'number','required'=>true], $row['initialbalance'] ?? 0);
    echo field_input(['name'=>'usedbalance','label'=>'Used Balance','label_ar'=>'الرصيد المستخدم','type'=>'number','required'=>true], $row['usedbalance'] ?? 0);
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'الرصيد المتبقي (يُحسب تلقائياً)' : 'Remaining Balance (auto)').'</label>'
        .'<input type="text" id="remaining_preview" class="form-control" readonly></div>';
    echo '<script>
    function updateRemaining(){
      var c=parseInt(document.querySelector("[name=carryoverbalance]").value||0,10);
      var i=parseInt(document.querySelector("[name=initialbalance]").value||0,10);
      var u=parseInt(document.querySelector("[name=usedbalance]").value||0,10);
      var el=document.getElementById("remaining_preview");
      if(el) el.value = (c+i-u);
    }
    ["carryoverbalance","initialbalance","usedbalance"].forEach(function(n){
      var el=document.querySelector("[name="+n+"]");
      if(el){ el.addEventListener("input", updateRemaining); }
    });
    updateRemaining();
    </script>';
    echo '<div class="d-grid gap-2"><button class="btn btn-primary">Save</button>
          <a href="?module=leavesbalances" class="btn btn-secondary">Cancel</a></div>';
    echo '</form>';
    return;
}

// ---------- EDIT FORM ----------
if ($action === 'edit' && $id && $row) {
    require_permission($module, 'edit');
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-pencil-square me-2"></i>'.($isAr ? 'تعديل رصيد إجازة' : 'Edit Leave Balance').'</h2>
            <a href="?module=leavesbalances" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
          </div>';
    echo '<form method="post" action="?module=leavesbalances&a=edit&id='.$id.'">';
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'الموظف' : 'Employee').'</label>'
        .'<select name="empid" class="form-select"><option value="">-- Select --</option>';
    foreach ($employees as $e) {
        $sel = ($row['empid'] == $e['id']) ? ' selected' : '';
        $label = $isAr ? $e['empfullnamear'] : $e['empfullname'];
        echo '<option value="'.$e['id'].'"'.$sel.'>'.htmlspecialchars($label).'</option>';
    }
    echo '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'نوع الإجازة' : 'Leave Type').'</label>'
        .'<select name="leavetypeid" class="form-select"><option value="">-- Select --</option>';
    foreach ($leavetypes as $lt) {
        $sel = ($row['leavetypeid'] == $lt['id']) ? ' selected' : '';
        $label = $isAr ? $lt['leavetypenamear'] : $lt['leavetypename'];
        echo '<option value="'.$lt['id'].'"'.$sel.'>'.htmlspecialchars($label).'</option>';
    }
    echo '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'السنة المالية' : 'Fiscal Year').'</label>'
        .'<select name="fiscalyearid" class="form-select"><option value="">-- Select --</option>';
    foreach ($fyears as $f) {
        $sel = ($row['fiscalyearid'] == $f['id']) ? ' selected' : '';
        echo '<option value="'.$f['id'].'"'.$sel.'>'.htmlspecialchars($f['fstartdate'].' to '.$f['fenddate']).'</option>';
    }
    echo '</select></div>';
    echo field_input(['name'=>'carryoverbalance','label'=>'Carryover Balance','label_ar'=>'الرصيد المحول','type'=>'number','required'=>true], $row['carryoverbalance']);
    echo field_input(['name'=>'initialbalance','label'=>'Initial Balance','label_ar'=>'الرصيد الابتدائي','type'=>'number','required'=>true], $row['initialbalance']);
    echo field_input(['name'=>'usedbalance','label'=>'Used Balance','label_ar'=>'الرصيد المستخدم','type'=>'number','required'=>true], $row['usedbalance']);
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'الرصيد المتبقي' : 'Remaining Balance').'</label>'
        .'<input type="text" id="remaining_preview" class="form-control" value="'.htmlspecialchars($row['remainingbalance']).'" readonly></div>';
    echo '<script>
    function updateRemaining(){
      var c=parseInt(document.querySelector("[name=carryoverbalance]").value||0,10);
      var i=parseInt(document.querySelector("[name=initialbalance]").value||0,10);
      var u=parseInt(document.querySelector("[name=usedbalance]").value||0,10);
      var el=document.getElementById("remaining_preview");
      if(el) el.value = (c+i-u);
    }
    ["carryoverbalance","initialbalance","usedbalance"].forEach(function(n){
      var el=document.querySelector("[name="+n+"]");
      if(el){ el.addEventListener("input", updateRemaining); }
    });
    </script>';
    echo '<div class="d-grid gap-2"><button class="btn btn-primary">Update</button>
          <a href="?module=leavesbalances" class="btn btn-secondary">Cancel</a></div>';
    echo '</form>';
    return;
}

// ---------- LIST ----------
require_permission($module, 'view');
$search = trim($_GET['search'] ?? '');
$where = "b.omitted = 0";
$params = [];
if ($search !== '') {
    $norm = arabic_normalize($search);
    $where .= " AND (b.empfullname LIKE ? OR b.empfullnamear LIKE ? OR b.leavetypename LIKE ? OR b.leavetypenamear LIKE ?)";
    $params = ['%'.$search.'%', '%'.$norm.'%', '%'.$search.'%', '%'.$norm.'%'];
}
$rows = fetchAll("SELECT b.*, e.empfullname, e.empfullnamear
    FROM " . table('leavesbalances') . " b
    LEFT JOIN " . table('employeesmain') . " e ON e.id = b.empid
    WHERE $where ORDER BY b.id DESC", $params);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-wallet2 me-2"></i><?= $isAr ? 'أرصدة الإجازات' : 'Leave Balances' ?></h2>
    <div>
        <?php if ($canAdd): ?><a href="?module=leavesbalances&a=add" class="btn btn-primary me-1"><i class="bi bi-plus me-1"></i><?= $isAr ? 'إضافة' : 'Add' ?></a><?php endif; ?>
        <a href="?module=leavesbalances&a=export&fmt=csv" class="btn btn-outline-secondary btn-sm me-1">CSV</a>
        <a href="?module=leavesbalances&a=export&fmt=xlsx" class="btn btn-outline-success btn-sm me-1">XLSX</a>
        <a href="?module=leavesbalances&a=export&fmt=docx" class="btn btn-outline-primary btn-sm me-1">Word</a>
        <a href="?module=leavesbalances&a=export&fmt=pdf" class="btn btn-outline-danger btn-sm me-1">PDF</a>
    </div>
</div>
<form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="module" value="leavesbalances">
    <div class="col-md-4"><input type="search" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="<?= $isAr ? 'بحث (عربي/إنجليزي)...' : 'Search (Arabic/English)...' ?>"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
    <div class="col-md-2"><a href="?module=leavesbalances" class="btn btn-outline-secondary w-100">Reset</a></div>
</form>
<div class="row mt-3 justify-content-center">
<div class="col-12 col-lg-11">
<div class="table-responsive">
<table class="table table-striped table-hover align-middle table-sm">
<thead class="table-dark"><tr>
<th>#</th><th><?= $isAr ? 'الموظف' : 'Employee' ?></th>
<th><?= $isAr ? 'نوع الإجازة' : 'Leave Type' ?></th>
<th><?= $isAr ? 'السنة المالية' : 'Fiscal Year' ?></th>
<th><?= $isAr ? 'من' : 'From' ?></th>
<th><?= $isAr ? 'إلى' : 'To' ?></th>
<th><?= $isAr ? 'محول من السنة الماضية' : 'Carryover' ?></th>
<th><?= $isAr ? 'ابتدائي' : 'Initial' ?></th>
<th><?= $isAr ? 'مستخدم' : 'Used' ?></th>
<th><?= $isAr ? 'متبقي' : 'Remaining' ?></th>
<th>Actions</th>
</tr></thead>
<tbody>
<?php foreach ($rows as $i => $r): ?>
<tr>
<td><?= $i+1 ?></td>
<td><?= htmlspecialchars($isAr ? ($r['empfullnamear'] ?? '') : ($r['empfullname'] ?? '')) ?></td>
<td><?= htmlspecialchars($isAr ? ($r['leavetypenamear'] ?? '') : ($r['leavetypename'] ?? '')) ?></td>
<td><?= htmlspecialchars($r['fstartdate'] ?? '') ?> to <?= htmlspecialchars($r['fenddate'] ?? '') ?></td>
<td><?= htmlspecialchars($r['fstartdate'] ?? '') ?></td>
<td><?= htmlspecialchars($r['fenddate'] ?? '') ?></td>
<td><?= (int)($r['carryoverbalance'] ?? 0) ?></td>
<td><?= (int)($r['initialbalance'] ?? 0) ?></td>
<td><?= (int)($r['usedbalance'] ?? 0) ?></td>
<td><?= (int)($r['remainingbalance'] ?? 0) ?></td>
<td class="d-flex gap-1">
    <?php if ($canEdit): ?><a href="?module=leavesbalances&a=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a><?php endif; ?>
    <?php if ($canDel): ?><a href="?module=leavesbalances&a=del&id=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Omit this balance?')"><i class="bi bi-trash"></i></a><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
</div>
</div>
