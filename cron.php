<?php
/**
 * cron.php - Sync module info.php data to the appmodules database table.
 *
 * Reads every modules/<folder>/info.php file and synchronizes it with
 * uhr_appmodules. New modules are inserted, existing ones are updated,
 * and modules removed from disk are marked as omitted.
 *
 * Usage (CLI):
 *   php cron.php
 *
 * Can also be registered as a scheduled task / cron job.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/modules.php';

$pdo = db();
if (!$pdo) {
    die("Database connection failed.\n");
}

$P = prefix();
$modulesDir = __DIR__ . '/modules';
$modules = get_modules();

// Build a map of folder => module info from disk
$diskModules = [];
foreach ($modules as $m) {
    $diskModules[$m['folder']] = $m;
}

// Get existing modules from the database
$existing = [];
$stmt = $pdo->query("SELECT modulefolder, id, rguid FROM {$P}appmodules");
while ($row = $stmt->fetch()) {
    $existing[$row['modulefolder']] = $row;
}

$now = date('Y-m-d H:i:s');
$addedById = 1; // System
$addedBy = 'System';
$addedByName = 'System';
$addedByNameAr = 'نظام';

$inserted = 0;
$updated = 0;
$omitted = 0;

foreach ($diskModules as $folder => $m) {
    $rguid = bin2hex(random_bytes(16));
    $name = $m['name'] ?? $folder;
    $nameAr = $m['name_ar'] ?? $name; // info.php may not have name_ar yet
    $description = $m['description'] ?? '';
    $descriptionAr = $m['description_ar'] ?? $description;
    $groupName = $m['group'] ?? 'Other';
    $groupNameAr = $m['group_ar'] ?? $groupName;
    $groupOrder = (int)($m['group_order'] ?? 999);
    $menuOrder = (int)($m['order'] ?? 999);
    $enabled = !empty($m['enabled']) ? 1 : 0;
    $omitted = 0;

    if (isset($existing[$folder])) {
        // Update existing module
        $rowId = $existing[$folder]['id'];
        $pdo->prepare("UPDATE {$P}appmodules SET
            modulename = ?, modulenamear = ?, modulefolder = ?, menu_order = ?,
            description = ?, description_ar = ?, group_name = ?, group_name_ar = ?, group_order = ?,
            lastedit = NOW(), editedbyid = ?, editedby = ?, editedbyname = ?, editedbynamear = ?,
            omitted = 0
            WHERE id = ?")->execute([
            $name, $nameAr, $folder, $menuOrder,
            $description, $descriptionAr, $groupName, $groupNameAr, $groupOrder,
            $addedById, $addedBy, $addedByName, $addedByNameAr,
            $rowId
        ]);
        $updated++;
    } else {
        // Insert new module
        $pdo->prepare("INSERT INTO {$P}appmodules (
            rguid, modulename, modulenamear, modulefolder, menu_order,
            description, description_ar, group_name, group_name_ar, group_order,
            dtadded, addedbyid, addedby, addedbyname, addedbynamear,
            lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted
        ) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,NOW(),?,?,?,?,0)")->execute([
            $rguid, $name, $nameAr, $folder, $menuOrder,
            $description, $descriptionAr, $groupName, $groupNameAr, $groupOrder,
            $addedById, $addedBy, $addedByName, $addedByNameAr,
            $addedById, $addedBy, $addedByName, $addedByNameAr
        ]);
        $inserted++;
    }
}

// Mark modules that exist in DB but not on disk as omitted
foreach ($existing as $folder => $row) {
    if (!isset($diskModules[$folder])) {
        $pdo->prepare("UPDATE {$P}appmodules SET omitted = 1, lastedit = NOW() WHERE id = ?")->execute([$row['id']]);
        $omitted++;
    }
}

echo "AppModules sync complete.\n";
echo "  Inserted: $inserted\n";
echo "  Updated:  $updated\n";
echo "  Omitted:  $omitted\n";
echo "  Total on disk: " . count($diskModules) . "\n";
echo "  Total in DB:  " . count($existing) . "\n";
