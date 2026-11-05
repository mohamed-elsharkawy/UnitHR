<?php
/**
 * DepStructure - custom module (hierarchy, type, manager name resolution)
 */
require_login();
require_once __DIR__ . '/../../includes/helpers.php';
$module = 'depstructure';
$isAr = lang() === 'ar';
$u = current_user();
$canAdd = user_can($module, 'add');
$canEdit = user_can($module, 'edit');
$canDel = user_can($module, 'del');

$deptypes    = fetchAll("SELECT * FROM " . table('deptypes') . " WHERE omitted=0 ORDER BY deptypename ASC");
$employees   = fetchAll("SELECT * FROM " . table('employeesmain') . " WHERE omitted=0 ORDER BY empfullname ASC");
$departments = fetchAll("SELECT * FROM " . table('depstructure') . " WHERE omitted=0 ORDER BY depname ASC");

$action = $_GET['a'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$row = $id ? fetchRow("SELECT * FROM " . table('depstructure') . " WHERE id=? LIMIT 1", [$id]) : null;

// ---------- EXPORT ----------
if ($action === 'export') {
    require_permission($module, 'view');
    $fmt = $_GET['fmt'] ?? 'csv';
    $search = trim($_GET['search'] ?? '');
    $where = "omitted = 0";
    $params = [];
    if ($search !== '') {
        $norm = arabic_normalize($search);
        $where .= " AND (depname LIKE ? OR depnamear LIKE ?)";
        $params = ['%'.$search.'%', '%'.$norm.'%'];
    }
    $rows = fetchAll("SELECT depname AS 'Department Name', depnamear AS 'Department Name (Arabic)',
                             depparentname AS 'Parent Department', depparentnamear AS 'Parent Department (Arabic)',
                             deptype AS 'Type', deptypear AS 'Type (Arabic)',
                             managername AS 'Manager', managernamear AS 'Manager (Arabic)'
                      FROM " . table('depstructure') . " WHERE $where ORDER BY depname", $params);
    if ($rows === false) { set_flash('error', 'Database not available.'); header('Location: index.php?module=home'); exit; }
    if (empty($rows)) { set_flash('error', 'No data to export.'); header('Location: index.php?module=depstructure'); exit; }
    $headers = array_keys($rows[0]);
    $dataRows = [];
    foreach ($rows as $r) { $dataRows[] = array_values($r); }
    do_export($fmt, 'depstructure_export', $isAr ? 'تقرير هيكلة الأقسام' : 'Department Structure Report', $headers, $dataRows);
    exit;
}

$optSelect = function($optionsTable, $valueCol, $labelCol, $selected = null, $allowEmpty = true) use ($isAr) {
    $rows = fetchAll("SELECT $valueCol, $labelCol FROM " . table($optionsTable) . " WHERE omitted=0 ORDER BY $labelCol ASC");
    $out = '';
    if ($allowEmpty) $out .= '<option value="">-- ' . ($isAr ? 'اختر' : 'Select') . ' --</option>';
    foreach ($rows as $r) {
        $sel = ($selected != '' && $r[$valueCol] == $selected) ? ' selected' : '';
        $out .= '<option value="' . htmlspecialchars($r[$valueCol]) . '"' . $sel . '>'
            . htmlspecialchars($r[$labelCol], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
    }
    return $out;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    require_permission($module, $action === 'add' ? 'add' : 'edit');
    $data = [
        'depname'        => $_POST['depname'] ?? '',
        'depnamear'      => $_POST['depnamear'] ?? '',
        'depparentid'    => ($_POST['depparentid'] ?? '') ?: null,
        'deptypeid'      => ($_POST['deptypeid'] ?? '') ?: null,
        'managerid'      => ($_POST['managerid'] ?? '') ?: null,
    ];
    if ($data['depparentid']) {
        $p = fetchRow("SELECT depname, depnamear FROM " . table('depstructure') . " WHERE id=?", [$data['depparentid']]);
        $data['depparentname']    = $p['depname'] ?? '';
        $data['depparentnamear']  = $p['depnamear'] ?? '';
    } else { $data['depparentname'] = ''; $data['depparentnamear'] = ''; }
    if ($data['deptypeid']) {
        $t = fetchRow("SELECT deptypename, deptypenamear FROM " . table('deptypes') . " WHERE id=?", [$data['deptypeid']]);
        $data['deptype']  = $t['deptypename'] ?? '';
        $data['deptypear']= $t['deptypenamear'] ?? '';
    } else { $data['deptype'] = ''; $data['deptypear'] = ''; }
    if ($data['managerid']) {
        $m = fetchRow("SELECT empfullname, empfullnamear FROM " . table('employeesmain') . " WHERE id=?", [$data['managerid']]);
        $data['managername']    = $m['empfullname'] ?? '';
        $data['managernamear']  = $m['empfullnamear'] ?? '';
    } else { $data['managername'] = ''; $data['managernamear'] = ''; }

    if (trim($data['depname']) === '' || trim($data['depnamear']) === '') {
        set_flash('error', 'Department name (Arabic & English) is required.');
    } else {
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
            q("INSERT INTO " . table('depstructure') . " (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")", $params);
            set_flash('success', 'Department added.');
            header('Location: index.php?module=depstructure'); exit;
        } elseif ($action === 'edit' && $id) {
            shadow_log('depstructure', $id, $row, 'update');
            $sets = []; $params = [];
            foreach ($data as $k => $v) { $sets[] = '`'.$k.'`=?'; $params[] = $v; }
            $sets[] = '`lastedit`=?';   $params[] = $now;
            $sets[] = '`editedbyid`=?'; $params[] = $u['id'];
            $sets[] = '`editedby`=?';   $params[] = $u['username'];
            $sets[] = '`editedbyname`=?';   $params[] = $an;
            $sets[] = '`editedbynamear`=?'; $params[] = $an;
            $params[] = $id;
            q("UPDATE " . table('depstructure') . " SET " . implode(',', $sets) . " WHERE id=?", $params);
            set_flash('success', 'Department updated.');
            header('Location: index.php?module=depstructure'); exit;
        }
    }
}

if ($action === 'del' && $id) {
    require_permission($module, 'del');
    $row = fetchRow("SELECT * FROM " . table('depstructure') . " WHERE id=? LIMIT 1", [$id]);
    if (isset($_GET['confirm']) && $_GET['confirm'] === '1') {
        $used = fetchRow("SELECT COUNT(*) c FROM " . table('employeesmain') . " WHERE depid=? AND omitted=0", [$id]);
        if ((int)$used['c'] > 0) {
            set_flash('error', 'Cannot omit: this department is used by employees.');
        } else {
            if ($row) shadow_log('depstructure', $id, $row, 'omit');
            q("UPDATE " . table('depstructure') . " SET omitted=1 WHERE id=?", [$id]);
            set_flash('success', 'Department omitted.');
        }
        header('Location: index.php?module=depstructure'); exit;
    }
    echo '<div class="alert alert-warning">
            <h4>Omit department?</h4>
            <p>Records are not deleted; they are marked omitted and hidden from reports.</p>
            <a href="index.php?module=depstructure&a=del&id='.$id.'&confirm=1" class="btn btn-danger">Yes, Omit</a>
            <a href="index.php?module=depstructure" class="btn btn-secondary">Cancel</a>
          </div>';
    return;
}

if ($action === 'add') {
    require_permission($module, 'add');
    $d = ['depname'=>'','depnamear'=>'','depparentid'=>'','deptypeid'=>'','managerid'=>''];
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-plus-lg me-2"></i>'.($isAr?'إضافة قسم':'Add Department').'</h2>
            <a href="index.php?module=depstructure" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
          </div>';
    echo '<form method="post" action="index.php?module=depstructure&a=add">';
    echo field_input(['name'=>'depname','label'=>'Department Name','label_ar'=>'اسم القسم','type'=>'text','required'=>true], $d['depname']);
    echo field_input(['name'=>'depnamear','label'=>'Department Name (Arabic)','label_ar'=>'اسم القسم بالعربية','type'=>'text','required'=>true], $d['depnamear']);
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'القسم الأصلي':'Parent Department').'</label>'
        .'<select name="depparentid" class="form-select"><option value="">-- ' . ($isAr?'لا يوجد':'None') . ' --</option>'
        . $optSelect('depstructure','id','depname' . ($isAr?'ar':''), '', false) . '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'نوع القسم':'Department Type').'</label>'
        .'<select name="deptypeid" class="form-select"><option value="">-- ' . ($isAr?'اختر':'Select') . ' --</option>';
    foreach ($deptypes as $t) {
        $label = $isAr ? $t['deptypenamear'] : $t['deptypename'];
        echo '<option value="'.$t['id'].'">'.htmlspecialchars($label).'</option>';
    }
    echo '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'المدير':'Manager').'</label>'
        .'<select name="managerid" class="form-select"><option value="">-- ' . ($isAr?'اختر':'Select') . ' --</option>';
    foreach ($employees as $e) {
        $label = $isAr ? $e['empfullnamear'] : $e['empfullname'];
        echo '<option value="'.$e['id'].'">'.htmlspecialchars($label).'</option>';
    }
    echo '</select></div>';
    echo '<div class="d-grid gap-2"><button class="btn btn-primary">Save</button>
          <a href="index.php?module=depstructure" class="btn btn-secondary">Cancel</a></div>';
    echo '</form>';
    return;
}

if ($action === 'edit' && $id && $row) {
    require_permission($module, 'edit');
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-pencil-square me-2"></i>'.($isAr?'تعديل قسم':'Edit Department').'</h2>
            <a href="index.php?module=depstructure" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
          </div>';
    echo '<form method="post" action="index.php?module=depstructure&a=edit&id='.$id.'">';
    echo field_input(['name'=>'depname','label'=>'Department Name','label_ar'=>'اسم القسم','type'=>'text','required'=>true], $row['depname']);
    echo field_input(['name'=>'depnamear','label'=>'Department Name (Arabic)','label_ar'=>'اسم القسم بالعربية','type'=>'text','required'=>true], $row['depnamear']);
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'القسم الأصلي':'Parent Department').'</label>'
        .'<select name="depparentid" class="form-select"><option value="">-- ' . ($isAr?'لا يوجد':'None') . ' --</option>';
    foreach ($departments as $d) {
        if ($d['id'] == $id) continue;
        $sel = ($row['depparentid'] == $d['id']) ? ' selected' : '';
        $label = $isAr ? $d['depnamear'] : $d['depname'];
        echo '<option value="'.$d['id'].'"'.$sel.'>'.htmlspecialchars($label).'</option>';
    }
    echo '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'نوع القسم':'Department Type').'</label>'
        .'<select name="deptypeid" class="form-select"><option value="">-- ' . ($isAr?'اختر':'Select') . ' --</option>';
    foreach ($deptypes as $t) {
        $sel = ($row['deptypeid'] == $t['id']) ? ' selected' : '';
        $label = $isAr ? $t['deptypenamear'] : $t['deptypename'];
        echo '<option value="'.$t['id'].'"'.$sel.'>'.htmlspecialchars($label).'</option>';
    }
    echo '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'المدير':'Manager').'</label>'
        .'<select name="managerid" class="form-select"><option value="">-- ' . ($isAr?'اختر':'Select') . ' --</option>';
    foreach ($employees as $e) {
        $sel = ($row['managerid'] == $e['id']) ? ' selected' : '';
        $label = $isAr ? $e['empfullnamear'] : $e['empfullname'];
        echo '<option value="'.$e['id'].'"'.$sel.'>'.htmlspecialchars($label).'</option>';
    }
    echo '</select></div>';
    echo '<div class="d-grid gap-2"><button class="btn btn-primary">Update</button>
          <a href="index.php?module=depstructure" class="btn btn-secondary">Cancel</a></div>';
    echo '</form>';
    return;
}

