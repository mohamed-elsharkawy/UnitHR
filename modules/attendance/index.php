<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

require_once __DIR__ . '/../../includes/attendance.php';
require_login();
$module = 'attendance';
$isAr = lang() === 'ar';

function att_name($row) {
    global $isAr;
    return $isAr ? ($row['name_ar'] ?? '') : ($row['name_en'] ?? '');
}

$action = $_GET['a'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---------- SCHEDULE SAVE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['schedule_add','schedule_edit'], true)) {
    require_permission($module, $action === 'schedule_add' ? 'add' : 'edit');
    $nameAr = trim($_POST['name_ar'] ?? '');
    $nameEn = trim($_POST['name_en'] ?? '');
    $descAr = trim($_POST['description_ar'] ?? '');
    $descEn = trim($_POST['description_en'] ?? '');
    $start  = trim($_POST['start_time'] ?? '');
    $end    = trim($_POST['end_time'] ?? '');
    $active = !empty($_POST['is_active']) ? 1 : 0;
    $days   = $_POST['workdays'] ?? [];
    if ($nameAr === '' || $nameEn === '' || $start === '' || $end === '') {
        set_flash('error', $isAr ? 'جميع الحقول المطلوبة.' : 'All required fields.');
        header('Location: ?module=attendance&a=' . $action . ($action==='schedule_edit' ? '&id='.$id : ''));
        exit;
    }
    $u = current_user(); $an = $u['username_ar'] ?? $u['username'];
    $now = date('Y-m-d H:i:s');
    if ($action === 'schedule_add') {
        $rguid = bin2hex(random_bytes(16));
        q("INSERT INTO " . table('work_schedules') . " (rguid,name_ar,name_en,description_ar,description_en,start_time,end_time,is_active,dtadded,addedbyid,addedby,addedbyname,addedbynamear,lastedit,editedbyid,editedby,editedbyname,editedbynamear,omitted) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)",
            [$rguid,$nameAr,$nameEn,$descAr,$descEn,$start,$end,$active,$now,$u['id'],$u['username'],$an,$an,$now,$u['id'],$u['username'],$an,$an]);
        $sid = (int)lastInsertId();
    } else {
        q("UPDATE " . table('work_schedules') . " SET name_ar=?,name_en=?,description_ar=?,description_en=?,start_time=?,end_time=?,is_active=?,lastedit=NOW(),editedbyid=?,editedby=?,editedbyname=?,editedbynamear=? WHERE id=?",
            [$nameAr,$nameEn,$descAr,$descEn,$start,$end,$active,$u['id'],$u['username'],$an,$an,$id]);
        $sid = $id;
    }
    // replace days
    q("DELETE FROM " . table('work_schedule_days') . " WHERE schedule_id=?", [$sid]);
    for ($d = 0; $d <= 6; $d++) {
        $isWork = in_array((string)$d, array_map('strval', $days), true) ? 1 : 0;
        q("INSERT INTO " . table('work_schedule_days') . " (schedule_id,day_of_week,is_working_day) VALUES (?,?,?)", [$sid,$d,$isWork]);
    }
    set_flash('success', $isAr ? 'تم حفظ الدوام.' : 'Schedule saved.');
    header('Location: ?module=attendance&a=schedules'); exit;
}

// ---------- ASSIGNMENT SAVE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['assignment_add','assignment_edit'], true)) {
    require_permission($module, $action === 'assignment_add' ? 'add' : 'edit');
    $type = trim($_POST['assignment_type'] ?? 'employee');
    $empid = ($_POST['employee_id'] ?? '') ?: null;
    $depid = ($_POST['depid'] ?? '') ?: null;
    $empgroup = ($_POST['empgroup'] ?? '') ?: null;
    $sid = (int)($_POST['schedule_id'] ?? 0);
    $from = trim($_POST['effective_from'] ?? '');
    $to = trim($_POST['effective_to'] ?? '') ?: null;
    $prio = (int)($_POST['priority'] ?? 0);
    if ($sid <= 0 || $from === '') {
        set_flash('error', $isAr ? 'الدوام وتاريخ البدء مطلوبان.' : 'Schedule and effective from are required.');
        header('Location: ?module=attendance&a=' . $action . ($action==='assignment_edit' ? '&id='.$id : ''));
        exit;
    }
    $u = current_user(); $an = $u['username_ar'] ?? $u['username'];
    $now = date('Y-m-d H:i:s');
    if ($action === 'assignment_add') {
        $rguid = bin2hex(random_bytes(16));
        q("INSERT INTO " . table('schedule_assignments') . " (rguid,assignment_type,employee_id,depid,empgroup,schedule_id,effective_from,effective_to,priority,dtadded,addedbyid,addedby,addedbyname,addedbynamear,lastedit,editedbyid,editedby,editedbyname,editedbynamear,omitted) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)",
            [$rguid,$type,$empid,$depid,$empgroup,$sid,$from,$to,$prio,$now,$u['id'],$u['username'],$an,$an,$now,$u['id'],$u['username'],$an,$an]);
    } else {
        q("UPDATE " . table('schedule_assignments') . " SET assignment_type=?,employee_id=?,depid=?,empgroup=?,schedule_id=?,effective_from=?,effective_to=?,priority=?,lastedit=NOW(),editedbyid=?,editedby=?,editedbyname=?,editedbynamear=? WHERE id=?",
            [$type,$empid,$depid,$empgroup,$sid,$from,$to,$prio,$u['id'],$u['username'],$an,$an,$id]);
    }
    set_flash('success', $isAr ? 'تم حفظ التعيين.' : 'Assignment saved.');
    header('Location: ?module=attendance&a=assignments'); exit;
}

