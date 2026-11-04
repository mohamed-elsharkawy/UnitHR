<?php
/**
 * Employee Self-Service (ESS) - Login required.
 * Employees access their own data using login credentials.
 * Admins can look up any employee by National ID.
 */
require_login();
require_once __DIR__ . '/../../includes/helpers.php';
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['empid'])) {
    $empid = (int)$_POST['empid'];
    $ltid = (int)($_POST['leavetypeid'] ?? 0);
    $start = trim($_POST['startfrom'] ?? '');
    $end = trim($_POST['endon'] ?? '');
    if (!$isAdmin) {
        $own = fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE nid=? AND omitted=0 LIMIT 1", [$u['username'] ?? '']);
        if (!$own || (int)$own['id'] !== $empid) {
            set_flash('error', 'You can only submit leave requests for your own record.');
            header('Location: index.php?module=ess');
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
        $fy = fetchRow("SELECT id FROM " . table('fiscalyears') . " WHERE omitted=0 AND ? BETWEEN fstartdate AND fenddate LIMIT 1", [$start]);
        $fy2 = fetchRow("SELECT id FROM " . table('fiscalyears') . " WHERE omitted=0 AND ? BETWEEN fstartdate AND fenddate LIMIT 1", [$end]);
        if (!$fy || !$fy2 || $fy['id'] !== $fy2['id']) {
            $errors[] = 'Leave period cannot span between two fiscal years.';
        } else {
            $lt = fetchRow("SELECT incwe, leavetypename, leavetypenamear FROM " . table('leavestypes') . " WHERE id=? LIMIT 1", [$ltid]);
            $days = 0;
            $s = strtotime($start); $e = strtotime($end);
            for ($d = $s; $d <= $e; $d = strtotime('+1 day', $d)) {
                $w = (int)date('w', $d);
                if (!$lt['incwe'] && ($w === 0 || $w === 6)) { continue; }
                $days++;
            }
            $bal = fetchRow("SELECT remainingbalance FROM " . table('leavesbalances') . " WHERE empid=? AND fiscalyearid=? AND leavetypeid=? AND omitted=0 LIMIT 1", [$empid, $fy['id'], $ltid]);
            $remaining = (int)($bal['remainingbalance'] ?? 0);
            if ($days > $remaining) {
                $errors[] = 'Not enough leave balance.';
            } else {
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
                     $start, $end, $remaining, $remaining, 0, 0, '', '',
                     $now, $empid, $emp['empfullname'], $emp['empfullname'], $emp['empfullnamear'],
                     $now, $empid, $emp['empfullname'], $emp['empfullname'], $emp['empfullnamear']]);
                if (!$insRes) { set_flash('error', 'DB insert failed: ' . ($_SESSION['db_error'] ?? 'unknown')); }
                else { set_flash('success', 'Leave request submitted and is pending approval.'); }
                header('Location: index.php?module=ess&step=view&nid=' . urlencode($emp['nid']));
                exit;
            }
        }
    }
    if (!empty($errors)) { foreach ($errors as $err) { set_flash('error', $err); } }
}

