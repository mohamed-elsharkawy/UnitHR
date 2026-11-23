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
require_once __DIR__ . '/includes/module_sync.php';

try {
    $stats = sync_modules_to_db();
} catch (Throwable $e) {
    $stderr = defined('STDERR') ? STDERR : fopen('php://stderr', 'w');
    fwrite($stderr, "AppModules sync failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "AppModules sync complete.\n";
echo "  Inserted: {$stats['inserted']}\n";
echo "  Updated:  {$stats['updated']}\n";
echo "  Omitted:  {$stats['omitted']}\n";
echo "  Total on disk: {$stats['disk']}\n";
echo "  Total in DB:  {$stats['db']}\n";
