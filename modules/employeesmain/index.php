<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

/**
 * EmployeesMain - custom module (NID deduction, cascading updates to balances/leaves)
 */
require_login();
require_once __DIR__ . '/../../includes/helpers.php';
$module = 'employeesmain';
$isAr = lang() === 'ar';
$u = current_user();
$canAdd = user_can($module, 'add');
$canEdit = user_can($module, 'edit');
$canDel = user_can($module, 'del');

function nid_deduce($nid, $isAr) {
    $nid = trim((string)$nid);
    $dop = ''; $gender = '';
    if (strlen($nid) >= 7) {
        $c = substr($nid, 0, 1);
        $century = ($c === '2') ? '19' : (($c === '3') ? '20' : '19');
        // National ID: century | year(2) | month(2) | day(2) ...
        $yy = substr($nid, 1, 2);
        $mm = substr($nid, 3, 2);
        $dd = substr($nid, 5, 2);
        $candidate = $century . $yy . '-' . $mm . '-' . $dd;
        if (checkdate((int)$mm, (int)$dd, (int)($century . $yy))) {
            $dop = $candidate;
        }
        // Gender comes from the last three digits: even = Male, odd = Female.
        $gender = gender_from_nid($nid);
    }
    return ['dop' => $dop, 'gender' => $gender];
}

function retire_dor($dop) {
    if (!$dop) return '';
    $yb = date('Y', strtotime($dop));
    $rp = fetchRow("SELECT serviceyears FROM " . table('retireplan') . " WHERE yearofbirth=? AND omitted=0 LIMIT 1", [$yb]);
    if (!$rp) return '';
    $sy = (int)$rp['serviceyears'];
    return date('Y-m-d', strtotime($dop . " +{$sy} years - 1 day"));
}

/**
 * Working-day flags stored on each employee. 1 = working day, 0 = not a workday.
 * Defaults follow the current weekend setting (Sunday/Saturday).
 */
function employee_workday_fields() {
    return [
        'sunworkday' => ['en' => 'Sunday',    'ar' => 'الأحد',    'default' => 0],
        'monworkday' => ['en' => 'Monday',    'ar' => 'الاثنين',  'default' => 1],
        'tueworkday' => ['en' => 'Tuesday',   'ar' => 'الثلاثاء', 'default' => 1],
        'wedworkday' => ['en' => 'Wednesday', 'ar' => 'الأربعاء', 'default' => 1],
        'thuworkday' => ['en' => 'Thursday',  'ar' => 'الخميس',   'default' => 1],
        'friworkday' => ['en' => 'Friday',    'ar' => 'الجمعة',   'default' => 1],
        'satworkday' => ['en' => 'Saturday',  'ar' => 'السبت',    'default' => 0],
    ];
}

function employee_workdays_input($isAr, array $values = []) {
    echo '<div class="mb-3"><label class="form-label">' . ($isAr ? 'أيام العمل' : 'Workdays') . '</label>';
    echo '<div class="d-flex flex-wrap gap-3">';
    foreach (employee_workday_fields() as $col => $meta) {
        $checked = array_key_exists($col, $values)
            ? !empty($values[$col])
            : !empty($meta['default']);
        $label = $isAr ? $meta['ar'] : $meta['en'];
        echo '<div class="form-check">'
            . '<input class="form-check-input" type="checkbox" name="' . $col . '" id="' . $col . '" value="1"' . ($checked ? ' checked' : '') . '>'
            . '<label class="form-check-label" for="' . $col . '">' . htmlspecialchars($label) . '</label>'
            . '</div>';
    }
    echo '</div>';
    echo '<div class="form-text">' . ($isAr
        ? 'حدد أيام العمل لهذا الموظف. الأيام غير المحددة لا تُحتسب في الإجازات التي لا تشمل عطلات نهاية الأسبوع.'
        : 'Select the days this employee works. Unchecked days are not counted for leave types that exclude weekends.') . '</div>';
    echo '</div>';
}

/**
 * Shared NID JavaScript for the add/edit forms.
 * - Gender: last three digits, even = Male / odd = Female.
 * - When $withDob is true it also fills the birth and retirement dates.
 */
function employees_nid_script($withDob = false) {
    ?>
<script>
function uhrGenderFromNid(nid) {
    var digits = (nid || "").replace(/\D/g, "");
    if (digits.length < 3) return "";
    var last3 = parseInt(digits.slice(-3), 10);
    return (last3 % 2 === 0) ? "Male" : "Female";
}
function uhrApplyGender(nid) {
    var gender = uhrGenderFromNid(nid);
    if (!gender) return;
    var isAr = document.documentElement.lang === "ar";
    var label = isAr ? (gender === "Male" ? "ذكر" : "أنثى") : gender;
    var field = document.getElementById("gender_field");
    var hidden = document.getElementById("gender_hidden");
    if (field) {
        field.innerHTML = '<option value="' + gender + '" selected>' + label + '</option>';
        if (typeof uhrRefreshSelect2 === "function") uhrRefreshSelect2("gender_field");
    }
    if (hidden) hidden.value = gender;
}
function uhrDeduceFromNid(nid) {
    nid = (nid || "").trim();
    if (nid.replace(/\D/g, "").length < 14) return;
    <?php if ($withDob): ?>
    var c = nid.charAt(0);
    var century = (c === "2") ? "19" : ((c === "3") ? "20" : "19");
    var dop = century + nid.substring(1, 3) + "-" + nid.substring(3, 5) + "-" + nid.substring(5, 7);
    var d = new Date(dop);
    if (isNaN(d.getTime())) dop = "";
    var dopField = document.getElementById("dop_field");
    if (dopField) dopField.value = dop;
    if (dop) {
        var dorField = document.getElementById("dor_field");
        fetch("?module=employeesmain&a=retire_dor&dop=" + encodeURIComponent(dop))
            .then(function(r) { return r.text(); })
            .then(function(t) { if (dorField) dorField.value = t.trim(); });
    }
    <?php endif; ?>
    uhrApplyGender(nid);
}
document.addEventListener("DOMContentLoaded", function() {
    var nidInput = document.getElementById("nid_field");
    if (!nidInput) return;
    ["input", "keyup", "blur", "focusout"].forEach(function(evt) {
        nidInput.addEventListener(evt, function() { uhrDeduceFromNid(this.value); });
    });
    uhrDeduceFromNid(nidInput.value);
});
</script>
    <?php
}

