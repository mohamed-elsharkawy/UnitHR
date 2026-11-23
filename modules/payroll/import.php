<?php
/**
 * UnitHR - Payroll CSV import
 *
 * Supports:
 *  - Basic-salary CSV: employee identifier + basic salary (optional year/month)
 *  - Deduction/payability CSV, long format: nid, code, amount|rate
 *  - Deduction/payability CSV, wide format: nid, CODE1, CODE2, ...
 *
 * Files may be UTF-8 (with or without BOM) or Windows-1256. Delimiters can be
 * comma, semicolon or tab. Imported values are stored per employee/type/month;
 * the latest basic salary also updates the employee's payroll setup.
 */

if (!function_exists('table')) {
    require_once __DIR__ . '/../../includes/config.php';
}
if (!function_exists('db')) {
    require_once __DIR__ . '/../../includes/db.php';
}
if (!function_exists('payroll_money')) {
    require_once __DIR__ . '/engine.php';
}

/** Translate a payroll message when the language layer is available. */
function payroll_t(string $key, ...$args): string
{
    if (function_exists('L')) {
        return L($key, ...$args);
    }
    return $args ? ($key . ' ' . implode(' ', $args)) : $key;
}

/** Convert Arabic-Indic digits and separators to ASCII for numeric parsing. */
function payroll_normalize_digits(string $value): string
{
    $map = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٫' => '.', '٬' => '', '،' => ',',
    ];
    return strtr($value, $map);
}

