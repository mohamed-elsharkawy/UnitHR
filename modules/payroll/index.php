<?php
/**
 * UnitHR - Payroll module
 *
 * Configurable payabilities/deductions, Egyptian income tax, social insurance,
 * CSV imports, payroll runs, payslips and year-end reconciliation.
 */

if (!defined('UNITHR_ENTRY')) {
    header('Location: ../../');
    exit;
}

require_once __DIR__ . '/engine.php';
require_once __DIR__ . '/import.php';

global $module, $isAr, $canEdit, $canAdd, $canDel, $currency, $page;
$module = 'payroll';
require_login();
require_permission($module, 'view');

$isAr = lang() === 'ar';
$canEdit = user_can($module, 'edit');
$canAdd = user_can($module, 'add');
$canDel = user_can($module, 'del');
$currency = (string)payroll_setting('currency', 'EGP');
$page = isset($_GET['a']) ? (string)$_GET['a'] : 'dashboard';

// -------------------------------------------------------------------------
// Small presentation and input helpers
// -------------------------------------------------------------------------
function payroll_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function payroll_fmt($value): string
{
    return number_format((float)$value, 2, '.', ',');
}

function payroll_display_name(array $row, bool $isAr): string
{
    if ($isAr) {
        return (string)(($row['empfullnamear'] ?? '') !== '' ? $row['empfullnamear'] : ($row['empfullname'] ?? ''));
    }
    return (string)($row['empfullname'] ?? '');
}

function payroll_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function payroll_month_select(string $name, int $selected, string $id = '', bool $required = false): void
{
    $idAttr = $id !== '' ? ' id="' . payroll_h($id) . '"' : '';
    echo '<select class="form-select" name="' . payroll_h($name) . '"' . $idAttr . ($required ? ' required' : '') . '>';
    for ($m = 1; $m <= 12; $m++) {
        echo '<option value="' . $m . '"' . ($m === $selected ? ' selected' : '') . '>'
            . payroll_h(L('payroll_month_' . $m)) . '</option>';
    }
    echo '</select>';
}

function payroll_year_input(string $name, int $value, string $class = 'form-control'): void
{
    echo '<input type="number" class="' . payroll_h($class) . '" name="' . payroll_h($name)
        . '" value="' . $value . '" min="2000" max="2100" step="1" required>';
}

function payroll_method_label(string $method): string
{
    $key = 'payroll_method_' . $method;
    $label = L($key);
    return $label === $key ? $method : $label;
}

function payroll_type_value_label(array $type): string
{
    $method = (string)($type['calc_method'] ?? '');
    $rate = (float)($type['rate'] ?? 0);
    $amount = (float)($type['amount'] ?? 0);
    switch ($method) {
        case 'percent_basic':
        case 'percent_gross':
        case 'percent_type':
            return payroll_fmt($rate) . '%';
        case 'fixed':
            return payroll_fmt($amount);
        case 'degree':
            return L('payroll_method_degree');
        case 'seniority':
            return $rate > 0
                ? payroll_fmt($rate) . '% / ' . L('payroll_service_years')
                : payroll_fmt($amount) . ' / ' . L('payroll_service_years');
        case 'manual':
            return L('payroll_method_manual');
        default:
            return L('payroll_automatic');
    }
}

function payroll_status_badge(string $status): string
{
    if ($status === 'final') {
        return '<span class="badge bg-success">' . payroll_h(L('payroll_final')) . '</span>';
    }
    return '<span class="badge bg-secondary">' . payroll_h(L('payroll_draft')) . '</span>';
}

function payroll_source_label(?string $source): string
{
    $source = (string)$source;
    if ($source === '') {
        return '';
    }
    $key = 'payroll_source_' . $source;
    $label = L($key);
    return $label === $key ? $source : $label;
}

function payroll_input_money(string $key, float $default = 0.0, bool $nullable = false): ?float
{
    $raw = trim((string)($_POST[$key] ?? ''));
    if ($raw === '') {
        return $nullable ? null : $default;
    }
    $value = payroll_parse_number($raw);
    if ($value === null) {
        throw new RuntimeException(L('payroll_invalid_amount'));
    }
    return $value;
}

function payroll_input_rate(string $key, float $default = 0.0): float
{
    $raw = trim((string)($_POST[$key] ?? ''));
    if ($raw === '') {
        return $default;
    }
    $value = payroll_parse_number($raw);
    if ($value === null || $value < 0 || $value > 100) {
        throw new RuntimeException(L('payroll_invalid_rate'));
    }
    return $value;
}

function payroll_input_date(string $key): ?string
{
    $raw = trim((string)($_POST[$key] ?? ''));
    if ($raw === '') {
        return null;
    }
    $date = DateTime::createFromFormat('Y-m-d', $raw);
    if (!$date || $date->format('Y-m-d') !== $raw) {
        throw new RuntimeException(L('payroll_employee_saved') === '' ? L('payroll_required') : L('payroll_invalid_amount'));
    }
    return $raw;
}

function payroll_input_year(string $key, int $default): int
{
    $raw = trim((string)($_POST[$key] ?? ''));
    if ($raw === '') {
        return $default;
    }
    $value = payroll_parse_number($raw);
    if ($value === null || $value < 2000 || $value > 2100) {
        throw new RuntimeException(L('payroll_invalid_year'));
    }
    return (int)$value;
}

function payroll_input_month(string $key, int $default): int
{
    $raw = trim((string)($_POST[$key] ?? ''));
    if ($raw === '') {
        return $default;
    }
    $value = payroll_parse_number($raw);
    if ($value === null || $value < 1 || $value > 12) {
        throw new RuntimeException(L('payroll_invalid_month'));
    }
    return (int)$value;
}

function payroll_exec(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function payroll_save_setting(PDO $pdo, string $key, string $value): void
{
    $actor = payroll_import_actor();
    $existing = payroll_exec($pdo, "SELECT id FROM " . table('payroll_settings') . " WHERE setting_key=? LIMIT 1", [$key])->fetchColumn();
    if ($existing) {
        payroll_exec($pdo, "UPDATE " . table('payroll_settings') . "
            SET setting_value=?, omitted=0, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=?
            WHERE id=?", [
            $value, $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], (int)$existing,
        ]);
        return;
    }
    payroll_exec($pdo, "INSERT INTO " . table('payroll_settings') . "
        (rguid, setting_key, setting_value, dtadded, addedbyid, addedby, addedbyname, addedbynamear,
         lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
        VALUES (?,?,?,NOW(),?,?,?,?,NOW(),?,?,?,?,0)", [
        bin2hex(random_bytes(16)), $key, $value,
        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
    ]);
}

function payroll_export_if_requested(string $base, string $title, array $headers, array $rows): void
{
    $format = strtolower((string)($_GET['export'] ?? ''));
    if (!in_array($format, ['csv', 'xlsx', 'docx', 'pdf'], true)) {
        return;
    }
    do_export($format, $base . '_' . date('Ymd'), $title, $headers, $rows);
}

function payroll_nav(string $active): void
{
    $items = [
        'dashboard' => ['payroll_dashboard', 'bi-speedometer2'],
        'runs' => ['payroll_runs', 'bi-table'],
        'employees' => ['payroll_employees', 'bi-people'],
        'types' => ['payroll_types', 'bi-list-check'],
        'import' => ['payroll_import', 'bi-filetype-csv'],
        'tax' => ['payroll_tax', 'bi-percent'],
        'insurance' => ['payroll_insurance', 'bi-shield-check'],
        'year_end' => ['payroll_year_end', 'bi-calendar-check'],
        'settings' => ['payroll_settings', 'bi-gear'],
    ];
    echo '<ul class="nav nav-pills flex-wrap gap-1 mb-4 payroll-no-print">';
    foreach ($items as $key => $item) {
        $class = $key === $active ? 'nav-link active' : 'nav-link';
        echo '<li class="nav-item"><a class="' . $class . '" href="?module=payroll&a=' . $key . '">'
            . '<i class="bi ' . $item[1] . ' me-1"></i>' . payroll_h(L($item[0])) . '</a></li>';
    }
    echo '</ul>';
}

function payroll_page_title(string $title, string $subtitle = ''): void
{
    echo '<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">';
    echo '<div><h3 class="mb-1"><i class="bi bi-cash-stack me-2"></i>' . payroll_h($title) . '</h3>';
    if ($subtitle !== '') {
        echo '<div class="text-muted">' . payroll_h($subtitle) . '</div>';
    }
    echo '</div></div>';
}

function payroll_export_buttons(string $baseUrl): void
{
    echo '<div class="btn-group btn-group-sm payroll-no-print">';
    foreach (['xlsx' => 'XLS', 'pdf' => 'PDF', 'docx' => 'Word', 'csv' => 'CSV'] as $format => $label) {
        echo '<a class="btn btn-outline-secondary" href="' . payroll_h($baseUrl . '&export=' . $format) . '">'
            . payroll_h(L('export')) . ' ' . $label . '</a>';
    }
    echo '</div>';
}

function payroll_run_totals_from_results(array $results): array
{
    $totals = [
        'employees' => count($results),
        'gross' => 0.0,
        'taxable' => 0.0,
        'tax' => 0.0,
        'insurance' => 0.0,
        'employer_insurance' => 0.0,
        'deductions' => 0.0,
        'net' => 0.0,
    ];
    foreach ($results as $row) {
        $totals['gross'] += (float)$row['gross_pay'];
        $totals['taxable'] += (float)$row['taxable_base'];
        $totals['tax'] += (float)$row['tax_amount'];
        $totals['insurance'] += (float)$row['insurance_amount'];
        $totals['employer_insurance'] += (float)$row['employer_insurance'];
        $totals['deductions'] += (float)$row['deductions_total'];
        $totals['net'] += (float)$row['net_salary'];
    }
    foreach (['gross', 'taxable', 'tax', 'insurance', 'employer_insurance', 'deductions', 'net'] as $key) {
        $totals[$key] = payroll_money($totals[$key]);
    }
    return $totals;
}

function payroll_insert_run_rows(PDO $pdo, int $runId, array $results): void
{
    foreach ($results as $result) {
        $details = [
            'service_years' => $result['service_years'],
            'tax_info' => $result['tax_info'],
            'insurance_info' => $result['insurance_info'],
            'settings' => $result['settings'],
        ];
        payroll_exec($pdo, "INSERT INTO " . table('payroll_run_items') . "
            (rguid, run_id, empid, empfullname, empfullnamear, basic_salary, gross_pay, taxable_base,
             tax_amount, insurance_base, insurance_amount, employer_insurance, deductions_total, net_salary,
             details_json, dtadded, omitted)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),0)", [
            bin2hex(random_bytes(16)), $runId, (int)$result['empid'], $result['empfullname'], $result['empfullnamear'],
            $result['basic_salary'], $result['gross_pay'], $result['taxable_base'], $result['tax_amount'],
            $result['insurance_base'], $result['insurance_amount'], $result['employer_insurance'],
            $result['deductions_total'], $result['net_salary'],
            json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $itemId = (int)$pdo->lastInsertId();
        foreach (array_merge($result['pay_lines'], $result['deduction_lines']) as $line) {
            payroll_exec($pdo, "INSERT INTO " . table('payroll_run_lines') . "
                (rguid, item_id, type_kind, type_code, name, name_ar, calc_method, rate, base_amount, amount,
                 taxable, insurable, sort_order, notes, dtadded, omitted)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),0)", [
                bin2hex(random_bytes(16)), $itemId, $line['kind'], $line['code'], $line['name'], $line['name_ar'],
                $line['calc_method'], $line['rate'], $line['base_amount'], $line['amount'],
                $line['before_tax'], $line['before_insurance'], $line['sort_order'], $line['source'] ?? null,
            ]);
        }
    }
}

function payroll_refresh_run_totals(PDO $pdo, int $runId): array
{
    $stmt = payroll_exec($pdo, "SELECT COUNT(*) AS employees,
            COALESCE(SUM(gross_pay),0) AS gross, COALESCE(SUM(taxable_base),0) AS taxable,
            COALESCE(SUM(tax_amount),0) AS tax, COALESCE(SUM(insurance_amount),0) AS insurance,
            COALESCE(SUM(employer_insurance),0) AS employer_insurance,
            COALESCE(SUM(deductions_total),0) AS deductions, COALESCE(SUM(net_salary),0) AS net
        FROM " . table('payroll_run_items') . " WHERE run_id=? AND omitted=0", [$runId]);
    $row = $stmt->fetch() ?: [];
    $actor = payroll_import_actor();
    payroll_exec($pdo, "UPDATE " . table('payroll_runs') . "
        SET employee_count=?, gross_total=?, taxable_total=?, tax_total=?, insurance_total=?,
            employer_insurance_total=?, deductions_total=?, net_total=?,
            lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=?
        WHERE id=?", [
        (int)($row['employees'] ?? 0), (float)($row['gross'] ?? 0), (float)($row['taxable'] ?? 0),
        (float)($row['tax'] ?? 0), (float)($row['insurance'] ?? 0), (float)($row['employer_insurance'] ?? 0),
        (float)($row['deductions'] ?? 0), (float)($row['net'] ?? 0),
        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], $runId,
    ]);
    return $row;
}