// ---------- PUNCH SAVE (manual) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'punch_add') {
    require_permission($module, 'add');
    $empid = (int)($_POST['employee_id'] ?? 0);
    $device = trim($_POST['device_id'] ?? '');
    $dt = trim($_POST['punch_datetime'] ?? '');
    $dt = str_replace('T', ' ', $dt);
    if (strlen($dt) === 16) $dt .= ':00'; // YYYY-MM-DD HH:MM -> add seconds
    $src = trim($_POST['source_record_id'] ?? '');
    $info = trim($_POST['source_info'] ?? 'Manual');
    if ($empid <= 0 || $dt === '') {
        set_flash('error', $isAr ? 'الموظف والوقت مطلوبان.' : 'Employee and datetime required.');
        header('Location: ?module=attendance&a=punches'); exit;
    }
    // dedup
    $dup = null;
    if ($src !== '') {
        $dup = fetchRow("SELECT id FROM " . table('biometric_punches') . " WHERE source_record_id=? LIMIT 1", [$src]);
    }
    if (!$dup) {
        $dup = fetchRow("SELECT id FROM " . table('biometric_punches') . " WHERE employee_id=? AND device_id=? AND punch_datetime=? LIMIT 1", [$empid,$device,$dt]);
    }
    if ($dup) {
        set_flash('error', $isAr ? 'بصمة مكررة (تم تجاهلها).' : 'Duplicate punch (ignored).');
    } else {
        $rguid = bin2hex(random_bytes(16));
        $u = current_user(); $an = $u['username_ar'] ?? $u['username'];
        q("INSERT INTO " . table('biometric_punches') . " (rguid,employee_id,device_id,punch_datetime,source_record_id,imported_at,source_info,raw_payload,dtadded,addedbyid,addedby,addedbyname,addedbynamear,omitted) VALUES (?,?,?,?,?,NOW(),?,NULL,NOW(),?,?,?,?,0)",
            [$rguid,$empid,$device,$dt,$src ?: null,$info,$u['id'],$u['username'],$an,$an]);
        // Auto-recalculate attendance for this employee/date so the punch shows up.
        attendance_calculate($empid, substr($dt, 0, 10), true);
        set_flash('success', $isAr ? 'تمت إضافة البصمة.' : 'Punch added.');
    }
    header('Location: ?module=attendance&a=punches'); exit;
}

// ---------- CSV IMPORT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'punch_import') {
    require_permission($module, 'add');
    $imported = 0; $skipped = 0;
    $affected = [];
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if ($handle) {
            $header = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 3) { $skipped++; continue; }
                $empid = (int)$row[0];
                $device = trim($row[1] ?? '');
                $dt = trim($row[2] ?? '');
                $dt = str_replace('T', ' ', $dt);
                if (strlen($dt) === 16) $dt .= ':00';
                $src = trim($row[3] ?? '');
                $info = trim($row[4] ?? 'CSV Import');
                if ($empid <= 0 || $dt === '') { $skipped++; continue; }
                $dup = null;
                if ($src !== '') $dup = fetchRow("SELECT id FROM " . table('biometric_punches') . " WHERE source_record_id=? LIMIT 1", [$src]);
                if (!$dup) $dup = fetchRow("SELECT id FROM " . table('biometric_punches') . " WHERE employee_id=? AND device_id=? AND punch_datetime=? LIMIT 1", [$empid,$device,$dt]);
                if ($dup) { $skipped++; continue; }
                $rguid = bin2hex(random_bytes(16));
                $u = current_user(); $an = $u['username_ar'] ?? $u['username'];
                $raw = json_encode($row, JSON_UNESCAPED_UNICODE);
                q("INSERT INTO " . table('biometric_punches') . " (rguid,employee_id,device_id,punch_datetime,source_record_id,imported_at,source_info,raw_payload,dtadded,addedbyid,addedby,addedbyname,addedbynamear,omitted) VALUES (?,?,?,?,?,NOW(),?,?,NOW(),?,?,?,?,0)",
                    [$rguid,$empid,$device,$dt,$src ?: null,$info,$raw,$u['id'],$u['username'],$an,$an]);
                $imported++;
                $affected[$empid . '|' . substr($dt,0,10)] = [$empid, substr($dt,0,10)];
            }
            fclose($handle);
        }
    }
    foreach ($affected as $pair) {
        attendance_calculate((int)$pair[0], $pair[1], true);
    }
    set_flash('success', ($isAr ? 'تم استيراد ' : 'Imported ') . $imported . ($isAr ? ' بصمة، تم تجاهل ' : ' punches, skipped ') . $skipped . '.');
    header('Location: ?module=attendance&a=punches'); exit;
}

// ---------- RECALC ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'recalc') {
    require_permission($module, 'edit');
    $empid = (int)($_POST['employee_id'] ?? 0);
    $from = trim($_POST['from_date'] ?? $_POST['date_from'] ?? '');
    $to = trim($_POST['to_date'] ?? $_POST['date_to'] ?? '');
    $force = !empty($_POST['force']);
    if ($from === '' || $to === '') {
        set_flash('error', $isAr ? 'حدد الفترة.' : 'Select period.');
        header('Location: ?module=attendance'); exit;
    }
    if ($empid > 0) {
        attendance_recalculate_range($empid, $from, $to, $force);
    } else {
        attendance_recalculate_all($from, $to, $force);
    }
    set_flash('success', $isAr ? 'تم إعادة الحساب.' : 'Recalculation done.');
    header('Location: ?module=attendance&date_from=' . urlencode($from) . '&date_to=' . urlencode($to)); exit;
}

// ---------- SCHEDULE DELETE ----------
if ($action === 'schedule_del' && $id) {
    require_permission($module, 'del');
    q("UPDATE " . table('work_schedules') . " SET omitted=1 WHERE id=?", [$id]);
    set_flash('success', $isAr ? 'تم الحذف.' : 'Deleted.');
    header('Location: ?module=attendance&a=schedules'); exit;
}
// ---------- ASSIGNMENT DELETE ----------
if ($action === 'assignment_del' && $id) {
    require_permission($module, 'del');
    q("UPDATE " . table('schedule_assignments') . " SET omitted=1 WHERE id=?", [$id]);
    set_flash('success', $isAr ? 'تم الحذف.' : 'Deleted.');
    header('Location: ?module=attendance&a=assignments'); exit;
}
// ---------- PUNCH DELETE ----------
if ($action === 'punch_del' && $id) {
    require_permission($module, 'del');
    q("UPDATE " . table('biometric_punches') . " SET omitted=1 WHERE id=?", [$id]);
    set_flash('success', $isAr ? 'تم الحذف.' : 'Deleted.');
    header('Location: ?module=attendance&a=punches'); exit;
}

