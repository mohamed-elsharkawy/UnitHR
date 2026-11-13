<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

/**
 * Users module - multi-select permissions support.
 *
 * Grid hides password and OTP Secret Key. A user can be assigned multiple
 * permission records (uhr_user_permissions); effective access is the OR-merge
 * of all selected records (see includes/auth.php).
 */
require_once __DIR__ . '/../../includes/crud_engine.php';
require_login();

// ---------- RESET OTP ----------
if (isset($_GET['a']) && $_GET['a'] === 'reset-otp' && isset($_GET['id'])) {
    require_permission('users', 'edit');
    $uid = (int)$_GET['id'];
    q("UPDATE " . table('users') . " SET otp_secret = NULL, otp_enabled = 0 WHERE id = ?", [$uid]);
    set_flash('success', 'OTP has been reset for this user. They will need to set up OTP on next login.');
    header('Location: ?module=users'); exit;
}

// ---------- Hook: normalize data before save ----------
function module_users_before_save(&$data, $isAdd, $id = 0, $old = []) {
    // Populate department name from departmentid
    if (!empty($data['departmentid'])) {
        $dept = fetchRow("SELECT depname, depnamear FROM " . table('depstructure') . " WHERE id = ? LIMIT 1", [$data['departmentid']]);
        if ($dept) {
            $data['departmentname'] = $dept['depname'];
            $data['departmentnamear'] = $dept['depnamear'];
        } else {
            $data['departmentname'] = '';
            $data['departmentnamear'] = '';
        }
    } else {
        $data['departmentname'] = '';
        $data['departmentnamear'] = '';
    }
    // Encrypt password if provided
    if (!empty($data['password'])) {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    } elseif (!$isAdd) {
        unset($data['password']);
    }
    // Ensure boolean-like fields
    foreach (['otp_enabled','is_admin','is_superadmin'] as $k) {
        $data[$k] = !empty($data[$k]) ? 1 : 0;
    }
    // permissionid is legacy; multi-select permissions live in uhr_user_permissions
    $data['permissionid'] = null;
}

// ---------- Sync multi-select permissions into junction table ----------
function sync_user_permissions($userId, $permIds, $u, $an, $now) {
    q("DELETE FROM " . table('user_permissions') . " WHERE user_id = ? AND omitted = 0", [(int)$userId]);
    foreach ((array)$permIds as $pid) {
        if ($pid === '' || $pid === null) continue;
        q("INSERT INTO " . table('user_permissions') . "
            (rguid,user_id,permission_id,dtadded,addedbyid,addedby,addedbyname,addedbynamear,omitted)
            VALUES (?,?,?,NOW(),?,?,?,?,0)",
            [bin2hex(random_bytes(16)), (int)$userId, (int)$pid, $u['id'], $u['username'], $an, $an]);
    }
}

// Option label for the permissions multi-select: several permission records may
// exist for the same module (different capability mixes), so show the flags.
function perm_option_label($p, $isAr) {
    $base = $isAr ? (!empty($p['modulenamear']) ? $p['modulenamear'] : $p['modulename']) : $p['modulename'];
    $caps = [];
    if (!empty($p['isadmin'])) { $caps[] = $isAr ? 'مدير' : 'admin'; }
    if (!empty($p['canadd']))  { $caps[] = $isAr ? 'إضافة' : 'add'; }
    if (!empty($p['canedit'])) { $caps[] = $isAr ? 'تعديل' : 'edit'; }
    if (!empty($p['candel']))  { $caps[] = $isAr ? 'حذف' : 'delete'; }
    if (!$caps) { $caps[] = $isAr ? 'عرض فقط' : 'view only'; }
    return $base . ' — ' . implode('/', $caps);
}

// ---------- JavaScript for auto-updating department names ----------
echo '<script>
function updateDeptNames(select) {
    var deptId = select.value;
    if (!deptId) {
        document.querySelector("input[name=\"departmentname\"]").value = "";
        document.querySelector("input[name=\"departmentnamear\"]").value = "";
        return;
    }
    fetch("?module=users&a=getdept&deptid=" + deptId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.depname) document.querySelector("input[name=\"departmentname\"]").value = data.depname;
            if (data.depnamear) document.querySelector("input[name=\"departmentnamear\"]").value = data.depnamear;
        });
}
</script>';

