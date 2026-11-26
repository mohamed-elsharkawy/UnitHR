<?php
/**
 * My Profile module
 *
 * Every signed-in account can manage its own email address, password and
 * persistent API token. The module is rendered at the end of the side menu and
 * is always available to signed-in users.
 */
$moduleInfo = [
    'name'        => 'My Profile',
    'name_ar'     => 'ملفي الشخصي',
    'description' => 'Manage your account: email, password and API token.',
    'description_ar' => 'إدارة حسابك: البريد الإلكتروني وكلمة المرور ورمز واجهة البرمجة (API).',
    'version'     => '2.7.0',
    'author'      => 'UnitHR Team',
    'icon'        => 'bi bi-person-circle',
    'group'       => 'Account',
    'group_ar'    => 'الحساب',
    'group_order' => 99,
    'order'       => 1,
    'enabled'     => true,
];
