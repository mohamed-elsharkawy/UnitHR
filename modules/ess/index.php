<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

/**
 * Employee Self-Service (ESS) - Login required.
 * Employees access their own data using login credentials.
 * Admins can look up any employee by National ID.
 */
require_login();
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/leave_rules.php';
require_once __DIR__ . '/../../includes/attendance.php';
$isAr = lang() === 'ar';

$u = current_user();
$isAdmin = is_superadmin() || !empty($u['is_admin']);

// Determine which employee to show
$emp = null;
$nid = trim($_GET['nid'] ?? '');

// If employee (not admin), look up by their username (which is their NID)
if (!$isAdmin) {
    $nid = $u['username'] ?? '';
    $emp = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE nid=? AND omitted=0 LIMIT 1", [$nid]);
} elseif ($nid !== '') {
    // Admin looking up by NID
    $emp = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE nid=? AND omitted=0 LIMIT 1", [$nid]);
}

// ---------- Leave request (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['empid']) && empty($_POST['leave_action']) && empty($_POST['attendance_permission_action'])) {
    $empid = (int)$_POST['empid'];
    $ltid = (int)($_POST['leavetypeid'] ?? 0);
    $start = trim($_POST['startfrom'] ?? '');
    $end = trim($_POST['endon'] ?? '');
    if (!$isAdmin) {
        $own = fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE nid=? AND omitted=0 LIMIT 1", [$u['username'] ?? '']);
        if (!$own || (int)$own['id'] !== $empid) {
            set_flash('error', 'You can only submit leave requests for your own record.');
            header('Location: ?module=ess');
            exit;
        }
    }
    $emp = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [$empid]);
    $leavetypes = fetchAll("SELECT * FROM " . table('leavestypes') . " WHERE omitted=0 ORDER BY leavetypename ASC");
    $fyears = fetchAll("SELECT id, fstartdate, fenddate FROM " . table('fiscalyears') . " WHERE omitted=0 ORDER BY fstartdate DESC");
    $errors = [];
    if (!$emp) { $errors[] = 'Employee not found.'; }
    if ($ltid === 0) { $errors[] = 'Leave type is required.'; }
    if ($start === '' || $end === '') { $errors[] = 'Start and end dates are required.'; }
    if ($start !== '' && $end !== '' && strtotime($end) < strtotime($start)) {
        $errors[] = 'End date cannot be older than start date.';
    }
    if (empty($errors)) {
        // Apply the leave-type rules: fiscal year, quotas, weekends, holidays
        // and balance (including days already reserved by pending requests).
        $check = leave_evaluate_request($empid, $ltid, $start, $end, 0);
        if (!$check['ok']) {
            $errors = array_merge($errors, $check['errors']);
        } else {
            $lt = $check['leaveType'];
            $remaining = $check['remaining'];
            $days = $check['days'];
            $balanceBefore = $remaining;
            // Show the projected balance immediately, even while pending.
            $balanceAfter = max(0, $remaining - $days);
            $supId = (int)($emp['empsuperiorid'] ?? 0);
            $supName = $emp['empsuperiorname'] ?? '';
            $supNameAr = $emp['empsuperiornamear'] ?? '';
            $now = date('Y-m-d H:i:s');
            $insRes = q("INSERT INTO " . table('empleaves') . "
                (rguid, empid, empfullname, empfullnamear, leavetypeid, leavetypename, leavetypenamear,
                 startfrom, endon, balancebefore, balanceafter, isapproved, empsuperiorid,
                 empsuperiorname, empsuperiornamear, dtadded, addedbyid, addedby,
                 addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname,
                 editedbynamear)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [bin2hex(random_bytes(16)), $empid, $emp['empfullname'], $emp['empfullnamear'],
                 $ltid, $lt['leavetypename'], $lt['leavetypenamear'],
                 $start, $end, $balanceBefore, $balanceAfter, 0, $supId ?: null, $supName, $supNameAr,
                 $now, $empid, $emp['empfullname'], $emp['empfullname'], $emp['empfullnamear'],
                 $now, $empid, $emp['empfullname'], $emp['empfullname'], $emp['empfullnamear']]);
            if (!$insRes) {
                set_flash('error', 'DB insert failed: ' . ($_SESSION[UHR_SESSION_KEY]['db_error'] ?? 'unknown'));
            } else {
                $leaveId = (int)lastInsertId();
                notify_leave_submitted($leaveId, $emp, $lt, $start, $end, $days);
                set_flash('success', 'Leave request submitted and is pending approval.');
            }
            header('Location: ?module=ess&step=view&nid=' . urlencode($emp['nid']));
            exit;
        }
    }
    if (!empty($errors)) { foreach ($errors as $err) { set_flash('error', $err); } }
}