// -------------------------------------------------------------------------
// POST actions
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payroll_action'])) {
    if (!$canEdit) {
        set_flash('error', L('payroll_no_permission'));
        payroll_redirect('?module=payroll&a=' . urlencode($page));
    }
    $payrollAction = (string)$_POST['payroll_action'];
    $errorRedirect = '?module=payroll&a=' . urlencode($page);
    try {
        switch ($payrollAction) {
            case 'type_save': {
                $errorRedirect = '?module=payroll&a=types';
                $id = (int)($_POST['id'] ?? 0);
                $existing = $id > 0
                    ? fetchRow("SELECT * FROM " . table('payroll_types') . " WHERE id=? AND omitted=0 LIMIT 1", [$id])
                    : null;
                if ($id > 0 && !$existing) {
                    throw new RuntimeException(L('payroll_type_not_found'));
                }
                $isSystem = $existing && !empty($existing['is_system']);
                $kind = (string)($_POST['kind'] ?? ($existing['kind'] ?? ''));
                if (!in_array($kind, ['payability', 'deduction'], true)) {
                    throw new RuntimeException(L('payroll_required'));
                }
                $code = $isSystem ? (string)$existing['code'] : strtoupper(trim((string)($_POST['code'] ?? '')));
                if (!$isSystem && !preg_match('/^[A-Z0-9_\-]{2,50}$/', $code)) {
                    throw new RuntimeException(L('payroll_code_invalid'));
                }
                if (!$isSystem) {
                    $duplicate = fetchRow("SELECT id FROM " . table('payroll_types') . " WHERE UPPER(code)=? AND id<>? LIMIT 1", [$code, $id]);
                    if ($duplicate) {
                        throw new RuntimeException(L('payroll_code_exists'));
                    }
                }
                $name = trim((string)($_POST['name'] ?? ''));
                $nameAr = trim((string)($_POST['name_ar'] ?? ''));
                if ($name === '' || $nameAr === '') {
                    throw new RuntimeException(L('payroll_required'));
                }
                $description = trim((string)($_POST['description'] ?? ''));
                $descriptionAr = trim((string)($_POST['description_ar'] ?? ''));
                $beforeTax = isset($_POST['before_tax']) ? 1 : 0;
                $beforeInsurance = isset($_POST['before_insurance']) ? 1 : 0;
                $sortOrder = (int)($_POST['sort_order'] ?? 100);
                $enabled = isset($_POST['enabled']) ? 1 : 0;
                if ($existing && !empty($existing['is_basic'])) {
                    $enabled = 1;
                }
                $method = (string)($_POST['calc_method'] ?? ($existing['calc_method'] ?? 'fixed'));
                $allowedMethods = ['fixed', 'manual', 'percent_basic', 'percent_gross', 'percent_type', 'degree', 'seniority'];
                if ($isSystem) { $allowedMethods = array_merge($allowedMethods, ['tax', 'insurance']); }
                if (!in_array($method, $allowedMethods, true)) {
                    throw new RuntimeException(L('payroll_required'));
                }
                $rate = payroll_input_rate('rate', 0.0);
                $amount = payroll_input_money('amount', 0.0);
                if ($rate < 0 || $amount < 0) {
                    throw new RuntimeException(L('payroll_invalid_amount'));
                }
                $baseTypeId = (int)($_POST['base_type_id'] ?? 0);
                if ($baseTypeId > 0) {
                    if ($baseTypeId === $id) {
                        throw new RuntimeException(L('payroll_type_not_found'));
                    }
                    $baseType = fetchRow("SELECT id FROM " . table('payroll_types') . " WHERE id=? AND omitted=0 LIMIT 1", [$baseTypeId]);
                    if (!$baseType) {
                        throw new RuntimeException(L('payroll_type_not_found'));
                    }
                } else {
                    $baseTypeId = 0;
                }
                $maxAmount = payroll_input_money('max_amount', 0.0, true);
                $minAmount = payroll_input_money('min_amount', 0.0, true);
                if (($maxAmount !== null && $maxAmount < 0) || ($minAmount !== null && $minAmount < 0)) {
                    throw new RuntimeException(L('payroll_invalid_amount'));
                }
                if ($maxAmount !== null && $minAmount !== null && $maxAmount < $minAmount) {
                    throw new RuntimeException(L('payroll_invalid_amount'));
                }
                $beforeTax = isset($_POST['before_tax']) ? 1 : 0;
                $beforeInsurance = isset($_POST['before_insurance']) ? 1 : 0;
                $sortOrder = (int)($_POST['sort_order'] ?? 100);
                $enabled = isset($_POST['enabled']) ? 1 : 0;
                if ($existing && !empty($existing['is_basic'])) {
                    $enabled = 1;
                }
                $description = trim((string)($_POST['description'] ?? ''));
                $descriptionAr = trim((string)($_POST['description_ar'] ?? ''));
                $actor = payroll_import_actor();
                $pdo = db();

                // Validate degree amounts before writing anything.
                $degreeAmounts = [];
                if ($method === 'degree' && isset($_POST['degree_amount']) && is_array($_POST['degree_amount'])) {
                    foreach ($_POST['degree_amount'] as $degreeId => $raw) {
                        $raw = trim((string)$raw);
                        if ($raw === '') {
                            continue;
                        }
                        $parsed = payroll_parse_number($raw);
                        if ($parsed === null || $parsed < 0) {
                            throw new RuntimeException(L('payroll_invalid_amount'));
                        }
                        $degreeAmounts[(int)$degreeId] = payroll_money($parsed);
                    }
                }

                $pdo->beginTransaction();
                try {
                    if ($existing) {
                        payroll_exec($pdo, "UPDATE " . table('payroll_types') . "
                            SET kind=?, code=?, name=?, name_ar=?, description=?, description_ar=?,
                                calc_method=?, rate=?, amount=?, base_type_id=?, max_amount=?, min_amount=?,
                                before_tax=?, before_insurance=?, sort_order=?, enabled=?,
                                lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=?
                            WHERE id=?", [
                            $kind, $code, $name, $nameAr, $description, $descriptionAr,
                            $method, $rate, $amount, $baseTypeId ?: null, $maxAmount, $minAmount,
                            $beforeTax, $beforeInsurance, $sortOrder, $enabled,
                            $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], $id,
                        ]);
                        $savedId = $id;
                    } else {
                        payroll_exec($pdo, "INSERT INTO " . table('payroll_types') . "
                            (rguid, kind, code, name, name_ar, description, description_ar, calc_method, rate, amount,
                             base_type_id, max_amount, min_amount, before_tax, before_insurance, is_basic, is_system,
                             sort_order, enabled, dtadded, addedbyid, addedby, addedbyname, addedbynamear,
                             lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,0,?,?,NOW(),?,?,?,?,NOW(),?,?,?,?,0)", [
                            bin2hex(random_bytes(16)), $kind, $code, $name, $nameAr, $description, $descriptionAr,
                            $method, $rate, $amount, $baseTypeId ?: null, $maxAmount, $minAmount,
                            $beforeTax, $beforeInsurance, $sortOrder, $enabled,
                            $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                            $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                        ]);
                        $savedId = (int)$pdo->lastInsertId();
                    }

                    if ($method === 'degree') {
                        $degrees = fetchAll("SELECT id FROM " . table('empdegrees') . " WHERE omitted=0");
                        payroll_exec($pdo, "UPDATE " . table('payroll_type_degrees') . " SET omitted=1 WHERE type_id=?", [$savedId]);
                        foreach ($degrees as $degree) {
                            $degreeId = (int)$degree['id'];
                            if (!array_key_exists($degreeId, $degreeAmounts)) {
                                continue;
                            }
                            $existingDegree = payroll_exec($pdo, "SELECT id FROM " . table('payroll_type_degrees') . "
                                WHERE type_id=? AND degree_id=? ORDER BY omitted ASC, id DESC LIMIT 1", [$savedId, $degreeId])->fetchColumn();
                            if ($existingDegree) {
                                payroll_exec($pdo, "UPDATE " . table('payroll_type_degrees') . "
                                    SET amount=?, omitted=0, lastedit=NOW() WHERE id=?", [$degreeAmounts[$degreeId], (int)$existingDegree]);
                            } else {
                                payroll_exec($pdo, "INSERT INTO " . table('payroll_type_degrees') . "
                                    (rguid, type_id, degree_id, amount, dtadded, omitted)
                                    VALUES (?,?,?,?,NOW(),0)", [
                                    bin2hex(random_bytes(16)), $savedId, $degreeId, $degreeAmounts[$degreeId],
                                ]);
                            }
                        }
                    } else {
                        payroll_exec($pdo, "UPDATE " . table('payroll_type_degrees') . " SET omitted=1 WHERE type_id=?", [$savedId]);
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                set_flash('success', $existing ? L('payroll_type_updated') : L('payroll_type_added'));
                payroll_redirect('?module=payroll&a=type_edit&id=' . $savedId);
            }

            case 'type_toggle': {
                $errorRedirect = '?module=payroll&a=types';
                $id = (int)($_POST['id'] ?? 0);
                $type = fetchRow("SELECT * FROM " . table('payroll_types') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
                if (!$type) {
                    throw new RuntimeException(L('payroll_type_not_found'));
                }
                if (!empty($type['is_basic']) && (int)$type['enabled'] === 1) {
                    throw new RuntimeException(L('payroll_basic_cannot_disable'));
                }
                $actor = payroll_import_actor();
                $newEnabled = (int)$type['enabled'] ? 0 : 1;
                payroll_exec(db(), "UPDATE " . table('payroll_types') . "
                    SET enabled=?, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?", [
                    $newEnabled, $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], $id,
                ]);
                set_flash('success', $newEnabled ? L('payroll_type_enabled_msg') : L('payroll_type_disabled_msg'));
                payroll_redirect('?module=payroll&a=types');
            }

            case 'tax_add': {
                $errorRedirect = '?module=payroll&a=tax';
                $year = payroll_input_year('tax_year', (int)date('Y') + 1);
                $existing = fetchRow("SELECT * FROM " . table('payroll_tax_years') . " WHERE tax_year=? LIMIT 1", [$year]);
                if ($existing && (int)$existing['omitted'] === 0) {
                    throw new RuntimeException(L('payroll_tax_year_exists'));
                }
                $previous = fetchRow("SELECT * FROM " . table('payroll_tax_years') . " WHERE tax_year<? AND omitted=0 ORDER BY tax_year DESC LIMIT 1", [$year]);
                $actor = payroll_import_actor();
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    if ($existing) {
                        payroll_exec($pdo, "UPDATE " . table('payroll_tax_years') . "
                            SET omitted=0, enabled=1, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=?
                            WHERE id=?", [
                            $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], (int)$existing['id'],
                        ]);
                    } else {
                        payroll_exec($pdo, "INSERT INTO " . table('payroll_tax_years') . "
                            (rguid, tax_year, personal_exemption, round_to, enabled, notes, dtadded,
                             addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
                            VALUES (?,?,?,?,1,?,NOW(),?,?,?,?,NOW(),?,?,?,?,0)", [
                            bin2hex(random_bytes(16)), $year,
                            (float)($previous['personal_exemption'] ?? 20000), (float)($previous['round_to'] ?? 10),
                            L('payroll_tax_high_income_hint'),
                            $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                            $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                        ]);
                    }
                    $bracketCount = (int)payroll_exec($pdo, "SELECT COUNT(*) FROM " . table('payroll_tax_brackets') . " WHERE tax_year=? AND omitted=0", [$year])->fetchColumn();
                    if ($bracketCount === 0 && $previous) {
                        $prevBrackets = fetchAll("SELECT * FROM " . table('payroll_tax_brackets') . " WHERE tax_year=? AND omitted=0 ORDER BY schedule_min, bracket_order, id", [(int)$previous['tax_year']]);
                        foreach ($prevBrackets as $bracket) {
                            payroll_exec($pdo, "INSERT INTO " . table('payroll_tax_brackets') . "
                                (rguid, tax_year, schedule_min, schedule_max, bracket_order, from_amount, to_amount, rate,
                                 notes, dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
                                VALUES (?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,NOW(),?,?,?,?,0)", [
                                bin2hex(random_bytes(16)), $year, $bracket['schedule_min'], $bracket['schedule_max'],
                                $bracket['bracket_order'], $bracket['from_amount'], $bracket['to_amount'], $bracket['rate'],
                                $bracket['notes'],
                                $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                                $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                            ]);
                        }
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                set_flash('success', L('payroll_tax_year_added'));
                payroll_redirect('?module=payroll&a=tax_edit&year=' . $year);
            }

            case 'tax_save': {
                $year = (int)($_POST['tax_year'] ?? 0);
                $errorRedirect = '?module=payroll&a=tax_edit&year=' . $year;
                $taxYear = fetchRow("SELECT * FROM " . table('payroll_tax_years') . " WHERE tax_year=? AND omitted=0 LIMIT 1", [$year]);
                if (!$taxYear) {
                    throw new RuntimeException(L('payroll_tax_year_required'));
                }
                $exemption = payroll_input_money('personal_exemption', 20000.0);
                $roundTo = payroll_input_money('round_to', 10.0);
                if ($exemption < 0 || $roundTo <= 0) {
                    throw new RuntimeException(L('payroll_invalid_amount'));
                }
                $posted = $_POST['brackets'] ?? [];
                if (!is_array($posted)) {
                    $posted = [];
                }
                $clean = [];
                foreach ($posted as $row) {
                    if (!is_array($row) || !empty($row['delete'])) {
                        continue;
                    }
                    $rawScheduleMin = trim((string)($row['schedule_min'] ?? ''));
                    $rawScheduleMax = trim((string)($row['schedule_max'] ?? ''));
                    $rawFrom = trim((string)($row['from_amount'] ?? ''));
                    $rawTo = trim((string)($row['to_amount'] ?? ''));
                    $rawRate = trim((string)($row['rate'] ?? ''));
                    if ($rawScheduleMin === '' && $rawScheduleMax === '' && $rawFrom === '' && $rawTo === '' && $rawRate === '') {
                        continue;
                    }
                    $scheduleMin = payroll_parse_number($rawScheduleMin);
                    $scheduleMax = $rawScheduleMax === '' ? null : payroll_parse_number($rawScheduleMax);
                    $from = payroll_parse_number($rawFrom);
                    $to = $rawTo === '' ? null : payroll_parse_number($rawTo);
                    $rate = payroll_parse_number($rawRate);
                    if ($scheduleMin === null || $scheduleMin < 0 || $from === null || $from < 0
                        || $rate === null || $rate < 0 || $rate > 100
                        || ($scheduleMax !== null && $scheduleMax <= $scheduleMin)
                        || ($to !== null && $to <= $from)) {
                        throw new RuntimeException(L('payroll_invalid_bracket'));
                    }
                    $clean[] = [
                        'schedule_min' => $scheduleMin,
                        'schedule_max' => $scheduleMax,
                        'bracket_order' => (int)($row['bracket_order'] ?? 1),
                        'from_amount' => $from,
                        'to_amount' => $to,
                        'rate' => $rate,
                    ];
                }
                if (empty($clean)) {
                    throw new RuntimeException(L('payroll_bracket_required'));
                }
                usort($clean, fn($a, $b) => [$a['schedule_min'], $a['bracket_order']] <=> [$b['schedule_min'], $b['bracket_order']]);
                $actor = payroll_import_actor();
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    payroll_exec($pdo, "UPDATE " . table('payroll_tax_years') . "
                        SET personal_exemption=?, round_to=?, enabled=?, notes=?, lastedit=NOW(),
                            editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?", [
                        $exemption, $roundTo, isset($_POST['enabled']) ? 1 : 0, trim((string)($_POST['notes'] ?? '')),
                        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], (int)$taxYear['id'],
                    ]);
                    payroll_exec($pdo, "UPDATE " . table('payroll_tax_brackets') . " SET omitted=1 WHERE tax_year=?", [$year]);
                    foreach ($clean as $bracket) {
                        payroll_exec($pdo, "INSERT INTO " . table('payroll_tax_brackets') . "
                            (rguid, tax_year, schedule_min, schedule_max, bracket_order, from_amount, to_amount, rate,
                             notes, dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
                            VALUES (?,?,?,?,?,?,?,?,NULL,NOW(),?,?,?,?,NOW(),?,?,?,?,0)", [
                            bin2hex(random_bytes(16)), $year, $bracket['schedule_min'], $bracket['schedule_max'],
                            $bracket['bracket_order'], $bracket['from_amount'], $bracket['to_amount'], $bracket['rate'],
                            $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                            $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                        ]);
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                set_flash('success', L('payroll_tax_saved'));
                payroll_redirect('?module=payroll&a=tax_edit&year=' . $year);
            }

            case 'tax_toggle': {
                $errorRedirect = '?module=payroll&a=tax';
                $id = (int)($_POST['id'] ?? 0);
                $year = fetchRow("SELECT * FROM " . table('payroll_tax_years') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
                if (!$year) {
                    throw new RuntimeException(L('payroll_tax_year_required'));
                }
                $actor = payroll_import_actor();
                $newEnabled = (int)$year['enabled'] ? 0 : 1;
                payroll_exec(db(), "UPDATE " . table('payroll_tax_years') . "
                    SET enabled=?, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?", [
                    $newEnabled, $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], $id,
                ]);
                set_flash('success', $newEnabled ? L('payroll_tax_year_enabled_msg') : L('payroll_tax_year_disabled_msg'));
                payroll_redirect('?module=payroll&a=tax');
            }

            case 'insurance_add': {
                $errorRedirect = '?module=payroll&a=insurance';
                $year = payroll_input_year('insurance_year', (int)date('Y') + 1);
                $existing = fetchRow("SELECT * FROM " . table('payroll_insurance_rules') . " WHERE insurance_year=? LIMIT 1", [$year]);
                if ($existing && (int)$existing['omitted'] === 0) {
                    throw new RuntimeException(L('payroll_insurance_exists'));
                }
                $previous = fetchRow("SELECT * FROM " . table('payroll_insurance_rules') . " WHERE insurance_year<? AND omitted=0 ORDER BY insurance_year DESC LIMIT 1", [$year]);
                $actor = payroll_import_actor();
                if ($existing) {
                    payroll_exec(db(), "UPDATE " . table('payroll_insurance_rules') . "
                        SET omitted=0, enabled=1, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=?
                        WHERE id=?", [
                        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], (int)$existing['id'],
                    ]);
                } else {
                    payroll_exec(db(), "INSERT INTO " . table('payroll_insurance_rules') . "
                        (rguid, insurance_year, employee_rate, employer_rate, min_base, max_base, enabled, notes,
                         dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
                        VALUES (?,?,?,?,?,?,1,?,NOW(),?,?,?,?,NOW(),?,?,?,?,0)", [
                        bin2hex(random_bytes(16)), $year,
                        (float)($previous['employee_rate'] ?? 11), (float)($previous['employer_rate'] ?? 18.75),
                        (float)($previous['min_base'] ?? 0), $previous['max_base'] ?? null,
                        L('payroll_insurance_limit_hint'),
                        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                    ]);
                }
                set_flash('success', L('payroll_insurance_added'));
                payroll_redirect('?module=payroll&a=insurance');
            }

            case 'insurance_save': {
                $id = (int)($_POST['id'] ?? 0);
                $errorRedirect = '?module=payroll&a=insurance_edit&id=' . $id;
                $rule = fetchRow("SELECT * FROM " . table('payroll_insurance_rules') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
                if (!$rule) {
                    throw new RuntimeException(L('payroll_required'));
                }
                $employeeRate = payroll_input_rate('employee_rate', 0.0);
                $employerRate = payroll_input_rate('employer_rate', 0.0);
                $minBase = payroll_input_money('min_base', 0.0);
                $maxBase = payroll_input_money('max_base', 0.0, true);
                if ($minBase < 0 || ($maxBase !== null && ($maxBase < 0 || $maxBase < $minBase))) {
                    throw new RuntimeException(L('payroll_invalid_amount'));
                }
                $actor = payroll_import_actor();
                payroll_exec(db(), "UPDATE " . table('payroll_insurance_rules') . "
                    SET employee_rate=?, employer_rate=?, min_base=?, max_base=?, enabled=?, notes=?,
                        lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?", [
                    $employeeRate, $employerRate, $minBase, $maxBase, isset($_POST['enabled']) ? 1 : 0,
                    trim((string)($_POST['notes'] ?? '')),
                    $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], $id,
                ]);
                set_flash('success', L('payroll_insurance_saved'));
                payroll_redirect('?module=payroll&a=insurance');
            }

            case 'insurance_toggle': {
                $errorRedirect = '?module=payroll&a=insurance';
                $id = (int)($_POST['id'] ?? 0);
                $rule = fetchRow("SELECT * FROM " . table('payroll_insurance_rules') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
                if (!$rule) {
                    throw new RuntimeException(L('payroll_required'));
                }
                $actor = payroll_import_actor();
                $newEnabled = (int)$rule['enabled'] ? 0 : 1;
                payroll_exec(db(), "UPDATE " . table('payroll_insurance_rules') . "
                    SET enabled=?, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?", [
                    $newEnabled, $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], $id,
                ]);
                set_flash('success', $newEnabled ? L('payroll_insurance_enabled_msg') : L('payroll_insurance_disabled_msg'));
                payroll_redirect('?module=payroll&a=insurance');
            }

            case 'settings_save': {
                $errorRedirect = '?module=payroll&a=settings';
                $pdo = db();
                payroll_save_setting($pdo, 'tax_enabled', isset($_POST['tax_enabled']) ? '1' : '0');
                payroll_save_setting($pdo, 'insurance_enabled', isset($_POST['insurance_enabled']) ? '1' : '0');
                $newCurrency = trim((string)($_POST['currency'] ?? 'EGP'));
                if ($newCurrency === '') {
                    $newCurrency = 'EGP';
                }
                payroll_save_setting($pdo, 'currency', substr($newCurrency, 0, 10));
                set_flash('success', L('payroll_settings_saved'));
                payroll_redirect('?module=payroll&a=settings');
            }

            case 'employee_save': {
                $empid = (int)($_POST['empid'] ?? 0);
                $errorRedirect = '?module=payroll&a=employee&id=' . $empid;
                $employee = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [$empid]);
                if (!$employee) {
                    throw new RuntimeException(L('payroll_employee_not_found'));
                }
                $basic = payroll_input_money('basic_salary', 0.0);
                $override = payroll_input_money('insurance_base_override', 0.0, true);
                if ($basic < 0 || ($override !== null && $override < 0)) {
                    throw new RuntimeException(L('payroll_invalid_amount'));
                }
                $types = fetchAll("SELECT * FROM " . table('payroll_types') . " WHERE omitted=0 AND is_basic=0 ORDER BY sort_order, id");
                $assignments = [];
                foreach ($types as $type) {
                    $tid = (int)$type['id'];
                    $isSystemType = !empty($type['is_system']);
                    $checked = !empty($_POST['assign'][$tid]);
                    $existing = fetchRow("SELECT * FROM " . table('payroll_employee_types') . "
                        WHERE empid=? AND type_id=? ORDER BY omitted ASC, id DESC LIMIT 1", [$empid, $tid]);
                    if (!$checked) {
                        if ($isSystemType) {
                            // System types apply by default; an explicit disabled row turns them off.
                            if ($existing) {
                                $assignments[] = ['type_id' => $tid, 'delete' => false, 'existing' => $existing,
                                    'amount' => $existing['amount_override'], 'rate' => $existing['rate_override'],
                                    'from' => $existing['effective_from'], 'to' => $existing['effective_to'],
                                    'disable' => true];
                            } else {
                                $assignments[] = ['type_id' => $tid, 'delete' => false, 'existing' => null,
                                    'amount' => null, 'rate' => null, 'from' => null, 'to' => null,
                                    'disable' => true];
                            }
                        } elseif ($existing && (int)$existing['omitted'] === 0) {
                            $assignments[] = ['type_id' => $tid, 'delete' => true, 'existing' => $existing];
                        }
                        continue;
                    }
                    $amountRaw = trim((string)($_POST['amount_override'][$tid] ?? ''));
                    $rateRaw = trim((string)($_POST['rate_override'][$tid] ?? ''));
                    $amountOverride = null;
                    $rateOverride = null;
                    if ($amountRaw !== '') {
                        $amountOverride = payroll_parse_number($amountRaw);
                        if ($amountOverride === null || $amountOverride < 0) {
                            throw new RuntimeException(L('payroll_invalid_amount'));
                        }
                    }
                    if ($rateRaw !== '') {
                        $rateOverride = payroll_parse_number($rateRaw);
                        if ($rateOverride === null || $rateOverride < 0 || $rateOverride > 100) {
                            throw new RuntimeException(L('payroll_invalid_rate'));
                        }
                    }
                    $from = trim((string)($_POST['effective_from'][$tid] ?? ''));
                    $to = trim((string)($_POST['effective_to'][$tid] ?? ''));
                    foreach ([$from, $to] as $dateValue) {
                        if ($dateValue !== '') {
                            $date = DateTime::createFromFormat('Y-m-d', $dateValue);
                            if (!$date || $date->format('Y-m-d') !== $dateValue) {
                                throw new RuntimeException(L('payroll_invalid_amount'));
                            }
                        }
                    }
                    if ($from !== '' && $to !== '' && $from > $to) {
                        throw new RuntimeException(L('payroll_effective_bad'));
                    }
                    $assignments[] = [
                        'type_id' => $tid,
                        'delete' => false,
                        'existing' => $existing ?: null,
                        'amount' => $amountOverride === null ? null : payroll_money($amountOverride),
                        'rate' => $rateOverride,
                        'from' => $from !== '' ? $from : null,
                        'to' => $to !== '' ? $to : null,
                    ];
                }

                $actor = payroll_import_actor();
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $existingSettings = payroll_exec($pdo, "SELECT * FROM " . table('payroll_employee_settings') . "
                        WHERE empid=? ORDER BY omitted ASC, id DESC LIMIT 1", [$empid])->fetch();
                    if ($existingSettings) {
                        payroll_exec($pdo, "UPDATE " . table('payroll_employee_settings') . "
                            SET basic_salary=?, insurance_base_override=?, tax_exempt=?, insurance_exempt=?, notes=?,
                                omitted=0, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=?
                            WHERE id=?", [
                            $basic, $override, isset($_POST['tax_exempt']) ? 1 : 0, isset($_POST['insurance_exempt']) ? 1 : 0,
                            trim((string)($_POST['notes'] ?? '')),
                            $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], (int)$existingSettings['id'],
                        ]);
                    } else {
                        payroll_exec($pdo, "INSERT INTO " . table('payroll_employee_settings') . "
                            (rguid, empid, basic_salary, insurance_base_override, tax_exempt, insurance_exempt, notes,
                             dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
                            VALUES (?,?,?,?,?,?,?,NOW(),?,?,?,?,NOW(),?,?,?,?,0)", [
                            bin2hex(random_bytes(16)), $empid, $basic, $override,
                            isset($_POST['tax_exempt']) ? 1 : 0, isset($_POST['insurance_exempt']) ? 1 : 0,
                            trim((string)($_POST['notes'] ?? '')),
                            $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                            $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                        ]);
                    }

                    foreach ($assignments as $assignment) {
                        $tid = (int)$assignment['type_id'];
                        $existing = $assignment['existing'];
                        if (!empty($assignment['delete'])) {
                            payroll_exec($pdo, "UPDATE " . table('payroll_employee_types') . "
                                SET omitted=1, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=?
                                WHERE id=?", [
                                $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], (int)$existing['id'],
                            ]);
                            continue;
                        }
                        $enabledFlag = empty($assignment['disable']) ? 1 : 0;
                        if ($existing) {
                            payroll_exec($pdo, "UPDATE " . table('payroll_employee_types') . "
                                SET amount_override=?, rate_override=?, effective_from=?, effective_to=?,
                                    enabled=?, omitted=0, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=?
                                WHERE id=?", [
                                $assignment['amount'], $assignment['rate'], $assignment['from'], $assignment['to'],
                                $enabledFlag,
                                $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], (int)$existing['id'],
                            ]);
                        } else {
                            payroll_exec($pdo, "INSERT INTO " . table('payroll_employee_types') . "
                                (rguid, empid, type_id, amount_override, rate_override, effective_from, effective_to,
                                 enabled, dtadded, addedbyid, addedby, addedbyname, addedbynamear,
                                 lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
                                VALUES (?,?,?,?,?,?,?, ?,NOW(),?,?,?,?,NOW(),?,?,?,?,0)", [
                                bin2hex(random_bytes(16)), $empid, $tid, $assignment['amount'], $assignment['rate'],
                                $assignment['from'], $assignment['to'], $enabledFlag,
                                $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                                $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                            ]);
                        }
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                set_flash('success', L('payroll_employee_saved'));
                payroll_redirect('?module=payroll&a=employee&id=' . $empid);
            }

            case 'run_create': {
                $errorRedirect = '?module=payroll&a=runs';
                $year = payroll_input_year('payroll_year', (int)date('Y'));
                $month = payroll_input_month('payroll_month', (int)date('n'));
                $existing = fetchRow("SELECT * FROM " . table('payroll_runs') . "
                    WHERE payroll_year=? AND payroll_month=? AND omitted=0 LIMIT 1", [$year, $month]);
                if ($existing) {
                    set_flash('error', L('payroll_run_exists'));
                    payroll_redirect('?module=payroll&a=run&id=' . (int)$existing['id']);
                }
                $computed = payroll_compute_period($year, $month);
                if (empty($computed['results'])) {
                    throw new RuntimeException(L('payroll_run_no_employees'));
                }
                $actor = payroll_import_actor();
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    payroll_exec($pdo, "INSERT INTO " . table('payroll_runs') . "
                        (rguid, payroll_year, payroll_month, period_start, period_end, status,
                         dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
                        VALUES (?,?,?,?,?,'draft',NOW(),?,?,?,?,NOW(),?,?,?,?,0)", [
                        bin2hex(random_bytes(16)), $year, $month, $computed['context']['period_start'], $computed['context']['period_end'],
                        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
                    ]);
                    $runId = (int)$pdo->lastInsertId();
                    payroll_insert_run_rows($pdo, $runId, $computed['results']);
                    payroll_refresh_run_totals($pdo, $runId);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                set_flash('success', L('payroll_run_created'));
                payroll_redirect('?module=payroll&a=run&id=' . $runId);
            }

            case 'run_recalculate': {
                $id = (int)($_POST['id'] ?? 0);
                $errorRedirect = '?module=payroll&a=run&id=' . $id;
                $run = fetchRow("SELECT * FROM " . table('payroll_runs') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
                if (!$run) {
                    throw new RuntimeException(L('payroll_run_not_found'));
                }
                if ($run['status'] !== 'draft') {
                    throw new RuntimeException(L('payroll_run_locked'));
                }
                $computed = payroll_compute_period((int)$run['payroll_year'], (int)$run['payroll_month']);
                if (empty($computed['results'])) {
                    throw new RuntimeException(L('payroll_run_no_employees'));
                }
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    payroll_exec($pdo, "UPDATE " . table('payroll_run_lines') . " l
                        INNER JOIN " . table('payroll_run_items') . " i ON i.id=l.item_id
                        SET l.omitted=1 WHERE i.run_id=?", [$id]);
                    payroll_exec($pdo, "UPDATE " . table('payroll_run_items') . " SET omitted=1 WHERE run_id=?", [$id]);
                    payroll_insert_run_rows($pdo, $id, $computed['results']);
                    payroll_refresh_run_totals($pdo, $id);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                set_flash('success', L('payroll_run_recalculated_msg'));
                payroll_redirect('?module=payroll&a=run&id=' . $id);
            }

            case 'run_finalize': {
                $id = (int)($_POST['id'] ?? 0);
                $errorRedirect = '?module=payroll&a=run&id=' . $id;
                $run = fetchRow("SELECT * FROM " . table('payroll_runs') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
                if (!$run) {
                    throw new RuntimeException(L('payroll_run_not_found'));
                }
                if ($run['status'] !== 'draft') {
                    throw new RuntimeException(L('payroll_run_locked'));
                }
                $count = (int)fetchRow("SELECT COUNT(*) AS c FROM " . table('payroll_run_items') . " WHERE run_id=? AND omitted=0", [$id])['c'];
                if ($count < 1) {
                    throw new RuntimeException(L('payroll_run_cannot_finalize'));
                }
                $actor = payroll_import_actor();
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    payroll_refresh_run_totals($pdo, $id);
                    payroll_exec($pdo, "UPDATE " . table('payroll_runs') . "
                        SET status='final', finalized_at=NOW(), finalizedbyid=?, finalizedby=?,
                            lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?", [
                        $actor['id'], $actor['username'], $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], $id,
                    ]);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                set_flash('success', L('payroll_run_finalized_msg'));
                payroll_redirect('?module=payroll&a=run&id=' . $id);
            }

            case 'run_reopen': {
                $id = (int)($_POST['id'] ?? 0);
                $errorRedirect = '?module=payroll&a=run&id=' . $id;
                $run = fetchRow("SELECT * FROM " . table('payroll_runs') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
                if (!$run) {
                    throw new RuntimeException(L('payroll_run_not_found'));
                }
                if ($run['status'] !== 'final') {
                    throw new RuntimeException(L('payroll_run_draft_only'));
                }
                $actor = payroll_import_actor();
                payroll_exec(db(), "UPDATE " . table('payroll_runs') . "
                    SET status='draft', finalized_at=NULL, finalizedbyid=NULL, finalizedby=NULL,
                        lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?", [
                    $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], $id,
                ]);
                set_flash('success', L('payroll_run_reopened_msg'));
                payroll_redirect('?module=payroll&a=run&id=' . $id);
            }

            case 'run_omit': {
                $id = (int)($_POST['id'] ?? 0);
                $errorRedirect = '?module=payroll&a=runs';
                $run = fetchRow("SELECT * FROM " . table('payroll_runs') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
                if (!$run) {
                    throw new RuntimeException(L('payroll_run_not_found'));
                }
                if ($run['status'] !== 'draft') {
                    throw new RuntimeException(L('payroll_run_draft_only'));
                }
                $actor = payroll_import_actor();
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    payroll_exec($pdo, "UPDATE " . table('payroll_run_lines') . " l
                        INNER JOIN " . table('payroll_run_items') . " i ON i.id=l.item_id
                        SET l.omitted=1 WHERE i.run_id=?", [$id]);
                    payroll_exec($pdo, "UPDATE " . table('payroll_run_items') . " SET omitted=1 WHERE run_id=?", [$id]);
                    payroll_exec($pdo, "UPDATE " . table('payroll_runs') . "
                        SET omitted=1, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=? WHERE id=?", [
                        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], $id,
                    ]);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                set_flash('success', L('payroll_run_omitted_msg'));
                payroll_redirect('?module=payroll&a=runs');
            }

            case 'import_upload': {
                $kind = (string)($_POST['kind'] ?? 'basic');
                $kind = in_array($kind, ['basic', 'values'], true) ? $kind : 'basic';
                $errorRedirect = '?module=payroll&a=import&kind=' . $kind;
                $file = $_FILES['csv'] ?? null;
                if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    throw new RuntimeException(L('payroll_import_file_required'));
                }
                if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException(L('payroll_import_upload_error'));
                }
                if ((int)$file['size'] > 10 * 1024 * 1024) {
                    throw new RuntimeException(L('payroll_import_too_large'));
                }
                $originalName = (string)($file['name'] ?? 'upload.csv');
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($extension, ['csv', 'txt'], true)) {
                    throw new RuntimeException(L('payroll_import_invalid_extension'));
                }
                if (!is_uploaded_file($file['tmp_name'])) {
                    throw new RuntimeException(L('payroll_import_upload_error'));
                }
                $year = payroll_input_year('payroll_year', (int)date('Y'));
                $month = payroll_input_month('payroll_month', (int)date('n'));
                $dryRun = !empty($_POST['dry_run']);
                $note = sprintf(L('payroll_import_note'), $originalName);
                if ($kind === 'basic') {
                    $result = payroll_import_basic($file['tmp_name'], $year, $month, $dryRun, $note);
                } else {
                    $result = payroll_import_values($file['tmp_name'], $year, $month, !empty($_POST['create_missing']), $dryRun, $note);
                }
                $result['file_name'] = $originalName;
                $result['stored'] = false;
                if (!$dryRun && (int)$result['imported'] > 0) {
                    $uploadDir = __DIR__ . '/../../uploads/payroll';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0775, true);
                    }
                    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName);
                    $target = $uploadDir . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $safeName;
                    if (@move_uploaded_file($file['tmp_name'], $target)) {
                        $result['stored'] = true;
                    }
                }
                $_SESSION[UHR_SESSION_KEY]['payroll_import_result'] = $result;
                if ($dryRun) {
                    if (empty($result['errors'])) {
                        set_flash('info', sprintf(L('payroll_import_validation_success'), (int)$result['imported']));
                    } else {
                        set_flash('warning', L('payroll_import_validation_errors'));
                    }
                } elseif ((int)$result['imported'] > 0) {
                    set_flash(empty($result['errors']) ? 'success' : 'warning', sprintf(L('payroll_import_success'), (int)$result['imported']));
                } else {
                    set_flash('error', L('payroll_import_failed'));
                }
                payroll_redirect('?module=payroll&a=import&kind=' . $kind);
            }

            default:
                throw new RuntimeException(L('payroll_no_permission'));
        }
    } catch (Throwable $e) {
        set_flash('error', $e->getMessage());
        payroll_redirect($errorRedirect);

// =========================================================================
    }
}