$departments  = fetchAll("SELECT id, depname, depnamear FROM " . table('depstructure') . " WHERE omitted=0 ORDER BY depname ASC");
$statuses     = fetchAll("SELECT id, empstatusname, empstatusnamear FROM " . table('empstatus') . " WHERE omitted=0 ORDER BY empstatusname ASC");
$degrees      = fetchAll("SELECT id, degreename, degreenamear, employeesgroup_id FROM " . table('empdegrees') . " WHERE omitted=0 ORDER BY degreename ASC");
$types        = fetchAll("SELECT id, emptypename, emptypenamear FROM " . table('employeestypes') . " WHERE omitted=0 ORDER BY emptypename ASC");
$groups       = fetchAll("SELECT id, empgroupname, empgroupnamear FROM " . table('employeesgroup') . " WHERE omitted=0 ORDER BY empgroupname ASC");
$jobgroups    = fetchAll("SELECT id, jobgroupname, jobgroupnamear FROM " . table('jobgroups') . " WHERE omitted=0 ORDER BY jobgroupname ASC");
$employees    = fetchAll("SELECT id, empfullname, empfullnamear FROM " . table('employeesmain') . " WHERE omitted=0 ORDER BY empfullname ASC");

$action = $_GET['a'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$row = $id ? fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? LIMIT 1", [$id]) : null;

// Handle AJAX retirement date lookup
if ($action === 'retire_dor' && !empty($_GET['dop'])) {
    $dop = trim($_GET['dop']);
    ajax_output(retire_dor($dop), 'text/plain; charset=utf-8');
}

// ---------- EXPORT ----------
if ($action === 'export') {
    require_permission($module, 'view');
    $fmt = $_GET['fmt'] ?? 'csv';
    $search = trim($_GET['search'] ?? '');
    $where = "omitted = 0";
    $params = [];
    if ($search !== '') {
        $norm = arabic_normalize($search);
        $where .= " AND (empfullname LIKE ? OR empfullnamear LIKE ? OR nid LIKE ?)";
        $params = ['%'.$search.'%', '%'.$norm.'%', '%'.$search.'%'];
    }
    $rows = fetchAll("SELECT empfullname AS 'Full Name', empfullnamear AS 'Full Name (Arabic)',
                             email AS 'Email', nid AS 'National ID', insuranceid AS 'Insurance ID',
                             depname AS 'Department', depnamear AS 'Department (Arabic)',
                             empstatusname AS 'Status', empstatusnamear AS 'Status (Arabic)',
                             currentdegreename AS 'Degree', currentdegreenamear AS 'Degree (Arabic)',
                             empgender AS 'Gender'
                      FROM " . table('employeesmain') . " WHERE $where ORDER BY empfullname", $params);
    if ($rows === false) { set_flash('error', 'Database not available.'); header('Location: ?module=home'); exit; }
    if (empty($rows)) { set_flash('error', 'No data to export.'); header('Location: ?module=employeesmain'); exit; }
    $headers = array_keys($rows[0]);
    $dataRows = [];
    foreach ($rows as $r) { $dataRows[] = array_values($r); }
    do_export($fmt, 'employeesmain_export', $isAr ? 'تقرير الموظفين' : 'Employees Report', $headers, $dataRows);
    exit;
}

$opt = function($list, $value, $labelAr, $labelEn, $selected = null, $allowEmpty = true) use ($isAr) {
    $out = '';
    if ($allowEmpty) $out .= '<option value="">-- ' . ($isAr ? 'اختر' : 'Select') . ' --</option>';
    foreach ($list as $r) {
        $sel = ($selected != '' && $r[$value] == $selected) ? ' selected' : '';
        $label = $isAr ? $r[$labelAr] : $r[$labelEn];
        $out .= '<option value="' . htmlspecialchars($r[$value]) . '"' . $sel . '>'
            . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
    }
    return $out;
};

$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    require_permission($module, $action === 'add' ? 'add' : 'edit');
    $nid = trim($_POST['nid'] ?? '');
    $insuranceid = trim($_POST['insuranceid'] ?? '');
    $biometricid = trim($_POST['biometricid'] ?? '');
    if (strlen($nid) < 14) {
        set_flash('error', 'National ID must be at least 14 characters.');
    } elseif ($nid !== '' && fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE nid = ? AND omitted = 0 " . ($action === 'edit' && $id ? "AND id != ?" : "") . " LIMIT 1", $action === 'edit' && $id ? [$nid, $id] : [$nid])) {
        set_flash('error', 'An employee with this National ID already exists.');
        header('Location: ?module=employeesmain&a=' . $action . ($action === 'edit' && $id ? '&id=' . $id : '')); exit;
    } elseif ($insuranceid !== '' && fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE insuranceid = ? AND insuranceid != '' AND omitted = 0 " . ($action === 'edit' && $id ? "AND id != ?" : "") . " LIMIT 1", $action === 'edit' && $id ? [$insuranceid, $id] : [$insuranceid])) {
        set_flash('error', 'An employee with this Insurance ID already exists.');
        header('Location: ?module=employeesmain&a=' . $action . ($action === 'edit' && $id ? '&id=' . $id : '')); exit;
    } elseif ($biometricid !== '' && fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE biometricid = ? AND biometricid != '' AND omitted = 0 " . ($action === 'edit' && $id ? "AND id != ?" : "") . " LIMIT 1", $action === 'edit' && $id ? [$biometricid, $id] : [$biometricid])) {
        set_flash('error', 'An employee with this Biometric ID already exists.');
        header('Location: ?module=employeesmain&a=' . $action . ($action === 'edit' && $id ? '&id=' . $id : '')); exit;
    } else {
        $deduced = nid_deduce($nid, $isAr);
        $dor = retire_dor($deduced['dop']);
        $data = [
            'empfullname'        => trim($_POST['empfullname'] ?? ''),
            'empfullnamear'      => trim($_POST['empfullnamear'] ?? ''),
            'email'              => trim($_POST['email'] ?? ''),
            'nid'                => $nid,
            'insuranceid'        => trim($_POST['insuranceid'] ?? ''),
            'dop'                => trim($_POST['dop'] ?? '') ?: $deduced['dop'],
            'dor'                => trim($_POST['dor'] ?? '') ?: $dor,
            'empgender'          => $deduced['gender'] !== '' ? $deduced['gender'] : trim($_POST['empgender'] ?? ''),
            'depid'              => ($_POST['depid'] ?? '') ?: null,
            'biometricid'        => trim($_POST['biometricid'] ?? ''),
            'empstatusid'        => ($_POST['empstatusid'] ?? '') ?: null,
            'currentdegreeid'    => ($_POST['currentdegreeid'] ?? '') ?: null,
            'emptypeid'          => ($_POST['emptypeid'] ?? '') ?: null,
            'empgroup'           => ($_POST['empgroup'] ?? '') ?: null,
            'empsuperiorid'      => ($_POST['empsuperiorid'] ?? '') ?: null,
            'degreeacquireddate' => trim($_POST['degreeacquireddate'] ?? '') ?: null,
            'senioritydate'      => trim($_POST['senioritydate'] ?? '') ?: null,
            'jobgroupid'         => ($_POST['jobgroupid'] ?? '') ?: null,
        ];
        foreach (employee_workday_fields() as $col => $meta) {
            $data[$col] = !empty($_POST[$col]) ? 1 : 0;
        }
        // resolve names
        if ($data['depid']) {
            $p = fetchRow("SELECT depname, depnamear FROM " . table('depstructure') . " WHERE id=?", [$data['depid']]);
            $data['depname'] = $p['depname'] ?? ''; $data['depnamear'] = $p['depnamear'] ?? '';
        } else { $data['depname'] = ''; $data['depnamear'] = ''; }
        if ($data['empstatusid']) {
            $s = fetchRow("SELECT empstatusname, empstatusnamear FROM " . table('empstatus') . " WHERE id=?", [$data['empstatusid']]);
            $data['empstatusname'] = $s['empstatusname'] ?? ''; $data['empstatusnamear'] = $s['empstatusnamear'] ?? '';
        } else { $data['empstatusname'] = ''; $data['empstatusnamear'] = ''; }
        if ($data['currentdegreeid']) {
            $g = fetchRow("SELECT degreename, degreenamear FROM " . table('empdegrees') . " WHERE id=?", [$data['currentdegreeid']]);
            $data['currentdegreename'] = $g['degreename'] ?? ''; $data['currentdegreenamear'] = $g['degreenamear'] ?? '';
        } else { $data['currentdegreename'] = ''; $data['currentdegreenamear'] = ''; }
        if ($data['emptypeid']) {
            $t = fetchRow("SELECT emptypename, emptypenamear FROM " . table('employeestypes') . " WHERE id=?", [$data['emptypeid']]);
            $data['emptypename'] = $t['emptypename'] ?? ''; $data['emptypenamear'] = $t['emptypenamear'] ?? '';
        } else { $data['emptypename'] = ''; $data['emptypenamear'] = ''; }
        if ($data['empgroup']) {
            $gr = fetchRow("SELECT empgroupname, empgroupnamear FROM " . table('employeesgroup') . " WHERE id=?", [$data['empgroup']]);
            $data['empgroupname'] = $gr['empgroupname'] ?? ''; $data['empgroupnamear'] = $gr['empgroupnamear'] ?? '';
        } else { $data['empgroupname'] = ''; $data['empgroupnamear'] = ''; }
        if ($data['empsuperiorid']) {
            $sup = fetchRow("SELECT empfullname, empfullnamear FROM " . table('employeesmain') . " WHERE id=?", [$data['empsuperiorid']]);
            $data['empsuperiorname'] = $sup['empfullname'] ?? ''; $data['empsuperiornamear'] = $sup['empfullnamear'] ?? '';
        } else { $data['empsuperiorname'] = ''; $data['empsuperiornamear'] = ''; }
        if ($data['jobgroupid']) {
            $jg = fetchRow("SELECT jobgroupname, jobgroupnamear FROM " . table('jobgroups') . " WHERE id=?", [$data['jobgroupid']]);
            $data['jobgroupname'] = $jg['jobgroupname'] ?? ''; $data['jobgroupnamear'] = $jg['jobgroupnamear'] ?? '';
        } else { $data['jobgroupname'] = ''; $data['jobgroupnamear'] = ''; }

        if ($data['empfullname'] === '' || $data['empfullnamear'] === '') {
            $formError = 'Employee full name (Arabic & English) is required.';
        } elseif (array_sum(array_map(function ($col) use ($data) {
            return !empty($data[$col]) ? 1 : 0;
        }, array_keys(employee_workday_fields()))) === 0) {
            $formError = 'Select at least one working day for this employee.';
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
                $insertResult = q("INSERT INTO " . table('employeesmain') . " (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")", $params);
                if (!$insertResult) {
                    set_flash('error', 'Failed to add employee: ' . (db_error() ?: 'Unknown error'));
                } else {
                    $newId = (int)lastInsertId();
                // initialize leave balance for each leave type and fiscal year
                $fys = fetchAll("SELECT id, fstartdate, fenddate FROM " . table('fiscalyears') . " WHERE omitted=0");
                $lts = fetchAll("SELECT id, leavetypename, leavetypenamear FROM " . table('leavestypes') . " WHERE omitted=0");
                foreach ($fys as $fy) {
                    foreach ($lts as $lt) {
                        q("INSERT INTO " . table('leavesbalances') . "
                            (rguid, empid, empfullname, empfullnamear, leavetypeid, leavetypename, leavetypenamear,
                             fiscalyearid, fstartdate, fenddate,
                             carryoverbalance, initialbalance, usedbalance, remainingbalance,
                             dtadded, addedbyid, addedby, addedbyname, addedbynamear,
                             lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
                            VALUES (?,?,?,?,?,?,?,?,?,?,0,0,0,0,?,?,?,?,?,?,?,?,?,?,0)",
                            [bin2hex(random_bytes(16)), $newId, $data['empfullname'], $data['empfullnamear'],
                             $lt['id'], $lt['leavetypename'], $lt['leavetypenamear'],
                             $fy['id'], $fy['fstartdate'], $fy['fenddate'],
                             $now, $u['id'], $u['username'], $an, $an,
                             $now, $u['id'], $u['username'], $an, $an]);
                    }
                }
                // Create login credentials for the new employee
                $empUsername = $data['nid'];
                $empEmail = !empty($data['email']) ? $data['email'] : $data['nid'] . '@unithr.local';
                $encPass = password_hash('user1234', PASSWORD_DEFAULT);
                $existingUser = fetchRow("SELECT id FROM " . table('users') . " WHERE username = ? OR email = ? LIMIT 1", [$empUsername, $empEmail]);
                if (!$existingUser) {
                    q("INSERT INTO " . table('users') . " (rguid, username, email, password, is_admin, is_superadmin, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,?,?,?,?,? ,?,?,?,?,?,?,?,0)", [
                        bin2hex(random_bytes(16)), $empUsername, $empEmail, $encPass, 0, 0,
                        $u['id'], $u['username'], $u['username_ar'] ?? $u['username'], $u['username_ar'] ?? $u['username'],
                        date('Y-m-d H:i:s'), $u['id'], $u['username'], $u['username_ar'] ?? $u['username'], $u['username_ar'] ?? $u['username']
                    ]);
                }
                set_flash('success', 'Employee added. Login credentials created (username: ' . $empUsername . ', password: user1234). Leave balances initialized for all leave types and fiscal years.');
                header('Location: ?module=employeesmain'); exit;
                }
            } elseif ($action === 'edit' && $id) {
                shadow_log('employeesmain', $id, $row, 'update');
                $sets = []; $params = [];
                foreach ($data as $k => $v) { $sets[] = '`'.$k.'`=?'; $params[] = $v; }
                $sets[] = '`lastedit`=?';   $params[] = $now;
                $sets[] = '`editedbyid`=?'; $params[] = $u['id'];
                $sets[] = '`editedby`=?';   $params[] = $u['username'];
                $sets[] = '`editedbyname`=?';   $params[] = $an;
                $sets[] = '`editedbynamear`=?'; $params[] = $an;
                $params[] = $id;
                q("UPDATE " . table('employeesmain') . " SET " . implode(',', $sets) . " WHERE id=?", $params);
                // cascade: update related balances and leaves with new names
                q("UPDATE " . table('leavesbalances') . " SET empfullname=?, empfullnamear=? WHERE empid=? AND omitted=0",
                    [$data['empfullname'], $data['empfullnamear'], $id]);
                q("UPDATE " . table('empleaves') . " SET empfullname=?, empfullnamear=? WHERE empid=? AND omitted=0",
                    [$data['empfullname'], $data['empfullnamear'], $id]);
                set_flash('success', 'Employee updated. Related balances and leave records updated.');
                header('Location: ?module=employeesmain'); exit;
            }
        }
    }
    // repopulate row for form on error
    if ($row === null && $action === 'edit') $row = $data;
    if ($action === 'add') $row = $data;
}

if ($action === 'del' && $id) {
    require_permission($module, 'del');
    $row = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? LIMIT 1", [$id]);
    if (isset($_GET['confirm']) && $_GET['confirm'] === '1') {
        $bal = fetchRow("SELECT COUNT(*) c FROM " . table('leavesbalances') . " WHERE empid=? AND omitted=0", [$id]);
        $lev = fetchRow("SELECT COUNT(*) c FROM " . table('empleaves') . " WHERE empid=? AND omitted=0", [$id]);
        if ((int)$bal['c'] > 0 || (int)$lev['c'] > 0) {
            set_flash('error', 'Cannot omit: employee has leave balances or leave records.');
        } else {
            if ($row) shadow_log('employeesmain', $id, $row, 'omit');
            q("UPDATE " . table('employeesmain') . " SET omitted=1 WHERE id=?", [$id]);
            set_flash('success', 'Employee omitted.');
        }
        header('Location: ?module=employeesmain'); exit;
    }
    echo '<div class="alert alert-warning">
            <h4>Omit employee?</h4>
            <p>Records are not deleted; they are marked omitted and hidden from reports.</p>
            <a href="?module=employeesmain&a=del&id='.$id.'&confirm=1" class="btn btn-danger">Yes, Omit</a>
            <a href="?module=employeesmain" class="btn btn-secondary">Cancel</a>
          </div>';
    return;
}

if ($action === 'add') {
    require_permission($module, 'add');
    $d = [];
    $ded = nid_deduce('', $isAr);

    $dor = retire_dor($ded['dop']);
    if ($formError !== '') {
        echo '<div class="alert alert-danger">'.htmlspecialchars($formError).'</div>';
    }
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-plus-lg me-2"></i>'.($isAr?'إضافة موظف':'Add Employee').'</h2>
            <a href="?module=employeesmain" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
          </div>';
    echo '<form method="post" action="?module=employeesmain&a=add">';
    echo field_input(['name'=>'empfullname','label'=>'Full Name','label_ar'=>'الاسم الكامل','type'=>'text','required'=>true], '');
    echo field_input(['name'=>'empfullnamear','label'=>'Full Name (Arabic)','label_ar'=>'الاسم الكامل بالعربية','type'=>'text','required'=>true], '');
    echo field_input(['name'=>'email','label'=>'Email','label_ar'=>'البريد الإلكتروني','type'=>'email'], '');
    echo field_input(['name'=>'nid','label'=>'National ID (min 14 chars)','label_ar'=>'الرقم القومي','type'=>'text','required'=>true,'placeholder'=>'e.g. 29001010123456','extra'=>'id="nid_field"'], '');
    // Gender (and birth/retirement dates) update live from the national ID.
    employees_nid_script(true);
    echo field_input(['name'=>'insuranceid','label'=>'Insurance ID','label_ar'=>'الرقم التأميني','type'=>'text'], '');
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'تاريخ الميلاد (من الرقم القومي)':'Date of Birth (from NID)').'</label>'
        .'<input type="date" name="dop" id="dop_field" class="form-control" value="'.htmlspecialchars($ded['dop']).'" readonly></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'تاريخ التقاعد':'Retirement Date').'</label>'
        .'<input type="date" name="dor" id="dor_field" class="form-control" value="'.htmlspecialchars($dor).'" readonly></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'النوع':'Gender').'</label>'
        .'<select id="gender_field" class="form-select" disabled>'
        .'<option value="'.htmlspecialchars($ded['gender']).'" selected>'.htmlspecialchars($isAr ? ($ded['gender'] === 'Male' ? 'ذكر' : 'أنثى') : $ded['gender']).'</option>'
        .'</select>'
        .'<input type="hidden" name="empgender" id="gender_hidden" value="'.htmlspecialchars($ded['gender']).'"></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'القسم':'Department').'</label>'
        .'<select name="depid" class="form-select">'
        . $opt($departments, 'id', 'depnamear', 'depname') . '</select></div>';
    echo field_input(['name'=>'biometricid','label'=>'Biometric ID','label_ar'=>'كود جهاز البصمة','type'=>'text'], '');
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'الحالة الوظيفية':'Job Status').'</label>'
        .'<select name="empstatusid" class="form-select">'
        . $opt($statuses, 'id', 'empstatusnamear', 'empstatusname') . '</select></div>';
        // Build degree options with data-group for client-side filtering by employee group
    $degOpts = '<option value="">-- ' . ($isAr ? 'اختر' : 'Select') . ' --</option>';
    foreach ($degrees as $d) {
        $g = $d['employeesgroup_id'] ?? '';
        $label = $isAr ? $d['degreenamear'] : $d['degreename'];
        $degOpts .= '<option value="' . htmlspecialchars($d['id']) . '" data-group="' . htmlspecialchars($g) . '">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
    }
    echo '<script>window.UHR_INIT_DEGREE = ' . json_encode($row['currentdegreeid'] !== null ? (int)$row['currentdegreeid'] : null) . ';</script>';
    echo '<div class="mb-3"><label class="form-label">' . ($isAr ? 'مجموعة الموظفين' : 'Employee Group') . '</label>'
        . '<select name="empgroup" id="empgroup_field" class="form-select">'
        . $opt($groups, 'id', 'empgroupnamear', 'empgroupname', $row['empgroup']) . '</select></div>';
    echo '<div class="mb-3"><label class="form-label">' . ($isAr ? 'الدرجة الوظيفية' : 'Job Degree') . '</label>'
        . '<select name="currentdegreeid" id="degree_field" class="form-select" data-no-s2-init="1">' . $degOpts . '</select></div>';
    echo '<div class="mb-3"><label class="form-label">' . ($isAr ? 'نوع الموظف' : 'Employee Type') . '</label>'
        . '<select name="emptypeid" class="form-select">'
        . $opt($types, 'id', 'emptypenamear', 'emptypename', $row['emptypeid']) . '</select></div>';
    echo <<<'JS'
