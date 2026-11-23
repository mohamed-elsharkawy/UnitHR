<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

/**
 * Reports - export data in CSV, XLSX, Word, PDF (offline assets).
 */
require_login();
require_permission('reports', 'view');
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/modules.php';
$isAr = lang() === 'ar';

$reports = [
    'employees' => [
        'title' => $isAr ? 'تقرير الموظفين' : 'Employees Report',
        'sql'   => "SELECT empfullname AS 'Full Name', empfullnamear AS 'Full Name (Arabic)', nid AS 'National ID',
                           depname AS 'Department', depnamear AS 'Department (Arabic)',
                           empstatusname AS 'Status', empstatusnamear AS 'Status (Arabic)',
                           currentdegreename AS 'Degree', currentdegreenamear AS 'Degree (Arabic)',
                           empgender AS 'Gender'
                    FROM " . table('employeesmain') . " WHERE omitted=0 ORDER BY empfullname",
    ],
    'leaves' => [
        'title' => $isAr ? 'تقرير الإجازات' : 'Leaves Report',
        'sql'   => "SELECT empfullname AS 'Employee', empfullnamear AS 'Employee (Arabic)',
                           leavetypename AS 'Leave Type', leavetypenamear AS 'Leave Type (Arabic)',
                           startfrom AS 'Start From', endon AS 'End On',
                           balancebefore AS 'Balance Before', balanceafter AS 'Balance After',
                           isapproved AS 'Approved', empsuperiorname AS 'Superior'
                    FROM " . table('empleaves') . " WHERE omitted=0 ORDER BY id DESC",
    ],
    'balances' => [
        'title' => $isAr ? 'تقرير أرصدة الإجازات' : 'Leave Balances Report',
        'sql'   => "SELECT b.empfullname AS 'Employee', b.empfullnamear AS 'Employee (Arabic)',
                           b.leavetypename AS 'Leave Type', b.leavetypenamear AS 'Leave Type (Arabic)',
                           b.fstartdate AS 'Fiscal Year Start', b.fenddate AS 'Fiscal Year End',
                           b.carryoverbalance AS 'Carryover', b.initialbalance AS 'Initial',
                           b.usedbalance AS 'Used', b.remainingbalance AS 'Remaining'
                    FROM " . table('leavesbalances') . " b WHERE b.omitted=0 ORDER BY b.id DESC",
    ],
    'archives' => [
        'title' => $isAr ? 'تقرير أرشيف الموظفين' : 'Employees Archives Report',
        'sql'   => "SELECT a.empfullname AS 'Employee', a.empfullnamear AS 'Employee (Arabic)',
                           a.nid AS 'NID', a.documenttypename AS 'Document Type',
                           a.documenttypenamear AS 'Document Type (Arabic)',
                           a.documentname AS 'Document Name', a.documentpath AS 'File Path',
                           a.notes AS 'Notes'
                    FROM " . table('emparchives') . " a WHERE a.omitted=0 ORDER BY a.id DESC",
    ],
    'promotions' => [
        'title' => $isAr ? 'تقرير الترقيات' : 'Manual Promotions Report',
        'sql'   => "SELECT p.empfullname AS 'Employee', p.empfullnamear AS 'Employee (Arabic)',
                           p.promotiondate AS 'Promotion Date', p.degreename AS 'Degree Acquired',
                           p.degreenamear AS 'Degree Acquired (Arabic)',
                           p.degreeacquireddate AS 'Degree Acquired Date',
                           p.decisionnumber AS 'Decision Number', p.decisiondate AS 'Decision Date',
                           p.senioritydate AS 'Seniority Date', p.decisionfilepath AS 'Decision File'
                    FROM " . table('emppromotions') . " p WHERE p.omitted=0 ORDER BY p.id DESC",
    ],
];