$employees = fetchAll("SELECT id, empfullname, empfullnamear, depid, empgroup FROM " . table('employeesmain') . " WHERE omitted=0 ORDER BY empfullname ASC");
$departments = fetchAll("SELECT id, depname, depnamear FROM " . table('depstructure') . " WHERE omitted=0 ORDER BY depname ASC");
$groups = fetchAll("SELECT id, empgroupname, empgroupnamear FROM " . table('employeesgroup') . " WHERE omitted=0 ORDER BY empgroupname ASC");

// ---------- LIST (daily attendance) ----------
if ($action === 'list' || $action === '') {
    require_permission($module, 'view');
    $dateFrom = trim($_GET['date_from'] ?? date('Y-m-01'));
    $dateTo   = trim($_GET['date_to']   ?? date('Y-m-d'));
    $empFilter = (int)($_GET['empid'] ?? 0);
    $depFilter = (int)($_GET['depid'] ?? 0);
    $statusFilter = trim($_GET['status'] ?? '');
    $where = "da.omitted=0 AND da.attendance_date BETWEEN ? AND ?";
    $params = [$dateFrom, $dateTo];
    if ($empFilter > 0) { $where .= " AND da.employee_id=?"; $params[] = $empFilter; }
    if ($depFilter > 0) { $where .= " AND e.depid=?"; $params[] = $depFilter; }
    if ($statusFilter !== '') { $where .= " AND da.status_code=?"; $params[] = $statusFilter; }
    $rows = fetchAll("SELECT da.*, e.empfullname, e.empfullnamear, e.depid FROM " . table('daily_attendance') . " da JOIN " . table('employeesmain') . " e ON e.id=da.employee_id WHERE $where ORDER BY da.attendance_date DESC, e.empfullname ASC", $params);

    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-clock-history me-2"></i>' . ($isAr ? 'الحضور والانصراف' : 'Attendance') . '</h2>
            <div>
              <a href="?module=attendance&a=schedules" class="btn btn-outline-primary btn-sm">' . ($isAr ? 'الدوامات' : 'Schedules') . '</a>
              <a href="?module=attendance&a=assignments" class="btn btn-outline-primary btn-sm">' . ($isAr ? 'التعيينات' : 'Assignments') . '</a>
              <a href="?module=attendance&a=punches" class="btn btn-outline-primary btn-sm">' . ($isAr ? 'البصمات' : 'Punches') . '</a>
              <a href="?module=attendance&a=reports" class="btn btn-outline-secondary btn-sm">' . ($isAr ? 'التقارير' : 'Reports') . '</a>
            </div>
          </div>';

    // filters
    echo '<form method="get" class="row g-2 mb-3">
            <input type="hidden" name="module" value="attendance">
            <div class="col-md-2"><label class="form-label">' . ($isAr?'من':'From') . '</label><input type="date" name="date_from" value="' . htmlspecialchars($dateFrom) . '" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label">' . ($isAr?'إلى':'To') . '</label><input type="date" name="date_to" value="' . htmlspecialchars($dateTo) . '" class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label">' . ($isAr?'الموظف':'Employee') . '</label><select name="empid" class="form-select form-select-sm"><option value="">-- ' . ($isAr?'الكل':'All') . ' --</option>';
    foreach ($employees as $e) echo '<option value="' . $e['id'] . '"' . ($empFilter==$e['id']?' selected':'') . '>' . htmlspecialchars($isAr?$e['empfullnamear']:$e['empfullname']) . '</option>';
    echo '</select></div>
            <div class="col-md-2"><label class="form-label">' . ($isAr?'القسم':'Dept') . '</label><select name="depid" class="form-select form-select-sm"><option value="">-- ' . ($isAr?'الكل':'All') . ' --</option>';
    foreach ($departments as $d) echo '<option value="' . $d['id'] . '"' . ($depFilter==$d['id']?' selected':'') . '>' . htmlspecialchars($isAr?$d['depnamear']:$d['depname']) . '</option>';
    echo '</select></div>
            <div class="col-md-2"><label class="form-label">' . ($isAr?'الحالة':'Status') . '</label><select name="status" class="form-select form-select-sm"><option value="">-- ' . ($isAr?'الكل':'All') . ' --</option>';
    foreach (attendance_status_labels() as $code=>$lbl) echo '<option value="' . $code . '"' . ($statusFilter===$code?' selected':'') . '>' . htmlspecialchars($lbl) . '</option>';
    echo '</select></div>
            <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100">' . ($isAr?'تصفية':'Filter') . '</button></div>
          </form>';

    // recalc form
    echo '<form method="post" action="?module=attendance&a=recalc" class="row g-2 mb-3 align-items-end">
            <div class="col-md-3"><label class="form-label">' . ($isAr?'الموظف (0=الكل)':'Employee (0=all)') . '</label><select name="employee_id" class="form-select form-select-sm"><option value="0">' . ($isAr?'الكل':'All') . '</option>';
    foreach ($employees as $e) echo '<option value="' . $e['id'] . '">' . htmlspecialchars($isAr?$e['empfullnamear']:$e['empfullname']) . '</option>';
    echo '</select></div>
            <div class="col-md-2"><label class="form-label">' . ($isAr?'من':'From') . '</label><input type="date" name="date_from" value="' . htmlspecialchars($dateFrom) . '" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label">' . ($isAr?'إلى':'To') . '</label><input type="date" name="date_to" value="' . htmlspecialchars($dateTo) . '" class="form-control form-control-sm"></div>
            <div class="col-md-2"><div class="form-check"><input type="checkbox" name="force" value="1" class="form-check-input" id="force"><label class="form-check-label" for="force">' . ($isAr?'(تجاوز اليدوي)':'Force (override manual)') . '</label></div></div>
            <div class="col-md-3"><button class="btn btn-warning btn-sm">' . ($isAr?'إعادة حساب':'Recalculate') . '</button></div>
          </form>';

    echo '<div class="table-responsive" style="max-height: calc(100vh / 3); overflow-y: auto;"><table class="table table-sm table-bordered table-hover">
            <thead class="table-light"><tr>
              <th>' . ($isAr?'التاريخ':'Date') . '</th>
              <th>' . ($isAr?'الموظف':'Employee') . '</th>
              <th>' . ($isAr?'الدوام':'Schedule') . '</th>
              <th>' . ($isAr?'البدء':'Start') . '</th>
              <th>' . ($isAr?'الانتهاء':'End') . '</th>
              <th>' . ($isAr?'تسجيل الدخول':'Check-in') . '</th>
              <th>' . ($isAr?'تسجيل الخروج':'Check-out') . '</th>
              <th>' . ($isAr?'المصدر':'Source') . '</th>
              <th>' . ($isAr?'الحالة':'Status') . '</th>
              <th>' . ($isAr?'تأخر':'Late') . '</th>
              <th>' . ($isAr?'مغادرة مبكرة':'Early Dep') . '</th>
              <th>' . ($isAr?'إضافي':'OT') . '</th>
            </tr></thead><tbody>';
    foreach ($rows as $r) {
        $schedName = '';
        if ($r['schedule_id']) {
            $s = fetchRow("SELECT name_ar,name_en FROM " . table('work_schedules') . " WHERE id=?", [$r['schedule_id']]);
            if ($s) $schedName = $isAr ? $s['name_ar'] : $s['name_en'];
        }
        echo '<tr>
          <td>' . htmlspecialchars($r['attendance_date']) . '</td>
          <td>' . htmlspecialchars($isAr ? $r['empfullnamear'] : $r['empfullname']) . '</td>
          <td>' . htmlspecialchars($schedName) . '</td>
          <td>' . htmlspecialchars($r['scheduled_start'] ?? '') . '</td>
          <td>' . htmlspecialchars($r['scheduled_end'] ?? '') . '</td>
          <td>' . htmlspecialchars($r['first_punch'] ? date('H:i', strtotime($r['first_punch'])) : '') . '</td>
          <td>' . htmlspecialchars($r['last_punch'] ? date('H:i', strtotime($r['last_punch'])) : '') . '</td>
          <td>' . htmlspecialchars($r['checkout_source'] ?? '') . '</td>
          <td>' . htmlspecialchars(attendance_status_label($r['status_code'])) . '</td>
          <td>' . (int)$r['late_minutes'] . '</td>
          <td>' . (int)$r['early_departure_minutes'] . '</td>
          <td>' . (int)$r['total_overtime_minutes'] . '</td>
        </tr>';
    }
    echo '</tbody></table></div>';
    if (empty($rows)) echo '<div class="alert alert-info">' . ($isAr ? 'لا توجد سجلات.' : 'No records.') . '</div>';
}

