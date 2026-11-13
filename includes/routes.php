<?php
/**
 * OpenFrame - Routes
 * Automatically reads the modules folder and builds routes.
 */

require_once __DIR__ . '/modules.php';

function get_routes() {
    $routes = [];
    $modules = get_modules();
    foreach ($modules as $module) {
        $routes[] = [
            'name'        => $module['name'],
            'name_ar'     => $module['name_ar'] ?? $module['name'],
            'icon'        => $module['icon'] ?? '',
            'folder'      => $module['folder'],
            'description' => $module['description'] ?? '',
            'group'       => $module['group'] ?? '',
            'group_ar'    => $module['group_ar'] ?? ($module['group'] ?? ''),
            'order'       => $module['order'] ?? 999,
            'url'         => '?module=' . $module['folder'],
            'enabled'     => $module['enabled'] ?? true,
        ];
    }
    return $routes;
}

function current_module() {
    $folder = $_GET['module'] ?? 'home';
    if (!is_string($folder) || !preg_match('/^[a-z0-9_-]+$/i', $folder)) {
        return 'home';
    }
    return $folder;
}

function load_module($folder) {
    if (!is_string($folder) || !preg_match('/^[a-z0-9_-]+$/i', $folder)) {
        echo '<div class="alert alert-warning">Invalid module.</div>';
        return;
    }
    $indexFile = __DIR__ . '/../modules/' . $folder . '/index.php';
    $real = realpath($indexFile);
    $base = realpath(__DIR__ . '/../modules');
    if ($real && $base && str_starts_with($real, $base) && file_exists($real)) {
        include $real;
    } else {
        echo '<div class="alert alert-warning">Module file not found: ' . htmlspecialchars($folder) . '</div>';
    }
}
