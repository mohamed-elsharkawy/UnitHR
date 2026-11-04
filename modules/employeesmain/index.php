<?php
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
        // Egyptian NID: 13th digit (index 12) is gender — odd = Male, even = Female
        $genderDigit = isset($nid[12]) ? (int)$nid[12] : (int)substr($nid, -1);
        $gender = ($genderDigit % 2 !== 0) ? 'Male' : 'Female';
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

$departments  = fetchAll("SELECT id, depname, depnamear FROM " . table('depstructure') . " WHERE omitted=0 ORDER BY depname ASC");
$statuses     = fetchAll("SELECT id, empstatusname, empstatusnamear FROM " . table('empstatus') . " WHERE omitted=0 ORDER BY empstatusname ASC");
$degrees      = fetchAll("SELECT id, degreename, degreenamear FROM " . table('empdegrees') . " WHERE omitted=0 ORDER BY degreename ASC");
$types        = fetchAll("SELECT id, emptypename, emptypenamear FROM " . table('employeestypes') . " WHERE omitted=0 ORDER BY emptypename ASC");
$groups       = fetchAll("SELECT id, empgroupname, empgroupnamear FROM " . table('employeesgroup') . " WHERE omitted=0 ORDER BY empgroupname ASC");
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
    if ($rows === false) { set_flash('error', 'Database not available.'); header('Location: index.php?module=home'); exit; }
    if (empty($rows)) { set_flash('error', 'No data to export.'); header('Location: index.php?module=employeesmain'); exit; }
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    require_permission($module, $action === 'add' ? 'add' : 'edit');
    $nid = trim($_POST['nid'] ?? '');
    $insuranceid = trim($_POST['insuranceid'] ?? '');
    $biometricid = trim($_POST['biometricid'] ?? '');
    if (strlen($nid) < 14) {
        set_flash('error', 'National ID must be at least 14 characters.');
    } elseif ($nid !== '' && fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE nid = ? AND omitted = 0 " . ($action === 'edit' && $id ? "AND id != ?" : "") . " LIMIT 1", $action === 'edit' && $id ? [$nid, $id] : [$nid])) {
        set_flash('error', 'An employee with this National ID already exists.');
        header('Location: index.php?module=employeesmain&a=' . $action . ($action === 'edit' && $id ? '&id=' . $id : '')); exit;
    } elseif ($insuranceid !== '' && fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE insuranceid = ? AND insuranceid != '' AND omitted = 0 " . ($action === 'edit' && $id ? "AND id != ?" : "") . " LIMIT 1", $action === 'edit' && $id ? [$insuranceid, $id] : [$insuranceid])) {
        set_flash('error', 'An employee with this Insurance ID already exists.');
        header('Location: index.php?module=employeesmain&a=' . $action . ($action === 'edit' && $id ? '&id=' . $id : '')); exit;
    } elseif ($biometricid !== '' && fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE biometricid = ? AND biometricid != '' AND omitted = 0 " . ($action === 'edit' && $id ? "AND id != ?" : "") . " LIMIT 1", $action === 'edit' && $id ? [$biometricid, $id] : [$biometricid])) {
        set_flash('error', 'An employee with this Biometric ID already exists.');
        header('Location: index.php?module=employeesmain&a=' . $action . ($action === 'edit' && $id ? '&id=' . $id : '')); exit;
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
            'empgender'          => trim($_POST['empgender'] ?? '') ?: $deduced['gender'],
            'depid'              => ($_POST['depid'] ?? '') ?: null,
            'biometricid'        => trim($_POST['biometricid'] ?? ''),
            'empstatusid'        => ($_POST['empstatusid'] ?? '') ?: null,
            'currentdegreeid'    => ($_POST['currentdegreeid'] ?? '') ?: null,
            'emptypeid'          => ($_POST['emptypeid'] ?? '') ?: null,
            'empgroup'           => ($_POST['empgroup'] ?? '') ?: null,
            'empsuperiorid'      => ($_POST['empsuperiorid'] ?? '') ?: null,
        ];
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

        if ($data['empfullname'] === '' || $data['empfullnamear'] === '') {
            set_flash('error', 'Employee full name (Arabic & English) is required.');
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
                header('Location: index.php?module=employeesmain'); exit;
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
                header('Location: index.php?module=employeesmain'); exit;
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
        header('Location: index.php?module=employeesmain'); exit;
    }
    echo '<div class="alert alert-warning">
            <h4>Omit employee?</h4>
            <p>Records are not deleted; they are marked omitted and hidden from reports.</p>
            <a href="index.php?module=employeesmain&a=del&id='.$id.'&confirm=1" class="btn btn-danger">Yes, Omit</a>
            <a href="index.php?module=employeesmain" class="btn btn-secondary">Cancel</a>
          </div>';
    return;
}

