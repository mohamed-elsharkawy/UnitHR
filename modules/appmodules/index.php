<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

/**
 * Application Modules - list modules and enable/disable them.
 *
 * Only SuperAdmins may enable or disable a module. A toggle rewrites the
 * module's info.php 'enabled' flag and then synchronizes uhr_appmodules by
 * running the same routine used by cron.php.
 */
require_login();
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/modules.php';
require_once __DIR__ . '/../../includes/module_sync.php';

$module = 'appmodules';
$isAr = lang() === 'ar';
$isSuper = is_superadmin();
$action = $_GET['a'] ?? 'list';

// Core modules keep the application reachable. Disabling one of these could
// lock every user (including the SuperAdmin) out of the UI.
$protectedModules = ['home', 'appmodules', 'ess', 'notifications', 'attpermissions', 'userprofile'];

if (!function_exists('appmodules_redirect')) {
    function appmodules_redirect(): void
    {
        header('Location: ?module=appmodules');
        exit;
    }
}

if (!function_exists('appmodules_error')) {
    function appmodules_error(string $message): void
    {
        set_flash('error', $message);
        appmodules_redirect();
    }
}

if ($action === 'toggle') {
    if (!$isSuper) {
        appmodules_error($isAr
            ? 'غير مسموح. فقط المدير العام يمكنه تفعيل أو تعطيل الوحدات.'
            : 'Not allowed. Only SuperAdmin can enable or disable modules.');
    }

    $folder = (string)($_GET['folder'] ?? '');
    $enabled = (int)($_GET['enabled'] ?? 1) === 1;

    if (!preg_match('/^[a-z0-9_-]+$/i', $folder)) {
        appmodules_error($isAr ? 'اسم مجلد غير صالح.' : 'Invalid module folder.');
    }
    if (in_array($folder, $protectedModules, true)) {
        appmodules_error($isAr
            ? 'لا يمكن تعطيل هذه الوحدة لأنها أساسية لعمل التطبيق.'
            : 'This core module cannot be disabled.');
    }

    $infoFile = dirname(__DIR__) . '/' . $folder . '/info.php';
    if (!is_file($infoFile)) {
        appmodules_error($isAr ? 'الوحدة غير موجودة.' : 'Module not found.');
    }

    $content = file_get_contents($infoFile);
    if ($content === false) {
        appmodules_error($isAr ? 'تعذر قراءة ملف الوحدة.' : 'Could not read the module info file.');
    }

    $count = 0;
    $new = preg_replace(
        "/(['\"]enabled['\"]\s*=>\s*)(true|false)/i",
        '${1}' . ($enabled ? 'true' : 'false'),
        $content,
        1,
        $count
    );
    if ($new === null || $count === 0) {
        appmodules_error($isAr
            ? 'تعذر العثور على خاصية enabled في ملف info.php.'
            : 'Could not find the enabled flag in info.php.');
    }
    if ($new !== $content && file_put_contents($infoFile, $new) === false) {
        appmodules_error($isAr ? 'تعذر حفظ ملف الوحدة.' : 'Could not write the module info file.');
    }

    // Drop the compiled copy so the sync below reads the new value immediately.
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($infoFile, true);
    }
    clearstatcache(true, $infoFile);

    try {
        $stats = sync_modules_to_db();
        set_flash('success', $isAr
            ? 'تم ' . ($enabled ? 'تفعيل' : 'تعطيل') . ' الوحدة "' . $folder . '" وتحديث قاعدة البيانات (' . $stats['updated'] . ' سجل).'
            : 'Module "' . $folder . '" has been ' . ($enabled ? 'enabled' : 'disabled') . ' and the database was updated (' . $stats['updated'] . ' records).');
    } catch (Throwable $e) {
        set_flash('error', $isAr
            ? 'تم تحديث info.php لكن فشلت مزامنة قاعدة البيانات: ' . $e->getMessage()
            : 'info.php was updated but the database sync failed: ' . $e->getMessage());
    }
    appmodules_redirect();
}

if ($action === 'sync') {
    if (!$isSuper) {
        appmodules_error($isAr
            ? 'غير مسموح. فقط المدير العام يمكنه مزامنة الوحدات.'
            : 'Not allowed. Only SuperAdmin can synchronize modules.');
    }

    try {
        $stats = sync_modules_to_db();
        set_flash('success', $isAr
            ? 'تمت مزامنة وحدات التطبيق مع قاعدة البيانات (' . $stats['updated'] . ' تحديث، ' . $stats['inserted'] . ' إضافة).'
            : 'Application modules synchronized with the database (' . $stats['updated'] . ' updated, ' . $stats['inserted'] . ' inserted).');
    } catch (Throwable $e) {
        set_flash('error', $isAr
            ? 'فشلت مزامنة قاعدة البيانات: ' . $e->getMessage()
            : 'Database synchronization failed: ' . $e->getMessage());
    }
    appmodules_redirect();
}

$search = trim((string)($_GET['search'] ?? ''));
$allModules = get_modules();

// The DB flag is only a mirror. The info.php value is the source of truth for
// whether a module is actually active, so warn when the two disagree.
$dbStatus = [];
foreach (fetchAll("SELECT modulefolder, enabled FROM " . table('appmodules') . " WHERE omitted = 0") as $r) {
    $dbStatus[$r['modulefolder']] = (int)$r['enabled'];
}
$mismatch = false;
foreach ($allModules as $m) {
    $folder = (string)($m['folder'] ?? '');
    $diskEnabled = (!array_key_exists('enabled', $m) || !empty($m['enabled'])) ? 1 : 0;
    if (array_key_exists($folder, $dbStatus) && $dbStatus[$folder] !== $diskEnabled) {
        $mismatch = true;
        break;
    }
}