// ---------- SCHEDULES ----------
elseif ($action === 'schedules') {
    require_permission($module, 'view');
    $rows = fetchAll("SELECT * FROM " . table('work_schedules') . " WHERE omitted=0 ORDER BY name_en ASC");
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2>' . ($isAr ? 'الدوامات' : 'Work Schedules') . '</h2>
            <a href="?module=attendance&a=schedule_add" class="btn btn-primary btn-sm">' . ($isAr ? 'إضافة دوام' : 'Add Schedule') . '</a>
          </div>';
    echo '<div class="table-responsive"><table class="table table-sm table-bordered">
            <thead class="table-light"><tr>
              <th>' . ($isAr?'الاسم (AR)':'Name (AR)') . '</th>
              <th>' . ($isAr?'الاسم (EN)':'Name (EN)') . '</th>
              <th>' . ($isAr?'البدء':'Start') . '</th>
              <th>' . ($isAr?'الانتهاء':'End') . '</th>
              <th>' . ($isAr?'أيام العمل':'Working Days') . '</th>
              <th>' . ($isAr?'فعال':'Active') . '</th>
              <th></th>
            </tr></thead><tbody>';
    $dayLabels = $isAr ? ['أحد','اثنين','ثلاثاء','أربعاء','خميس','جمعة','سبت'] : ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    foreach ($rows as $r) {
        $ds = fetchAll("SELECT day_of_week,is_working_day FROM " . table('work_schedule_days') . " WHERE schedule_id=?", [$r['id']]);
        $workDays = [];
        foreach ($ds as $d) if ($d['is_working_day']) $workDays[] = $dayLabels[(int)$d['day_of_week']];
        echo '<tr>
          <td>' . htmlspecialchars($r['name_ar']) . '</td>
          <td>' . htmlspecialchars($r['name_en']) . '</td>
          <td>' . htmlspecialchars($r['start_time']) . '</td>
          <td>' . htmlspecialchars($r['end_time']) . '</td>
          <td>' . htmlspecialchars(implode(', ', $workDays)) . '</td>
          <td>' . ($r['is_active'] ? '✓' : '') . '</td>
          <td>
            <a href="?module=attendance&a=schedule_edit&id=' . $r['id'] . '" class="btn btn-sm btn-outline-secondary">' . ($isAr?'تعديل':'Edit') . '</a>
            <a href="?module=attendance&a=schedule_del&id=' . $r['id'] . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\''.($isAr?'حذف؟':'Delete?').'\')">' . ($isAr?'حذف':'Del') . '</a>
          </td>
        </tr>';
    }
    echo '</tbody></table></div>';
    if (empty($rows)) echo '<div class="alert alert-info">' . ($isAr ? 'لا توجد دوامات.' : 'No schedules.') . '</div>';
}