if ($action === 'add') {
    require_permission($module, 'add');
    $d = [];
    $ded = nid_deduce('', $isAr);

    $dor = retire_dor($ded['dop']);
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-plus-lg me-2"></i>'.($isAr?'إضافة موظف':'Add Employee').'</h2>
            <a href="index.php?module=employeesmain" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
          </div>';
    echo '<form method="post" action="index.php?module=employeesmain&a=add">';
    echo field_input(['name'=>'empfullname','label'=>'Full Name','label_ar'=>'الاسم الكامل','type'=>'text','required'=>true], '');
    echo field_input(['name'=>'empfullnamear','label'=>'Full Name (Arabic)','label_ar'=>'الاسم الكامل بالعربية','type'=>'text','required'=>true], '');
    echo field_input(['name'=>'email','label'=>'Email','label_ar'=>'البريد الإلكتروني','type'=>'email'], '');
    echo field_input(['name'=>'nid','label'=>'National ID (min 14 chars)','label_ar'=>'','type'=>'text','required'=>true,'placeholder'=>'e.g. 29001010123456'], '');
    // Add onblur event to NID field via JavaScript
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        var nidInput = document.querySelector("input[name=\"nid\"]");
        if (nidInput) {
            nidInput.addEventListener("blur", function() { deduceFromNID(this.value); });
            nidInput.addEventListener("input", function() {
                if (this.value.length >= 14) { deduceFromNID(this.value); }
            });
        }
    });
    function deduceFromNID(nid) {
        nid = (nid || "").trim();
        if (nid.length < 14) return;
        var c = nid.charAt(0);
        var century = (c === "2") ? "19" : ((c === "3") ? "20" : "19");
        var yy = nid.substring(1, 3);
        var mm = nid.substring(3, 5);
        var dd = nid.substring(5, 7);
        var year = century + yy;
        var dop = year + "-" + mm + "-" + dd;
        // Validate date
        var d = new Date(dop);
        if (isNaN(d.getTime())) { dop = ""; }
        // Gender: odd = Male, even = Female
        var genderDigit = parseInt(nid.charAt(12) || nid.charAt(nid.length - 1), 10);
        var gender = (genderDigit % 2 !== 0) ? "Male" : "Female";
        var genderAr = (gender === "Male") ? "ذكر" : "أنثى";
        // Update fields
        var dopField = document.getElementById("dop_field");
        var dorField = document.getElementById("dor_field");
        var genderField = document.getElementById("gender_field");
        var genderHidden = document.getElementById("gender_hidden");
        if (dopField) dopField.value = dop;
        if (genderField) {
            genderField.innerHTML = "<option value=\"" + gender + "\" selected>" + (document.documentElement.lang === "ar" ? genderAr : gender) + "</option>";
        }
        if (genderHidden) genderHidden.value = gender;
        // Retirement date - fetch from server
        if (dop) {
            fetch("index.php?module=employeesmain&a=retire_dor&dop=" + encodeURIComponent(dop))
                .then(function(r) { return r.text(); })
                .then(function(t) { if (dorField) dorField.value = t.trim(); });
        }
    }
    </script>';
    echo field_input(['name'=>'insuranceid','label'=>'Insurance ID','label_ar'=>'','type'=>'text'], '');
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'تاريخ الميلاد (من الرقم القومي)':'Date of Birth (from NID)').'</label>'
        .'<input type="date" name="dop" id="dop_field" class="form-control" value="'.htmlspecialchars($ded['dop']).'" readonly></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'تاريخ التقاعد':'Retirement Date').'</label>'
        .'<input type="date" name="dor" id="dor_field" class="form-control" value="'.htmlspecialchars($dor).'" readonly></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'النوع':'Gender').'</label>'
        .'<select name="empgender" id="gender_field" class="form-select" disabled>'
        .'<option value="'.htmlspecialchars($ded['gender']).'" selected>'.htmlspecialchars($isAr ? ($ded['gender'] === 'Male' ? 'ذكر' : 'أنثى') : $ded['gender']).'</option>'
        .'</select>'
        .'<input type="hidden" name="empgender" id="gender_hidden" value="'.htmlspecialchars($ded['gender']).'"></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'القسم':'Department').'</label>'
        .'<select name="depid" class="form-select">'
        . $opt($departments, 'id', 'depnamear', 'depname') . '</select></div>';
    echo field_input(['name'=>'biometricid','label'=>'Biometric ID','label_ar'=>'','type'=>'text'], '');
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'الحالة الوظيفية':'Job Status').'</label>'
        .'<select name="empstatusid" class="form-select">'
        . $opt($statuses, 'id', 'empstatusnamear', 'empstatusname') . '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'الدرجة الوظيفية':'Job Degree').'</label>'
        .'<select name="currentdegreeid" class="form-select">'
        . $opt($degrees, 'id', 'degreenamear', 'degreename') . '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'نوع الموظف':'Employee Type').'</label>'
        .'<select name="emptypeid" class="form-select">'
        . $opt($types, 'id', 'emptypenamear', 'emptypename') . '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'مجموعة الموظفين':'Employee Group').'</label>'
        .'<select name="empgroup" class="form-select">'
        . $opt($groups, 'id', 'empgroupnamear', 'empgroupname') . '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'المدير المباشر':'Direct Superior').'</label>'
        .'<select name="empsuperiorid" class="form-select">'
        . $opt($employees, 'id', 'empfullnamear', 'empfullname') . '</select></div>';
    echo '<div class="d-grid gap-2"><button class="btn btn-primary">Save</button>
          <a href="index.php?module=employeesmain" class="btn btn-secondary">Cancel</a></div>';
    echo '</form>';
    return;
}

