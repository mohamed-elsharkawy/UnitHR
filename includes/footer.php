<?php if (!empty($isEss)): ?>
</main>
<footer class="bg-dark text-light text-center py-2 mt-auto" style="z-index: 1100; position: fixed !important; bottom:0vh !important; left:0 !important; width:100vw !important;">
    <div class="container">
        <p class="mb-0 small">&copy; <?= date('Y') ?> <?= htmlspecialchars(app('appname', 'UnitHR')) ?>
            <button type="button" class="btn btn-outline-light btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#aboutModal"><i class="bi bi-info-circle me-1">&nbsp;</i><?= lang() === 'ar' ? 'حول' : 'About' ?></button>
        </p>
    </div>
</footer>
<?php elseif (!empty($isAuthPage)): ?>
</main>
<footer class="bg-dark text-light text-center py-2 mt-auto" style="z-index: 1100; position: fixed !important; bottom:0vh !important; left:0 !important; width:100vw !important;">
    <div class="container">
        <p class="mb-0 small">&copy; <?= date('Y') ?> <?= htmlspecialchars(app('appname', 'UnitHR')) ?>
            <button type="button" class="btn btn-outline-light btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#aboutModal"><i class="bi bi-info-circle me-1">&nbsp;</i><?= lang() === 'ar' ? 'حول' : 'About' ?></button>
        </p>
    </div>
</footer>
<?php else: ?>
        </main>
    </div>
    <footer class="bg-dark text-light text-center py-2 mt-auto" style="z-index: 1100; position: fixed !important; bottom:0vh !important; left:0 !important; width:100vw !important;">
        <div class="container-fluid">
            <!--
			<p class="mb-0 small">&copy; <?= date('Y') ?> <a href="https://github.com/mohamed-elsharkawy/UnitHR" target="_blank" rel="noopener noreferrer" style="color: #fff; text-decoration: none;"><?= htmlspecialchars(app('appname', 'UnitHR')) ?></a> - <?= htmlspecialchars(app('appauthor')) ?>
			</p>
            -->
			<p class="mb-0 small">&copy; <?= date('Y') ?> <a href="https://github.com/mohamed-elsharkawy/UnitHR" target="_blank" rel="noopener noreferrer" style="color: #fff; text-decoration: none;"><?= htmlspecialchars(app('appname', 'UnitHR')) ?></a>
                <button type="button" class="btn btn-outline-light btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#aboutModal"><i class="bi bi-info-circle me-1">&nbsp;</i><?= lang() === 'ar' ? 'حول' : 'About' ?></button>
            </p>
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
$devtoolDisabled = !empty($_SESSION[UHR_SESSION_KEY]['devtool_disabled']);
?>
<!-- About modal: application information -->
<div class="modal fade w3-animate-zoom" id="aboutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
<?php if ($isAr): ?>
                <h5 class="modal-title"><i class="bi bi-info-circle me-1"></i><?= $isAr ? 'حول التطبيق' : 'About' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $isAr ? 'إغلاق' : 'Close' ?>"></button>
<?php else: ?>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $isAr ? 'إغلاق' : 'Close' ?>"></button>
                <h5 class="modal-title"><i class="bi bi-info-circle me-1"></i><?= $isAr ? 'حول التطبيق' : 'About' ?></h5>
<?php endif; ?>
            </div>
            <div class="modal-body text-center">
                <img src="<?= htmlspecialchars(app('applogo')) ?>" class="raised-box" alt="Logo" width="64" height="48" onerror="this.style.display='none'">
                <h4 class="mt-2 mb-1"><?= htmlspecialchars(app('appname', 'UnitHR')) ?></h4>
                <p class="text-muted mb-3"><?= htmlspecialchars(app('appversion', '')) ?></p>
                <table class="table table-sm text-start small mb-3">
                    <tr><th class="w-50"><?= $isAr ? 'الإصدار' : 'Version' ?></th><td><?= htmlspecialchars(app('appversion', '')) ?></td></tr>
                    <tr><th><?= $isAr ? 'الإطار البرمجي' : 'Framework' ?></th><td><a href="https://github.com/mohamed-elsharkawy/OpenFrame" target="_blank" rel="noopener noreferrer">OpenFrame</a></td></tr>
                    <tr><th><?= $isAr ? 'التطوير' : 'Developed by' ?></th><td><?= htmlspecialchars(app('appauthor')) ?></td></tr>
                    <tr><th><?= $isAr ? 'للتواصل' : 'Contact' ?></th><td><a href="mailto:<?= htmlspecialchars(app('appemail')) ?>"><?= htmlspecialchars(app('appemail')) ?></a></td></tr>
                    <!--
					<tr><th><?= $isAr ? 'قاعدة البيانات' : 'Database' ?></th><td><?= htmlspecialchars(app('dbname')) ?> (<?= htmlspecialchars(prefix()) ?>)</td></tr>
					-->
                </table>
                <p class="text-muted small mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars(app('appname', 'UnitHR')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= $isAr ? 'إغلاق' : 'Close' ?></button>
            </div>
        </div>
    </div>
</div>
<?php if ($isAdmin): ?>
<div class="text-center py-1" style="z-index: 1100; position: fixed !important; bottom:1vh !important; left:0 !important;">
    <a href="?toggle_devtool=1<?= isset($_GET['module']) ? '&module=' . urlencode($_GET['module']) : '' ?>" class="btn btn-sm <?= $devtoolDisabled ? 'btn-success' : 'btn-warning' ?>">
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
