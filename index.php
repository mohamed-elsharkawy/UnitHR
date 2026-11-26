<?php
// Buffer output so header()/redirects work even after HTML has been emitted
ob_start();
/**
 * UnitHR - Front Controller (OpenFrame pattern)
 * Loads config, DB, helpers, auth, routes, then either the login page or
 * the requested module inside the shared header/footer layout.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/routes.php';

// Front-controller entry marker: modules check this to prevent direct access.
if (!defined('UNITHR_ENTRY')) {
    define('UNITHR_ENTRY', true);
}

// Handle language switch
if (!empty($_GET['lang'])) {
    $_SESSION[UHR_SESSION_KEY]['lang'] = in_array($_GET['lang'], ['ar', 'en']) ? $_GET['lang'] : 'ar';
    header('Location: ' . (isset($_GET['module']) ? '?module=' . urlencode($_GET['module']) : './'));
    exit;
}

// Handle devtool toggle (admin only)
if (isset($_GET['toggle_devtool'])) {
    require_login();
    $u = current_user();
    if (is_superadmin() || !empty($u['is_admin'])) {
        $_SESSION[UHR_SESSION_KEY]['devtool_disabled'] = empty($_SESSION[UHR_SESSION_KEY]['devtool_disabled']) ? 1 : 0;
    }
    $redirect = './';
    if (isset($_GET['module'])) $redirect .= '?module=' . urlencode($_GET['module']);
    header('Location: ' . $redirect);
    exit;
}

// Handle actions
$action = $_GET['action'] ?? '';
if ($action === 'login') {
    if (is_logged_in()) {
        header('Location: ?module=home');
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $res = attempt_login($_POST['login'] ?? '', $_POST['password'] ?? '', $_POST['otp'] ?? '');
        if ($res['ok']) {
            if (!empty($res['otp_setup_required'])) {
                // First-time login: establish a partial session and redirect to OTP setup page
                $_SESSION[UHR_SESSION_KEY]['otp_setup_required'] = 1;
                $_SESSION[UHR_SESSION_KEY]['user_id'] = (int)($res['user']['id'] ?? 0);
                $_SESSION[UHR_SESSION_KEY]['user_permissions'] = $_SESSION[UHR_SESSION_KEY]['otp_setup_perms'] ?? [];
                $_SESSION[UHR_SESSION_KEY]['user'] = [
                    'id' => (int)($res['user']['id'] ?? 0),
                    'username' => $res['user']['username'] ?? '',
                    'username_ar' => $res['user']['username'] ?? '',
                    'email' => $res['user']['email'] ?? '',
                    'is_superadmin' => !empty($res['user']['is_superadmin']) ? 1 : 0,
                    'is_admin' => !empty($res['user']['is_admin']) ? 1 : 0,
                    'is_hradmin' => !empty($_SESSION[UHR_SESSION_KEY]['user_permissions']['_is_hradmin']) ? 1 : 0,
                    'is_hruser' => !empty($_SESSION[UHR_SESSION_KEY]['user_permissions']['_is_hruser']) ? 1 : 0,
                ];
                header('Location: ?action=setup-otp');
                exit;
            }
            $_SESSION[UHR_SESSION_KEY]['otp_verified'] = 1;
            // Redirect employees to ESS, admins to dashboard
            $redirectModule = is_app_staff() ? '' : 'ess';
            if ($redirectModule) {
                header('Location: ?module=' . $redirectModule);
            } else {
                header('Location: ./');
            }
            exit;
        }
        if (!empty($res['otp_required'])) {
            $_SESSION[UHR_SESSION_KEY]['login_error'] = $res['error'];
            $_SESSION[UHR_SESSION_KEY]['otp_required'] = 1;
            header('Location: ?action=login');
            exit;
        }
        // OTP pending - redirect to OTP verification page (second step)
        if (!empty($res['otp_pending'])) {
            header('Location: ?action=verify-otp');
            exit;
        }
        $_SESSION[UHR_SESSION_KEY]['login_error'] = $res['error'];
        header('Location: ?action=login');
        exit;
    }
    include __DIR__ . '/includes/header.php';
    include __DIR__ . '/includes/login.php';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Change Password: the self-service form now lives in the My Profile
// module so password, email and API-token settings share one page.
if ($action === 'change-password') {
    require_login();
    header('Location: ?module=userprofile#profile-password');
    exit;
}

// OTP verification page (second step of login)
if ($action === 'verify-otp') {
    if (empty($_SESSION[UHR_SESSION_KEY]['otp_pending'])) {
        header('Location: ?action=login');
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = trim($_POST['otp_code'] ?? '');
        $result = verify_otp_login($code);
        if ($result['ok']) {
            $redirectModule = is_app_staff() ? '' : 'ess';
            if ($redirectModule) {
                header('Location: ?module=' . $redirectModule);
            } else {
                header('Location: ./');
            }
            exit;
        }
        $_SESSION[UHR_SESSION_KEY]['login_error'] = $result['error'];
        header('Location: ?action=login');
        exit;
    }
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <h2 class="card-title"><i class="bi bi-shield-lock me-2"></i><?= lang() === 'ar' ? 'التحقق من رمز OTP' : 'OTP Verification' ?></h2>
                    </div>
                    <p class="text-center text-muted"><?= lang() === 'ar' ? 'أدخل رمز التحقق من تطبيق المصادقة.' : 'Enter the verification code from your authenticator app.' ?></p>
                    <?php if ($flash = get_flash()): ?>
                    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
                    <?php endif; ?>
                    <form action="?action=verify-otp" method="post" novalidate>
                        <div class="mb-3">
                            <label class="form-label"><?= lang() === 'ar' ? 'رمز التحقق (6 أرقام)' : 'Verification Code (6 digits)' ?></label>
                            <input type="text" name="otp_code" class="form-control" maxlength="6" required autofocus>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg"><?= lang() === 'ar' ? 'تحقق' : 'Verify' ?></button>
                            <a href="?action=logout" class="btn btn-secondary"><?= lang() === 'ar' ? 'إلغاء' : 'Cancel' ?></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

if ($action === 'logout') {
    logout();
    exit;
}

// Legacy 'otp' action: redirect to login (OTP is verified at login time)
if ($action === 'otp') {
    header('Location: ?action=login');
    exit;
}

// OTP setup page (first-time login for admins/HR staff)
if ($action === 'setup-otp') {
    require_login();
    if (empty($_SESSION[UHR_SESSION_KEY]['otp_setup_required'])) {
        header('Location: ./');
        exit;
    }
    $uid = (int)($_SESSION[UHR_SESSION_KEY]['otp_force_setup_user'] ?? current_user_id());
    $user = fetchRow("SELECT id, username, email, otp_secret, otp_enabled FROM " . table('users') . " WHERE id=? AND omitted=0 LIMIT 1", [$uid]);
    if (!$user) {
        header('Location: ./');
        exit;
    }
    if (empty($user['otp_secret'])) {
        $newSecret = otp_generate_secret();
        q("UPDATE " . table('users') . " SET otp_secret = ? WHERE id = ?", [$newSecret, $uid]);
        $user['otp_secret'] = $newSecret;
    }
    $qrUri = otp_provisioning_uri($user['otp_secret'], $user['username'], 'UnitHR');
    require_once __DIR__ . '/includes/qrcode.php';
    $qrSvg = qr_svg($qrUri, 4);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = trim($_POST['otp_code'] ?? '');
        if (otp_verify($user['otp_secret'], $code)) {
            q("UPDATE " . table('users') . " SET otp_enabled = 1 WHERE id = ?", [$uid]);
            $_SESSION[UHR_SESSION_KEY]['otp_setup_required'] = 0;
            $fullUser = fetchRow("SELECT * FROM " . table('users') . " WHERE id=? AND omitted=0 LIMIT 1", [$uid]);
            $perms = $_SESSION[UHR_SESSION_KEY]['otp_setup_perms'] ?? ($_SESSION[UHR_SESSION_KEY]['user_permissions'] ?? []);
            unset($_SESSION[UHR_SESSION_KEY]['otp_force_setup_user'], $_SESSION[UHR_SESSION_KEY]['otp_setup_perms']);
            if ($fullUser) {
                complete_login($fullUser, $perms, true);
            } else {
                $_SESSION[UHR_SESSION_KEY]['otp_verified'] = 1;
            }
            set_flash('success', 'OTP authenticator configured successfully.');
            header('Location: ./');
            exit;
        } else {
            set_flash('error', 'Invalid OTP code. Try again.');
        }
    }
    include __DIR__ . '/includes/header.php';
    ?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h2 class="card-title"><i class="bi bi-qr-code me-2"></i><?= lang() === 'ar' ? 'إعداد المصادقة الثنائية (OTP)' : 'OTP Authenticator Setup' ?></h2>
                </div>
                <p class="text-center mb-3"><?= lang() === 'ar' ? 'افتح تطبيق المصادقة (Google / Microsoft / FreeOTP / Oracle) واطبع الرمز التالي:' : 'Open your authenticator app (Google / Microsoft / FreeOTP / Oracle Authenticator) and scan the QR code:' ?></p>
                <div class="text-center mb-3">
                    <div class="mb-2"><?= $qrSvg ?></div>
                    <div class="alert alert-info mt-2">
                        <strong><?= lang() === 'ar' ? 'مفتاح السر (سري):' : 'Secret Key (keep private):' ?></strong>
                        <?= htmlspecialchars($user['otp_secret']) ?>
                    </div>
                    <div class="mb-2 text-muted small">
                        <?= lang() === 'ar' ? 'امسح رمز QR أعلاه بتطبيق المصادقة (Google / Microsoft / FreeOTP / Oracle). works offline (no internet needed).' : 'Scan the QR code above with your authenticator app (Google / Microsoft / FreeOTP / Oracle). Works offline.' ?>
                    </div>
                </div>
                <?php if ($flash = get_flash()): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
                <?php endif; ?>
                <form method="post" action="?action=setup-otp">
                    <div class="mb-3">
                        <label class="form-label"><?= lang() === 'ar' ? 'رمز التحقق (6 أرقام)' : 'Verification Code (6 digits)' ?></label>
                        <input type="text" name="otp_code" class="form-control" maxlength="6" required autofocus>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg"><?= lang() === 'ar' ? 'تفعيل وتسجيل الدخول' : 'Enable &amp; Login' ?></button>
                    </div>
                </form>
                <div class="text-center mt-3 text-muted small"><?= lang() === 'ar' ? 'الرمز يتغير كل 30 ثانية.' : 'Code changes every 30 seconds.' ?></div>
            </div>
        </div>
    </div>
</div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Employee Self-Service is open to employees (no admin login required);
// the ESS module is a self-contained page with its own layout.
if (current_module() === 'ess') {
    load_module('ess');
    exit;
}

// Require login for the application
require_login();

// Redirect employees (non-staff) to ESS if trying to access admin modules
// (notifications is available to employees: it is their personal inbox).
if (!is_app_staff() && !in_array(current_module(), ['ess', 'notifications', 'attpermissions', 'userprofile'], true)) {
    header('Location: ?module=ess');
    exit;
}

// Permission gate: non-admin users may only open modules they have view
// permission for (admins / superadmins have full access). Modules that are
// always available to signed-in users (dashboard, ESS, personal inbox,
// attendance self-service) bypass this check.
$alwaysVisibleModules = ['home', 'ess', 'notifications', 'attpermissions', 'userprofile'];
if (!is_superadmin() && !(current_user() && !empty(current_user()['is_admin']))) {
    $cm = current_module();
    if (!in_array($cm, $alwaysVisibleModules, true) && !user_can($cm, 'view')) {
        set_flash('error', 'You do not have permission to access this module.');
        header('Location: ?module=home');
        exit;
    }
}

// Disabled modules are hidden from the menu; also block direct URLs.
// "home" is never blocked here, otherwise a disabled dashboard would loop.
$cm = current_module();
if ($cm !== 'home') {
    $cmInfo = get_module($cm);
    if ($cmInfo !== null && array_key_exists('enabled', $cmInfo) && empty($cmInfo['enabled'])) {
        set_flash('error', lang() === 'ar' ? 'هذه الوحدة معطلة.' : 'This module is disabled.');
        header('Location: ?module=home');
        exit;
    }
}

// OTP gate for admins & HR staff
require_otp();

// AJAX endpoints must not be wrapped in the layout
if (isset($_GET['a']) && in_array($_GET['a'], ['retire_dor', 'superior'], true)) {
    load_module(current_module());
    exit;
}

include __DIR__ . '/includes/header.php';
load_module(current_module());
?>
<button type="button" id="uhrScrollTop" class="btn btn-primary shadow uhr-scroll-top"
        aria-label="<?= lang() === 'ar' ? 'العودة إلى الأعلى' : 'Back to top' ?>"
        title="<?= lang() === 'ar' ? 'العودة إلى الأعلى' : 'Back to top' ?>">
    <i class="bi bi-arrow-up"></i>
</button>
<script>
(function () {
    var btn = document.getElementById('uhrScrollTop');
    if (!btn) return;
    var content = document.querySelector('.content-wrapper');
    function scrollTopValue() {
        return Math.max(window.pageYOffset || 0, content ? content.scrollTop : 0);
    }
    function updateButton() {
        btn.classList.toggle('show', scrollTopValue() > 240);
    }
    window.addEventListener('scroll', updateButton, { passive: true });
    if (content) content.addEventListener('scroll', updateButton, { passive: true });
    btn.addEventListener('click', function () {
        if (content) content.scrollTo({ top: 0, behavior: 'smooth' });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    updateButton();
})();
</script>
<?php
include __DIR__ . '/includes/footer.php';
