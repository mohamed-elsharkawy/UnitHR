<?php
/**
 * UnitHR - Payroll calculation engine
 *
 * Loads the configurable payability/deduction types, employee assignments,
 * period values (CSV imports), Egyptian tax schedules and social-insurance
 * rules, then produces a line-by-line payroll result.
 *
 * Nothing in this file writes data: it is a pure calculation layer used by
 * the Payroll screens, CSV importer and year-end reconciliation.
 */

if (!function_exists('table')) {
    require_once __DIR__ . '/../../includes/config.php';
}
if (!function_exists('db')) {
    require_once __DIR__ . '/../../includes/db.php';
}

/** Round a monetary value to two decimals. */
function payroll_money($value): float
{
    return round((float)$value, 2);
}

/** True when a database value is present (not null and not an empty string). */
function payroll_has_value($value): bool
{
    return $value !== null && $value !== '';
}

/** Apply a type's minimum and maximum limits to an amount. */
function payroll_apply_limits(float $amount, array $type): float
{
    if ($amount < 0) {
        $amount = 0;
    }
    if (payroll_has_value($type['min_amount'] ?? null)) {
        $amount = max($amount, (float)$type['min_amount']);
    }
    if (payroll_has_value($type['max_amount'] ?? null)) {
        $amount = min($amount, (float)$type['max_amount']);
    }
    return payroll_money($amount);
}

/** Round down to a configured step (Egyptian tax law uses EGP 10). */
function payroll_round_down(float $amount, float $step): float
{
    if ($step <= 0) {
        return payroll_money(max(0, $amount));
    }
    return payroll_money(floor(($amount + 1e-9) / $step) * $step);
}

/** Full years between two dates, used for seniority-based types. */
function payroll_service_years(?string $startDate, ?string $endDate): int
{
    if (empty($startDate) || empty($endDate)) {
        return 0;
    }
    try {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
    } catch (Throwable $e) {
        return 0;
    }
    if ($start > $end) {
        return 0;
    }
    return (int)$start->diff($end)->y;
}

/** First and last day of a payroll period. */
function payroll_period_bounds(int $year, int $month): array
{
    $month = max(1, min(12, $month));
    $start = sprintf('%04d-%02d-01', $year, $month);
    return [$start, date('Y-m-t', strtotime($start))];
}

/** Read all payroll settings as key => value. */
function payroll_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    foreach (fetchAll("SELECT setting_key, setting_value FROM " . table('payroll_settings') . " WHERE omitted=0") as $row) {
        $cache[$row['setting_key']] = $row['setting_value'];
    }
    return $cache;
}