<script>
(function(){
  var groupSel = document.querySelector('select[name="empgroup"]');
  var degSel = document.querySelector('select[name="currentdegreeid"]');
  if (!degSel) return;
  function currentGroup(){ return groupSel ? groupSel.value : ''; }
  function degMatcher(params, data) {
    if (!data.id) return data;
    var g = currentGroup();
    if (g !== '') {
      var og = data.element ? data.element.getAttribute('data-group') : null;
      if (String(og) !== String(g)) return null;
    }
    if (params.term) {
      if (data.text.toUpperCase().indexOf(params.term.toUpperCase()) === -1) return null;
    }
    return data;
  }
  function initDegS2() {
    if (!window.jQuery || !jQuery.fn.select2) return;
    if (degSel.getAttribute('data-select2-id')) return;
    var isAr = document.documentElement.lang === 'ar';
    jQuery(degSel).select2({
      theme: 'bootstrap-5', width: '100%', language: isAr ? 'ar' : 'en',
      matcher: degMatcher, minimumResultsForSearch: 0
    });
  }
  if (groupSel && window.jQuery) {
    jQuery(groupSel).on('change', function(){
      var g = this.value;
      var cur = degSel.value;
      var cleared = false;
      if (cur !== '') {
        var curOpt = degSel.querySelector('option[value="' + cur + '"]');
        var curGroup = curOpt ? curOpt.getAttribute('data-group') : 'NOOPT';
        if (g !== '' && String(curGroup) !== String(g)) {
          degSel.value = '';
          cleared = true;
        }
      }
      if (window.jQuery && jQuery.fn.select2) {
        jQuery(degSel).trigger('change');
      }
      degSel.setAttribute('data-dbg', 'g=' + g + ' cur=' + cur + ' cleared=' + cleared);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ setTimeout(initDegS2, 50); });
  } else {
    setTimeout(initDegS2, 50);
  }
  if (window.UHR_INIT_DEGREE) {
    degSel.value = window.UHR_INIT_DEGREE;
  }
})();
</script>
JS;
employee_workdays_input($isAr, []);
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'مجموعة الوظائف':'Job Group').'</label>'
        .'<select name="jobgroupid" class="form-select"><option value="">-- Select --</option>'
        . $opt($jobgroups, 'id', 'jobgroupnamear', 'jobgroupname') . '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'المدير المباشر':'Direct Superior').'</label>'
        .'<select name="empsuperiorid" class="form-select">'
        . $opt($employees, 'id', 'empfullnamear', 'empfullname') . '</select></div>';
    echo '<div class="d-grid gap-2"><button class="btn btn-primary">Save</button>
          <a href="?module=employeesmain" class="btn btn-secondary">Cancel</a></div>';
    echo '</form>';
    return;
}