// ---------- VIEW (details + hierarchy) ----------
if ($action === 'view' && $id) {
    require_permission($module, 'view');
    $row = fetchRow("SELECT * FROM " . table('depstructure') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
    if (!$row) { set_flash('error','Record not found.'); header('Location: index.php?module=depstructure'); exit; }
    // children
    $children = fetchAll("SELECT id, depname, depnamear, deptype, managername FROM " . table('depstructure') . " WHERE depparentid=? AND omitted=0 ORDER BY depname", [$id]);
    ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-diagram-3 me-2"></i><?= $isAr ? 'تفاصيل القسم' : 'Department Details' ?></h2>
    <a href="index.php?module=depstructure" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'معلومات القسم' : 'Department Information' ?></div>
            <div class="card-body">
                <p><strong><?= $isAr ? 'اسم القسم' : 'Department Name' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['depnamear'] ?? '') : ($row['depname'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'القسم الأصلي' : 'Parent' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['depparentnamear'] ?? '') : ($row['depparentname'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'نوع القسم' : 'Type' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['deptypear'] ?? '') : ($row['deptype'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'المدير' : 'Manager' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['managernamear'] ?? '') : ($row['managername'] ?? '')) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'الأقسام الفرعية' : 'Sub-departments' ?></div>
            <div class="card-body">
                <?php if (count($children) === 0): ?>
                <p class="text-muted"><?= $isAr ? 'لا توجد أقسام فرعية' : 'No sub-departments' ?></p>
                <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($children as $ch): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= htmlspecialchars($isAr ? $ch['depnamear'] : $ch['depname']) ?></span>
                        <a href="index.php?module=depstructure&a=view&id=<?= $ch['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<div class="mb-3">
    <?php if ($canEdit): ?><a href="index.php?module=depstructure&a=edit&id=<?= $row['id'] ?>" class="btn btn-warning"><i class="bi bi-pencil me-1"></i><?= $isAr ? 'تعديل' : 'Edit' ?></a><?php endif; ?>
    <a href="index.php?module=depstructure" class="btn btn-secondary ms-2"><?= $isAr ? 'العودة' : 'Back' ?></a>
</div>
<?php
    // footer included by index.php

    return;
}

// LIST
require_permission($module, 'view');
$search = trim($_GET['search'] ?? '');
$where = "omitted = 0";
$params = [];
if ($search !== '') {
    $norm = arabic_normalize($search);
    $where .= " AND (depname LIKE ? OR depnamear LIKE ?)";
    $params = ['%'.$search.'%', '%'.$norm.'%'];
}
$rows = fetchAll("SELECT * FROM " . table('depstructure') . " WHERE $where ORDER BY depname ASC", $params);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-diagram-3 me-2"></i><?= $isAr ? 'هيكلة الأقسام' : 'Department Structure' ?></h2>
    <div>
        <?php if ($canAdd): ?><a href="index.php?module=depstructure&a=add" class="btn btn-primary me-1"><i class="bi bi-plus me-1"></i><?= $isAr ? 'إضافة' : 'Add' ?></a><?php endif; ?>
        <a href="index.php?module=depstructure&a=export&fmt=csv" class="btn btn-outline-secondary btn-sm me-1">CSV</a>
        <a href="index.php?module=depstructure&a=export&fmt=xlsx" class="btn btn-outline-success btn-sm me-1">XLSX</a>
        <a href="index.php?module=depstructure&a=export&fmt=docx" class="btn btn-outline-primary btn-sm me-1">Word</a>
        <a href="index.php?module=depstructure&a=export&fmt=pdf" class="btn btn-outline-danger btn-sm me-1">PDF</a>
    </div>
</div>
<form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="module" value="depstructure">
    <div class="col-md-4"><input type="search" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Search (Arabic/English)..."></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
    <div class="col-md-2"><a href="index.php?module=depstructure" class="btn btn-outline-secondary w-100">Reset</a></div>
</form>
<div class="table-responsive">
<table class="table table-striped table-hover align-middle">
<thead class="table-dark"><tr>
<th>#</th><th><?= $isAr ? 'اسم القسم' : 'Department Name' ?></th>
<th><?= $isAr ? 'القسم الأصلي' : 'Parent' ?></th>
<th><?= $isAr ? 'نوع القسم' : 'Type' ?></th>
<th><?= $isAr ? 'المدير' : 'Manager' ?></th>
<th>Actions</th>
</tr></thead>
<tbody>
<?php foreach ($rows as $i => $r): ?>
<tr>
<td><?= $i+1 ?></td>
<td><?= htmlspecialchars($isAr ? ($r['depnamear'] ?? '') : ($r['depname'] ?? '')) ?></td>
<td><?= htmlspecialchars($isAr ? ($r['depparentnamear'] ?? '') : ($r['depparentname'] ?? '')) ?></td>
<td><?= htmlspecialchars($isAr ? ($r['deptypear'] ?? '') : ($r['deptype'] ?? '')) ?></td>
<td><?= htmlspecialchars($isAr ? ($r['managernamear'] ?? '') : ($r['managername'] ?? '')) ?></td>
<td class="d-flex gap-1">
    <a href="index.php?module=depstructure&a=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
    <?php if ($canEdit): ?><a href="index.php?module=depstructure&a=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a><?php endif; ?>
    <?php if ($canDel): ?><a href="index.php?module=depstructure&a=del&id=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Omit this department?')"><i class="bi bi-trash"></i></a><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
