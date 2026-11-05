<?php
/**
 * EmpSeniority - Employee Seniority & Promotion Management
 */
require_login();
require_once __DIR__ . '/../../includes/helpers.php';
$module = 'empseniority';
$isAr = lang() === 'ar';
$u = current_user();
$canEdit = user_can($module, 'edit');

// Handle promotion action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_empid'])) {
    require_permission($module, 'edit');
    $empid = (int)$_POST['promote_empid'];
    $newDegreeId = (int)$_POST['new_degree_id'];
    $promotionDate = trim($_POST['promotion_date'] ?? date('Y-m-d'));
    
    $emp = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [$empid]);
    $currentDegree = fetchRow("SELECT * FROM " . table('empdegrees') . " WHERE id=? AND omitted=0 LIMIT 1", [$emp['currentdegreeid']]);
    $newDegree = fetchRow("SELECT * FROM " . table('empdegrees') . " WHERE id=? AND omitted=0 LIMIT 1", [$newDegreeId]);
    
    if ($emp && $currentDegree && $newDegree) {
        $seniorityDate = $emp['senioritydate'] ?: $emp['degreeacquireddate'];
        $interimPeriod = (int)$currentDegree['interimperiod'];
        
        // Calculate new seniority date
        $newSeniorityDate = calculate_seniority_date(
            $emp['degreeacquireddate'] ?: $emp['dop'],
            $interimPeriod,
            $promotionDate
        );
        
        // Update employee
        $now = date('Y-m-d H:i:s');
        $an = $u['username_ar'] ?? $u['username'];
        q("UPDATE " . table('employeesmain') . " SET 
            currentdegreeid = ?, 
            currentdegreename = ?, 
            currentdegreenamear = ?,
            degreeacquireddate = ?,
            senioritydate = ?,
            lastedit = ?, 
            editedbyid = ?, 
            editedby = ?, 
            editedbyname = ?, 
            editedbynamear = ?
            WHERE id = ?",
            [$newDegreeId, $newDegree['degreename'], $newDegree['degreenamear'],
             $promotionDate, $newSeniorityDate, $now, $u['id'], $u['username'], $an, $an, $empid]);
        
        // Cascade update to balances and leaves
        q("UPDATE " . table('leavesbalances') . " SET empfullname=?, empfullnamear=? WHERE empid=? AND omitted=0",
            [$emp['empfullname'], $emp['empfullnamear'], $empid]);
        q("UPDATE " . table('empleaves') . " SET empfullname=?, empfullnamear=? WHERE empid=? AND omitted=0",
            [$emp['empfullname'], $emp['empfullnamear'], $empid]);
        
        set_flash('success', $isAr ? 'تمت ترقية الموظف بنجاح.' : 'Employee promoted successfully.');
        header('Location: index.php?module=empseniority'); exit;
    } else {
        set_flash('error', $isAr ? 'بيانات غير صحيحة.' : 'Invalid data.');
    }
}

