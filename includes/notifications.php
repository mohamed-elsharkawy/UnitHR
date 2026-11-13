<?php
/**
 * UnitHR - Notification helpers.
 *
 * One row per recipient keeps read/unread state simple and lets a global
 * announcement be marked read by each user independently.
 *
 * Scopes:
 *   user   - one specific user account
 *   group  - hr (admins / HR staff) or employees (everyone else)
 *   global - every active user account
 */

/**
 * Does the notifications table exist? (Useful before install/migration.)
 */
function notifications_table_exists() {
    static $exists = null;
    if ($exists === null) {
        $row = fetchRow(
            "SELECT 1 FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1",
            [table('notifications')]
        );
        $exists = (bool)$row;
    }
    return $exists;
}

/**
 * Current user ID.
 */
function notifications_user_id() {
    return (int)($_SESSION[UHR_SESSION_KEY]['user_id'] ?? 0);
}

/**
 * Unread notification count for a user (defaults to the current user).
 */
function notifications_unread_count($userid = null) {
    $uid = $userid === null ? notifications_user_id() : (int)$userid;
    if ($uid <= 0 || !notifications_table_exists()) {
        return 0;
    }
    $row = fetchRow(
        "SELECT COUNT(*) AS c FROM " . table('notifications') . "
         WHERE userid = ? AND isread = 0 AND omitted = 0",
        [$uid]
    );
    return (int)($row['c'] ?? 0);
}

/**
 * Latest notifications for a user (defaults to the current user).
 */
function notifications_recent($userid = null, $limit = 6) {
    $uid = $userid === null ? notifications_user_id() : (int)$userid;
    $limit = max(1, min(50, (int)$limit));
    if ($uid <= 0 || !notifications_table_exists()) {
        return [];
    }
    return fetchAll(
        "SELECT * FROM " . table('notifications') . "
         WHERE userid = ? AND omitted = 0
         ORDER BY id DESC LIMIT " . $limit,
        [$uid]
    );
}

function notifications_find($id, $userid = null) {
    $uid = $userid === null ? notifications_user_id() : (int)$userid;
    if ($uid <= 0 || !notifications_table_exists()) {
        return null;
    }
    return fetchRow(
        "SELECT * FROM " . table('notifications') . "
         WHERE id = ? AND userid = ? AND omitted = 0 LIMIT 1",
        [(int)$id, $uid]
    );
}

/**
 * Mark a notification as read.
 */
function notifications_mark_read($id, $userid = null) {
    $uid = $userid === null ? notifications_user_id() : (int)$userid;
    if ($uid <= 0 || !notifications_table_exists()) {
        return false;
    }
    return q(
        "UPDATE " . table('notifications') . "
         SET isread = 1, readat = COALESCE(readat, NOW())
         WHERE id = ? AND userid = ? AND omitted = 0",
        [(int)$id, $uid]
    );
}

/**
 * Mark all notifications for a user as read.
 */
function notifications_mark_all_read($userid = null) {
    $uid = $userid === null ? notifications_user_id() : (int)$userid;
    if ($uid <= 0 || !notifications_table_exists()) {
        return false;
    }
    return q(
        "UPDATE " . table('notifications') . "
         SET isread = 1, readat = COALESCE(readat, NOW())
         WHERE userid = ? AND isread = 0 AND omitted = 0",
        [$uid]
    );
}

/**
 * Delete a notification.
 */
function notifications_delete($id, $userid = null) {
    $uid = $userid === null ? notifications_user_id() : (int)$userid;
    if ($uid <= 0 || !notifications_table_exists()) {
        return false;
    }
    return q(
        "UPDATE " . table('notifications') . "
         SET omitted = 1, lastedit = NOW()
         WHERE id = ? AND userid = ? AND omitted = 0",
        [(int)$id, $uid]
    );
}

/**
 * Insert one notification row for one recipient.
 */
function notifications_insert_for_user($userid, array $payload) {
    $userid = (int)$userid;
    if ($uid <= 0 || !notifications_table_exists()) {
        return false;
    }
    $u = current_user();
    $byId = $u['id'] ?? 0;
    $by = $u['username'] ?? 'system';
    $byName = $u['username_ar'] ?? $by;

    return q(
        "INSERT INTO " . table('notifications') . "
         (rguid, userid, empid, scope, scopevalue, title, titlear, message, messagear,
          link, module, refid, isread, dtadded, addedbyid, addedby, addedbyname,
           addedbynamear, omitted)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,?,0)",
        [
            bin2hex(random_bytes(16)),
            $userid,
            !empty($data['empid']) ? (int)$data['empid'] : null,
            $data['scope'] ?? 'user',
            $data['scopevalue'] ?? null,
            (string)($data['title'] ?? 'Notification'),
            (string)($data['titlear'] ?? ($data['title'] ?? 'إشعار')),
            (string)($data['message'] ?? ''),
            (string)($data['messagear'] ?? ($data['message'] ?? '')),
            $data['link'] ?? null,
            $data['module'] ?? null,
            !empty($data['refid']) ? (int)$data['refid'] : null,
            $byId,
            $by,
            $byName,
            $byName,
            0,
        ]
    );
}