// ---------- AJAX department lookup ----------
if (isset($_GET['a']) && $_GET['a'] === 'getdept' && isset($_GET['deptid'])) {
    $dept = fetchRow("SELECT depname, depnamear FROM " . table('depstructure') . " WHERE id = ? LIMIT 1", [(int)$_GET['deptid']]);
    header('Content-Type: application/json');
    echo json_encode($dept ?: ['depname' => '', 'depnamear' => '']);
    exit;
}

$boolOpts = ['table'=>'reqexam','value'=>'id','label'=>'label','label_ar'=>'labelar','where'=>[]];
$isAr = lang() === 'ar';
$module = 'users';
$table = 'users';
$pk = 'id';
$u = current_user();
$canAdd = user_can($module, 'add');
$canEdit = user_can($module, 'edit');
$canDel = user_can($module, 'del');

$action = $_GET['a'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$search = trim($_GET['search'] ?? '');

// Permission options for multi-select
$permOptions = fetchAll("SELECT id, modulename, modulenamear, isadmin, canadd, canedit, candel FROM " . table('permissions') . " WHERE omitted = 0 ORDER BY modulename ASC, id ASC");

// ---------- EXPORT ----------
if ($action === 'export') {
    require_permission($module, 'view');
    $fmt = $_GET['fmt'] ?? 'csv';
    $where = "u.omitted = 0";
    $params = [];
    if ($search !== '') {
        $norm = arabic_normalize($search);
        $where .= " AND (u.username LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.email LIKE ?)";
        $params = ['%'.$search.'%', '%'.$norm.'%', '%'.$search.'%', '%'.$norm.'%'];
    }
    $rows = fetchAll("SELECT u.username, u.email, d.depname, d.depnamear,
                            u.is_admin, u.is_superadmin, u.otp_enabled,
                            GROUP_CONCAT(DISTINCT p.modulename ORDER BY p.id SEPARATOR ', ') AS perm_names_en,
                            GROUP_CONCAT(DISTINCT p.modulenamear ORDER BY p.id SEPARATOR ', ') AS perm_names_ar
                     FROM " . table('users') . " u
                     LEFT JOIN " . table('depstructure') . " d ON d.id = u.departmentid
                     LEFT JOIN " . table('user_permissions') . " up ON up.user_id = u.id AND up.omitted = 0
                     LEFT JOIN " . table('permissions') . " p ON p.id = up.permission_id AND p.omitted = 0
                     WHERE $where
                     GROUP BY u.id
                     ORDER BY u.username ASC", $params);
    $outRows = [];
    foreach ($rows as $r) {
        $permName = $isAr ? ($r['perm_names_ar'] ?? $r['perm_names_en']) : ($r['perm_names_en'] ?? $r['perm_names_ar']);
        $outRows[] = [
            $r['username'], $r['email'], $r['depname'] ?? '',
            $permName ?: '—',
            $r['is_admin'] ? 'Yes' : 'No',
            $r['is_superadmin'] ? 'Yes' : 'No',
            $r['otp_enabled'] ? 'Yes' : 'No',
        ];
    }
    $headers = [$isAr ? 'اسم المستخدم' : 'Username', $isAr ? 'البريد الإلكتروني' : 'Email',
                $isAr ? 'القسم' : 'Department', $isAr ? 'الصلاحيات' : 'Permissions',
                $isAr ? 'مدير تطبيق' : 'Is App Admin', $isAr ? 'مشرف عام' : 'Is SuperAdmin',
                $isAr ? 'تفعيل OTP' : 'OTP Enabled'];
    do_export($fmt, 'users_export', $isAr ? 'المستخدمون' : 'Users', $headers, $outRows);
    exit;
}

// ---------- ADD ----------
if ($action === 'add') {
    require_permission($module, 'add');
    $data = ['username'=>'','email'=>'','password'=>'','departmentid'=>'','departmentname'=>'','departmentnamear'=>'','otp_secret'=>'','otp_enabled'=>'0','is_admin'=>'0','is_superadmin'=>'0'];
    $selPerms = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'departmentid' => ($_POST['departmentid'] ?? '') ?: null,
            'departmentname' => '',
            'departmentnamear' => '',
            'otp_secret' => trim($_POST['otp_secret'] ?? ''),
            'otp_enabled' => !empty($_POST['otp_enabled']) ? 1 : 0,
            'is_admin' => !empty($_POST['is_admin']) ? 1 : 0,
            'is_superadmin' => !empty($_POST['is_superadmin']) ? 1 : 0,
        ];
        $selPerms = $_POST['permissions'] ?? [];
        $ok = true;
        if ($data['username'] === '') { $ok = false; set_flash('error', $isAr ? 'اسم المستخدم مطلوب.' : 'Username is required.'); }
        elseif ($data['email'] === '') { $ok = false; set_flash('error', $isAr ? 'البريد الإلكتروني مطلوب.' : 'Email is required.'); }
        elseif ($data['password'] === '') { $ok = false; set_flash('error', $isAr ? 'كلمة المرور مطلوبة.' : 'Password is required.'); }
        elseif (fetchRow("SELECT id FROM " . table('users') . " WHERE username = ? AND omitted = 0 LIMIT 1", [$data['username']])) {
            $ok = false; set_flash('error', $isAr ? 'اسم المستخدم موجود بالفعل.' : 'Username already exists.');
        }
        elseif (fetchRow("SELECT id FROM " . table('users') . " WHERE email = ? AND omitted = 0 LIMIT 1", [$data['email']])) {
            $ok = false; set_flash('error', $isAr ? 'البريد الإلكتروني موجود بالفعل.' : 'Email already exists.');
        }
        if ($ok) {
            module_users_before_save($data, true);
            $now = date('Y-m-d H:i:s');
            $an = $u['username_ar'] ?? $u['username'];
            $cols = []; $vals = []; $params = [];
            $cols[] = '`rguid`'; $vals[] = '?'; $params[] = bin2hex(random_bytes(16));
            foreach ($data as $k => $v) {
                if (is_audit_col($k)) continue;
                $cols[] = '`'.$k.'`'; $vals[] = '?'; $params[] = $v;
            }
            $cols[] = '`dtadded`'; $vals[] = '?'; $params[] = $now;
            $cols[] = '`addedbyid`'; $vals[] = '?'; $params[] = $u['id'];
            $cols[] = '`addedby`'; $vals[] = '?'; $params[] = $u['username'];
            $cols[] = '`addedbyname`'; $vals[] = '?'; $params[] = $an;
            $cols[] = '`addedbynamear`'; $vals[] = '?'; $params[] = $an;
            $cols[] = '`lastedit`'; $vals[] = '?'; $params[] = $now;
            $cols[] = '`editedbyid`'; $vals[] = '?'; $params[] = $u['id'];
            $cols[] = '`editedby`'; $vals[] = '?'; $params[] = $u['username'];
            $cols[] = '`editedbyname`'; $vals[] = '?'; $params[] = $an;
            $cols[] = '`editedbynamear`'; $vals[] = '?'; $params[] = $an;
            $cols[] = '`omitted`'; $vals[] = '?'; $params[] = 0;
            if (q("INSERT INTO " . table('users') . " (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")", $params)) {
                $newId = (int)lastInsertId();
                sync_user_permissions($newId, $selPerms, $u, $an, $now);
                set_flash('success', $isAr ? 'تم إضافة المستخدم.' : 'User added successfully.');
                header('Location: ?module=users'); exit;
            } else {
                set_flash('error', db_error() ?: 'Failed to add user.');
            }
        }
    }
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-plus-lg me-2"></i>'.($isAr ? 'إضافة مستخدم' : 'Add User').'</h2>
            <a href="?module=users" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>'.($isAr ? 'العودة' : 'Back').'</a>
          </div>';
    echo '<form method="post" action="?module=users&a=add">';
    echo field_input(['name'=>'username','label'=>'Username','label_ar'=>'اسم المستخدم','type'=>'text','required'=>true], $data['username']);
    echo field_input(['name'=>'email','label'=>'Email','label_ar'=>'البريد الإلكتروني','type'=>'text','required'=>true], $data['email']);
    echo field_input(['name'=>'password','label'=>'Password','label_ar'=>'كلمة المرور','type'=>'password','required'=>true], '');
    echo field_input(['name'=>'departmentid','label'=>'Department','label_ar'=>'القسم','type'=>'select','options'=>['table'=>'depstructure','value'=>'id','label'=>'depname','label_ar'=>'depnamear','where'=>['omitted'=>0]], 'allow_empty'=>true, 'extra'=>'onchange="updateDeptNames(this)"'], $data['departmentid']);
    echo field_input(['name'=>'departmentname','label'=>'Department Name','label_ar'=>'اسم القسم','type'=>'hidden'], $data['departmentname']);
    echo field_input(['name'=>'departmentnamear','label'=>'Department Name (Arabic)','label_ar'=>'اسم القسم بالعربية','type'=>'hidden'], $data['departmentnamear']);
    // Multi-select permissions
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'الصلاحيات' : 'Permissions').'</label>
          <select name="permissions[]" class="form-select" multiple size="6">';
    foreach ($permOptions as $p) {
        $sel = in_array((string)$p['id'], array_map('strval', $selPerms), true) ? ' selected' : '';
        echo '<option value="'.htmlspecialchars($p['id']).'"'.$sel.'>'
            . htmlspecialchars(perm_option_label($p, $isAr), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
    }
    echo '</select><div class="form-text">'.($isAr ? 'اضغط مع التحديد لتحديد أكثر من سجل صلاحية.' : 'Hold Ctrl/Cmd to select multiple permission records.') . '</div></div>';
    echo field_input(['name'=>'otp_secret','label'=>'OTP Secret Key','label_ar'=>'مفتاح سر OTP','type'=>'text'], $data['otp_secret']);
    echo field_input(['name'=>'otp_enabled','label'=>'OTP Enabled','label_ar'=>'تفعيل OTP','type'=>'select','options'=>$boolOpts,'required'=>true], $data['otp_enabled']);
    echo field_input(['name'=>'is_admin','label'=>'Is App Admin','label_ar'=>'مدير تطبيق','type'=>'select','options'=>$boolOpts,'required'=>true], $data['is_admin']);
    echo field_input(['name'=>'is_superadmin','label'=>'Is SuperAdmin','label_ar'=>'مشرف عام','type'=>'select','options'=>$boolOpts,'required'=>true], $data['is_superadmin']);
    echo '<div class="d-grid gap-2"><button class="btn btn-primary">'.($isAr ? 'حفظ' : 'Save').'</button>
          <a href="?module=users" class="btn btn-secondary">'.($isAr ? 'إلغاء' : 'Cancel').'</a></div>';
    echo '</form>';
    return;
}

