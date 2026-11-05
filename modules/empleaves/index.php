<?php
/**
 * EmpLeaves - custom module (validation, approval, balance update, lock once approved)
 */
require_login();
require_once __DIR__ . '/../../includes/helpers.php';
$module = 'empleaves';
$isAr = lang() === 'ar';
$u = current_user();
$canAdd = user_can($module, 'add');
$canEdit = user_can($module, 'edit');
$canDel = user_can($module, 'del');

$employees  = fetchAll("SELECT id, empfullname, empfullnamear FROM " . table('employeesmain') . " WHERE omitted=0 ORDER BY empfullname ASC");
$leavetypes = fetchAll("SELECT id, leavetypename, leavetypenamear, incwe, incholidays FROM " . table('leavestypes') . " WHERE omitted=0 ORDER BY leavetypename ASC");
$fyears     = fetchAll("SELECT id, fstartdate, fenddate FROM " . table('fiscalyears') . " WHERE omitted=0 ORDER BY fstartdate DESC");

$action = $_GET['a'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$row = $id ? fetchRow("SELECT * FROM " . table('empleaves') . " WHERE id=? LIMIT 1", [$id]) : null;

// ---------- EXPORT ----------
if ($action === 'export') {
    require_permission($module, 'view');
    $fmt = $_GET['fmt'] ?? 'csv';
    $search = trim($_GET['search'] ?? '');
    $where = "l.omitted = 0";
    $params = [];
    if ($search !== '') {
        $norm = arabic_normalize($search);
        $where .= " AND (l.empfullname LIKE ? OR l.empfullnamear LIKE ? OR l.leavetypename LIKE ? OR l.leavetypenamear LIKE ?)";
        $params = ['%'.$search.'%', '%'.$norm.'%', '%'.$search.'%', '%'.$norm.'%'];
    }
    $rows = fetchAll("SELECT l.empfullname AS 'Employee', l.empfullnamear AS 'Employee (Arabic)',
                             l.leavetypename AS 'Leave Type', l.leavetypenamear AS 'Leave Type (Arabic)',
                             l.startfrom AS 'Start From', l.endon AS 'End On',
                             l.balancebefore AS 'Balance Before', l.balanceafter AS 'Balance After',
                             l.isapproved AS 'Approved', l.empsuperiorname AS 'Superior'
                      FROM " . table('empleaves') . " l
                      WHERE $where ORDER BY l.id DESC", $params);
    if ($rows === false) { set_flash('error', 'Database not available.'); header('Location: index.php?module=home'); exit; }
    if (empty($rows)) { set_flash('error', 'No data to export.'); header('Location: index.php?module=empleaves'); exit; }
    $headers = array_keys($rows[0]);
    $dataRows = [];
    foreach ($rows as $r) { $dataRows[] = array_values($r); }
    do_export($fmt, 'empleaves_export', $isAr ? 'تقرير الإجازات' : 'Leaves Report', $headers, $dataRows);
    exit;
}

function leave_days($start, $end, $incwe = 0, $incholidays = 0) {
    $s = strtotime($start);
    $e = strtotime($end);
    if ($s === false || $e === false || $e < $s) return 0;
    $days = 0;
    for ($d = $s; $d <= $e; $d = strtotime('+1 day', $d)) {
        $w = (int)date('w', $d);
        if (!$incwe && ($w === 0 || $w === 6)) continue;
        $days++;
    }
    return $days;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    require_permission($module, $action === 'add' ? 'add' : 'edit');
    $empid = (int)($_POST['empid'] ?? 0);
    $ltid = (int)($_POST['leavetypeid'] ?? 0);
    $start = trim($_POST['startfrom'] ?? '');
    $end = trim($_POST['endon'] ?? '');
    $isapproved = !empty($_POST['isapproved']) ? 1 : 0;
    $supid = (int)($_POST['empsuperiorid'] ?? 0);
    if ($empid === 0 || $ltid === 0 || $start === '' || $end === '') {
        set_flash('error', 'Employee, Leave Type, start and end dates are required.');
    } elseif (strtotime($end) < strtotime($start)) {
        set_flash('error', 'End date cannot be older than start date.');
    } else {
        // must not cross two fiscal years
        $fy = fetchRow("SELECT id, fstartdate, fenddate FROM " . table('fiscalyears') . " WHERE omitted=0 AND ? BETWEEN fstartdate AND fenddate LIMIT 1", [$start]);
        $fy2 = fetchRow("SELECT id FROM " . table('fiscalyears') . " WHERE omitted=0 AND ? BETWEEN fstartdate AND fenddate LIMIT 1", [$end]);
        if (!$fy || !$fy2 || $fy['id'] !== $fy2['id']) {
            set_flash('error', 'Leave period cannot span between two fiscal years.');
        } else {
            $lt = fetchRow("SELECT incwe, incholidays, leavetypename, leavetypenamear FROM " . table('leavestypes') . " WHERE id=? LIMIT 1", [$ltid]);
            $days = leave_days($start, $end, $lt['incwe'] ?? 0, $lt['incholidays'] ?? 0);
            $e = fetchRow("SELECT empfullname, empfullnamear, empsuperiorid, empsuperiorname, empsuperiornamear FROM " . table('employeesmain') . " WHERE id=? LIMIT 1", [$empid]);
            $supName = ''; $supNameAr = '';
            if ($supid) {
                $s = fetchRow("SELECT empfullname, empfullnamear FROM " . table('employeesmain') . " WHERE id=? LIMIT 1", [$supid]);
                $supName = $s['empfullname'] ?? ''; $supNameAr = $s['empfullnamear'] ?? '';
            } elseif ($e) {
                $supName = $e['empsuperiorname'] ?? ''; $supNameAr = $e['empsuperiornamear'] ?? '';
            }
            $data = [
                'empid' => $empid,
                'empfullname' => $e['empfullname'] ?? '',
                'empfullnamear' => $e['empfullnamear'] ?? '',
                'leavetypeid' => $ltid,
                'leavetypename' => $lt['leavetypename'] ?? '',
                'leavetypenamear' => $lt['leavetypenamear'] ?? '',
                'startfrom' => $start,
                'endon' => $end,
                'balancebefore' => 0,
                'balanceafter' => 0,
                'isapproved' => $isapproved,
                'empsuperiorid' => $supid,
                'empsuperiorname' => $supName,
                'empsuperiornamear' => $supNameAr,
            ];
            // find balance for this employee, fiscal year, and leave type
            $bal = fetchRow("SELECT remainingbalance FROM " . table('leavesbalances') . " WHERE empid=? AND fiscalyearid=? AND leavetypeid=? AND omitted=0 LIMIT 1", [$empid, $fy['id'], $ltid]);
            $balanceBefore = (int)($bal['remainingbalance'] ?? 0);
            $data['balancebefore'] = $balanceBefore;
            $balanceAfter = $balanceBefore;
            $canSave = true;
            if ($isapproved && $days > $balanceBefore) {
                $canSave = false;
                set_flash('error', 'Not enough leave balance.');
            }
            $data['balanceafter'] = $isapproved && $canSave ? ($balanceBefore - $days) : $balanceBefore;
            if ($canSave) {
                $now = date('Y-m-d H:i:s');
                $an = $u['username_ar'] ?? $u['username'];
                if ($action === 'add') {
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
                    $ins = q("INSERT INTO " . table('empleaves') . " (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")", $params);
                    if ($ins && $isapproved) {
                        q("UPDATE " . table('leavesbalances') . " SET usedbalance = usedbalance + ?, remainingbalance = remainingbalance - ?, lastedit=?, editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE empid=? AND fiscalyearid=? AND leavetypeid=? AND omitted=0",
                            [$days, $days, date('Y-m-d H:i:s'), $u['id'], $u['username'], $u['username_ar'] ?? $u['username'], $u['username_ar'] ?? $u['username'], $empid, $fy['id'], $ltid]);
                    }
                    if ($ins) {
                        set_flash('success', 'Leave request added.' . ($isapproved ? ' Balance updated.' : ''));
                        header('Location: index.php?module=empleaves'); exit;
                    }
                    set_flash('error', db_error() ?: 'Failed to add leave request.');
                } elseif ($action === 'edit' && $id) {
                    if (!empty($row['isapproved'])) {
                        set_flash('error', 'An approved leave cannot be edited or removed.');
                    } else {
                        shadow_log('empleaves', $id, $row, 'update');
                        // if previously approved, revert balance; then apply new
                        if (!empty($row['isapproved'])) {
                            $oldDays = leave_days($row['startfrom'], $row['endon'], $lt['incwe'] ?? 0, $lt['incholidays'] ?? 0);
                            q("UPDATE " . table('leavesbalances') . " SET usedbalance = usedbalance - ?, remainingbalance = remainingbalance + ?, lastedit=?, editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE empid=? AND fiscalyearid=? AND omitted=0",
                                [$oldDays, $oldDays, date('Y-m-d H:i:s'), $u['id'], $u['username'], $u['username_ar'] ?? $u['username'], $u['username_ar'] ?? $u['username'], $empid, $fy['id']]);
                        }
                        $sets = []; $params = [];
                        foreach ($data as $k => $v) { $sets[] = '`'.$k.'`=?'; $params[] = $v; }
                        $sets[] = '`lastedit`=?';   $params[] = $now;
                        $sets[] = '`editedbyid`=?'; $params[] = $u['id'];
                        $sets[] = '`editedby`=?';   $params[] = $u['username'];
                        $sets[] = '`editedbyname`=?';   $params[] = $an;
                        $sets[] = '`editedbynamear`=?'; $params[] = $an;
                        $params[] = $id;
                        $upd = q("UPDATE " . table('empleaves') . " SET " . implode(',', $sets) . " WHERE id=?", $params);
                        if ($upd && $isapproved) {
                            q("UPDATE " . table('leavesbalances') . " SET usedbalance = usedbalance + ?, remainingbalance = remainingbalance - ?, lastedit=?, editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE empid=? AND fiscalyearid=? AND leavetypeid=? AND omitted=0",
                                [$days, $days, date('Y-m-d H:i:s'), $u['id'], $u['username'], $u['username_ar'] ?? $u['username'], $u['username_ar'] ?? $u['username'], $empid, $fy['id'], $ltid]);
                        }
                        if ($upd) {
                            set_flash('success', 'Leave request updated.' . ($isapproved ? ' Balance updated.' : ''));
                            header('Location: index.php?module=empleaves'); exit;
                        }
                        set_flash('error', db_error() ?: 'Failed to update leave request.');
                    }
                }
            }
        }
    }
}

if ($action === 'del' && $id) {
    require_permission($module, 'del');
    $row = fetchRow("SELECT * FROM " . table('empleaves') . " WHERE id=? LIMIT 1", [$id]);
    if (isset($_GET['confirm']) && $_GET['confirm'] === '1') {
        if (!empty($row['isapproved'])) {
            set_flash('error', 'An approved leave cannot be edited or removed.');
        } else {
            // revert balance if it was approved (shouldn't be here, but safe)
            if ($row) shadow_log('empleaves', $id, $row, 'omit');
            q("UPDATE " . table('empleaves') . " SET omitted=1 WHERE id=?", [$id]);
            set_flash('success', 'Leave request omitted.');
        }
        header('Location: index.php?module=empleaves'); exit;
    }
    echo '<div class="alert alert-warning">
            <h4>Omit leave request?</h4>
            <p>Records are not deleted; they are marked omitted and hidden from reports.</p>
            <a href="index.php?module=empleaves&a=del&id='.$id.'&confirm=1" class="btn btn-danger">Yes, Omit</a>
            <a href="index.php?module=empleaves" class="btn btn-secondary">Cancel</a>
          </div>';
    return;
}

if ($action === 'add') {
    require_permission($module, 'add');
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-plus-lg me-2"></i>'.($isAr ? 'إضافة طلب إجازة' : 'Add Leave Request').'</h2>
            <a href="index.php?module=empleaves" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
          </div>';
    echo '<form method="post" action="index.php?module=empleaves&a=add">';
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'الموظف' : 'Employee').'</label>'
        .'<select name="empid" class="form-select required" onchange="updateSuperior(this.value)"><option value="">-- Select --</option>';
    foreach ($employees as $e) {
        echo '<option value="'.$e['id'].'">'.htmlspecialchars($isAr ? $e['empfullnamear'] : $e['empfullname']).'</option>';
    }
    echo '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'نوع الإجازة' : 'Leave Type').'</label>'
        .'<select name="leavetypeid" class="form-select required"><option value="">-- Select --</option>';
    foreach ($leavetypes as $t) {
        echo '<option value="'.$t['id'].'">'.htmlspecialchars($isAr ? $t['leavetypenamear'] : $t['leavetypename']).'</option>';
    }
    echo '</select></div>';
    echo '<div class="row"><div class="col-md-6 mb-3"><label class="form-label">'.($isAr ? 'تاريخ البداية' : 'Start From').'</label>'
        .'<input type="date" name="startfrom" class="form-control required"></div>
        <div class="col-md-6 mb-3"><label class="form-label">'.($isAr ? 'تاريخ الانتهاء' : 'End On').'</label>'
        .'<input type="date" name="endon" class="form-control required"></div></div>';
    echo '<div class="mb-3"><div class="form-check"><input type="checkbox" name="isapproved" value="1" class="form-check-input" id="isapproved">'
        .'<label class="form-check-label" for="isapproved">'.($isAr ? 'موافق عليه (المدير / مسؤول الموارد البشرية)' : 'Approved (Superior / HR)').'</label></div></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'المدير المباشر / منتمد الإجازة' : 'Superior / Leave Approver').'</label>'
        .'<select name="empsuperiorid" class="form-select" id="empsuperiorid"><option value="">-- Select --</option></select></div>';
    echo '<div class="d-grid gap-2"><button class="btn btn-primary">Save</button>
          <a href="index.php?module=empleaves" class="btn btn-secondary">Cancel</a></div>';
    echo '</form>';
    echo '<script>
    function updateSuperior(empId){
      fetch("index.php?module=empleaves&a=superior&emp="+empId)
        .then(r=>r.text()).then(t=>{document.getElementById("empsuperiorid").innerHTML=t;});
    }
    </script>';
    return;
}

if ($action === 'superior' && !empty($_GET['emp'])) {
    $emp = (int)$_GET['emp'];
    $e = fetchRow("SELECT empsuperiorid, empsuperiorname, empsuperiornamear FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [$emp]);
    $html = '<option value="">-- Select --</option>';
    if ($e && $e['empsuperiorid']) {
        $sel = $isAr ? $e['empsuperiornamear'] : $e['empsuperiorname'];
        $html = '<option value="'.$e['empsuperiorid'].'" selected>'.htmlspecialchars($sel).'</option>';
    }
    ajax_output($html);
}

if ($action === 'edit' && $id && $row) {
    require_permission($module, 'edit');
    if (!empty($row['isapproved'])) {
        set_flash('error', 'An approved leave cannot be edited or removed.');
        header('Location: index.php?module=empleaves'); exit;
    }
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-pencil-square me-2"></i>'.($isAr ? 'تعديل طلب إجازة' : 'Edit Leave Request').'</h2>
            <a href="index.php?module=empleaves" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
          </div>';
    echo '<form method="post" action="index.php?module=empleaves&a=edit&id='.$id.'">';
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'الموظف' : 'Employee').'</label>'
        .'<select name="empid" class="form-select required"><option value="">-- Select --</option>';
    foreach ($employees as $e) {
        $sel = ($row['empid'] == $e['id']) ? ' selected' : '';
        echo '<option value="'.$e['id'].'"'.$sel.'>'.htmlspecialchars($isAr ? $e['empfullnamear'] : $e['empfullname']).'</option>';
    }
    echo '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'نوع الإجازة' : 'Leave Type').'</label>'
        .'<select name="leavetypeid" class="form-select required"><option value="">-- Select --</option>';
    foreach ($leavetypes as $t) {
        $sel = ($row['leavetypeid'] == $t['id']) ? ' selected' : '';
        echo '<option value="'.$t['id'].'"'.$sel.'>'.htmlspecialchars($isAr ? $t['leavetypenamear'] : $t['leavetypename']).'</option>';
    }
    echo '</select></div>';
    echo '<div class="row"><div class="col-md-6 mb-3"><label class="form-label">'.($isAr ? 'تاريخ البداية' : 'Start From').'</label>'
        .'<input type="date" name="startfrom" class="form-control required" value="'.htmlspecialchars($row['startfrom']).'"></div>
        <div class="col-md-6 mb-3"><label class="form-label">'.($isAr ? 'تاريخ الانتهاء' : 'End On').'</label>'
        .'<input type="date" name="endon" class="form-control required" value="'.htmlspecialchars($row['endon']).'"></div></div>';
    echo '<div class="mb-3"><div class="form-check"><input type="checkbox" name="isapproved" value="1" class="form-check-input" id="isapproved"'.(!empty($row['isapproved']) ? ' checked' : '').'>'
        .'<label class="form-check-label" for="isapproved">'.($isAr ? 'موافق عليه' : 'Approved').'</label></div></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'المدير المباشر' : 'Superior').'</label>'
        .'<select name="empsuperiorid" class="form-select"><option value="">-- Select --</option>';
    foreach ($employees as $e) {
        if ($e['id'] == $row['empid']) continue;
        $sel = ($row['empsuperiorid'] == $e['id']) ? ' selected' : '';
        echo '<option value="'.$e['id'].'"'.$sel.'>'.htmlspecialchars($isAr ? $e['empfullnamear'] : $e['empfullname']).'</option>';
    }
    echo '</select></div>';
    echo '<div class="d-grid gap-2"><button class="btn btn-primary">Update</button>
          <a href="index.php?module=empleaves" class="btn btn-secondary">Cancel</a></div>';
    echo '</form>';
    return;
}

// ---------- VIEW (details) ----------
if ($action === 'view' && $id) {
    require_permission($module, 'view');
    $row = fetchRow("SELECT * FROM " . table('empleaves') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
    if (!$row) { set_flash('error','Record not found.'); header('Location: index.php?module=empleaves'); exit; }
    ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-calendar-check me-2"></i><?= $isAr ? 'تفاصيل الإجازة' : 'Leave Details' ?></h2>
    <a href="index.php?module=empleaves" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'معلومات الإجازة' : 'Leave Information' ?></div>
            <div class="card-body">
                <p><strong><?= $isAr ? 'الموظف' : 'Employee' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['empfullnamear'] ?? '') : ($row['empfullname'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'نوع الإجازة' : 'Leave Type' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['leavetypenamear'] ?? '') : ($row['leavetypename'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'من' : 'From' ?>:</strong> <?= htmlspecialchars($row['startfrom'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'إلى' : 'To' ?>:</strong> <?= htmlspecialchars($row['endon'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'الرصيد قبل' : 'Balance Before' ?>:</strong> <?= (int)($row['balancebefore'] ?? 0) ?></p>
                <p><strong><?= $isAr ? 'الرصيد بعد' : 'Balance After' ?>:</strong> <?= (int)($row['balanceafter'] ?? 0) ?></p>
                <p><strong><?= $isAr ? 'الحالة' : 'Status' ?>:</strong> <?= !empty($row['isapproved']) ? ($isAr ? 'موافق' : 'Approved') : ($isAr ? 'قيد الانتظار' : 'Pending') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'الموافقة' : 'Approval' ?></div>
            <div class="card-body">
                <p><strong><?= $isAr ? 'المدير' : 'Superior' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['empsuperiornamear'] ?? '') : ($row['empsuperiorname'] ?? '')) ?></p>
                <?php if (!empty($row['rejectedby'])): ?>
                <p><strong><?= $isAr ? 'من رفض' : 'Rejected By' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['rejectedbynamear'] ?? '') : ($row['rejectedbyname'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'تاريخ الرفض' : 'Rejected At' ?>:</strong> <?= htmlspecialchars($row['rejectedat'] ?? '') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<div class="mb-3">
    <?php if ($canEdit && empty($row['isapproved'])): ?><a href="index.php?module=empleaves&a=edit&id=<?= $row['id'] ?>" class="btn btn-warning"><i class="bi bi-pencil me-1"></i><?= $isAr ? 'تعديل' : 'Edit' ?></a><?php endif; ?>
    <a href="index.php?module=empleaves" class="btn btn-secondary ms-2"><?= $isAr ? 'العودة' : 'Back' ?></a>
</div>
<?php
    return;
}

// LIST
require_permission($module, 'view');
$search = trim($_GET['search'] ?? '');
$where = "l.omitted = 0";
$params = [];
if ($search !== '') {
    $norm = arabic_normalize($search);
    $where .= " AND (l.empfullname LIKE ? OR l.empfullnamear LIKE ? OR l.leavetypename LIKE ? OR l.leavetypenamear LIKE ?)";
    $params = ['%'.$search.'%', '%'.$norm.'%', '%'.$search.'%', '%'.$norm.'%'];
}
$rows = fetchAll("SELECT l.*, e.empfullname, e.empfullnamear
    FROM " . table('empleaves') . " l
    LEFT JOIN " . table('employeesmain') . " e ON e.id = l.empid
    WHERE $where ORDER BY l.id DESC", $params);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-calendar-check me-2"></i><?= $isAr ? 'إجازات الموظفين' : 'Employee Leaves' ?></h2>
    <div>
        <?php if ($canAdd): ?><a href="index.php?module=empleaves&a=add" class="btn btn-primary me-1"><i class="bi bi-plus me-1"></i><?= $isAr ? 'إضافة' : 'Add' ?></a><?php endif; ?>
        <a href="index.php?module=empleaves&a=export&fmt=csv" class="btn btn-outline-secondary btn-sm me-1">CSV</a>
        <a href="index.php?module=empleaves&a=export&fmt=xlsx" class="btn btn-outline-success btn-sm me-1">XLSX</a>
        <a href="index.php?module=empleaves&a=export&fmt=docx" class="btn btn-outline-primary btn-sm me-1">Word</a>
        <a href="index.php?module=empleaves&a=export&fmt=pdf" class="btn btn-outline-danger btn-sm me-1">PDF</a>
    </div>
</div>
<form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="module" value="empleaves">
    <div class="col-md-4"><input type="search" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Search (Arabic/English)..."></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
    <div class="col-md-2"><a href="index.php?module=empleaves" class="btn btn-outline-secondary w-100">Reset</a></div>
</form>
<div class="table-responsive">
<table class="table table-striped table-hover align-middle table-sm">
<thead class="table-dark"><tr>
<th>#</th><th><?= $isAr ? 'الموظف' : 'Employee' ?></th>
<th><?= $isAr ? 'نوع الإجازة' : 'Leave Type' ?></th>
<th><?= $isAr ? 'من' : 'From' ?></th>
<th><?= $isAr ? 'إلى' : 'To' ?></th>
<th><?= $isAr ? 'الرصيد قبل' : 'Balance Before' ?></th>
<th><?= $isAr ? 'الرصيد بعد' : 'Balance After' ?></th>
<th><?= $isAr ? 'الحالة' : 'Status' ?></th>
<th><?= $isAr ? 'المدير' : 'Superior' ?></th>
<th><?= $isAr ? 'من رفض' : 'Rejected By' ?></th>
<th><?= $isAr ? 'تاريخ الرفض' : 'Rejected At' ?></th>
<th>Actions</th>
</tr></thead>
<tbody>
<?php foreach ($rows as $i => $r): ?>
<tr>
<td><?= $i+1 ?></td>
<td><?= htmlspecialchars($isAr ? ($r['empfullnamear'] ?? '') : ($r['empfullname'] ?? '')) ?></td>
<td><?= htmlspecialchars($isAr ? ($r['leavetypenamear'] ?? '') : ($r['leavetypename'] ?? '')) ?></td>
<td><?= htmlspecialchars($r['startfrom'] ?? '') ?></td>
<td><?= htmlspecialchars($r['endon'] ?? '') ?></td>
<td><?= (int)($r['balancebefore'] ?? 0) ?></td>
<td><?= (int)($r['balanceafter'] ?? 0) ?></td>
<td><?= !empty($r['isapproved']) ? ($isAr ? 'موافق' : 'Approved') : ($isAr ? 'غير موافق' : 'Pending') ?></td>
<td><?= htmlspecialchars($isAr ? ($r['empsuperiornamear'] ?? '') : ($r['empsuperiorname'] ?? '')) ?></td>
    <td><?= htmlspecialchars($isAr ? ($r['rejectedbynamear'] ?? '') : ($r['rejectedbyname'] ?? '')) ?></td>
    <td><?= htmlspecialchars($r['rejectedat'] ?? '') ?></td>
<td class="d-flex gap-1">
    <a href="index.php?module=empleaves&a=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
    <?php if ($canEdit && empty($r['isapproved'])): ?><a href="index.php?module=empleaves&a=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a><?php endif; ?>
    <?php if ($canDel && empty($r['isapproved'])): ?><a href="index.php?module=empleaves&a=del&id=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Omit this leave?')"><i class="bi bi-trash"></i></a><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