if ($action === 'edit' && $id && $row) {
    require_permission($module, 'edit');
    $ded = nid_deduce($row['nid'] ?? '', $isAr);
    $dor = retire_dor($row['dop'] ?? '');
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-pencil-square me-2"></i>'.($isAr?'تعديل الموظف':'Edit Employee').'</h2>
            <a href="index.php?module=employeesmain" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
          </div>';
    echo '<form method="post" action="index.php?module=employeesmain&a=edit&id='.$id.'">';
    echo field_input(['name'=>'empfullname','label'=>'Full Name','label_ar'=>'الاسم الكامل','type'=>'text','required'=>true], $row['empfullname']);
    echo field_input(['name'=>'empfullnamear','label'=>'Full Name (Arabic)','label_ar'=>'الاسم الكامل بالعربية','type'=>'text','required'=>true], $row['empfullnamear']);
    echo field_input(['name'=>'email','label'=>'Email','label_ar'=>'البريد الإلكتروني','type'=>'email'], $row['email']);
    echo field_input(['name'=>'nid','label'=>'National ID','label_ar'=>'','type'=>'text','required'=>true], $row['nid']);
    echo field_input(['name'=>'insuranceid','label'=>'Insurance ID','label_ar'=>'','type'=>'text'], $row['insuranceid']);
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'تاريخ الميلاد (من الرقم القومي)':'Date of Birth (from NID)').'</label>'
        .'<input type="date" name="dop" class="form-control" value="'.htmlspecialchars($row['dop']).'"></div>';
    echo field_input(['name'=>'dor','label'=>'Retirement Date','label_ar'=>'تاريخ التقاعد','type'=>'date'], $row['dor']);
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'النوع':'Gender').'</label>'
        .'<select name="empgender" class="form-select">'
        .'<option value="Male"'.($row['empgender']=='Male' || $row['empgender']=='ذكر' ? ' selected' : '').'>'.($isAr ? 'ذكر' : 'Male').'</option>'
        .'<option value="Female"'.($row['empgender']=='Female' || $row['empgender']=='أنثى' ? ' selected' : '').'>'.($isAr ? 'أنثى' : 'Female').'</option>'
        .'</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'القسم':'Department').'</label>'
        .'<select name="depid" class="form-select">'
        . $opt($departments, 'id', 'depnamear', 'depname', $row['depid']) . '</select></div>';
    echo field_input(['name'=>'biometricid','label'=>'Biometric ID','label_ar'=>'','type'=>'text'], $row['biometricid']);
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'الحالة الوظيفية':'Job Status').'</label>'
        .'<select name="empstatusid" class="form-select">'
        . $opt($statuses, 'id', 'empstatusnamear', 'empstatusname', $row['empstatusid']) . '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'الدرجة الوظيفية':'Job Degree').'</label>'
        .'<select name="currentdegreeid" class="form-select">'
        . $opt($degrees, 'id', 'degreenamear', 'degreename', $row['currentdegreeid']) . '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'نوع الموظف':'Employee Type').'</label>'
        .'<select name="emptypeid" class="form-select">'
        . $opt($types, 'id', 'emptypenamear', 'emptypename', $row['emptypeid']) . '</select></div>';
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'مجموعة الموظفين':'Employee Group').'</label>'
        .'<select name="empgroup" class="form-select">'
        . $opt($groups, 'id', 'empgroupnamear', 'empgroupname', $row['empgroup']) . '</select></div>';
    $supList = fetchAll("SELECT id, empfullname, empfullnamear FROM " . table('employeesmain') . " WHERE omitted=0 AND id <> ? ORDER BY empfullname ASC", [$id]);
    echo '<div class="mb-3"><label class="form-label">'.($isAr?'المدير المباشر':'Direct Superior').'</label>'
        .'<select name="empsuperiorid" class="form-select">'
        . $opt($supList, 'id', 'empfullnamear', 'empfullname', $row['empsuperiorid']) . '</select></div>';
    echo '<div class="d-grid gap-2"><button class="btn btn-primary">Update</button>
          <a href="index.php?module=employeesmain" class="btn btn-secondary">Cancel</a></div>';
    echo '</form>';
    return;
}