// Page functions
// =========================================================================

function payroll_page_dashboard(): void
{
    global $isAr, $currency;
    $types = fetchAll("SELECT * FROM " . table('payroll_types') . " WHERE omitted=0 ORDER BY sort_order, id");
    $payabilityCount = count(array_filter($types, fn($t) => $t['kind'] === 'payability'));
    $deductionCount = count(array_filter($types, fn($t) => $t['kind'] === 'deduction'));
    $activeTypes = count(array_filter($types, fn($t) => (int)$t['enabled'] === 1));
    $employeesWithSalary = (int)(fetchRow("SELECT COUNT(*) AS c FROM " . table('payroll_employee_settings') . " WHERE omitted=0 AND basic_salary>0")['c'] ?? 0);
    $latestRun = fetchRow("SELECT * FROM " . table('payroll_runs') . " WHERE omitted=0 ORDER BY payroll_year DESC, payroll_month DESC, id DESC LIMIT 1");
    $ytd = fetchRow("SELECT COALESCE(SUM(gross_total),0) g, COALESCE(SUM(tax_total),0) t, COALESCE(SUM(net_total),0) n FROM " . table('payroll_runs') . " WHERE omitted=0 AND status='final' AND payroll_year=?", [(int)date('Y')]);
    $currentTaxYear = payroll_setting('current_tax_year', (int)date('Y'));
    $taxYearRow = fetchRow("SELECT * FROM " . table('payroll_tax_years') . " WHERE tax_year=? AND omitted=0 LIMIT 1", [(int)$currentTaxYear]);
    if (!$taxYearRow) {
        $taxYearRow = fetchRow("SELECT * FROM " . table('payroll_tax_years') . " WHERE omitted=0 ORDER BY tax_year DESC LIMIT 1");
    }
    $currentInsuranceYear = payroll_setting('current_insurance_year', (int)date('Y'));
    $insuranceRow = fetchRow("SELECT * FROM " . table('payroll_insurance_rules') . " WHERE insurance_year=? AND omitted=0 LIMIT 1", [(int)$currentInsuranceYear]);
    if (!$insuranceRow) {
        $insuranceRow = fetchRow("SELECT * FROM " . table('payroll_insurance_rules') . " WHERE omitted=0 ORDER BY insurance_year DESC LIMIT 1");
    }
    $recentRuns = fetchAll("SELECT * FROM " . table('payroll_runs') . " WHERE omitted=0 ORDER BY payroll_year DESC, payroll_month DESC, id DESC LIMIT 8");

    payroll_page_title(L('payroll_dashboard'), L('payroll_dashboard_subtitle'));
    echo '<div class="row g-3 mb-4">';
    $cards = [
        [L('payroll_active_types'), $activeTypes, 'bi-list-check', 'primary'],
        [L('payroll_payability_types'), $payabilityCount, 'bi-plus-circle', 'success'],
        [L('payroll_deduction_types'), $deductionCount, 'bi-dash-circle', 'danger'],
        [L('payroll_employees_with_salary'), $employeesWithSalary, 'bi-people', 'info'],
    ];
    foreach ($cards as $card) {
        echo '<div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body d-flex align-items-center gap-3">'
            . '<div class="rounded-circle bg-' . $card[3] . ' bg-opacity-10 text-' . $card[3] . ' p-3"><i class="bi ' . $card[2] . ' fs-4"></i></div>'
            . '<div><div class="text-muted small">' . payroll_h($card[0]) . '</div><div class="fs-4 fw-bold">' . (int)$card[1] . '</div></div>'
            . '</div></div></div>';
    }
    echo '</div>';

    echo '<div class="row g-3 mb-4">';
    echo '<div class="col-md-6"><div class="card h-100"><div class="card-body">';
    echo '<h6 class="text-muted">' . payroll_h(L('payroll_latest_run')) . '</h6>';
    if ($latestRun) {
        echo '<div class="fs-5 fw-bold">' . payroll_h(L('payroll_month_' . (int)$latestRun['payroll_month'])) . ' ' . (int)$latestRun['payroll_year'] . '</div>';
        echo '<div class="text-muted">' . payroll_h(L('payroll_employee_count')) . ': ' . (int)$latestRun['employee_count'] . ' &middot; '
            . payroll_h(L('payroll_net_salary')) . ': ' . payroll_h(payroll_fmt($latestRun['net_total'])) . ' ' . payroll_h($currency) . '</div>';
        echo '<a class="btn btn-sm btn-outline-primary mt-2" href="?module=payroll&a=run&id=' . (int)$latestRun['id'] . '">'
            . payroll_h(L('payroll_view_all')) . '</a>';
    } else {
        echo '<div class="text-muted">' . payroll_h(L('payroll_no_runs_yet')) . '</div>';
    }
    echo '</div></div></div>';

    echo '<div class="col-md-6"><div class="card h-100"><div class="card-body">';
    echo '<h6 class="text-muted">' . payroll_h(L('payroll_ytd_gross')) . ' / ' . payroll_h(L('payroll_ytd_tax')) . ' / ' . payroll_h(L('payroll_ytd_net')) . '</h6>';
    echo '<div class="row text-center mt-2">';
    echo '<div class="col-4"><div class="fw-bold">' . payroll_h(payroll_fmt($ytd['g'] ?? 0)) . '</div><div class="small text-muted">' . payroll_h(L('payroll_gross_pay')) . '</div></div>';
    echo '<div class="col-4"><div class="fw-bold">' . payroll_h(payroll_fmt($ytd['t'] ?? 0)) . '</div><div class="small text-muted">' . payroll_h(L('payroll_income_tax')) . '</div></div>';
    echo '<div class="col-4"><div class="fw-bold">' . payroll_h(payroll_fmt($ytd['n'] ?? 0)) . '</div><div class="small text-muted">' . payroll_h(L('payroll_net_salary')) . '</div></div>';
    echo '</div>';
    echo '</div></div></div>';
    echo '</div>';

    echo '<div class="row g-3 mb-4">';
    echo '<div class="col-md-6"><div class="card h-100"><div class="card-body">';
    echo '<h6 class="text-muted">' . payroll_h(L('payroll_current_tax_year')) . '</h6>';
    if ($taxYearRow) {
        echo '<div class="fs-5 fw-bold">' . (int)$taxYearRow['tax_year'] . '</div>';
        echo '<div class="text-muted">' . payroll_h(L('payroll_personal_exemption')) . ': ' . payroll_fmt($taxYearRow['personal_exemption']) . ' &middot; '
            . payroll_h(L('payroll_round_to')) . ': ' . payroll_fmt($taxYearRow['round_to']) . '</div>';
        echo '<a class="btn btn-sm btn-outline-primary mt-2" href="?module=payroll&a=tax_edit&year=' . (int)$taxYearRow['tax_year'] . '">'
            . payroll_h(L('payroll_edit')) . '</a>';
    } else {
        echo '<div class="text-muted">' . payroll_h(L('payroll_no_data')) . '</div>';
    }
    echo '</div></div></div>';
    echo '<div class="col-md-6"><div class="card h-100"><div class="card-body">';
    echo '<h6 class="text-muted">' . payroll_h(L('payroll_current_insurance_year')) . '</h6>';
    if ($insuranceRow) {
        echo '<div class="fs-5 fw-bold">' . (int)$insuranceRow['insurance_year'] . '</div>';
        echo '<div class="text-muted">' . payroll_h(L('payroll_employee_rate')) . ': ' . payroll_fmt($insuranceRow['employee_rate']) . '% &middot; '
            . payroll_h(L('payroll_employer_rate')) . ': ' . payroll_fmt($insuranceRow['employer_rate']) . '% &middot; '
            . payroll_h(L('payroll_max_insurance_base')) . ': ' . payroll_fmt($insuranceRow['max_base']) . '</div>';
        echo '<a class="btn btn-sm btn-outline-primary mt-2" href="?module=payroll&a=insurance_edit&id=' . (int)$insuranceRow['id'] . '">'
            . payroll_h(L('payroll_edit')) . '</a>';
    } else {
        echo '<div class="text-muted">' . payroll_h(L('payroll_no_data')) . '</div>';
    }
    echo '</div></div></div>';
    echo '</div>';

    echo '<div class="card mb-4"><div class="card-header d-flex justify-content-between align-items-center">'
        . '<span>' . payroll_h(L('payroll_recent_runs')) . '</span>'
        . '<a class="btn btn-sm btn-primary" href="?module=payroll&a=runs">' . payroll_h(L('payroll_runs')) . '</a></div>';
    echo '<div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>'
        . '<th>' . payroll_h(L('payroll_period')) . '</th>'
        . '<th>' . payroll_h(L('payroll_employee_count')) . '</th>'
        . '<th>' . payroll_h(L('payroll_gross_pay')) . '</th>'
        . '<th>' . payroll_h(L('payroll_net_salary')) . '</th>'
        . '<th>' . payroll_h(L('payroll_run_status')) . '</th>'
        . '<th></th></tr></thead><tbody>';
    if (empty($recentRuns)) {
        echo '<tr><td colspan="6" class="text-center text-muted py-3">' . payroll_h(L('payroll_no_runs_yet')) . '</td></tr>';
    } else {
        foreach ($recentRuns as $run) {
            echo '<tr>'
                . '<td>' . payroll_h(L('payroll_month_' . (int)$run['payroll_month'])) . ' ' . (int)$run['payroll_year'] . '</td>'
                . '<td>' . (int)$run['employee_count'] . '</td>'
                . '<td>' . payroll_h(payroll_fmt($run['gross_total'])) . '</td>'
                . '<td>' . payroll_h(payroll_fmt($run['net_total'])) . '</td>'
                . '<td>' . payroll_status_badge((string)$run['status']) . '</td>'
                . '<td><a class="btn btn-sm btn-outline-primary" href="?module=payroll&a=run&id=' . (int)$run['id'] . '">'
                . payroll_h(L('payroll_view')) . '</a></td></tr>';
        }
    }
    echo '</tbody></table></div></div></div>';

    echo '<div class="card"><div class="card-header">' . payroll_h(L('payroll_quick_actions')) . '</div><div class="card-body d-flex flex-wrap gap-2">';
    echo '<a class="btn btn-outline-primary" href="?module=payroll&a=types"><i class="bi bi-list-check me-1"></i>' . payroll_h(L('payroll_manage_types')) . '</a>';
    echo '<a class="btn btn-outline-primary" href="?module=payroll&a=tax"><i class="bi bi-percent me-1"></i>' . payroll_h(L('payroll_tax')) . '</a>';
    echo '<a class="btn btn-outline-primary" href="?module=payroll&a=insurance"><i class="bi bi-shield-check me-1"></i>' . payroll_h(L('payroll_insurance')) . '</a>';
    echo '<a class="btn btn-outline-primary" href="?module=payroll&a=employees"><i class="bi bi-people me-1"></i>' . payroll_h(L('payroll_employees')) . '</a>';
    echo '<a class="btn btn-outline-primary" href="?module=payroll&a=import"><i class="bi bi-filetype-csv me-1"></i>' . payroll_h(L('payroll_import_csv')) . '</a>';
    echo '<a class="btn btn-outline-primary" href="?module=payroll&a=runs"><i class="bi bi-table me-1"></i>' . payroll_h(L('payroll_runs')) . '</a>';
    echo '<a class="btn btn-outline-primary" href="?module=payroll&a=year_end"><i class="bi bi-calendar-check me-1"></i>' . payroll_h(L('payroll_year_end')) . '</a>';
    echo '<a class="btn btn-outline-primary" href="?module=payroll&a=settings"><i class="bi bi-gear me-1"></i>' . payroll_h(L('payroll_settings')) . '</a>';
    echo '</div></div>';
}

