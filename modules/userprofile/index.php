<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

/**
 * My Profile
 *
 * Self-service account page for every signed-in user:
 *   - update the account email (and the linked employeesmain email)
 *   - change the account password
 *   - view and rotate the persistent API token
 */
require_login();

if (!function_exists('profile_h')) {
    function profile_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('profile_role_label')) {
    function profile_role_label($user, $isAr)
    {
        $roles = [];
        if (!empty($user['is_superadmin'])) {
            $roles[] = $isAr ? 'مدير عام' : 'SuperAdmin';
        }
        if (!empty($user['is_admin'])) {
            $roles[] = $isAr ? 'مدير تطبيق' : 'App Admin';
        }
        $sessionUser = current_user() ?: [];
        if (!empty($sessionUser['is_hradmin'])) {
            $roles[] = $isAr ? 'مدير موارد بشرية' : 'HR Admin';
        }
        if (!empty($sessionUser['is_hruser'])) {
            $roles[] = $isAr ? 'مستخدم موارد بشرية' : 'HR User';
        }
        if (empty($roles)) {
            $roles[] = $isAr ? 'مستخدم' : 'User';
        }
        return implode(' / ', array_unique($roles));
    }
}

$isAr = lang() === 'ar';
$uid = current_user_id();

$user = fetchRow("SELECT * FROM " . table('users') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$uid]);
if (!$user) {
    set_flash('error', $isAr ? 'تعذر العثور على حسابك.' : 'Your account could not be found.');
    header('Location: ?action=logout');
    exit;
}

// Older accounts created before the API-token column existed are healed here.
$user['api_token'] = ensure_user_api_token($user);
$linkedEmployee = find_employee_for_user($user);

