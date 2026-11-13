<aside id="sidebar" class="sidebar bg-dark" style="overflow-y: auto; max-height: calc(100vh - 120px);">
        <div class="sidebar-header p-3 border-bottom border-secondary d-flex align-items-center justify-content-end">
            <button class="btn btn-outline-light btn-sm sidebar-toggle" type="button" onclick="toggleSidebar()"><i class="bi bi-layout-sidebar"></i></button>
        </div>
        <nav class="py-2">
                <?php foreach ($moduleGroups as $group): ?>
                    <?php
                    $groupRoutes = [];
                    foreach ($group['modules'] as $m) {
                        $f = $m['folder'];
                        if (!isset($routesByFolder[$f])) { continue; }
                        // Permission filter: signed-in users only see modules they
                        // can view. Self-service modules (dashboard, ESS, personal
                        // notifications inbox, own attendance permissions) stay
                        // visible for everyone.
                        $alwaysVisible = in_array($f, ['home', 'ess', 'notifications', 'attpermissions'], true);
                        if (!$alwaysVisible && !user_can($f, 'view')) { continue; }
                        $groupRoutes[] = $routesByFolder[$f];
                    }
                    if (empty($groupRoutes)) continue;
                    $currentFolder = current_module();
                    $groupHasActive = false;
                    foreach ($groupRoutes as $r) { if ($r['folder'] === $currentFolder) { $groupHasActive = true; break; } }
                    ?>
                    <?php if (!empty($group['ungrouped'])): ?>
                        <!-- Ungrouped modules: flat menu items at the top -->
                        <ul class="nav flex-column group-items mb-2">
                            <?php foreach ($groupRoutes as $r): ?>
                                <?php $active = ($currentFolder === $r['folder']) ? ' active' : ''; ?>
                                <li class="nav-item">
                                    <a class="nav-link<?= $active ?>" href="<?= htmlspecialchars($r['url']) ?>">
                                        <?php if (!empty($r['icon'])): ?><i class="<?= htmlspecialchars($r['icon']) ?> me-1">&nbsp;</i><?php endif; ?><?= htmlspecialchars(mod_display_name($r['folder'], $r)) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <?php $groupName = $group['group']; ?>
                        <?php $groupId = 'grp_' . preg_replace('/[^a-z]/i', '', $groupName); ?>
                        <div class="group-toggle<?= $groupHasActive ? '' : ' collapsed' ?>" data-bs-toggle="collapse" data-bs-target="#<?= $groupId ?>" aria-expanded="<?= $groupHasActive ? 'true' : 'false' ?>">
                            <span><?= htmlspecialchars(is_rtl() ? ($group['group_ar'] ?? $groupName) : $groupName) ?></span>
                            <i class="bi bi-chevron-down chevron"></i>
                        </div>
                        <div class="collapse<?= $groupHasActive ? ' show' : '' ?>" id="<?= $groupId ?>">
                            <ul class="nav flex-column group-items mb-1">
                                <?php foreach ($groupRoutes as $r): ?>
                                    <?php $active = ($currentFolder === $r['folder']) ? ' active' : ''; ?>
                                    <li class="nav-item">
                                        <a class="nav-link<?= $active ?>" href="<?= htmlspecialchars($r['url']) ?>">
                                            <?php if (!empty($r['icon'])): ?><i class="<?= htmlspecialchars($r['icon']) ?> me-1">&nbsp;</i><?php endif; ?><?= htmlspecialchars(mod_display_name($r['folder'], $r)) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
    </aside>