function payroll_page_types(): void
{
    global $isAr, $canEdit, $canAdd, $canDel;
    $kindFilter = (string)($_GET['kind'] ?? '');
    if (!in_array($kindFilter, ['', 'payability', 'deduction'], true)) {
        $kindFilter = '';
    }
    $types = fetchAll("SELECT * FROM " . table('payroll_types') . " WHERE omitted=0 ORDER BY kind, sort_order, id");
    if ($kindFilter !== '') {
        $types = array_values(array_filter($types, fn($t) => $t['kind'] === $kindFilter));
    }

    payroll_page_title(L('payroll_types'), L('payroll_subtitle'));
    echo '<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">';
    echo '<div class="btn-group">';
    echo '<a class="btn btn-sm ' . ($kindFilter === '' ? 'btn-primary' : 'btn-outline-primary') . '" href="?module=payroll&a=types">' . payroll_h(L('payroll_all_kinds')) . '</a>';
    echo '<a class="btn btn-sm ' . ($kindFilter === 'payability' ? 'btn-primary' : 'btn-outline-primary') . '" href="?module=payroll&a=types&kind=payability">' . payroll_h(L('payroll_payabilities')) . '</a>';
    echo '<a class="btn btn-sm ' . ($kindFilter === 'deduction' ? 'btn-primary' : 'btn-outline-primary') . '" href="?module=payroll&a=types&kind=deduction">' . payroll_h(L('payroll_deductions')) . '</a>';
    echo '</div>';
    if ($canAdd) {
        echo '<a class="btn btn-primary" href="?module=payroll&a=type_edit"><i class="bi bi-plus-lg me-1"></i>' . payroll_h(L('payroll_new_type')) . '</a>';
    }
    echo '</div>';

    echo '<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>'
        . '<th>' . payroll_h(L('payroll_kind')) . '</th>'
        . '<th>' . payroll_h(L('code')) . '</th>'
        . '<th>' . payroll_h(L('name')) . '</th>'
        . '<th>' . payroll_h(L('payroll_calc_method')) . '</th>'
        . '<th>' . payroll_h(L('payroll_rate_percent')) . ' / ' . payroll_h(L('payroll_fixed_amount')) . '</th>'
        . '<th>' . payroll_h(L('payroll_before_tax')) . '</th>'
        . '<th>' . payroll_h(L('payroll_before_insurance')) . '</th>'
        . '<th>' . payroll_h(L('payroll_max_amount')) . '</th>'
        . '<th>' . payroll_h(L('status')) . '</th>'
        . '<th></th></tr></thead><tbody>';
    if (empty($types)) {
        echo '<tr><td colspan="10" class="text-center text-muted py-3">' . payroll_h(L('no_records')) . '</td></tr>';
    } else {
        foreach ($types as $type) {
            echo '<tr>';
            echo '<td>' . payroll_h($type['kind'] === 'payability' ? L('payroll_payability') : L('payroll_deduction')) . '</td>';
            echo '<td><code>' . payroll_h($type['code']) . '</code>' . (!empty($type['is_system']) ? ' <span class="badge bg-info">' . payroll_h(L('payroll_system')) . '</span>' : '') . '</td>';
            echo '<td>' . payroll_h($isAr ? ($type['name_ar'] ?: $type['name']) : $type['name']) . '</td>';
            echo '<td>' . payroll_h(payroll_method_label((string)$type['calc_method'])) . '</td>';
            echo '<td>' . payroll_h(payroll_type_value_label($type)) . '</td>';
            echo '<td>' . ((int)$type['before_tax'] ? '<i class="bi bi-check text-success"></i>' : '<i class="bi bi-x text-muted"></i>') . '</td>';
            echo '<td>' . ((int)$type['before_insurance'] ? '<i class="bi bi-check text-success"></i>' : '<i class="bi bi-x text-muted"></i>') . '</td>';
echo '<td>' . (payroll_has_value($type['max_amount']) ? payroll_h(payroll_fmt($type['max_amount'])) : '-') . '</td>';
            echo '<td>' . ((int)$type['enabled'] ? '<span class="badge bg-success">' . payroll_h(L('payroll_active')) . '</span>' : '<span class="badge bg-secondary">' . payroll_h(L('payroll_inactive')) . '</span>') . '</td>';
            echo '<td class="text-nowrap">';
            if ($canEdit) {
                echo '<a class="btn btn-sm btn-outline-primary me-1" href="?module=payroll&a=type_edit&id=' . (int)$type['id'] . '"><i class="bi bi-pencil"></i></a>';
                if (empty($type['is_basic'])) {
                    echo '<form method="post" class="d-inline" onsubmit="return confirm(\'' . payroll_h(L('payroll_disable')) . '?\')">'
                        . '<input type="hidden" name="payroll_action" value="type_toggle"><input type="hidden" name="id" value="' . (int)$type['id'] . '">'
                        . '<button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-power"></i></button></form>';
                }
            }
            echo '</td></tr>';
        }
    }
    echo '</tbody></table></div></div></div>';
}