/** A single payroll setting with fallback. */
function payroll_setting(string $key, $default = null)
{
    $settings = payroll_settings();
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

/**
 * Build a full calculation context for a period.
 *
 * @return array{
 *   year:int,month:int,period_start:string,period_end:string,
 *   types:array,types_by_id:array,types_by_code:array,degrees:array,degree_amounts:array,
 *   tax_year:?array,tax_brackets:array,insurance_rule:?array,
 *   employees:array,settings_by_emp:array,assign_by_emp:array,values_by_emp:array,
 *   settings:array
 * }
 */
function payroll_build_context(int $year, int $month): array
{
    [$periodStart, $periodEnd] = payroll_period_bounds($year, $month);

    $types = fetchAll("SELECT * FROM " . table('payroll_types') . " WHERE omitted=0 ORDER BY sort_order ASC, id ASC");
    $typesById = [];
    $typesByCode = [];
    foreach ($types as $type) {
        $typesById[(int)$type['id']] = $type;
        $typesByCode[strtoupper((string)$type['code'])] = $type;
    }

    $degrees = fetchAll("SELECT id, degreename, degreenamear FROM " . table('empdegrees') . " WHERE omitted=0 ORDER BY id ASC");
    $degreeAmounts = [];
    foreach (fetchAll("SELECT * FROM " . table('payroll_type_degrees') . " WHERE omitted=0") as $row) {
        $degreeAmounts[(int)$row['type_id']][(int)$row['degree_id']] = (float)$row['amount'];
    }

    $taxYear = fetchRow(
        "SELECT * FROM " . table('payroll_tax_years') . " WHERE tax_year=? AND omitted=0 LIMIT 1",
        [$year]
    );
    if (!$taxYear) {
        $taxYear = fetchRow(
            "SELECT * FROM " . table('payroll_tax_years') . " WHERE tax_year<=? AND omitted=0 ORDER BY tax_year DESC LIMIT 1",
            [$year]
        );
    }
    $taxBrackets = $taxYear
        ? fetchAll("SELECT * FROM " . table('payroll_tax_brackets') . " WHERE tax_year=? AND omitted=0 ORDER BY schedule_min ASC, bracket_order ASC, id ASC", [(int)$taxYear['tax_year']])
        : [];

    $insuranceRule = fetchRow(
        "SELECT * FROM " . table('payroll_insurance_rules') . " WHERE insurance_year=? AND omitted=0 LIMIT 1",
        [$year]
    );
    if (!$insuranceRule) {
        $insuranceRule = fetchRow(
            "SELECT * FROM " . table('payroll_insurance_rules') . " WHERE insurance_year<=? AND omitted=0 ORDER BY insurance_year DESC LIMIT 1",
            [$year]
        );
    }

    $employees = fetchAll(
        "SELECT id, empfullname, empfullnamear, nid, insuranceid, currentdegreeid,
                currentdegreename, currentdegreenamear, senioritydate, degreeacquireddate, dop, dor
         FROM " . table('employeesmain') . " WHERE omitted=0 ORDER BY empfullname ASC"
    );

    $settingsByEmp = [];
    foreach (fetchAll("SELECT * FROM " . table('payroll_employee_settings') . " WHERE omitted=0") as $row) {
        $settingsByEmp[(int)$row['empid']] = $row;
    }

    $assignByEmp = [];
    foreach (fetchAll("SELECT * FROM " . table('payroll_employee_types') . " WHERE omitted=0") as $row) {
        $assignByEmp[(int)$row['empid']][(int)$row['type_id']] = $row;
    }

    $valuesByEmp = [];
    foreach (fetchAll(
        "SELECT * FROM " . table('payroll_employee_type_values') . " WHERE payroll_year=? AND payroll_month=? AND omitted=0",
        [$year, $month]
    ) as $row) {
        $valuesByEmp[(int)$row['empid']][(int)$row['type_id']] = $row;
    }

    return [
        'year' => $year,
        'month' => $month,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'types' => $types,
        'types_by_id' => $typesById,
        'types_by_code' => $typesByCode,
        'degrees' => $degrees,
        'degree_amounts' => $degreeAmounts,
        'tax_year' => $taxYear ?: null,
        'tax_brackets' => $taxBrackets,
        'insurance_rule' => $insuranceRule ?: null,
        'employees' => $employees,
        'settings_by_emp' => $settingsByEmp,
        'assign_by_emp' => $assignByEmp,
        'values_by_emp' => $valuesByEmp,
        'settings' => payroll_settings(),
    ];
}

/** Whether an assignment is active for the period and not disabled. */
function payroll_assignment_active(?array $assignment, string $periodStart, string $periodEnd): bool
{
    if (!$assignment) {
        return false;
    }
    if (isset($assignment['enabled']) && !(int)$assignment['enabled']) {
        return false;
    }
    if (!empty($assignment['effective_from']) && $assignment['effective_from'] > $periodEnd) {
        return false;
    }
    if (!empty($assignment['effective_to']) && $assignment['effective_to'] < $periodStart) {
        return false;
    }
    return true;
}

/**
 * Whether a type is applicable to an employee.
 * Assignments are required for optional types; an imported period value also
 * makes the type applicable, which keeps CSV-only payroll usable.
 */
function payroll_type_applies(array $type, ?array $assignment, ?array $value, string $periodStart, string $periodEnd): bool
{
    if (!(int)($type['enabled'] ?? 1)) {
        return false;
    }
    if ($assignment !== null) {
        return payroll_assignment_active($assignment, $periodStart, $periodEnd);
    }
    return $value !== null;
}

/** Resolve the rate for a type, preferring employee and CSV overrides. */
function payroll_type_rate(array $type, ?array $assignment, ?array $value): float
{
    if ($value && payroll_has_value($value['rate'] ?? null)) {
        return (float)$value['rate'];
    }
    if ($assignment && payroll_has_value($assignment['rate_override'] ?? null)) {
        return (float)$assignment['rate_override'];
    }
    return (float)$type['rate'];
}

/** Resolve a fixed amount for a type, preferring CSV and employee overrides. */
function payroll_type_fixed_amount(array $type, ?array $assignment, ?array $value, float $fallback = 0.0): float
{
    if ($value && payroll_has_value($value['amount'] ?? null)) {
        return (float)$value['amount'];
    }
    if ($assignment && payroll_has_value($assignment['amount_override'] ?? null)) {
        return (float)$assignment['amount_override'];
    }
    if (payroll_has_value($type['amount'] ?? null) && (float)$type['amount'] != 0.0) {
        return (float)$type['amount'];
    }
    return $fallback;
}

/** Egyptian income tax for an annual amount before the personal exemption. */
function payroll_tax_for_annual(float $annualBeforeExemption, ?array $taxYear, array $brackets): array
{
    $result = [
        'annual_before_exemption' => payroll_money(max(0, $annualBeforeExemption)),
        'personal_exemption' => 0.0,
        'annual_taxable' => 0.0,
        'annual_tax' => 0.0,
        'monthly_tax' => 0.0,
        'schedule_min' => null,
        'schedule_max' => null,
        'breakdown' => [],
        'tax_year' => $taxYear['tax_year'] ?? null,
    ];
    if (!$taxYear || empty($brackets)) {
        return $result;
    }

    $exemption = (float)$taxYear['personal_exemption'];
    $step = (float)$taxYear['round_to'];
    $annualTaxable = payroll_round_down(max(0, $annualBeforeExemption - $exemption), $step);
    $result['personal_exemption'] = payroll_money($exemption);
    $result['annual_taxable'] = $annualTaxable;

    // Group bracket rows by their schedule (the Egyptian high-income tiers).
    $schedules = [];
    foreach ($brackets as $row) {
        $key = (string)$row['schedule_min'] . '|' . (string)$row['schedule_max'];
        if (!isset($schedules[$key])) {
            $schedules[$key] = [
                'min' => (float)$row['schedule_min'],
                'max' => payroll_has_value($row['schedule_max']) ? (float)$row['schedule_max'] : null,
                'rows' => [],
            ];
        }
        $schedules[$key]['rows'][] = $row;
    }
    uasort($schedules, fn($a, $b) => $a['min'] <=> $b['min']);

    $chosen = null;
    foreach ($schedules as $schedule) {
        $inSchedule = $schedule['min'] < $annualTaxable - 1e-9
            && ($schedule['max'] === null || $annualTaxable <= $schedule['max'] + 1e-9);
        if ($inSchedule) {
            $chosen = $schedule;
            break;
        }
    }
    if ($chosen === null) {
        // Zero income uses the lowest schedule; amounts below the first
        // threshold also use the lowest schedule (they produce no tax).
        $chosen = reset($schedules);
        if ($annualTaxable > end($schedules)['min']) {
            $chosen = end($schedules);
        }
    }
    if (!$chosen) {
        return $result;
    }

    $result['schedule_min'] = $chosen['min'];
    $result['schedule_max'] = $chosen['max'];

    $annualTax = 0.0;
    $breakdown = [];
    foreach ($chosen['rows'] as $band) {
        $from = (float)$band['from_amount'];
        $to = payroll_has_value($band['to_amount']) ? (float)$band['to_amount'] : $annualTaxable;
        if ($to < $from) {
            continue;
        }
        $bandAmount = max(0, min($annualTaxable, $to) - $from);
        if ($bandAmount <= 0) {
            continue;
        }
        $rate = (float)$band['rate'];
        $tax = payroll_money($bandAmount * $rate / 100);
        $annualTax += $tax;
        $breakdown[] = [
            'from' => payroll_money($from),
            'to' => payroll_has_value($band['to_amount']) ? payroll_money($to) : null,
            'rate' => $rate,
            'amount' => payroll_money($bandAmount),
            'tax' => $tax,
        ];
    }

    $result['annual_tax'] = payroll_money($annualTax);
    $result['monthly_tax'] = payroll_money($annualTax / 12);
    $result['breakdown'] = $breakdown;
    return $result;
}

/** Clamp and calculate social insurance for a monthly base. */
function payroll_insurance_for_base(float $base, ?array $rule): array
{
    $result = [
        'base' => 0.0,
        'employee_rate' => 0.0,
        'employer_rate' => 0.0,
        'employee' => 0.0,
        'employer' => 0.0,
        'min_base' => 0.0,
        'max_base' => null,
        'insurance_year' => $rule['insurance_year'] ?? null,
    ];
    if (!$rule) {
        return $result;
    }

    $min = (float)$rule['min_base'];
    $max = payroll_has_value($rule['max_base']) ? (float)$rule['max_base'] : null;
    $result['min_base'] = payroll_money($min);
    $result['max_base'] = $max === null ? null : payroll_money($max);
    $result['employee_rate'] = (float)$rule['employee_rate'];
    $result['employer_rate'] = (float)$rule['employer_rate'];

    if ($base <= 0) {
        return $result;
    }
    $base = max($base, $min);
    if ($max !== null) {
        $base = min($base, $max);
    }
    $result['base'] = payroll_money($base);
    $result['employee'] = payroll_money($base * $result['employee_rate'] / 100);
    $result['employer'] = payroll_money($base * $result['employer_rate'] / 100);
    return $result;
}

/**
 * Calculate one employee's payroll for a period.
 *
 * @param array $employee Row from employeesmain.
 * @param array $ctx      Context from payroll_build_context().
 * @return array
 */
function payroll_compute_employee(array $employee, array $ctx): array
{
    $empid = (int)$employee['id'];
    $settings = $ctx['settings_by_emp'][$empid] ?? null;
    $assignments = $ctx['assign_by_emp'][$empid] ?? [];
    $values = $ctx['values_by_emp'][$empid] ?? [];
    $periodStart = $ctx['period_start'];
    $periodEnd = $ctx['period_end'];
    $degreeId = (int)($employee['currentdegreeid'] ?? 0);

    $serviceStart = $employee['senioritydate'] ?: ($employee['dop'] ?: $employee['degreeacquireddate']);
    $serviceYears = payroll_service_years($serviceStart, $periodEnd);

    $basicType = null;
    foreach ($ctx['types'] as $type) {
        if ((int)$type['is_basic'] === 1) {
            $basicType = $type;
            break;
        }
    }
    if (!$basicType && isset($ctx['types_by_code']['BASIC'])) {
        $basicType = $ctx['types_by_code']['BASIC'];
    }

    // Basic salary: current-period CSV value wins, then employee setup.
    $basic = 0.0;
    $basicSource = 'settings';
    if ($basicType) {
        $basicId = (int)$basicType['id'];
        $basicAssignment = $assignments[$basicId] ?? null;
        $basicValue = $values[$basicId] ?? null;
        if ($basicValue && payroll_has_value($basicValue['amount'] ?? null)) {
            $basic = (float)$basicValue['amount'];
            $basicSource = 'import';
        } elseif ($settings) {
            $basic = (float)$settings['basic_salary'];
        } elseif ($basicAssignment && payroll_has_value($basicAssignment['amount_override'] ?? null)) {
            $basic = (float)$basicAssignment['amount_override'];
            $basicSource = 'assignment';
        }
    }
    $basic = payroll_money(max(0, $basic));

    $computedByType = [];
    $baseAmountByType = [];
    $payLines = [];
    $payabilityTypes = [];
    $deductionTypes = [];

    foreach ($ctx['types'] as $type) {
        if (!(int)$type['enabled']) {
            continue;
        }
        if ((int)$type['is_basic']) {
            continue;
        }
        $tid = (int)$type['id'];
        if (($type['calc_method'] ?? '') === 'tax' || ($type['calc_method'] ?? '') === 'insurance') {
            continue;
        }
        $assignment = $assignments[$tid] ?? null;
        $value = $values[$tid] ?? null;
        if (!payroll_type_applies($type, $assignment, $value, $periodStart, $periodEnd)) {
            continue;
        }
        if (($type['kind'] ?? '') === 'payability') {
            $payabilityTypes[] = [$type, $assignment, $value];
        } elseif (($type['kind'] ?? '') === 'deduction') {
            $deductionTypes[] = [$type, $assignment, $value];
        }
    }

    // Add the basic-salary line first (it is always the main payability).
    if ($basicType) {
        $basicLine = [
            'type_id' => (int)$basicType['id'],
            'kind' => 'payability',
            'code' => $basicType['code'],
            'name' => $basicType['name'],
            'name_ar' => $basicType['name_ar'],
            'calc_method' => 'fixed',
            'rate' => 0.0,
            'base_amount' => null,
            'amount' => $basic,
            'before_tax' => (int)$basicType['before_tax'],
            'before_insurance' => (int)$basicType['before_insurance'],
            'sort_order' => (int)$basicType['sort_order'],
            'source' => $basicSource,
            'notes' => null,
        ];
        $payLines[] = $basicLine;
        $computedByType[(int)$basicType['id']] = $basic;
        $baseAmountByType[(int)$basicType['id']] = $basic;
    }

    // Payabilities that are not based on the running gross are calculated first.
    $deferred = [];
    foreach ($payabilityTypes as [$type, $assignment, $value]) {
        $method = (string)$type['calc_method'];
        if ($method === 'percent_gross') {
            $deferred[] = [$type, $assignment, $value];
            continue;
        }
        $line = payroll_calculate_line($type, $assignment, $value, $basic, 0.0, $degreeId, $serviceYears, $ctx, $computedByType);
        if ($line !== null) {
            $payLines[] = $line;
            $computedByType[(int)$type['id']] = $line['amount'];
            $baseAmountByType[(int)$type['id']] = $line['base_amount'] ?? $line['amount'];
        }
    }

    $grossPay = 0.0;
    foreach ($payLines as $line) {
        $grossPay += $line['amount'];
    }
    $grossPay = payroll_money($grossPay);

    // Percent-of-gross payabilities use the total of all other payabilities.
    foreach ($deferred as [$type, $assignment, $value]) {
        $line = payroll_calculate_line($type, $assignment, $value, $basic, $grossPay, $degreeId, $serviceYears, $ctx, $computedByType);
        if ($line !== null) {
            $payLines[] = $line;
            $computedByType[(int)$type['id']] = $line['amount'];
            $baseAmountByType[(int)$type['id']] = $line['base_amount'] ?? $line['amount'];
            $grossPay = payroll_money($grossPay + $line['amount']);
        }
    }

    // Deductions other than tax and insurance. Percent-of-gross deductions
    // use the final total payabilities.
    $deductionLines = [];
    foreach ($deductionTypes as [$type, $assignment, $value]) {
        $line = payroll_calculate_line($type, $assignment, $value, $basic, $grossPay, $degreeId, $serviceYears, $ctx, $computedByType);
        if ($line !== null) {
            $deductionLines[] = $line;
            $computedByType[(int)$type['id']] = $line['amount'];
            $baseAmountByType[(int)$type['id']] = $line['base_amount'] ?? $line['amount'];
        }
    }

    // Insurance base and contribution.
    $insuranceType = null;
    foreach ($ctx['types'] as $type) {
        if (($type['calc_method'] ?? '') === 'insurance' && !(int)$type['omitted']) {
            $insuranceType = $type;
            break;
        }
    }
    $insuranceInfo = payroll_insurance_for_base(0, $ctx['insurance_rule']);
    $insuranceBase = 0.0;
    $insuranceAmount = 0.0;
    $employerInsurance = 0.0;
    $insuranceApplied = false;
    $insuranceEnabled = (string)($ctx['settings']['insurance_enabled'] ?? '1') !== '0';
    $insuranceExempt = $settings && !empty($settings['insurance_exempt']);
    $insuranceAssignment = $insuranceType ? ($assignments[(int)$insuranceType['id']] ?? null) : null;
    $insuranceTypeActive = $insuranceType && (int)$insuranceType['enabled'];
    if ($insuranceAssignment && isset($insuranceAssignment['enabled']) && !(int)$insuranceAssignment['enabled']) {
        $insuranceTypeActive = false;
    }

    if ($insuranceEnabled && $insuranceTypeActive && !$insuranceExempt) {
        foreach ($payLines as $line) {
            if (!empty($line['before_insurance'])) {
                $insuranceBase += $line['amount'];
            }
        }
        foreach ($deductionLines as $line) {
            if (!empty($line['before_insurance'])) {
                $insuranceBase -= $line['amount'];
            }
        }
        if ($settings && payroll_has_value($settings['insurance_base_override'] ?? null)) {
            $insuranceBase = (float)$settings['insurance_base_override'];
        }
        $insuranceBase = max(0, $insuranceBase);
        if ($insuranceBase > 0) {
            $insuranceInfo = payroll_insurance_for_base($insuranceBase, $ctx['insurance_rule']);
            $insuranceBase = $insuranceInfo['base'];
            $insuranceAmount = $insuranceInfo['employee'];
            $employerInsurance = $insuranceInfo['employer'];
            $insuranceApplied = true;
        }
    }

    if ($insuranceApplied && $insuranceType) {
        $deductionLines[] = [
            'type_id' => (int)$insuranceType['id'],
            'kind' => 'deduction',
            'code' => $insuranceType['code'],
            'name' => $insuranceType['name'],
            'name_ar' => $insuranceType['name_ar'],
            'calc_method' => 'insurance',
            'rate' => $insuranceInfo['employee_rate'],
            'base_amount' => $insuranceBase,
            'amount' => $insuranceAmount,
            'before_tax' => (int)$insuranceType['before_tax'],
            'before_insurance' => 0,
            'sort_order' => (int)$insuranceType['sort_order'],
            'source' => 'calculated',
            'notes' => null,
        ];
        $computedByType[(int)$insuranceType['id']] = $insuranceAmount;
        $baseAmountByType[(int)$insuranceType['id']] = $insuranceBase;
    }

    // Taxable base: payabilities/deductions flagged as before tax, including
    // the employee insurance contribution when the insurance type says so.
    $taxableBase = 0.0;
    foreach ($payLines as $line) {
        if (!empty($line['before_tax'])) {
            $taxableBase += $line['amount'];
        }
    }
    foreach ($deductionLines as $line) {
        if (!empty($line['before_tax'])) {
            $taxableBase -= $line['amount'];
        }
    }
    $taxableBase = payroll_money(max(0, $taxableBase));

    $taxType = null;
    foreach ($ctx['types'] as $type) {
        if (($type['calc_method'] ?? '') === 'tax') {
            $taxType = $type;
            break;
        }
    }
    $taxInfo = payroll_tax_for_annual(0, $ctx['tax_year'], []);
    $taxAmount = 0.0;
    $taxApplied = false;
    $taxEnabled = (string)($ctx['settings']['tax_enabled'] ?? '1') !== '0';
    $taxExempt = $settings && !empty($settings['tax_exempt']);
    $taxAssignment = $taxType ? ($assignments[(int)$taxType['id']] ?? null) : null;
    $taxTypeActive = $taxType && (int)$taxType['enabled'];
    if ($taxAssignment && isset($taxAssignment['enabled']) && !(int)$taxAssignment['enabled']) {
        $taxTypeActive = false;
    }

    if ($taxEnabled && $taxTypeActive && !$taxExempt) {
        $taxInfo = payroll_tax_for_annual($taxableBase * 12, $ctx['tax_year'], $ctx['tax_brackets']);
        $taxAmount = $taxInfo['monthly_tax'];
        if ($taxType) {
            $taxAmount = payroll_apply_limits($taxAmount, $taxType);
        }
        if ($taxAmount > 0) {
            $taxApplied = true;
        }
    }

    if ($taxApplied && $taxType) {
        $deductionLines[] = [
            'type_id' => (int)$taxType['id'],
            'kind' => 'deduction',
            'code' => $taxType['code'],
            'name' => $taxType['name'],
            'name_ar' => $taxType['name_ar'],
            'calc_method' => 'tax',
            'rate' => 0.0,
            'base_amount' => $taxableBase,
            'amount' => $taxAmount,
            'before_tax' => 0,
            'before_insurance' => 0,
            'sort_order' => (int)$taxType['sort_order'],
            'source' => 'calculated',
            'notes' => null,
        ];
        $computedByType[(int)$taxType['id']] = $taxAmount;
    }

    usort($payLines, fn($a, $b) => [$a['sort_order'], $a['type_id']] <=> [$b['sort_order'], $b['type_id']]);
    usort($deductionLines, fn($a, $b) => [$a['sort_order'], $a['type_id']] <=> [$b['sort_order'], $b['type_id']]);

    $deductionsTotal = 0.0;
    foreach ($deductionLines as $line) {
        $deductionsTotal += $line['amount'];
    }
    $deductionsTotal = payroll_money($deductionsTotal);
    $netSalary = payroll_money($grossPay - $deductionsTotal);

    return [
        'empid' => $empid,
        'empfullname' => $employee['empfullname'] ?? '',
        'empfullnamear' => $employee['empfullnamear'] ?? '',
        'nid' => $employee['nid'] ?? '',
        'insuranceid' => $employee['insuranceid'] ?? '',
        'degree_id' => $degreeId,
        'degree_name' => $employee['currentdegreename'] ?? '',
        'degree_name_ar' => $employee['currentdegreenamear'] ?? '',
        'service_years' => $serviceYears,
        'basic_salary' => $basic,
        'gross_pay' => $grossPay,
        'taxable_base' => $taxableBase,
        'tax_amount' => payroll_money($taxAmount),
        'insurance_base' => payroll_money($insuranceBase),
        'insurance_amount' => payroll_money($insuranceAmount),
        'employer_insurance' => payroll_money($employerInsurance),
        'deductions_total' => $deductionsTotal,
        'net_salary' => $netSalary,
        'pay_lines' => $payLines,
        'deduction_lines' => $deductionLines,
        'tax_info' => $taxInfo,
        'insurance_info' => $insuranceInfo,
        'settings' => $settings,
        'assignments' => $assignments,
        'period_values' => $values,
    ];
}

/** Build one calculated line for a non-system type. */
function payroll_calculate_line(
    array $type,
    ?array $assignment,
    ?array $value,
    float $basic,
    float $grossBase,
    int $degreeId,
    int $serviceYears,
    array $ctx,
    array $computedByType
): ?array {
    if (!(int)$type['enabled']) {
        return null;
    }
    $method = (string)$type['calc_method'];
    $rate = payroll_type_rate($type, $assignment, $value);
    $fixed = payroll_type_fixed_amount($type, $assignment, $value, 0.0);
    $baseAmount = null;
    $source = 'calculated';

    if ($value && payroll_has_value($value['amount'] ?? null)) {
        $amount = (float)$value['amount'];
        $source = 'import';
    } elseif ($assignment && payroll_has_value($assignment['amount_override'] ?? null)) {
        $amount = (float)$assignment['amount_override'];
        $source = 'assignment';
    } else {
        switch ($method) {
            case 'fixed':
                $amount = $fixed;
                break;
            case 'manual':
                $amount = 0.0;
                break;
            case 'percent_basic':
                $baseAmount = $basic;
                $amount = $basic * $rate / 100;
                break;
            case 'percent_gross':
                $baseAmount = $grossBase;
                $amount = $grossBase * $rate / 100;
                break;
            case 'percent_type':
                $baseTypeId = (int)($type['base_type_id'] ?? 0);
                if ($baseTypeId > 0) {
                    $baseAmount = $computedByType[$baseTypeId] ?? 0.0;
                } else {
                    $baseAmount = $grossBase;
                }
                $amount = (float)$baseAmount * $rate / 100;
                break;
            case 'degree':
                $baseAmount = $ctx['degree_amounts'][(int)$type['id']][$degreeId] ?? 0.0;
                $amount = (float)$baseAmount;
                break;
            case 'seniority':
                if ($rate > 0) {
                    $baseAmount = $basic;
                    $amount = $basic * $rate / 100 * max(0, $serviceYears);
                } else {
                    $amount = $fixed * max(0, $serviceYears);
                }
                break;
            default:
                $amount = $fixed;
        }
    }

    $amount = payroll_apply_limits($amount, $type);
    return [
        'type_id' => (int)$type['id'],
        'kind' => $type['kind'],
        'code' => $type['code'],
        'name' => $type['name'],
        'name_ar' => $type['name_ar'],
        'calc_method' => $method,
        'rate' => payroll_money($rate),
        'base_amount' => $baseAmount === null ? null : payroll_money($baseAmount),
        'amount' => $amount,
        'before_tax' => (int)$type['before_tax'],
        'before_insurance' => (int)$type['before_insurance'],
        'sort_order' => (int)$type['sort_order'],
        'source' => $source,
        'notes' => null,
    ];
}

/** Compute every eligible employee in a period. */
function payroll_compute_period(int $year, int $month, ?array $employeeIds = null): array
{
    $ctx = payroll_build_context($year, $month);
    $results = [];
    $wanted = $employeeIds === null ? null : array_flip(array_map('intval', $employeeIds));
    foreach ($ctx['employees'] as $employee) {
        $empid = (int)$employee['id'];
        if ($wanted !== null && !isset($wanted[$empid])) {
            continue;
        }
        $hasSettings = isset($ctx['settings_by_emp'][$empid]);
        $hasAssignments = !empty($ctx['assign_by_emp'][$empid]);
        $hasValues = !empty($ctx['values_by_emp'][$empid]);
        if (!$hasSettings && !$hasAssignments && !$hasValues) {
            continue;
        }
        $result = payroll_compute_employee($employee, $ctx);
        if ($result['gross_pay'] == 0.0 && $result['deductions_total'] == 0.0) {
            continue;
        }
        $results[] = $result;
    }
    return ['context' => $ctx, 'results' => $results];
}

/**
 * Year-end reconciliation from finalized runs.
 *
 * @return array{year:int,rows:array,totals:array,tax_year:?array,insurance_rule:?array,run_count:int}
 */
function payroll_year_summary(int $year): array
{
    $ctx = payroll_build_context($year, 12);
    $rows = fetchAll(
        "SELECT i.empid, MAX(i.empfullname) AS empfullname, MAX(i.empfullnamear) AS empfullnamear,
                COUNT(*) AS months,
                SUM(i.basic_salary) AS basic_total,
                SUM(i.gross_pay) AS gross_total,
                SUM(i.taxable_base) AS taxable_total,
                SUM(i.tax_amount) AS tax_total,
                SUM(i.insurance_base) AS insurance_base_total,
                SUM(i.insurance_amount) AS insurance_total,
                SUM(i.employer_insurance) AS employer_insurance_total,
                SUM(i.deductions_total) AS deductions_total,
                SUM(i.net_salary) AS net_total
         FROM " . table('payroll_run_items') . " i
         INNER JOIN " . table('payroll_runs') . " r ON r.id = i.run_id
         WHERE r.payroll_year=? AND r.status='final' AND r.omitted=0 AND i.omitted=0
         GROUP BY i.empid
         ORDER BY empfullname ASC",
        [$year]
    );

    $summaryRows = [];
    $totals = [
        'employees' => 0,
        'basic' => 0.0,
        'gross' => 0.0,
        'taxable' => 0.0,
        'tax_paid' => 0.0,
        'tax_due' => 0.0,
        'tax_offset' => 0.0,
        'insurance_base' => 0.0,
        'insurance' => 0.0,
        'employer_insurance' => 0.0,
        'deductions' => 0.0,
        'net' => 0.0,
        'creditors' => 0,
        'debtors' => 0,
    ];
    foreach ($rows as $row) {
        $taxInfo = payroll_tax_for_annual((float)$row['taxable_total'], $ctx['tax_year'], $ctx['tax_brackets']);
        $taxDue = $taxInfo['annual_tax'];
        $taxPaid = payroll_money($row['tax_total']);
        $offset = payroll_money($taxDue - $taxPaid);
        $status = $offset > 0.009 ? 'debtor' : ($offset < -0.009 ? 'creditor' : 'settled');
        $summaryRows[] = [
            'empid' => (int)$row['empid'],
            'empfullname' => $row['empfullname'],
            'empfullnamear' => $row['empfullnamear'],
            'months' => (int)$row['months'],
            'basic_total' => payroll_money($row['basic_total']),
            'gross_total' => payroll_money($row['gross_total']),
            'taxable_total' => payroll_money($row['taxable_total']),
            'tax_paid' => $taxPaid,
            'tax_due' => $taxDue,
            'tax_offset' => $offset,
            'tax_status' => $status,
            'annual_taxable' => $taxInfo['annual_taxable'],
            'insurance_base_total' => payroll_money($row['insurance_base_total']),
            'insurance_total' => payroll_money($row['insurance_total']),
            'employer_insurance_total' => payroll_money($row['employer_insurance_total']),
            'deductions_total' => payroll_money($row['deductions_total']),
            'net_total' => payroll_money($row['net_total']),
        ];
        foreach (['basic_total' => 'basic', 'gross_total' => 'gross', 'taxable_total' => 'taxable',
                  'insurance_base_total' => 'insurance_base', 'insurance_total' => 'insurance',
                  'employer_insurance_total' => 'employer_insurance', 'deductions_total' => 'deductions',
                  'net_total' => 'net'] as $key => $totalKey) {
            $totals[$totalKey] = payroll_money($totals[$totalKey] + (float)$row[$key]);
        }
        $totals['tax_paid'] = payroll_money($totals['tax_paid'] + $taxPaid);
        $totals['tax_due'] = payroll_money($totals['tax_due'] + $taxDue);
        $totals['tax_offset'] = payroll_money($totals['tax_offset'] + $offset);
        $totals['employees']++;
        if ($status === 'creditor') {
            $totals['creditors']++;
        } elseif ($status === 'debtor') {
            $totals['debtors']++;
        }
    }

    return [
        'year' => $year,
        'rows' => $summaryRows,
        'totals' => $totals,
        'tax_year' => $ctx['tax_year'],
        'insurance_rule' => $ctx['insurance_rule'],
        'run_count' => (int)(fetchRow(
            "SELECT COUNT(*) AS c FROM " . table('payroll_runs') . " WHERE payroll_year=? AND status='final' AND omitted=0",
            [$year]
        )['c'] ?? 0),
    ];
}

/** Human month name through the language files. */
function payroll_month_name(int $month): string
{
    $key = 'payroll_month_' . max(1, min(12, $month));
    return function_exists('L') ? L($key) : (string)$month;
}