// ---------- APPROVE/REJECT LEAVE (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['leave_action']) && !empty($_POST['leave_id'])) {
    $leaveId = (int)$_POST['leave_id'];
    $leaveAction = $_POST['leave_action']; // 'approve' or 'reject'
    $leave = fetchRow("SELECT * FROM " . table('empleaves') . " WHERE id=? AND omitted=0 LIMIT 1", [$leaveId]);
    
    if ($leave && empty($leave['isapproved'])) {
        $empid = (int)$leave['empid'];
        $employee = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [$empid]);
        
        // Check if current user is the superior
        $currentEmpId = $isAdmin ? null : (int)($emp['id'] ?? 0);
        $isSuperior = $isAdmin || ($currentEmpId && $employee && (int)$employee['empsuperiorid'] === $currentEmpId);
        
        if ($isSuperior) {
            $now = date('Y-m-d H:i:s');
            $an = $u['username_ar'] ?? $u['username'];
            
            if ($leaveAction === 'approve') {
                // Approve: deduct from balance
                $fy = fetchRow("SELECT id FROM " . table('fiscalyears') . " WHERE omitted=0 AND ? BETWEEN fstartdate AND fenddate LIMIT 1", [$leave['startfrom']]);
                if ($fy) {
                    $lt = fetchRow("SELECT incwe, incholidays FROM " . table('leavestypes') . " WHERE id=? LIMIT 1", [$leave['leavetypeid']]);
                    $days = 0;
                    $s = strtotime($leave['startfrom']); $e = strtotime($leave['endon']);
                    for ($d = $s; $d <= $e; $d = strtotime('+1 day', $d)) {
                        $w = (int)date('w', $d);
                        if (!($lt['incwe'] ?? 0) && ($w === 0 || $w === 6)) continue;
                        $days++;
                    }
                    // Deduct from balance
                    q("UPDATE " . table('leavesbalances') . " SET usedbalance = usedbalance + ?, remainingbalance = remainingbalance - ?, lastedit=?, editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE empid=? AND fiscalyearid=? AND leavetypeid=? AND omitted=0",
                        [$days, $days, $now, $u['id'], $u['username'], $an, $an, $empid, $fy['id'], $leave['leavetypeid']]);
                }
                q("UPDATE " . table('empleaves') . " SET isapproved = 1, balanceafter = balancebefore - ?, lastedit=?, editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id = ?",
                    [$days ?? 0, $now, $u['id'], $u['username'], $an, $an, $leaveId]);
                set_flash('success', $isAr ? 'تمت الموافقة على الإجازة.' : 'Leave approved.');
            } elseif ($leaveAction === 'reject') {
                $rejectReason = trim($_POST['reject_reason'] ?? '');
                q("UPDATE " . table('empleaves') . " SET isapproved = 0, rejectedbyid = ?, rejectedby = ?, rejectedbyname = ?, rejectedbynamear = ?, rejectedat = ?, lastedit=?, editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id = ?",
                    [$u['id'], $u['username'], $an, $an, $now, $now, $u['id'], $u['username'], $an, $an, $leaveId]);
                set_flash('success', $isAr ? 'تم رفض الإجازة.' : 'Leave rejected.');
            }
            header('Location: index.php?module=ess&step=view&nid=' . urlencode($employee['nid'] ?? '')); exit;
        } else {
            set_flash('error', $isAr ? 'ليس لديك صلاحية للموافقة على هذه الإجازة.' : 'You do not have permission to approve this leave.');
        }
    }
}

// If no employee resolved yet, try by empid (for redirect after request)
if (!$emp && !empty($_GET['empid'])) {
    $emp = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [(int)$_GET['empid']]);
}