// ---------- EDIT ----------
if ($action === 'edit' && $id) {
    require_permission($module, 'edit');
    $row = fetchRow("SELECT * FROM " . table('users') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$id]);
    if (!$row) { set_flash('error', $isAr ? 'المستخدم غير موجود.' : 'User not found.'); header('Location: ?module=users'); exit; }
    $curPerms = fetchAll("SELECT permission_id FROM " . table('user_permissions') . " WHERE user_id = ? AND omitted = 0", [$id]);
    $selPerms = array_column($curPerms, 'permission_id');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        shadow_log($table, $id, $row, 'update');
        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'departmentid' => ($_POST['departmentid'] ?? '') ?: null,
            'departmentname' => '',
            'departmentnamear' => '',
            'otp_secret' => trim($_POST['otp_secret'] ?? ''),
            'otp_enabled' => !empty($_POST['otp_enabled']) ? 1 : 0,
            'is_admin' => !empty($_POST['is_admin']) ? 1 : 0,
            'is_superadmin' => !empty($_POST['is_superadmin']) ? 1 : 0,
        ];
        $selPerms = $_POST['permissions'] ?? [];
        $ok = true;
        if ($data['username'] === '') { $ok = false; set_flash('error', $isAr ? 'اسم المستخدم مطلوب.' : 'Username is required.'); }
        elseif ($data['email'] === '') { $ok = false; set_flash('error', $isAr ? 'البريد الإلكتروني مطلوب.' : 'Email is required.'); }
        elseif (fetchRow("SELECT id FROM " . table('users') . " WHERE username = ? AND omitted = 0 AND id != ? LIMIT 1", [$data['username'], $id])) {
            $ok = false; set_flash('error', $isAr ? 'اسم المستخدم موجود بالفعل.' : 'Username already exists.');
        }
        elseif (fetchRow("SELECT id FROM " . table('users') . " WHERE email = ? AND omitted = 0 AND id != ? LIMIT 1", [$data['email'], $id])) {
            $ok = false; set_flash('error', $isAr ? 'البريد الإلكتروني موجود بالفعل.' : 'Email already exists.');
        }
        if ($ok) {
            module_users_before_save($data, false, $id, $row);
            $now = date('Y-m-d H:i:s');
            $an = $u['username_ar'] ?? $u['username'];
            $sets = []; $params = [];
            foreach ($data as $k => $v) {
                if (is_audit_col($k)) continue;
                $sets[] = '`'.$k.'`=?'; $params[] = $v;
            }
            $sets[] = '`lastedit`=?'; $params[] = $now;
            $sets[] = '`editedbyid`=?'; $params[] = $u['id'];
            $sets[] = '`editedby`=?'; $params[] = $u['username'];
            $sets[] = '`editedbyname`=?'; $params[] = $an;
            $sets[] = '`editedbynamear`=?'; $params[] = $an;
            $params[] = $id;
            if (q("UPDATE " . table('users') . " SET " . implode(',', $sets) . " WHERE `id`=?", $params)) {
                sync_user_permissions($id, $selPerms, $u, $an, $now);
                set_flash('success', $isAr ? 'تم تحديث المستخدم.' : 'User updated.');
                header('Location: ?module=users'); exit;
            } else {
                set_flash('error', db_error() ?: 'Failed to update user.');
            }
            $row = array_merge($row, $data);
        }
    }
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-pencil-square me-2"></i>'.($isAr ? 'تعديل مستخدم' : 'Edit User').'</h2>
            <a href="?module=users" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>'.($isAr ? 'العودة' : 'Back').'</a>
          </div>';
    echo '<form method="post" action="?module=users&a=edit&id='.$id.'">';
    echo field_input(['name'=>'username','label'=>'Username','label_ar'=>'اسم المستخدم','type'=>'text','required'=>true], $row['username']);
    echo field_input(['name'=>'email','label'=>'Email','label_ar'=>'البريد الإلكتروني','type'=>'text','required'=>true], $row['email']);
    echo field_input(['name'=>'password','label'=>'Password','label_ar'=>'كلمة المرور','type'=>'password'], '');
    echo '<div class="form-text">'.($isAr ? 'اتركها فارغة للحفاظ على كلمة المرور الحالية.' : 'Leave blank to keep current password.') . '</div>';
    echo field_input(['name'=>'departmentid','label'=>'Department','label_ar'=>'القسم','type'=>'select','options'=>['table'=>'depstructure','value'=>'id','label'=>'depname','label_ar'=>'depnamear','where'=>['omitted'=>0]], 'allow_empty'=>true, 'extra'=>'onchange="updateDeptNames(this)"'], $row['departmentid']);
    echo field_input(['name'=>'departmentname','label'=>'Department Name','label_ar'=>'اسم القسم','type'=>'hidden'], $row['departmentname']);
    echo field_input(['name'=>'departmentnamear','label'=>'Department Name (Arabic)','label_ar'=>'اسم القسم بالعربية','type'=>'hidden'], $row['departmentnamear']);
    echo '<div class="mb-3"><label class="form-label">'.($isAr ? 'الصلاحيات' : 'Permissions').'</label>
          <select name="permissions[]" class="form-select" multiple size="6">';
    foreach ($permOptions as $p) {
        $sel = in_array((string)$p['id'], array_map('strval', $selPerms), true) ? ' selected' : '';
        echo '<option value="'.htmlspecialchars($p['id']).'"'.$sel.'>'
            . htmlspecialchars(perm_option_label($p, $isAr), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
    }
    echo '</select><div class="form-text">'.($isAr ? 'اضغط مع التحديد لتحديد أكثر من سجل صلاحية.' : 'Hold Ctrl/Cmd to select multiple permission records.') . '</div></div>';
    echo field_input(['name'=>'otp_secret','label'=>'OTP Secret Key','label_ar'=>'مفتاح سر OTP','type'=>'text'], $row['otp_secret']);
    echo field_input(['name'=>'otp_enabled','label'=>'OTP Enabled','label_ar'=>'تفعيل OTP','type'=>'select','options'=>$boolOpts,'required'=>true], $row['otp_enabled']);
    echo field_input(['name'=>'is_admin','label'=>'Is App Admin','label_ar'=>'مدير تطبيق','type'=>'select','options'=>$boolOpts,'required'=>true], $row['is_admin']);
    echo field_input(['name'=>'is_superadmin','label'=>'Is SuperAdmin','label_ar'=>'مشرف عام','type'=>'select','options'=>$boolOpts,'required'=>true], $row['is_superadmin']);
    echo '<div class="d-grid gap-2"><button class="btn btn-primary">'.($isAr ? 'تحديث' : 'Update').'</button>
          <a href="?module=users" class="btn btn-secondary">'.($isAr ? 'إلغاء' : 'Cancel').'</a></div>';
    echo '</form>';
    return;
}

