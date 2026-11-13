<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

/**
 * Notifications - inbox for every user plus a compose form for HR/admin.
 *
 * Employees receive notifications about their own requests. Superiors receive
 * requests submitted by their subordinates, and HR can send global or
 * group-wide announcements.
 */
require_login();
require_once __DIR__ . '/../../includes/helpers.php';

$u = current_user();
$uid = notifications_user_id();
$isAr = lang() === 'ar';
$isStaff = is_app_staff();
$action = $_GET['a'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---------- OPEN / MARK READ ----------
if ($action === 'open' && $id) {
    $n = notifications_find($id, $uid);
    if ($n) {
        notifications_mark_read($id, $uid);
        $link = trim((string)($n['link'] ?? ''));
        $link = str_replace(["\r", "\n"], '', $link);
        if ($link !== '' && (strpos($link, '') === 0 || strpos($link, '?') === 0)) {
            header('Location: ' . $link);
            exit;
        }
    }
    header('Location: ?module=notifications');
    exit;
}

// ---------- MARK ALL READ ----------
if ($action === 'read_all') {
    notifications_mark_all_read($uid);
    set_flash('success', $isAr ? 'تم تعليم كل الإشعارات كمقروءة.' : 'All notifications marked as read.');
    header('Location: ?module=notifications');
    exit;
}

// ---------- DELETE OWN NOTIFICATION ----------
if ($action === 'del' && $id) {
    if (notifications_delete($id, $uid)) {
        set_flash('success', $isAr ? 'تم حذف الإشعار.' : 'Notification deleted.');
    }
    header('Location: ?module=notifications');
    exit;
}

// ---------- COMPOSE (HR / ADMIN) ----------
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isStaff) {
        set_flash('error', $isAr ? 'ليس لديك صلاحية إرسال الإشعارات.' : 'You do not have permission to send notifications.');
        header('Location: ?module=notifications');
        exit;
    }
    $scope = $_POST['scope'] ?? 'global';
    if (!in_array($scope, ['global', 'hr', 'employees', 'user'], true)) {
        $scope = 'global';
    }
    $target = (int)($_POST['target_userid'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $titleAr = trim($_POST['titlear'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $messageAr = trim($_POST['messagear'] ?? '');
    $link = trim($_POST['link'] ?? '');

    $errors = [];
    if ($title === '' && $titleAr === '') {
        $errors[] = $isAr ? 'عنوان الإشعار مطلوب.' : 'Notification title is required.';
    }
    if ($message === '' && $messageAr === '') {
        $errors[] = $isAr ? 'نص الإشعار مطلوب.' : 'Notification message is required.';
    }
    if ($scope === 'user' && $target <= 0) {
        $errors[] = $isAr ? 'اختر مستخدماً.' : 'Select a user.';
    }

    if (!empty($errors)) {
        foreach ($errors as $err) { set_flash('error', $err); }
    } else {
        $payload = [
            'title'     => $title !== '' ? $title : $titleAr,
            'titlear'   => $titleAr !== '' ? $titleAr : $title,
            'message'   => $message !== '' ? $message : $messageAr,
            'messagear' => $messageAr !== '' ? $messageAr : $message,
            'link'      => $link !== '' ? $link : null,
            'module'    => 'notifications',
        ];
        $scopeValue = $scope === 'user' ? $target : $scope;
        $sendScope = $scope === 'user' ? 'user' : 'group';
        if ($scope === 'global') {
            $sendScope = 'global';
            $scopeValue = null;
        }
        $sent = notifications_send_scope($sendScope, $scopeValue, $payload);
        set_flash('success', ($isAr ? 'تم إرسال الإشعار إلى ' : 'Notification sent to ') . (int)$sent . ($isAr ? ' مستخدم.' : ' user(s).'));
        header('Location: ?module=notifications');
        exit;
    }
}

// ---------- LIST ----------
$search = trim($_GET['search'] ?? '');
$unreadOnly = !empty($_GET['unread']);
$where = "userid = ? AND omitted = 0";
$params = [$uid];
if ($unreadOnly) {
    $where .= " AND isread = 0";
}
if ($search !== '') {
    $norm = arabic_normalize($search);
    $where .= " AND (title LIKE ? OR titlear LIKE ? OR message LIKE ? OR messagear LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $norm . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $norm . '%';
}
$rows = fetchAll(
    "SELECT * FROM " . table('notifications') . "
     WHERE $where ORDER BY id DESC LIMIT 500",
    $params
);
$unreadCount = notifications_unread_count($uid);
$users = $isStaff
    ? fetchAll("SELECT id, username, email FROM " . table('users') . " WHERE omitted = 0 ORDER BY username")
    : [];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-bell me-2"></i><?= $isAr ? 'الإشعارات' : 'Notifications' ?></h2>
    <div>
        <?php if ($unreadCount > 0): ?>
        <a href="?module=notifications&a=read_all" class="btn btn-outline-secondary btn-sm me-1">
            <i class="bi bi-check2-all me-1"></i><?= $isAr ? 'تعليم الكل كمقروء' : 'Mark all read' ?>
        </a>
        <?php endif; ?>
        <?php if ($isStaff): ?>
        <a href="?module=notifications&a=compose" class="btn btn-primary btn-sm">
            <i class="bi bi-send me-1"></i><?= $isAr ? 'إرسال إشعار' : 'Send Notification' ?>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'compose' && $isStaff): ?>
<div class="card mb-3">
    <div class="card-header"><?= $isAr ? 'إرسال إشعار' : 'Send Notification' ?></div>
    <div class="card-body">
        <form method="post" action="?module=notifications&a=send">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?= $isAr ? 'الجمهور' : 'Audience' ?> <span class="text-danger">*</span></label>
                    <select name="scope" id="scope_select" class="form-select" required>
                        <option value="global"><?= $isAr ? 'الجميع' : 'Global (everyone)' ?></option>
                        <option value="hr"><?= $isAr ? 'موظفو الموارد البشرية' : 'HR Employees' ?></option>
                        <option value="employees"><?= $isAr ? 'الموظفون العاديون' : 'Normal Employees' ?></option>
                        <option value="user"><?= $isAr ? 'مستخدم محدد' : 'Specific User' ?></option>
                    </select>
                </div>
                <div class="col-md-4 mb-3" id="target_wrap" style="display:none;">
                    <label class="form-label"><?= $isAr ? 'المستخدم' : 'User' ?> <span class="text-danger">*</span></label>
                    <select name="target_userid" class="form-select">
                        <option value="">-- <?= $isAr ? 'اختر' : 'Select' ?> --</option>
                        <?php foreach ($users as $usr): ?>
                        <option value="<?= (int)$usr['id'] ?>"><?= htmlspecialchars($usr['username'] . ' (' . $usr['email'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?= $isAr ? 'رابط (اختياري)' : 'Link (optional)' ?></label>
                    <input type="text" name="link" class="form-control" placeholder="?module=...">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= $isAr ? 'العنوان (إنجليزي)' : 'Title (English)' ?></label>
                    <input type="text" name="title" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= $isAr ? 'العنوان (عربي)' : 'Title (Arabic)' ?></label>
                    <input type="text" name="titlear" class="form-control" dir="rtl">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= $isAr ? 'النص (إنجليزي)' : 'Message (English)' ?></label>
                    <textarea name="message" class="form-control" rows="3"></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= $isAr ? 'النص (عربي)' : 'Message (Arabic)' ?></label>
                    <textarea name="messagear" class="form-control" rows="3" dir="rtl"></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i><?= $isAr ? 'إرسال' : 'Send' ?></button>
            <a href="?module=notifications" class="btn btn-secondary"><?= $isAr ? 'إلغاء' : 'Cancel' ?></a>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var scope = document.getElementById('scope_select');
    var wrap = document.getElementById('target_wrap');
    if (!scope || !wrap) return;
    function toggle() { wrap.style.display = (scope.value === 'user') ? '' : 'none'; }
    // jQuery listener: Select2 fires jQuery change events (native addEventListener would miss them).
    if (window.jQuery) { jQuery(scope).on('change', toggle); } else { scope.addEventListener('change', toggle); }
    toggle();
});
</script>
<?php endif; ?>

<form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="module" value="notifications">
    <div class="col-md-5">
        <input type="search" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="<?= $isAr ? 'بحث...' : 'Search...' ?>">
    </div>
    <div class="col-md-3">
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="unread" value="1" id="unread_filter" <?= $unreadOnly ? 'checked' : '' ?>>
            <label class="form-check-label" for="unread_filter"><?= $isAr ? 'غير المقروءة فقط' : 'Unread only' ?></label>
        </div>
    </div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><?= $isAr ? 'بحث' : 'Search' ?></button></div>
    <div class="col-md-2"><a href="?module=notifications" class="btn btn-outline-secondary w-100"><?= $isAr ? 'إعادة تعيين' : 'Reset' ?></a></div>
</form>

<div class="table-responsive">
<table class="table table-striped table-hover align-middle table-sm">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th><?= $isAr ? 'العنوان' : 'Title' ?></th>
            <th><?= $isAr ? 'النص' : 'Message' ?></th>
            <th><?= $isAr ? 'النطاق' : 'Scope' ?></th>
            <th><?= $isAr ? 'التاريخ' : 'Date' ?></th>
            <th><?= $isAr ? 'الحالة' : 'Status' ?></th>
            <th><?= $isAr ? 'الإجراءات' : 'Actions' ?></th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="text-center text-muted"><?= $isAr ? 'لا توجد إشعارات' : 'No notifications' ?></td></tr>
    <?php else: ?>
        <?php foreach ($rows as $i => $n): ?>
        <?php
            $title = $isAr ? ($n['titlear'] ?: $n['title']) : $n['title'];
            $msg = $isAr ? ($n['messagear'] ?: $n['message']) : $n['message'];
            $scopeLabel = $n['scope'];
            if ($n['scope'] === 'group') {
                $scopeLabel = $isAr
                    ? ($n['scopevalue'] === 'hr' ? 'موظفو الموارد البشرية' : 'الموظفون')
                    : ($n['scopevalue'] === 'hr' ? 'HR Employees' : 'Employees');
            } elseif ($n['scope'] === 'global') {
                $scopeLabel = $isAr ? 'الجميع' : 'Global';
            } else {
                $scopeLabel = $isAr ? 'خاص' : 'Personal';
            }
        ?>
        <tr class="<?= empty($n['isread']) ? 'fw-semibold' : '' ?>">
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($title) ?></td>
            <td><?= htmlspecialchars($msg) ?></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($scopeLabel) ?></span></td>
            <td><?= htmlspecialchars($n['dtadded'] ?? '') ?></td>
            <td>
                <?php if (!empty($n['isread'])): ?>
                <span class="badge bg-success"><?= $isAr ? 'مقروء' : 'Read' ?></span>
                <?php else: ?>
                <span class="badge bg-warning text-dark"><?= $isAr ? 'غير مقروء' : 'Unread' ?></span>
                <?php endif; ?>
            </td>
            <td class="d-flex gap-1">
                <a href="?module=notifications&a=open&id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-info" title="<?= $isAr ? 'فتح' : 'Open' ?>"><i class="bi bi-box-arrow-up-right"></i></a>
                <a href="?module=notifications&a=del&id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('<?= $isAr ? 'حذف هذا الإشعار؟' : 'Delete this notification?' ?>')" title="<?= $isAr ? 'حذف' : 'Delete' ?>"><i class="bi bi-trash"></i></a>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>
