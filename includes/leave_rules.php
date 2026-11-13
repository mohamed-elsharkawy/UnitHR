<?php
/**
 * UnitHR - Leave rules engine.
 *
 * Shared by the ESS portal and the admin Employee Leaves module so that the
 * rules declared on each leave type are applied consistently:
 *   - tpel        times per employee life   (0 = unlimited)
 *   - tpey        times per fiscal year     (0 = unlimited)
 *   - incwe       include weekends          (0/1)
 *   - incholidays include official holidays (0/1)
 */

/**
 * Strictly parse a Y-m-d date.
 *
 * @return DateTimeImmutable|null
 */
function leave_parse_date($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$d) {
        return null;
    }
    $errors = DateTimeImmutable::getLastErrors();
    if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return null;
    }
    return $d->format('Y-m-d') === $value ? $d : null;
}

/**
 * Weekday numbers treated as the weekend (0=Sunday ... 6=Saturday).
 * Egypt uses Friday/Saturday; change this to [5, 6] to switch.
 */
function leave_weekend_days() {
    return [0, 6];
}

/**
 * Map weekday number (0=Sunday ... 6=Saturday) to the employeesmain column
 * that stores whether the employee works that day.
 */
function leave_workday_columns() {
    return [
        0 => 'sunworkday',
        1 => 'monworkday',
        2 => 'tueworkday',
        3 => 'wedworkday',
        4 => 'thuworkday',
        5 => 'friworkday',
        6 => 'satworkday',
    ];
}

/**
 * Human-readable weekday names for messages (0=Sunday ... 6=Saturday).
 */
