<?php
/**
 * UnitHR - Attendance engine.
 */

function attendance_status_labels() {
    $isAr = function_exists('lang') && lang() === 'ar';
    return $isAr ? [
        'PRESENT'            => 'حاضر',
        'ABSENT'             => 'غائب',
        'LATE'               => 'متأخر',
        'EARLY_DEPARTURE'    => 'مغادرة مبكرة',
        'INCOMPLETE'         => 'ناقص',
        'DAY_OFF'            => 'يوم راحة',
        'HOLIDAY'            => 'عطلة رسمية',
        'ON_LEAVE'           => 'في إجازة',
        'PERSONAL_PERMISSION'=> 'إذن شخصي',
        'OFFICIAL_BUSINESS'  => 'مهمة رسمية',
        'WORK_ASSIGNMENT'    => 'تكليف عمل',
    ] : [
        'PRESENT'            => 'Present',
        'ABSENT'             => 'Absent',
        'LATE'               => 'Late',
        'EARLY_DEPARTURE'    => 'Early Departure',
        'INCOMPLETE'         => 'Incomplete',
        'DAY_OFF'            => 'Day Off',
        'HOLIDAY'            => 'Holiday',
        'ON_LEAVE'           => 'On Leave',
        'PERSONAL_PERMISSION'=> 'Personal Permission',
        'OFFICIAL_BUSINESS'  => 'Official Business',
        'WORK_ASSIGNMENT'    => 'Work Assignment',
    ];
}

function attendance_status_label($code) {
    $labels = attendance_status_labels();
    return $labels[$code] ?? $code;
}