function payroll_page_type_edit(): void
{
    global $isAr, $canEdit;
    $id = (int)($_GET['id'] ?? 0);
    $type = null;
    if ($id > 0) {
        $type = fetchRow("SELECT * FROM " . table('payroll_types') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
    }
    $isSystem = $type && !empty($type['is_system']);
    $isBasic = $type && !empty($type['is_basic']);
    $degrees = fetchAll("SELECT * FROM " . table('empdegrees') . " WHERE omitted=0 ORDER BY id");
    $degreeAmounts = [];
    if ($type) {
        foreach (fetchAll("SELECT * FROM " . table('payroll_type_degrees') . " WHERE type_id=? AND omitted=0", [(int)$type['id']]) as $row) {
            $degreeAmounts[(int)$row['degree_id']] = (float)$row['amount'];
        }
    }
    $otherTypes = fetchAll("SELECT id, code, name, name_ar FROM " . table('payroll_types') . " WHERE omitted=0 AND id<>? ORDER BY sort_order, id", [$id ?: 0]);

    payroll_page_title($type ? L('payroll_edit_type') : L('payroll_new_type'), L('payroll_type_details'));

    echo '<form method="post" class="card"><div class="card-body"><input type="hidden" name="payroll_action" value="type_save">';
    if ($type) {
        echo '<input type="hidden" name="id" value="' . (int)$type['id'] . '">';
    }
    echo '<div class="row g-3">';

    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_kind')) . '</label>';
    if ($type) {
        $kind = $type['kind'];
        echo '<select class="form-select" name="kind" required>'
            . '<option value="payability"' . ($kind === 'payability' ? ' selected' : '') . '>' . payroll_h(L('payroll_payability')) . '</option>'
            . '<option value="deduction"' . ($kind === 'deduction' ? ' selected' : '') . '>' . payroll_h(L('payroll_deduction')) . '</option></select>';
    } else {
        echo '<select class="form-select" name="kind" required>'
            . '<option value="payability">' . payroll_h(L('payroll_payability')) . '</option>'
            . '<option value="deduction">' . payroll_h(L('payroll_deduction')) . '</option></select>';
    }
    echo '</div>';

    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('code')) . '</label>';
    if ($isSystem || $type) {
        echo '<input class="form-control" name="code" value="' . payroll_h($type['code']) . '" ' . ($isSystem ? 'readonly' : '') . ' required>';
    } else {
        echo '<input class="form-control" name="code" value="" placeholder="BONUS" required>';
    }
    echo '</div>';

    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('name')) . '</label>'
        . '<input class="form-control" name="name" value="' . payroll_h($type['name'] ?? '') . '" required></div>';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('name')) . ' (AR)</label>'
        . '<input class="form-control" name="name_ar" value="' . payroll_h($type['name_ar'] ?? '') . '" required></div>';

    echo '<div class="col-md-6"><label class="form-label">' . payroll_h(L('description')) . '</label>'
        . '<textarea class="form-control" name="description" rows="2">' . payroll_h($type['description'] ?? '') . '</textarea></div>';
    echo '<div class="col-md-6"><label class="form-label">' . payroll_h(L('description')) . ' (AR)</label>'
        . '<textarea class="form-control" name="description_ar" rows="2">' . payroll_h($type['description_ar'] ?? '') . '</textarea></div>';

    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_calc_method')) . '</label>';
    if ($type) {
        $methods = ['fixed', 'manual', 'percent_basic', 'percent_gross', 'percent_type', 'degree', 'seniority'];
        if ($isSystem || !empty($type['is_system'])) {
            $methods = array_merge($methods, ['tax', 'insurance']);
        }
        $methods = array_values(array_unique($methods));
        echo '<select class="form-select" name="calc_method" id="calc_method" required>';
        foreach ($methods as $m) {
            $selected = $type['calc_method'] === $m ? ' selected' : '';
            echo '<option value="' . $m . '"' . $selected . '>' . payroll_h(payroll_method_label($m)) . '</option>';
        }
        echo '</select>';
    } else {
        $methods = ['fixed', 'manual', 'percent_basic', 'percent_gross', 'percent_type', 'degree', 'seniority'];
        echo '<select class="form-select" name="calc_method" id="calc_method" required>';
        foreach ($methods as $m) {
            echo '<option value="' . $m . '">' . payroll_h(payroll_method_label($m)) . '</option>';
        }
        echo '</select>';
    }
    echo '</div>';

    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_rate_percent')) . '</label>'
        . '<input type="number" step="0.0001" class="form-control" name="rate" value="' . payroll_h($type['rate'] ?? '0') . '"></div>';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_fixed_amount')) . '</label>'
        . '<input type="number" step="0.01" class="form-control" name="amount" value="' . payroll_h($type['amount'] ?? '0') . '"></div>';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_base_type')) . '</label>';
    echo '<select class="form-select" name="base_type_id"><option value="0">' . payroll_h(L('payroll_base_total_payabilities')) . '</option>';
    foreach ($otherTypes as $other) {
        $selected = ($type && (int)$type['base_type_id'] === (int)$other['id']) ? ' selected' : '';
        echo '<option value="' . (int)$other['id'] . '"' . $selected . '>' . payroll_h($isAr ? ($other['name_ar'] ?: $other['name']) : $other['name']) . ' (' . payroll_h($other['code']) . ')</option>';
    }
    echo '</select></div>';

    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_min_amount')) . '</label>'
        . '<input type="number" step="0.01" class="form-control" name="min_amount" value="' . payroll_h($type['min_amount'] ?? '') . '"></div>';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_max_amount')) . '</label>'
        . '<input type="number" step="0.01" class="form-control" name="max_amount" value="' . payroll_h($type['max_amount'] ?? '') . '"></div>';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_sort_order')) . '</label>'
        . '<input type="number" class="form-control" name="sort_order" value="' . payroll_h($type['sort_order'] ?? '100') . '"></div>';
    echo '<div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch mb-2">'
        . '<input class="form-check-input" type="checkbox" name="enabled" id="enabled" ' . ((!$type || (int)$type['enabled'] === 1) ? 'checked' : '') . ' ' . ($isBasic ? 'disabled' : '') . '>'
        . '<label class="form-check-label" for="enabled">' . payroll_h(L('payroll_active')) . '</label></div></div>';

    echo '<div class="col-12"><div class="border rounded p-3 bg-light">';
    echo '<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="before_tax" id="before_tax" ' . ((!$type || (int)$type['before_tax'] === 1) ? 'checked' : '') . '>'
        . '<label class="form-check-label" for="before_tax">' . payroll_h(L('payroll_before_tax')) . '</label></div>';
    echo '<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="before_insurance" id="before_insurance" ' . ((!$type || (int)$type['before_insurance'] === 1) ? 'checked' : '') . '>'
        . '<label class="form-check-label" for="before_insurance">' . payroll_h(L('payroll_before_insurance')) . '</label></div>';
    echo '<div class="form-text">' . payroll_h(L('payroll_flags_hint')) . '</div>';
    echo '</div></div>';

    // Degree amounts
    echo '<div class="col-12" id="degree_block" style="display:none"><label class="form-label">' . payroll_h(L('payroll_degree_amounts')) . '</label>'
        . '<div class="form-text mb-2">' . payroll_h(L('payroll_degree_amounts_hint')) . '</div>';
    echo '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>' . payroll_h(L('payroll_degree')) . '</th><th>' . payroll_h(L('payroll_fixed_amount')) . '</th></tr></thead><tbody>';
    foreach ($degrees as $degree) {
        $degreeName = $isAr ? ($degree['degreenamear'] ?: $degree['degreename']) : $degree['degreename'];
        echo '<tr><td>' . payroll_h($degreeName) . '</td>'
            . '<td><input type="number" step="0.01" class="form-control form-control-sm" name="degree_amount[' . (int)$degree['id'] . ']" value="'
            . payroll_h($degreeAmounts[(int)$degree['id']] ?? '') . '"></td></tr>';
    }
    echo '</tbody></table></div></div>';

    echo '</div></div>';
    echo '<div class="card-footer text-end"><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>' . payroll_h(L('save')) . '</button>'
        . ' <a class="btn btn-outline-secondary" href="?module=payroll&a=types">' . payroll_h(L('cancel')) . '</a></div></form>';

    // Show degree block when degree method selected
    echo '<script>
    (function(){
      var sel = document.getElementById("calc_method");
      var block = document.getElementById("degree_block");
      if(!sel){ if("' . ($type && $type['calc_method'] === 'degree' ? '1' : '0') . '"==="1" && block){ block.style.display="block"; } return; }
      function upd(){ block.style.display = (sel.value==="degree") ? "block" : "none"; }
      sel.addEventListener("change", upd); upd();
    })();
    </script>';
}

function payroll_page_tax(): void
{
    global $isAr;
    $years = fetchAll("SELECT y.*, (SELECT COUNT(*) FROM " . table('payroll_tax_brackets') . " b WHERE b.tax_year=y.tax_year AND b.omitted=0) AS bracket_count FROM " . table('payroll_tax_years') . " y WHERE y.omitted=0 ORDER BY y.tax_year DESC");

    payroll_page_title(L('payroll_tax'), L('payroll_tax_years'));
    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
    echo '<form method="post" class="d-flex gap-2"><input type="hidden" name="payroll_action" value="tax_add">'
        . '<input type="number" class="form-control" name="tax_year" placeholder="' . payroll_h(L('payroll_tax_year')) . '" min="2000" max="2100" style="width:120px" required>'
        . '<button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>' . payroll_h(L('payroll_add_tax_year')) . '</button></form>';
    echo '</div>';

    echo '<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>'
        . '<th>' . payroll_h(L('payroll_tax_year')) . '</th>'
        . '<th>' . payroll_h(L('payroll_personal_exemption')) . '</th>'
        . '<th>' . payroll_h(L('payroll_round_to')) . '</th>'
        . '<th>' . payroll_h(L('payroll_tax_brackets')) . '</th>'
        . '<th>' . payroll_h(L('status')) . '</th>'
        . '<th></th></tr></thead><tbody>';
    if (empty($years)) {
        echo '<tr><td colspan="6" class="text-center text-muted py-3">' . payroll_h(L('no_records')) . '</td></tr>';
    } else {
        foreach ($years as $year) {
            echo '<tr>'
                . '<td class="fw-bold">' . (int)$year['tax_year'] . '</td>'
                . '<td>' . payroll_fmt($year['personal_exemption']) . '</td>'
                . '<td>' . payroll_fmt($year['round_to']) . '</td>'
                . '<td>' . (int)$year['bracket_count'] . '</td>'
                . '<td>' . ((int)$year['enabled'] ? '<span class="badge bg-success">' . payroll_h(L('payroll_active')) . '</span>' : '<span class="badge bg-secondary">' . payroll_h(L('payroll_inactive')) . '</span>') . '</td>'
                . '<td class="text-nowrap">'
                . '<a class="btn btn-sm btn-outline-primary me-1" href="?module=payroll&a=tax_edit&year=' . (int)$year['tax_year'] . '"><i class="bi bi-pencil"></i> ' . payroll_h(L('edit')) . '</a>'
                . '<form method="post" class="d-inline"><input type="hidden" name="payroll_action" value="tax_toggle"><input type="hidden" name="id" value="' . (int)$year['id'] . '">'
                . '<button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-power"></i></button></form>'
                . '</td></tr>';
        }
    }
    echo '</tbody></table></div></div></div>';
}

function payroll_page_tax_edit(): void
{
    global $isAr;
    $year = (int)($_GET['year'] ?? 0);
    $taxYear = fetchRow("SELECT * FROM " . table('payroll_tax_years') . " WHERE tax_year=? AND omitted=0 LIMIT 1", [$year]);
    if (!$taxYear) {
        set_flash('error', L('payroll_tax_year_required'));
        payroll_redirect('?module=payroll&a=tax');
    }
    $brackets = fetchAll("SELECT * FROM " . table('payroll_tax_brackets') . " WHERE tax_year=? AND omitted=0 ORDER BY schedule_min, bracket_order, id", [$year]);

    payroll_page_title(L('payroll_tax_year_details') . ' ' . $year, L('payroll_tax_brackets_hint'));

    echo '<form method="post" class="card"><div class="card-body"><input type="hidden" name="payroll_action" value="tax_save">';
    echo '<input type="hidden" name="tax_year" value="' . $year . '">';
    echo '<div class="row g-3 mb-3">';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_personal_exemption')) . '</label>'
        . '<input type="number" step="0.01" class="form-control" name="personal_exemption" value="' . payroll_h($taxYear['personal_exemption']) . '" required></div>';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_round_to')) . '</label>'
        . '<input type="number" step="0.01" class="form-control" name="round_to" value="' . payroll_h($taxYear['round_to']) . '" required></div>';
    echo '<div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch mb-2">'
        . '<input class="form-check-input" type="checkbox" name="enabled" id="ty_enabled" ' . ((int)$taxYear['enabled'] ? 'checked' : '') . '>'
        . '<label class="form-check-label" for="ty_enabled">' . payroll_h(L('payroll_active')) . '</label></div></div>';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_notes')) . '</label>'
        . '<input class="form-control" name="notes" value="' . payroll_h($taxYear['notes'] ?? '') . '"></div>';
    echo '</div>';

    echo '<div class="alert alert-info small">' . payroll_h(L('payroll_tax_high_income_hint')) . '</div>';

    echo '<div class="table-responsive"><table class="table table-sm" id="bracket_table"><thead><tr>'
        . '<th>' . payroll_h(L('payroll_schedule_min')) . '</th>'
        . '<th>' . payroll_h(L('payroll_schedule_max')) . '</th>'
        . '<th>' . payroll_h(L('payroll_bracket_order')) . '</th>'
        . '<th>' . payroll_h(L('payroll_from_amount')) . '</th>'
        . '<th>' . payroll_h(L('payroll_to_amount')) . '</th>'
        . '<th>' . payroll_h(L('payroll_band_rate')) . '</th>'
        . '<th></th></tr></thead><tbody>';
    if (empty($brackets)) {
        $brackets = [['schedule_min' => 0, 'schedule_max' => 600000, 'bracket_order' => 1, 'from_amount' => 0, 'to_amount' => 40000, 'rate' => 0]];
    }
    foreach ($brackets as $b) {
        echo '<tr>'
            . '<td><input type="number" step="0.01" class="form-control form-control-sm" name="brackets[][schedule_min]" value="' . payroll_h($b['schedule_min']) . '" required></td>'
            . '<td><input type="number" step="0.01" class="form-control form-control-sm" name="brackets[][schedule_max]" value="' . payroll_h($b['schedule_max'] ?? '') . '"></td>'
            . '<td><input type="number" class="form-control form-control-sm" name="brackets[][bracket_order]" value="' . payroll_h($b['bracket_order']) . '" required></td>'
            . '<td><input type="number" step="0.01" class="form-control form-control-sm" name="brackets[][from_amount]" value="' . payroll_h($b['from_amount']) . '" required></td>'
            . '<td><input type="number" step="0.01" class="form-control form-control-sm" name="brackets[][to_amount]" value="' . payroll_h($b['to_amount'] ?? '') . '"></td>'
            . '<td><input type="number" step="0.0001" class="form-control form-control-sm" name="brackets[][rate]" value="' . payroll_h($b['rate']) . '" required></td>'
            . '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'tr\').remove()"><i class="bi bi-trash"></i></button></td>'
            . '</tr>';
    }
    echo '</tbody></table></div>';

    echo '<button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addBracketRow()"><i class="bi bi-plus-lg me-1"></i>' . payroll_h(L('payroll_add_band')) . '</button>';

    echo '<script>
    function addBracketRow(){
      var tbody = document.querySelector("#bracket_table tbody");
      var tr = document.createElement("tr");
      tr.innerHTML = \'<td><input type="number" step="0.01" class="form-control form-control-sm" name="brackets[][schedule_min]" value="0" required></td>\'
        + \'<td><input type="number" step="0.01" class="form-control form-control-sm" name="brackets[][schedule_max]" value=""></td>\'
        + \'<td><input type="number" class="form-control form-control-sm" name="brackets[][bracket_order]" value="1" required></td>\'
        + \'<td><input type="number" step="0.01" class="form-control form-control-sm" name="brackets[][from_amount]" value="0" required></td>\'
        + \'<td><input type="number" step="0.01" class="form-control form-control-sm" name="brackets[][to_amount]" value=""></td>\'
        + \'<td><input type="number" step="0.0001" class="form-control form-control-sm" name="brackets[][rate]" value="0" required></td>\'
        + \'<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\\\'tr\\\').remove()"><i class="bi bi-trash"></i></button></td>\';
      tbody.appendChild(tr);
    }
    </script>';

    echo '</div><div class="card-footer text-end"><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>' . payroll_h(L('save')) . '</button>'
        . ' <a class="btn btn-outline-secondary" href="?module=payroll&a=tax">' . payroll_h(L('cancel')) . '</a></div></form>';
}

function payroll_page_insurance(): void
{
    global $isAr;
    $rules = fetchAll("SELECT * FROM " . table('payroll_insurance_rules') . " WHERE omitted=0 ORDER BY insurance_year DESC");

    payroll_page_title(L('payroll_insurance'), L('payroll_insurance_rules'));
    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
    echo '<form method="post" class="d-flex gap-2"><input type="hidden" name="payroll_action" value="insurance_add">'
        . '<input type="number" class="form-control" name="insurance_year" placeholder="' . payroll_h(L('payroll_insurance_year')) . '" min="2000" max="2100" style="width:120px" required>'
        . '<button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>' . payroll_h(L('payroll_add_insurance_year')) . '</button></form>';
    echo '</div>';

    echo '<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>'
        . '<th>' . payroll_h(L('payroll_insurance_year')) . '</th>'
        . '<th>' . payroll_h(L('payroll_employee_rate')) . '</th>'
        . '<th>' . payroll_h(L('payroll_employer_rate')) . '</th>'
        . '<th>' . payroll_h(L('payroll_min_insurance_base')) . '</th>'
        . '<th>' . payroll_h(L('payroll_max_insurance_base')) . '</th>'
        . '<th>' . payroll_h(L('status')) . '</th>'
        . '<th></th></tr></thead><tbody>';
    if (empty($rules)) {
        echo '<tr><td colspan="7" class="text-center text-muted py-3">' . payroll_h(L('no_records')) . '</td></tr>';
    } else {
        foreach ($rules as $rule) {
            echo '<tr>'
                . '<td class="fw-bold">' . (int)$rule['insurance_year'] . '</td>'
                . '<td>' . payroll_fmt($rule['employee_rate']) . '%</td>'
                . '<td>' . payroll_fmt($rule['employer_rate']) . '%</td>'
                . '<td>' . payroll_fmt($rule['min_base']) . '</td>'
                . '<td>' . (payroll_has_value($rule['max_base']) ? payroll_fmt($rule['max_base']) : 'ط·آ£ط¢آ¢ط£آ¢أ¢â‚¬ع‘ط¢آ¬ط£آ¢أ¢â€ڑآ¬أ¢â‚¬إ’') . '</td>'
                . '<td>' . ((int)$rule['enabled'] ? '<span class="badge bg-success">' . payroll_h(L('payroll_active')) . '</span>' : '<span class="badge bg-secondary">' . payroll_h(L('payroll_inactive')) . '</span>') . '</td>'
                . '<td class="text-nowrap">'
                . '<a class="btn btn-sm btn-outline-primary me-1" href="?module=payroll&a=insurance_edit&id=' . (int)$rule['id'] . '"><i class="bi bi-pencil"></i> ' . payroll_h(L('edit')) . '</a>'
                . '<form method="post" class="d-inline"><input type="hidden" name="payroll_action" value="insurance_toggle"><input type="hidden" name="id" value="' . (int)$rule['id'] . '">'
                . '<button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-power"></i></button></form>'
                . '</td></tr>';
        }
    }
    echo '</tbody></table></div></div></div>';
}