// ---------- VIEW ----------
if ($action === 'view' && $id) {
    require_permission($module, 'view');
    $row = fetchRow("SELECT u.*, d.depname, d.depnamear,
                            GROUP_CONCAT(DISTINCT p.modulename ORDER BY p.id SEPARATOR ', ') AS perm_names_en,
                            GROUP_CONCAT(DISTINCT p.modulenamear ORDER BY p.id SEPARATOR ', ') AS perm_names_ar
                     FROM " . table('users') . " u
                     LEFT JOIN " . table('depstructure') . " d ON d.id = u.departmentid
                     LEFT JOIN " . table('user_permissions') . " up ON up.user_id = u.id AND up.omitted = 0
                     LEFT JOIN " . table('permissions') . " p ON p.id = up.permission_id AND p.omitted = 0
                     WHERE u.id = ? AND u.omitted = 0
                     GROUP BY u.id LIMIT 1", [$id]);
    if (!$row) { set_flash('error', $isAr ? 'المستخدم غير موجود.' : 'User not found.'); header('Location: ?module=users'); exit; }
    $permName = $isAr ? ($row['perm_names_ar'] ?? $row['perm_names_en']) : ($row['perm_names_en'] ?? $row['perm_names_ar']);
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-eye me-2"></i>'.($isAr ? 'تفاصيل المستخدم' : 'User Details').'</h2>
            <a href="?module=users" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>'.($isAr ? 'العودة' : 'Back').'</a>
          </div>';
    echo '<div class="card"><div class="card-body">
            <p><strong>'.($isAr ? 'اسم المستخدم' : 'Username').':</strong> '.htmlspecialchars($row['username']).'</p>
            <p><strong>'.($isAr ? 'البريد الإلكتروني' : 'Email').':</strong> '.htmlspecialchars($row['email']).'</p>
            <p><strong>'.($isAr ? 'القسم' : 'Department').':</strong> '.htmlspecialchars($isAr ? ($row['depnamear'] ?? '') : ($row['depname'] ?? '')).'</p>
            <p><strong>'.($isAr ? 'الصلاحيات' : 'Permissions').':</strong> '.htmlspecialchars($permName ?: '—').'</p>
            <p><strong>'.($isAr ? 'مدير تطبيق' : 'Is App Admin').':</strong> '.($row['is_admin'] ? 'Yes' : 'No').'</p>
            <p><strong>'.($isAr ? 'مشرف عام' : 'Is SuperAdmin').':</strong> '.($row['is_superadmin'] ? 'Yes' : 'No').'</p>
            <p><strong>'.($isAr ? 'تفعيل OTP' : 'OTP Enabled').':</strong> '.($row['otp_enabled'] ? 'Yes' : 'No').'</p>
          </div></div>';
    if ($canEdit) echo '<a href="?module=users&a=edit&id='.$id.'" class="btn btn-warning mt-3"><i class="bi bi-pencil me-1"></i>'.($isAr ? 'تعديل' : 'Edit').'</a>';
    if ($canEdit) echo ' <a href="?module=users&a=reset-otp&id='.$id.'" class="btn btn-sm btn-secondary mt-3" onclick="return confirm(\''.($isAr ? 'إعادة تعيين OTP لهذا المستخدم؟' : 'Reset OTP for this user?').'\')"><i class="bi bi-key"></i> '.($isAr ? 'إعادة تعيين OTP' : 'Reset OTP').'</a>';
    echo ' <a href="?module=users" class="btn btn-secondary mt-3">'.($isAr ? 'العودة' : 'Back').'</a>';
    return;
}

// ---------- DELETE ----------
if ($action === 'del' && $id) {
    require_permission($module, 'del');
    $confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';
    if (!$confirm) {
        echo '<div class="alert alert-warning">
                <h4>'.($isAr ? 'حذف المستخدم؟' : 'Omit user?').'</h4>
                <p>'.($isAr ? 'لن يتم حذف المستخدم فعلياً؛ سيتم وضع علامة محذوف عليه وإخفاؤه من التقارير.' : 'Records are not actually deleted; they are marked as omitted and hidden from reports.').'</p>
                <a href="?module=users&a=del&id='.$id.'&confirm=1" class="btn btn-danger">'.($isAr ? 'نعم، حذف' : 'Yes, Omit').'</a>
                <a href="?module=users" class="btn btn-secondary">'.($isAr ? 'إلغاء' : 'Cancel').'</a>
              </div>';
        return;
    }
    $row = fetchRow("SELECT * FROM " . table('users') . " WHERE id = ? LIMIT 1", [$id]);
    if ($row) { shadow_log($table, $id, $row, 'omit'); }
    q("UPDATE " . table('users') . " SET omitted = 1 WHERE id = ?", [$id]);
    q("UPDATE " . table('user_permissions') . " SET omitted = 1 WHERE user_id = ?", [$id]);
    set_flash('success', $isAr ? 'تم حذف المستخدم.' : 'User omitted.');
    header('Location: ?module=users');
    exit;
}

// ---------- LIST ----------
require_permission($module, 'view');
$where = "u.omitted = 0";
$params = [];
if ($search !== '') {
    $norm = arabic_normalize($search);
    $where .= " AND (u.username LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.email LIKE ?)";
    $params = ['%'.$search.'%', '%'.$norm.'%', '%'.$search.'%', '%'.$norm.'%'];
}
$rows = fetchAll("SELECT u.*, d.depname, d.depnamear,
                        GROUP_CONCAT(DISTINCT p.modulename ORDER BY p.id SEPARATOR ', ') AS perm_names_en,
                        GROUP_CONCAT(DISTINCT p.modulenamear ORDER BY p.id SEPARATOR ', ') AS perm_names_ar
                 FROM " . table('users') . " u
                 LEFT JOIN " . table('depstructure') . " d ON d.id = u.departmentid
                 LEFT JOIN " . table('user_permissions') . " up ON up.user_id = u.id AND up.omitted = 0
                 LEFT JOIN " . table('permissions') . " p ON p.id = up.permission_id AND p.omitted = 0
                 WHERE $where
                 GROUP BY u.id
                 ORDER BY u.username ASC", $params);

// Sync any legacy permissionid into the junction table (one-time migration)
foreach ($rows as $r) {
    if (!empty($r['permissionid'])) {
        $exists = fetchRow("SELECT id FROM " . table('user_permissions') . " WHERE user_id=? AND permission_id=? AND omitted=0 LIMIT 1", [(int)$r['id'], (int)$r['permissionid']]);
        if (!$exists) {
            q("INSERT INTO " . table('user_permissions') . " (rguid,user_id,permission_id,dtadded,addedbyid,addedby,addedbyname,addedbynamear,omitted) VALUES (?,?,?,NOW(),?,?,?,?,0)",
                [bin2hex(random_bytes(16)), (int)$r['id'], (int)$r['permissionid'], 1, 'System', 'System', 'System']);
        }
    }
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-table me-2"></i><?= $isAr ? 'المستخدمون' : 'Users' ?></h2>
    <div>
      <a href="?module=users&a=export&fmt=csv" class="btn btn-outline-secondary btn-sm me-1"><i class="bi bi-file-earmark-csv me-1"></i>CSV</a>
      <a href="?module=users&a=export&fmt=xlsx" class="btn btn-outline-success btn-sm me-1"><i class="bi bi-file-earmark-excel me-1"></i>XLSX</a>
      <a href="?module=users&a=export&fmt=docx" class="btn btn-outline-primary btn-sm me-1"><i class="bi bi-file-earmark-word me-1"></i>Word</a>
      <a href="?module=users&a=export&fmt=pdf" class="btn btn-outline-danger btn-sm me-1"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
      <?= $canAdd ? '<a href="?module=users&a=add" class="btn btn-primary ms-2"><i class="bi bi-plus me-1"></i>'.($isAr ? 'إضافة مستخدم' : 'Add New').'</a>' : '' ?>
    </div>
</div>
<form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="module" value="users">
    <div class="col-md-4"><input type="search" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="<?= $isAr ? 'بحث (عربي/إنجليزي)...' : 'Search (Arabic/English)...' ?>"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><?= $isAr ? 'بحث' : 'Search' ?></button></div>
    <div class="col-md-2"><a href="?module=users" class="btn btn-outline-secondary w-100"><?= $isAr ? 'إعادة تعيين' : 'Reset' ?></a></div>
</form>
<?php if (count($rows) === 0): ?>
    <div class="alert alert-info"><?= $isAr ? 'لا توجد سجلات.' : 'No records found.' ?></div>
<?php else: ?>
    <div class="table-responsive"><table class="table table-striped table-hover align-middle">
        <thead class="table-dark"><tr>
            <th><?= $isAr ? 'اسم المستخدم' : 'Username' ?></th>
            <th><?= $isAr ? 'البريد الإلكتروني' : 'Email' ?></th>
            <th><?= $isAr ? 'القسم' : 'Department' ?></th>
            <th><?= $isAr ? 'الصلاحيات' : 'Permissions' ?></th>
            <th><?= $isAr ? 'مدير تطبيق' : 'Is App Admin' ?></th>
            <th><?= $isAr ? 'مشرف عام' : 'Is SuperAdmin' ?></th>
            <th><?= $isAr ? 'تفعيل OTP' : 'OTP Enabled' ?></th>
            <th>Actions</th>
        </tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['username']) ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><?= htmlspecialchars($isAr ? ($r['depnamear'] ?? '') : ($r['depname'] ?? '')) ?></td>
            <td><?= htmlspecialchars($isAr ? ($r['perm_names_ar'] ?? $r['perm_names_en'] ?? '—') : ($r['perm_names_en'] ?? $r['perm_names_ar'] ?? '—')) ?></td>
            <td><?= $r['is_admin'] ? 'Yes' : 'No' ?></td>
            <td><?= $r['is_superadmin'] ? 'Yes' : 'No' ?></td>
            <td><?= $r['otp_enabled'] ? 'Yes' : 'No' ?></td>
            <td class="d-flex gap-1">
                <a href="?module=users&a=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                <?php if ($canEdit): ?>
                <a href="?module=users&a=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                <a href="?module=users&a=reset-otp&id=<?= $r['id'] ?>" class="btn btn-sm btn-secondary" onclick="return confirm('<?= $isAr ? 'إعادة تعيين OTP لهذا المستخدم؟' : 'Reset OTP for this user?' ?>')" title="Reset OTP"><i class="bi bi-key"></i></a>
                <?php endif; ?>
                <?php if ($canDel): ?>
                <a href="?module=users&a=del&id=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('<?= $isAr ? 'حذف هذا المستخدم؟' : 'Omit this user?' ?>')"><i class="bi bi-trash"></i></a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
<?php endif; ?>