function attendance_effective_schedule($empid, $date) {
    $emp = fetchRow("SELECT id, depid, empgroup FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [(int)$empid]);
    if (!$emp) return null;
    $assignments = fetchAll(
        "SELECT sa.*, ws.start_time, ws.end_time, ws.is_active, ws.name_ar, ws.name_en
         FROM " . table('schedule_assignments') . " sa
         JOIN " . table('work_schedules') . " ws ON ws.id = sa.schedule_id
         WHERE sa.omitted=0 AND ws.is_active=1 AND ws.omitted=0
           AND sa.effective_from <= ?
           AND (sa.effective_to IS NULL OR sa.effective_to >= ?)
           AND (
                (sa.assignment_type='employee' AND sa.employee_id=?) OR
                (sa.assignment_type='department' AND sa.depid=?) OR
                (sa.assignment_type='group' AND sa.empgroup=?)
           )",
        [$date, $date, (int)$empid, $emp['depid'], $emp['empgroup']]
    );
    if (empty($assignments)) return null;
    $rank = ['employee'=>3,'department'=>2,'group'=>1];
    $best = null;
    foreach ($assignments as $a) {
        if ($best === null) { $best = $a; continue; }
        $ra = $rank[$a['assignment_type']] ?? 0;
        $rb = $rank[$best['assignment_type']] ?? 0;
        if ($ra > $rb) { $best = $a; continue; }
        if ($ra === $rb) {
            if (strcmp($a['effective_from'], $best['effective_from']) > 0) { $best = $a; continue; }
            if ($a['effective_from'] === $best['effective_from'] && (int)$a['priority'] > (int)$best['priority']) { $best = $a; continue; }
        }
    }
    if (!$best) return null;
    $days = fetchAll("SELECT day_of_week, is_working_day FROM " . table('work_schedule_days') . " WHERE schedule_id=? ", [(int)$best['schedule_id']]);
    $dayMap = [];
    foreach ($days as $d) $dayMap[(int)$d['day_of_week']] = (int)$d['is_working_day'];
    return [
        'assignment'   => $best,
        'schedule_id'  => (int)$best['schedule_id'],
        'name_ar'      => $best['name_ar'],
        'name_en'      => $best['name_en'],
        'start_time'   => $best['start_time'],
        'end_time'     => $best['end_time'],
        'day_map'      => $dayMap,
    ];
}

function attendance_approved_leave($empid, $date) {
    $row = fetchRow(
        "SELECT id FROM " . table('empleaves') . "
         WHERE empid=? AND omitted=0 AND isapproved=1 AND rejectedat IS NULL
           AND startfrom <= ? AND endon >= ? LIMIT 1",
        [(int)$empid, $date, $date]
    );
    return (bool)$row;
}

function attendance_is_holiday($date) {
    $rows = fetchAll("SELECT holidaydate, isrecurring FROM " . table('holidays') . " WHERE omitted=0");
    $md = (new DateTimeImmutable($date))->format('m-d');
    foreach ($rows as $h) {
        if (!empty($h['isrecurring'])) {
            if (substr($h['holidaydate'], 5, 5) === $md) return true;
        } else {
            if ($h['holidaydate'] === $date) return true;
        }
    }
    return false;
}

function attendance_punches($empid, $date) {
    $start = $date . ' 00:00:00';
    $end   = $date . ' 23:59:59';
    return fetchAll(
        "SELECT * FROM " . table('biometric_punches') . "
         WHERE employee_id=? AND omitted=0 AND punch_datetime BETWEEN ? AND ? ORDER BY punch_datetime ASC",
        [(int)$empid, $start, $end]
    );
}

function attendance_calculate($empid, $date, $force = false) {
    $empid = (int)$empid;
    $existing = fetchRow("SELECT * FROM " . table('daily_attendance') . " WHERE employee_id=? AND attendance_date=? LIMIT 1", [$empid, $date]);
    if ($existing && (int)$existing['manual_adjusted'] === 1 && !$force) {
        return $existing;
    }

    $sched = attendance_effective_schedule($empid, $date);
    $punches = attendance_punches($empid, $date);
    $now = date('Y-m-d H:i:s');
    $u = function_exists('current_user') ? current_user() : null;
    $an = $u ? ($u['username_ar'] ?? $u['username']) : 'System';

    if (!$sched) {
        // No effective schedule: if punches exist, still record as PRESENT (no
        // schedule-based late/OT). Otherwise skip (no absence without a schedule).
        if (empty($punches)) {
            if ($existing && (int)$existing['manual_adjusted'] === 0) {
                q("UPDATE " . table('daily_attendance') . " SET omitted=1 WHERE id=?", [$existing['id']]);
            }
            return null;
        }
        // create/update a punch-only record
        $firstPunch = $punches[0]['punch_datetime'];
        $lastPunch = count($punches) > 1 ? $punches[count($punches)-1]['punch_datetime'] : $firstPunch;
        $checkoutSource = count($punches) > 1 ? 'BIOMETRIC' : 'ASSUMED';
        if (count($punches) === 1) {
            $lastPunch = $firstPunch; // no assumed checkout without schedule end
            $checkoutSource = 'BIOMETRIC';
        }
        $data = [
            'employee_id' => $empid,
            'attendance_date' => $date,
            'schedule_id' => null,
            'scheduled_start' => null,
            'scheduled_end' => null,
            'first_punch' => $firstPunch,
            'last_punch' => $lastPunch,
            'checkout_source' => $checkoutSource,
            'status_code' => 'PRESENT',
            'late_minutes' => 0,
            'early_departure_minutes' => 0,
            'early_overtime_minutes' => 0,
            'late_overtime_minutes' => 0,
            'total_overtime_minutes' => 0,
            'calculation_status' => 'CALCULATED',
            'calculated_at' => $now,
        ];
        if ($existing) {
            $sets = []; $params = [];
            foreach ($data as $k => $v) { $sets[] = "$k=?"; $params[] = $v; }
            $params[] = $existing['id'];
            q("UPDATE " . table('daily_attendance') . " SET " . implode(',', $sets) . ", manual_adjusted=0, lastedit=NOW() WHERE id=?", $params);
            $id = $existing['id'];
        } else {
            $rguid = bin2hex(random_bytes(16));
            q("INSERT INTO " . table('daily_attendance') . " (rguid, employee_id, attendance_date, schedule_id, scheduled_start, scheduled_end, first_punch, last_punch, checkout_source, status_code, late_minutes, early_departure_minutes, early_overtime_minutes, late_overtime_minutes, total_overtime_minutes, calculation_status, calculated_at, dtadded, addedbyid, addedby, addedbyname, addedbynamear, omitted) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,0)",
                [$rguid, $empid, $date, null, null, null, $firstPunch, $lastPunch, $checkoutSource, 'PRESENT', 0, 0, 0, 0, 0, 'CALCULATED', $now, $u ? $u['id'] : 1, $u ? $u['username'] : 'System', $an, $an]);
            $id = lastInsertId();
        }
        return fetchRow("SELECT * FROM " . table('daily_attendance') . " WHERE id=?", [$id]);
    }

    $dayOfWeek = (int)(new DateTimeImmutable($date))->format('w');
    $isWorking = isset($sched['day_map'][$dayOfWeek]) ? (int)$sched['day_map'][$dayOfWeek] : 0;

    $punches = attendance_punches($empid, $date);
    $firstPunch = null; $lastPunch = null;
    if (!empty($punches)) {
        $firstPunch = $punches[0]['punch_datetime'];
        $lastPunch  = $punches[count($punches)-1]['punch_datetime'];
    }

    $status = 'DAY_OFF';
    $late = 0; $early = 0; $earlyOT = 0; $lateOT = 0; $totalOT = 0;
    $checkoutSource = null;

    $schedStart = $sched['start_time'];
    $schedEnd   = $sched['end_time'];
    $schedStartDt = new DateTimeImmutable($date . ' ' . $schedStart);
    $schedEndDt   = new DateTimeImmutable($date . ' ' . $schedEnd);

    if (!$isWorking) {
        $status = 'DAY_OFF';
    } elseif (attendance_approved_leave($empid, $date)) {
        $status = 'ON_LEAVE';
    } elseif (attendance_is_holiday($date)) {
        $status = 'HOLIDAY';
    } elseif (empty($punches)) {
        $status = 'ABSENT';
    } else {
        $firstDt = new DateTimeImmutable($firstPunch);
        if (count($punches) === 1) {
            $checkoutSource = 'ASSUMED';
            $assumedCheckout = $schedEndDt;
            $lastDt = $assumedCheckout;
            $lastPunch = $assumedCheckout->format('Y-m-d H:i:s');
        } else {
            $checkoutSource = 'BIOMETRIC';
            $lastDt = new DateTimeImmutable($lastPunch);
        }
        if ($firstDt > $schedStartDt) {
            $late = (int)(($firstDt->getTimestamp() - $schedStartDt->getTimestamp()) / 60);
        }
        if ($checkoutSource === 'BIOMETRIC' && $lastDt < $schedEndDt) {
            $early = (int)(($schedEndDt->getTimestamp() - $lastDt->getTimestamp()) / 60);
        }
        if ($firstDt < $schedStartDt) {
            $earlyOT = (int)(($schedStartDt->getTimestamp() - $firstDt->getTimestamp()) / 60);
        }
        if ($checkoutSource === 'BIOMETRIC' && $lastDt > $schedEndDt) {
            $lateOT = (int)(($lastDt->getTimestamp() - $schedEndDt->getTimestamp()) / 60);
        }
        $totalOT = $earlyOT + $lateOT;
        if ($late > 0) $status = 'LATE';
        elseif ($early > 0) $status = 'EARLY_DEPARTURE';
        else $status = 'PRESENT';
    }

    if (empty($punches)) $checkoutSource = null;

    $data = [
        'employee_id'            => $empid,
        'attendance_date'        => $date,
        'schedule_id'            => $sched['schedule_id'],
        'scheduled_start'        => $schedStart,
        'scheduled_end'          => $schedEnd,
        'first_punch'            => $firstPunch,
        'last_punch'             => $lastPunch,
        'checkout_source'        => $checkoutSource,
        'status_code'            => $status,
        'late_minutes'           => $late,
        'early_departure_minutes'=> $early,
        'early_overtime_minutes' => $earlyOT,
        'late_overtime_minutes'  => $lateOT,
        'total_overtime_minutes' => $totalOT,
        'calculation_status'     => 'CALCULATED',
        'calculated_at'          => $now,
    ];

    if ($existing) {
        $sets = []; $params = [];
        foreach ($data as $k => $v) { $sets[] = "$k=?"; $params[] = $v; }
        $params[] = $existing['id'];
        q("UPDATE " . table('daily_attendance') . " SET " . implode(',', $sets) . ", manual_adjusted=0, lastedit=NOW() WHERE id=?", $params);
        $id = $existing['id'];
    } else {
        $rguid = bin2hex(random_bytes(16));
        q("INSERT INTO " . table('daily_attendance') . "
           (rguid, employee_id, attendance_date, schedule_id, scheduled_start, scheduled_end, first_punch, last_punch, checkout_source, status_code, late_minutes, early_departure_minutes, early_overtime_minutes, late_overtime_minutes, total_overtime_minutes, calculation_status, calculated_at, dtadded, addedbyid, addedby, addedbyname, addedbynamear, omitted)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,0)",
           [$rguid, $empid, $date, $sched['schedule_id'], $schedStart, $schedEnd, $firstPunch, $lastPunch, $checkoutSource, $status, $late, $early, $earlyOT, $lateOT, $totalOT, 'CALCULATED', $now,
            $u ? $u['id'] : 1, $u ? $u['username'] : 'System', $an, $an]);
        $id = lastInsertId();
    }
    return fetchRow("SELECT * FROM " . table('daily_attendance') . " WHERE id=?", [$id]);
}

function attendance_recalculate_range($empid, $from, $to, $force = false) {
    $s = new DateTimeImmutable($from);
    $e = new DateTimeImmutable($to);
    $cur = $s;
    while ($cur <= $e) {
        attendance_calculate((int)$empid, $cur->format('Y-m-d'), $force);
        $cur = $cur->modify('+1 day');
    }
}

function attendance_recalculate_all($from, $to, $force = false) {
    $emps = fetchAll("SELECT id FROM " . table('employeesmain') . " WHERE omitted=0");
    foreach ($emps as $emp) {
        attendance_recalculate_range((int)$emp['id'], $from, $to, $force);
    }
}