/** Parse a numeric CSV value; returns null for blank or invalid values. */
function payroll_parse_number($value): ?float
{
    if ($value === null) {
        return null;
    }
    $value = trim(payroll_normalize_digits((string)$value));
    if ($value === '') {
        return null;
    }
    $value = str_replace(["\xC2\xA0", ' ', "\t", ','], '', $value);
    $value = preg_replace('/[^\d.\-]/u', '', $value);
    if ($value === '' || $value === '-' || $value === '.') {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    return (float)$value;
}

/** Normalize a CSV header to a stable snake_case key. */
function payroll_csv_header_key(string $header): string
{
    $header = trim($header);
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
    $header = str_replace(["\xC2\xA0"], ' ', $header);
    $header = function_exists('mb_strtolower') ? mb_strtolower($header, 'UTF-8') : strtolower($header);
    $header = preg_replace('/[^\p{L}\p{N}]+/u', '_', $header);
    return trim((string)$header, '_');
}

/** Header aliases accepted by the importer. */
function payroll_csv_aliases(): array
{
    return [
        'employee' => [
            'nid', 'national_id', 'nationalid', 'national_number', 'nationalnumber',
            'insuranceid', 'insurance_id', 'insurance_number', 'insurancenumber',
            'empid', 'employee_id', 'employeeid', 'id', 'employee_number', 'empno', 'emp_id',
            'الرقم_القومي', 'الرقم_التاميني', 'رقم_الموظف', 'كود_الموظف',
        ],
        'basic' => [
            'basic_salary', 'basicsalary', 'basic', 'basic_pay', 'basicpay',
            'salary', 'monthly_salary', 'monthlysalary', 'amount', 'value',
            'الراتب_الأساسي', 'الراتب_الاساسي', 'الراتب', 'الأساسي', 'الاساسي', 'المبلغ',
        ],
        'code' => [
            'code', 'type_code', 'typecode', 'deduction_code', 'deductioncode',
            'payability_code', 'payabilitycode', 'type', 'deduction', 'payability',
            'كود', 'الكود', 'كود_النوع', 'النوع', 'نوع_الخصم',
        ],
        'amount' => [
            'amount', 'value', 'deduction_amount', 'deductionamount', 'monthly_amount',
            'monthlyamount', 'total', 'المبلغ', 'القيمة', 'قيمة_الخصم',
        ],
        'rate' => ['rate', 'percentage', 'percent', 'النسبة', 'نسبة'],
        'year' => ['year', 'payroll_year', 'payrollyear', 'السنة', 'سنة'],
        'month' => ['month', 'payroll_month', 'payrollmonth', 'الشهر', 'شهر'],
        'name' => ['name', 'employee_name', 'employeename', 'empfullname', 'الاسم', 'اسم_الموظف'],
    ];
}

/** Return the first matching header value from a normalized row. */
function payroll_row_value(array $row, string $aliasKey, ?array $aliases = null): ?string
{
    $aliases = $aliases ?: payroll_csv_aliases();
    foreach ($aliases[$aliasKey] ?? [] as $alias) {
        if (array_key_exists($alias, $row) && trim((string)$row[$alias]) !== '') {
            return trim((string)$row[$alias]);
        }
    }
    return null;
}

/** Detect the delimiter of the first non-empty line (outside quotes). */
function payroll_csv_delimiter(string $content): string
{
    $line = '';
    foreach (preg_split('/\r\n|\n|\r/', $content) as $candidate) {
        if (trim($candidate) !== '') {
            $line = $candidate;
            break;
        }
    }
    $counts = [',' => 0, ';' => 0, "\t" => 0];
    $inQuotes = false;
    $length = strlen($line);
    for ($i = 0; $i < $length; $i++) {
        $ch = $line[$i];
        if ($ch === '"') {
            $inQuotes = !$inQuotes;
            continue;
        }
        if (!$inQuotes && isset($counts[$ch])) {
            $counts[$ch]++;
        }
    }
    arsort($counts);
    $delimiter = array_key_first($counts);
    return $counts[$delimiter] > 0 ? $delimiter : ',';
}

/**
 * Read and normalize a CSV file.
 *
 * @return array{headers:array,rows:array,delimiter:string,encoding:string}
 * @throws RuntimeException on an unreadable or empty file.
 */
function payroll_csv_read(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException(payroll_t('payroll_import_err_unreadable'));
    }
    if (filesize($path) > 10 * 1024 * 1024) {
        throw new RuntimeException(payroll_t('payroll_import_err_too_large'));
    }
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException(payroll_t('payroll_import_err_unreadable'));
    }

    $encoding = 'UTF-8';
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3);
    } elseif (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
        $encoding = 'Windows-1256';
        $converted = function_exists('mb_convert_encoding')
            ? mb_convert_encoding($content, 'UTF-8', 'Windows-1256')
            : @iconv('Windows-1256', 'UTF-8//IGNORE', $content);
        if ($converted !== false) {
            $content = $converted;
        }
    }

    $delimiter = payroll_csv_delimiter($content);
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $content);
    rewind($stream);

    $rawHeaders = fgetcsv($stream, 0, $delimiter, '"', '');
    if ($rawHeaders === false || $rawHeaders === [null]) {
        fclose($stream);
        throw new RuntimeException(payroll_t('payroll_import_err_empty_file'));
    }

    $headers = [];
    $seen = [];
    foreach ($rawHeaders as $raw) {
        $key = payroll_csv_header_key((string)$raw);
        if ($key === '') {
            $key = 'column_' . (count($headers) + 1);
        }
        if (isset($seen[$key])) {
            $seen[$key]++;
            $key .= '_' . $seen[$key];
        } else {
            $seen[$key] = 1;
        }
        $headers[] = $key;
    }

    $rows = [];
    $rownum = 1;
    while (($data = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
        $rownum++;
        if ($data === [null]) {
            continue;
        }
        $isEmpty = true;
        foreach ($data as $cell) {
            if (trim((string)$cell) !== '') {
                $isEmpty = false;
                break;
            }
        }
        if ($isEmpty) {
            continue;
        }
        $row = ['_rownum' => $rownum];
        foreach ($headers as $index => $key) {
            $value = $data[$index] ?? '';
            $row[$key] = is_string($value) ? trim($value) : $value;
        }
        $rows[] = $row;
    }
    fclose($stream);

    if (empty($rows)) {
        throw new RuntimeException(payroll_t('payroll_import_err_no_rows'));
    }

    return ['headers' => $headers, 'rows' => $rows, 'delimiter' => $delimiter, 'encoding' => $encoding];
}