/**
 * Send one notification to multiple users.
 */
function notifications_send_to_users(array $userids, array $payload) {
    $sent = 0;
    $seen = [];
    foreach ($userids as $uid) {
        $uid = (int)$uid;
        if ($uid <= 0 || isset($seen[$uid])) {
            continue;
        }
        $seen[$uid] = true;
        if (notifications_insert_for_user($uid, $payload)) {
            $sent++;
        }
    }
    return $sent;
}

/**
 * Send one notification to all employees.
 */
function notifications_notify_employees(array $payload) {
    $emps = fetchAll("SELECT id FROM " . table('employeesmain') . " WHERE omitted = 0");
    $ids = array_map('intval', array_column($emps, 'id'));
    return notifications_send_to_users($ids, $payload);
}

/**
 * Notify the direct superior of an employee. If the superior has no login
 * account (or none is configured), HR users receive the notification instead.
 */
function notifications_notify_superior($empid, array $payload) {
    $emp = fetchRow(
        "SELECT id, empfullname, empfullnamear, nid, empsuperiorid
         FROM " . table('employeesmain') . " WHERE id = ? AND omitted = 0 LIMIT 1",
        [(int)$empid]
    );
    if (!$emp) {
        return 0;
    }
    $payload['empid'] = (int)$emp['id'];
    $superiorUser = !empty($emp['empsuperiorid'])
        ? notifications_user_id_for_employee($emp['empsuperiorid'])
        : 0;

    if ($superiorUser > 0) {
        $payload['scope'] = 'user';
        $payload['scopevalue'] = null;
        return notifications_send_to_users([$superiorUser], $payload);
    }

    // No superior login: fall back to the HR group so the request is not lost.
    return notifications_send_scope('group', 'hr', $payload);
}

/**
 * Notify the employee about their leave/permission request decision.
 */
function notifications_notify_employee($empid, array $payload) {
    $uid = notifications_user_id_for_employee($empid);
    if ($uid <= 0) {
        return 0;
    }
    $payload['empid'] = (int)$empid;
    $payload['scope'] = 'user';
    $payload['scopevalue'] = null;
    return notifications_send_to_users([$uid], $payload);
}

/**
 * Standard notification for a submitted leave request.
 */
function notify_leave_submitted($leaveId, array $employee, array $leaveType, $start, $end, $days) {
    $isAr = lang() === 'ar';
    $name = $isAr ? ($employee['empfullnamear'] ?? '') : ($employee['empfullname'] ?? '');
    $type = $isAr ? ($leaveType['leavetypenamear'] ?? '') : ($leaveType['leavetypename'] ?? '');
    $nid = $employee['nid'] ?? '';

    $payload = [
        'title'   => 'New leave request',
        'titlear' => 'طلب إجازة جديد',
        'message' => $name . ' requested ' . $type . ' from ' . $start . ' to ' . $end . ' (' . (int)$days . ' day(s)).',
        'messagear' => $name . ' طلب ' . $type . ' من ' . $start . ' إلى ' . $end . ' (' . (int)$days . ' يوم).',
        'link'    => '?module=ess#leave_requests',
        'module'  => 'empleaves',
        'refid'   => (int)$leaveId,
    ];

    return notifications_notify_superior((int)$employee['id'] ?? 0, $payload);
}

/**
 * Standard notification for an approved / rejected leave request.
 */
