<?php
// Build grouped module tree for the sidebar
$moduleGroups = [
    'Dashboard'            => ['home'],
    'Organization'         => ['deptypes', 'depstructure'],
    'Employees'            => ['employeesmain', 'employeesgroup', 'employeestypes', 'empdegrees', 'empstatus', 'retireplan'],
    'Leaves'               => ['leavestypes', 'leavesbalances', 'empleaves'],
    'Users & Permissions'  => ['users', 'permissions', 'appmodules', 'accesslog'],
    'Fiscal'               => ['fiscalyears'],
    'Reports'              => ['reports'],
    'Self-Service'         => ['ess'],
];

$groupNamesAr = [
    'Dashboard'           => 'لوحة التحكم',
    'Organization'        => 'التنظيم',
    'Employees'           => 'الموظفون',
    'Leaves'              => 'الإجازات',
    'Users & Permissions' => 'المستخدمون والصلاحيات',
    'Fiscal'              => 'السنة المالية',
    'Reports'             => 'التقارير',
    'Self-Service'        => 'خدمة الموظف الذاتية',
];

$moduleNamesAr = [
    'home'             => 'لوحة التحكم',
    'appmodules'       => 'وحدات التطبيق',
    'deptypes'         => 'أنواع الأقسام',
    'depstructure'     => 'هيكلة الأقسام',
    'users'            => 'المستخدمون',
    'permissions'      => 'الصلاحيات',
    'accesslog'        => 'سجل الدخول',
    'employeesgroup'   => 'مجموعات الموظفين',
    'employeestypes'   => 'أنواع الموظفين',
    'leavestypes'      => 'أنواع الإجازات',
    'empdegrees'       => 'الدرجات الوظيفية',
    'empstatus'        => 'الحالة الوظيفية',
    'retireplan'       => 'خطة التقاعد',
    'fiscalyears'      => 'السنة المالية',
    'employeesmain'    => 'الموظفون الرئيسيون',
    'leavesbalances'   => 'أرصدة الإجازات',
    'empleaves'        => 'إجازات الموظفين',
    'reports'          => 'التقارير',
    'ess'              => 'خدمة الموظف الذاتية',
];

$routesByFolder = [];
foreach (get_routes() as $r) {
    $routesByFolder[$r['folder']] = $r;
}