// Payroll reports (only when the payroll module is enabled)
if (function_exists('get_module')) {
    $payrollInfo = get_module('payroll');
    if (is_array($payrollInfo) && (array_key_exists('enabled', $payrollInfo) ? !empty($payrollInfo['enabled']) : true)) {
        $reports['payroll_runs'] = [
            'title' => $isAr ? 'مسيرات الرواتب' : 'Payroll Runs',
            'sql'   => "SELECT r.payroll_year AS 'Year', r.payroll_month AS 'Month', r.status AS 'Status',
                               r.employee_count AS 'Employees', r.gross_total AS 'Gross', r.taxable_total AS 'Taxable',
                               r.tax_total AS 'Tax', r.insurance_total AS 'Insurance', r.employer_insurance_total AS 'Employer Insurance',
                               r.deductions_total AS 'Deductions', r.net_total AS 'Net'
                        FROM " . table('payroll_runs') . " r WHERE r.omitted=0 ORDER BY r.payroll_year DESC, r.payroll_month DESC",
        ];
        $reports['payroll_items'] = [
            'title' => $isAr ? 'بنود مسيرات الرواتب' : 'Payroll Run Items',
            'sql'   => "SELECT i.empfullname AS 'Employee', i.empfullnamear AS 'Employee (Arabic)',
                               i.basic_salary AS 'Basic', i.gross_pay AS 'Gross', i.taxable_base AS 'Taxable Base',
                               i.tax_amount AS 'Tax', i.insurance_base AS 'Insurance Base', i.insurance_amount AS 'Insurance',
                               i.employer_insurance AS 'Employer Insurance', i.deductions_total AS 'Deductions', i.net_salary AS 'Net'
                        FROM " . table('payroll_run_items') . " i
                        INNER JOIN " . table('payroll_runs') . " r ON r.id=i.run_id
                        WHERE i.omitted=0 AND r.omitted=0 ORDER BY r.payroll_year DESC, r.payroll_month DESC, i.empfullname",
        ];
        $reports['payroll_types'] = [
            'title' => $isAr ? 'أنواع الإستحقاقات والخصومات' : 'Payroll Types',
            'sql'   => "SELECT code AS 'Code', name AS 'Name', name_ar AS 'Name (Arabic)', kind AS 'Kind',
                               calc_method AS 'Method', rate AS 'Rate', amount AS 'Amount', before_tax AS 'Before Tax',
                               before_insurance AS 'Before Insurance', enabled AS 'Enabled'
                        FROM " . table('payroll_types') . " WHERE omitted=0 ORDER BY kind, sort_order",
        ];
    }
}

$report = $_GET['report'] ?? '';
$fmt = $_GET['fmt'] ?? '';

if ($fmt !== '' && isset($reports[$report])) {
    $rep = $reports[$report];
    $rows = fetchAll($rep['sql']);
    if ($rows === false) {
        set_flash('error', 'Database not available.');
        header('Location: ?module=reports');
        exit;
    }
    if (empty($rows)) {
        set_flash('error', 'No data to export.');
        header('Location: ?module=reports');
        exit;
    }
    $headers = array_keys($rows[0]);
    $dataRows = [];
    foreach ($rows as $r) {
        $dataRows[] = array_values($r);
    }
    do_export($fmt, $report . '_report', $rep['title'], $headers, $dataRows);
    exit;
}

$formats = [
    'csv'  => 'CSV',
    'xlsx' => 'XLSX',
    'docx' => $isAr ? 'وورد' : 'Word',
    'pdf'  => 'PDF',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-file-earmark-bar-graph me-2"></i><?= $isAr ? 'التقارير' : 'Reports' ?></h2>
</div>
<div class="row">
<?php foreach ($reports as $key => $rep): ?>
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($rep['title']) ?></h5>
                <p class="text-muted small"><?= $isAr ? 'اختر صيغة التصدير' : 'Choose an export format' ?></p>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($formats as $f => $label): ?>
                    <a class="btn btn-sm btn-outline-primary" href="?module=reports&amp;report=<?= urlencode($key) ?>&amp;fmt=<?= urlencode($f) ?>"><?= htmlspecialchars($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
