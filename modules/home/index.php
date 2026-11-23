<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

// UnitHR - Dashboard
require_login();
$isAr = lang() === 'ar';

$modEnabled = function ($folder) {
    $info = get_module($folder);
    return is_array($info) && (array_key_exists('enabled', $info) ? !empty($info['enabled']) : true);
};

$stats = [];
$chartEmpType = ['labels' => [], 'values' => []];
$chartEmpGroup = ['labels' => [], 'values' => []];
$chartEmpDegree = ['labels' => [], 'values' => []];

if (db_available()) {
    $count = function ($tbl, $where = 'omitted=0') {
        $row = fetchRow("SELECT COUNT(*) c FROM " . table($tbl) . " WHERE " . $where);
        return (int)(($row && isset($row['c'])) ? $row['c'] : 0);
    };

    $stats['departments'] = $count('depstructure');
    $stats['employees']   = $count('employeesmain');
    $stats['users']       = $count('users');
    $stats['leavetypes']  = $modEnabled('leavestypes') ? $count('leavestypes') : 0;

    if ($modEnabled('empleaves')) {
        $stats['leaves']      = $count('empleaves');
        $stats['leaves_pending']  = $count('empleaves', "omitted=0 AND isapproved=0 AND (rejectedat IS NULL OR rejectedat='')");
        $stats['leaves_approved'] = $count('empleaves', "omitted=0 AND isapproved=1");
        $stats['leaves_rejected'] = $count('empleaves', "omitted=0 AND rejectedat IS NOT NULL AND rejectedat<>''");
    } else {
        $stats['leaves'] = 0; $stats['leaves_pending'] = 0; $stats['leaves_approved'] = 0; $stats['leaves_rejected'] = 0;
    }

    if ($modEnabled('attendance')) {
        $stats['att_records'] = $count('daily_attendance');
        $stats['att_present'] = $count('daily_attendance', "omitted=0 AND status_code='PRESENT'");
        $stats['att_absent']  = $count('daily_attendance', "omitted=0 AND status_code='ABSENT'");
    } else {
        $stats['att_records'] = 0; $stats['att_present'] = 0; $stats['att_absent'] = 0;
    }

    if ($modEnabled('attpermissions')) {
        $stats['att_perms']   = $count('att_permission_requests');
        $stats['att_perms_pending'] = $count('att_permission_requests', "omitted=0 AND (status IS NULL OR status='' OR status='PENDING')");
        $stats['att_perms_approved'] = $count('att_permission_requests', "omitted=0 AND status='APPROVED'");
        $stats['att_perms_rejected'] = $count('att_permission_requests', "omitted=0 AND status='REJECTED'");
    } else {
        $stats['att_perms'] = 0; $stats['att_perms_pending'] = 0; $stats['att_perms_approved'] = 0; $stats['att_perms_rejected'] = 0;
    }

    if ($modEnabled('payroll')) {
        $stats['payroll_runs'] = $count('payroll_runs');
        $stats['payroll_final_runs'] = $count('payroll_runs', "omitted=0 AND status='final'");
        $row = fetchRow("SELECT COALESCE(SUM(net_total),0) n FROM " . table('payroll_runs') . " WHERE omitted=0 AND status='final' AND payroll_year=?", [(int)date('Y')]);
        $stats['payroll_ytd_net'] = $row ? (float)$row['n'] : 0;
    } else {
        $stats['payroll_runs'] = 0; $stats['payroll_final_runs'] = 0; $stats['payroll_ytd_net'] = 0;
    }

    // Employees by type
    $rows = fetchAll("SELECT emptypename, emptypenamear, COUNT(*) c FROM " . table('employeesmain') . " WHERE omitted=0 GROUP BY emptypename, emptypenamear ORDER BY c DESC");
    foreach ($rows as $r) {
        $chartEmpType['labels'][] = $isAr ? ($r['emptypenamear'] ?: $r['emptypename']) : $r['emptypename'];
        $chartEmpType['values'][] = (int)$r['c'];
    }

    // Employees by group
    $rows = fetchAll("SELECT empgroupname, empgroupnamear, COUNT(*) c FROM " . table('employeesmain') . " WHERE omitted=0 GROUP BY empgroupname, empgroupnamear ORDER BY c DESC");
    foreach ($rows as $r) {
        $chartEmpGroup['labels'][] = $isAr ? ($r['empgroupnamear'] ?: $r['empgroupname']) : $r['empgroupname'];
        $chartEmpGroup['values'][] = (int)$r['c'];
    }

    // Employees by degree
    $rows = fetchAll("SELECT currentdegreename, currentdegreenamear, COUNT(*) c FROM " . table('employeesmain') . " WHERE omitted=0 GROUP BY currentdegreename, currentdegreenamear ORDER BY c DESC");
    foreach ($rows as $r) {
        $chartEmpDegree['labels'][] = $isAr ? ($r['currentdegreenamear'] ?: $r['currentdegreename']) : $r['currentdegreename'];
        $chartEmpDegree['values'][] = (int)$r['c'];
    }
} else {
    $stats = ['departments'=>0,'employees'=>0,'leaves'=>0,'users'=>0,'leavetypes'=>0,
              'leaves_pending'=>0,'leaves_approved'=>0,'leaves_rejected'=>0,
              'att_records'=>0,'att_present'=>0,'att_absent'=>0,
              'att_perms'=>0,'att_perms_pending'=>0,'att_perms_approved'=>0,'att_perms_rejected'=>0,
              'payroll_runs'=>0,'payroll_final_runs'=>0,'payroll_ytd_net'=>0];
}