if ($action === 'edit' && $id && $row) {
    require_permission($module, 'edit');
    $ded = nid_deduce($row['nid'] ?? '', $isAr);
    $dor = retire_dor($row['dop'] ?? '');
    if ($formError !== '') {
        echo '<div class="alert alert-danger">'.htmlspecialchars($formError).'</div>';
    }
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-pencil-square me-2"></i>'.($isAr?'تعديل الموظف':'Edit Employee').'</h2>
            <a href="?module=employeesmain" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
          </div>';
    echo '<form method="post" action="?module=employeesmain&a=edit&id='.$id.'">';
    echo field_input(['name'=>'empfullname','label'=>'Full Name','label_ar'=>'الاسم الكامل','type'=>'text','required'=>true], $row['empfullname']);
    echo field_input(['name'=>'empfullnamear','label'=>'Full Name (Arabic)','label_ar'=>'الاسم الكامل بالعربية','type'=>'text','required'=>true], $row['empfullnamear']);
    echo field_input(['name'=>'email','label'=>'Email','label_ar'=>'البريد الإلكتروني','type'=>'email'], $row['email']);
    echo field_input(['name'=>'nid','label'=>'National ID','label_ar'=>'','type'=>'text','required'=>true,'extra'=>'id="nid_field"'], $row['nid']);
    echo field_input(['name'=>'insuranceid','label'=>'Insurance ID','label_ar'=>'','type'=>'text'], $row['insuranceid']);
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'تاريخ الميلاد (من الرقم القومي)':'Date of Birth (from NID)').'</label>'
        .'<input type="date" name="dop" class="form-control" value="'.htmlspecialchars($row['dop']).'"></div>';
    echo field_input(['name'=>'dor','label'=>'Retirement Date','label_ar'=>'تاريخ التقاعد','type'=>'date'], $row['dor']);
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'النوع':'Gender').'</label>'
        .'<select id="gender_field" class="form-select" disabled>'
        .'<option value="'.htmlspecialchars($ded['gender']).'" selected>'.htmlspecialchars($isAr ? ($ded['gender'] === 'Male' ? 'ذكر' : 'أنثى') : $ded['gender']).'</option>'
        .'</select>'
        .'<input type="hidden" name="empgender" id="gender_hidden" value="'.htmlspecialchars($ded['gender']).'"></div>';
    // Recalculate gender live when the national ID changes.
    employees_nid_script(false);
    echo field_input(['name'=>'degreeacquireddate','label'=>'Degree Acquired Date','label_ar'=>'تاريخ الحصول على الدرجة','type'=>'date'], $row['degreeacquireddate']);
    echo field_input(['name'=>'senioritydate','label'=>'Seniority Date','label_ar'=>'تاريخ الأقدمية','type'=>'date'], $row['senioritydate']);
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'مجموعة الوظائف':'Job Group').'</label>'
        .'<select name="jobgroupid" class="form-select"><option value="">-- Select --</option>'
        . $opt($jobgroups, 'id', 'jobgroupnamear', 'jobgroupname', $row['jobgroupid']) . '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'القسم':'Department').'</label>'
        .'<select name="depid" class="form-select">'
        . $opt($departments, 'id', 'depnamear', 'depname', $row['depid']) . '</select></div>';
    echo field_input(['name'=>'biometricid','label'=>'Biometric ID','label_ar'=>'','type'=>'text'], $row['biometricid']);
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'الحالة الوظيفية':'Job Status').'</label>'
        .'<select name="empstatusid" class="form-select">'
        . $opt($statuses, 'id', 'empstatusnamear', 'empstatusname', $row['empstatusid']) . '</select></div>';
    echo '<script>window.UHR_DEGREES = ' . json_encode(array_map(function($d){return ['id'=>(int)$d['id'],'name'=>$d['degreename'],'nameAr'=>$d['degreenamear'],'group'=>($d['employeesgroup_id'] !== null ? (int)$d['employeesgroup_id'] : null)];}, $degrees), JSON_UNESCAPED_UNICODE) . '; window.UHR_INIT_DEGREE = ' . json_encode($row['currentdegreeid'] !== null ? (int)$row['currentdegreeid'] : null) . ';</script>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'مجموعة الموظفين':'Employee Group').'</label>'
        .'<select name="empgroup" id="empgroup_field" class="form-select">'
        . $opt($groups, 'id', 'empgroupnamear', 'empgroupname', $row['empgroup']) . '</select></div>';
    $degOpts = '<option value="">-- ' . ($isAr ? 'اختر' : 'Select') . ' --</option>';
    foreach ($degrees as $d) {
        $g = $d['employeesgroup_id'] ?? '';
        $label = $isAr ? $d['degreenamear'] : $d['degreename'];
        $degOpts .= '<option value="' . htmlspecialchars($d['id']) . '" data-group="' . htmlspecialchars($g) . '">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
    }
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'الدرجة الوظيفية':'Job Degree').'</label>'
        .'<select name="currentdegreeid" id="degree_field" class="form-select" data-no-s2-init="1">' . $degOpts . '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'نوع الموظف':'Employee Type').'</label>'
        .'<select name="emptypeid" class="form-select">'
        . $opt($types, 'id', 'emptypenamear', 'emptypename', $row['emptypeid']) . '</select></div>';