// ---------- Attendance Permission request (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['attendance_permission_action']) && $_POST['attendance_permission_action'] === 'submit') {
    $empid = (int)$_POST['empid'];
    $ltid = (int)($_POST['leavetypeid'] ?? 0);
    $start = trim($_POST['startfrom'] ?? '');
    $end = trim($_POST['endon'] ?? '');
    if (!$isAdmin) {
        $own = fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE nid=? AND omitted=0 LIMIT 1", [$u['username'] ?? '']);
        if (!$own || (int)$own['id'] !== $empid) {
            set_flash('error', 'You can only submit leave requests for your own record.');
            header('Location: ?module=ess');
            exit;
        }
    }
    $emp = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [$empid]);
    $leavetypes = fetchAll("SELECT * FROM " . table('leavestypes') . " WHERE omitted=0 ORDER BY leavetypename ASC");
    $fyears = fetchAll("SELECT id, fstartdate, fenddate FROM " . table('fiscalyears') . " WHERE omitted=0 ORDER BY fstartdate DESC");
    $errors = [];
    if (!$emp) { $errors[] = 'Employee not found.'; }
    if ($ltid === 0) { $errors[] = 'Leave type is required.'; }
    if ($start === '' || $end === '') { $errors[] = 'Start and end dates are required.'; }
    if ($start !== '' && $end !== '' && strtotime($end) < strtotime($start)) {
        $errors[] = 'End date cannot be older than start date.';
    }
    if (empty($errors)) {
        // Apply the leave-type rules: fiscal year, quotas, weekends, holidays
        // and balance (including days already reserved by pending requests).
        $check = leave_evaluate_request($empid, $ltid, $start, $end, 0);
        if (!$check['ok']) {
            $errors = array_merge($errors, $check['errors']);
        } else {
            $lt = $check['leaveType'];
            $remaining = $check['remaining'];
            $days = $check['days'];
            $balanceBefore = $remaining;
            // Show the projected balance immediately, even while pending.
            $balanceAfter = max(0, $remaining - $days);
            $supId = (int)($emp['empsuperiorid'] ?? 0);
            $supName = $emp['empsuperiorname'] ?? '';
            $supNameAr = $emp['empsuperiornamear'] ?? '';
            $now = date('Y-m-d H:i:s');
            $insRes = q("INSERT INTO " . table('empleaves') . "
                (rguid, empid, empfullname, empfullnamear, leavetypeid, leavetypename, leavetypenamear,
                 startfrom, endon, balancebefore, balanceafter, isapproved, empsuperiorid,
                 empsuperiorname, empsuperiornamear, dtadded, addedbyid, addedby,
                 addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname,
                 editedbynamear)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [bin2hex(random_bytes(16)), $empid, $emp['empfullname'], $emp['empfullnamear'],
                 $ltid, $lt['leavetypename'], $lt['leavetypenamear'],
                 $start, $end, $balanceBefore, $balanceAfter, 0, $supId ?: null, $supName, $supNameAr,
                 $now, $empid, $emp['empfullname'], $emp['empfullname'], $emp['empfullnamear'],
                 $now, $empid, $emp['empfullname'], $emp['empfullname'], $emp['empfullnamear']]);
            if (!$insRes) {
                set_flash('error', 'DB insert failed: ' . ($_SESSION[UHR_SESSION_KEY]['db_error'] ?? 'unknown'));
            } else {
                $leaveId = (int)lastInsertId();
                notify_leave_submitted($leaveId, $emp, $lt, $start, $end, $days);
                set_flash('success', 'Leave request submitted and is pending approval.');
            }
            header('Location: ?module=ess&step=view&nid=' . urlencode($emp['nid']));
            exit;
        }
    }
    if (!empty($errors)) { foreach ($errors as $err) { set_flash('error', $err); } }
}