function payroll_page_insurance_edit(): void
{
    global $isAr;
    $id = (int)($_GET['id'] ?? 0);
    $rule = fetchRow("SELECT * FROM " . table('payroll_insurance_rules') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
    if (!$rule) {
        set_flash('error', L('payroll_required'));
        payroll_redirect('?module=payroll&a=insurance');
    }

    payroll_page_title(L('payroll_insurance') . ' ط·آ£ط¢آ¢ط£آ¢أ¢â‚¬ع‘ط¢آ¬ط£آ¢أ¢â€ڑآ¬أ¢â‚¬إ’ ' . (int)$rule['insurance_year'], L('payroll_insurance_limit_hint'));

    echo '<form method="post" class="card"><div class="card-body"><input type="hidden" name="payroll_action" value="insurance_save">';
    echo '<input type="hidden" name="id" value="' . (int)$rule['id'] . '">';
    echo '<div class="row g-3">';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_insurance_year')) . '</label>'
        . '<input class="form-control" value="' . (int)$rule['insurance_year'] . '" disabled></div>';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_employee_rate')) . '</label>'
        . '<input type="number" step="0.0001" class="form-control" name="employee_rate" value="' . payroll_h($rule['employee_rate']) . '" required></div>';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_employer_rate')) . '</label>'
        . '<input type="number" step="0.0001" class="form-control" name="employer_rate" value="' . payroll_h($rule['employer_rate']) . '" required></div>';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_min_insurance_base')) . '</label>'
        . '<input type="number" step="0.01" class="form-control" name="min_base" value="' . payroll_h($rule['min_base']) . '" required></div>';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_max_insurance_base')) . '</label>'
        . '<input type="number" step="0.01" class="form-control" name="max_base" value="' . payroll_h($rule['max_base'] ?? '') . '"></div>';
    echo '<div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch mb-2">'
        . '<input class="form-check-input" type="checkbox" name="enabled" id="ins_enabled" ' . ((int)$rule['enabled'] ? 'checked' : '') . '>'
        . '<label class="form-check-label" for="ins_enabled">' . payroll_h(L('payroll_active')) . '</label></div></div>';
    echo '<div class="col-md-6"><label class="form-label">' . payroll_h(L('payroll_notes')) . '</label>'
        . '<input class="form-control" name="notes" value="' . payroll_h($rule['notes'] ?? '') . '"></div>';
    echo '</div></div>';
    echo '<div class="card-footer text-end"><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>' . payroll_h(L('save')) . '</button>'
        . ' <a class="btn btn-outline-secondary" href="?module=payroll&a=insurance">' . payroll_h(L('cancel')) . '</a></div></form>';
}

function payroll_page_settings(): void
{
    global $isAr;
    $taxEnabled = (string)payroll_setting('tax_enabled', '1');
    $insuranceEnabled = (string)payroll_setting('insurance_enabled', '1');
    $currency = (string)payroll_setting('currency', 'EGP');

    payroll_page_title(L('payroll_settings'), L('payroll_settings_hint'));
    echo '<form method="post" class="card"><div class="card-body"><input type="hidden" name="payroll_action" value="settings_save">';
    echo '<div class="row g-3">';
    echo '<div class="col-md-4"><div class="form-check form-switch">'
        . '<input class="form-check-input" type="checkbox" name="tax_enabled" id="tax_enabled" ' . ($taxEnabled !== '0' ? 'checked' : '') . '>'
        . '<label class="form-check-label" for="tax_enabled">' . payroll_h(L('payroll_enable_tax')) . '</label></div></div>';
    echo '<div class="col-md-4"><div class="form-check form-switch">'
        . '<input class="form-check-input" type="checkbox" name="insurance_enabled" id="insurance_enabled" ' . ($insuranceEnabled !== '0' ? 'checked' : '') . '>'
        . '<label class="form-check-label" for="insurance_enabled">' . payroll_h(L('payroll_enable_insurance')) . '</label></div></div>';
    echo '<div class="col-md-4"><label class="form-label">' . payroll_h(L('payroll_currency')) . '</label>'
        . '<input class="form-control" name="currency" value="' . payroll_h($currency) . '" maxlength="10"></div>';
    echo '</div></div>';
    echo '<div class="card-footer text-end"><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>' . payroll_h(L('save')) . '</button></div></form>';

}

function payroll_page_employees(): void
{
    global $isAr;
    $search = trim((string)($_GET['q'] ?? ''));
    $sql = "SELECT e.id, e.empfullname, e.empfullnamear, e.nid, e.insuranceid, e.currentdegreename, e.currentdegreenamear,
                   s.basic_salary, s.tax_exempt, s.insurance_exempt,
                   (SELECT COUNT(*) FROM " . table('payroll_employee_types') . " t WHERE t.empid=e.id AND t.omitted=0 AND t.enabled=1) AS assigned_count,
                   (SELECT COUNT(*) FROM " . table('payroll_employee_type_values') . " v WHERE v.empid=e.id AND v.omitted=0) AS value_count
            FROM " . table('employeesmain') . " e
            LEFT JOIN " . table('payroll_employee_settings') . " s ON s.empid=e.id AND s.omitted=0
            WHERE e.omitted=0";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (e.empfullname LIKE ? OR e.empfullnamear LIKE ? OR e.nid LIKE ? OR e.insuranceid LIKE ?)";
        $like = '%' . $search . '%';
        $params = [$like, $like, $like, $like];
    }
    $sql .= " ORDER BY e.empfullname";
    $employees = fetchAll($sql, $params);

    payroll_page_title(L('payroll_employees'), L('payroll_employee_setup'));
    echo '<form method="get" class="mb-3"><input type="hidden" name="module" value="payroll"><input type="hidden" name="a" value="employees">';
    echo '<div class="input-group" style="max-width:420px"><input class="form-control" name="q" value="' . payroll_h($search) . '" placeholder="' . payroll_h(L('payroll_search_employee')) . '">'
        . '<button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button></div></form>';

    echo '<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>'
        . '<th>' . payroll_h(L('payroll_employee_name')) . '</th>'
        . '<th>' . payroll_h(L('payroll_national_id')) . '</th>'
        . '<th>' . payroll_h(L('payroll_insurance_number')) . '</th>'
        . '<th>' . payroll_h(L('payroll_degree')) . '</th>'
        . '<th>' . payroll_h(L('payroll_basic_salary')) . '</th>'
        . '<th>' . payroll_h(L('payroll_assigned_types')) . '</th>'
        . '<th>' . payroll_h(L('payroll_exempt')) . '</th>'
        . '<th></th></tr></thead><tbody>';
    if (empty($employees)) {
        echo '<tr><td colspan="8" class="text-center text-muted py-3">' . payroll_h(L('no_records')) . '</td></tr>';
    } else {
        foreach ($employees as $emp) {
            $degreeName = $isAr ? ($emp['currentdegreenamear'] ?: $emp['currentdegreename']) : $emp['currentdegreename'];
            echo '<tr>'
                . '<td>' . payroll_h($isAr ? ($emp['empfullnamear'] ?: $emp['empfullname']) : $emp['empfullname']) . '</td>'
                . '<td>' . payroll_h($emp['nid']) . '</td>'
                . '<td>' . payroll_h($emp['insuranceid']) . '</td>'
                . '<td>' . payroll_h($degreeName) . '</td>'
                . '<td>' . (payroll_has_value($emp['basic_salary']) ? payroll_h(payroll_fmt($emp['basic_salary'])) : '<span class="text-danger">' . payroll_h(L('payroll_salary_not_set')) . '</span>') . '</td>'
                . '<td>' . (int)$emp['assigned_count'] . ' / ' . (int)$emp['value_count'] . '</td>'
                . '<td>'
                . ((int)($emp['tax_exempt'] ?? 0) ? '<span class="badge bg-warning text-dark">T</span> ' : '')
                . ((int)($emp['insurance_exempt'] ?? 0) ? '<span class="badge bg-warning text-dark">I</span>' : '')
                . '</td>'
                . '<td><a class="btn btn-sm btn-outline-primary" href="?module=payroll&a=employee&id=' . (int)$emp['id'] . '"><i class="bi bi-pencil"></i> ' . payroll_h(L('edit')) . '</a></td></tr>';
        }
    }
    echo '</tbody></table></div></div></div>';
}