if (!function_exists('mod_display_name')) {
    function mod_display_name($folder, $route) {
        if (lang() === 'ar') {
            global $moduleNamesAr;
            return $moduleNamesAr[$folder] ?? $route['name'];
        }
        return $route['name'];
    }
}
?>
<?php $isEss = (current_module() === 'ess'); ?>
<?php $isAuthPage = in_array($_GET['action'] ?? '', ['login', 'verify-otp', 'setup-otp', 'otp', 'logout', 'change-password']); ?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= dir_attr() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(app('appname', 'UnitHR')) ?></title>
    <!-- Bootstrap CSS (offline assets) -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { min-height: 100vh; width: 260px; transition: all 0.3s; overflow-y: auto; }
        .sidebar .nav-link { color: #adb5bd; padding: 0.35rem 1rem; font-size: 0.9rem; }
        .sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,0.12); font-weight: 600; border-left: 3px solid #0d6efd; }
        [dir="rtl"] .sidebar .nav-link.active { border-left: none; border-right: 3px solid #0d6efd; }
        .sidebar .group-toggle { color: #adb5bd; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.6rem 1rem 0.3rem; cursor: pointer; user-select: none; display: flex; align-items: center; justify-content: space-between; }
        .sidebar .group-toggle:hover { color: #fff; }
        .sidebar .group-toggle .chevron { transition: transform 0.2s; font-size: 0.7rem; }
        .sidebar .group-toggle.collapsed .chevron { transform: rotate(-90deg); }
        [dir="rtl"] .sidebar .group-toggle.collapsed .chevron { transform: rotate(90deg); }
        .sidebar .group-items .nav-link { padding-left: 1.75rem; font-size: 0.85rem; }
        [dir="rtl"] .sidebar .group-items .nav-link { padding-left: 1rem; padding-right: 1.75rem; }
        .content-wrapper { flex: 1; min-width: 0; }
        .sidebar.collapsed { width: 0; overflow: hidden; padding: 0; border: 0; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -260px; z-index: 1040; height: 100vh; }
            [dir="rtl"] .sidebar { left: auto; right: -260px; }
            .sidebar.show { left: 0; }
            [dir="rtl"] .sidebar.show { right: 0; left: auto; }
        }
        .sidebar-toggle { display: none; }
        @media (max-width: 768px) { .sidebar-toggle { display: inline-block; } }
    </style>
</head>
<body>
<?php if ($isEss): ?>
<!-- Simple header for Employee Self-Service (no admin sidebar) -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php?module=ess">
            <img src="<?= htmlspecialchars(app('applogo')) ?>" alt="Logo" width="31" height="28" class="me-2" onerror="this.style.display='none'">
			<span class="fw-bold"><?= htmlspecialchars(app('appname', 'UnitHR')) ?> - <?= lang() === 'ar' ? 'خدمة الموظف الذاتية' : 'Employee Self-Service' ?></span>
        </a>
        <ul class="navbar-nav ms-auto align-items-center">
            <li class="nav-item me-2">
                <a class="nav-link lang-switch text-white-50" href="index.php?lang=<?= lang() === 'ar' ? 'en' : 'ar' ?>&module=ess">
                    <i class="bi bi-translate me-1"></i><?= lang() === 'ar' ? 'English' : 'العربية' ?>
                </a>
            </li>
        </ul>
    </div>
</nav>
<main class="container py-4">
    <?php if ($flash = get_flash()): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
        <?= htmlspecialchars($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
<?php elseif ($isAuthPage): ?>
<!-- Simple header for auth pages (no sidebar) -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="<?= htmlspecialchars(app('applogo')) ?>" alt="Logo" width="28" height="28" class="me-2" onerror="this.style.display='none'">
            <span class="fw-bold"><?= htmlspecialchars(app('appname', 'UnitHR')) ?></span>
        </a>
        <ul class="navbar-nav ms-auto align-items-center">
            <li class="nav-item me-2">
                <a class="nav-link lang-switch text-white-50" href="index.php?lang=<?= lang() === 'ar' ? 'en' : 'ar' ?>">
                    <i class="bi bi-translate me-1"></i><?= lang() === 'ar' ? 'English' : 'العربية' ?>
                </a>
            </li>
        </ul>
    </div>
</nav>
<main class="container py-4">
    <?php if ($flash = get_flash()): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
        <?= htmlspecialchars($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
<?php else: ?>
<div class="page-container d-flex flex-column" style="min-height: 100vh;">
    <!-- Row 1: Header/Navbar -->
    <nav class="navbar navbar-expand navbar-dark bg-dark border-bottom border-secondary">
        <div class="container-fluid">
            <button class="btn btn-outline-light btn-sm sidebar-toggle me-2" type="button" onclick="document.getElementById('sidebar').classList.toggle('show')"><i class="bi bi-list"></i></button>
            <button class="btn btn-outline-light btn-sm d-none d-lg-inline-block" type="button" onclick="toggleSidebar()"><i class="bi bi-layout-sidebar"></i></button>
            <span class="navbar-brand mb-0 h6 d-none d-md-inline"><?= htmlspecialchars(app('appname', 'UnitHR')) ?></span>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-2">
                    <a class="nav-link lang-switch text-white-50" href="index.php?lang=<?= lang() === 'ar' ? 'en' : 'ar' ?><?= isset($_GET['module']) ? '&module=' . urlencode($_GET['module']) : '' ?>">
                        <i class="bi bi-translate me-1"></i><?= lang() === 'ar' ? 'English' : 'العربية' ?>
                    </a>
                </li>
                <?php if (is_logged_in()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white-50" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars(current_user()['username'] ?? '') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="index.php?module=users"><?= lang() === 'ar' ? 'المستخدمون' : 'Users' ?></a></li>
                        <li><a class="dropdown-item" href="index.php?module=accesslog"><?= lang() === 'ar' ? 'سجل الدخول' : 'Access Log' ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="index.php?action=change-password"><i class="bi bi-key me-1"></i><?= lang() === 'ar' ? 'تغيير كلمة المرور' : 'Change Password' ?></a></li>
                        <li><a class="dropdown-item" href="index.php?action=change-password"><i class="bi bi-key me-1"></i><?= lang() === 'ar' ? 'تغيير كلمة المرور' : 'Change Password' ?></a></li>
                        <li><a class="dropdown-item" href="index.php?action=logout"><?= lang() === 'ar' ? 'تسجيل خروج' : 'Logout' ?></a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link text-white-50" href="index.php?action=login"><i class="bi bi-box-arrow-in-right me-1"></i><?= lang() === 'ar' ? 'تسجيل دخول' : 'Login' ?></a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <!-- Row 2: Sidebar + Page Content -->
    <div class="d-flex flex-grow-1" style="overflow: hidden;">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar bg-dark" style="overflow-y: auto; max-height: calc(100vh - 120px);">
        <div class="sidebar-header p-3 border-bottom border-secondary d-flex align-items-center justify-content-end">
			<button class="btn btn-outline-light btn-sm sidebar-toggle" type="button" onclick="toggleSidebar()"><i class="bi bi-layout-sidebar"></i></button>
        </div>
        <nav class="py-2">
            <?php foreach ($moduleGroups as $groupName => $folders): ?>
                <?php
                $groupRoutes = [];
                foreach ($folders as $f) {
                    if (isset($routesByFolder[$f])) { $groupRoutes[] = $routesByFolder[$f]; }
                }
                if (empty($groupRoutes)) continue;
                $groupId = 'grp_' . preg_replace('/[^a-z]/i', '', $groupName);
                $currentFolder = current_module();
                $groupHasActive = false;
                foreach ($groupRoutes as $r) { if ($r['folder'] === $currentFolder) { $groupHasActive = true; break; } }
                ?>
                <div class="group-toggle<?= $groupHasActive ? '' : ' collapsed' ?>" data-bs-toggle="collapse" data-bs-target="#<?= $groupId ?>" aria-expanded="<?= $groupHasActive ? 'true' : 'false' ?>">
                    <span><?= htmlspecialchars(is_rtl() ? ($groupNamesAr[$groupName] ?? $groupName) : $groupName) ?></span>
                    <i class="bi bi-chevron-down chevron"></i>
                </div>
                <div class="collapse<?= $groupHasActive ? ' show' : '' ?>" id="<?= $groupId ?>">
                    <ul class="nav flex-column group-items mb-1">
                        <?php foreach ($groupRoutes as $r): ?>
                            <?php $active = ($currentFolder === $r['folder']) ? ' active' : ''; ?>
                            <li class="nav-item">
                                <a class="nav-link<?= $active ?>" href="<?= htmlspecialchars($r['url']) ?>">
                                    <?= htmlspecialchars(mod_display_name($r['folder'], $r)) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- Main content -->
    <div class="content-wrapper d-flex flex-column flex-grow-1" style="overflow-y: auto;">
        <main class="container-fluid py-4 px-4">
            <?php if ($flash = get_flash()): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                <?= htmlspecialchars($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
<?php endif; ?>
<script>
function toggleSidebar() {
    var s = document.getElementById('sidebar');
    if (s) s.classList.toggle('collapsed');
}
// Add red asterisk to mandatory field labels
document.addEventListener('DOMContentLoaded', function() {
    var inputs = document.querySelectorAll('input[required], select[required], textarea[required]');
    inputs.forEach(function(input) {
        var label = input.closest('.mb-3') ? input.closest('.mb-3').querySelector('label') : null;
        if (label && !label.querySelector('.text-danger')) {
            label.innerHTML += ' <span class="text-danger">*</span>';
        }
    });
});
</script>