function notify_leave_decision(array $leave, $decision, $actorName = '') {
    $isAr = lang() === 'ar';
    $empid = (int)($leave['empid'] ?? 0);
    if ($empid <= 0) {
        return 0;
    }
    $type = $isAr ? ($leave['leavetypenamear'] ?? '') : ($leave['leavetypename'] ?? '');
    $start = $leave['startfrom'] ?? '';
    $end = $leave['endon'] ?? '';
    $approved = ($decision === 'approved');

    $nid = (string)($leave['nid'] ?? '');
    if ($nid === '') {
        $emp = fetchRow(
            "SELECT nid FROM " . table('employeesmain') . " WHERE id = ? AND omitted = 0 LIMIT 1",
            [$empid]
        );
        $nid = (string)($emp['nid'] ?? '');
    }

    $payload = [
        'title'   => $approved ? 'Leave request approved' : 'Leave request rejected',
        'titlear' => $approved ? 'تمت الموافقة على طلب الإجازة' : 'تم رفض طلب الإجازة',
        'message' => 'Your ' . $type . ' request (' . $start . ' to ' . $end . ') was '
            . ($approved ? 'approved' : 'rejected') . ($actorName ? ' by ' . $actorName : '') . '.',
        'messagear' => $name . ' طلب ' . $type . ' (' . $start . ' إلى ' . $end . ') تم '
            . ($approved ? 'الموافقة عليه' : 'تم رفضه') . ($actorName ? ' بواسطة ' . $actorName : '') . '.',
        'link'    => '?module=ess#leave_requests',
        'module'  => 'empleaves',
        'refid'   => (int)($leave['id'] ?? 0),
    ];

    return notifications_notify_employee($empid, $payload);
}

/**
 * Generic hook for a permission / approval request module.
 * A future "leave permission" module can call this with its own data.
 */
function notify_permission_request($empid, $requestName, $start, $end, $refid = 0) {
    $emp = fetchRow(
        "SELECT id, empfullname, empfullnamear, nid FROM " . table('employeesmain') . "
         WHERE id = ? AND omitted = 0 LIMIT 1",
        [(int)$empid]
    );
    if (!$emp) {
        return 0;
    }
    $isAr = lang() === 'ar';
    $name = $isAr ? $emp['empfullnamear'] : $emp['empfullname'];
    $payload = [
        'title'   => 'New permission request',
        'titlear' => 'طلب إذن جديد',
        'message' => $name . ' submitted ' . $requestName . ' from ' . $start . ' to ' . $end . '.',
        'messagear' => $name . ' طلب ' . $requestName . ' من ' . $start . ' إلى ' . $end . '.',
        'link'    => '?module=ess#attendance_permissions',
        'module'  => 'attpermissions',
        'refid'   => (int)$refid,
    ];

    return notifications_notify_superior((int)$emp['id'], $payload);
}

/**
 * Render the navbar bell (expects to sit inside a <ul class="navbar-nav">).
 */
function notifications_render_bell() {
    $uid = notifications_user_id();
    if ($uid <= 0) {
        return;
    }
    $isAr = lang() === 'ar';
    $unread = notifications_unread_count($uid);
    $recent = notifications_recent($uid, 6);
    ?>
    <li class="nav-item dropdown me-2">
        <a class="nav-link position-relative text-white-50" href="#" role="button" data-bs-toggle="dropdown" aria-label="<?= $isAr ? 'الإشعارات' : 'Notifications' ?>">
            <i class="bi bi-bell"></i>
            <?php if ($unread > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?= $unread > 99 ? '99+' : (int)$unread ?>
            </span>
            <?php endif; ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 330px; max-height: 420px; overflow-y: auto;">
            <li class="nav-item">
                <div class="p-2">
                    <strong><?= $isAr ? 'الإشعارات' : 'Notifications' ?></strong>
                    <?php if ($unread > 0): ?>
                    <span class="badge bg-danger"><?= $unread > 99 ? '99+' : (int)$unread ?></span>
                    <?php endif; ?>
                </div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <?php if (empty($recent)): ?>
            <li><span class="dropdown-item text-muted"><?= $isAr ? 'لا توجد إشعارات' : 'No notifications' ?></span></li>
            <?php else: ?>
                <?php foreach ($recent as $n): ?>
                <li>
                    <a class="dropdown-item<?= empty($n['isread']) ? ' fw-bold' : '' ?>" href="?module=notifications&a=open&id=<?= (int)$n['id'] ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <span class="small"><?= htmlspecialchars($isAr ? ($n['titlear'] ?? $n['title']) : $n['title']) ?></span>
                            <span class="badge bg-dark rounded-pill text-light small"><?= htmlspecialchars($n['checkout_source'] ?? $n['status'] ?? '') ?></span>
                        </div>
                        <small class="d-block text-truncate" style="max-width: 180px;"><?= htmlspecialchars($isAr ? ($n['messagear'] ?? $n['message']) : $n['message']) ?></small>
                        <small class="text-muted d-block"><?= htmlspecialchars($n['dtadded'] ?? '') ?></small>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-center" href="?module=notifications"><?= $isAr ? 'عرض كل الإشعارات' : 'View all notifications' ?></a></li>
        </ul>
    </li>
<?php
}