// Get all employees with seniority info
$search = trim($_GET['search'] ?? '');
$where = "e.omitted = 0";
$params = [];
if ($search !== '') {
    $norm = arabic_normalize($search);
    $where .= " AND (e.empfullname LIKE ? OR e.empfullnamear LIKE ?)";
    $params = ['%'.$search.'%', '%'.$norm.'%'];
}
$employees = fetchAll("SELECT e.*, d.degreename, d.degreenamear, d.interimperiod, d.nextdegreeid
    FROM " . table('employeesmain') . " e
    LEFT JOIN " . table('empdegrees') . " d ON d.id = e.currentdegreeid
    WHERE $where
    ORDER BY e.empfullname ASC", $params);

// Get all degrees for promotion dropdown
$degrees = fetchAll("SELECT id, degreename, degreenamear, interimperiod, nextdegreeid FROM " . table('empdegrees') . " WHERE omitted=0 ORDER BY degreename ASC");

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-graph-up-arrow me-2"></i><?= $isAr ? 'أقدمية الموظفين' : 'Employee Seniority' ?></h2>
</div>

<form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="module" value="empseniority">
    <div class="col-md-4"><input type="search" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Search (Arabic/English)..."></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
    <div class="col-md-2"><a href="index.php?module=empseniority" class="btn btn-outline-secondary w-100">Reset</a></div>
</form>

<div class="table-responsive">
<table class="table table-striped table-hover align-middle table-sm">
<thead class="table-dark"><tr>
    <th>#</th>
    <th><?= $isAr ? 'الموظف' : 'Employee' ?></th>
    <th><?= $isAr ? 'الدرجة الحالية' : 'Current Degree' ?></th>
    <th><?= $isAr ? 'تاريخ الحصول على الدرجة' : 'Degree Acquired' ?></th>
    <th><?= $isAr ? 'تاريخ الأقدمية' : 'Seniority Date' ?></th>
    <th><?= $isAr ? 'فترة الانتقال' : 'Interim Period' ?></th>
    <th><?= $isAr ? 'مؤهل للترقية' : 'Eligible' ?></th>
    <th><?= $isAr ? 'الدرجة التالية' : 'Next Degree' ?></th>
    <th><?= $isAr ? 'الإجراءات' : 'Actions' ?></th>
</tr></thead>
<tbody>
<?php foreach ($employees as $i => $e): ?>
<?php
    $eligible = is_eligible_for_promotion($e['currentdegreeid'], $e['senioritydate'] ?: $e['degreeacquireddate']);
    $nextDegree = null;
    if ($e['nextdegreeid']) {
        $nextDegree = fetchRow("SELECT degreename, degreenamear FROM " . table('empdegrees') . " WHERE id=? AND omitted=0 LIMIT 1", [$e['nextdegreeid']]);
    }
?>
<tr>
    <td><?= $i+1 ?></td>
    <td><?= htmlspecialchars($isAr ? ($e['empfullnamear'] ?? '') : ($e['empfullname'] ?? '')) ?></td>
    <td><?= htmlspecialchars($isAr ? ($e['degreenamear'] ?? '') : ($e['degreename'] ?? '')) ?></td>
    <td><?= htmlspecialchars($e['degreeacquireddate'] ?? '-') ?></td>
    <td><?= htmlspecialchars($e['senioritydate'] ?? '-') ?></td>
    <td><?= (int)($e['interimperiod'] ?? 0) ?> <?= $isAr ? 'سنوات' : 'years' ?></td>
    <td>
        <?php if ($eligible): ?>
            <span class="badge bg-success"><?= $isAr ? 'نعم' : 'Yes' ?></span>
        <?php else: ?>
            <span class="badge bg-secondary"><?= $isAr ? 'لا' : 'No' ?></span>
        <?php endif; ?>
    </td>
    <td><?= $nextDegree ? htmlspecialchars($isAr ? $nextDegree['degreenamear'] : $nextDegree['degreename']) : ($isAr ? 'لا يوجد' : 'N/A') ?></td>
    <td>
        <?php if ($eligible && $nextDegree): ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#promoteModal<?= $e['id'] ?>">
                <i class="bi bi-arrow-up-circle me-1"></i><?= $isAr ? 'ترقية' : 'Promote' ?>
            </button>
            <!-- Promote Modal -->
            <div class="modal fade" id="promoteModal<?= $e['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><?= $isAr ? 'ترقية موظف' : 'Promote Employee' ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post" action="index.php?module=empseniority">
                            <div class="modal-body">
                                <input type="hidden" name="promote_empid" value="<?= $e['id'] ?>">
                                <div class="mb-3">
                                    <label class="form-label"><?= $isAr ? 'الموظف' : 'Employee' ?></label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($isAr ? ($e['empfullnamear'] ?? '') : ($e['empfullname'] ?? '')) ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= $isAr ? 'الدرجة الحالية' : 'Current Degree' ?></label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($isAr ? ($e['degreenamear'] ?? '') : ($e['degreename'] ?? '')) ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= $isAr ? 'الدرجة التالية' : 'Next Degree' ?></label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($isAr ? ($nextDegree['degreenamear'] ?? '') : ($nextDegree['degreename'] ?? '')) ?>" readonly>
                                    <input type="hidden" name="new_degree_id" value="<?= $e['nextdegreeid'] ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= $isAr ? 'تاريخ الترقية' : 'Promotion Date' ?></label>
                                    <input type="date" name="promotion_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="alert alert-info small">
                                    <?= $isAr ? 'تاريخ الأقدمية في الدرجة الجديدة سيتم حسابه تلقائياً بناءً على الوقت الإضافي.' : 'Seniority date in the new degree will be calculated automatically based on excess time.' ?>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $isAr ? 'إلغاء' : 'Cancel' ?></button>
                                <button type="submit" class="btn btn-primary"><?= $isAr ? 'ترقية' : 'Promote' ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>