$statCards = [];
$statCards[] = ['label' => $isAr ? 'الموظفون' : 'Employees', 'value' => $stats['employees'], 'color' => 'success', 'icon' => 'bi-people-fill'];
$statCards[] = ['label' => $isAr ? 'الأقسام' : 'Departments', 'value' => $stats['departments'], 'color' => 'info', 'icon' => 'bi-building'];
$statCards[] = ['label' => $isAr ? 'المستخدمون' : 'Users', 'value' => $stats['users'], 'color' => 'primary', 'icon' => 'bi-person-badge'];
if ($modEnabled('leavestypes')) { $statCards[] = ['label' => $isAr ? 'أنواع الإجازات' : 'Leave Types', 'value' => $stats['leavetypes'], 'color' => 'secondary', 'icon' => 'bi-calendar-event']; }
if ($modEnabled('empleaves')) { $statCards[] = ['label' => $isAr ? 'الإجازات' : 'Leaves', 'value' => $stats['leaves'], 'color' => 'warning', 'icon' => 'bi-calendar-check']; }
if ($modEnabled('attendance')) { $statCards[] = ['label' => $isAr ? 'سجلات الحضور' : 'Attendance Records', 'value' => $stats['att_records'], 'color' => 'dark', 'icon' => 'bi-clock-history']; }
if ($modEnabled('attpermissions')) { $statCards[] = ['label' => $isAr ? 'أذونات الحضور' : 'Attendance Permissions', 'value' => $stats['att_perms'], 'color' => 'danger', 'icon' => 'bi-shield-check']; }
if ($modEnabled('payroll')) {
    $statCards[] = ['label' => $isAr ? 'مسيرات الرواتب' : 'Payroll Runs', 'value' => $stats['payroll_runs'], 'color' => 'success', 'icon' => 'bi-cash-stack'];
    $statCards[] = ['label' => $isAr ? 'صافي الإستحقاقات (السنة)' : 'Payroll Net (Year)', 'value' => number_format($stats['payroll_ytd_net'], 2), 'color' => 'info', 'icon' => 'bi-wallet2'];
}
?>
<script src="assets/js/chart.umd.min.js"></script>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-speedometer2 me-2"></i><?= $isAr ? 'لوحة التحكم' : 'Dashboard' ?></h2>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
<?php foreach ($statCards as $card): ?>
<div class="col-6 col-md-3">
    <div class="card text-white bg-<?= htmlspecialchars($card['color']) ?> h-100">
        <div class="card-body d-flex align-items-center">
            <i class="bi <?= htmlspecialchars($card['icon']) ?> fs-1 me-3 opacity-75"></i>
            <div>
                <h6 class="card-title mb-1"><?= htmlspecialchars($card['label']) ?></h6>
                <p class="display-6 fw-bold mb-0"><?= (int)$card['value'] ?></p>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Breakdown cards -->