/** Load active employees indexed by id, national id and insurance id. */
function payroll_import_employee_maps(): array
{
    $byId = [];
    $byNid = [];
    $byInsurance = [];
    foreach (fetchAll(
        "SELECT id, empfullname, empfullnamear, nid, insuranceid FROM " . table('employeesmain') . " WHERE omitted=0"
    ) as $row) {
        $byId[(int)$row['id']] = $row;
        if ((string)$row['nid'] !== '') {
            $byNid[payroll_lookup_key((string)$row['nid'])] = $row;
        }
        if ((string)$row['insuranceid'] !== '') {
            $byInsurance[payroll_lookup_key((string)$row['insuranceid'])] = $row;
        }
    }
    return ['by_id' => $byId, 'by_nid' => $byNid, 'by_insurance' => $byInsurance];
}

function payroll_lookup_key(string $value): string
{
    return strtoupper(trim(payroll_normalize_digits($value)));
}

/** Resolve the employee identifier in a CSV row. */
function payroll_import_resolve_employee(array $row, array $employeeMaps, ?array $aliases = null): ?array
{
    $value = payroll_row_value($row, 'employee', $aliases);
    if ($value === null) {
        return null;
    }
    $key = payroll_lookup_key($value);
    if ($key === '') {
        return null;
    }
    if (ctype_digit($key) && isset($employeeMaps['by_id'][(int)$key])) {
        return $employeeMaps['by_id'][(int)$key];
    }
    if (isset($employeeMaps['by_nid'][$key])) {
        return $employeeMaps['by_nid'][$key];
    }
    if (isset($employeeMaps['by_insurance'][$key])) {
        return $employeeMaps['by_insurance'][$key];
    }
    return null;
}

/** Current actor details for audit columns. */
function payroll_import_actor(): array
{
    if (function_exists('current_user')) {
        $user = current_user();
        if ($user) {
            return [
                'id' => (int)($user['id'] ?? 0),
                'username' => (string)($user['username'] ?? 'system'),
                'name' => (string)($user['username'] ?? 'System'),
                'name_ar' => (string)($user['username_ar'] ?? ($user['username'] ?? 'نظام')),
            ];
        }
    }
    return ['id' => 1, 'username' => 'System', 'name' => 'System', 'name_ar' => 'نظام'];
}

/** Find the BASIC type (created by the installer). */
function payroll_basic_type(): ?array
{
    $row = fetchRow("SELECT * FROM " . table('payroll_types') . " WHERE is_basic=1 AND omitted=0 ORDER BY id LIMIT 1");
    if ($row) {
        return $row;
    }
    return fetchRow("SELECT * FROM " . table('payroll_types') . " WHERE code='BASIC' AND omitted=0 LIMIT 1") ?: null;
}

/** Update or insert employee payroll settings. */
function payroll_import_save_settings(PDO $pdo, int $empid, float $basic, string $note): void
{
    $actor = payroll_import_actor();
    $existing = $pdo->prepare("SELECT * FROM " . table('payroll_employee_settings') . " WHERE empid=? ORDER BY omitted ASC, id DESC LIMIT 1");
    $existing->execute([$empid]);
    $row = $existing->fetch();
    if ($row) {
        $pdo->prepare("UPDATE " . table('payroll_employee_settings') . "
            SET basic_salary=?, notes=?, omitted=0, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=?
            WHERE id=?")->execute([
            $basic, $note, $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], (int)$row['id'],
        ]);
        return;
    }
    $pdo->prepare("INSERT INTO " . table('payroll_employee_settings') . "
        (rguid, empid, basic_salary, notes, dtadded, addedbyid, addedby, addedbyname, addedbynamear,
         lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
        VALUES (?,?,?,?,NOW(),?,?,?,?,NOW(),?,?,?,?,0)")->execute([
        bin2hex(random_bytes(16)), $empid, $basic, $note,
        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
    ]);
}