$modules = $allModules;
if ($search !== '') {
    $needle = $search;
    $modules = array_values(array_filter($modules, function ($m) use ($needle) {
        $haystack = implode(' ', [
            $m['name'] ?? '',
            $m['name_ar'] ?? '',
            $m['folder'] ?? '',
            $m['group'] ?? '',
            $m['group_ar'] ?? '',
        ]);
        return stripos($haystack, $needle) !== false;
    }));
}

?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-puzzle me-2"></i><?= $isAr ? 'وحدات التطبيق' : 'Application Modules' ?></h2>
    <?php if ($isSuper): ?>
    <a href="?module=appmodules&amp;a=sync" class="btn btn-outline-primary">
        <i class="bi bi-arrow-repeat me-1"></i><?= $isAr ? 'مزامنة الآن' : 'Sync Now' ?>
    </a>
    <?php endif; ?>
</div>

<?php if (!$isSuper): ?>
<div class="alert alert-info small mb-3">
    <?= $isAr ? 'تفعيل/تعطيل الوحدات متاح للمدير العام فقط.' : 'Enabling/disabling modules is restricted to SuperAdmins.' ?>
</div>
<?php endif; ?>

<?php if ($mismatch): ?>
<div class="alert alert-warning small mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <?= $isAr
        ? 'حالة قاعدة البيانات لا تطابق ملفات الوحدات. استخدم "مزامنة الآن" لتحديثها.'
        : 'The database state does not match the module files. Use "Sync Now" to update it.' ?>
</div>
<?php endif; ?>

<form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="module" value="appmodules">
    <div class="col-md-4">
        <input type="search" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>"
               placeholder="<?= $isAr ? 'بحث (الاسم/المجلد/المجموعة)...' : 'Search (name/folder/group)...' ?>">
    </div>
    <div class="col-auto">
        <button class="btn btn-primary"><i class="bi bi-search me-1"></i><?= $isAr ? 'بحث' : 'Search' ?></button>
    </div>
    <div class="col-auto">
        <a href="?module=appmodules" class="btn btn-outline-secondary"><?= $isAr ? 'إعادة تعيين' : 'Reset' ?></a>
    </div>
</form>

<?php if (empty($modules)): ?>
<div class="alert alert-info"><?= $isAr ? 'لا توجد وحدات مطابقة.' : 'No matching modules.' ?></div>
<?php else: ?>
<div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
<table class="table table-striped table-hover align-middle table-sm">
    <thead class="table-dark">
    <tr>
        <th>#</th>
        <th><?= $isAr ? 'الوحدة' : 'Module' ?></th>
        <th><?= $isAr ? 'المجلد' : 'Folder' ?></th>
        <th><?= $isAr ? 'المجموعة' : 'Group' ?></th>
        <th><?= $isAr ? 'الترتيب' : 'Order' ?></th>
        <th><?= $isAr ? 'الحالة' : 'Status' ?></th>
        <?php if ($isSuper): ?><th><?= $isAr ? 'التحكم' : 'Actions' ?></th><?php endif; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($modules as $i => $m):
        $folder = (string)($m['folder'] ?? '');
        $enabled = (!array_key_exists('enabled', $m) || !empty($m['enabled'])) ? 1 : 0;
        $isProtected = in_array($folder, $protectedModules, true);
    ?>
    <tr>
        <td><?= $i + 1 ?></td>
        <td>
            <i class="<?= htmlspecialchars($m['icon'] ?? 'bi bi-box') ?> me-1"></i>
            <?= htmlspecialchars($isAr ? ($m['name_ar'] ?? $m['name'] ?? $folder) : ($m['name'] ?? $folder)) ?>
        </td>
        <td><code><?= htmlspecialchars($folder) ?></code></td>
        <td><?= htmlspecialchars($isAr ? ($m['group_ar'] ?? $m['group'] ?? '') : ($m['group'] ?? '')) ?></td>
        <td><?= (int)($m['order'] ?? 0) ?></td>
        <td>
            <?php if ($enabled): ?>
                <span class="badge bg-success"><?= $isAr ? 'مفعّلة' : 'Enabled' ?></span>
            <?php else: ?>
                <span class="badge bg-secondary"><?= $isAr ? 'معطّلة' : 'Disabled' ?></span>
            <?php endif; ?>
        </td>
        <?php if ($isSuper): ?>
        <td>
            <?php if ($isProtected): ?>
                <span class="badge bg-light text-dark" title="<?= $isAr ? 'وحدة أساسية لا يمكن تعطيلها' : 'Core module; cannot be disabled' ?>">
                    <i class="bi bi-lock me-1"></i><?= $isAr ? 'أساسية' : 'Required' ?>
                </span>
            <?php elseif ($enabled): ?>
                <a href="?module=appmodules&amp;a=toggle&amp;folder=<?= urlencode($folder) ?>&amp;enabled=0"
                   class="btn btn-sm btn-outline-secondary"
                   onclick="return confirm('<?= $isAr ? 'تعطيل هذه الوحدة؟' : 'Disable this module?' ?>')">
                    <i class="bi bi-toggle-off me-1"></i><?= $isAr ? 'تعطيل' : 'Disable' ?>
                </a>
            <?php else: ?>
                <a href="?module=appmodules&amp;a=toggle&amp;folder=<?= urlencode($folder) ?>&amp;enabled=1"
                   class="btn btn-sm btn-outline-success"
                   onclick="return confirm('<?= $isAr ? 'تفعيل هذه الوحدة؟' : 'Enable this module?' ?>')">
                    <i class="bi bi-toggle-on me-1"></i><?= $isAr ? 'تفعيل' : 'Enable' ?>
                </a>
            <?php endif; ?>
        </td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
