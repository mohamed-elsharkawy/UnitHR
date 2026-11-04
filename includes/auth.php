<?php
/**
 * UnitHR - Authentication
 * Login with username/email + password. Admins & HR staff additionally require
 * a TOTP OTP from an offline authenticator.
 */

require_once __DIR__ . '/helpers.php';

function hash_password($plain) {
    return password_hash($plain, PASSWORD_DEFAULT);
}

function verify_password($plain, $stored) {
    if (!is_string($stored) || $stored === '') {
        return false;
    }
    // bcrypt, argon2, and other password_hash() formats
    if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')
        || str_starts_with($stored, '$2b$') || str_starts_with($stored, '$argon2')) {
        return password_verify($plain, $stored);
    }
    // Legacy fallback: reversible RC4 (for existing installs migrated before this fix)
    return decrypt($stored) === $plain;
}

/**
 * Process a login attempt. Returns ['ok'=>true,'user'=>..] or ['ok'=>false,'error'=>..]
 */
function attempt_login($login, $password, $otp = '') {
    $pdo = db();
    if (!$pdo) {
        return ['ok' => false, 'error' => 'Database connection is not available. Configure MySQL and run install.'];
    }
    $login = trim($login);
    if ($login === '' || $password === '') {
        return ['ok' => false, 'error' => 'Please enter username/email and password.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM " . table('users') . " WHERE (username = ? OR email = ?) AND omitted = 0 LIMIT 1");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['ok' => false, 'error' => 'Invalid username/email or password.'];
    }

    if (!verify_password($password, $user['password'])) {
        return ['ok' => false, 'error' => 'Invalid username/email or password.'];
    }

    // Determine permissions record
    $perms = [];
    $isAdmin = !empty($user['is_admin']);
    $isSuper = !empty($user['is_superadmin']);
    if ($isSuper || $isAdmin) {
        $perms['all_modules'] = true;
        $perms['_is_admin'] = 1;
        $perms['_is_superadmin'] = $isSuper ? 1 : 0;
        $perms['_is_hradmin'] = 1;
        $perms['_is_hruser'] = 1;
    } else {
        if (!empty($user['permissionid'])) {
            $pStmt = $pdo->prepare("SELECT * FROM " . table('permissions') . " WHERE id = ? AND omitted = 0 LIMIT 1");
            $pStmt->execute([(int)$user['permissionid']]);
            $p = $pStmt->fetch();
            if ($p) {
                $perms[$p['modulename']] = [
                    'isadmin' => !empty($p['isadmin']),
                    'canadd'  => !empty($p['canadd']),
                    'canedit' => !empty($p['canedit']),
                    'candel'  => !empty($p['candel']),
                ];
                $perms['_is_hradmin'] = !empty($p['ishradmin']) ? 1 : 0;
                $perms['_is_hruser']  = !empty($p['ishruser']) ? 1 : 0;
                $perms['_is_admin']   = 0;
                $perms['_is_superadmin'] = 0;
            }
        }
    }

    // OTP requirement for admins and HR staff
    $needsOtp = $isAdmin || $isSuper || !empty($perms['_is_hradmin']) || !empty($perms['_is_hruser']);

    if ($needsOtp) {
        if (!empty($user['otp_secret']) && !empty($user['otp_enabled'])) {
            // Password correct but OTP needed - store pending state, do NOT complete login yet
            $_SESSION['otp_pending'] = 1;
            $_SESSION['otp_pending_user'] = $user;
            $_SESSION['otp_pending_perms'] = $perms;
            $_SESSION['otp_pending_needsOtp'] = $needsOtp;
            return ['ok' => false, 'otp_pending' => true, 'error' => ''];
        } else {
            // First-time login with no OTP secret configured: allow login but force setup
            $_SESSION['otp_force_setup_user'] = (int)$user['id'];
            $_SESSION['otp_setup_perms'] = $perms;
            return ['ok' => true, 'otp_setup_required' => true, 'user' => $user];
        }
    }

    // No OTP needed - complete login
    return complete_login($user, $perms, $needsOtp);
}

/**
 * Complete the login process after password (and OTP if needed) verification.
 */
function complete_login($user, $perms, $needsOtp) {
    $pdo = db();
    $now = date('Y-m-d H:i:s');
    $isAdmin = !empty($user['is_admin']);
    $isSuper = !empty($user['is_superadmin']);

    $pdo->prepare("UPDATE " . table('users') . " SET lastedit = ?, lastlogin = ? WHERE id = ?")
        ->execute([$now, $now, (int)$user['id']]);
    $pdo->prepare("INSERT INTO " . table('accesslog') . "
        (rguid, username, ip, useragent, login_time, success, reason, dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
        VALUES (?,?,?,?,?,1,'Successful login',?,?,?,?,?,?,?,?,?,?,0)")
        ->execute([
            bin2hex(random_bytes(16)),
            $user['username'],
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $now,
            $now,
            (int)$user['id'],
            $user['username'],
            $user['addedbyname'] ?? $user['username'],
            $user['addedbynamear'] ?? $user['username'],
            $now,
            (int)$user['id'],
            $user['username'],
            $user['editedbyname'] ?? $user['username'],
            $user['editedbynamear'] ?? $user['username'],
        ]);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'username_ar' => $user['addedbynamear'] ?? $user['username'],
        'email' => $user['email'],
        'departmentid' => $user['departmentid'] ?? null,
        'departmentname' => $user['departmentname'] ?? '',
        'departmentnamear' => $user['departmentnamear'] ?? '',
        'permissionid' => $user['permissionid'] ?? null,
        'is_admin' => $isAdmin ? 1 : 0,
        'is_superadmin' => $isSuper ? 1 : 0,
        'is_hradmin' => !empty($perms['_is_hradmin']) ? 1 : 0,
        'is_hruser' => !empty($perms['_is_hruser']) ? 1 : 0,
        'otp_enabled' => !empty($user['otp_enabled']) ? 1 : 0,
    ];
    $_SESSION['user_permissions'] = $perms;
    $_SESSION['otp_verified'] = $needsOtp ? 1 : 0;

    // Clear pending state
    unset($_SESSION['otp_pending'], $_SESSION['otp_pending_user'], $_SESSION['otp_pending_perms'], $_SESSION['otp_pending_needsOtp']);

    return ['ok' => true, 'user' => $_SESSION['user']];
}

/**
 * Verify OTP for pending login. Returns ['ok'=>true] or ['ok'=>false, 'error'=>...].
 * On failure, logs the attempt and clears the session.
 */
function verify_otp_login($code) {
    if (empty($_SESSION['otp_pending']) || empty($_SESSION['otp_pending_user'])) {
        return ['ok' => false, 'error' => 'No pending login. Please start over.'];
    }

    $user = $_SESSION['otp_pending_user'];
    $perms = $_SESSION['otp_pending_perms'] ?? [];
    $needsOtp = !empty($_SESSION['otp_pending_needsOtp']);

    if (empty($user['otp_secret']) || !otp_verify($user['otp_secret'], $code)) {
        // OTP failed - log the attempt
        $pdo = db();
        $now = date('Y-m-d H:i:s');
        $pdo->prepare("INSERT INTO " . table('accesslog') . "
            (rguid, username, ip, useragent, login_time, success, reason, dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
            VALUES (?,?,?,?,?,0,'Failed OTP verification',?,?,?,?,?,?,?,?,?,?,0)")
            ->execute([
                bin2hex(random_bytes(16)),
                $user['username'],
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $now,
                $now,
                (int)$user['id'],
                $user['username'],
                $user['addedbyname'] ?? $user['username'],
                $user['addedbynamear'] ?? $user['username'],
                $now,
                (int)$user['id'],
                $user['username'],
                $user['editedbyname'] ?? $user['username'],
                $user['editedbynamear'] ?? $user['username'],
            ]);

        unset($_SESSION['otp_pending'], $_SESSION['otp_pending_user'], $_SESSION['otp_pending_perms'], $_SESSION['otp_pending_needsOtp']);
        return ['ok' => false, 'error' => 'Invalid OTP code. Please try again.'];
    }

    // OTP correct - complete login
    return complete_login($user, $perms, $needsOtp);
}

function logout() {
    $u = current_user();
    if ($u && db_available()) {
        q("INSERT INTO " . table('accesslog') . "
            (rguid, username, ip, useragent, login_time, success, reason, dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
            VALUES (?,?,?,?,?,1,'Logout',?,?,?,?,?,?,?,?,?,?,0)",
            [
                bin2hex(random_bytes(16)),
                $u['username'],
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
                (int)$u['id'],
                $u['username'],
                $u['username_ar'],
                $u['username_ar'],
                date('Y-m-d H:i:s'),
                (int)$u['id'],
                $u['username'],
                $u['username_ar'],
                $u['username_ar'],
            ]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
    header('Location: index.php');
    exit;
}