<div class="row g-3 mb-4">
<?php if ($modEnabled('empleaves')): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><?= $isAr ? 'الإجازات' : 'Leaves' ?></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span><?= $isAr ? 'قيد الانتظار' : 'Pending' ?></span><span class="badge bg-warning text-dark"><?= (int)$stats['leaves_pending'] ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span><?= $isAr ? 'موافق' : 'Approved' ?></span><span class="badge bg-success"><?= (int)$stats['leaves_approved'] ?></span></div>
                <div class="d-flex justify-content-between"><span><?= $isAr ? 'مرفوض' : 'Rejected' ?></span><span class="badge bg-danger"><?= (int)$stats['leaves_rejected'] ?></span></div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php if ($modEnabled('attendance')): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><?= $isAr ? 'الحضور' : 'Attendance' ?></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span><?= $isAr ? 'حاضر' : 'Present' ?></span><span class="badge bg-success"><?= (int)$stats['att_present'] ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span><?= $isAr ? 'غائب' : 'Absent' ?></span><span class="badge bg-danger"><?= (int)$stats['att_absent'] ?></span></div>
                <div class="d-flex justify-content-between"><span><?= $isAr ? 'إجمالي السجلات' : 'Total Records' ?></span><span class="badge bg-secondary"><?= (int)$stats['att_records'] ?></span></div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php if ($modEnabled('attpermissions')): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><?= $isAr ? 'أذونات الحضور' : 'Attendance Permissions' ?></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span><?= $isAr ? 'قيد الانتظار' : 'Pending' ?></span><span class="badge bg-warning text-dark"><?= (int)$stats['att_perms_pending'] ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span><?= $isAr ? 'موافق' : 'Approved' ?></span><span class="badge bg-success"><?= (int)$stats['att_perms_approved'] ?></span></div>
                <div class="d-flex justify-content-between"><span><?= $isAr ? 'مرفوض' : 'Rejected' ?></span><span class="badge bg-danger"><?= (int)$stats['att_perms_rejected'] ?></span></div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php if ($modEnabled('payroll')): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><?= $isAr ? 'الإستحقاقات' : 'Payroll' ?></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span><?= $isAr ? 'مسيرات' : 'Runs' ?></span><span class="badge bg-success"><?= (int)$stats['payroll_runs'] ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span><?= $isAr ? 'معتمدة' : 'Finalized' ?></span><span class="badge bg-primary"><?= (int)$stats['payroll_final_runs'] ?></span></div>
                <div class="d-flex justify-content-between"><span><?= $isAr ? 'صافي (السنة)' : 'Net (Year)' ?></span><span class="badge bg-info"><?= number_format($stats['payroll_ytd_net'], 2) ?></span></div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<!-- Charts -->
<div class="row g-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><?= $isAr ? 'الموظفون حسب النوع' : 'Employees by Type' ?></div>
            <div class="card-body">
                <?php if (empty($chartEmpType['values'])): ?>
                    <p class="text-muted text-center mb-0"><?= $isAr ? 'لا توجد بيانات' : 'No data' ?></p>
                <?php else: ?>
                    <canvas id="chartEmpType" height="220"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><?= $isAr ? 'الموظفون حسب المجموعة' : 'Employees by Group' ?></div>
            <div class="card-body">
                <?php if (empty($chartEmpGroup['values'])): ?>
                    <p class="text-muted text-center mb-0"><?= $isAr ? 'لا توجد بيانات' : 'No data' ?></p>
                <?php else: ?>
                    <canvas id="chartEmpGroup" height="220"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><?= $isAr ? 'الموظفون حسب الدرجة' : 'Employees by Degree' ?></div>
            <div class="card-body">
                <?php if (empty($chartEmpDegree['values'])): ?>
                    <p class="text-muted text-center mb-0"><?= $isAr ? 'لا توجد بيانات' : 'No data' ?></p>
                <?php else: ?>
                    <canvas id="chartEmpDegree" height="220"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var palette = ['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#fd7e14','#20c997','#0dcaf0','#6c757d'];
    function makeChart(id, type, labels, data) {
        var ctx = document.getElementById(id);
        if (!ctx) return;
        new Chart(ctx, {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    label: '',
                    data: data,
                    backgroundColor: palette.slice(0, data.length),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: type !== 'bar', position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });
    }
    makeChart('chartEmpType', 'doughnut', <?= json_encode($chartEmpType['labels']) ?>, <?= json_encode($chartEmpType['values']) ?>);
    makeChart('chartEmpGroup', 'bar', <?= json_encode($chartEmpGroup['labels']) ?>, <?= json_encode($chartEmpGroup['values']) ?>);
    makeChart('chartEmpDegree', 'bar', <?= json_encode($chartEmpDegree['labels']) ?>, <?= json_encode($chartEmpDegree['values']) ?>);
})();
</script>