/** Upsert a per-employee/type/month value. */
function payroll_import_save_value(PDO $pdo, int $empid, int $typeId, int $year, int $month, ?float $amount, ?float $rate, string $source): void
{
    $actor = payroll_import_actor();
    $existing = $pdo->prepare("SELECT id FROM " . table('payroll_employee_type_values') . "
        WHERE empid=? AND type_id=? AND payroll_year=? AND payroll_month=? LIMIT 1");
    $existing->execute([$empid, $typeId, $year, $month]);
    $id = $existing->fetchColumn();
    if ($id) {
        $pdo->prepare("UPDATE " . table('payroll_employee_type_values') . "
            SET amount=?, rate=?, source=?, omitted=0, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=?
            WHERE id=?")->execute([
            $amount, $rate, $source, $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], (int)$id,
        ]);
        return;
    }
    $pdo->prepare("INSERT INTO " . table('payroll_employee_type_values') . "
        (rguid, empid, type_id, payroll_year, payroll_month, amount, rate, source, dtadded,
         addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
        VALUES (?,?,?,?,?,?,?,?,NOW(),?,?,?,?,NOW(),?,?,?,?,0)")->execute([
        bin2hex(random_bytes(16)), $empid, $typeId, $year, $month, $amount, $rate, $source,
        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
    ]);
}

/** Ensure an employee has an assignment row for a type. */
function payroll_import_save_assignment(PDO $pdo, int $empid, int $typeId): void
{
    $check = $pdo->prepare("SELECT * FROM " . table('payroll_employee_types') . "
        WHERE empid=? AND type_id=? ORDER BY omitted ASC, id DESC LIMIT 1");
    $check->execute([$empid, $typeId]);
    $row = $check->fetch();
    $actor = payroll_import_actor();
    if ($row) {
        if ((int)$row['omitted'] === 1 || (int)$row['enabled'] === 0) {
            $pdo->prepare("UPDATE " . table('payroll_employee_types') . "
                SET omitted=0, enabled=1, lastedit=NOW(), editedbyid=?, editedby=?, editedbyname=?, editedbynamear=?
                WHERE id=?")->execute([
                $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'], (int)$row['id'],
            ]);
        }
        return;
    }
    $pdo->prepare("INSERT INTO " . table('payroll_employee_types') . "
        (rguid, empid, type_id, enabled, dtadded, addedbyid, addedby, addedbyname, addedbynamear,
         lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
        VALUES (?,?,?,1,NOW(),?,?,?,?,NOW(),?,?,?,?,0)")->execute([
        bin2hex(random_bytes(16)), $empid, $typeId,
        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
    ]);
}

/** Sanitize a type code from a CSV header. */
function payroll_import_code(string $raw): string
{
    $raw = trim(strtoupper($raw));
    $raw = preg_replace('/[^A-Z0-9_\-\p{L}]+/u', '_', $raw);
    return trim((string)$raw, '_');
}

/** Find or create an imported type. */
function payroll_import_type(string $rawCode, string $label, bool $createMissing, array &$stats): ?array
{
    $code = payroll_import_code($rawCode);
    if ($code === '') {
        return null;
    }
    $row = fetchRow("SELECT * FROM " . table('payroll_types') . " WHERE UPPER(code)=? AND omitted=0 LIMIT 1", [$code]);
    if ($row) {
        return $row;
    }
    if (!$createMissing) {
        return null;
    }
    $actor = payroll_import_actor();
    $name = trim($label) !== '' ? trim($label) : $code;
    $pdo = db();
    $pdo->prepare("INSERT INTO " . table('payroll_types') . "
        (rguid, kind, code, name, name_ar, description, description_ar, calc_method, rate, amount, base_type_id,
         max_amount, min_amount, before_tax, before_insurance, is_basic, is_system, sort_order, enabled,
         dtadded, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted)
        VALUES (?, 'deduction', ?, ?, ?, ?, ?, 'manual', 0, 0, NULL, NULL, NULL, 0, 0, 0, 0, 500, 1,
                NOW(), ?, ?, ?, ?, NOW(), ?, ?, ?, ?, 0)")->execute([
        bin2hex(random_bytes(16)), $code, $name, $name,
        payroll_t('payroll_import_created_type_note'),
        payroll_t('payroll_import_created_type_note'),
        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
        $actor['id'], $actor['username'], $actor['name'], $actor['name_ar'],
    ]);
    $stats['types_created']++;
    return fetchRow("SELECT * FROM " . table('payroll_types') . " WHERE id=?", [(int)$pdo->lastInsertId()]) ?: null;
}