$profileAction = (string)($_POST['profile_action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profileAction !== '') {
    if (!csrf_verify()) {
        set_flash('error', $isAr ? 'انتهت صلاحية الجلسة. أعد المحاولة.' : 'Your session expired. Please try again.');
        header('Location: ?module=userprofile');
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $actorName = (string)($user['username'] ?? '');
    $actorNameAr = (string)($user['addedbynamear'] ?? $user['username'] ?? '');

    if ($profileAction === 'update_email') {
        $newEmail = trim((string)($_POST['email'] ?? ''));
        if ($newEmail === '') {
            set_flash('error', $isAr ? 'البريد الإلكتروني مطلوب.' : 'Email is required.');
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', $isAr ? 'صيغة البريد الإلكتروني غير صحيحة.' : 'Please enter a valid email address.');
        } elseif (mb_strlen($newEmail) > 255) {
            set_flash('error', $isAr ? 'البريد الإلكتروني طويل جداً.' : 'The email address is too long.');
        } elseif (strcasecmp($newEmail, (string)$user['email']) === 0) {
            set_flash('info', $isAr ? 'لم يتغير البريد الإلكتروني.' : 'The email address was not changed.');
        } elseif (fetchRow("SELECT id FROM " . table('users') . " WHERE email = ? AND id <> ? LIMIT 1", [$newEmail, $uid])) {
            set_flash('error', $isAr ? 'البريد الإلكتروني مستخدم بالفعل.' : 'That email address is already in use.');
        } else {
            shadow_log('users', $uid, $user, 'update');
            $saved = q("UPDATE " . table('users') . "
                         SET email = ?, lastedit = ?, editedbyid = ?, editedby = ?, editedbyname = ?, editedbynamear = ?
                         WHERE id = ?",
                [$newEmail, $now, $uid, $actorName, $actorName, $actorNameAr, $uid]);
            if ($saved) {
                $linkedId = sync_user_email_to_employee($user, (string)$user['email'], $newEmail);
                $_SESSION[UHR_SESSION_KEY]['user']['email'] = $newEmail;
                $message = $isAr ? 'تم تحديث البريد الإلكتروني.' : 'Email updated successfully.';
                if ($linkedId) {
                    $message .= $isAr
                        ? ' وتم تحديث بريد الموظف المرتبط في بيانات الموظفين.'
                        : ' The linked employee record was updated as well.';
                }
                set_flash('success', $message);
            } else {
                set_flash('error', $isAr ? 'تعذر تحديث البريد الإلكتروني.' : 'Could not update the email address.');
            }
        }
    } elseif ($profileAction === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            set_flash('error', $isAr ? 'جميع حقول كلمة المرور مطلوبة.' : 'All password fields are required.');
        } elseif (!verify_password($currentPassword, $user['password'])) {
            set_flash('error', $isAr ? 'كلمة المرور الحالية غير صحيحة.' : 'The current password is incorrect.');
        } elseif ($newPassword !== $confirmPassword) {
            set_flash('error', $isAr ? 'كلمة المرور الجديدة وتأكيدها غير متطابقين.' : 'The new password and confirmation do not match.');
        } elseif (strlen($newPassword) < 8) {
            set_flash('error', $isAr ? 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.' : 'The password must be at least 8 characters long.');
        } elseif ($newPassword === $currentPassword) {
            set_flash('error', $isAr ? 'اختر كلمة مرور مختلفة عن الحالية.' : 'Choose a password different from the current one.');
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $saved = q("UPDATE " . table('users') . "
                         SET password = ?, lastedit = ?, editedbyid = ?, editedby = ?, editedbyname = ?, editedbynamear = ?
                         WHERE id = ?",
                [$hash, $now, $uid, $actorName, $actorName, $actorNameAr, $uid]);
            if ($saved) {
                set_flash('success', $isAr ? 'تم تغيير كلمة المرور.' : 'Password changed successfully.');
            } else {
                set_flash('error', $isAr ? 'تعذر تغيير كلمة المرور.' : 'Could not change the password.');
            }
        }
    } elseif ($profileAction === 'regenerate_token') {
        $newToken = '';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = bin2hex(random_bytes(32));
            if (!fetchRow("SELECT id FROM " . table('users') . " WHERE api_token = ? AND id <> ? LIMIT 1", [$candidate, $uid])) {
                $newToken = $candidate;
                break;
            }
        }
        if ($newToken === '') {
            set_flash('error', $isAr ? 'تعذر إنشاء رمز جديد. حاول مرة أخرى.' : 'Could not generate a new token. Please try again.');
        } else {
            shadow_log('users', $uid, $user, 'update');
            $saved = q("UPDATE " . table('users') . "
                         SET api_token = ?, lastedit = ?, editedbyid = ?, editedby = ?, editedbyname = ?, editedbynamear = ?
                         WHERE id = ?",
                [$newToken, $now, $uid, $actorName, $actorName, $actorNameAr, $uid]);
            if ($saved) {
                set_flash('success', $isAr
                    ? 'تم إنشاء رمز API جديد. الرمز القديم وجميع رموز الدخول المؤقتة أصبحت غير صالحة.'
                    : 'A new API token was generated. The old token and all short-lived bearer tokens are now invalid.');
            } else {
                set_flash('error', $isAr ? 'تعذر حفظ الرمز الجديد.' : 'Could not save the new token.');
            }
        }
    }

    header('Location: ?module=userprofile');
    exit;
}

// Refresh after ensure_user_api_token() (it may have written a token).
$user = fetchRow("SELECT * FROM " . table('users') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$uid]);
$apiToken = (string)($user['api_token'] ?? '');
$linkedEmployee = find_employee_for_user($user);
$apiUrl = rtrim((string)app('apppath'), '/') . '/api/';

$department = $isAr
    ? (string)($user['departmentnamear'] ?? '')
    : (string)($user['departmentname'] ?? '');
