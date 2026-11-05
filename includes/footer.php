<?php if (current_module() === 'ess'): ?>
</main>
<footer class="bg-dark text-light text-center py-3 mt-auto">
    <div class="container">
        <p class="mb-0 small">&copy; <?= date('Y') ?> <?= htmlspecialchars(app('appname', 'UnitHR')) ?></p>
    </div>
</footer>
<?php elseif (in_array($_GET['action'] ?? '', ['login', 'verify-otp', 'setup-otp', 'otp', 'logout', 'change-password'])): ?>
</main>
<footer class="bg-dark text-light text-center py-3 mt-auto">
    <div class="container">
        <p class="mb-0 small">&copy; <?= date('Y') ?> <?= htmlspecialchars(app('appname', 'UnitHR')) ?></p>
    </div>
</footer>
<?php else: ?>
        </main>
    </div>
    </div>
    <footer class="bg-dark text-light text-center py-3 mt-auto">
        <div class="container-fluid">
            <p class="mb-0 small">&copy; <?= date('Y') ?> <?= htmlspecialchars(app('appname', 'UnitHR')) ?> - <?= htmlspecialchars(app('appauthor')) ?></p>
        </div>
    </footer>
</div>
<?php endif; ?>
<!-- Bootstrap JS (offline assets) -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
<?php
// Show devtool toggle button for admins only
$isAr = lang() === 'ar';
$u = current_user();
$isAdmin = is_superadmin() || !empty($u['is_admin']);
$devtoolDisabled = !empty($_SESSION['devtool_disabled']);
?>
<?php if ($isAdmin): ?>
<div class="text-center py-1" style="background: #343a40;">
    <a href="index.php?toggle_devtool=1<?= isset($_GET['module']) ? '&module=' . urlencode($_GET['module']) : '' ?>" class="btn btn-sm <?= $devtoolDisabled ? 'btn-success' : 'btn-warning' ?>">
        <i class="bi bi-<?= $devtoolDisabled ? 'shield-check' : 'shield-exclamation' ?> me-1"></i>
        <?= $isAr ? ($devtoolDisabled ? 'تفعيل حماية DevTools' : 'تعطيل حماية DevTools') : ($devtoolDisabled ? 'Enable DevTools Protection' : 'Disable DevTools Protection') ?>
    </a>
    <small class="text-muted ms-2"><?= $devtoolDisabled ? ($isAr ? '(معطلة لهذه الجلسة)' : '(disabled for this session)') : ($isAr ? '(نشطة)' : '(active)') ?></small>
</div>
<?php endif; ?>
<?php if (!$devtoolDisabled): ?>
<!-- Disable DevTools -->
<script disable-devtool-auto src="assets/js/disable-devtool.min.js" disable-menu="true" disable-cut="false" disable-copy="false" disable-paste="false" tk-name="mshark" clear-log="true" url="redirect.html"></script>
<?php endif; ?>
</body>
</html>
