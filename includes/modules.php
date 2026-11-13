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

/**
 * Group routes by the 'group' identifier declared in each module's info.php.
 *
 * If a group does not exist it is created; if it already exists the module is
 * appended to it. Modules inside a group are ordered by their 'order' value,
 * then by display name. Known groups come first in $groupOrder; unknown groups
 * follow alphabetically. Modules without a group fall back to "Other".
 *
 * @param array $routesByFolder Route arrays keyed by module folder
 * @param array $groupOrder     Preferred group identifiers
 * @return array group identifier => list of module folders
 */
function build_module_groups(array $routesByFolder, array $groupOrder = []) {
    $groups = [];
    $ungrouped = [];
    foreach ($routesByFolder as $folder => $route) {
        $group = trim((string)($route['group'] ?? ''));
        if ($group === '') {
            $ungrouped[] = $folder;
            continue;
        }
        if (!isset($groups[$group])) {
            $groups[$group] = [];
        }
        $groups[$group][] = $folder;
    }

    foreach ($groups as $group => &$folders) {
        usort($folders, function ($a, $b) use ($routesByFolder) {
            $orderA = (int)($routesByFolder[$a]['order'] ?? 999);
            $orderB = (int)($routesByFolder[$b]['order'] ?? 999);
            if ($orderA === $orderB) {
                return strcmp($routesByFolder[$a]['name'] ?? $a, $routesByFolder[$b]['name'] ?? $b);
            }
            return $orderA <=> $orderB;
        });
    }
    unset($folders);

    $ordered = [];
    // Ungrouped modules first, as a special '' key.
    if (!empty($ungrouped)) {
        usort($ungrouped, function ($a, $b) use ($routesByFolder) {
            $orderA = (int)($routesByFolder[$a]['order'] ?? 999);
            $orderB = (int)($routesByFolder[$b]['order'] ?? 999);
            if ($orderA === $orderB) {
                return strcmp($routesByFolder[$a]['name'] ?? $a, $routesByFolder[$b]['name'] ?? $b);
            }
            return $orderA <=> $orderB;
        });
        $ordered[''] = $ungrouped;
    }
    foreach ($groupOrder as $group) {
        if (isset($groups[$group]) && !empty($groups[$group])) {
            $ordered[$group] = $groups[$group];
            unset($groups[$group]);
        }
    }
    ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($groups as $group => $folders) {
        $ordered[$group] = $folders;
    }

    return $ordered;
}

/**
 * Build the list of module groups from all module info.php files.
 * Each group entry contains: 'group' (identifier), 'group_ar' (Arabic name),
 * 'group_order' (sorting order), and 'modules' (list of module folders).
 *
 * @return array list of group arrays sorted by group_order
 */
function get_module_groups() {
    $modules = get_modules();
    $groups = [];
    $ungrouped = [];

    foreach ($modules as $m) {
        $group = trim((string)($m['group'] ?? ''));
        if ($group === '') {
            // Modules without a group are not added to any group; they are
            // collected as standalone menu items that render at the top.
            $ungrouped[] = $m;
            continue;
        }
        if (!isset($groups[$group])) {
            $groups[$group] = [
                'group'       => $group,
                'group_ar'    => $m['group_ar'] ?? $group,
                'group_order' => (int)($m['group_order'] ?? 999),
                'modules'     => [],
                'ungrouped'   => false,
            ];
        }
        // Prefer a non-empty Arabic name if the first module lacked one.
        if (empty($groups[$group]['group_ar']) && !empty($m['group_ar'])) {
            $groups[$group]['group_ar'] = $m['group_ar'];
        }
        $groups[$group]['modules'][] = $m;
    }

    // Sort modules within each group by order, then name
    foreach ($groups as $group => &$g) {
        usort($g['modules'], function ($a, $b) {
            $oa = (int)($a['order'] ?? 999);
            $ob = (int)($b['order'] ?? 999);
            if ($oa === $ob) {
                return strcmp($a['name'] ?? '', $b['name'] ?? '');
            }
            return $oa <=> $ob;
        });
    }
    unset($g);

    // Sort grouped entries by group_order, then name
    uasort($groups, function ($a, $b) {
        $oa = $a['group_order'];
        $ob = $b['group_order'];
        if ($oa === $ob) {
            return strcmp($a['group'], $b['group']);
        }
        return $oa <=> $ob;
    });

    // Ungrouped modules become a pseudo-group rendered at the very top,
    // as flat menu items (no collapsible group header).
    $result = [];
    if (!empty($ungrouped)) {
        usort($ungrouped, function ($a, $b) {
            $oa = (int)($a['order'] ?? 999);
            $ob = (int)($b['order'] ?? 999);
            if ($oa === $ob) {
                return strcmp($a['name'] ?? '', $b['name'] ?? '');
            }
            return $oa <=> $ob;
        });
        array_unshift($result, [
            'group'       => '',
            'group_ar'    => '',
            'group_order' => -1,
            'modules'     => $ungrouped,
            'ungrouped'   => true,
        ]);
    }
    foreach (array_values($groups) as $g) {
        $result[] = $g;
    }

    return $result;
}
