<?php
/**
 * OpenFrame - Modules Reader
 * Scans the modules/ folder and reads each module's info.php
 */

function get_modules() {
    $modules = [];
    $modulesDir = __DIR__ . '/../modules';

    if (!is_dir($modulesDir)) {
        return $modules;
    }

    foreach (scandir($modulesDir) as $dir) {
        if ($dir === '.' || $dir === '..') {
            continue;
        }
        $infoFile = $modulesDir . '/' . $dir . '/info.php';
        if (is_dir($modulesDir . '/' . $dir) && file_exists($infoFile)) {
            $moduleInfo = [];
            include $infoFile;
            if (!empty($moduleInfo) && isset($moduleInfo['name'])) {
                $moduleInfo['folder'] = $dir;
                $modules[] = $moduleInfo;
            }
        }
    }

    usort($modules, function ($a, $b) {
        return strcmp($a['name'] ?? '', $b['name'] ?? '');
    });

    return $modules;
}

function get_module($folder) {
    $infoFile = __DIR__ . '/../modules/' . $folder . '/info.php';
    if (file_exists($infoFile)) {
        $moduleInfo = [];
        include $infoFile;
        $moduleInfo['folder'] = $folder;
        return $moduleInfo;
    }
    return null;
}
