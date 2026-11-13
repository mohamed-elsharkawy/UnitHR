<?php
// Layout mode: ESS pages, auth pages, or the full admin layout.
// These flags can be preset by the caller; otherwise derive them from the request.
if (!isset($isEss)) {
    $isEss = (function_exists('current_module') && current_module() === 'ess')
        || (function_exists('current_module') && current_module() === 'notifications' && !is_app_staff());
}
if (!isset($isAuthPage)) {
    $isAuthPage = in_array($_GET['action'] ?? '', ['login', 'verify-otp', 'setup-otp', 'otp', 'logout', 'change-password'], true);
}

// Sidebar submenu groups. Each module declares its group in modules/<folder>/info.php.
// Groups are built dynamically from info.php files; no hardcoded arrays needed.
require_once __DIR__ . '/modules.php';

$routesByFolder = [];
foreach (get_routes() as $r) {
    $routesByFolder[$r['folder']] = $r;
}

// Build the submenu groups from each module's 'group' identifier.
$moduleGroups = get_module_groups();

if (!function_exists('mod_display_name')) {
    function mod_display_name($folder, $route) {
        if (lang() === 'ar') {
            return $route['name_ar'] ?? $route['name'] ?? $folder;
        }
        return $route['name'] ?? $folder;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= dir_attr() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="icon" type="image/png" href="<?php echo $_SESSION[UHR_SESSION_KEY]['applogo']; ?>">
    <title><?= htmlspecialchars(app('appname', 'UnitHR')) ?></title>
    <!-- Bootstrap CSS (offline assets) -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap-icons.min.css" rel="stylesheet">
    <!-- W3.CSS (offline assets): animations used across the app -->
    <link href="assets/css/w3.css" rel="stylesheet">
    <!-- Select2 (offline assets): searchable dropdowns in every module -->
    <link href="assets/css/select2.min.css" rel="stylesheet">
    <link href="assets/css/select2-bootstrap-5-theme.min.css" rel="stylesheet">
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
        .sidebar.collapsed { width: 0; overflow: hidden; padding: 0; border: 0; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -260px; z-index: 1040; height: 100vh; }
            [dir="rtl"] .sidebar { left: auto; right: -260px; }
            .sidebar.show { left: 0; }
            [dir="rtl"] .sidebar.show { right: 0; left: auto; }
        }
        .sidebar-toggle { display: none; }
        @media (max-width: 768px) { .sidebar-toggle { display: inline-block; } }
        /* RTL navbar: keep the language switch and user menu at the left edge */
        [dir="rtl"] .navbar-nav.ms-auto { margin-left: 0 !important; margin-right: auto !important; }
        [dir="rtl"] .navbar .dropdown-menu-end { left: 0; right: auto; }
        /* Sidebar full height */
        #sidebar { height: 100vh !important; min-height: 100vh !important; }
        /* Page container flex layout for sticky footer */
        .page-container { display: flex; flex-direction: column; min-height: 100vh; }
        .content-wrapper { flex: 1; }
        /* Footer styling */
        footer.bg-dark { background: #212529 !important; border-top: 1px solid #495057; }
        footer .container, footer .container-fluid { max-width: 100%; }
        footer p { margin: 0.5rem 0; font-size: 0.85rem; }
        footer a { color: #fff; text-decoration: none; }
        footer a:hover { color: #0d6efd; text-decoration: underline; }
		.raised-box {
		border: 2px outset #cccccc;
		padding: 1px;
		border-radius: 5px;
		}
		
		.raised-frame {
		border: 2px ridge #cccccc;
		padding: 1px;
		border-radius: 5px;
		}
        <?php if (function_exists('current_module') && current_module() === 'empdegrees'): ?>
        .table-responsive { max-height: 50vh; overflow-y: auto; }
        <?php endif; ?>
    </style>
</head>
<body>
<?php if ($isEss): ?>
<!-- Simple header for Employee Self-Service (no admin sidebar) -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="?module=ess">
            <img src="<?= htmlspecialchars(app('applogo')) ?>" alt="Logo" width="38" height="28" class="me-2 raised-box" onerror="this.style.display='none'">&nbsp;
            <span class="fw-bold"><?= htmlspecialchars(app('appname', 'UnitHR')) ?> - <?= lang() === 'ar' ? 'خدمة الموظف الذاتية' : 'Employee Self-Service' ?></span>
        </a>
        <ul class="navbar-nav ms-auto align-items-center">
            <?php if (is_logged_in()) { notifications_render_bell(); } ?>
            <li class="nav-item me-2">
                <a class="nav-link lang-switch text-white-50" href="?lang=<?= lang() === 'ar' ? 'en' : 'ar' ?>&module=ess">
                    <i class="bi bi-translate me-1"></i><?= lang() === 'ar' ? 'English' : 'العربية' ?>
                </a>
            </li>
        </ul>
    </div>
</nav>
<main class="container py-4" style="padding-bottom: 24vh !important;">
    <?php if ($flash = get_flash()): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show w3-animate-top">
        <?= htmlspecialchars($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
<?php elseif ($isAuthPage): ?>
<!-- Simple header for auth pages (no sidebar) -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="">
            <img src="<?= htmlspecialchars(app('applogo')) ?>" alt="Logo" width="38" height="28" class="me-2 raised-box" onerror="this.style.display='none'">
            <span class="fw-bold">&nbsp;<?= htmlspecialchars(app('appname', 'UnitHR')) ?></span>
        </a>
        <ul class="navbar-nav ms-auto align-items-center">
            <li class="nav-item me-2">
                <a class="nav-link lang-switch text-white-50" href="?lang=<?= lang() === 'ar' ? 'en' : 'ar' ?>">
                    <i class="bi bi-translate me-1"></i><?= lang() === 'ar' ? 'English' : 'العربية' ?>
                </a>
            </li>
        </ul>
    </div>
</nav>
<main class="container py-4" style="padding-bottom: 24vh !important;">
    <?php if ($flash = get_flash()): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show w3-animate-top">
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
                <?php if (is_logged_in()) { notifications_render_bell(); } ?>
                <li class="nav-item me-2">
                    <a class="nav-link lang-switch text-white-50" href="?lang=<?= lang() === 'ar' ? 'en' : 'ar' ?><?= isset($_GET['module']) ? '&module=' . urlencode($_GET['module']) : '' ?>">
                        <i class="bi bi-translate me-1"></i><?= lang() === 'ar' ? 'English' : 'العربية' ?>
                    </a>
                </li>
                <?php if (is_logged_in()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white-50" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1">&nbsp;</i><?= htmlspecialchars(current_user()['username'] ?? '') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end text-center">
                        <?php if (user_can('users', 'view')): ?>
                        <li><a class="dropdown-item" href="?module=users"><?= lang() === 'ar' ? 'المستخدمون' : 'Users' ?></a></li>
                        <?php endif; ?>
                        <?php if (user_can('accesslog', 'view')): ?>
                        <li><a class="dropdown-item" href="?module=accesslog"><?= lang() === 'ar' ? 'سجل الدخول' : 'Access Log' ?></a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="?action=change-password"><i class="bi bi-key me-1">&nbsp;</i><?= lang() === 'ar' ? 'تغيير كلمة المرور' : 'Change Password' ?></a></li>
                        <li><a class="dropdown-item" href="?action=logout"><?= lang() === 'ar' ? 'تسجيل خروج' : 'Logout' ?></a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link text-white-50" href="?action=login"><i class="bi bi-box-arrow-in-right me-1"></i><?= lang() === 'ar' ? 'تسجيل دخول' : 'Login' ?></a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <!-- Row 2: Sidebar + Page Content -->
    <div class="d-flex flex-grow-1" style="overflow: hidden;">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidemenu.php'; ?>

    <!-- Main content -->
    <div class="content-wrapper d-flex flex-column flex-grow-1" style="overflow-y: auto;">
        <main class="container-fluid py-4 px-4" style="padding-bottom: 24vh !important;">
            <?php if ($flash = get_flash()): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show w3-animate-top">
                <?= htmlspecialchars($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
<?php endif; ?>
<!-- jQuery + Select2 (offline assets): replaces the old dropdown search textbox -->
<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/select2.min.js"></script>
<script src="assets/js/select2-i18n-ar.js"></script>
<script>
function toggleSidebar() {
    var s = document.getElementById('sidebar');
    if (s) s.classList.toggle('collapsed');
}

// Add red asterisk to mandatory field labels and turn every dropdown into a
// Select2 search box. Runs once per page, after the DOM is ready, on every layout.
function initUI() {
    var requiredInputs = document.querySelectorAll('input[required], select[required], textarea[required]');
    requiredInputs.forEach(function(input) {
        var label = input.closest('.mb-3') ? input.closest('.mb-3').querySelector('label') : null;
        if (label && !label.querySelector('.text-danger')) {
            label.innerHTML += ' <span class="text-danger">*</span>';
        }
    });

    uhrInitSelect2(document);
}

// Convert all dropdowns inside a container to Select2. Safe to call again after
// a script rebuilds a select's options (uhrRefreshSelect2 below handles those).
function uhrInitSelect2(scope) {
    if (!window.jQuery || !jQuery.fn.select2) return;
    var selects = (scope || document).querySelectorAll('select.form-select, select.form-control');
    selects.forEach(function(select) {
        if (select.hasAttribute('data-select2-id')) return; // already initialised
        if (select.hasAttribute('data-no-s2-init')) return; // skip (custom init elsewhere)
        var placeholder = '';
        if (select.options.length && select.options[0].value === '') {
            placeholder = select.options[0].textContent;
        }
        jQuery(select).select2({
            theme: 'bootstrap-5',
            width: '100%',
            language: <?= json_encode(lang() === 'ar' ? 'ar' : 'en') ?>,
            placeholder: placeholder,
            allowClear: !!placeholder,
            minimumResultsForSearch: 0
        });
    });
}

// Re-sync Select2 display after options were rebuilt through innerHTML.
// Namespaced trigger refreshes Select2 only, so cascade handlers don't re-run.
function uhrRefreshSelect2(id) {
    if (!window.jQuery || !jQuery.fn.select2) return;
    var el = document.getElementById(id);
    if (el && el.hasAttribute('data-select2-id')) jQuery(el).trigger('change.select2');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initUI);
} else {
    initUI();
}
</script>