/** Validate a period from a row/default. */
function payroll_import_period(?string $yearValue, ?string $monthValue, int $defaultYear, int $defaultMonth, array &$errors, int $rownum): ?array
{
    $year = $defaultYear;
    $month = $defaultMonth;
    if ($yearValue !== null && trim($yearValue) !== '') {
        $parsed = payroll_parse_number($yearValue);
        if ($parsed === null || $parsed < 2000 || $parsed > 2100) {
            $errors[] = ['row' => $rownum, 'message' => payroll_t('payroll_import_err_year', $yearValue)];
            return null;
        }
        $year = (int)$parsed;
    }
    if ($monthValue !== null && trim($monthValue) !== '') {
        $parsed = payroll_parse_number($monthValue);
        if ($parsed === null || $parsed < 1 || $parsed > 12) {
            $errors[] = ['row' => $rownum, 'message' => payroll_t('payroll_import_err_month', $monthValue)];
            return null;
        }
        $month = (int)$parsed;
    }
    return [$year, $month];
}

/** Shared result skeleton. */
function payroll_import_result(string $kind): array
{
    return [
        'kind' => $kind,
        'format' => $kind,
        'rows' => 0,
        'imported' => 0,
        'skipped' => 0,
        'types_created' => 0,
        'errors' => [],
        'encoding' => '',
        'delimiter' => '',
        'dry_run' => false,
    ];
}

/**
 * Import basic salaries.
 *
 * @param string $path CSV path
 * @param int $defaultYear Fallback year
 * @param int $defaultMonth Fallback month
 * @param bool $dryRun Validate and roll back
 * @param string $note Note stored on employee settings
 */