if ($department === '') {
    $department = (string)($user['departmentname'] ?? $user['departmentnamear'] ?? '');
}
$employeeName = '';
if ($linkedEmployee) {
    $employeeName = $isAr
        ? (string)($linkedEmployee['empfullnamear'] ?? $linkedEmployee['empfullname'] ?? '')
        : (string)($linkedEmployee['empfullname'] ?? $linkedEmployee['empfullnamear'] ?? '');
}
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="mb-0"><i class="bi bi-person-circle me-2"></i><?= $isAr ? 'ملفي الشخصي' : 'My Profile' ?></h2>
    <a href="?module=home" class="btn btn-secondary">
        <i class="bi bi-arrow-<?= $isAr ? 'right' : 'left' ?> me-1"></i><?= $isAr ? 'العودة إلى لوحة التحكم' : 'Back to Dashboard' ?>
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-person-vcard me-1"></i><?= $isAr ? 'بيانات الحساب' : 'Account Information' ?>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4"><?= $isAr ? 'اسم المستخدم' : 'Username' ?></dt>
                    <dd class="col-sm-8"><code><?= profile_h($user['username']) ?></code></dd>

                    <dt class="col-sm-4"><?= $isAr ? 'البريد الإلكتروني' : 'Email' ?></dt>
                    <dd class="col-sm-8"><?= profile_h($user['email']) ?></dd>

                    <dt class="col-sm-4"><?= $isAr ? 'القسم' : 'Department' ?></dt>
                    <dd class="col-sm-8"><?= $department !== '' ? profile_h($department) : '—' ?></dd>

                    <dt class="col-sm-4"><?= $isAr ? 'الصلاحية' : 'Role' ?></dt>
                    <dd class="col-sm-8"><?= profile_h(profile_role_label($user, $isAr)) ?></dd>

                    <dt class="col-sm-4"><?= $isAr ? 'آخر دخول' : 'Last Login' ?></dt>
                    <dd class="col-sm-8"><?= !empty($user['lastlogin']) ? profile_h($user['lastlogin']) : '—' ?></dd>

                    <dt class="col-sm-4"><?= $isAr ? 'مصادقة OTP' : 'OTP Authentication' ?></dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($user['otp_enabled'])): ?>
                            <span class="badge bg-success"><?= $isAr ? 'مفعّلة' : 'Enabled' ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?= $isAr ? 'غير مفعّلة' : 'Not enabled' ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4"><?= $isAr ? 'الموظف المرتبط' : 'Linked Employee' ?></dt>
                    <dd class="col-sm-8">
                        <?php if ($linkedEmployee): ?>
                            <?php if (user_can('employeesmain', 'view')): ?>
                                <a href="?module=employeesmain&amp;a=view&amp;id=<?= (int)$linkedEmployee['id'] ?>">
                                    <?= profile_h($employeeName) ?>
                                </a>
                            <?php else: ?>
                                <?= profile_h($employeeName) ?>
                            <?php endif; ?>
                            <div class="small text-muted"><?= profile_h($linkedEmployee['email'] ?? '') ?></div>
                        <?php else: ?>
                            <span class="text-muted"><?= $isAr ? 'لا يوجد سجل موظف مرتبط.' : 'No linked employee record.' ?></span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-envelope me-1"></i><?= $isAr ? 'البريد الإلكتروني' : 'Email Address' ?>
            </div>
            <div class="card-body">
                <form method="post" action="?module=userprofile" autocomplete="email">
                    <?= csrf_field() ?>
                    <input type="hidden" name="profile_action" value="update_email">
                    <div class="mb-3">
                        <label class="form-label" for="profile_email"><?= $isAr ? 'البريد الإلكتروني' : 'Email Address' ?></label>
                        <input type="email" class="form-control" id="profile_email" name="email"
                               value="<?= profile_h($user['email']) ?>" maxlength="255" required>
                        <div class="form-text">
                            <?= $isAr
                                ? 'إذا كان حسابك مرتبطاً بموظف، فسيتم تحديث بريده في بيانات الموظفين أيضاً.'
                                : 'If your account is linked to an employee, the employee master email is updated too.' ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i><?= $isAr ? 'حفظ البريد الإلكتروني' : 'Save Email' ?>
                    </button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm" id="profile-password">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-key me-1"></i><?= $isAr ? 'كلمة المرور' : 'Password' ?>
            </div>
            <div class="card-body">
                <form method="post" action="?module=userprofile" autocomplete="current-password">
                    <?= csrf_field() ?>
                    <input type="hidden" name="profile_action" value="change_password">
                    <div class="mb-3">
                        <label class="form-label" for="profile_current_password"><?= $isAr ? 'كلمة المرور الحالية' : 'Current Password' ?></label>
                        <input type="password" class="form-control" id="profile_current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="profile_new_password"><?= $isAr ? 'كلمة المرور الجديدة' : 'New Password' ?></label>
                            <input type="password" class="form-control" id="profile_new_password" name="new_password" minlength="8" required autocomplete="new-password">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="profile_confirm_password"><?= $isAr ? 'تأكيد كلمة المرور الجديدة' : 'Confirm New Password' ?></label>
                            <input type="password" class="form-control" id="profile_confirm_password" name="confirm_password" minlength="8" required autocomplete="new-password">
                        </div>
                    </div>
                    <div class="form-text mb-3">
                        <?= $isAr ? '8 أحرف على الأقل. يُفضل استخدام مزيج من الحروف والأرقام والرموز.' : 'At least 8 characters. Mix letters, numbers and symbols for a stronger password.' ?>
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-shield-lock me-1"></i><?= $isAr ? 'تغيير كلمة المرور' : 'Change Password' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-3">
    <div class="card-header bg-info text-dark d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-key-fill me-1"></i><?= $isAr ? 'رمز واجهة البرمجة (API Token)' : 'API Token' ?></span>
        <span class="badge bg-light text-dark"><?= $isAr ? 'سري' : 'Secret' ?></span>
    </div>
    <div class="card-body">
        <p class="mb-2">
            <?= $isAr
                ? 'استخدم هذا الرمز في ترويسة الطلبات:'
                : 'Use this token in the request header:' ?>
            <code>X-API-Token: &lt;token&gt;</code>
            <?= $isAr ? 'أو' : 'or' ?>
            <code>Authorization: Bearer &lt;token&gt;</code>
        </p>
        <div class="input-group mb-3">
            <input type="password" class="form-control font-monospace" id="profile_api_token"
                   value="<?= profile_h($apiToken) ?>" readonly aria-label="API token">
            <button class="btn btn-outline-secondary" type="button" id="profile_token_toggle">
                <i class="bi bi-eye"></i><span class="d-none d-sm-inline ms-1"><?= $isAr ? 'إظهار' : 'Show' ?></span>
            </button>
            <button class="btn btn-outline-primary" type="button" id="profile_token_copy">
                <i class="bi bi-clipboard"></i><span class="d-none d-sm-inline ms-1"><?= $isAr ? 'نسخ' : 'Copy' ?></span>
            </button>
        </div>
        <div class="alert alert-warning small mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= $isAr
                ? 'هذا الرمز يمنح نفس صلاحيات حسابك عبر واجهة البرمجة. لا تشاركه مع أي شخص. عند إعادة إنشائه يتوقف الرمز القديم فوراً.'
                : 'This token grants the same access as your account through the API. Do not share it. Regenerating it invalidates the old token immediately.' ?>
        </div>
        <form method="post" action="?module=userprofile" onsubmit="return confirm('<?= $isAr ? 'إنشاء رمز API جديد؟ سيتوقف الرمز القديم عن العمل.' : 'Generate a new API token? The old token will stop working.' ?>');">
            <?= csrf_field() ?>
            <input type="hidden" name="profile_action" value="regenerate_token">
            <button type="submit" class="btn btn-outline-danger">
                <i class="bi bi-arrow-repeat me-1"></i><?= $isAr ? 'إنشاء رمز جديد' : 'Regenerate Token' ?>
            </button>
        </form>
        <hr>
        <div class="small text-muted">
            <strong><?= $isAr ? 'مثال:' : 'Example:' ?></strong>
            <code dir="ltr">curl -H "X-API-Token: <?= profile_h($apiToken) ?>" "<?= profile_h($apiUrl) ?>?action=me"</code>
        </div>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('profile_api_token');
    var toggle = document.getElementById('profile_token_toggle');
    var copy = document.getElementById('profile_token_copy');

    if (toggle && input) {
        toggle.addEventListener('click', function () {
            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            var icon = toggle.querySelector('i');
            if (icon) icon.className = reveal ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    }

    if (copy && input) {
        copy.addEventListener('click', function () {
            var value = input.value;
            function done() {
                var old = copy.innerHTML;
                copy.innerHTML = '<i class="bi bi-check2"></i><span class="d-none d-sm-inline ms-1"><?= $isAr ? 'تم النسخ' : 'Copied' ?></span>';
                setTimeout(function () { copy.innerHTML = old; }, 1600);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(done).catch(function () {});
            } else {
                input.type = 'text';
                input.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                input.type = 'password';
            }
        });
    }
})();
</script>