function payroll_page_employee(): void
{
    global $isAr, $canEdit;
    $empid = (int)($_GET['id'] ?? 0);
    $employee = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [$empid]);
    if (!$employee) {
        set_flash('error', L('payroll_employee_not_found'));
        payroll_redirect('?module=payroll&a=employees');
    }
    $settings = fetchRow("SELECT * FROM " . table('payroll_employee_settings') . " WHERE empid=? AND omitted=0 ORDER BY id DESC LIMIT 1", [$empid]);
    $types = fetchAll("SELECT * FROM " . table('payroll_types') . " WHERE omitted=0 AND is_basic=0 ORDER BY sort_order, id");
    $assignments = [];
    foreach (fetchAll("SELECT * FROM " . table('payroll_employee_types') . " WHERE empid=? AND omitted=0", [$empid]) as $row) {
        $assignments[(int)$row['type_id']] = $row;
    }
    $degreeName = $isAr ? ($employee['currentdegreenamear'] ?: $employee['currentdegreename']) : $employee['currentdegreename'];
    $serviceYears = payroll_service_years($employee['senioritydate'] ?: $employee['dop'], date('Y-m-d'));

    echo '<div class="mb-3"><a class="btn btn-sm btn-outline-secondary" href="?module=payroll&a=employees"><i class="bi bi-arrow-left me-1"></i>' . payroll_h(L('payroll_back_to_employees')) . '</a></div>';
    payroll_page_title(sprintf(L('payroll_employee_setup_for'), $isAr ? ($employee['empfullnamear'] ?: $employee['empfullname']) : $employee['empfullname']), L('payroll_assign_hint'));

    echo '<div class="card mb-3"><div class="card-body"><div class="row g-2 small">';
    echo '<div class="col-md-3"><strong>' . payroll_h(L('payroll_national_id')) . ':</strong> ' . payroll_h($employee['nid']) . '</div>';
    echo '<div class="col-md-3"><strong>' . payroll_h(L('payroll_insurance_number')) . ':</strong> ' . payroll_h($employee['insuranceid']) . '</div>';
    echo '<div class="col-md-3"><strong>' . payroll_h(L('payroll_degree')) . ':</strong> ' . payroll_h($degreeName) . '</div>';
    echo '<div class="col-md-3"><strong>' . payroll_h(L('payroll_service_years')) . ':</strong> ' . $serviceYears . '</div>';
    echo '</div></div></div>';

    echo '<form method="post" class="card"><div class="card-body"><input type="hidden" name="payroll_action" value="employee_save">';
    echo '<input type="hidden" name="empid" value="' . $empid . '">';
    echo '<div class="row g-3 mb-3">';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_basic_salary')) . '</label>'
        . '<input type="number" step="0.01" class="form-control" name="basic_salary" value="' . payroll_h($settings['basic_salary'] ?? '0') . '" required></div>';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_insurance_base_override')) . '</label>'
        . '<input type="number" step="0.01" class="form-control" name="insurance_base_override" value="' . payroll_h($settings['insurance_base_override'] ?? '') . '"></div>';
    echo '<div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch mb-2">'
        . '<input class="form-check-input" type="checkbox" name="tax_exempt" id="tax_exempt" ' . (!empty($settings['tax_exempt']) ? 'checked' : '') . '>'
        . '<label class="form-check-label" for="tax_exempt">' . payroll_h(L('payroll_tax_exempt')) . '</label></div></div>';
    echo '<div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch mb-2">'
        . '<input class="form-check-input" type="checkbox" name="insurance_exempt" id="insurance_exempt" ' . (!empty($settings['insurance_exempt']) ? 'checked' : '') . '>'
        . '<label class="form-check-label" for="insurance_exempt">' . payroll_h(L('payroll_insurance_exempt')) . '</label></div></div>';
    echo '<div class="col-12"><label class="form-label">' . payroll_h(L('notes')) . '</label>'
        . '<input class="form-control" name="notes" value="' . payroll_h($settings['notes'] ?? '') . '"></div>';
    echo '</div>';

    echo '<h6 class="mb-2">' . payroll_h(L('payroll_applicable_types')) . '</h6>';
    echo '<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr>'
        . '<th>' . payroll_h(L('payroll_applies')) . '</th>'
        . '<th>' . payroll_h(L('payroll_type')) . '</th>'
        . '<th>' . payroll_h(L('payroll_kind')) . '</th>'
        . '<th>' . payroll_h(L('payroll_calc_method')) . '</th>'
        . '<th>' . payroll_h(L('payroll_amount_override')) . '</th>'
        . '<th>' . payroll_h(L('payroll_rate_override')) . '</th>'
        . '<th>' . payroll_h(L('payroll_effective_from')) . '</th>'
        . '<th>' . payroll_h(L('payroll_effective_to')) . '</th></tr></thead><tbody>';
    if (empty($types)) {
        echo '<tr><td colspan="8" class="text-center text-muted">' . payroll_h(L('payroll_no_types_defined')) . '</td></tr>';
    } else {
        foreach ($types as $type) {
            $tid = (int)$type['id'];
            $assignment = $assignments[$tid] ?? null;
            $isSystemType = !empty($type['is_system']);
            if ($assignment) {
                $checked = (int)$assignment['omitted'] === 0 && (int)$assignment['enabled'] === 1;
            } else {
                $checked = $isSystemType;
            }
            $disabled = (int)$type['enabled'] === 0;
            echo '<tr' . ($disabled ? ' class="table-secondary"' : '') . '>';
            echo '<td><input type="checkbox" class="form-check-input" name="assign[' . $tid . ']" value="1" ' . ($checked ? 'checked' : '') . '></td>';
            echo '<td>' . payroll_h($isAr ? ($type['name_ar'] ?: $type['name']) : $type['name']) . ' <code class="small">' . payroll_h($type['code']) . '</code>'
                . ($disabled ? ' <span class="badge bg-secondary">' . payroll_h(L('payroll_inactive')) . '</span>' : '') . '</td>';
            echo '<td>' . payroll_h($type['kind'] === 'payability' ? L('payroll_payability') : L('payroll_deduction')) . '</td>';
            echo '<td>' . payroll_h(payroll_method_label((string)$type['calc_method'])) . ' <small class="text-muted">' . payroll_h(payroll_type_value_label($type)) . '</small></td>';
            echo '<td><input type="number" step="0.01" class="form-control form-control-sm" name="amount_override[' . $tid . ']" value="' . payroll_h($assignment['amount_override'] ?? '') . '"></td>';
            echo '<td><input type="number" step="0.0001" class="form-control form-control-sm" name="rate_override[' . $tid . ']" value="' . payroll_h($assignment['rate_override'] ?? '') . '"></td>';
            echo '<td><input type="date" class="form-control form-control-sm" name="effective_from[' . $tid . ']" value="' . payroll_h($assignment['effective_from'] ?? '') . '"></td>';
            echo '<td><input type="date" class="form-control form-control-sm" name="effective_to[' . $tid . ']" value="' . payroll_h($assignment['effective_to'] ?? '') . '"></td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';

    echo '<div class="form-text mb-3">' . payroll_h(L('payroll_override_hint')) . '</div>';
    echo '</div><div class="card-footer text-end"><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>' . payroll_h(L('save')) . '</button>'
        . ' <a class="btn btn-outline-secondary" href="?module=payroll&a=employees">' . payroll_h(L('cancel')) . '</a></div></form>';
}

function payroll_page_import(): void
{
    global $isAr;
    $kind = (string)($_GET['kind'] ?? 'basic');
    if (!in_array($kind, ['basic', 'values'], true)) {
        $kind = 'basic';
    }
    $result = $_SESSION[UHR_SESSION_KEY]['payroll_import_result'] ?? null;
    if (is_array($result)) {
        unset($_SESSION[UHR_SESSION_KEY]['payroll_import_result']);
    }

    payroll_page_title($kind === 'basic' ? L('payroll_import_basic_title') : L('payroll_import_values_title'),
        $kind === 'basic' ? L('payroll_import_basic_hint') : L('payroll_import_values_hint'));

    echo '<div class="mb-3">';
    echo '<a class="btn btn-sm ' . ($kind === 'basic' ? 'btn-primary' : 'btn-outline-primary') . '" href="?module=payroll&a=import&kind=basic">' . payroll_h(L('payroll_import_basic')) . '</a> ';
    echo '<a class="btn btn-sm ' . ($kind === 'values' ? 'btn-primary' : 'btn-outline-primary') . '" href="?module=payroll&a=import&kind=values">' . payroll_h(L('payroll_import_values')) . '</a>';
    echo '</div>';

    if ($kind === 'basic') {
        echo '<div class="alert alert-secondary small"><strong>' . payroll_h(L('payroll_import_basic_example')) . ':</strong> <code>nid,basic_salary</code><br>'
            . '<code>28106010102098,10000</code></div>';
    } else {
        echo '<div class="alert alert-secondary small"><strong>' . payroll_h(L('payroll_import_long_example')) . ':</strong> <code>nid,code,amount</code><br>'
            . '<code>28106010102098,LOAN,500</code><br>'
            . '<strong>' . payroll_h(L('payroll_import_wide_example')) . ':</strong> <code>nid,LOAN,PHONE_BILL</code><br>'
            . '<code>28106010102098,500,150</code></div>';
    }
    echo '<div class="alert alert-info small">' . payroll_h(L('payroll_import_formats_hint')) . '</div>';

    echo '<form method="post" enctype="multipart/form-data" class="card"><div class="card-body"><input type="hidden" name="payroll_action" value="import_upload">';
    echo '<input type="hidden" name="kind" value="' . payroll_h($kind) . '">';
    echo '<div class="row g-3">';
    echo '<div class="col-md-6"><label class="form-label">' . payroll_h(L('payroll_csv_file')) . '</label>'
        . '<input type="file" class="form-control" name="csv" accept=".csv,.txt,text/*" required></div>';
    echo '<div class="col-md-3"><label class="form-label">' . payroll_h(L('payroll_default_period')) . '</label><div class="d-flex gap-2">';
    payroll_year_input('payroll_year', (int)date('Y'), 'form-control');
    payroll_month_select('payroll_month', (int)date('n'));
    echo '</div></div>';
    echo '<div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2">'
        . '<input class="form-check-input" type="checkbox" name="dry_run" id="dry_run" value="1">'
        . '<label class="form-check-label" for="dry_run">' . payroll_h(L('payroll_validate_only')) . '</label></div></div>';
    if ($kind === 'values') {
        echo '<div class="col-md-6 d-flex align-items-end"><div class="form-check mb-2">'
            . '<input class="form-check-input" type="checkbox" name="create_missing" id="create_missing" value="1" checked>'
            . '<label class="form-check-label" for="create_missing">' . payroll_h(L('payroll_create_missing_types')) . '</label></div></div>';
    }
    echo '</div></div>';
    echo '<div class="card-footer text-end"><button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>' . payroll_h(L('payroll_import_run')) . '</button></div></form>';

    if (is_array($result)) {
        echo '<div class="card mt-4"><div class="card-header">' . payroll_h(L('payroll_import_result_title')) . '</div><div class="card-body">';
        if (!empty($result['dry_run'])) {
            echo '<div class="alert alert-info">' . payroll_h(L('payroll_import_dry_run_notice')) . '</div>';
        }
        echo '<div class="row g-2 mb-3 small">';
        echo '<div class="col">' . payroll_h(L('payroll_import_rows')) . ': <strong>' . (int)($result['rows'] ?? 0) . '</strong></div>';
        echo '<div class="col">' . payroll_h(L('payroll_import_imported')) . ': <strong>' . (int)($result['imported'] ?? 0) . '</strong></div>';
        echo '<div class="col">' . payroll_h(L('payroll_import_skipped')) . ': <strong>' . (int)($result['skipped'] ?? 0) . '</strong></div>';
        echo '<div class="col">' . payroll_h(L('payroll_import_types_created')) . ': <strong>' . (int)($result['types_created'] ?? 0) . '</strong></div>';
        echo '<div class="col">' . payroll_h(L('payroll_import_encoding')) . ': <strong>' . payroll_h($result['encoding'] ?? '') . '</strong></div>';
        echo '<div class="col">' . payroll_h(L('payroll_import_delimiter')) . ': <strong>' . payroll_h($result['delimiter'] ?? '') . '</strong></div>';
        if (!empty($result['stored'])) {
            echo '<div class="col-12 text-muted">' . payroll_h(L('payroll_import_stored')) . '</div>';
        }
        echo '</div>';
        $errors = $result['errors'] ?? [];
        if (empty($errors)) {
            echo '<div class="alert alert-success mb-0">' . payroll_h(L('payroll_import_no_errors')) . '</div>';
        } else {
            echo '<h6>' . payroll_h(L('payroll_import_errors')) . ' (' . count($errors) . ')</h6>';
            echo '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr>'
                . '<th>' . payroll_h(L('payroll_import_row')) . '</th>'
                . '<th>' . payroll_h(L('payroll_import_error')) . '</th></tr></thead><tbody>';
            foreach ($errors as $error) {
                echo '<tr><td>' . (int)($error['row'] ?? 0) . '</td><td>' . payroll_h($error['message'] ?? '') . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div></div>';
    }
}

function payroll_page_runs(): void
{
    global $isAr;
    $yearFilter = (int)($_GET['year'] ?? 0);
    $sql = "SELECT * FROM " . table('payroll_runs') . " WHERE omitted=0";
    $params = [];
    if ($yearFilter > 0) {
        $sql .= " AND payroll_year=?";
        $params[] = $yearFilter;
    }
    $sql .= " ORDER BY payroll_year DESC, payroll_month DESC, id DESC";
    $runs = fetchAll($sql, $params);

    payroll_page_title(L('payroll_runs'), '');
    echo '<div class="row g-3 mb-4"><div class="col-md-6"><div class="card"><div class="card-body">';
    echo '<h6>' . payroll_h(L('payroll_create_run')) . '</h6>';
    echo '<form method="post" class="row g-2 align-items-end"><input type="hidden" name="payroll_action" value="run_create">';
    echo '<div class="col"><label class="form-label">' . payroll_h(L('payroll_year')) . '</label>';
    payroll_year_input('payroll_year', (int)date('Y'));
    echo '</div><div class="col"><label class="form-label">' . payroll_h(L('payroll_month')) . '</label>';
    payroll_month_select('payroll_month', (int)date('n'));
    echo '</div><div class="col-auto"><button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>' . payroll_h(L('payroll_create_run')) . '</button></div>';
    echo '</form>';
    echo '<div class="form-text mt-2">' . payroll_h(L('payroll_run_recalc_hint')) . '</div>';
    echo '</div></div></div>';
    echo '<div class="col-md-6"><div class="card"><div class="card-body">';
    echo '<h6>' . payroll_h(L('filter')) . '</h6>';
    echo '<form method="get" class="row g-2 align-items-end"><input type="hidden" name="module" value="payroll"><input type="hidden" name="a" value="runs">';
    echo '<div class="col"><label class="form-label">' . payroll_h(L('payroll_year')) . '</label>';
    echo '<input type="number" class="form-control" name="year" value="' . ($yearFilter ?: '') . '" min="2000" max="2100"></div>';
    echo '<div class="col-auto"><button type="submit" class="btn btn-outline-primary">' . payroll_h(L('search')) . '</button></div>';
    echo '</form></div></div></div></div>';

    echo '<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>'
        . '<th>' . payroll_h(L('payroll_period')) . '</th>'
        . '<th>' . payroll_h(L('payroll_employee_count')) . '</th>'
        . '<th>' . payroll_h(L('payroll_gross_pay')) . '</th>'
        . '<th>' . payroll_h(L('payroll_total_deductions')) . '</th>'
        . '<th>' . payroll_h(L('payroll_net_salary')) . '</th>'
        . '<th>' . payroll_h(L('payroll_run_status')) . '</th>'
        . '<th></th></tr></thead><tbody>';
    if (empty($runs)) {
        echo '<tr><td colspan="7" class="text-center text-muted py-3">' . payroll_h(L('no_records')) . '</td></tr>';
    } else {
        foreach ($runs as $run) {
            echo '<tr>'
                . '<td class="fw-bold">' . payroll_h(L('payroll_month_' . (int)$run['payroll_month'])) . ' ' . (int)$run['payroll_year'] . '</td>'
                . '<td>' . (int)$run['employee_count'] . '</td>'
                . '<td>' . payroll_h(payroll_fmt($run['gross_total'])) . '</td>'
                . '<td>' . payroll_h(payroll_fmt($run['deductions_total'])) . '</td>'
                . '<td>' . payroll_h(payroll_fmt($run['net_total'])) . '</td>'
                . '<td>' . payroll_status_badge((string)$run['status']) . '</td>'
                . '<td><a class="btn btn-sm btn-outline-primary" href="?module=payroll&a=run&id=' . (int)$run['id'] . '">' . payroll_h(L('view')) . '</a></td></tr>';
        }
    }
    echo '</tbody></table></div></div></div>';
}

function payroll_page_run(): void
{
    global $isAr, $canEdit;
    $id = (int)($_GET['id'] ?? 0);
    $run = fetchRow("SELECT * FROM " . table('payroll_runs') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
    if (!$run) {
        set_flash('error', L('payroll_run_not_found'));
        payroll_redirect('?module=payroll&a=runs');
    }
    $items = fetchAll("SELECT * FROM " . table('payroll_run_items') . " WHERE run_id=? AND omitted=0 ORDER BY empfullname", [$id]);
    $isDraft = $run['status'] === 'draft';
    $currency = (string)payroll_setting('currency', 'EGP');

    // Export handling
    $export = strtolower((string)($_GET['export'] ?? ''));
    if ($export !== '' && in_array($export, ['csv', 'xlsx', 'docx', 'pdf'], true)) {
        $headers = [L('payroll_employee_name'), L('payroll_basic'), L('payroll_gross_pay'), L('payroll_taxable_base'), L('payroll_income_tax'), L('payroll_insurance_base'), L('payroll_employee_insurance'), L('payroll_total_deductions'), L('payroll_net_salary')];
        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                $isAr ? ($item['empfullnamear'] ?: $item['empfullname']) : $item['empfullname'],
                payroll_fmt($item['basic_salary']), payroll_fmt($item['gross_pay']), payroll_fmt($item['taxable_base']),
                payroll_fmt($item['tax_amount']), payroll_fmt($item['insurance_base']), payroll_fmt($item['insurance_amount']),
                payroll_fmt($item['deductions_total']), payroll_fmt($item['net_salary']),
            ];
        }
        do_export($export, 'payroll_run_' . $run['payroll_year'] . '_' . $run['payroll_month'], L('payroll_run_details'), $headers, $rows);
        return;
    }
    $exportLines = strtolower((string)($_GET['export_lines'] ?? ''));
    if ($exportLines !== '' && in_array($exportLines, ['csv', 'xlsx', 'docx', 'pdf'], true)) {
        $headers = [L('payroll_employee_name'), L('payroll_line_code'), L('payroll_line_type'), L('payroll_line_base'), L('payroll_line_rate'), L('payroll_line_amount'), L('payroll_line_taxable'), L('payroll_line_insurable')];
        $rows = [];
        foreach ($items as $item) {
            $lines = fetchAll("SELECT * FROM " . table('payroll_run_lines') . " WHERE item_id=? AND omitted=0 ORDER BY sort_order, id", [(int)$item['id']]);
            foreach ($lines as $line) {
                $rows[] = [
                    $isAr ? ($item['empfullnamear'] ?: $item['empfullname']) : $item['empfullname'],
                    $line['type_code'], $line['type_kind'], payroll_fmt($line['base_amount']),
                    payroll_fmt($line['rate']), payroll_fmt($line['amount']),
                    (int)$line['taxable'] ? L('yes') : L('no'),
                    (int)$line['insurable'] ? L('yes') : L('no'),
                ];
            }
        }
        do_export($exportLines, 'payroll_run_lines_' . $run['payroll_year'] . '_' . $run['payroll_month'], L('payroll_run_lines'), $headers, $rows);
        return;
    }

    payroll_page_title(L('payroll_run_details'), L('payroll_month_' . (int)$run['payroll_month']) . ' ' . (int)$run['payroll_year']);

    echo '<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3 payroll-no-print">';
    echo '<div>' . payroll_status_badge((string)$run['status']) . ' <span class="text-muted ms-2">' . payroll_h(L('payroll_period')) . ': '
        . payroll_h($run['period_start']) . ' to ' . payroll_h($run['period_end']) . '</span></div>';
    echo '<div class="d-flex gap-2 flex-wrap">';
    payroll_export_buttons('?module=payroll&a=run&id=' . $id . '&');
    echo '<a class="btn btn-sm btn-outline-secondary" href="?module=payroll&a=runs">' . payroll_h(L('payroll_back_to_runs')) . '</a>';
    echo '</div></div>';

    // Summary cards
    echo '<div class="row g-2 mb-3">';
    $summary = [
        [L('payroll_employee_count'), (int)$run['employee_count'], ''],
        [L('payroll_gross_pay'), payroll_fmt($run['gross_total']), $currency],
        [L('payroll_taxable_base'), payroll_fmt($run['taxable_total']), $currency],
        [L('payroll_income_tax'), payroll_fmt($run['tax_total']), $currency],
        [L('payroll_employee_insurance'), payroll_fmt($run['insurance_total']), $currency],
        [L('payroll_employer_insurance'), payroll_fmt($run['employer_insurance_total']), $currency],
        [L('payroll_total_deductions'), payroll_fmt($run['deductions_total']), $currency],
        [L('payroll_net_salary'), payroll_fmt($run['net_total']), $currency],
    ];
    foreach ($summary as $card) {
        echo '<div class="col-6 col-md-3"><div class="card"><div class="card-body py-2">'
            . '<div class="small text-muted">' . payroll_h($card[0]) . '</div>'
            . '<div class="fw-bold">' . payroll_h($card[1]) . ' ' . payroll_h($card[2]) . '</div>'
            . '</div></div></div>';
    }
    echo '</div>';

    // Items table
    echo '<div class="card mb-3"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>'
        . '<th>' . payroll_h(L('payroll_employee_name')) . '</th>'
        . '<th>' . payroll_h(L('payroll_basic')) . '</th>'
        . '<th>' . payroll_h(L('payroll_gross_pay')) . '</th>'
        . '<th>' . payroll_h(L('payroll_taxable_base')) . '</th>'
        . '<th>' . payroll_h(L('payroll_income_tax')) . '</th>'
        . '<th>' . payroll_h(L('payroll_insurance_base')) . '</th>'
        . '<th>' . payroll_h(L('payroll_employee_insurance')) . '</th>'
        . '<th>' . payroll_h(L('payroll_total_deductions')) . '</th>'
        . '<th>' . payroll_h(L('payroll_net_salary')) . '</th>'
        . '<th></th></tr></thead><tbody>';
    if (empty($items)) {
        echo '<tr><td colspan="10" class="text-center text-muted py-3">' . payroll_h(L('payroll_no_items')) . '</td></tr>';
    } else {
        foreach ($items as $item) {
            echo '<tr>'
                . '<td>' . payroll_h($isAr ? ($item['empfullnamear'] ?: $item['empfullname']) : $item['empfullname']) . '</td>'
                . '<td>' . payroll_h(payroll_fmt($item['basic_salary'])) . '</td>'
                . '<td>' . payroll_h(payroll_fmt($item['gross_pay'])) . '</td>'
                . '<td>' . payroll_h(payroll_fmt($item['taxable_base'])) . '</td>'
                . '<td>' . payroll_h(payroll_fmt($item['tax_amount'])) . '</td>'
                . '<td>' . payroll_h(payroll_fmt($item['insurance_base'])) . '</td>'
                . '<td>' . payroll_h(payroll_fmt($item['insurance_amount'])) . '</td>'
                . '<td>' . payroll_h(payroll_fmt($item['deductions_total'])) . '</td>'
                . '<td class="fw-bold">' . payroll_h(payroll_fmt($item['net_salary'])) . '</td>'
                . '<td><a class="btn btn-sm btn-outline-primary" href="?module=payroll&a=payslip&run=' . $id . '&item=' . (int)$item['id'] . '"><i class="bi bi-receipt"></i></a></td></tr>';
        }
    }
    echo '</tbody></table></div></div></div>';

    // Actions
    echo '<div class="d-flex flex-wrap gap-2 payroll-no-print">';
    if ($isDraft) {
        echo '<form method="post" class="d-inline"><input type="hidden" name="payroll_action" value="run_recalculate"><input type="hidden" name="id" value="' . $id . '">'
            . '<button class="btn btn-warning" type="submit" onclick="return confirm(\'' . payroll_h(L('payroll_run_recalc_hint')) . '\')"><i class="bi bi-arrow-repeat me-1"></i>' . payroll_h(L('payroll_recalculate')) . '</button></form>';
        echo '<form method="post" class="d-inline"><input type="hidden" name="payroll_action" value="run_finalize"><input type="hidden" name="id" value="' . $id . '">'
            . '<button class="btn btn-success" type="submit" onclick="return confirm(\'' . payroll_h(L('payroll_finalize_confirm')) . '\')"><i class="bi bi-check-lg me-1"></i>' . payroll_h(L('payroll_finalize')) . '</button></form>';
        echo '<form method="post" class="d-inline"><input type="hidden" name="payroll_action" value="run_omit"><input type="hidden" name="id" value="' . $id . '">'
            . '<button class="btn btn-outline-danger" type="submit" onclick="return confirm(\'' . payroll_h(L('payroll_omit_run_confirm')) . '\')"><i class="bi bi-trash me-1"></i>' . payroll_h(L('payroll_omit_run')) . '</button></form>';
    } else {
        echo '<form method="post" class="d-inline"><input type="hidden" name="payroll_action" value="run_reopen"><input type="hidden" name="id" value="' . $id . '">'
            . '<button class="btn btn-outline-warning" type="submit" onclick="return confirm(\'' . payroll_h(L('payroll_reopen_confirm')) . '\')"><i class="bi bi-unlock me-1"></i>' . payroll_h(L('payroll_reopen')) . '</button></form>';
    }
    echo '</div>';

    if (!$isDraft && !empty($run['finalizedby'])) {
        echo '<div class="text-muted small mt-3">' . payroll_h(L('payroll_finalized_by')) . ': ' . payroll_h($run['finalizedby'])
            . ' &middot; ' . payroll_h(L('payroll_finalized_at')) . ': ' . payroll_h($run['finalized_at']) . '</div>';
    }
}

function payroll_page_payslip(): void
{
    global $isAr;
    $runId = (int)($_GET['run'] ?? 0);
    $itemId = (int)($_GET['item'] ?? 0);
    $run = fetchRow("SELECT * FROM " . table('payroll_runs') . " WHERE id=? AND omitted=0 LIMIT 1", [$runId]);
    $item = fetchRow("SELECT * FROM " . table('payroll_run_items') . " WHERE id=? AND run_id=? AND omitted=0 LIMIT 1", [$itemId, $runId]);
    if (!$run || !$item) {
        set_flash('error', L('payroll_run_not_found'));
        payroll_redirect('?module=payroll&a=runs');
    }
    $lines = fetchAll("SELECT * FROM " . table('payroll_run_lines') . " WHERE item_id=? AND omitted=0 ORDER BY sort_order, id", [$itemId]);
    $payLines = array_values(array_filter($lines, fn($l) => $l['type_kind'] === 'payability'));
    $dedLines = array_values(array_filter($lines, fn($l) => $l['type_kind'] === 'deduction'));
    $details = json_decode((string)$item['details_json'], true) ?: [];
    $taxInfo = $details['tax_info'] ?? [];
    $insuranceInfo = $details['insurance_info'] ?? [];
    $currency = (string)payroll_setting('currency', 'EGP');

    $export = strtolower((string)($_GET['export'] ?? ''));
    if ($export !== '' && in_array($export, ['csv', 'xlsx', 'docx', 'pdf'], true)) {
        $headers = [L('payroll_line_type'), L('payroll_line_code'), L('payroll_line_base'), L('payroll_line_rate'), L('payroll_line_amount')];
        $rows = [];
        foreach ($lines as $line) {
            $rows[] = [
                ($isAr ? ($line['name_ar'] ?: $line['name']) : $line['name']) . ' (' . $line['type_code'] . ')',
                $line['type_code'], payroll_fmt($line['base_amount']), payroll_fmt($line['rate']), payroll_fmt($line['amount']),
            ];
        }
        do_export($export, 'payslip_' . $item['empid'] . '_' . $run['payroll_year'] . '_' . $run['payroll_month'], L('payroll_payslip'), $headers, $rows);
        return;
    }

    echo '<div class="payslip-container">';
    echo '<div class="d-flex justify-content-between align-items-center mb-3 payroll-no-print">';
    echo '<a class="btn btn-sm btn-outline-secondary" href="?module=payroll&a=run&id=' . $runId . '"><i class="bi bi-arrow-left me-1"></i>' . payroll_h(L('payroll_back_to_runs')) . '</a>';
    echo '<div class="d-flex gap-2">';
    payroll_export_buttons('?module=payroll&a=payslip&run=' . $runId . '&item=' . $itemId . '&');
    echo '<button class="btn btn-sm btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>' . payroll_h(L('payroll_print')) . '</button>';
    echo '</div></div>';

    echo '<div class="card"><div class="card-body">';
    echo '<div class="text-center mb-3"><h4>' . payroll_h(L('payroll_payslip')) . '</h4>'
        . '<div class="text-muted">' . payroll_h(L('payroll_payslip_for'), $isAr ? ($item['empfullnamear'] ?: $item['empfullname']) : $item['empfullname']) . '</div>'
        . '<div class="small">' . payroll_h(L('payroll_period')) . ': ' . payroll_h(L('payroll_month_' . (int)$run['payroll_month'])) . ' ' . (int)$run['payroll_year'] . '</div></div>';

    echo '<div class="row g-2 mb-3 small">';
    echo '<div class="col-md-6"><strong>' . payroll_h(L('payroll_national_id')) . ':</strong> ' . payroll_h($item['nid'] ?? '') . '</div>';
    echo '<div class="col-md-6"><strong>' . payroll_h(L('payroll_insurance_number')) . ':</strong> ' . payroll_h($item['insuranceid'] ?? '') . '</div>';
    echo '<div class="col-md-6"><strong>' . payroll_h(L('payroll_degree')) . ':</strong> ' . payroll_h($isAr ? ($item['empfullnamear'] ? '' : '') : '') . '</div>';
    echo '</div>';

    echo '<div class="row g-3">';
    // Payabilities
    echo '<div class="col-md-6"><h6 class="text-success">' . payroll_h(L('payroll_payabilities')) . '</h6>';
    echo '<table class="table table-sm"><tbody>';
    foreach ($payLines as $line) {
        echo '<tr><td>' . payroll_h($isAr ? ($line['name_ar'] ?: $line['name']) : $line['name']) . ' <small class="text-muted">' . payroll_h($line['type_code']) . '</small></td>'
            . '<td class="text-end">' . payroll_h(payroll_fmt($line['amount'])) . '</td></tr>';
    }
    echo '<tr class="fw-bold"><td>' . payroll_h(L('payroll_gross_pay')) . '</td><td class="text-end">' . payroll_h(payroll_fmt($item['gross_pay'])) . '</td></tr>';
    echo '</tbody></table></div>';

    // Deductions
    echo '<div class="col-md-6"><h6 class="text-danger">' . payroll_h(L('payroll_deductions')) . '</h6>';
    echo '<table class="table table-sm"><tbody>';
    foreach ($dedLines as $line) {
        echo '<tr><td>' . payroll_h($isAr ? ($line['name_ar'] ?: $line['name']) : $line['name']) . ' <small class="text-muted">' . payroll_h($line['type_code']) . '</small></td>'
            . '<td class="text-end">' . payroll_h(payroll_fmt($line['amount'])) . '</td></tr>';
    }
    echo '<tr class="fw-bold"><td>' . payroll_h(L('payroll_total_deductions')) . '</td><td class="text-end">' . payroll_h(payroll_fmt($item['deductions_total'])) . '</td></tr>';
    echo '</tbody></table></div>';
    echo '</div>';

    // Tax & insurance detail
    echo '<hr><div class="row g-3 small">';
    echo '<div class="col-md-6"><strong>' . payroll_h(L('payroll_taxable_base')) . ':</strong> ' . payroll_h(payroll_fmt($item['taxable_base'])) . ' ' . payroll_h($currency) . '</div>';
    echo '<div class="col-md-6"><strong>' . payroll_h(L('payroll_income_tax')) . ':</strong> ' . payroll_h(payroll_fmt($item['tax_amount'])) . ' ' . payroll_h($currency) . '</div>';
    echo '<div class="col-md-6"><strong>' . payroll_h(L('payroll_insurance_base')) . ':</strong> ' . payroll_h(payroll_fmt($item['insurance_base'])) . ' ' . payroll_h($currency) . '</div>';
    echo '<div class="col-md-6"><strong>' . payroll_h(L('payroll_employee_insurance')) . ':</strong> ' . payroll_h(payroll_fmt($item['insurance_amount'])) . ' ' . payroll_h($currency) . '</div>';
    echo '<div class="col-md-6"><strong>' . payroll_h(L('payroll_employer_insurance')) . ':</strong> ' . payroll_h(payroll_fmt($item['employer_insurance'])) . ' ' . payroll_h($currency) . '</div>';
    echo '</div>';

    if (!empty($taxInfo['breakdown'])) {
        echo '<hr><h6>' . payroll_h(L('payroll_tax_breakdown')) . '</h6>';
        echo '<div class="table-responsive"><table class="table table-sm"><thead><tr>'
            . '<th>' . payroll_h(L('payroll_from_amount')) . '</th>'
            . '<th>' . payroll_h(L('payroll_to_amount')) . '</th>'
            . '<th>' . payroll_h(L('payroll_band_rate')) . '</th>'
            . '<th>' . payroll_h(L('payroll_taxable_amount')) . '</th>'
            . '<th>' . payroll_h(L('payroll_tax_amount')) . '</th></tr></thead><tbody>';
        foreach ($taxInfo['breakdown'] as $band) {
            echo '<tr>'
                . '<td>' . payroll_fmt($band['from']) . '</td>'
        . '<td>' . ($band['to'] === null ? 'inf' : payroll_fmt($band['to'])) . '</td>'
                . '<td>' . payroll_fmt($band['rate']) . '%</td>'
                . '<td>' . payroll_fmt($band['amount']) . '</td>'
                . '<td>' . payroll_fmt($band['tax']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    echo '<hr><div class="text-center"><h5>' . payroll_h(L('payroll_net_pay')) . ': '
        . payroll_h(payroll_fmt($item['net_salary'])) . ' ' . payroll_h($currency) . '</h5></div>';

    echo '<div class="row mt-4 small text-muted"><div class="col-6 text-center">' . payroll_h(L('payroll_employee_signature')) . '</div>'
        . '<div class="col-6 text-center">' . payroll_h(L('payroll_authorized_signature')) . '</div></div>';
    echo '</div></div></div>';
}

function payroll_page_year_end(): void
{
    global $isAr;
    $year = (int)($_GET['year'] ?? date('Y'));
    if ($year < 2000 || $year > 2100) {
        $year = (int)date('Y');
    }
    $summary = payroll_year_summary($year);
    $currency = (string)payroll_setting('currency', 'EGP');

    $export = strtolower((string)($_GET['export'] ?? ''));
    if ($export !== '' && in_array($export, ['csv', 'xlsx', 'docx', 'pdf'], true)) {
        $headers = [L('payroll_employee_name'), L('payroll_months_paid'), L('payroll_annual_gross'), L('payroll_taxable_income'), L('payroll_tax_withheld'), L('payroll_annual_tax_due'), L('payroll_tax_offset'), L('payroll_status'), L('payroll_annual_insurance_base'), L('payroll_annual_insurance'), L('payroll_annual_net')];
        $rows = [];
        foreach ($summary['rows'] as $row) {
            $statusLabel = $row['tax_status'] === 'creditor' ? L('payroll_tax_creditor') : ($row['tax_status'] === 'debtor' ? L('payroll_tax_debtor') : L('payroll_tax_settled'));
            $rows[] = [
                $isAr ? ($row['empfullnamear'] ?: $row['empfullname']) : $row['empfullname'],
                (int)$row['months'], payroll_fmt($row['gross_total']), payroll_fmt($row['taxable_total']),
                payroll_fmt($row['tax_paid']), payroll_fmt($row['tax_due']), payroll_fmt($row['tax_offset']),
                $statusLabel, payroll_fmt($row['insurance_base_total']), payroll_fmt($row['insurance_total']), payroll_fmt($row['net_total']),
            ];
        }
        $totals = $summary['totals'];
        $rows[] = [
            L('total'), $totals['employees'], payroll_fmt($totals['gross']), payroll_fmt($totals['taxable']),
            payroll_fmt($totals['tax_paid']), payroll_fmt($totals['tax_due']), payroll_fmt($totals['tax_offset']),
            '', payroll_fmt($totals['insurance_base']), payroll_fmt($totals['insurance']), payroll_fmt($totals['net']),
        ];
        do_export($export, 'payroll_year_end_' . $year, L('payroll_year_end_title'), $headers, $rows);
        return;
    }

    payroll_page_title(L('payroll_year_end_title'), L('payroll_year_end_hint'));

    echo '<form method="get" class="mb-3 d-flex gap-2 align-items-end"><input type="hidden" name="module" value="payroll"><input type="hidden" name="a" value="year_end">';
    echo '<div><label class="form-label">' . payroll_h(L('payroll_year')) . '</label>'
        . '<input type="number" class="form-control" name="year" value="' . $year . '" min="2000" max="2100"></div>';
    echo '<button type="submit" class="btn btn-primary">' . payroll_h(L('search')) . '</button>';
    echo '<div class="ms-auto">' . payroll_export_buttons('?module=payroll&a=year_end&year=' . $year . '&') . '</div>';
    echo '</form>';

    $totals = $summary['totals'];
    echo '<div class="row g-2 mb-3">';
    $cards = [
        [L('payroll_employee_count'), (int)$totals['employees'], ''],
        [L('payroll_total_tax_paid'), payroll_fmt($totals['tax_paid']), $currency],
        [L('payroll_total_tax_due'), payroll_fmt($totals['tax_due']), $currency],
        [L('payroll_total_net_offset'), payroll_fmt($totals['tax_offset']), $currency],
        [L('payroll_creditors'), (int)$totals['creditors'], ''],
        [L('payroll_debtors'), (int)$totals['debtors'], ''],
    ];
    foreach ($cards as $card) {
        echo '<div class="col-6 col-md-2"><div class="card"><div class="card-body py-2">'
            . '<div class="small text-muted">' . payroll_h($card[0]) . '</div>'
            . '<div class="fw-bold">' . payroll_h($card[1]) . ' ' . payroll_h($card[2]) . '</div>'
            . '</div></div></div>';
    }
    echo '</div>';

    echo '<div class="alert alert-info small">' . payroll_h(L('payroll_offset_positive_hint')) . ' ' . payroll_h(L('payroll_offset_negative_hint')) . '</div>';

    if (empty($summary['rows'])) {
        echo '<div class="alert alert-warning">' . payroll_h(L('payroll_no_final_runs')) . '</div>';
        return;
    }

    echo '<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>'
        . '<th>' . payroll_h(L('payroll_employee_name')) . '</th>'
        . '<th>' . payroll_h(L('payroll_months_paid')) . '</th>'
        . '<th>' . payroll_h(L('payroll_annual_gross')) . '</th>'
        . '<th>' . payroll_h(L('payroll_taxable_income')) . '</th>'
        . '<th>' . payroll_h(L('payroll_tax_withheld')) . '</th>'
        . '<th>' . payroll_h(L('payroll_annual_tax_due')) . '</th>'
        . '<th>' . payroll_h(L('payroll_tax_offset')) . '</th>'
        . '<th>' . payroll_h(L('payroll_status')) . '</th>'
        . '<th>' . payroll_h(L('payroll_annual_insurance_base')) . '</th>'
        . '<th>' . payroll_h(L('payroll_annual_insurance')) . '</th>'
        . '<th>' . payroll_h(L('payroll_annual_net')) . '</th></tr></thead><tbody>';
    foreach ($summary['rows'] as $row) {
        $statusLabel = $row['tax_status'] === 'creditor' ? L('payroll_tax_creditor') : ($row['tax_status'] === 'debtor' ? L('payroll_tax_debtor') : L('payroll_tax_settled'));
        $statusClass = $row['tax_status'] === 'creditor' ? 'text-success' : ($row['tax_status'] === 'debtor' ? 'text-danger' : 'text-muted');
        echo '<tr>'
            . '<td>' . payroll_h($isAr ? ($row['empfullnamear'] ?: $row['empfullname']) : $row['empfullname']) . '</td>'
            . '<td>' . (int)$row['months'] . '</td>'
            . '<td>' . payroll_h(payroll_fmt($row['gross_total'])) . '</td>'
            . '<td>' . payroll_h(payroll_fmt($row['taxable_total'])) . '</td>'
            . '<td>' . payroll_h(payroll_fmt($row['tax_paid'])) . '</td>'
            . '<td>' . payroll_h(payroll_fmt($row['tax_due'])) . '</td>'
            . '<td class="' . $statusClass . ' fw-bold">' . payroll_h(payroll_fmt($row['tax_offset'])) . '</td>'
            . '<td class="' . $statusClass . '">' . payroll_h($statusLabel) . '</td>'
            . '<td>' . payroll_h(payroll_fmt($row['insurance_base_total'])) . '</td>'
            . '<td>' . payroll_h(payroll_fmt($row['insurance_total'])) . '</td>'
            . '<td>' . payroll_h(payroll_fmt($row['net_total'])) . '</td></tr>';
    }
    // Totals row
    echo '<tr class="fw-bold table-light">'
        . '<td>' . payroll_h(L('total')) . '</td>'
        . '<td>' . (int)$totals['employees'] . '</td>'
        . '<td>' . payroll_h(payroll_fmt($totals['gross'])) . '</td>'
        . '<td>' . payroll_h(payroll_fmt($totals['taxable'])) . '</td>'
        . '<td>' . payroll_h(payroll_fmt($totals['tax_paid'])) . '</td>'
        . '<td>' . payroll_h(payroll_fmt($totals['tax_due'])) . '</td>'
        . '<td>' . payroll_h(payroll_fmt($totals['tax_offset'])) . '</td>'
        . '<td></td>'
        . '<td>' . payroll_h(payroll_fmt($totals['insurance_base'])) . '</td>'
        . '<td>' . payroll_h(payroll_fmt($totals['insurance'])) . '</td>'
        . '<td>' . payroll_h(payroll_fmt($totals['net'])) . '</td></tr>';
    echo '</tbody></table></div></div></div>';

    echo '<div class="text-muted small mt-2">' . payroll_h(L('payroll_finalized_runs_only')) . ' (' . (int)$summary['run_count'] . ' ' . payroll_h(L('payroll_runs')) . ')</div>';
}


// =========================================================================
// Router
// =========================================================================

// Template downloads (CSV)
if (isset($_GET['template'])) {
    $template = (string)$_GET['template'];
    $filename = 'payroll_template';
    $headers = [];
    $rows = [];
    if ($template === 'basic') {
        $filename = 'payroll_basic_template';
        $headers = ['nid', 'basic_salary'];
        $rows = [['28106010102098', '10000'], ['27106010102098', '5000']];
    } elseif ($template === 'wide') {
        $filename = 'payroll_wide_template';
        $headers = ['nid', 'LOAN', 'PHONE_BILL'];
        $rows = [['28106010102098', '500', '150'], ['27106010102098', '250', '75']];
    } elseif ($template === 'long') {
        $filename = 'payroll_long_template';
        $headers = ['nid', 'code', 'amount'];
        $rows = [['28106010102098', 'LOAN', '500'], ['28106010102098', 'PHONE_BILL', '150']];
    }
    if (!empty($headers)) {
        export_clean_buffers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
}

// Page dispatch
switch ($page) {
    case 'types':
        payroll_page_types();
        break;
    case 'type_edit':
        payroll_page_type_edit();
        break;
    case 'tax':
        payroll_page_tax();
        break;
    case 'tax_edit':
        payroll_page_tax_edit();
        break;
    case 'insurance':
        payroll_page_insurance();
        break;
    case 'insurance_edit':
        payroll_page_insurance_edit();
        break;
    case 'employees':
        payroll_page_employees();
        break;
    case 'employee':
        payroll_page_employee();
        break;
    case 'import':
        payroll_page_import();
        break;
    case 'runs':
        payroll_page_runs();
        break;
    case 'run':
        payroll_page_run();
        break;
    case 'payslip':
        payroll_page_payslip();
        break;
    case 'year_end':
        payroll_page_year_end();
        break;
    case 'settings':
        payroll_page_settings();
        break;
    case 'dashboard':
    default:
        payroll_page_dashboard();
        break;

}