// ---------- VIEW: employee dashboard ----------
if ($emp) {
    $empid = (int)$emp['id'];
    $leavetypes = fetchAll("SELECT * FROM " . table('leavestypes') . " WHERE omitted=0 ORDER BY leavetypename ASC");
    $fyears = fetchAll("SELECT id, fstartdate, fenddate FROM " . table('fiscalyears') . " WHERE omitted=0 ORDER BY fstartdate DESC");
    $currentFy = fetchRow("SELECT id FROM " . table('fiscalyears') . " WHERE omitted=0 AND ? BETWEEN fstartdate AND fenddate LIMIT 1", [date('Y-m-d')]);
    if (!$currentFy) {
        $currentFy = fetchRow("SELECT id FROM " . table('fiscalyears') . " WHERE omitted=0 ORDER BY fstartdate DESC LIMIT 1");
    }
    if (!$currentFy) {
        $currentFy = fetchRow("SELECT id FROM " . table('fiscalyears') . " WHERE omitted=0 ORDER BY fstartdate DESC LIMIT 1");
    }
    $currentFyId = $currentFy ? (int)$currentFy['id'] : 0;
    $balances = $currentFyId
        ? fetchAll("SELECT * FROM " . table('leavesbalances') . " WHERE empid=? AND fiscalyearid=? AND omitted=0 ORDER BY leavetypename ASC", [$empid, $currentFyId])
        : fetchAll("SELECT * FROM " . table('leavesbalances') . " WHERE empid=? AND omitted=0 ORDER BY fstartdate DESC", [$empid]);
    $leaves = fetchAll("SELECT * FROM " . table('empleaves') . " WHERE empid=? AND omitted=0 ORDER BY id DESC", [$empid]);
    
    // Check if this employee has subordinates (for leave approval)
    $subordinates = fetchAll("SELECT id, empfullname, empfullnamear FROM " . table('employeesmain') . " WHERE empsuperiorid=? AND omitted=0", [$empid]);
    $hasSubordinates = count($subordinates) > 0;
    
    // Get pending leave requests from subordinates
    $pendingLeaves = [];
    if ($hasSubordinates) {
        $subIds = array_column($subordinates, 'id');
        $placeholders = implode(',', array_fill(0, count($subIds), '?'));
        $pendingLeaves = fetchAll("SELECT * FROM " . table('empleaves') . " WHERE empid IN ($placeholders) AND isapproved = 0 AND rejectedat IS NULL AND omitted = 0 ORDER BY id DESC", $subIds);
    }
    // Used in the request form to show which days count for this employee.
    $empWorkdayNames = leave_workday_summary(leave_employee_workdays($empid));
    include __DIR__ . '/../../includes/header.php';
    ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($isAr ? $emp['empfullnamear'] : $emp['empfullname']) ?></h2>
    <div>
        <?php if ($isAdmin): ?>
        <a href="?module=employeesmain" class="btn btn-secondary me-2"><i class="bi bi-arrow-left me-1"></i><?= $isAr ? 'العودة' : 'Back' ?></a>
        <?php endif; ?>
        <a href="?action=logout" class="btn btn-danger"><i class="bi bi-box-arrow-right me-1"></i><?= $isAr ? 'تسجيل خروج' : 'Logout' ?></a>
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-body">
                <!-- <p><strong><?= $isAr ? 'الاسم' : 'Name' ?>:</strong> <?= htmlspecialchars($isAr ? $emp['empfullnamear'] : $emp['empfullname']) ?></p> -->
                <p><strong><?= $isAr ? 'الرقم القومي' : 'National ID' ?>:</strong> <?= htmlspecialchars($emp['nid']) ?></p>
                <p><strong><?= $isAr ? 'البريد الإلكتروني' : 'Email' ?>:</strong> <?= htmlspecialchars($emp['email'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'اسم المستخدم (تسجيل الدخول)' : 'Login Username' ?>:</strong> <?= htmlspecialchars($emp['nid']) ?></p>
                <!--
				<p><strong><?= $isAr ? 'النوع' : 'Gender' ?>:</strong> <?= htmlspecialchars($isAr ? ($emp['empgender'] === 'Male' ? 'ذكر' : 'أنثى') : ($emp['empgender'] ?? '')) ?></p>
				-->
                <p><strong><?= $isAr ? 'القسم' : 'Department' ?>:</strong> <?= htmlspecialchars($isAr ? $emp['depnamear'] : $emp['depname'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'الحالة الوظيفية' : 'Status' ?>:</strong> <?= htmlspecialchars($isAr ? $emp['empstatusnamear'] :$emp['empstatusname'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'الدرجة' : 'Degree' ?>:</strong> <?= htmlspecialchars($isAr ? $emp['currentdegreenamear'] : $emp['currentdegreename'] ?? '') ?></p>
                <!--
				<p><strong><?= $isAr ? 'تاريخ الميلاد' : 'Date of Birth' ?>:</strong> <?= htmlspecialchars($emp['dop'] ?? '') ?></p>
                <hr class="my-3">
				-->
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body">
				<p><strong><?= $isAr ? 'تغيير كلمة المرور' : 'Change Password' ?></strong></p>
				<a href="?action=change-password" class="btn btn-outline-secondary btn-sm ms-2"><?= $isAr ? 'تغيير كلمة المرور' : 'Change your Password' ?></a>
                <!--
				*** Remarked for a much easier approach ***
				<form method="post" action="?module=ess&action=changepassword">
                    <div class="mb-2">
                        <label class="form-label"><?= $isAr ? 'كلمة المرور الحالية' : 'Current Password' ?></label>
                        <input type="password" name="current_password" class="form-control" required autofocus>
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><?= $isAr ? 'كلمة المرور الجديدة' : 'New Password' ?></label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><?= $isAr ? 'تأكيد كلمة المرور الجديدة' : 'Confirm New Password' ?></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button class="btn btn-primary btn-sm"><?= $isAr ? 'تغيير كلمة المرور' : 'Change Password' ?></button>
                    <a href="?module=ess" class="btn btn-outline-secondary btn-sm ms-2"><?= $isAr ? 'إلغاء' : 'Cancel' ?></a>
                </form>
				-->
            </div>
        </div>
    </div>
    <div class="col-md-4" id="leave_requests">
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'طلب إجازة جديدة' : 'Request Leave' ?></div>
            <div class="card-body">
                <form method="post" action="?module=ess&step=request">
                    <input type="hidden" name="empid" value="<?= $empid ?>">
                    <div class="mb-2"><label class="form-label"><?= $isAr ? 'نوع الإجازة' : 'Leave Type' ?></label>
                        <select name="leavetypeid" class="form-select form-select-sm"><option value="">-- <?= $isAr ? 'اختر' : 'Select' ?> --</option>
                        <?php foreach ($leavetypes as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($isAr ? $t['leavetypenamear'] : $t['leavetypename']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="row"><div class="col-6 mb-2"><label class="form-label"><?= $isAr ? 'من' : 'From' ?></label>
                        <input type="date" name="startfrom" class="form-control form-control-sm"></div>
                        <div class="col-6 mb-2"><label class="form-label"><?= $isAr ? 'إلى' : 'To' ?></label>
                        <input type="date" name="endon" class="form-control form-control-sm"></div></div>
                    <?php if (!empty($empWorkdayNames)): ?>
                    <div class="form-text mb-2">
                        <?= $isAr ? 'أيام العمل: ' : 'Working days: ' ?><strong><?= htmlspecialchars(implode($isAr ? '، ' : ', ', $empWorkdayNames)) ?></strong>
                        <br><?= $isAr ? 'لا تُحتسب الأيام غير المذكورة في الإجازات التي لا تشمل عطلات نهاية الأسبوع.' : 'Days not listed are not counted for leave types that exclude weekends.' ?>
                    </div>
                    <?php endif; ?>
                    <button class="btn btn-primary btn-sm"><?= $isAr ? 'إرسال الطلب' : 'Submit Request' ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4" id="attendance_permissions">
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'طلب إذن حضور' : 'Attendance Permission' ?></div>
            <div class="card-body">
                <form method="post" action="?module=ess">
                    <input type="hidden" name="empid" value="<?= $empid ?>">
                    <input type="hidden" name="attendance_permission_action" value="submit">
                    <div class="mb-2"><label class="form-label"><?= $isAr ? 'نوع الإذن' : 'Permission Type' ?></label>
                        <select name="permission_type" class="form-select form-select-sm" required>
                            <option value="">-- <?= $isAr ? 'اختر' : 'Select' ?> --</option>
                            <option value="EARLY_DEPARTURE"><?= $isAr ? 'إذن انصراف مبكر' : 'Early Departure' ?></option>
                            <option value="LATE_ARRIVAL"><?= $isAr ? 'إذن حضور متأخر' : 'Late Arrival' ?></option>
                            <option value="PERSONAL"><?= $isAr ? 'إذن شخصي' : 'Personal Permission' ?></option>
                            <option value="OFFICIAL_BUSINESS"><?= $isAr ? 'إذن مصلحي' : 'Official Business' ?></option>
                            <option value="WORK_ASSIGNMENT"><?= $isAr ? 'مأمورية' : 'Work Assignment' ?></option>
                        </select></div>
                    <div class="row"><div class="col-6 mb-2"><label class="form-label"><?= $isAr ? 'من' : 'From' ?></label>
                        <input type="date" name="start_date" class="form-control form-control-sm"></div>
                        <div class="col-6 mb-2"><label class="form-label"><?= $isAr ? 'إلى' : 'To' ?></label>
                        <input type="date" name="end_date" class="form-control form-control-sm"></div></div>
                    <div class="row"><div class="col-6 mb-2"><label class="form-label"><?= $isAr ? 'وقت البدء' : 'Start Time' ?></label>
                        <input type="time" name="start_time" class="form-control form-control-sm"></div>
                        <div class="col-6 mb-2"><label class="form-label"><?= $isAr ? 'وقت الانتهاء' : 'End Time' ?></label>
                        <input type="time" name="end_time" class="form-control form-control-sm"></div></div>
                    <div class="mb-2"><label class="form-label"><?= $isAr ? 'السبب' : 'Reason' ?></label>
                        <textarea name="permission_reason" class="form-control" rows="2"></textarea></div>
                    <button class="btn btn-primary btn-sm"><?= $isAr ? 'إرسال الطلب' : 'Submit Request' ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php if ($hasSubordinates && !empty($pendingLeaves)): ?>
<div class="card mt-3">
    <div class="card-header"><?= $isAr ? 'طلبات إجازات بانتظار الموافقة' : 'Pending Leave Approvals' ?></div>
    <div class="card-body">
        <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr>
                <th><?= $isAr ? 'الموظف' : 'Employee' ?></th>
                <th><?= $isAr ? 'نوع الإجازة' : 'Leave Type' ?></th>
                <th><?= $isAr ? 'من' : 'From' ?></th>
                <th><?= $isAr ? 'إلى' : 'To' ?></th>
                <th><?= $isAr ? 'الرصيد قبل' : 'Balance Before' ?></th>
                <th><?= $isAr ? 'الإجراءات' : 'Actions' ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($pendingLeaves as $pl): ?>
            <?php
                $subName = '';
                foreach ($subordinates as $sub) {
                    if ($sub['id'] == $pl['empid']) {
                        $subName = $isAr ? $sub['empfullnamear'] : $sub['empfullname'];
                        break;
                    }
                }
            ?>
            <tr>
                <td><?= htmlspecialchars($subName) ?></td>
                <td><?= htmlspecialchars($isAr ? $pl['leavetypenamear'] : $pl['leavetypename']) ?></td>
                <td><?= htmlspecialchars($pl['startfrom']) ?></td>
                <td><?= htmlspecialchars($pl['endon']) ?></td>
                <td><?= (int)($pl['balancebefore'] ?? 0) ?></td>
                <td>
                    <form method="post" action="?module=ess&step=view&nid=<?= urlencode($emp['nid']) ?>" style="display:inline;">
                        <input type="hidden" name="leave_id" value="<?= $pl['id'] ?>">
                        <input type="hidden" name="leave_action" value="approve">
                        <input type="hidden" name="empid" value="<?= $pl['empid'] ?>">
                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('<?= $isAr ? 'الموافقة على هذه الإجازة؟' : 'Approve this leave?' ?>')">
                            <i class="bi bi-check-lg"></i> <?= $isAr ? 'موافقة' : 'Approve' ?>
                        </button>
                    </form>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $pl['id'] ?>">
                        <i class="bi bi-x-lg"></i> <?= $isAr ? 'رفض' : 'Reject' ?>
                    </button>
                    <!-- Reject Modal -->
                    <div class="modal fade" id="rejectModal<?= $pl['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><?= $isAr ? 'رفض الإجازة' : 'Reject Leave' ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="post" action="?module=ess&step=view&nid=<?= urlencode($emp['nid']) ?>">
                                    <div class="modal-body">
                                        <input type="hidden" name="leave_id" value="<?= $pl['id'] ?>">
                                        <input type="hidden" name="leave_action" value="reject">
                                        <input type="hidden" name="empid" value="<?= $pl['empid'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label"><?= $isAr ? 'سبب الرفض' : 'Rejection Reason' ?></label>
                                            <textarea name="reject_reason" class="form-control" rows="3"></textarea>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $isAr ? 'إلغاء' : 'Cancel' ?></button>
                                            <button type="submit" class="btn btn-danger"><?= $isAr ? 'رفض' : 'Reject' ?></button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card mt-3">
    <div class="card-header"><?= $isAr ? 'تاريخ الإجازات' : 'Leave History' ?></div>
    <div class="card-body">
        <?php if (count($leaves) === 0): ?>
        <p class="text-muted"><?= $isAr ? 'لا توجد إجازات' : 'No leave records' ?></p>
        <?php else: ?>
        <table class="table table-sm table-fixed-height">
            <thead><tr><th><?= $isAr ? 'النوع' : 'Type' ?></th><th><?= $isAr ? 'من' : 'From' ?></th><th><?= $isAr ? 'إلى' : 'To' ?></th><th><?= $isAr ? 'الحالة' : 'Status' ?></th></tr></thead>
            <tbody>
            <?php foreach ($leaves as $l): ?>
            <tr>
                <td><?= htmlspecialchars($l['startfrom']) ?></td><td><?= htmlspecialchars($l['endon']) ?></td>
                <td><?php
                    if (!empty($l['isapproved'])) {
                        echo $isAr ? 'موافق' : 'Approved';
                    } elseif (!empty($l['rejectedat'])) {
                        echo $isAr ? 'مرفوض' : 'Rejected';
                    } else {
                        echo $isAr ? 'قيد الانتظار' : 'Pending';
                    }
                ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php
    if ($emp) {
        $attFrom = date('Y-m-d', strtotime('-30 days'));
        $attTo   = date('Y-m-d');
        $attRows = fetchAll("SELECT * FROM " . table('daily_attendance') . " WHERE employee_id=? AND attendance_date BETWEEN ? AND ? AND omitted=0 ORDER BY attendance_date DESC", [$emp['id'], $attFrom, $attTo]);
?>
<div class="card mt-3" id="attendance_records">
  <div class="card-header"><?= $isAr ? 'الحضور والانصراف (آخر 30 يوم)' : 'Attendance (last 30 days)' ?></div>
  <div class="card-body">
    <?php if (empty($attRows)): ?>
      <p class="text-muted"><?= $isAr ? 'لا توجد سجلات' : 'No records' ?></p>
    <?php else: ?>
    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
    <table class="table table-sm table-fixed-height">
      <thead><tr>
        <th><?= $isAr?'التاريخ':'Date' ?></th>
        <th><?= $isAr?'الحالة':'Status' ?></th>
        <th><?= $isAr?'الدخول':'In' ?></th>
        <th><?= $isAr?'الخروج':'Out' ?></th>
        <th><?= $isAr?'تأخر':'Late' ?></th>
        <th><?= $isAr?'إضافي':'OT' ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($attRows as $ar): ?>
      <tr>
        <td><?= htmlspecialchars($ar['attendance_date']) ?></td>
        <td><?= htmlspecialchars(attendance_status_label($ar['status_code'])) ?></td>
        <td><?= htmlspecialchars($ar['first_punch'] ? date('H:i', strtotime($ar['first_punch'])) : '') ?></td>
        <td><?= htmlspecialchars($ar['last_punch'] ? date('H:i', strtotime($ar['last_punch'])) : '') ?></td>
        <td><?= (int)$ar['late_minutes'] ?></td>
        <td><?= (int)$ar['total_overtime_minutes'] ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php
    }
?>
<?php
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

// ---------- VIEW: no employee resolved ----------
// Arrives here when an admin opens the module without ?nid=, or when an
// employee's record cannot be found (omitted/deleted). Always render a page
// instead of an empty response.
if ($isAdmin) {
    $allemployees = fetchAll(
        "SELECT id, nid, empfullname, empfullnamear FROM " . table('employeesmain') . "
         WHERE omitted=0 ORDER BY empfullname ASC"
    );
    include __DIR__ . '/../../includes/header.php';
    ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-person-circle me-2"></i><?= $isAr ? 'خدمة الموظف الذاتية' : 'Employee Self-Service' ?></h2>
    <div>
        <a href="?module=employeesmain" class="btn btn-secondary me-2"><i class="bi bi-arrow-left me-1"></i><?= $isAr ? 'العودة' : 'Back' ?></a>
        <a href="?action=logout" class="btn btn-danger"><i class="bi bi-box-arrow-right me-1"></i><?= $isAr ? 'تسجيل خروج' : 'Logout' ?></a>
    </div>
</div>
<div class="card mb-3">
    <div class="card-header"><?= $isAr ? 'بحث عن موظف' : 'Employee Lookup' ?></div>
    <div class="card-body">
        <p class="text-muted mb-3"><?= $isAr ? 'اختر موظفاً حسب الرقم القومي لعرض صفحة الخدمة الذاتية الخاصة به.' : 'Select an employee by National ID to open their self-service page.' ?></p>
        <form method="get" action="" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="ess">
            <input type="hidden" name="step" value="view">
            <div class="col-md-6 mb-2">
                <label class="form-label"><?= $isAr ? 'الرقم القومي' : 'National ID' ?></label>
                <select name="nid" class="form-select" required>
                    <option value="">-- <?= $isAr ? 'اختر' : 'Select' ?> --</option>
                    <?php foreach ($allemployees as $o): ?>
                    <option value="<?= htmlspecialchars($o['nid']) ?>"><?= htmlspecialchars(($isAr ? $o['empfullnamear'] : $o['empfullname']) . ' - ' . $o['nid']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto mb-2">
                <button class="btn btn-primary btn-sm"><?= $isAr ? 'عرض' : 'View' ?></button>
            </div>
        </form>
    </div>
</div>
    <?php
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

include __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-person-circle me-2"></i><?= $isAr ? 'خدمة الموظف الذاتية' : 'Employee Self-Service' ?></h2>
    <a href="?action=logout" class="btn btn-danger"><i class="bi bi-box-arrow-right me-1"></i><?= $isAr ? 'تسجيل خروج' : 'Logout' ?></a>
</div>
<div class="alert alert-danger mb-0">
    <?= $isAr ? 'لم يتم العثور على سجلك الوظيفي. يرجى الاتصال بإدارة الموارد البشرية.' : 'Your employee record was not found. Please contact HR.' ?>
</div>
<?php
include __DIR__ . '/../../includes/footer.php';
exit;