elseif (in_array($action, ['schedule_add','schedule_edit'], true)) {
    require_permission($module, $action === 'schedule_add' ? 'add' : 'edit');
    $row = null;
    $selectedDays = [];
    if ($action === 'schedule_edit' && $id) {
        $row = fetchRow("SELECT * FROM " . table('work_schedules') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
        if (!$row) { set_flash('error', $isAr?'غير موجود':'Not found'); header('Location: ?module=attendance&a=schedules'); exit; }
        $ds = fetchAll("SELECT day_of_week,is_working_day FROM " . table('work_schedule_days') . " WHERE schedule_id=?", [$id]);
        foreach ($ds as $d) if ($d['is_working_day']) $selectedDays[] = (int)$d['day_of_week'];
    }
    $dayLabels = $isAr ? ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'] : ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2>' . ($isAr ? ($action==='schedule_add'?'إضافة دوام':'تعديل دوام') : ($action==='schedule_add'?'Add Schedule':'Edit Schedule')) . '</h2>
            <a href="?module=attendance&a=schedules" class="btn btn-secondary btn-sm">' . ($isAr?'رجوع':'Back') . '</a>
          </div>';
    echo '<form method="post" action="?module=attendance&a=' . $action . ($action==='schedule_edit'?'&id='.$id:'') . '">';
    echo '<div class="row"><div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'الاسم (عربي)':'Name (Arabic)') . '</label><input type="text" name="name_ar" class="form-control" value="' . htmlspecialchars($row['name_ar'] ?? '') . '" required></div>
          <div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'الاسم (إنجليزي)':'Name (English)') . '</label><input type="text" name="name_en" class="form-control" value="' . htmlspecialchars($row['name_en'] ?? '') . '" required></div></div>';
    echo '<div class="row"><div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'الوصف (عربي)':'Description (Arabic)') . '</label><textarea name="description_ar" class="form-control">' . htmlspecialchars($row['description_ar'] ?? '') . '</textarea></div>
          <div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'الوصف (إنجليزي)':'Description (English)') . '</label><textarea name="description_en" class="form-control">' . htmlspecialchars($row['description_en'] ?? '') . '</textarea></div></div>';
    echo '<div class="row"><div class="col-md-3 mb-3"><label class="form-label">' . ($isAr?'وقت البدء':'Start Time') . '</label><input type="time" name="start_time" class="form-control" value="' . htmlspecialchars($row['start_time'] ?? '09:00') . '" required></div>
          <div class="col-md-3 mb-3"><label class="form-label">' . ($isAr?'وقت الانتهاء':'End Time') . '</label><input type="time" name="end_time" class="form-control" value="' . htmlspecialchars($row['end_time'] ?? '15:00') . '" required></div>
          <div class="col-md-3 mb-3"><div class="form-check mt-4"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"' . (($row['is_active'] ?? 1) ? ' checked' : '') . '><label class="form-check-label" for="is_active">' . ($isAr?'فعال':'Active') . '</label></div></div></div>';
    echo '<div class="mb-3"><label class="form-label">' . ($isAr?'أيام العمل':'Working Days') . '</label><div class="row">';
    for ($d = 0; $d <= 6; $d++) {
        $checked = in_array($d, $selectedDays, true) ? ' checked' : '';
        if ($row === null && $d >= 1 && $d <= 5) $checked = ' checked'; // default Mon-Fri
        echo '<div class="col-md-2"><div class="form-check"><input type="checkbox" name="workdays[]" value="' . $d . '" class="form-check-input" id="wd' . $d . '"' . $checked . '><label class="form-check-label" for="wd' . $d . '">' . $dayLabels[$d] . '</label></div></div>';
    }
    echo '</div></div>';
    echo '<button class="btn btn-primary">' . ($isAr?'حفظ':'Save') . '</button></form>';
}

// ---------- ASSIGNMENTS ----------
elseif ($action === 'assignments') {
    require_permission($module, 'view');
    $rows = fetchAll("SELECT sa.*, ws.name_ar, ws.name_en FROM " . table('schedule_assignments') . " sa JOIN " . table('work_schedules') . " ws ON ws.id=sa.schedule_id WHERE sa.omitted=0 ORDER BY sa.effective_from DESC");
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2>' . ($isAr ? 'تعيينات الدوام' : 'Schedule Assignments') . '</h2>
            <a href="?module=attendance&a=assignment_add" class="btn btn-primary btn-sm">' . ($isAr ? 'إضافة تعيين' : 'Add Assignment') . '</a>
          </div>';
    echo '<div class="table-responsive"><table class="table table-sm table-bordered">
            <thead class="table-light"><tr>
              <th>' . ($isAr?'النوع':'Type') . '</th>
              <th>' . ($isAr?'الموظف/القسم/المجموعة':'Employee/Dept/Group') . '</th>
              <th>' . ($isAr?'الدوام':'Schedule') . '</th>
              <th>' . ($isAr?'من':'From') . '</th>
              <th>' . ($isAr?'إلى':'To') . '</th>
              <th></th>
            </tr></thead><tbody>';
    foreach ($rows as $r) {
        $target = '';
        if ($r['assignment_type'] === 'employee' && $r['employee_id']) {
            $e = fetchRow("SELECT empfullname,empfullnamear FROM " . table('employeesmain') . " WHERE id=?", [$r['employee_id']]);
            $target = $e ? ($isAr ? $e['empfullnamear'] : $e['empfullname']) : ('#' . $r['employee_id']);
        } elseif ($r['assignment_type'] === 'department' && $r['depid']) {
            $d = fetchRow("SELECT depname,depnamear FROM " . table('depstructure') . " WHERE id=?", [$r['depid']]);
            $target = $d ? ($isAr ? $d['depnamear'] : $d['depname']) : ('#' . $r['depid']);
        } elseif ($r['assignment_type'] === 'group' && $r['empgroup']) {
            $g = fetchRow("SELECT empgroupname,empgroupnamear FROM " . table('employeesgroup') . " WHERE id=?", [$r['empgroup']]);
            $target = $g ? ($isAr ? $g['empgroupnamear'] : $g['empgroupname']) : ('#' . $r['empgroup']);
        }
        $schedName = $isAr ? $r['name_ar'] : $r['name_en'];
        echo '<tr>
          <td>' . htmlspecialchars($r['assignment_type']) . '</td>
          <td>' . htmlspecialchars($target) . '</td>
          <td>' . htmlspecialchars($schedName) . '</td>
          <td>' . htmlspecialchars($r['effective_from']) . '</td>
          <td>' . htmlspecialchars($r['effective_to'] ?? '') . '</td>
          <td>
            <a href="?module=attendance&a=assignment_edit&id=' . $r['id'] . '" class="btn btn-sm btn-outline-secondary">' . ($isAr?'تعديل':'Edit') . '</a>
            <a href="?module=attendance&a=assignment_del&id=' . $r['id'] . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\''.($isAr?'حذف؟':'Delete?').'\')">' . ($isAr?'حذف':'Del') . '</a>
          </td>
        </tr>';
    }
    echo '</tbody></table></div>';
    if (empty($rows)) echo '<div class="alert alert-info">' . ($isAr ? 'لا توجد تعيينات.' : 'No assignments.') . '</div>';
}

