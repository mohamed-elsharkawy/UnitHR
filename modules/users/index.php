<?php
/**
 * Users module with password encryption and OTP setup hooks.
 */
require_once __DIR__ . '/../../includes/crud_engine.php';

function module_users_before_save(&$data, $isAdd, $id = 0, $old = []) {
    // Encrypt password if provided (reversible encryption per requirements)
    if (!empty($data['password'])) {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    } elseif (!$isAdd) {
        unset($data['password']);
    }
    // Ensure boolean-like fields are stored as 0/1
    foreach (['otp_enabled','is_admin','is_superadmin'] as $k) {
        $data[$k] = !empty($data[$k]) ? 1 : 0;
    }
}

// ---------- RESET OTP ----------
if (isset($_GET['a']) && $_GET['a'] === 'reset-otp' && isset($_GET['id'])) {
    require_login();
    require_permission('users', 'edit');
    $uid = (int)$_GET['id'];
    q("UPDATE " . table('users') . " SET otp_secret = NULL, otp_enabled = 0 WHERE id = ?", [$uid]);
    set_flash('success', 'OTP has been reset for this user. They will need to set up OTP on next login.');
    header('Location: index.php?module=users'); exit;
}

$boolOpts = ['table'=>'reqexam','value'=>'id','label'=>'label','label_ar'=>'labelar','where'=>[]];
run_table_module([
    'table'        => 'users',
    'module'       => 'users',
    'title'        => 'Users',
    'title_ar'     => 'المستخدمون',
    'order_by'     => 'username ASC',
    'search_fields'=> ['username','email','departmentname'],
    'fields'       => [
        ['name'=>'username','label'=>'Username','label_ar'=>'اسم المستخدم','type'=>'text','required'=>true],
        ['name'=>'email','label'=>'Email','label_ar'=>'البريد الإلكتروني','type'=>'text','required'=>true],
        ['name'=>'password','label'=>'Password','label_ar'=>'كلمة المرور','type'=>'password','required'=>true],
        ['name'=>'departmentid','label'=>'Department','label_ar'=>'القسم','type'=>'select','options'=>['table'=>'depstructure','value'=>'id','label'=>'depname','label_ar'=>'depnamear','where'=>['omitted'=>0]], 'allow_empty'=>true],
        ['name'=>'departmentname','label'=>'Department Name','label_ar'=>'اسم القسم','type'=>'text'],
        ['name'=>'departmentnamear','label'=>'Department Name (Arabic)','label_ar'=>'اسم القسم بالعربية','type'=>'text'],
        ['name'=>'permissionid','label'=>'Permissions Record','label_ar'=>'سجل الصلاحيات','type'=>'select','options'=>['table'=>'permissions','value'=>'id','label'=>'modulename','label_ar'=>'modulenamear','where'=>['omitted'=>0]], 'allow_empty'=>true],
        ['name'=>'otp_secret','label'=>'OTP Secret Key','label_ar'=>'مفتاح سر OTP','type'=>'text'],
        ['name'=>'otp_enabled','label'=>'OTP Enabled','label_ar'=>'تفعيل OTP','type'=>'select','options'=>$boolOpts,'required'=>true],
        ['name'=>'is_admin','label'=>'Is App Admin','label_ar'=>'مدير تطبيق','type'=>'select','options'=>$boolOpts,'required'=>true],
        ['name'=>'is_superadmin','label'=>'Is SuperAdmin','label_ar'=>'مشرف عام','type'=>'select','options'=>$boolOpts,'required'=>true],
    ],
]);
