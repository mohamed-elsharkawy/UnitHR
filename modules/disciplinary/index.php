<?php
// UnitHR - Employee Disciplinary Actions module
require_login();
require_once __DIR__ . '/../../includes/helpers.php';
$isAr = lang() === 'ar';
$module = 'disciplinary';
require_permission($module, 'view');
$a = $_GET['a'] ?? 'cases';
$canEdit = user_can($module, 'edit');

function da_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- handle form posts ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Every write (categories, severities, deductions, penalties, violations,
    // cases, actions, appeals) requires the module 'edit' capability.
    require_permission($module, 'edit');
    // Categories
    if ($a === 'categories_save') {
        $id = (int)($_POST['id'] ?? 0);
        $nameEn = trim($_POST['name_en'] ?? '');
        $nameAr = trim($_POST['name_ar'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $descAr = trim($_POST['description_ar'] ?? '');
        if ($nameEn === '') { set_flash('error', $isAr ? 'الاسم (إنجليزي) مطلوب.' : 'English name is required.'); header('Location: ?module=disciplinary&a=categories'); exit; }
        if ($id > 0) {
            q("UPDATE " . table('offense_categories') . " SET name_en=?, name_ar=?, description=?, description_ar=?, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?",
              [$nameEn, $nameAr, $desc, $descAr, (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '', $id]);
        } else {
            q("INSERT INTO " . table('offense_categories') . " (rguid,name_en,name_ar,description,description_ar,addedbyid,addedby,addedbyname,addedbynamear) VALUES (?,?,?,?,?,?,?,?,?)",
              [bin2hex(random_bytes(16)), $nameEn, $nameAr, $desc, $descAr, (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '']);
        }
        set_flash('success', $isAr ? 'تم الحفظ.' : 'Saved.');
        header('Location: ?module=disciplinary&a=categories'); exit;
    }
    // Severity
    if ($a === 'severity_save') {
        $id = (int)($_POST['id'] ?? 0);
        $lvl = trim($_POST['severity_level'] ?? '');
        $lvlAr = trim($_POST['severity_level_ar'] ?? '');
        if ($lvl === '') { set_flash('error', $isAr ? 'المستوى مطلوب.' : 'Level is required.'); header('Location: ?module=disciplinary&a=severity'); exit; }
        if ($id > 0) {
            q("UPDATE " . table('disciplinary_violations_severity') . " SET severity_level=?, severity_level_ar=?, description=?, description_ar=?, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?",
              [$lvl, $lvlAr, trim($_POST['description'] ?? ''), trim($_POST['description_ar'] ?? ''), (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '', $id]);
        } else {
            q("INSERT INTO " . table('disciplinary_violations_severity') . " (rguid,severity_level,severity_level_ar,description,description_ar,addedbyid,addedby,addedbyname,addedbynamear) VALUES (?,?,?,?,?,?,?,?,?)",
              [bin2hex(random_bytes(16)), $lvl, $lvlAr, trim($_POST['description'] ?? ''), trim($_POST['description_ar'] ?? ''), (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '']);
        }
        set_flash('success', $isAr ? 'تم الحفظ.' : 'Saved.');
        header('Location: ?module=disciplinary&a=severity'); exit;
    }
    // Deduction types
    if ($a === 'deduction_types_save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['deduction_type_name'] ?? '');
        $nameAr = trim($_POST['deduction_type_name_ar'] ?? '');
        $maxDays = trim($_POST['max_deduction_days'] ?? '');
        $legal = !empty($_POST['requires_legal_approval']) ? 1 : 0;
        if ($name === '') { set_flash('error', $isAr ? 'الاسم مطلوب.' : 'Name is required.'); header('Location: ?module=disciplinary&a=deduction_types'); exit; }
        if ($id > 0) {
            q("UPDATE " . table('deduction_types') . " SET deduction_type_name=?, deduction_type_name_ar=?, max_deduction_days=?, requires_legal_approval=?, description=?, description_ar=?, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?",
              [$name, $nameAr, $maxDays, $legal, trim($_POST['description'] ?? ''), trim($_POST['description_ar'] ?? ''), (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '', $id]);
        } else {
            q("INSERT INTO " . table('deduction_types') . " (rguid,deduction_type_name,deduction_type_name_ar,max_deduction_days,requires_legal_approval,description,description_ar,addedbyid,addedby,addedbyname,addedbynamear) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
              [bin2hex(random_bytes(16)), $name, $nameAr, $maxDays, $legal, trim($_POST['description'] ?? ''), trim($_POST['description_ar'] ?? ''), (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '']);
        }
        set_flash('success', $isAr ? 'تم الحفظ.' : 'Saved.');
        header('Location: ?module=disciplinary&a=deduction_types'); exit;
    }
    // Cases status
    if ($a === 'statuses_save') {
        $id = (int)($_POST['id'] ?? 0);
        $st = trim($_POST['case_status'] ?? '');
        $stAr = trim($_POST['case_status_ar'] ?? '');
        if ($st === '') { set_flash('error', $isAr ? 'الحالة مطلوبة.' : 'Status is required.'); header('Location: ?module=disciplinary&a=statuses'); exit; }
        if ($id > 0) {
            q("UPDATE " . table('disciplinary_cases_status') . " SET case_status=?, case_status_ar=?, description=?, description_ar=?, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?",
              [$st, $stAr, trim($_POST['description'] ?? ''), trim($_POST['description_ar'] ?? ''), (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '', $id]);
        } else {
            q("INSERT INTO " . table('disciplinary_cases_status') . " (rguid,case_status,case_status_ar,description,description_ar,addedbyid,addedby,addedbyname,addedbynamear) VALUES (?,?,?,?,?,?,?,?,?)",
              [bin2hex(random_bytes(16)), $st, $stAr, trim($_POST['description'] ?? ''), trim($_POST['description_ar'] ?? ''), (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '']);
        }
        set_flash('success', $isAr ? 'تم الحفظ.' : 'Saved.');
        header('Location: ?module=disciplinary&a=statuses'); exit;
    }
    // Violations
    if ($a === 'violations_save') {
        $id = (int)($_POST['id'] ?? 0);
        $titleEn = trim($_POST['title_en'] ?? '');
        $titleAr = trim($_POST['title_ar'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $severityId = (int)($_POST['severity_id'] ?? 0);
        $defaultPenalty = (int)($_POST['default_penalty_id'] ?? 0);
        if ($titleEn === '') { set_flash('error', $isAr ? 'العنوان (إنجليزي) مطلوب.' : 'English title is required.'); header('Location: ?module=disciplinary&a=violations'); exit; }
        // resolve names
        $cat = $categoryId ? fetchRow("SELECT name_en,name_ar FROM " . table('offense_categories') . " WHERE id=? AND omitted=0", [$categoryId]) : null;
        $sev = $severityId ? fetchRow("SELECT severity_level,severity_level_ar FROM " . table('disciplinary_violations_severity') . " WHERE id=? AND omitted=0", [$severityId]) : null;
        $catName = $cat ? $cat['name_en'] : null;
        $catNameAr = $cat ? $cat['name_ar'] : null;
        $sevName = $sev ? $sev['severity_level'] : null;
        $sevNameAr = $sev ? $sev['severity_level_ar'] : null;
        $dp = $defaultPenalty > 0 ? $defaultPenalty : null;
        if ($id > 0) {
            q("UPDATE " . table('disciplinary_violations') . " SET title_en=?, title_ar=?, category_id=?, category=?, category_ar=?, severity_id=?, severity_level=?, severity_level_ar=?, default_penalty_id=?, description=?, description_ar=?, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?",
              [$titleEn, $titleAr, $categoryId ?: null, $catName, $catNameAr, $severityId ?: null, $sevName, $sevNameAr, $dp, trim($_POST['description'] ?? ''), trim($_POST['description_ar'] ?? ''), (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '', $id]);
        } else {
            q("INSERT INTO " . table('disciplinary_violations') . " (rguid,title_en,title_ar,category_id,category,category_ar,severity_id,severity_level,severity_level_ar,default_penalty_id,description,description_ar,addedbyid,addedby,addedbyname,addedbynamear) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
              [bin2hex(random_bytes(16)), $titleEn, $titleAr, $categoryId ?: null, $catName, $catNameAr, $severityId ?: null, $sevName, $sevNameAr, $dp, trim($_POST['description'] ?? ''), trim($_POST['description_ar'] ?? ''), (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '']);
        }
        set_flash('success', $isAr ? 'تم الحفظ.' : 'Saved.');
        header('Location: ?module=disciplinary&a=violations'); exit;
    }
    // Penalties
    if ($a === 'penalties_save') {
        $id = (int)($_POST['id'] ?? 0);
        $nameEn = trim($_POST['name_en'] ?? '');
        $nameAr = trim($_POST['name_ar'] ?? '');
        $dedType = trim($_POST['deduction_type'] ?? '');
        $dedTypeId = (int)($_POST['deduction_type_id'] ?? 0);
        $maxDays = (int)($_POST['max_deduction_days'] ?? 0);
        $legal = !empty($_POST['requires_legal_approval']) ? 1 : 0;
        if ($nameEn === '') { set_flash('error', $isAr ? 'الاسم (إنجليزي) مطلوب.' : 'English name is required.'); header('Location: ?module=disciplinary&a=penalties'); exit; }
        // resolve deduction type name
        $dt = $dedTypeId ? fetchRow("SELECT deduction_type_name FROM " . table('deduction_types') . " WHERE id=? AND omitted=0", [$dedTypeId]) : null;
        $dtName = $dt ? $dt['deduction_type_name'] : $dedType;
        $empId = (int)($_POST['employee_id'] ?? 0);
        $emp = $empId ? fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? AND omitted=0", [$empId]) : null;
        $fields = [
            'name_en' => $nameEn, 'name_ar' => $nameAr,
            'employee_id' => $emp ? (int)$emp['id'] : null,
            'empfullname' => $emp ? $emp['empfullname'] : null,
            'empfullnamear' => $emp ? $emp['empfullnamear'] : null,
            'nid' => $emp ? $emp['nid'] : null,
            'depid' => $emp ? (int)$emp['depid'] : null,
            'depname' => $emp ? $emp['depname'] : null,
            'depnamear' => $emp ? $emp['depnamear'] : null,
            'currentdegreeid' => $emp ? (int)$emp['currentdegreeid'] : null,
            'currentdegreename' => $emp ? $emp['currentdegreename'] : null,
            'currentdegreenamear' => $emp ? $emp['currentdegreenamear'] : null,
            'deduction_type_id' => $dedTypeId ?: null,
            'deduction_type' => $dtName ?: null,
            'max_deduction_days' => $maxDays,
            'requires_legal_approval' => $legal,
            'description' => trim($_POST['description'] ?? ''),
            'description_ar' => trim($_POST['description_ar'] ?? ''),
        ];
        if ($id > 0) {
            $sets = []; $vals = [];
            foreach ($fields as $k => $v) { $sets[] = "$k=?"; $vals[] = $v; }
            $sets[] = "lastedit=NOW()"; $sets[] = "editedbyid=?"; $vals[] = (int)(current_user()['id'] ?? 0);
            $sets[] = "editedby=?"; $vals[] = current_user()['username'] ?? '';
            $sets[] = "editedbyname=?"; $vals[] = current_user()['username'] ?? '';
            $sets[] = "editedbynamear=?"; $vals[] = current_user()['username'] ?? '';
            $vals[] = $id;
            q("UPDATE " . table('disciplinary_penalties') . " SET " . implode(',', $sets) . " WHERE id=?", $vals);
        } else {
            $cols = array_keys($fields); $cols[] = 'rguid'; $cols[] = 'addedbyid'; $cols[] = 'addedby'; $cols[] = 'addedbyname'; $cols[] = 'addedbynamear';
            $vals = array_values($fields); $vals[] = bin2hex(random_bytes(16)); $vals[] = (int)(current_user()['id'] ?? 0); $vals[] = current_user()['username'] ?? ''; $vals[] = current_user()['username'] ?? ''; $vals[] = current_user()['username'] ?? '';
            $ph = implode(',', array_fill(0, count($vals), '?'));
            q("INSERT INTO " . table('disciplinary_penalties') . " (" . implode(',', $cols) . ") VALUES ($ph)", $vals);
        }
        set_flash('success', $isAr ? 'تم الحفظ.' : 'Saved.');
        header('Location: ?module=disciplinary&a=penalties'); exit;
    }
    // Cases
    if ($a === 'cases_save') {
        $id = (int)($_POST['id'] ?? 0);
        $empId = (int)($_POST['employee_id'] ?? 0);
        $violationId = (int)($_POST['violation_id'] ?? 0);
        $incidentDate = trim($_POST['incident_date'] ?? '');
        $status = trim($_POST['status'] ?? 'Logged');
        $notes = trim($_POST['investigation_notes'] ?? '');
        if ($empId <= 0) { set_flash('error', $isAr ? 'الموظف مطلوب.' : 'Employee is required.'); header('Location: ?module=disciplinary&a=cases'); exit; }
        // resolve violation title
        $viol = $violationId ? fetchRow("SELECT title_en,title_ar FROM " . table('disciplinary_violations') . " WHERE id=? AND omitted=0", [$violationId]) : null;
        $vTitle = $viol ? $viol['title_en'] : trim($_POST['violation_title'] ?? '');
        $vTitleAr = $viol ? $viol['title_ar'] : trim($_POST['violation_title_ar'] ?? '');
        if ($incidentDate === '') $incidentDate = date('Y-m-d H:i:s');
        $emp = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? AND omitted=0", [$empId]);
        if (!$emp) { set_flash('error', $isAr ? 'موظف غير موجود.' : 'Employee not found.'); header('Location: ?module=disciplinary&a=cases'); exit; }
        if ($id > 0) {
            q("UPDATE " . table('disciplinary_cases') . " SET employee_id=?, violation_id=?, violation_title=?, violation_title_ar=?, incident_date=?, status=?, investigation_notes=?, updated_at=NOW(), lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?",
              [$empId, $violationId ?: null, $vTitle, $vTitleAr, $incidentDate, $status, $notes, (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '', $id]);
        } else {
            q("INSERT INTO " . table('disciplinary_cases') . " (rguid,employee_id,violation_id,violation_title,violation_title_ar,incident_date,reported_by,status,investigation_notes,addedbyid,addedby,addedbyname,addedbynamear) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
              [bin2hex(random_bytes(16)), $empId, $violationId ?: null, $vTitle, $vTitleAr, $incidentDate, (int)(current_user()['id'] ?? 0), $status, $notes, (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '']);
        }
        set_flash('success', $isAr ? 'تم الحفظ.' : 'Saved.');
        header('Location: ?module=disciplinary&a=cases'); exit;
    }
    // Actions (applied sanctions)
    if ($a === 'actions_save') {
        $id = (int)($_POST['id'] ?? 0);
        $caseId = (int)($_POST['case_id'] ?? 0);
        $penaltyId = (int)($_POST['penalty_id'] ?? 0);
        $effDate = trim($_POST['effective_date'] ?? '');
        $dedAmt = trim($_POST['deduction_amount'] ?? '0');
        $status = trim($_POST['status'] ?? 'Active');
        if ($caseId <= 0) { set_flash('error', $isAr ? 'الحالة مطلوبة.' : 'Case is required.'); header('Location: ?module=disciplinary&a=actions'); exit; }
        if ($effDate === '') $effDate = date('Y-m-d');
        if ($id > 0) {
            q("UPDATE " . table('disciplinary_actions') . " SET case_id=?, penalty_id=?, applied_by=?, effective_date=?, deduction_amount=?, status=?, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?",
              [$caseId, $penaltyId ?: null, (int)(current_user()['id'] ?? 0), $effDate, $dedAmt, $status, (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '', $id]);
        } else {
            q("INSERT INTO " . table('disciplinary_actions') . " (rguid,case_id,penalty_id,applied_by,effective_date,deduction_amount,status,addedbyid,addedby,addedbyname,addedbynamear) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
              [bin2hex(random_bytes(16)), $caseId, $penaltyId ?: null, (int)(current_user()['id'] ?? 0), $effDate, $dedAmt, $status, (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '']);
        }
        set_flash('success', $isAr ? 'تم الحفظ.' : 'Saved.');
        header('Location: ?module=disciplinary&a=actions'); exit;
    }
    // Appeals
    if ($a === 'appeals_save') {
        $id = (int)($_POST['id'] ?? 0);
        $caseId = (int)($_POST['case_id'] ?? 0);
        $statement = trim($_POST['appellant_statement'] ?? '');
        $decision = $_POST['decision'] ?? '';
        $appealDate = trim($_POST['appeal_date'] ?? '');
        if ($caseId <= 0) { set_flash('error', $isAr ? 'الحالة مطلوبة.' : 'Case is required.'); header('Location: ?module=disciplinary&a=appeals'); exit; }
        if ($appealDate === '') $appealDate = date('Y-m-d H:i:s');
        if ($id > 0) {
            q("UPDATE " . table('disciplinary_appeals') . " SET case_id=?, appellant_statement=?, appeal_date=?, decision=?, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?",
              [$caseId, $statement, $appealDate, $decision, (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '', $id]);
        } else {
            q("INSERT INTO " . table('disciplinary_appeals') . " (rguid,case_id,appellant_statement,appeal_date,decision,addedbyid,addedby,addedbyname,addedbynamear) VALUES (?,?,?,?,?,?,?,?,?)",
              [bin2hex(random_bytes(16)), $caseId, $statement, $appealDate, $decision, (int)(current_user()['id'] ?? 0), current_user()['username'] ?? '', current_user()['username'] ?? '', current_user()['username'] ?? '']);
        }
        set_flash('success', $isAr ? 'تم الحفظ.' : 'Saved.');
        header('Location: ?module=disciplinary&a=appeals'); exit;
    }
    // Omit (soft delete) handlers
    $omitTables = ['categories'=>'offense_categories','severity'=>'disciplinary_violations_severity','deduction_types'=>'deduction_types','statuses'=>'disciplinary_cases_status','violations'=>'disciplinary_violations','penalties'=>'disciplinary_penalties','cases'=>'disciplinary_cases','actions'=>'disciplinary_actions','appeals'=>'disciplinary_appeals'];
    if (isset($omitTables[$a === 'omit' ? ($_POST['type'] ?? '') : ''])) {}
    if ($a === 'omit') {
        $type = $_POST['type'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        if (isset($omitTables[$type]) && $id > 0) {
            q("UPDATE " . table($omitTables[$type]) . " SET omitted=1, lastedit=NOW() WHERE id=?", [$id]);
            set_flash('success', $isAr ? 'تم الحذف.' : 'Omitted.');
        }
        $back = $type === 'categories' ? 'categories' : ($type === 'severity' ? 'severity' : ($type === 'deduction_types' ? 'deduction_types' : ($type === 'statuses' ? 'statuses' : ($type === 'violations' ? 'violations' : ($type === 'penalties' ? 'penalties' : ($type === 'cases' ? 'cases' : ($type === 'actions' ? 'actions' : 'appeals')))))));
        header('Location: ?module=disciplinary&a=' . $back); exit;
    }
}

// ---------- sub-navigation ----------
$subtabs = [
    'cases' => $isAr ? 'الحالات' : 'Cases',
    'violations' => $isAr ? 'المخالفات' : 'Violations',
    'categories' => $isAr ? 'فئات المخالفات' : 'Offense Categories',
    'severity' => $isAr ? 'مستويات الخطورة' : 'Severity Levels',
    'deduction_types' => $isAr ? 'أنواع الخصومات' : 'Deduction Types',
    'penalties' => $isAr ? 'العقوبات' : 'Penalties',
    'statuses' => $isAr ? 'حالات القضايا' : 'Case Statuses',
    'actions' => $isAr ? 'الإجراءات' : 'Actions',
    'appeals' => $isAr ? 'الطعون' : 'Appeals',
];
?>
<link rel="stylesheet" href="assets/css/quill.snow.css">
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-shield-exclamation me-2"></i><?= $isAr ? 'جزاءات الموظفين' : 'Disciplinary Actions' ?></h2>
</div>
<ul class="nav nav-tabs mb-3">
<?php foreach ($subtabs as $key => $label): ?>
    <li class="nav-item"><a class="nav-link <?= $a === $key || strpos($a, $key) === 0 ? 'active' : '' ?>" href="?module=disciplinary&a=<?= $key ?>"><?= htmlspecialchars($label) ?></a></li>
<?php endforeach; ?>
</ul>
<?php if ($flash = get_flash()): ?>
<div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show"><?= htmlspecialchars($flash['msg']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php
// ============================================================
// CASES
// ============================================================
if ($a === 'cases' || $a === 'case_add' || $a === 'case_edit' || $a === 'case_view') {
    if ($a === 'case_add' || $a === 'case_edit') {
        $id = (int)($_GET['id'] ?? 0);
        $row = $id ? fetchRow("SELECT * FROM " . table('disciplinary_cases') . " WHERE id=? AND omitted=0", [$id]) : null;
        $emps = fetchAll("SELECT id, empfullname, empfullnamear, nid FROM " . table('employeesmain') . " WHERE omitted=0 ORDER BY empfullname ASC");
        $violations = fetchAll("SELECT id, title_en, title_ar FROM " . table('disciplinary_violations') . " WHERE omitted=0 ORDER BY title_en ASC");
        $statuses = fetchAll("SELECT case_status, case_status_ar FROM " . table('disciplinary_cases_status') . " WHERE omitted=0 ORDER BY case_status ASC");
        ?>
        <div class="card">
            <div class="card-header"><?= $id ? ($isAr ? 'تعديل حالة' : 'Edit Case') : ($isAr ? 'حالة جديدة' : 'New Case') ?></div>
            <div class="card-body">
                <form method="post" action="?module=disciplinary&a=cases_save">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= $isAr ? 'الموظف' : 'Employee' ?> <span class="text-danger">*</span></label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">-- <?= $isAr ? 'اختر' : 'Select' ?> --</option>
                                <?php foreach ($emps as $e): ?>
                                <option value="<?= (int)$e['id'] ?>" <?= ($row && (int)$row['employee_id'] === (int)$e['id']) ? 'selected' : '' ?>><?= htmlspecialchars(($isAr ? $e['empfullnamear'] : $e['empfullname']) . ' - ' . $e['nid']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= $isAr ? 'المخالفة' : 'Violation' ?></label>
                            <select name="violation_id" class="form-select">
                                <option value="">-- <?= $isAr ? 'اختر' : 'Select' ?> --</option>
                                <?php foreach ($violations as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= ($row && (int)$row['violation_id'] === (int)$v['id']) ? 'selected' : '' ?>><?= htmlspecialchars($isAr ? $v['title_ar'] : $v['title_en']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= $isAr ? 'عنوان المخالفة (EN)' : 'Violation Title (EN)' ?></label>
                            <input type="text" name="violation_title" class="form-control" value="<?= htmlspecialchars($row['violation_title'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= $isAr ? 'عنوان المخالفة (AR)' : 'Violation Title (AR)' ?></label>
                            <input type="text" name="violation_title_ar" class="form-control" value="<?= htmlspecialchars($row['violation_title_ar'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= $isAr ? 'تاريخ الحادث' : 'Incident Date' ?></label>
                            <input type="datetime-local" name="incident_date" class="form-control" value="<?= htmlspecialchars($row ? date('Y-m-d\TH:i', strtotime($row['incident_date'])) : date('Y-m-d\TH:i')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= $isAr ? 'الحالة' : 'Status' ?></label>
                            <select name="status" class="form-select">
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?= htmlspecialchars($s['case_status']) ?>" <?= ($row && $row['status'] === $s['case_status']) ? 'selected' : '' ?>><?= htmlspecialchars($isAr ? $s['case_status_ar'] : $s['case_status']) ?></option>
                                <?php endforeach; ?>
                                <?php if (empty($statuses)): ?>
                                <?php foreach (['Logged','Under_Investigation','Hearing_Scheduled','Pending_Approval','Resolved','Appealed'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($row && $row['status'] === $s) ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= $isAr ? 'ملاحظات التحقيق' : 'Investigation Notes' ?></label>
                            <textarea name="investigation_notes" class="form-control" rows="4"><?= htmlspecialchars($row['investigation_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary"><?= $isAr ? 'حفظ' : 'Save' ?></button>
                        <a href="?module=disciplinary&a=cases" class="btn btn-secondary"><?= $isAr ? 'إلغاء' : 'Cancel' ?></a>
                    </div>
                </form>
            </div>
        </div>
        <?php
    } elseif ($a === 'case_view') {
        $id = (int)($_GET['id'] ?? 0);
        $row = $id ? fetchRow("SELECT c.*, e.empfullname, e.empfullnamear, e.nid FROM " . table('disciplinary_cases') . " c LEFT JOIN " . table('employeesmain') . " e ON e.id=c.employee_id WHERE c.id=? AND c.omitted=0", [$id]) : null;
        if (!$row) { set_flash('error', $isAr ? 'غير موجود.' : 'Not found.'); header('Location: ?module=disciplinary&a=cases'); exit; }
        $actions = fetchAll("SELECT a.*, p.name_en, p.name_ar FROM " . table('disciplinary_actions') . " a LEFT JOIN " . table('disciplinary_penalties') . " p ON p.id=a.penalty_id WHERE a.case_id=? AND a.omitted=0", [$id]);
        $appeals = fetchAll("SELECT * FROM " . table('disciplinary_appeals') . " WHERE case_id=? AND omitted=0", [$id]);
        ?>
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'تفاصيل الحالة' : 'Case Details' ?> #<?= (int)$row['id'] ?></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><?= $isAr ? 'الموظف' : 'Employee' ?>:</strong> <?= htmlspecialchars($isAr ? $row['empfullnamear'] : $row['empfullname']) ?></p>
                        <p><strong><?= $isAr ? 'الرقم القومي' : 'NID' ?>:</strong> <?= htmlspecialchars($row['nid'] ?? '') ?></p>
                        <p><strong><?= $isAr ? 'المخالفة' : 'Violation' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['violation_title_ar'] ?: $row['violation_title']) : $row['violation_title']) ?></p>
                        <p><strong><?= $isAr ? 'تاريخ الحادث' : 'Incident Date' ?>:</strong> <?= htmlspecialchars($row['incident_date']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong><?= $isAr ? 'الحالة' : 'Status' ?>:</strong> <span class="badge bg-secondary"><?= htmlspecialchars($row['status']) ?></span></p>
                        <p><strong><?= $isAr ? 'ملاحظات التحقيق' : 'Investigation Notes' ?>:</strong><br><?= nl2br(htmlspecialchars($row['investigation_notes'] ?? '')) ?></p>
                    </div>
                </div>
                <div class="mt-2">
                    <a href="?module=disciplinary&a=case_edit&id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-primary"><?= $isAr ? 'تعديل' : 'Edit' ?></a>
                    <a href="?module=disciplinary&a=actions&a2=add&case_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-success"><?= $isAr ? 'إضافة إجراء' : 'Add Action' ?></a>
                    <a href="?module=disciplinary&a=appeals&a2=add&case_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning"><?= $isAr ? 'إضافة طعن' : 'Add Appeal' ?></a>
                    <a href="?module=disciplinary&a=cases" class="btn btn-sm btn-secondary"><?= $isAr ? 'رجوع' : 'Back' ?></a>
                </div>
            </div>
        </div>
        <?php if (!empty($actions)): ?>
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'الإجراءات المطبقة' : 'Applied Actions' ?></div>
            <div class="card-body">
                <table class="table table-sm"><thead><tr><th><?= $isAr?'العقوبة':'Penalty' ?></th><th><?= $isAr?'تاريخ السريان':'Effective' ?></th><th><?= $isAr?'المبلغ':'Amount' ?></th><th><?= $isAr?'الحالة':'Status' ?></th></tr></thead><tbody>
                <?php foreach ($actions as $ac): ?>
                <tr>
                    <td><?= htmlspecialchars($isAr ? $ac['name_ar'] : $ac['name_en']) ?></td>
                    <td><?= htmlspecialchars($ac['effective_date']) ?></td>
                    <td><?= htmlspecialchars($ac['deduction_amount']) ?></td>
                    <td><?= htmlspecialchars($ac['status']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($appeals)): ?>
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'الطعون' : 'Appeals' ?></div>
            <div class="card-body">
                <?php foreach ($appeals as $ap): ?>
                <div class="border rounded p-2 mb-2">
                    <div class="small text-muted"><?= htmlspecialchars($ap['appeal_date']) ?></div>
                    <div><strong><?= $isAr ? 'بيان الطاعن' : 'Appellant Statement' ?>:</strong> <?= nl2br(htmlspecialchars($ap['appellant_statement'] ?? '')) ?></div>
                    <div><strong><?= $isAr ? 'القرار' : 'Decision' ?>:</strong> <?= $ap['decision'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php
    } else {
        // list
        $rows = fetchAll("SELECT c.*, e.empfullname, e.empfullnamear FROM " . table('disciplinary_cases') . " c LEFT JOIN " . table('employeesmain') . " e ON e.id=c.employee_id WHERE c.omitted=0 ORDER BY c.id DESC");
        ?>
        <div class="d-flex justify-content-between mb-3">
            <h5><?= $isAr ? 'حالات جزاءات الموظفين' : 'Disciplinary Cases' ?></h5>
            <a href="?module=disciplinary&a=case_add" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= $isAr ? 'حالة جديدة' : 'New Case' ?></a>
        </div>
        <div class="table-responsive" style="max-height: calc(100vh / 3); overflow-y:auto;">
        <table class="table table-sm table-bordered">
            <thead class="table-light"><tr>
                <th>#</th>
                <th><?= $isAr ? 'الموظف' : 'Employee' ?></th>
                <th><?= $isAr ? 'المخالفة' : 'Violation' ?></th>
                <th><?= $isAr ? 'تاريخ الحادث' : 'Incident Date' ?></th>
                <th><?= $isAr ? 'الحالة' : 'Status' ?></th>
                <th><?= $isAr ? 'إجراءات' : 'Actions' ?></th>
            </tr></thead><tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= htmlspecialchars($isAr ? $r['empfullnamear'] : $r['empfullname']) ?></td>
                <td><?= htmlspecialchars($isAr ? ($r['violation_title_ar'] ?: $r['violation_title']) : $r['violation_title']) ?></td>
                <td><?= htmlspecialchars($r['incident_date']) ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($r['status']) ?></span></td>
                <td>
                    <a href="?module=disciplinary&a=case_view&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                    <a href="?module=disciplinary&a=case_edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted"><?= $isAr ? 'لا توجد سجلات' : 'No records' ?></td></tr><?php endif; ?>
            </tbody></table>
        </div>
        <?php
    }
}

// ============================================================
// VIOLATIONS
// ============================================================
elseif ($a === 'violations' || $a === 'violation_add' || $a === 'violation_edit') {
    if ($a === 'violation_add' || $a === 'violation_edit') {
        $id = (int)($_GET['id'] ?? 0);
        $row = $id ? fetchRow("SELECT * FROM " . table('disciplinary_violations') . " WHERE id=? AND omitted=0", [$id]) : null;
        $cats = fetchAll("SELECT id,name_en,name_ar FROM " . table('offense_categories') . " WHERE omitted=0 ORDER BY name_en");
        $sevs = fetchAll("SELECT id,severity_level,severity_level_ar FROM " . table('disciplinary_violations_severity') . " WHERE omitted=0 ORDER BY severity_level");
        $pens = fetchAll("SELECT id,name_en,name_ar FROM " . table('disciplinary_penalties') . " WHERE omitted=0 ORDER BY name_en");
        ?>
        <div class="card">
            <div class="card-header"><?= $id ? ($isAr ? 'تعديل مخالفة' : 'Edit Violation') : ($isAr ? 'مخالفة جديدة' : 'New Violation') ?></div>
            <div class="card-body">
                <form method="post" action="?module=disciplinary&a=violations_save">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= $isAr ? 'العنوان (إنجليزي)' : 'Title (EN)' ?> <span class="text-danger">*</span></label>
                            <textarea name="title_en" class="form-control" rows="2" required><?= htmlspecialchars($row['title_en'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= $isAr ? 'العنوان (عربي)' : 'Title (AR)' ?></label>
                            <textarea name="title_ar" class="form-control" rows="2"><?= htmlspecialchars($row['title_ar'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= $isAr ? 'الفئة' : 'Category' ?></label>
                            <select name="category_id" class="form-select">
                                <option value="">-- <?= $isAr ? 'اختر' : 'Select' ?> --</option>
                                <?php foreach ($cats as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= ($row && (int)$row['category_id'] === (int)$c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($isAr ? $c['name_ar'] : $c['name_en']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= $isAr ? 'مستوى الخطورة' : 'Severity' ?></label>
                            <select name="severity_id" class="form-select">
                                <option value="">-- <?= $isAr ? 'اختر' : 'Select' ?> --</option>
                                <?php foreach ($sevs as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= ($row && (int)$row['severity_id'] === (int)$s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($isAr ? $s['severity_level_ar'] : $s['severity_level']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= $isAr ? 'العقوبة الافتراضية' : 'Default Penalty' ?></label>
                            <select name="default_penalty_id" class="form-select">
                                <option value="">-- <?= $isAr ? 'اختر' : 'Select' ?> --</option>
                                <?php foreach ($pens as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= ($row && (int)$row['default_penalty_id'] === (int)$p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($isAr ? $p['name_ar'] : $p['name_en']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= $isAr ? 'الوصف (EN)' : 'Description (EN)' ?></label>
                            <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($row['description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= $isAr ? 'الوصف (AR)' : 'Description (AR)' ?></label>
                            <textarea name="description_ar" class="form-control" rows="2"><?= htmlspecialchars($row['description_ar'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary"><?= $isAr ? 'حفظ' : 'Save' ?></button>
                        <a href="?module=disciplinary&a=violations" class="btn btn-secondary"><?= $isAr ? 'إلغاء' : 'Cancel' ?></a>
                    </div>
                </form>
            </div>
        </div>
        <?php
    } else {
        $rows = fetchAll("SELECT * FROM " . table('disciplinary_violations') . " WHERE omitted=0 ORDER BY id DESC");
        ?>
        <div class="d-flex justify-content-between mb-3">
            <h5><?= $isAr ? 'المخالفات' : 'Violations' ?></h5>
            <a href="?module=disciplinary&a=violation_add" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= $isAr ? 'جديد' : 'New' ?></a>
        </div>
        <div class="table-responsive" style="max-height:calc(100vh/3);overflow-y:auto;">
        <table class="table table-sm table-bordered">
            <thead class="table-light"><tr>
                <th>#</th><th><?= $isAr?'العنوان':'Title' ?></th><th><?= $isAr?'الفئة':'Category' ?></th><th><?= $isAr?'الخطورة':'Severity' ?></th><th><?= $isAr?'إجراءات':'Actions' ?></th>
            </tr></thead><tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= htmlspecialchars($isAr ? $r['title_ar'] : $r['title_en']) ?></td>
                <td><?= htmlspecialchars($isAr ? $r['category_ar'] : $r['category']) ?></td>
                <td><?= htmlspecialchars($isAr ? $r['severity_level_ar'] : $r['severity_level']) ?></td>
                <td>
                    <a href="?module=disciplinary&a=violation_edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <form method="post" action="?module=disciplinary&a=omit" style="display:inline"><input type="hidden" name="type" value="violations"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= $isAr?'تأكيد؟':'Confirm?' ?>')"><i class="bi bi-trash"></i></button></form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?><tr><td colspan="5" class="text-center text-muted"><?= $isAr?'لا توجد سجلات':'No records' ?></td></tr><?php endif; ?>
            </tbody></table></div>
        <?php
    }
}

// ============================================================
// CATEGORIES
// ============================================================
elseif ($a === 'categories' || $a === 'category_add' || $a === 'category_edit') {
    if ($a === 'category_add' || $a === 'category_edit') {
        $id = (int)($_GET['id'] ?? 0);
        $row = $id ? fetchRow("SELECT * FROM " . table('offense_categories') . " WHERE id=? AND omitted=0", [$id]) : null;
        ?>
        <div class="card">
            <div class="card-header"><?= $id ? ($isAr ? 'تعديل فئة' : 'Edit Category') : ($isAr ? 'فئة جديدة' : 'New Category') ?></div>
            <div class="card-body">
                <form method="post" action="?module=disciplinary&a=categories_save">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label"><?= $isAr?'الاسم (EN)':'Name (EN)' ?> <span class="text-danger">*</span></label><input type="text" name="name_en" class="form-control" required value="<?= htmlspecialchars($row['name_en'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label"><?= $isAr?'الاسم (AR)':'Name (AR)' ?></label><input type="text" name="name_ar" class="form-control" value="<?= htmlspecialchars($row['name_ar'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label"><?= $isAr?'الوصف (EN)':'Description (EN)' ?></label><textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($row['description'] ?? '') ?></textarea></div>
                        <div class="col-md-6"><label class="form-label"><?= $isAr?'الوصف (AR)':'Description (AR)' ?></label><textarea name="description_ar" class="form-control" rows="2"><?= htmlspecialchars($row['description_ar'] ?? '') ?></textarea></div>
                    </div>
                    <div class="mt-3"><button type="submit" class="btn btn-primary"><?= $isAr?'حفظ':'Save' ?></button> <a href="?module=disciplinary&a=categories" class="btn btn-secondary"><?= $isAr?'إلغاء':'Cancel' ?></a></div>
                </form>
            </div>
        </div>
        <?php
    } else {
        $rows = fetchAll("SELECT * FROM " . table('offense_categories') . " WHERE omitted=0 ORDER BY id DESC");
        ?>
        <div class="d-flex justify-content-between mb-3"><h5><?= $isAr?'فئات المخالفات':'Offense Categories' ?></h5><a href="?module=disciplinary&a=category_add" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= $isAr?'جديد':'New' ?></a></div>
        <div class="table-responsive" style="max-height:calc(100vh/3);overflow-y:auto;"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>#</th><th><?= $isAr?'الاسم (EN)':'Name (EN)' ?></th><th><?= $isAr?'الاسم (AR)':'Name (AR)' ?></th><th><?= $isAr?'إجراءات':'Actions' ?></th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
        <tr><td><?= (int)$r['id'] ?></td><td><?= htmlspecialchars($r['name_en']) ?></td><td><?= htmlspecialchars($r['name_ar']) ?></td><td>
            <a href="?module=disciplinary&a=category_edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
            <form method="post" action="?module=disciplinary&a=omit" style="display:inline"><input type="hidden" name="type" value="categories"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= $isAr?'تأكيد؟':'Confirm?' ?>')"><i class="bi bi-trash"></i></button></form>
        </td></tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="4" class="text-center text-muted"><?= $isAr?'لا توجد سجلات':'No records' ?></td></tr><?php endif; ?>
        </tbody></table></div>
        <?php
    }
}

// ============================================================
// SEVERITY
// ============================================================
elseif ($a === 'severity' || $a === 'severity_add' || $a === 'severity_edit') {
    if ($a === 'severity_add' || $a === 'severity_edit') {
        $id = (int)($_GET['id'] ?? 0);
        $row = $id ? fetchRow("SELECT * FROM " . table('disciplinary_violations_severity') . " WHERE id=? AND omitted=0", [$id]) : null;
        ?>
        <div class="card"><div class="card-header"><?= $id ? ($isAr?'تعديل':'Edit') : ($isAr?'جديد':'New') ?></div><div class="card-body">
        <form method="post" action="?module=disciplinary&a=severity_save">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label"><?= $isAr?'المستوى (EN)':'Level (EN)' ?> <span class="text-danger">*</span></label><input type="text" name="severity_level" class="form-control" required value="<?= htmlspecialchars($row['severity_level'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label"><?= $isAr?'المستوى (AR)':'Level (AR)' ?></label><input type="text" name="severity_level_ar" class="form-control" value="<?= htmlspecialchars($row['severity_level_ar'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الوصف (EN)':'Description (EN)' ?></label><textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($row['description'] ?? '') ?></textarea></div>
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الوصف (AR)':'Description (AR)' ?></label><textarea name="description_ar" class="form-control" rows="2"><?= htmlspecialchars($row['description_ar'] ?? '') ?></textarea></div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary"><?= $isAr?'حفظ':'Save' ?></button> <a href="?module=disciplinary&a=severity" class="btn btn-secondary"><?= $isAr?'إلغاء':'Cancel' ?></a></div>
        </form></div></div>
        <?php
    } else {
        $rows = fetchAll("SELECT * FROM " . table('disciplinary_violations_severity') . " WHERE omitted=0 ORDER BY id DESC");
        ?>
        <div class="d-flex justify-content-between mb-3"><h5><?= $isAr?'مستويات الخطورة':'Severity Levels' ?></h5><a href="?module=disciplinary&a=severity_add" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= $isAr?'جديد':'New' ?></a></div>
        <div class="table-responsive" style="max-height:calc(100vh/3);overflow-y:auto;"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>#</th><th><?= $isAr?'المستوى (EN)':'Level (EN)' ?></th><th><?= $isAr?'المستوى (AR)':'Level (AR)' ?></th><th><?= $isAr?'إجراءات':'Actions' ?></th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
        <tr><td><?= (int)$r['id'] ?></td><td><?= htmlspecialchars($r['severity_level']) ?></td><td><?= htmlspecialchars($r['severity_level_ar']) ?></td><td>
            <a href="?module=disciplinary&a=severity_edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
            <form method="post" action="?module=disciplinary&a=omit" style="display:inline"><input type="hidden" name="type" value="severity"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= $isAr?'تأكيد؟':'Confirm?' ?>')"><i class="bi bi-trash"></i></button></form>
        </td></tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="4" class="text-center text-muted"><?= $isAr?'لا توجد سجلات':'No records' ?></td></tr><?php endif; ?>
        </tbody></table></div>
        <?php
    }
}

// ============================================================
// DEDUCTION TYPES
// ============================================================
elseif ($a === 'deduction_types' || $a === 'deduction_type_add' || $a === 'deduction_type_edit') {
    if ($a === 'deduction_type_add' || $a === 'deduction_type_edit') {
        $id = (int)($_GET['id'] ?? 0);
        $row = $id ? fetchRow("SELECT * FROM " . table('deduction_types') . " WHERE id=? AND omitted=0", [$id]) : null;
        ?>
        <div class="card"><div class="card-header"><?= $id ? ($isAr?'تعديل':'Edit') : ($isAr?'جديد':'New') ?></div><div class="card-body">
        <form method="post" action="?module=disciplinary&a=deduction_types_save">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الاسم (EN)':'Name (EN)' ?> <span class="text-danger">*</span></label><input type="text" name="deduction_type_name" class="form-control" required value="<?= htmlspecialchars($row['deduction_type_name'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الاسم (AR)':'Name (AR)' ?></label><input type="text" name="deduction_type_name_ar" class="form-control" value="<?= htmlspecialchars($row['deduction_type_name_ar'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label"><?= $isAr?'أقصى أيام الخصم':'Max Deduction Days' ?></label><input type="text" name="max_deduction_days" class="form-control" value="<?= htmlspecialchars($row['max_deduction_days'] ?? '') ?>"></div>
                <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input type="checkbox" name="requires_legal_approval" value="1" class="form-check-input" id="legal" <?= (!isset($row) || !empty($row['requires_legal_approval'])) ? 'checked' : '' ?>><label class="form-check-label" for="legal"><?= $isAr?'يتطلب موافقة قانونية':'Requires Legal Approval' ?></label></div></div>
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الوصف (EN)':'Description (EN)' ?></label><textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($row['description'] ?? '') ?></textarea></div>
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الوصف (AR)':'Description (AR)' ?></label><textarea name="description_ar" class="form-control" rows="2"><?= htmlspecialchars($row['description_ar'] ?? '') ?></textarea></div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary"><?= $isAr?'حفظ':'Save' ?></button> <a href="?module=disciplinary&a=deduction_types" class="btn btn-secondary"><?= $isAr?'إلغاء':'Cancel' ?></a></div>
        </form></div></div>
        <?php
    } else {
        $rows = fetchAll("SELECT * FROM " . table('deduction_types') . " WHERE omitted=0 ORDER BY id DESC");
        ?>
        <div class="d-flex justify-content-between mb-3"><h5><?= $isAr?'أنواع الخصومات':'Deduction Types' ?></h5><a href="?module=disciplinary&a=deduction_type_add" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= $isAr?'جديد':'New' ?></a></div>
        <div class="table-responsive" style="max-height:calc(100vh/3);overflow-y:auto;"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>#</th><th><?= $isAr?'الاسم (EN)':'Name (EN)' ?></th><th><?= $isAr?'الاسم (AR)':'Name (AR)' ?></th><th><?= $isAr?'أقصى أيام':'Max Days' ?></th><th><?= $isAr?'موافقة قانونية':'Legal' ?></th><th><?= $isAr?'إجراءات':'Actions' ?></th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
        <tr><td><?= (int)$r['id'] ?></td><td><?= htmlspecialchars($r['deduction_type_name']) ?></td><td><?= htmlspecialchars($r['deduction_type_name_ar']) ?></td><td><?= htmlspecialchars($r['max_deduction_days']) ?></td><td><?= !empty($r['requires_legal_approval']) ? '✓' : '—' ?></td><td>
            <a href="?module=disciplinary&a=deduction_type_edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
            <form method="post" action="?module=disciplinary&a=omit" style="display:inline"><input type="hidden" name="type" value="deduction_types"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= $isAr?'تأكيد؟':'Confirm?' ?>')"><i class="bi bi-trash"></i></button></form>
        </td></tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted"><?= $isAr?'لا توجد سجلات':'No records' ?></td></tr><?php endif; ?>
        </tbody></table></div>
        <?php
    }
}

// ============================================================
// PENALTIES
// ============================================================
elseif ($a === 'penalties' || $a === 'penalty_add' || $a === 'penalty_edit') {
    if ($a === 'penalty_add' || $a === 'penalty_edit') {
        $id = (int)($_GET['id'] ?? 0);
        $row = $id ? fetchRow("SELECT * FROM " . table('disciplinary_penalties') . " WHERE id=? AND omitted=0", [$id]) : null;
        $dedTypes = fetchAll("SELECT id,deduction_type_name,deduction_type_name_ar FROM " . table('deduction_types') . " WHERE omitted=0 ORDER BY deduction_type_name");
        $emps = fetchAll("SELECT id,empfullname,empfullnamear,nid FROM " . table('employeesmain') . " WHERE omitted=0 ORDER BY empfullname");
        ?>
        <div class="card"><div class="card-header"><?= $id ? ($isAr?'تعديل عقوبة':'Edit Penalty') : ($isAr?'عقوبة جديدة':'New Penalty') ?></div><div class="card-body">
        <form method="post" action="?module=disciplinary&a=penalties_save">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الاسم (EN)':'Name (EN)' ?> <span class="text-danger">*</span></label><input type="text" name="name_en" class="form-control" required value="<?= htmlspecialchars($row['name_en'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الاسم (AR)':'Name (AR)' ?></label><input type="text" name="name_ar" class="form-control" value="<?= htmlspecialchars($row['name_ar'] ?? '') ?>"></div>
                <div class="col-md-6">
                    <label class="form-label"><?= $isAr?'نوع الخصم':'Deduction Type' ?></label>
                    <select name="deduction_type_id" class="form-select">
                        <option value="">-- <?= $isAr?'اختر':'Select' ?> --</option>
                        <?php foreach ($dedTypes as $dt): ?>
                        <option value="<?= (int)$dt['id'] ?>" <?= ($row && (int)$row['deduction_type_id'] === (int)$dt['id']) ? 'selected' : '' ?>><?= htmlspecialchars($isAr ? $dt['deduction_type_name_ar'] : $dt['deduction_type_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label"><?= $isAr?'أقصى أيام الخصم':'Max Deduction Days' ?></label><input type="number" name="max_deduction_days" class="form-control" value="<?= (int)($row['max_deduction_days'] ?? 0) ?>"></div>
                <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input type="checkbox" name="requires_legal_approval" value="1" class="form-check-input" id="legal2" <?= (!isset($row) || !empty($row['requires_legal_approval'])) ? 'checked' : '' ?>><label class="form-check-label" for="legal2"><?= $isAr?'موافقة قانونية':'Legal Approval' ?></label></div></div>
                <div class="col-md-6">
                    <label class="form-label"><?= $isAr?'الموظف (اختياري)':'Employee (optional)' ?></label>
                    <select name="employee_id" class="form-select">
                        <option value="">-- <?= $isAr?'عام (بدون موظف)':'Generic (no employee)' ?> --</option>
                        <?php foreach ($emps as $e): ?>
                        <option value="<?= (int)$e['id'] ?>" <?= ($row && (int)$row['employee_id'] === (int)$e['id']) ? 'selected' : '' ?>><?= htmlspecialchars(($isAr ? $e['empfullnamear'] : $e['empfullname']) . ' - ' . $e['nid']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الوصف (EN)':'Description (EN)' ?></label><textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($row['description'] ?? '') ?></textarea></div>
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الوصف (AR)':'Description (AR)' ?></label><textarea name="description_ar" class="form-control" rows="2"><?= htmlspecialchars($row['description_ar'] ?? '') ?></textarea></div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary"><?= $isAr?'حفظ':'Save' ?></button> <a href="?module=disciplinary&a=penalties" class="btn btn-secondary"><?= $isAr?'إلغاء':'Cancel' ?></a></div>
        </form></div></div>
        <?php
    } else {
        $rows = fetchAll("SELECT * FROM " . table('disciplinary_penalties') . " WHERE omitted=0 ORDER BY id DESC");
        ?>
        <div class="d-flex justify-content-between mb-3"><h5><?= $isAr?'العقوبات':'Penalties' ?></h5><a href="?module=disciplinary&a=penalty_add" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= $isAr?'جديد':'New' ?></a></div>
        <div class="table-responsive" style="max-height:calc(100vh/3);overflow-y:auto;"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>#</th><th><?= $isAr?'الاسم (EN)':'Name (EN)' ?></th><th><?= $isAr?'الاسم (AR)':'Name (AR)' ?></th><th><?= $isAr?'نوع الخصم':'Deduction Type' ?></th><th><?= $isAr?'أقصى أيام':'Max Days' ?></th><th><?= $isAr?'إجراءات':'Actions' ?></th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
        <tr><td><?= (int)$r['id'] ?></td><td><?= htmlspecialchars($r['name_en']) ?></td><td><?= htmlspecialchars($r['name_ar']) ?></td><td><?= htmlspecialchars($r['deduction_type']) ?></td><td><?= (int)$r['max_deduction_days'] ?></td><td>
            <a href="?module=disciplinary&a=penalty_edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
            <form method="post" action="?module=disciplinary&a=omit" style="display:inline"><input type="hidden" name="type" value="penalties"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= $isAr?'تأكيد؟':'Confirm?' ?>')"><i class="bi bi-trash"></i></button></form>
        </td></tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted"><?= $isAr?'لا توجد سجلات':'No records' ?></td></tr><?php endif; ?>
        </tbody></table></div>
        <?php
    }
}

// ============================================================
// STATUSES
// ============================================================
elseif ($a === 'statuses' || $a === 'status_add' || $a === 'status_edit') {
    if ($a === 'status_add' || $a === 'status_edit') {
        $id = (int)($_GET['id'] ?? 0);
        $row = $id ? fetchRow("SELECT * FROM " . table('disciplinary_cases_status') . " WHERE id=? AND omitted=0", [$id]) : null;
        ?>
        <div class="card"><div class="card-header"><?= $id ? ($isAr?'تعديل':'Edit') : ($isAr?'جديد':'New') ?></div><div class="card-body">
        <form method="post" action="?module=disciplinary&a=statuses_save">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الحالة (EN)':'Status (EN)' ?> <span class="text-danger">*</span></label><input type="text" name="case_status" class="form-control" required value="<?= htmlspecialchars($row['case_status'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الحالة (AR)':'Status (AR)' ?></label><input type="text" name="case_status_ar" class="form-control" value="<?= htmlspecialchars($row['case_status_ar'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الوصف (EN)':'Description (EN)' ?></label><textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($row['description'] ?? '') ?></textarea></div>
                <div class="col-md-6"><label class="form-label"><?= $isAr?'الوصف (AR)':'Description (AR)' ?></label><textarea name="description_ar" class="form-control" rows="2"><?= htmlspecialchars($row['description_ar'] ?? '') ?></textarea></div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary"><?= $isAr?'حفظ':'Save' ?></button> <a href="?module=disciplinary&a=statuses" class="btn btn-secondary"><?= $isAr?'إلغاء':'Cancel' ?></a></div>
        </form></div></div>
        <?php
    } else {
        $rows = fetchAll("SELECT * FROM " . table('disciplinary_cases_status') . " WHERE omitted=0 ORDER BY id DESC");
        ?>
        <div class="d-flex justify-content-between mb-3"><h5><?= $isAr?'حالات القضايا':'Case Statuses' ?></h5><a href="?module=disciplinary&a=status_add" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= $isAr?'جديد':'New' ?></a></div>
        <div class="table-responsive" style="max-height:calc(100vh/3);overflow-y:auto;"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>#</th><th><?= $isAr?'الحالة (EN)':'Status (EN)' ?></th><th><?= $isAr?'الحالة (AR)':'Status (AR)' ?></th><th><?= $isAr?'إجراءات':'Actions' ?></th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
        <tr><td><?= (int)$r['id'] ?></td><td><?= htmlspecialchars($r['case_status']) ?></td><td><?= htmlspecialchars($r['case_status_ar']) ?></td><td>
            <a href="?module=disciplinary&a=status_edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
            <form method="post" action="?module=disciplinary&a=omit" style="display:inline"><input type="hidden" name="type" value="statuses"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= $isAr?'تأكيد؟':'Confirm?' ?>')"><i class="bi bi-trash"></i></button></form>
        </td></tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="4" class="text-center text-muted"><?= $isAr?'لا توجد سجلات':'No records' ?></td></tr><?php endif; ?>
        </tbody></table></div>
        <?php
    }
}

// ============================================================
// ACTIONS (applied sanctions)
// ============================================================
elseif ($a === 'actions' || $a === 'action_add' || $a === 'action_edit') {
    $a2 = $_GET['a2'] ?? '';
    if ($a2 === 'add') $a = 'action_add';
    if ($a === 'action_add' || $a === 'action_edit') {
        $id = (int)($_GET['id'] ?? 0);
        $caseId = (int)($_GET['case_id'] ?? ($_POST['case_id'] ?? 0));
        $row = $id ? fetchRow("SELECT * FROM " . table('disciplinary_actions') . " WHERE id=? AND omitted=0", [$id]) : null;
        if ($row) $caseId = (int)$row['case_id'];
        $cases = fetchAll("SELECT c.id, e.empfullname, e.empfullnamear FROM " . table('disciplinary_cases') . " c LEFT JOIN " . table('employeesmain') . " e ON e.id=c.employee_id WHERE c.omitted=0 ORDER BY c.id DESC");
        $pens = fetchAll("SELECT id,name_en,name_ar FROM " . table('disciplinary_penalties') . " WHERE omitted=0 ORDER BY name_en");
        $statuses = fetchAll("SELECT case_status,case_status_ar FROM " . table('disciplinary_cases_status') . " WHERE omitted=0 ORDER BY case_status");
        ?>
        <div class="card"><div class="card-header"><?= $id ? ($isAr?'تعديل إجراء':'Edit Action') : ($isAr?'إجراء جديد':'New Action') ?></div><div class="card-body">
        <form method="post" action="?module=disciplinary&a=actions_save">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label"><?= $isAr?'الحالة':'Case' ?> <span class="text-danger">*</span></label>
                    <select name="case_id" class="form-select" required>
                        <option value="">-- <?= $isAr?'اختر':'Select' ?> --</option>
                        <?php foreach ($cases as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ($caseId === (int)$c['id']) ? 'selected' : '' ?>>#<?= (int)$c['id'] ?> - <?= htmlspecialchars($isAr ? $c['empfullnamear'] : $c['empfullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= $isAr?'العقوبة':'Penalty' ?></label>
                    <select name="penalty_id" class="form-select">
                        <option value="">-- <?= $isAr?'اختر':'Select' ?> --</option>
                        <?php foreach ($pens as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= ($row && (int)$row['penalty_id'] === (int)$p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($isAr ? $p['name_ar'] : $p['name_en']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label"><?= $isAr?'تاريخ السريان':'Effective Date' ?></label><input type="date" name="effective_date" class="form-control" value="<?= htmlspecialchars($row['effective_date'] ?? date('Y-m-d')) ?>"></div>
                <div class="col-md-4"><label class="form-label"><?= $isAr?'مبلغ الخصم':'Deduction Amount' ?></label><input type="number" step="0.01" name="deduction_amount" class="form-control" value="<?= htmlspecialchars($row['deduction_amount'] ?? '0') ?>"></div>
                <div class="col-md-4">
                    <label class="form-label"><?= $isAr?'الحالة':'Status' ?></label>
                    <select name="status" class="form-select">
                        <?php $stOpts = !empty($statuses) ? array_column($statuses, 'case_status') : ['Active','Completed','Cancelled']; ?>
                        <?php foreach ($stOpts as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= ($row && $row['status'] === $s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary"><?= $isAr?'حفظ':'Save' ?></button> <a href="?module=disciplinary&a=actions" class="btn btn-secondary"><?= $isAr?'إلغاء':'Cancel' ?></a></div>
        </form></div></div>
        <?php
    } else {
        $rows = fetchAll("SELECT a.*, p.name_en, p.name_ar, e.empfullname, e.empfullnamear FROM " . table('disciplinary_actions') . " a LEFT JOIN " . table('disciplinary_penalties') . " p ON p.id=a.penalty_id LEFT JOIN " . table('disciplinary_cases') . " c ON c.id=a.case_id LEFT JOIN " . table('employeesmain') . " e ON e.id=c.employee_id WHERE a.omitted=0 ORDER BY a.id DESC");
        ?>
        <div class="d-flex justify-content-between mb-3"><h5><?= $isAr?'الإجراءات':'Actions' ?></h5><a href="?module=disciplinary&a=action_add" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= $isAr?'جديد':'New' ?></a></div>
        <div class="table-responsive" style="max-height:calc(100vh/3);overflow-y:auto;"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>#</th><th><?= $isAr?'الحالة':'Case' ?></th><th><?= $isAr?'الموظف':'Employee' ?></th><th><?= $isAr?'العقوبة':'Penalty' ?></th><th><?= $isAr?'تاريخ السريان':'Effective' ?></th><th><?= $isAr?'المبلغ':'Amount' ?></th><th><?= $isAr?'الحالة':'Status' ?></th><th><?= $isAr?'إجراءات':'Actions' ?></th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
        <tr><td><?= (int)$r['id'] ?></td><td>#<?= (int)$r['case_id'] ?></td><td><?= htmlspecialchars($isAr ? $r['empfullnamear'] : $r['empfullname']) ?></td><td><?= htmlspecialchars($isAr ? $r['name_ar'] : $r['name_en']) ?></td><td><?= htmlspecialchars($r['effective_date']) ?></td><td><?= htmlspecialchars($r['deduction_amount']) ?></td><td><?= htmlspecialchars($r['status']) ?></td><td>
            <a href="?module=disciplinary&a=action_edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
            <form method="post" action="?module=disciplinary&a=omit" style="display:inline"><input type="hidden" name="type" value="actions"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= $isAr?'تأكيد؟':'Confirm?' ?>')"><i class="bi bi-trash"></i></button></form>
        </td></tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted"><?= $isAr?'لا توجد سجلات':'No records' ?></td></tr><?php endif; ?>
        </tbody></table></div>
        <?php
    }
}

// ============================================================
// APPEALS (with Quill WYSIWYG for decision)
// ============================================================
elseif ($a === 'appeals' || $a === 'appeal_add' || $a === 'appeal_edit') {
    if ($a === 'appeal_add' || $a === 'appeal_edit') {
        $id = (int)($_GET['id'] ?? 0);
        $caseId = (int)($_GET['case_id'] ?? ($_POST['case_id'] ?? 0));
        $row = $id ? fetchRow("SELECT * FROM " . table('disciplinary_appeals') . " WHERE id=? AND omitted=0", [$id]) : null;
        if ($row) $caseId = (int)$row['case_id'];
        $cases = fetchAll("SELECT c.id, e.empfullname, e.empfullnamear FROM " . table('disciplinary_cases') . " c LEFT JOIN " . table('employeesmain') . " e ON e.id=c.employee_id WHERE c.omitted=0 ORDER BY c.id DESC");
        ?>
        <div class="card"><div class="card-header"><?= $id ? ($isAr?'تعديل طعن':'Edit Appeal') : ($isAr?'طعن جديد':'New Appeal') ?></div><div class="card-body">
        <form method="post" action="?module=disciplinary&a=appeals_save" id="appealForm">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="decision" id="decisionInput" value="<?= htmlspecialchars($row['decision'] ?? '') ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label"><?= $isAr?'الحالة':'Case' ?> <span class="text-danger">*</span></label>
                    <select name="case_id" class="form-select" required>
                        <option value="">-- <?= $isAr?'اختر':'Select' ?> --</option>
                        <?php foreach ($cases as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ($caseId === (int)$c['id']) ? 'selected' : '' ?>>#<?= (int)$c['id'] ?> - <?= htmlspecialchars($isAr ? $c['empfullnamear'] : $c['empfullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= $isAr?'تاريخ الطعن':'Appeal Date' ?></label>
                    <input type="datetime-local" name="appeal_date" class="form-control" value="<?= htmlspecialchars($row ? date('Y-m-d\TH:i', strtotime($row['appeal_date'])) : date('Y-m-d\TH:i')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label"><?= $isAr?'بيان الطاعن':'Appellant Statement' ?></label>
                    <textarea name="appellant_statement" class="form-control" rows="3"><?= htmlspecialchars($row['appellant_statement'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label"><?= $isAr?'القرار (محرر نصي)':'Decision (Rich Text)' ?></label>
                    <div id="quillEditor" style="height:200px;"><?= $row['decision'] ?? '' ?></div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary" onclick="document.getElementById('decisionInput').value = quill.root.innerHTML;"><?= $isAr?'حفظ':'Save' ?></button>
                <a href="?module=disciplinary&a=appeals" class="btn btn-secondary"><?= $isAr?'إلغاء':'Cancel' ?></a>
            </div>
        </form>
        </div></div>
        <script src="assets/js/quill.min.js"></script>
        <script>
        var quill = new Quill('#quillEditor', { theme: 'snow' });
        </script>
        <?php
    } else {
        $rows = fetchAll("SELECT a.*, c.employee_id, e.empfullname, e.empfullnamear FROM " . table('disciplinary_appeals') . " a LEFT JOIN " . table('disciplinary_cases') . " c ON c.id=a.case_id LEFT JOIN " . table('employeesmain') . " e ON e.id=c.employee_id WHERE a.omitted=0 ORDER BY a.id DESC");
        ?>
        <div class="d-flex justify-content-between mb-3"><h5><?= $isAr?'الطعون':'Appeals' ?></h5><a href="?module=disciplinary&a=appeal_add" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= $isAr?'جديد':'New' ?></a></div>
        <div class="table-responsive" style="max-height:calc(100vh/3);overflow-y:auto;"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>#</th><th><?= $isAr?'الحالة':'Case' ?></th><th><?= $isAr?'الموظف':'Employee' ?></th><th><?= $isAr?'تاريخ الطعن':'Appeal Date' ?></th><th><?= $isAr?'إجراءات':'Actions' ?></th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
        <tr><td><?= (int)$r['id'] ?></td><td>#<?= (int)$r['case_id'] ?></td><td><?= htmlspecialchars($isAr ? $r['empfullnamear'] : $r['empfullname']) ?></td><td><?= htmlspecialchars($r['appeal_date']) ?></td><td>
            <a href="?module=disciplinary&a=appeal_edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
            <form method="post" action="?module=disciplinary&a=omit" style="display:inline"><input type="hidden" name="type" value="appeals"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= $isAr?'تأكيد؟':'Confirm?' ?>')"><i class="bi bi-trash"></i></button></form>
        </td></tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="5" class="text-center text-muted"><?= $isAr?'لا توجد سجلات':'No records' ?></td></tr><?php endif; ?>
        </tbody></table></div>
        <?php
    }
}

// ============================================================
// DEFAULT
// ============================================================
else {
    echo '<div class="alert alert-info">' . ($isAr ? 'اختر تبويباً أعلاه.' : 'Select a tab above.') . '</div>';
}
?>