function payroll_import_basic(string $path, int $defaultYear, int $defaultMonth, bool $dryRun = false, string $note = ''): array
{
    $result = payroll_import_result('basic');
    $result['dry_run'] = $dryRun;
    try {
        $csv = payroll_csv_read($path);
    } catch (Throwable $e) {
        $result['errors'][] = ['row' => 0, 'message' => $e->getMessage()];
        return $result;
    }
    $result['encoding'] = $csv['encoding'];
    $result['delimiter'] = $csv['delimiter'] === "\t" ? 'tab' : $csv['delimiter'];
    $employeeMaps = payroll_import_employee_maps();
    $basicType = payroll_basic_type();
    if (!$basicType) {
        $result['errors'][] = ['row' => 0, 'message' => payroll_t('payroll_import_err_basic_type')];
        return $result;
    }

    $pdo = db();
    $result['rows'] = count($csv['rows']);
    $inTx = false;
    try {
        $inTx = $pdo->beginTransaction();
        foreach ($csv['rows'] as $row) {
            $rownum = (int)$row['_rownum'];
            $employeeValue = payroll_row_value($row, 'employee');
            if ($employeeValue === null) {
                $result['errors'][] = ['row' => $rownum, 'message' => payroll_t('payroll_import_err_employee_missing')];
                continue;
            }
            $employee = payroll_import_resolve_employee($row, $employeeMaps);
            if (!$employee) {
                $result['errors'][] = ['row' => $rownum, 'message' => payroll_t('payroll_import_err_employee', $employeeValue)];
                continue;
            }
            $amountValue = payroll_row_value($row, 'basic');
            if ($amountValue === null || trim($amountValue) === '') {
                $result['errors'][] = ['row' => $rownum, 'message' => payroll_t('payroll_import_err_amount', '')];
                continue;
            }
            $amount = payroll_parse_number($amountValue);
            if ($amount === null) {
                $result['errors'][] = ['row' => $rownum, 'message' => payroll_t('payroll_import_err_amount', $amountValue)];
                continue;
            }
            if ($amount < 0) {
                $result['errors'][] = ['row' => $rownum, 'message' => payroll_t('payroll_import_err_negative')];
                continue;
            }
            $period = payroll_import_period(
                payroll_row_value($row, 'year'),
                payroll_row_value($row, 'month'),
                $defaultYear,
                $defaultMonth,
                $result['errors'],
                $rownum
            );
            if ($period === null) {
                continue;
            }
            [$year, $month] = $period;
            payroll_import_save_settings($pdo, (int)$employee['id'], payroll_money($amount), $note);
            payroll_import_save_value($pdo, (int)$employee['id'], (int)$basicType['id'], $year, $month, payroll_money($amount), null, 'csv');
            $result['imported']++;
        }
        if ($dryRun && $inTx) {
            $pdo->rollBack();
            $inTx = false;
        } elseif ($inTx) {
            $pdo->commit();
            $inTx = false;
        }
    } catch (Throwable $e) {
        if ($inTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $result['errors'][] = ['row' => 0, 'message' => payroll_t('payroll_import_err_db', $e->getMessage())];
    }
    return $result;
}

/**
 * Import deduction (or payability) values in long or wide format.
 */
function payroll_import_values(string $path, int $defaultYear, int $defaultMonth, bool $createMissing = true, bool $dryRun = false, string $note = ''): array
{
    $result = payroll_import_result('values');
    $result['dry_run'] = $dryRun;
    $result['create_missing'] = $createMissing;
    try {
        $csv = payroll_csv_read($path);
    } catch (Throwable $e) {
        $result['errors'][] = ['row' => 0, 'message' => $e->getMessage()];
        return $result;
    }
    $result['encoding'] = $csv['encoding'];
    $result['delimiter'] = $csv['delimiter'] === "\t" ? 'tab' : $csv['delimiter'];

    $aliases = payroll_csv_aliases();
    $keyHeaders = array_filter($csv['headers'], fn($h) => in_array($h, $aliases['employee'], true));
    if (empty($keyHeaders)) {
        $result['errors'][] = ['row' => 0, 'message' => payroll_t('payroll_import_err_missing_key')];
        return $result;
    }
    $codeHeader = null;
    foreach ($aliases['code'] as $alias) {
        if (in_array($alias, $csv['headers'], true)) {
            $codeHeader = $alias;
            break;
        }
    }
    $amountHeader = null;
    foreach ($aliases['amount'] as $alias) {
        if (in_array($alias, $csv['headers'], true)) {
            $amountHeader = $alias;
            break;
        }
    }
    $rateHeader = null;
    foreach ($aliases['rate'] as $alias) {
        if (in_array($alias, $csv['headers'], true)) {
            $rateHeader = $alias;
            break;
        }
    }
    $isLong = $codeHeader !== null && ($amountHeader !== null || $rateHeader !== null);
    $result['format'] = $isLong ? 'long' : 'wide';

    $wideHeaders = [];
    if (!$isLong) {
        $skip = array_merge(
            $aliases['employee'], $aliases['year'], $aliases['month'], $aliases['name'],
            ['notes', 'note', 'department', 'depname', 'degreename', 'total_deductions', 'net_salary', 'gross_salary', 'total']
        );
        foreach ($csv['headers'] as $header) {
            if (str_starts_with($header, 'column_') || in_array($header, $skip, true)) {
                continue;
            }
            $wideHeaders[$header] = $header;
        }
        if (empty($wideHeaders)) {
            $result['errors'][] = ['row' => 0, 'message' => payroll_t('payroll_import_err_no_columns')];
            return $result;
        }
    }

    $employeeMaps = payroll_import_employee_maps();
    $pdo = db();
    $result['rows'] = count($csv['rows']);
    $typeCache = [];
    $inTx = false;
    try {
        $inTx = $pdo->beginTransaction();
        $processCell = function (array $row, int $rownum, string $rawCode, string $label, ?string $amountValue, ?string $rateValue) use (
            &$result, &$typeCache, $createMissing, $employeeMaps, $pdo, $defaultYear, $defaultMonth
        ): void {
            $amountTrim = $amountValue === null ? '' : trim($amountValue);
            $rateTrim = $rateValue === null ? '' : trim($rateValue);
            if ($amountTrim === '' && $rateTrim === '') {
                return;
            }
            $amount = null;
            $rate = null;
            if ($amountTrim !== '') {
                $amount = payroll_parse_number($amountTrim);
                if ($amount === null) {
                    $result['errors'][] = ['row' => $rownum, 'message' => payroll_t('payroll_import_err_amount', $amountTrim)];
                    return;
                }
                if ($amount < 0) {
                    $result['errors'][] = ['row' => $rownum, 'message' => payroll_t('payroll_import_err_negative')];
                    return;
                }
            }
            if ($rateTrim !== '') {
                $rate = payroll_parse_number($rateTrim);
                if ($rate === null) {
                    $result['errors'][] = ['row' => $rownum, 'message' => payroll_t('payroll_import_err_rate', $rateTrim)];
                    return;
                }
                if ($rate < 0) {
                    $result['errors'][] = ['row' => $rownum, 'message' => payroll_t('payroll_import_err_negative')];
                    return;
                }
            }
            $employee = payroll_import_resolve_employee($row, $employeeMaps);
            if (!$employee) {
                $lookup = payroll_row_value($row, 'employee') ?? '';
                $result['errors'][] = ['row' => $rownum, 'message' => payroll_t('payroll_import_err_employee', $lookup)];
                return;
            }
            $period = payroll_import_period(
                payroll_row_value($row, 'year'),
                payroll_row_value($row, 'month'),
                $defaultYear,
                $defaultMonth,
                $result['errors'],
                $rownum
            );
            if ($period === null) {
                return;
            }
            [$year, $month] = $period;
            $cacheKey = payroll_import_code($rawCode);
            if (!array_key_exists($cacheKey, $typeCache)) {
                $typeCache[$cacheKey] = payroll_import_type($rawCode, $label, $createMissing, $result);
            }
            $type = $typeCache[$cacheKey];
            if (!$type) {
                $result['errors'][] = ['row' => $rownum, 'message' => payroll_t('payroll_import_err_type', $rawCode)];
                return;
            }
            payroll_import_save_value($pdo, (int)$employee['id'], (int)$type['id'], $year, $month, $amount === null ? null : payroll_money($amount), $rate, 'csv');
            payroll_import_save_assignment($pdo, (int)$employee['id'], (int)$type['id']);
            $result['imported']++;
        };

        foreach ($csv['rows'] as $row) {
            $rownum = (int)$row['_rownum'];
            if ($isLong) {
                $rawCode = trim((string)($row[$codeHeader] ?? ''));
                if ($rawCode === '') {
                    $result['skipped']++;
                    continue;
                }
                $processCell($row, $rownum, $rawCode, $rawCode, $row[$amountHeader] ?? null, $rateHeader ? ($row[$rateHeader] ?? null) : null);
            } else {
                $any = false;
                foreach ($wideHeaders as $header) {
                    $cell = (string)($row[$header] ?? '');
                    if (trim($cell) === '') {
                        continue;
                    }
                    $any = true;
                    $isRate = str_ends_with(trim($cell), '%') || str_ends_with($header, '_rate');
                    if ($isRate) {
                        $processCell($row, $rownum, $header, $header, null, $cell);
                    } else {
                        $processCell($row, $rownum, $header, $header, $cell, null);
                    }
                }
                if (!$any) {
                    $result['skipped']++;
                }
            }
        }
        if ($dryRun && $inTx) {
            $pdo->rollBack();
            $inTx = false;
        } elseif ($inTx) {
            $pdo->commit();
            $inTx = false;
        }
    } catch (Throwable $e) {
        if ($inTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $result['errors'][] = ['row' => 0, 'message' => payroll_t('payroll_import_err_db', $e->getMessage())];
    }
    return $result;
}