echo <<<'JS'
<script>
(function(){
  var groupSel = document.querySelector('select[name="empgroup"]');
  var degSel = document.querySelector('select[name="currentdegreeid"]');
  if (!degSel) return;
  function currentGroup(){ return groupSel ? groupSel.value : ''; }
  function degMatcher(params, data) {
    if (!data.id) return data;
    var g = currentGroup();
    if (g !== '') {
      var og = data.element ? data.element.getAttribute('data-group') : null;
      if (String(og) !== String(g)) return null;
    }
    if (params.term) {
      if (data.text.toUpperCase().indexOf(params.term.toUpperCase()) === -1) return null;
    }
    return data;
  }
  function initDegS2() {
    if (!window.jQuery || !jQuery.fn.select2) return;
    if (degSel.getAttribute('data-select2-id')) return;
    var isAr = document.documentElement.lang === 'ar';
    jQuery(degSel).select2({
      theme: 'bootstrap-5', width: '100%', language: isAr ? 'ar' : 'en',
      matcher: degMatcher, minimumResultsForSearch: 0
    });
  }
  if (groupSel && window.jQuery) {
    jQuery(groupSel).on('change', function(){
      var g = this.value;
      var cur = degSel.value;
      var cleared = false;
      if (cur !== '') {
        var curOpt = degSel.querySelector('option[value="' + cur + '"]');
        var curGroup = curOpt ? curOpt.getAttribute('data-group') : 'NOOPT';
        if (g !== '' && String(curGroup) !== String(g)) {
          degSel.value = '';
          cleared = true;
        }
      }
      if (window.jQuery && jQuery.fn.select2) {
        jQuery(degSel).trigger('change');
      }
      degSel.setAttribute('data-dbg', 'g=' + g + ' cur=' + cur + ' cleared=' + cleared);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ setTimeout(initDegS2, 50); });
  } else {
    setTimeout(initDegS2, 50);
  }
  if (window.UHR_INIT_DEGREE) {
    degSel.value = window.UHR_INIT_DEGREE;
  }
})();
</script>
JS;
    employee_workdays_input($isAr, $row);
    $supList = fetchAll("SELECT id, empfullname, empfullnamear FROM " . table('employeesmain') . " WHERE omitted=0 AND id <> ? ORDER BY empfullname ASC", [$id]);
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'المدير المباشر':'Direct Superior').'</label>'
        .'<select name="empsuperiorid" class="form-select">'
        . $opt($supList, 'id', 'empfullnamear', 'empfullname', $row['empsuperiorid']) . '</select></div>';
    echo '<div class="d-grid gap-2"><button class="btn btn-primary">Update</button>
          <a href="?module=employeesmain" class="btn btn-secondary">Cancel</a></div>';
    echo '</form>';
    return;
}

