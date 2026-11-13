<?php
/**
 * UnitHR - Login form (shown when not authenticated)
 * Two tabs: Employees (default) and Admins
 */
$isAr = lang() === 'ar';
$error = $_SESSION[UHR_SESSION_KEY]['login_error'] ?? '';
unset($_SESSION[UHR_SESSION_KEY]['login_error']);
$otpRequired = !empty($_SESSION[UHR_SESSION_KEY]['otp_required']);
unset($_SESSION[UHR_SESSION_KEY]['otp_required']);
$otpSetup = !empty($_SESSION[UHR_SESSION_KEY]['otp_setup_required']);
unset($_SESSION[UHR_SESSION_KEY]['otp_setup_required']);

// Determine which tab to show
$activeTab = 'employees';
if ($otpRequired || $otpSetup) {
    $activeTab = 'admins';
}
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow w3-animate-top">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img src="<?= htmlspecialchars(app('applogo')) ?>" alt="Logo" width="65" height="48" class="mb-2 raised-box" onerror="this.style.display='none'">
                    <h2 class="card-title"><?= htmlspecialchars(app('appname')) ?></h2>
                    <p class="text-muted"><?= htmlspecialchars(app('appname')) ?> - HR Management Solution</p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger w3-animate-top"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Login Tabs -->
                <ul class="nav nav-tabs mb-3" id="loginTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab === 'employees' ? 'active' : '' ?>" id="employees-tab" data-bs-toggle="tab" data-bs-target="#employees" type="button" role="tab">
                            <i class="bi bi-person me-1"></i><?= $isAr ? 'تسجيل دخول الموظفين' : 'Employee Login' ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab === 'admins' ? 'active' : '' ?>" id="admins-tab" data-bs-toggle="tab" data-bs-target="#admins" type="button" role="tab">
                            <i class="bi bi-shield-lock me-1"></i><?= $isAr ? 'تسجيل دخول المشرفين' : 'Admin Login' ?>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="loginTabContent">
                    <!-- Employees Login Tab (default) -->
                    <div class="tab-pane fade <?= $activeTab === 'employees' ? 'show active' : '' ?>" id="employees" role="tabpanel">
                        <form action="?action=login" method="post" novalidate>
                            <div class="mb-3">
                                <label class="form-label"><?= $isAr ? 'اسم المستخدم / البريد الإلكتروني' : 'Username / Email' ?></label>
                                <input type="text" name="login" class="form-control" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= $isAr ? 'كلمة المرور' : 'Password' ?></label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg"><?= $isAr ? 'تسجيل دخول' : 'Login' ?></button>
                            </div>
                        </form>
                        <div class="text-center mt-3 text-muted small">
                            <?= $isAr ? 'أدخل بيانات اعتمادك للوصول إلى خدمة الموظف الذاتية.' : 'Enter your credentials to access Employee Self-Service.' ?>
                        </div>
                    </div>

                    <!-- Admins Login Tab -->
                    <div class="tab-pane fade <?= $activeTab === 'admins' ? 'show active' : '' ?>" id="admins" role="tabpanel">
                        <form action="?action=login" method="post" novalidate>
                            <div class="mb-3">
                                <label class="form-label"><?= $isAr ? 'اسم المستخدم / البريد الإلكتروني' : 'Username / Email' ?></label>
                                <input type="text" name="login" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= $isAr ? 'كلمة المرور' : 'Password' ?></label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <!-- OTP is now a separate step (verify-otp page) -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg"><?= $otpSetup ? ($isAr ? 'تفعيل OTP' : 'Verify &amp; Setup OTP') : ($isAr ? 'تسجيل دخول' : 'Login') ?></button>
                            </div>
                        </form>
                        <div class="text-center mt-3 text-muted small">
                            <?= $isAr ? 'المشرفون ومدراء الموارد البشرية يتطلبون رمز OTP من تطبيق مصادقة.' : 'Admins &amp; HR staff require OTP from an authenticator app.' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