elseif (in_array($action, ['assignment_add','assignment_edit'], true)) {
    require_permission($module, $action === 'assignment_add' ? 'add' : 'edit');
    $row = null;
    if ($action === 'assignment_edit' && $id) {
        $row = fetchRow("SELECT * FROM " . table('schedule_assignments') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);
        if (!$row) { set_flash('error', $isAr?'غير موجود':'Not found'); header('Location: ?module=attendance&a=assignments'); exit; }
    }
    $schedules = fetchAll("SELECT id,name_ar,name_en FROM " . table('work_schedules') . " WHERE omitted=0 AND is_active=1 ORDER BY name_en");
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2>' . ($isAr ? ($action==='assignment_add'?'إضافة تعيين':'تعديل تعيين') : ($action==='assignment_add'?'Add Assignment':'Edit Assignment')) . '</h2>
            <a href="?module=attendance&a=assignments" class="btn btn-secondary btn-sm">' . ($isAr?'رجوع':'Back') . '</a>
          </div>';
    echo '<form method="post" action="?module=attendance&a=' . $action . ($action==='assignment_edit'?'&id='.$id:'') . '">';
    echo '<div class="mb-3"><label class="form-label">' . ($isAr?'نوع التعيين':'Assignment Type') . '</label>
            <select name="assignment_type" class="form-select" id="atype">
              <option value="employee"' . (($row['assignment_type'] ?? '')==='employee'?' selected':'') . '>' . ($isAr?'موظف':'Employee') . '</option>
              <option value="department"' . (($row['assignment_type'] ?? '')==='department'?' selected':'') . '>' . ($isAr?'قسم':'Department') . '</option>
              <option value="group"' . (($row['assignment_type'] ?? '')==='group'?' selected':'') . '>' . ($isAr?'مجموعة':'Group') . '</option>
            </select></div>';
    echo '<div class="mb-3" id="empWrap"><label class="form-label">' . ($isAr?'الموظف':'Employee') . '</label><select name="employee_id" class="form-select"><option value="">-- ' . ($isAr?'اختر':'Select') . ' --</option>';
    foreach ($employees as $e) echo '<option value="' . $e['id'] . '"' . (($row['employee_id'] ?? '')==$e['id']?' selected':'') . '>' . htmlspecialchars($isAr?$e['empfullnamear']:$e['empfullname']) . '</option>';
    echo '</select></div>';
    echo '<div class="mb-3" id="depWrap"><label class="form-label">' . ($isAr?'القسم':'Department') . '</label><select name="depid" class="form-select"><option value="">-- ' . ($isAr?'اختر':'Select') . ' --</option>';
    foreach ($departments as $d) echo '<option value="' . $d['id'] . '"' . (($row['depid'] ?? '')==$d['id']?' selected':'') . '>' . htmlspecialchars($isAr?$d['depnamear']:$d['depname']) . '</option>';
    echo '</select></div>';
    echo '<div class="mb-3" id="grpWrap"><label class="form-label">' . ($isAr?'المجموعة':'Group') . '</label><select name="empgroup" class="form-select"><option value="">-- ' . ($isAr?'اختر':'Select') . ' --</option>';
    foreach ($groups as $g) echo '<option value="' . $g['id'] . '"' . (($row['empgroup'] ?? '')==$g['id']?' selected':'') . '>' . htmlspecialchars($isAr?$g['empgroupnamear']:$g['empgroupname']) . '</option>';
    echo '</select></div>';
    echo '<div class="mb-3"><label class="form-label">' . ($isAr?'الدوام':'Schedule') . '</label><select name="schedule_id" class="form-select" required><option value="">-- ' . ($isAr?'اختر':'Select') . ' --</option>';
    foreach ($schedules as $s) echo '<option value="' . $s['id'] . '"' . (($row['schedule_id'] ?? '')==$s['id']?' selected':'') . '>' . htmlspecialchars($isAr?$s['name_ar']:$s['name_en']) . '</option>';
    echo '</select></div>';
    echo '<div class="row"><div class="col-md-4 mb-3"><label class="form-label">' . ($isAr?'ساري من':'Effective From') . '</label><input type="date" name="effective_from" class="form-control" value="' . htmlspecialchars($row['effective_from'] ?? date('Y-01-01')) . '" required></div>
          <div class="col-md-4 mb-3"><label class="form-label">' . ($isAr?'ساري إلى (اختياري)':'Effective To (optional)') . '</label><input type="date" name="effective_to" class="form-control" value="' . htmlspecialchars($row['effective_to'] ?? '') . '"></div>
          <div class="col-md-4 mb-3"><label class="form-label">' . ($isAr?'الأولوية':'Priority') . '</label><input type="number" name="priority" class="form-control" value="' . htmlspecialchars($row['priority'] ?? 0) . '"></div></div>
          <small class="form-text text-muted">' . ($isAr?'حدد تاريخاً سابقاً لتغطية البصمات التاريخية.':'Set an earlier date to cover historical punches.') . '</small>';
    echo '<button class="btn btn-primary">' . ($isAr?'حفظ':'Save') . '</button></form>';
}