// ---------- VIEW (details) ----------
if ($action === 'view' && $id) {
    require_permission($module, 'view');
    $row = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
    if (!$row) { set_flash('error','Record not found.'); header('Location: ?module=employeesmain'); exit; }
    ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-person-lines-fill me-2"></i><?= $isAr ? 'تفاصيل الموظف' : 'Employee Details' ?></h2>
    <a href="?module=employeesmain" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'المعلومات الشخصية' : 'Personal Information' ?></div>
            <div class="card-body">
                <p><strong><?= $isAr ? 'الاسم' : 'Full Name' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['empfullnamear'] ?? '') : ($row['empfullname'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'الرقم القومي' : 'National ID' ?>:</strong> <?= htmlspecialchars($row['nid'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'اسم المستخدم (تسجيل الدخول)' : 'Login Username' ?>:</strong> <?= htmlspecialchars($row['nid'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'البريد الإلكتروني' : 'Email' ?>:</strong> <?= htmlspecialchars($row['email'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'تاريخ الميلاد' : 'Date of Birth' ?>:</strong> <?= htmlspecialchars($row['dop'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'تاريخ التقاعد' : 'Retirement Date' ?>:</strong> <?= htmlspecialchars($row['dor'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'النوع' : 'Gender' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['empgender'] === 'Male' ? 'ذكر' : 'أنثى') : ($row['empgender'] ?? '')) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'المعلومات الوظيفية' : 'Job Information' ?></div>
            <div class="card-body">
                <p><strong><?= $isAr ? 'القسم' : 'Department' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['depnamear'] ?? '') : ($row['depname'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'الحالة الوظيفية' : 'Status' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['empstatusnamear'] ?? '') : ($row['empstatusname'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'الدرجة' : 'Degree' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['currentdegreenamear'] ?? '') : ($row['currentdegreename'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'نوع الموظف' : 'Employee Type' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['emptypenamear'] ?? '') : ($row['emptypename'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'المجموعة' : 'Group' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['empgroupnamear'] ?? '') : ($row['empgroupname'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'المدير المباشر' : 'Direct Superior' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['empsuperiornamear'] ?? '') : ($row['empsuperiorname'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'رقم التأمين' : 'Insurance ID' ?>:</strong> <?= htmlspecialchars($row['insuranceid'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'البصمة' : 'Biometric ID' ?>:</strong> <?= htmlspecialchars($row['biometricid'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'تاريخ الحصول على الدرجة' : 'Degree Acquired Date' ?>:</strong> <?= htmlspecialchars($row['degreeacquireddate'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'تاريخ الأقدمية' : 'Seniority Date' ?>:</strong> <?= htmlspecialchars($row['senioritydate'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'مجموعة الوظائف' : 'Job Group' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['jobgroupnamear'] ?? '') : ($row['jobgroupname'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'أيام العمل' : 'Workdays' ?>:</strong></p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered text-center mb-0">
                        <thead class="table-light">
                            <tr>
                                <?php foreach (employee_workday_fields() as $col => $meta): ?>
                                    <th><?= htmlspecialchars($isAr ? $meta['ar'] : $meta['en']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php foreach (employee_workday_fields() as $col => $meta): ?>
                                    <td>
                                        <?php if (!empty($row[$col])): ?>
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        <?php else: ?>
                                            <i class="bi bi-x-circle text-secondary"></i>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="mb-3">
    <?php if ($canEdit): ?><a href="?module=employeesmain&a=edit&id=<?= $row['id'] ?>" class="btn btn-warning"><i class="bi bi-pencil me-1"></i><?= $isAr ? 'تعديل' : 'Edit' ?></a><?php endif; ?>
    <a href="?module=employeesmain" class="btn btn-secondary ms-2"><?= $isAr ? 'العودة' : 'Back' ?></a>
</div>
<?php
    return;
}

// LIST
require_permission($module, 'view');
$search = trim($_GET['search'] ?? '');
$where = "omitted = 0";
$params = [];
if ($search !== '') {
    $norm = arabic_normalize($search);
    $where .= " AND (empfullname LIKE ? OR empfullnamear LIKE ? OR nid LIKE ?)";
    $params = ['%'.$search.'%', '%'.$norm.'%', '%'.$search.'%'];
}
$rows = fetchAll("SELECT * FROM " . table('employeesmain') . " WHERE $where ORDER BY empfullname ASC", $params);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-person-lines-fill me-2"></i><?= $isAr ? 'الموظفين' : 'Employees Main' ?></h2>
    <div>
        <?php if ($canAdd): ?><a href="?module=employeesmain&a=add" class="btn btn-primary me-1"><i class="bi bi-plus me-1"></i><?= $isAr ? 'إضافة' : 'Add' ?></a><?php endif; ?>
        <a href="?module=employeesmain&a=export&fmt=csv" class="btn btn-outline-secondary btn-sm me-1">CSV</a>
        <a href="?module=employeesmain&a=export&fmt=xlsx" class="btn btn-outline-success btn-sm me-1">XLSX</a>
        <a href="?module=employeesmain&a=export&fmt=docx" class="btn btn-outline-primary btn-sm me-1">Word</a>
        <a href="?module=employeesmain&a=export&fmt=pdf" class="btn btn-outline-danger btn-sm me-1">PDF</a>
    </div>
</div>
<form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="module" value="employeesmain">
    <div class="col-md-4"><input type="search" name="search" class="form-control" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="<?= $isAr ? 'بحث (عربي/إنجليزي)...' : 'Search (Arabic/English)...' ?>"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
    <div class="col-md-2"><a href="?module=employeesmain" class="btn btn-outline-secondary w-100">Reset</a></div>
</form>
<div class="table-responsive" style="max-height: 40vh; overflow-y: auto;">
<table class="table table-striped table-hover align-middle table-sm">
<thead class="table-dark"><tr>
<th>#</th><th><?= $isAr ? 'الاسم' : 'Full Name' ?></th>
<th><?= $isAr ? 'الرقم القومي' : 'National ID' ?></th>
<th><?= $isAr ? 'القسم' : 'Department' ?></th>
<th><?= $isAr ? 'الحالة' : 'Status' ?></th>
<th><?= $isAr ? 'الدرجة' : 'Degree' ?></th>
<th>Actions</th>
</tr></thead>
<tbody>
<?php foreach ($rows as $i => $r): ?>
<tr>
<td><?= $i+1 ?></td>
<td><?= htmlspecialchars($isAr ? ($r['empfullnamear'] ?? '') : ($r['empfullname'] ?? '')) ?></td>
<td><?= htmlspecialchars($r['nid'] ?? '') ?></td>
<td><?= htmlspecialchars($isAr ? ($r['depnamear'] ?? '') : ($r['depname'] ?? '')) ?></td>
<td><?= htmlspecialchars($isAr ? ($r['empstatusnamear'] ?? '') : ($r['empstatusname'] ?? '')) ?></td>
<td><?= htmlspecialchars($isAr ? ($r['currentdegreenamear'] ?? '') : ($r['currentdegreename'] ?? '')) ?></td>
<td class="d-flex gap-1">
    <a href="?module=employeesmain&a=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
    <?php if ($canEdit): ?><a href="?module=employeesmain&a=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a><?php endif; ?>
    <?php if ($canDel): ?><a href="?module=employeesmain&a=del&id=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Omit this employee?')"><i class="bi bi-trash"></i></a><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
