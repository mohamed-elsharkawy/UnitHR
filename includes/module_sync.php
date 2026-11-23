<?php
/**
 * UnitHR - Module synchronization
 *
 * Keeps the uhr_appmodules table in sync with every modules/<folder>/info.php
 * file. Used by cron.php (CLI) and by the Application Modules screen after a
 * module is enabled or disabled.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/modules.php';

/**
 * Synchronize module metadata from disk into the database.
 *
 * @param PDO|null $pdo Optional existing connection.
 * @return array{inserted:int,updated:int,omitted:int,disk:int,db:int}
 * @throws RuntimeException When the database is unavailable.
 */
function sync_modules_to_db(?PDO $pdo = null): array
{
    if ($pdo === null) {
        $pdo = db();
    }
    if (!$pdo) {
        throw new RuntimeException('Database connection failed.');
    }

    $P = prefix();
    $modules = get_modules();

    // Build a map of folder => module info from disk.
    $diskModules = [];
    foreach ($modules as $m) {
        if (!empty($m['folder'])) {
            $diskModules[$m['folder']] = $m;
        }
    }

    // Existing rows, including omitted ones (a returning module is revived).
    $existing = [];
    $stmt = $pdo->query("SELECT modulefolder, id, rguid FROM {$P}appmodules");
    while ($row = $stmt->fetch()) {
        $existing[$row['modulefolder']] = $row;
    }

    $actorId = 1; // System
    $actor = 'System';
    $actorAr = 'نظام';

    $inserted = 0;
    $updated = 0;
    $omitted = 0;

    foreach ($diskModules as $folder => $m) {
        $name = $m['name'] ?? $folder;
        $nameAr = $m['name_ar'] ?? $name;
        $description = $m['description'] ?? '';
        $descriptionAr = $m['description_ar'] ?? $description;
        $groupName = $m['group'] ?? 'Other';
        $groupNameAr = $m['group_ar'] ?? $groupName;
        $groupOrder = (int)($m['group_order'] ?? 999);
        $menuOrder = (int)($m['order'] ?? 999);
        // Modules are enabled by default; only an explicit false disables one.
        $enabled = (!array_key_exists('enabled', $m) || !empty($m['enabled'])) ? 1 : 0;

        if (isset($existing[$folder])) {
            $rowId = (int)$existing[$folder]['id'];
            $pdo->prepare("UPDATE {$P}appmodules SET
                modulename = ?, modulenamear = ?, modulefolder = ?, menu_order = ?, enabled = ?,
                description = ?, description_ar = ?, group_name = ?, group_name_ar = ?, group_order = ?,
                lastedit = NOW(), editedbyid = ?, editedby = ?, editedbyname = ?, editedbynamear = ?,
                omitted = 0
                WHERE id = ?")->execute([
                $name, $nameAr, $folder, $menuOrder, $enabled,
                $description, $descriptionAr, $groupName, $groupNameAr, $groupOrder,
                $actorId, $actor, $actor, $actorAr,
                $rowId
            ]);
            $updated++;
        } else {
            $rguid = bin2hex(random_bytes(16));
            $pdo->prepare("INSERT INTO {$P}appmodules (
                rguid, modulename, modulenamear, modulefolder, menu_order, enabled,
                description, description_ar, group_name, group_name_ar, group_order,
                dtadded, addedbyid, addedby, addedbyname, addedbynamear,
                lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,NOW(),?,?,?,?,0)")->execute([
                $rguid, $name, $nameAr, $folder, $menuOrder, $enabled,
                $description, $descriptionAr, $groupName, $groupNameAr, $groupOrder,
                $actorId, $actor, $actor, $actorAr,
                $actorId, $actor, $actor, $actorAr
            ]);
            $inserted++;
        }
    }

    // Mark modules that exist in the database but no longer exist on disk.
    foreach ($existing as $folder => $row) {
        if (!isset($diskModules[$folder])) {
            $pdo->prepare("UPDATE {$P}appmodules SET omitted = 1, lastedit = NOW() WHERE id = ?")
                ->execute([(int)$row['id']]);
            $omitted++;
        }
    }

    return [
        'inserted' => $inserted,
        'updated'  => $updated,
        'omitted'  => $omitted,
        'disk'     => count($diskModules),
        'db'       => count($existing),
    ];
}