// ---------- PUNCHES ----------
elseif ($action === 'punches') {
    require_permission($module, 'view');
    $rows = fetchAll("SELECT bp.*, e.empfullname, e.empfullnamear FROM " . table('biometric_punches') . " bp JOIN " . table('employeesmain') . " e ON e.id=bp.employee_id WHERE bp.omitted=0 ORDER BY bp.punch_datetime DESC LIMIT 200");
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2>' . ($isAr ? 'البصمات (Hikvision)' : 'Biometric Punches (Hikvision)') . '</h2>
          </div>';
    // manual add
    echo '<div class="card mb-3"><div class="card-header">' . ($isAr?'إضافة بصمة يدوياً':'Manual Punch') . '</div><div class="card-body">
            <form method="post" action="?module=attendance&a=punch_add" class="row g-2">
              <div class="col-md-3"><label class="form-label">' . ($isAr?'الموظف':'Employee') . '</label><select name="employee_id" class="form-select form-select-sm" required><option value="">-- ' . ($isAr?'اختر':'Select') . ' --</option>';
    foreach ($employees as $e) echo '<option value="' . $e['id'] . '">' . htmlspecialchars($isAr?$e['empfullnamear']:$e['empfullname']) . '</option>';
    echo '</select></div>
              <div class="col-md-2"><label class="form-label">' . ($isAr?'الجهاز':'Device') . '</label><input type="text" name="device_id" class="form-control form-control-sm"></div>
              <div class="col-md-3"><label class="form-label">' . ($isAr?'التاريخ والوقت':'Date & Time') . '</label><input type="datetime-local" name="punch_datetime" class="form-control form-control-sm" required></div>
              <div class="col-md-2"><label class="form-label">' . ($isAr?'معرّف المصدر':'Source ID') . '</label><input type="text" name="source_record_id" class="form-control form-control-sm"></div>
              <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm">' . ($isAr?'إضافة':'Add') . '</button></div>
            </form>
          </div></div>';
    // CSV import
    echo '<div class="card mb-3"><div class="card-header">' . ($isAr?'استيراد CSV':'CSV Import') . '</div><div class="card-body">
            <form method="post" action="?module=attendance&a=punch_import" enctype="multipart/form-data" class="row g-2">
              <div class="col-md-6"><input type="file" name="csv_file" accept=".csv" class="form-control form-control-sm" required></div>
              <div class="col-md-6 d-flex align-items-end"><button class="btn btn-success btn-sm">' . ($isAr?'استيراد':'Import') . '</button></div>
            </form>
            <small class="text-muted">' . ($isAr?'الأعمدة: employee_id, device_id, punch_datetime (Y-m-d H:i:s), source_record_id, source_info':'Columns: employee_id, device_id, punch_datetime (Y-m-d H:i:s), source_record_id, source_info') . '</small>
          </div></div>';
    echo '<div class="table-responsive"><table class="table table-sm table-bordered">
            <thead class="table-light"><tr>
              <th>' . ($isAr?'الموظف':'Employee') . '</th>
              <th>' . ($isAr?'الجهاز':'Device') . '</th>
              <th>' . ($isAr?'الوقت':'Time') . '</th>
              <th>' . ($isAr?'المصدر':'Source') . '</th>
              <th>' . ($isAr?'معرّف المصدر':'Source ID') . '</th>
              <th></th>
            </tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>
          <td>' . htmlspecialchars($isAr ? $r['empfullnamear'] : $r['empfullname']) . '</td>
          <td>' . htmlspecialchars($r['device_id'] ?? '') . '</td>
          <td>' . htmlspecialchars($r['punch_datetime']) . '</td>
          <td>' . htmlspecialchars($r['source_info'] ?? '') . '</td>
          <td>' . htmlspecialchars($r['source_record_id'] ?? '') . '</td>
          <td><a href="?module=attendance&a=punch_del&id=' . $r['id'] . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\''.($isAr?'حذف؟':'Delete?').'\')">' . ($isAr?'حذف':'Del') . '</a></td>
        </tr>';
    }
    echo '</tbody></table></div>';
    if (empty($rows)) echo '<div class="alert alert-info">' . ($isAr ? 'لا توجد بصمات.' : 'No punches.') . '</div>';
}