function leave_workday_labels($isAr = null) {
    if ($isAr === null) {
        $isAr = function_exists('lang') && lang() === 'ar';
    }
    return $isAr
        ? [0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت']
        : [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
}

/**
 * Names of the working weekdays in a workday flag map.
 */
function leave_workday_summary($workdays, $isAr = null) {
    $labels = leave_workday_labels($isAr);
    $on = [];
    if (is_array($workdays)) {
        foreach ($workdays as $weekday => $flag) {
            if (!empty($flag) && isset($labels[$weekday])) {
                $on[] = $labels[$weekday];
            }
        }
    }
    return $on;
}

/**
 * Build the "no countable days" message in the current UI language, listing
 * the employee's working weekdays and any official holidays in the period.
 *
 * @param array|null $workdays [0..6 => 0|1] workday flags, or null when unknown
 * @param array      $holidayMap date => name
 * @param string     $context  'request' or 'approve'
 */
function leave_no_days_message($workdays, array $holidayMap = [], $context = 'request') {
    $isAr = function_exists('lang') && lang() === 'ar';
    $working = leave_workday_summary($workdays, $isAr);

    if ($isAr) {
        $msg = $context === 'approve'
            ? 'فترة الإجازة لا تحتوي على أيام قابلة للاحتساب.'
            : 'الفترة المحددة لا تحتوي على أيام إجازة قابلة للاحتساب.';
        if (is_array($workdays)) {
            $msg .= $working
                ? ' أيام عمل هذا الموظف: ' . implode('، ', $working) . '.'
                : ' لم تُحدد أيام عمل لهذا الموظف.';
        }
        if (!empty($holidayMap)) {
            $msg .= ' العطلات الرسمية خلال الفترة: ' . implode('، ', array_keys($holidayMap)) . '.';
        }
        return $msg . ' راجع إعداد أيام العمل في بيانات الموظف.';
    }

    $msg = $context === 'approve'
        ? 'The leave period contains no countable days.'
        : 'The selected period contains no countable leave days.';
    if (is_array($workdays)) {
        $msg .= $working
            ? ' This employee works on: ' . implode(', ', $working) . '.'
            : ' No working days are configured for this employee.';
    }
    if (!empty($holidayMap)) {
        $msg .= ' Official holiday(s) in the period: ' . implode(', ', array_keys($holidayMap)) . '.';
    }
    return $msg . ' Check the Workdays setting on the employee record.';
}

/**
 * Return [0..6 => 0|1] workday flags for an employee, or null when the
 * workday columns are not installed yet (legacy behaviour then applies).
 */
function leave_employee_workdays($empid) {
    $emp = fetchRow(
        "SELECT * FROM " . table('employeesmain') . " WHERE id = ? AND omitted = 0 LIMIT 1",
        [(int)$empid]
    );
    if (!$emp) {
        return null;
    }
    $map = [];
    foreach (leave_workday_columns() as $weekday => $column) {
        if (!array_key_exists($column, $emp)) {
            return null;
        }
        $map[$weekday] = !empty($emp[$column]) ? 1 : 0;
    }
    return $map;
}

/**
 * Build a lookup of holiday dates (Y-m-d => true) for a date range.
 * Recurring holidays are matched by month/day for every year in the range.
 * If the holidays table does not exist yet, an empty map is returned.
 */
function leave_holiday_map($start, $end) {
    static $tableExists = null;
    if ($tableExists === null) {
        $row = fetchRow(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1",
            [table('holidays')]
        );
        $tableExists = (bool)$row;
    }
    if (!$tableExists) {
        return [];
    }

    $s = leave_parse_date($start);
    $e = leave_parse_date($end);
    if (!$s || !$e || $e < $s) {
        return [];
    }

    $rows = fetchAll("SELECT holidaydate, isrecurring FROM " . table('holidays') . " WHERE omitted=0");
    $map = [];
    foreach ($rows as $row) {
        $base = leave_parse_date($row['holidaydate'] ?? '');
        if (!$base) {
            continue;
        }
        if (!empty($row['isrecurring'])) {
            for ($year = (int)$s->format('Y'); $year <= (int)$e->format('Y'); $year++) {
                $candidate = leave_parse_date(sprintf('%04d-%s', $year, $base->format('m-d')));
                if ($candidate && $candidate >= $s && $candidate <= $e) {
                    $map[$candidate->format('Y-m-d')] = true;
                }
            }
        } elseif ($base >= $s && $base <= $e) {
            $map[$base->format('Y-m-d')] = true;
        }
    }
    return $map;
}

/**
 * Count countable leave days for a period.
 *
 * Non-working days and holidays are skipped unless the leave type includes
 * them. When $workdays is supplied (from employeesmain) it defines the
 * employee's own non-working days; otherwise the global weekend is used.
 */
function leave_count_days($start, $end, array $leaveType, array $holidayMap = [], $workdays = null) {
    $s = leave_parse_date($start);
    $e = leave_parse_date($end);
    if (!$s || !$e || $e < $s) {
        return 0;
    }

    $includeWeekends = !empty($leaveType['incwe']);
    $includeHolidays = !empty($leaveType['incholidays']);
    $weekendDays = leave_weekend_days();
    $useEmployeeWorkdays = is_array($workdays) && count($workdays) === 7;
    $days = 0;

    for ($d = $s; $d <= $e; $d = $d->modify('+1 day')) {
        $key = $d->format('Y-m-d');
        $weekday = (int)$d->format('w');
        if (!$includeWeekends) {
            if ($useEmployeeWorkdays) {
                if (empty($workdays[$weekday])) {
                    continue;
                }
            } elseif (in_array($weekday, $weekendDays, true)) {
                continue;
            }
        }
        if (!$includeHolidays && isset($holidayMap[$key])) {
            continue;
        }
        $days++;
    }
    return $days;
}

/**
 * Return the fiscal year covering a date, or null.
 */
function leave_fiscal_year($date) {
    $d = leave_parse_date($date);
    if (!$d) {
        return null;
    }
    return fetchRow(
        "SELECT id, fstartdate, fenddate FROM " . table('fiscalyears') . "
         WHERE omitted=0 AND ? BETWEEN fstartdate AND fenddate LIMIT 1",
        [$d->format('Y-m-d')]
    );
}

/**
 * Count existing requests for quota checks.
 * Pending and approved requests count; rejected and omitted ones do not.
 *
 * @return array{life:int,year:int}
 */
function leave_quota_usage($empid, $leavetypeid, $fyStart, $fyEnd, $excludeId = 0) {
    $base = "FROM " . table('empleaves') . "
             WHERE empid=? AND leavetypeid=? AND omitted=0 AND rejectedat IS NULL";
    $params = [(int)$empid, (int)$leavetypeid];

    $sql = "SELECT COUNT(*) AS c " . $base;
    if ($excludeId) {
        $sql .= " AND id<>?";
        $params[] = (int)$excludeId;
    }
    $life = (int)(fetchRow($sql, $params)['c'] ?? 0);

    $params2 = [(int)$empid, (int)$leavetypeid, $fyStart, $fyEnd];
    $sql2 = "SELECT COUNT(*) AS c " . $base . " AND startfrom BETWEEN ? AND ?";
    if ($excludeId) {
        $sql2 .= " AND id<>?";
        $params2[] = (int)$excludeId;
    }
    $year = (int)(fetchRow($sql2, $params2)['c'] ?? 0);

    return ['life' => $life, 'year' => $year];
}

/**
 * Count days already reserved by other pending requests of the same type
 * inside the same fiscal year.
 */
function leave_pending_days($empid, $leavetypeid, $fyStart, $fyEnd, array $leaveType, array $holidayMap = [], $excludeId = 0, $workdays = null) {
    $sql = "SELECT id, startfrom, endon FROM " . table('empleaves') . "
            WHERE empid=? AND leavetypeid=? AND omitted=0 AND isapproved=0
              AND rejectedat IS NULL AND startfrom BETWEEN ? AND ?";
    $params = [(int)$empid, (int)$leavetypeid, $fyStart, $fyEnd];
    if ($excludeId) {
        $sql .= " AND id<>?";
        $params[] = (int)$excludeId;
    }

    $days = 0;
    foreach (fetchAll($sql, $params) as $row) {
        $days += leave_count_days($row['startfrom'], $row['endon'], $leaveType, $holidayMap, $workdays);
    }
    return $days;
}

/**
 * Validate a new or edited leave request against the leave type rules.
 *
 * @param int    $empid
 * @param int    $leavetypeid
 * @param string $start       Y-m-d
 * @param string $end         Y-m-d
 * @param int    $excludeId   Record being edited (0 on add)
 * @return array
 */
function leave_evaluate_request($empid, $leavetypeid, $start, $end, $excludeId = 0) {
    $result = [
        'ok'          => false,
        'errors'      => [],
        'leaveType'   => null,
        'fiscalYear'  => null,
        'days'        => 0,
        'remaining'   => 0,
        'pendingDays' => 0,
        'available'   => 0,
        'quota'       => ['life' => 0, 'year' => 0],
        'holidayMap'  => [],
        'workdays'    => null,
    ];

    $empid = (int)$empid;
    $leavetypeid = (int)$leavetypeid;
    $workdays = null;

    if ($empid <= 0) {
        $result['errors'][] = 'Employee is required.';
    } else {
        $employee = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [$empid]);
        if (!$employee) {
            $result['errors'][] = 'Employee not found.';
        } else {
            $workdays = leave_employee_workdays($empid);
        }
    }
    $result['workdays'] = $workdays;

    $lt = null;
    if ($leavetypeid <= 0) {
        $result['errors'][] = 'Leave type is required.';
    } else {
        $lt = fetchRow("SELECT * FROM " . table('leavestypes') . " WHERE id=? AND omitted=0 LIMIT 1", [$leavetypeid]);
        if (!$lt) {
            $result['errors'][] = 'Leave type not found.';
        }
    }
    $result['leaveType'] = $lt;

    $s = leave_parse_date($start);
    $e = leave_parse_date($end);
    if (!$s || !$e) {
        $result['errors'][] = 'Start and end dates are required (YYYY-MM-DD).';
    } elseif ($e < $s) {
        $result['errors'][] = 'End date cannot be older than start date.';
    } elseif ($lt) {
        $fyStart = leave_fiscal_year($s->format('Y-m-d'));
        $fyEnd = leave_fiscal_year($e->format('Y-m-d'));
        if (!$fyStart || !$fyEnd || (int)$fyStart['id'] !== (int)$fyEnd['id']) {
            $result['errors'][] = 'Leave period cannot span between two fiscal years.';
        } else {
            $result['fiscalYear'] = $fyStart;
            $holidayMap = leave_holiday_map($s->format('Y-m-d'), $e->format('Y-m-d'));
            $result['holidayMap'] = $holidayMap;

            $days = leave_count_days($s->format('Y-m-d'), $e->format('Y-m-d'), $lt, $holidayMap, $workdays);
            $result['days'] = $days;
            if ($days <= 0) {
                $result['errors'][] = leave_no_days_message($workdays, $holidayMap, 'request');
            }

            $quota = leave_quota_usage($empid, $leavetypeid, $fyStart['fstartdate'], $fyStart['fenddate'], $excludeId);
            $result['quota'] = $quota;
            if ((int)$lt['tpel'] > 0 && $quota['life'] >= (int)$lt['tpel']) {
                $result['errors'][] = 'This leave type allows at most ' . (int)$lt['tpel'] . ' request(s) per employee life.';
            }
            if ((int)$lt['tpey'] > 0 && $quota['year'] >= (int)$lt['tpey']) {
                $result['errors'][] = 'This leave type allows at most ' . (int)$lt['tpey'] . ' request(s) per fiscal year.';
            }

            $bal = fetchRow(
                "SELECT remainingbalance FROM " . table('leavesbalances') . "
                 WHERE empid=? AND fiscalyearid=? AND leavetypeid=? AND omitted=0 LIMIT 1",
                [$empid, $fyStart['id'], $leavetypeid]
            );
            $remaining = (int)($bal['remainingbalance'] ?? 0);
            $pending = leave_pending_days(
                $empid, $leavetypeid, $fyStart['fstartdate'], $fyStart['fenddate'],
                $lt, $holidayMap, $excludeId, $workdays
            );
            $result['remaining'] = $remaining;
            $result['pendingDays'] = $pending;
            $result['available'] = $remaining - $pending;

            if ($days > $result['available']) {
                $result['errors'][] = 'Not enough leave balance. Requested ' . $days
                    . ' day(s), available ' . max(0, $result['available']) . '.';
            }
        }
    }

    $result['ok'] = empty($result['errors']);
    return $result;
}

/**
 * Re-check an existing pending request before approval.
 * Returns the current day count, fiscal year and balance.
 */
function leave_evaluate_approval(array $leave) {
    $result = [
        'ok'         => false,
        'errors'     => [],
        'days'       => 0,
        'fiscalYear' => null,
        'remaining'  => 0,
        'leaveType'  => null,
        'workdays'   => null,
    ];

    $lt = fetchRow("SELECT * FROM " . table('leavestypes') . " WHERE id=? AND omitted=0 LIMIT 1", [(int)($leave['leavetypeid'] ?? 0)]);
    if (!$lt) {
        $result['errors'][] = 'Leave type not found.';
        return $result;
    }
    $result['leaveType'] = $lt;

    $fyStart = leave_fiscal_year($leave['startfrom'] ?? '');
    $fyEnd = leave_fiscal_year($leave['endon'] ?? '');
    if (!$fyStart || !$fyEnd || (int)$fyStart['id'] !== (int)$fyEnd['id']) {
        $result['errors'][] = 'No single fiscal year covers this leave period.';
        return $result;
    }
    $result['fiscalYear'] = $fyStart;

    $holidayMap = leave_holiday_map($leave['startfrom'], $leave['endon']);
    $workdays = leave_employee_workdays((int)($leave['empid'] ?? 0));
    $result['workdays'] = $workdays;
    $days = leave_count_days($leave['startfrom'], $leave['endon'], $lt, $holidayMap, $workdays);
    $result['days'] = $days;
    if ($days <= 0) {
        $result['errors'][] = leave_no_days_message($workdays, $holidayMap, 'approve');
        return $result;
    }

    $bal = fetchRow(
        "SELECT id, remainingbalance FROM " . table('leavesbalances') . "
         WHERE empid=? AND fiscalyearid=? AND leavetypeid=? AND omitted=0 LIMIT 1",
        [(int)$leave['empid'], $fyStart['id'], (int)$leave['leavetypeid']]
    );
    if (!$bal) {
        $result['errors'][] = 'No leave balance record found for this fiscal year.';
        return $result;
    }

    $remaining = (int)$bal['remainingbalance'];
    $result['remaining'] = $remaining;
    if ($days > $remaining) {
        $result['errors'][] = 'Not enough leave balance to approve. Requested ' . $days
            . ' day(s), available ' . $remaining . '.';
        return $result;
    }

    $result['ok'] = true;
    return $result;
}