// ---------- VIEW (details) ----------
if ($action === 'view' && $id) {
    require_permission($module, 'view');
    $row = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
    if (!$row) { set_flash('error','Record not found.'); header('Location: index.php?module=employeesmain'); exit; }
    ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-person-lines-fill me-2"></i><?= $isAr ? 'تفاصيل الموظف' : 'Employee Details' ?></h2>
    <a href="index.php?module=employeesmain" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'المعلومات الشخصية' : 'Personal Information' ?></div>
            <div class="card-body">
                <p><strong><?= $isAr ? 'الاسم (عربي)' : 'Full Name (Arabic)' ?>:</strong> <?= htmlspecialchars($row['empfullnamear'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'الاسم (إنجليزي)' : 'Full Name (English)' ?>:</strong> <?= htmlspecialchars($row['empfullname'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'الرقم القومي' : 'National ID' ?>:</strong> <?= htmlspecialchars($row['nid'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'اسم المستخدم (تسجيل الدخول)' : 'Login Username' ?>:</strong> <?= htmlspecialchars($row['nid'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'البريد الإلكتروني' : 'Email' ?>:</strong> <?= htmlspecialchars($row['email'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'رقم التأمين' : 'Insurance ID' ?>:</strong> <?= htmlspecialchars($row['insuranceid'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'تاريخ الميلاد' : 'Date of Birth' ?>:</strong> <?= htmlspecialchars($row['dop'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'تاريخ التقاعد' : 'Retirement Date' ?>:</strong> <?= htmlspecialchars($row['dor'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'النوع' : 'Gender' ?>:</strong> <?= htmlspecialchars($isAr ? ($row['empgender'] === 'Male' ? 'ذكر' : 'أنثى') : ($row['empgender'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'البصمة' : 'Biometric ID' ?>:</strong> <?= htmlspecialchars($row['biometricid'] ?? '') ?></p>
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
            </div>
        </div>
    </div>
</div>
<div class="mb-3">
    <?php if ($canEdit): ?><a href="index.php?module=employeesmain&a=edit&id=<?= $row['id'] ?>" class="btn btn-warning"><i class="bi bi-pencil me-1"></i><?= $isAr ? 'تعديل' : 'Edit' ?></a><?php endif; ?>
    <a href="index.php?module=employeesmain" class="btn btn-secondary ms-2"><?= $isAr ? 'العودة' : 'Back' ?></a>
</div>
<?php
    return;
}

// LIST
require_permission($module, 'view');
$rows = fetchAll("SELECT * FROM " . table('employeesmain') . " WHERE omitted=0 ORDER BY empfullname ASC");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-person-lines-fill me-2"></i><?= $isAr ? 'الموظفون الرئيسيون' : 'Employees Main' ?></h2>
    <div>
        <?php if ($canAdd): ?><a href="index.php?module=employeesmain&a=add" class="btn btn-primary me-1"><i class="bi bi-plus me-1"></i><?= $isAr ? 'إضافة' : 'Add' ?></a><?php endif; ?>
        <a href="index.php?module=employeesmain&a=export&fmt=csv" class="btn btn-outline-secondary btn-sm me-1">CSV</a>
        <a href="index.php?module=employeesmain&a=export&fmt=xlsx" class="btn btn-outline-success btn-sm me-1">XLSX</a>
        <a href="index.php?module=employeesmain&a=export&fmt=docx" class="btn btn-outline-primary btn-sm me-1">Word</a>
        <a href="index.php?module=employeesmain&a=export&fmt=pdf" class="btn btn-outline-danger btn-sm me-1">PDF</a>
    </div>
</div>
<div class="table-responsive">
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
    <a href="index.php?module=employeesmain&a=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
    <?php if ($canEdit): ?><a href="index.php?module=employeesmain&a=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a><?php endif; ?>
    <?php if ($canDel): ?><a href="index.php?module=employeesmain&a=del&id=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Omit this employee?')"><i class="bi bi-trash"></i></a><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