// ---------- REPORTS ----------
elseif ($action === 'reports') {
    require_permission($module, 'view');
    $rep = trim($_GET['rep'] ?? 'daily');
    $from = trim($_GET['from'] ?? date('Y-m-01'));
    $to   = trim($_GET['to']   ?? date('Y-m-d'));
    $fmt  = trim($_GET['fmt'] ?? '');
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2>' . ($isAr ? 'التقارير' : 'Reports') . '</h2>
            <a href="?module=attendance" class="btn btn-secondary btn-sm">' . ($isAr?'رجوع':'Back') . '</a>
          </div>';
    echo '<form method="get" class="row g-2 mb-3">
            <input type="hidden" name="module" value="attendance"><input type="hidden" name="a" value="reports">
            <div class="col-md-2"><label class="form-label">' . ($isAr?'التقرير':'Report') . '</label><select name="rep" class="form-select form-select-sm">
              <option value="daily"' . ($rep==='daily'?' selected':'') . '>' . ($isAr?'يومي':'Daily') . '</option>
              <option value="monthly"' . ($rep==='monthly'?' selected':'') . '>' . ($isAr?'شهري':'Monthly') . '</option>
              <option value="absence"' . ($rep==='absence'?' selected':'') . '>' . ($isAr?'الغياب':'Absence') . '</option>
              <option value="late"' . ($rep==='late'?' selected':'') . '>' . ($isAr?'التأخر':'Late') . '</option>
              <option value="early"' . ($rep==='early'?' selected':'') . '>' . ($isAr?'مغادرة مبكرة':'Early Departure') . '</option>
              <option value="overtime"' . ($rep==='overtime'?' selected':'') . '>' . ($isAr?'الإضافي':'Overtime') . '</option>
              <option value="missing"' . ($rep==='missing'?' selected':'') . '>' . ($isAr?'بصمات ناقصة':'Missing Punches') . '</option>
              <option value="assumed"' . ($rep==='assumed'?' selected':'') . '>' . ($isAr?'خروج مفترض':'Assumed Checkout') . '</option>
            </select></div>
            <div class="col-md-2"><label class="form-label">' . ($isAr?'من':'From') . '</label><input type="date" name="from" value="' . htmlspecialchars($from) . '" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label">' . ($isAr?'إلى':'To') . '</label><input type="date" name="to" value="' . htmlspecialchars($to) . '" class="form-control form-control-sm"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm">' . ($isAr?'عرض':'Show') . '</button></div>
          </form>';
    // export buttons
    echo '<div class="mb-3 d-flex flex-wrap gap-1">
            <span class="me-2 align-self-center small text-muted">' . ($isAr?'تصدير:':'Export:') . '</span>
            <a class="btn btn-sm btn-outline-danger" href="?module=attendance&a=reports&rep=' . urlencode($rep) . '&from=' . urlencode($from) . '&to=' . urlencode($to) . '&fmt=pdf"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
            <a class="btn btn-sm btn-outline-success" href="?module=attendance&a=reports&rep=' . urlencode($rep) . '&from=' . urlencode($from) . '&to=' . urlencode($to) . '&fmt=xlsx"><i class="bi bi-file-earmark-excel me-1"></i>XLSX</a>
            <a class="btn btn-sm btn-outline-primary" href="?module=attendance&a=reports&rep=' . urlencode($rep) . '&from=' . urlencode($from) . '&to=' . urlencode($to) . '&fmt=docx"><i class="bi bi-file-earmark-word me-1"></i>Word</a>
            <a class="btn btn-sm btn-outline-secondary" href="?module=attendance&a=reports&rep=' . urlencode($rep) . '&from=' . urlencode($from) . '&to=' . urlencode($to) . '&fmt=csv"><i class="bi bi-file-earmark-text me-1"></i>CSV</a>
          </div>';

    $where = "da.omitted=0 AND da.attendance_date BETWEEN ? AND ?";
    $params = [$from, $to];
    $rows = fetchAll("SELECT da.*, e.empfullname, e.empfullnamear FROM " . table('daily_attendance') . " da JOIN " . table('employeesmain') . " e ON e.id=da.employee_id WHERE $where ORDER BY da.attendance_date, e.empfullname", $params);

    // filter by report type
    $filtered = [];
    foreach ($rows as $r) {
        $ok = true;
        if ($rep === 'absence' && $r['status_code'] !== 'ABSENT') $ok = false;
        if ($rep === 'late' && $r['late_minutes'] <= 0) $ok = false;
        if ($rep === 'early' && $r['early_departure_minutes'] <= 0) $ok = false;
        if ($rep === 'overtime' && $r['total_overtime_minutes'] <= 0) $ok = false;
        if ($rep === 'missing' && !empty($r['first_punch']) && !empty($r['last_punch']) && $r['checkout_source'] !== 'ASSUMED') $ok = false;
        if ($rep === 'assumed' && $r['checkout_source'] !== 'ASSUMED') $ok = false;
        if ($rep === 'monthly') $ok = true;
        if ($ok) $filtered[] = $r;
    }

    echo '<div class="table-responsive"><table class="table table-sm table-bordered">
            <thead class="table-light"><tr>
              <th>' . ($isAr?'التاريخ':'Date') . '</th>
              <th>' . ($isAr?'الموظف':'Employee') . '</th>
              <th>' . ($isAr?'الحالة':'Status') . '</th>
              <th>' . ($isAr?'تأخر':'Late') . '</th>
              <th>' . ($isAr?'مغادرة مبكرة':'Early Dep') . '</th>
              <th>' . ($isAr?'إضافي':'OT') . '</th>
              <th>' . ($isAr?'الخروج':'Checkout') . '</th>
            </tr></thead><tbody>';
    foreach ($filtered as $r) {
        echo '<tr>
          <td>' . htmlspecialchars($r['attendance_date']) . '</td>
          <td>' . htmlspecialchars($isAr ? $r['empfullnamear'] : $r['empfullname']) . '</td>
          <td>' . htmlspecialchars(attendance_status_label($r['status_code'])) . '</td>
          <td>' . (int)$r['late_minutes'] . '</td>
          <td>' . (int)$r['early_departure_minutes'] . '</td>
          <td>' . (int)$r['total_overtime_minutes'] . '</td>
          <td>' . htmlspecialchars($r['checkout_source'] ?? '') . '</td>
        </tr>';
    }
    echo '</tbody></table></div>';
    // Export handling
    if ($fmt !== '' && !empty($filtered)) {
        $headers = $isAr
            ? ['التاريخ','الموظف','الحالة','تأخر (د)','مغادرة (د)','إضافي (د)','الخروج']
            : ['Date','Employee','Status','Late (m)','Early Dep (m)','OT (m)','Checkout'];
        $dataRows = [];
        foreach ($filtered as $r) {
            $dataRows[] = [
                $r['attendance_date'],
                $isAr ? ($r['empfullnamear'] ?? '') : ($r['empfullname'] ?? ''),
                attendance_status_label($r['status_code']),
                (int)$r['late_minutes'],
                (int)$r['early_departure_minutes'],
                (int)$r['total_overtime_minutes'],
                $r['checkout_source'] ?? '',
            ];
        }
        $title = $isAr ? 'تقرير الحضور والانصراف' : 'Attendance Report';
        do_export($fmt, 'attendance_' . $rep . '_report', $title, $headers, $dataRows);
        exit;
    }

    if (empty($filtered)) echo '<div class="alert alert-info">' . ($isAr ? 'لا توجد بيانات.' : 'No data.') . '</div>';

    // monthly summary:
    if ($rep === 'monthly') {
        echo '<h5 class="mt-4">' . ($isAr?'ملخص شهري':'Monthly Summary') . '</h5>';
        $summary = [];
        foreach ($filtered as $r) {
            $key = substr($r['attendance_date'],0,7) . '|' . $r['employee_id'];
            if (!isset($summary[$key])) {
                $summary[$key] = ['month'=>substr($r['attendance_date'],0,7),'emp'=>$isAr?$r['empfullnamear']:$r['empfullname'],'present'=>0,'absent'=>0,'late'=>0,'early'=>0,'ot'=>0];
            }
            $s = $summary[$key];
            if ($r['status_code'] === 'PRESENT' || $r['status_code'] === 'LATE' || $r['status_code'] === 'EARLY_DEPARTURE') $s['present']++;
            if ($r['status_code'] === 'ABSENT') $s['absent']++;
            $s['late'] += (int)$r['late_minutes'];
            $s['early'] += (int)$r['early_departure_minutes'];
            $s['ot'] += (int)$r['total_overtime_minutes'];
            $summary[$key] = $s;
        }
        echo '<div class="table-responsive"><table class="table table-sm table-bordered">
            <thead class="table-light"><tr>
              <th>' . ($isAr?'الشهر':'Month') . '</th>
              <th>' . ($isAr?'الموظف':'Employee') . '</th>
              <th>' . ($isAr?'حاضر':'Present') . '</th>
              <th>' . ($isAr?'غائب':'Absent') . '</th>
              <th>' . ($isAr?'تأخر (د)':'Late (m)') . '</th>
              <th>' . ($isAr?'مغادرة (د)':'Early (m)') . '</th>
              <th>' . ($isAr?'إضافي (د)':'OT (m)') . '</th>
            </tr></thead><tbody>';
        foreach ($summary as $s) {
            echo '<tr>
              <td>' . htmlspecialchars($s['month']) . '</td>
              <td>' . htmlspecialchars($s['emp']) . '</td>
              <td>' . $s['present'] . '</td>
              <td>' . $s['absent'] . '</td>
              <td>' . $s['late'] . '</td>
              <td>' . $s['early'] . '</td>
              <td>' . $s['ot'] . '</td>
            </tr>';
        }
        echo '</tbody></table></div>';
    }
}

// (footer provided by main layout)