// ---------- VIEW: employee dashboard ----------
if ($emp) {
    $empid = (int)$emp['id'];
    $leavetypes = fetchAll("SELECT * FROM " . table('leavestypes') . " WHERE omitted=0 ORDER BY leavetypename ASC");
    $fyears = fetchAll("SELECT id, fstartdate, fenddate FROM " . table('fiscalyears') . " WHERE omitted=0 ORDER BY fstartdate DESC");
    $balances = fetchAll("SELECT * FROM " . table('leavesbalances') . " WHERE empid=? AND omitted=0 ORDER BY fstartdate DESC", [$empid]);
    $leaves = fetchAll("SELECT * FROM " . table('empleaves') . " WHERE empid=? AND omitted=0 ORDER BY id DESC", [$empid]);
    
    // Check if this employee has subordinates (for leave approval)
    $subordinates = fetchAll("SELECT id, empfullname, empfullnamear FROM " . table('employeesmain') . " WHERE empsuperiorid=? AND omitted=0", [$empid]);
    $hasSubordinates = count($subordinates) > 0;
    
    // Get pending leave requests from subordinates
    $pendingLeaves = [];
    if ($hasSubordinates) {
        $subIds = array_column($subordinates, 'id');
        $placeholders = implode(',', array_fill(0, count($subIds), '?'));
        $pendingLeaves = fetchAll("SELECT * FROM " . table('empleaves') . " WHERE empid IN ($placeholders) AND isapproved = 0 AND omitted = 0 ORDER BY id DESC", $subIds);
    }
    include __DIR__ . '/../../includes/header.php';
    ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($isAr ? $emp['empfullnamear'] : $emp['empfullname']) ?></h2>
    <div>
        <?php if ($isAdmin): ?>
        <a href="index.php?module=employeesmain" class="btn btn-secondary me-2"><i class="bi bi-arrow-left me-1"></i><?= $isAr ? 'العودة' : 'Back' ?></a>
        <?php endif; ?>
        <a href="index.php?action=logout" class="btn btn-danger"><i class="bi bi-box-arrow-right me-1"></i><?= $isAr ? 'تسجيل خروج' : 'Logout' ?></a>
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'بيانات الموظف' : 'Employee Details' ?></div>
            <div class="card-body">
                <p><strong><?= $isAr ? 'الاسم' : 'Name' ?>:</strong> <?= htmlspecialchars($isAr ? $emp['empfullnamear'] : $emp['empfullname']) ?></p>
                <p><strong><?= $isAr ? 'الرقم القومي' : 'National ID' ?>:</strong> <?= htmlspecialchars($emp['nid']) ?></p>
                <p><strong><?= $isAr ? 'البريد الإلكتروني' : 'Email' ?>:</strong> <?= htmlspecialchars($emp['email'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'اسم المستخدم (تسجيل الدخول)' : 'Login Username' ?>:</strong> <?= htmlspecialchars($emp['nid']) ?></p>
                <p><strong><?= $isAr ? 'النوع' : 'Gender' ?>:</strong> <?= htmlspecialchars($isAr ? ($emp['empgender'] === 'Male' ? 'ذكر' : 'أنثى') : ($emp['empgender'] ?? '')) ?></p>
                <p><strong><?= $isAr ? 'القسم' : 'Department' ?>:</strong> <?= htmlspecialchars($emp['depname'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'الحالة الوظيفية' : 'Status' ?>:</strong> <?= htmlspecialchars($emp['empstatusname'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'الدرجة' : 'Degree' ?>:</strong> <?= htmlspecialchars($emp['currentdegreename'] ?? '') ?></p>
                <p><strong><?= $isAr ? 'تاريخ الميلاد' : 'Date of Birth' ?>:</strong> <?= htmlspecialchars($emp['dop'] ?? '') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'أرصدة الإجازات' : 'Leave Balances' ?></div>
            <div class="card-body">
                <?php if (count($balances) === 0): ?>
                <p class="text-muted"><?= $isAr ? 'لا توجد أرصدة' : 'No balances' ?></p>
                <?php else: ?>
                <table class="table table-sm">
                    <thead><tr><th><?= $isAr ? 'نوع الإجازة' : 'Leave Type' ?></th><th><?= $isAr ? 'السنة' : 'Year' ?></th><th><?= $isAr ? 'متبقي' : 'Remaining' ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($balances as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($isAr ? $b['leavetypenamear'] : $b['leavetypename']) ?></td>
                        <td><?= htmlspecialchars($b['fstartdate'] ?? '') ?></td>
                        <td><?= (int)($b['remainingbalance'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (!$isAdmin): ?>
<div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'تغيير كلمة المرور' : 'Change Password' ?></div>
            <div class="card-body">
                <p class="text-muted small"><?= $isAr ? 'تغيير كلمة المرور الخاصة بك' : 'Change your account password' ?></p>
                <a href="index.php?action=change-password" class="btn btn-primary btn-sm">
                    <i class="bi bi-key me-1"></i><?= $isAr ? 'تغيير كلمة المرور' : 'Change Password' ?>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><?= $isAr ? 'طلب إجازة جديدة' : 'Request Leave' ?></div>
            <div class="card-body">
                <form method="post" action="index.php?module=ess&step=request">
                    <input type="hidden" name="empid" value="<?= $empid ?>">
                    <div class="mb-2"><label class="form-label"><?= $isAr ? 'نوع الإجازة' : 'Leave Type' ?></label>
                        <select name="leavetypeid" class="form-select form-select-sm"><option value="">-- <?= $isAr ? 'اختر' : 'Select' ?> --</option>
                        <?php foreach ($leavetypes as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($isAr ? $t['leavetypenamear'] : $t['leavetypename']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="row"><div class="col-6 mb-2"><label class="form-label"><?= $isAr ? 'من' : 'From' ?></label>
                        <input type="date" name="startfrom" class="form-control form-control-sm"></div>
                        <div class="col-6 mb-2"><label class="form-label"><?= $isAr ? 'إلى' : 'To' ?></label>
                        <input type="date" name="endon" class="form-control form-control-sm"></div></div>
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
                    <form method="post" action="index.php?module=ess&step=view&nid=<?= urlencode($emp['nid']) ?>" style="display:inline;">
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
                                <form method="post" action="index.php?module=ess&step=view&nid=<?= urlencode($emp['nid']) ?>">
                                    <div class="modal-body">
                                        <input type="hidden" name="leave_id" value="<?= $pl['id'] ?>">
                                        <input type="hidden" name="leave_action" value="reject">
                                        <input type="hidden" name="empid" value="<?= $pl['empid'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label"><?= $isAr ? 'سبب الرفض' : 'Rejection Reason' ?></label>
                                            <textarea name="reject_reason" class="form-control" rows="3"></textarea>
                                        </div>
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
        <table class="table table-sm">
            <thead><tr><th><?= $isAr ? 'النوع' : 'Type' ?></th><th><?= $isAr ? 'من' : 'From' ?></th><th><?= $isAr ? 'إلى' : 'To' ?></th><th><?= $isAr ? 'الحالة' : 'Status' ?></th></tr></thead>
            <tbody>
            <?php foreach ($leaves as $l): ?>
            <tr><td><?= htmlspecialchars($isAr ? $l['leavetypenamear'] : $l['leavetypename']) ?></td>
                <td><?= htmlspecialchars($l['startfrom']) ?></td><td><?= htmlspecialchars($l['endon']) ?></td>
                <td><?= !empty($l['isapproved']) ? ($isAr ? 'موافق' : 'Approved') : ($isAr ? 'قيد الانتظار' : 'Pending') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

// ---------- ADMIN LOOKUP (by NID) ----------
if ($isAdmin) {
    include __DIR__ . '/../../includes/header.php';
    ?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <h2 class="card-title"><i class="bi bi-person-circle me-2"></i><?= $isAr ? 'خدمة الموظف الذاتية' : 'Employee Self-Service' ?></h2>
                </div>
                <p class="text-center text-muted"><?= $isAr ? 'أدخل الرقم القومي للبحث عن موظف.' : 'Enter National ID to look up an employee.' ?></p>
                <?php if ($nid !== '' && !$emp): ?>
                <div class="alert alert-danger"><?= $isAr ? 'لم يتم العثور على موظف بهذا الرقم القومي.' : 'No employee found with this National ID.' ?></div>
                <?php endif; ?>
                <form method="get" action="index.php?module=ess">
                    <input type="hidden" name="module" value="ess">
                    <div class="mb-3">
                        <label class="form-label"><?= $isAr ? 'الرقم القومي' : 'National ID' ?></label>
                        <input type="text" name="nid" class="form-control" value="<?= htmlspecialchars($nid) ?>" maxlength="50" required autofocus>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-primary btn-lg"><?= $isAr ? 'بحث' : 'Search' ?></button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <a href="index.php?action=logout" class="btn btn-outline-danger btn-sm"><?= $isAr ? 'تسجيل خروج' : 'Logout' ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

// ---------- NON-ADMIN WITHOUT EMPLOYEE RECORD ----------
include __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-body p-4">
                <div class="alert alert-warning">
                    <?= $isAr ? 'لم يتم العثور على سجل موظف مرتبط بحسابك. يرجى التواصل مع المسؤول.' : 'No employee record found for your account. Please contact the administrator.' ?>
                </div>
                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-secondary me-2"><?= $isAr ? 'العودة' : 'Back' ?></a>
                    <a href="index.php?action=logout" class="btn btn-danger"><?= $isAr ? 'تسجيل خروج' : 'Logout' ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
include __DIR__ . '/../../includes/footer.php';
