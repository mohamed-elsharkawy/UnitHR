<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }


require_login();

require_once __DIR__ . '/../../includes/attendance.php';

$module = 'attpermissions';

$isAr = lang() === 'ar';

$action = $_GET['a'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---------- Auto-seed permission types ----------

$tc = fetchRow("SELECT COUNT(*) c FROM " . table('att_permission_types') . " WHERE omitted=0");

if (!$tc || (int)$tc['c'] === 0) {

    $types = [

        ['EARLY_DEPARTURE','إذن انصراف مبكر','Permission for Early Departure','إذن للانصراف قبل نهاية الدوام','Permission to leave before scheduled end','TIME_BASED'],

        ['LATE_ARRIVAL','إذن حضور متأخر','Permission for Late Arrival','إذن للحضور بعد بداية الدوام','Permission to arrive after scheduled start','TIME_BASED'],

        ['PERSONAL','إذن شخصي','Personal Permission','إذن شخصي ليوم كامل','Full-day personal permission','FULL_DAY'],

        ['OFFICIAL_BUSINESS','إذن مصلحي','Official Business Permission','إذن لغرض مصلحي','Full-day official business permission','FULL_DAY'],

        ['WORK_ASSIGNMENT','مأمورية','Official Work Assignment','مأمورية رسمية','Official work assignment','FULL_DAY'],

    ];

    $u = current_user(); $an = $u ? ($u['username_ar'] ?? $u['username']) : 'System';

    foreach ($types as $t) {

        $rguid = bin2hex(random_bytes(16));

        q("INSERT INTO " . table('att_permission_types') . " (rguid,code,name_ar,name_en,description_ar,description_en,category,is_active,dtadded,addedbyid,addedby,addedbyname,addedbynamear,omitted) VALUES (?,?,?,?,?,?,?,?,1,NOW(),?,?,?,?,0)",

            [$rguid,$t[0],$t[1],$t[2],$t[3],$t[4],$t[5],$u ? $u['id'] : 1,$u ? $u['username'] : 'System',$an,$an]);

    }

}

// ---------- Helpers ----------

function attperm_is_time_based($type) {

    return in_array($type['category'] ?? '', ['TIME_BASED'], true);

}

function attperm_type($id) {

    return fetchRow("SELECT * FROM " . table('att_permission_types') . " WHERE id=? AND omitted=0 LIMIT 1", [(int)$id]);

}

function attperm_type_by_code($code) {

    return fetchRow("SELECT * FROM " . table('att_permission_types') . " WHERE code=? AND omitted=0 LIMIT 1", [$code]);

}

function attperm_resolve_approvers($empid) {

    $emp = fetchRow("SELECT id, empsuperiorid, depid FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [(int)$empid]);

    $chain = [];

    $cur = $emp;

    $seen = [];

    for ($i = 0; $i < 3 && $cur; $i++) {

        if (empty($cur['empsuperiorid'])) break;

        $sup = fetchRow("SELECT id, empsuperiorid, nid FROM " . table('employeesmain') . " WHERE id=? AND omitted=0 LIMIT 1", [(int)$cur['empsuperiorid']]);

        if (!$sup || isset($seen[$sup['id']])) break;

        $seen[$sup['id']] = true;

        $uid = notifications_user_id_for_employee((int)$sup['id']);

        if ($uid > 0) $chain[] = $uid;

        $cur = $sup;

    }

    $admins = fetchAll("SELECT id FROM " . table('users') . " WHERE (is_admin=1 OR is_superadmin=1) AND omitted=0");

    foreach ($admins as $a) {

        if (!in_array((int)$a['id'], $chain, true)) $chain[] = (int)$a['id'];

    }

    return array_values(array_unique($chain));

}

function attperm_overlap($empid, $isTime, $sd, $ed, $st, $et, $excludeId = 0) {

    $sql = "SELECT id, start_date, end_date, start_time, end_time FROM " . table('att_permission_requests') . "

            WHERE employee_id=? AND omitted=0 AND status IN ('PENDING','APPROVED') AND id <> ?";

    $rows = fetchAll($sql, [(int)$empid, (int)$excludeId]);

    foreach ($rows as $r) {

        $rTime = !empty($r['start_time']);

        if ($isTime && $rTime) {

            if ($r['start_date'] !== $sd) continue;

            if ($st < $r['end_time'] && $et > $r['start_time']) return true;

        } elseif (!$isTime && !$rTime) {

            if ($sd <= $r['end_date'] && $ed >= $r['start_date']) return true;

        } else {

            if ($isTime) {

                if ($sd >= $r['start_date'] && $sd <= $r['end_date']) return true;

            } else {

                if ($r['start_date'] >= $sd && $r['start_date'] <= $ed) return true;

            }

        }

    }

    if (!$isTime) {

        $leaves = fetchAll("SELECT startfrom,endon FROM " . table('empleaves') . " WHERE empid=? AND omitted=0 AND isapproved=1 AND rejectedat IS NULL AND startfrom <= ? AND endon >= ?", [(int)$empid, $ed, $sd]);

        if (!empty($leaves)) return true;

    }

    return false;

}

function attperm_schedule($empid, $date) {

    return attendance_effective_schedule((int)$empid, $date);

}

function attperm_count_working_days($empid, $sd, $ed) {

    $s = new DateTimeImmutable($sd); $e = new DateTimeImmutable($ed);

    $cur = $s; $n = 0;

    while ($cur <= $e) {

        $sch = attperm_schedule($empid, $cur->format('Y-m-d'));

        if ($sch) {

            $dow = (int)$cur->format('w');

            if (isset($sch['day_map'][$dow]) && (int)$sch['day_map'][$dow] === 1) $n++;

        }

        $cur = $cur->modify('+1 day');

    }

    return $n;

}

// ---------- SUBMIT REQUEST ----------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit') {

    require_login();

    $empid = (int)($_POST['empid'] ?? 0);

    if (!is_app_staff()) {

        $own = fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE nid=? AND omitted=0 LIMIT 1", [current_user()['username'] ?? '']);

        if (!$own || (int)$own['id'] !== $empid) { set_flash('error', $isAr?'غير مصرح':'Not allowed'); header('Location: ?module=attpermissions'); exit; }

    }

    $typeId = (int)($_POST['type_id'] ?? 0);

    $type = attperm_type($typeId);

    if (!$type) { set_flash('error', $isAr?'نوع غير صالح':'Invalid type'); header('Location: ?module=attpermissions'); exit; }

    $isTime = attperm_is_time_based($type);

    $sd = trim($_POST['start_date'] ?? '');

    $ed = trim($_POST['end_date'] ?? $sd);

    $st = $isTime ? trim($_POST['start_time'] ?? '') : null;

    $et = $isTime ? trim($_POST['end_time'] ?? '') : null;

    $reason = trim($_POST['reason'] ?? '');

    $dest = trim($_POST['destination'] ?? '');

    $task = trim($_POST['task_description'] ?? '');

    $ref = trim($_POST['official_reference'] ?? '');

    $errors = [];

    if ($sd === '') $errors[] = $isAr ? 'تاريخ البدء مطلوب' : 'Start date required';

    if (!$isTime && $ed === '') $errors[] = $isAr ? 'تاريخ الانتهاء مطلوب' : 'End date required';

    if ($isTime) {

        if ($st === '' || $et === '') $errors[] = $isAr ? 'الأوقات مطلوبة' : 'Times required';

        if ($st !== '' && $et !== '' && $et <= $st) $errors[] = $isAr ? 'وقت الانتهاء يجب أن يكون بعد البدء' : 'End time must be after start';

        $ed = $sd;

        if ($st !== '') $st = date('H:i:s', strtotime($st));

        if ($et !== '') $et = date('H:i:s', strtotime($et));

        $sch = attperm_schedule($empid, $sd);

        if (!$sch) $errors[] = $isAr ? 'لا يوجد دوام معين' : 'No schedule assigned';

        else {

            $dow = (int)(new DateTimeImmutable($sd))->format('w');

            if (!isset($sch['day_map'][$dow]) || (int)$sch['day_map'][$dow] !== 1) $errors[] = $isAr ? 'يوم غير دوام' : 'Not a working day';

            else {

                if ($st < $sch['start_time'] || $et > $sch['end_time']) $errors[] = $isAr ? 'الفاصل خارج ساعات الدوام' : 'Interval outside working hours';

            }

        }

    } else {

        if ($ed < $sd) $errors[] = $isAr ? 'تاريخ الانتهاء قبل البدء' : 'End before start';

    }

    if (!empty($errors) && attperm_overlap($empid, $isTime, $sd, $ed, $st, $et, 0)) {

        $errors[] = $isAr ? 'يوجد تداخل مع طلب آخر' : 'Overlaps existing request';

    }

    if (!empty($errors)) {

        set_flash('error', implode(' | ', $errors));

        header('Location: ?module=attpermissions&a=request_add&empid=' . $empid); exit;

    }

    $policy = fetchRow("SELECT * FROM " . table('att_permission_policies') . " WHERE type_id=? AND is_active=1 AND omitted=0 ORDER BY id LIMIT 1", [$typeId]);

    $rguid = bin2hex(random_bytes(16));

    $now = date('Y-m-d H:i:s');

    $u = current_user(); $an = $u ? ($u['username_ar'] ?? $u['username']) : 'System';

    q("INSERT INTO " . table('att_permission_requests') . " (rguid,employee_id,type_id,policy_id,start_date,end_date,start_time,end_time,reason,destination,task_description,official_reference,status,submitted_at,dtadded,addedbyid,addedby,addedbyname,addedbynamear,lastedit,editedbyid,editedby,editedbyname,editedbynamear,omitted) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)",

        [$rguid,$empid,$typeId,$policy ? $policy['id'] : null,$sd,$ed,$st,$et,$reason,$dest,$task,$ref,'PENDING',$now,$now,$u ? $u['id'] : 1,$u ? $u['username'] : 'System',$an,$an,$now,$u ? $u['id'] : 1,$u ? $u['username'] : 'System',$an,$an]);

    $rid = (int)lastInsertId();

    $approvers = attperm_resolve_approvers($empid);

    $level = 1;

    foreach ($approvers as $uid) {

        q("INSERT INTO " . table('att_permission_approvals') . " (request_id,approver_user_id,approval_level,decision,dtadded,addedbyid,addedby,addedbyname,addedbynamear,omitted) VALUES (?,?,?,?,NOW(),?,?,?,?,0)",

            [$rid,$uid,$level,'PENDING',$u ? $u['id'] : 1,$u ? $u['username'] : 'System',$an,$an]);

        $level++;

    }

    $emp = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? LIMIT 1", [$empid]);

    $startStr = $isTime ? "$sd $st" : $sd;

    $endStr = $isTime ? "$ed $et" : $ed;

    notify_permission_request((int)$empid, $isAr ? $type['name_ar'] : $type['name_en'], $startStr, $endStr, $rid);

    set_flash('success', $isAr ? 'تم تقديم الطلب.' : 'Request submitted.');

    header('Location: ?module=attpermissions&a=view&id=' . $rid); exit;

}

// ---------- APPROVE ----------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'approve') {

    require_login();

    $rid = (int)($_POST['request_id'] ?? 0);

    $comments = trim($_POST['comments'] ?? '');

    $req = fetchRow("SELECT * FROM " . table('att_permission_requests') . " WHERE id=? AND omitted=0 LIMIT 1", [$rid]);

    if (!$req || $req['status'] !== 'PENDING') { set_flash('error', $isAr?'طلب غير صالح':'Invalid request'); header('Location: ?module=attpermissions'); exit; }

    $uid = current_user_id();

    $approval = fetchRow("SELECT * FROM " . table('att_permission_approvals') . " WHERE request_id=? AND approver_user_id=? AND decision='PENDING' ORDER BY approval_level ASC LIMIT 1", [$rid, $uid]);

    if (!$approval) { set_flash('error', $isAr?'غير مصرح':'Not authorized'); header('Location: ?module=attpermissions'); exit; }

    $minLevel = fetchRow("SELECT MIN(approval_level) lvl FROM " . table('att_permission_approvals') . " WHERE request_id=? AND decision='PENDING'", [$rid]);

    if ((int)$approval['approval_level'] !== (int)$minLevel['lvl']) { set_flash('error', $isAr?'ليس دورك':'Not your turn'); header('Location: ?module=attpermissions'); exit; }

    $emp = fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE nid=? LIMIT 1", [current_user()['username'] ?? '']);

    if ($emp && (int)$emp['id'] === (int)$req['employee_id']) { set_flash('error', $isAr?'لا يمكن اعتماد طلبك':'Cannot self-approve'); header('Location: ?module=attpermissions'); exit; }

    $now = date('Y-m-d H:i:s');

    q("UPDATE " . table('att_permission_approvals') . " SET decision='APPROVED', comments=?, decided_at=? WHERE id=?", [$comments, $now, $approval['id']]);

    $remaining = fetchRow("SELECT COUNT(*) c FROM " . table('att_permission_approvals') . " WHERE request_id=? AND decision='PENDING'", [$rid]);

    if ((int)$remaining['c'] === 0) {

        q("UPDATE " . table('att_permission_requests') . " SET status='APPROVED', approved_at=? WHERE id=?", [$now, $rid]);

        attperm_apply_attendance($rid);

        $emp2 = fetchRow("SELECT * FROM " . table('employeesmain') . " WHERE id=? LIMIT 1", [$req['employee_id']]);

        $type = attperm_type((int)$req['type_id']);

        notifications_notify_employee((int)$req['employee_id'], [

            'title' => 'Permission Request Approved',

            'titlear' => 'تمت الموافقة على طلب الإذن',

            'message' => 'Your ' . ($isAr ? $type['name_ar'] : $type['name_en']) . ' request has been approved.',

            'messagear' => 'تمت الموافقة على طلب الإذن الخاص بك من نوع ' . $type['name_ar'] . '.',

            'link' => '?module=attpermissions&a=view&id=' . $rid,

            'module' => 'attpermissions',

            'refid' => $rid,

        ]);

    }

    set_flash('success', $isAr ? 'تم الاعتماد.' : 'Approved.');

    header('Location: ?module=attpermissions&a=view&id=' . $rid); exit;

}

// ---------- REJECT ----------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reject') {

    require_login();

    $rid = (int)($_POST['request_id'] ?? 0);

    $comments = trim($_POST['comments'] ?? '');

    $req = fetchRow("SELECT * FROM " . table('att_permission_requests') . " WHERE id=? AND omitted=0 LIMIT 1", [$rid]);

    if (!$req || $req['status'] !== 'PENDING') { set_flash('error', $isAr?'طلب غير صالح':'Invalid request'); header('Location: ?module=attpermissions'); exit; }

    $uid = current_user_id();

    $approval = fetchRow("SELECT * FROM " . table('att_permission_approvals') . " WHERE request_id=? AND approver_user_id=? AND decision='PENDING' ORDER BY approval_level ASC LIMIT 1", [$rid, $uid]);

    if (!$approval) { set_flash('error', $isAr?'غير مصرح':'Not authorized'); header('Location: ?module=attpermissions'); exit; }

    $minLevel = fetchRow("SELECT MIN(approval_level) lvl FROM " . table('att_permission_approvals') . " WHERE request_id=? AND decision='PENDING'", [$rid]);

    if ((int)$approval['approval_level'] !== (int)$minLevel['lvl']) { set_flash('error', $isAr?'ليس دورك':'Not your turn'); header('Location: ?module=attpermissions'); exit; }

    $emp = fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE nid=? LIMIT 1", [current_user()['username'] ?? '']);

    if ($emp && (int)$emp['id'] === (int)$req['employee_id']) { set_flash('error', $isAr?'لا يمكن رفض طلبك':'Cannot self-reject'); header('Location: ?module=attpermissions'); exit; }

    $now = date('Y-m-d H:i:s');

    q("UPDATE " . table('att_permission_approvals') . " SET decision='REJECTED', comments=?, decided_at=? WHERE id=?", [$comments, $now, $approval['id']]);

    q("UPDATE " . table('att_permission_requests') . " SET status='REJECTED', rejected_at=? WHERE id=?", [$now, $rid]);

    $type = attperm_type((int)$req['type_id']);

    notifications_notify_employee((int)$req['employee_id'], [

        'title' => 'Permission Request Rejected',

        'titlear' => 'تم رفض طلب الإذن',

        'message' => 'Your ' . ($isAr ? $type['name_ar'] : $type['name_en']) . ' request has been rejected.',

        'messagear' => 'تم رفض طلب الإذن الخاص بك من نوع ' . $type['name_ar'] . '.',

        'link' => '?module=attpermissions&a=view&id=' . $rid,

        'module' => 'attpermissions',

        'refid' => $rid,

    ]);

    set_flash('error', $isAr ? 'تم الرفض.' : 'Rejected.');

    header('Location: ?module=attpermissions&a=view&id=' . $rid); exit;

}

// ---------- CANCEL ----------

if ($action === 'cancel' && $id) {

    require_login();

    $req = fetchRow("SELECT * FROM " . table('att_permission_requests') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);

    if (!$req) { set_flash('error', $isAr?'غير موجود':'Not found'); header('Location: ?module=attpermissions'); exit; }

    $uid = current_user_id();

    $isOwner = false;

    $emp = fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE nid=? LIMIT 1", [current_user()['username'] ?? '']);

    if ($emp && (int)$emp['id'] === (int)$req['employee_id']) $isOwner = true;

    if (!$isOwner && !is_app_staff()) { set_flash('error', $isAr?'غير مصرح':'Not allowed'); header('Location: ?module=attpermissions'); exit; }

    if ($req['status'] !== 'PENDING') { set_flash('error', $isAr?'لا يمكن الإلغاء':'Cannot cancel'); header('Location: ?module=attpermissions'); exit; }

    $now = date('Y-m-d H:i:s');

    q("UPDATE " . table('att_permission_requests') . " SET status='CANCELLED', cancelled_at=? WHERE id=?", [$now, $id]);

    set_flash('success', $isAr ? 'تم الإلغاء.' : 'Cancelled.');

    header('Location: ?module=attpermissions'); exit;

}

// ---------- APPLY ATTENDANCE ----------

function attperm_apply_attendance($requestId) {

    $req = fetchRow("SELECT * FROM " . table('att_permission_requests') . " WHERE id=? LIMIT 1", [(int)$requestId]);

    if (!$req) return;

    $type = attperm_type((int)$req['type_id']);

    if (!$type) return;

    $isTime = attperm_is_time_based($type);

    $empid = (int)$req['employee_id'];

    if ($isTime) {

        $att = fetchRow("SELECT id FROM " . table('daily_attendance') . " WHERE employee_id=? AND attendance_date=? LIMIT 1", [$empid, $req['start_date']]);

        if ($att) {

            $permMinutes = (strtotime($req['end_time']) - strtotime($req['start_time'])) / 60;

            $rguid = bin2hex(random_bytes(16));

            q("INSERT INTO " . table('att_permission_attendance') . " (request_id,attendance_id,applied_minutes,application_status,applied_at) VALUES (?,?,?,'APPLIED',NOW())",

                [$requestId, $att['id'], (int)$permMinutes]);

        }

    } else {

        $s = new DateTimeImmutable($req['start_date']);

        $e = new DateTimeImmutable($req['end_date']);

        $cur = $s;

        $statusMap = ['PERSONAL'=>'PERSONAL_PERMISSION','OFFICIAL_BUSINESS'=>'OFFICIAL_BUSINESS','WORK_ASSIGNMENT'=>'WORK_ASSIGNMENT'];

        $statusCode = $statusMap[$type['code']] ?? 'PERSONAL_PERMISSION';

        while ($cur <= $e) {

            $d = $cur->format('Y-m-d');

            $sch = attendance_effective_schedule($empid, $d);

            if ($sch) {

                $dow = (int)$cur->format('w');

                if (isset($sch['day_map'][$dow]) && (int)$sch['day_map'][$dow] === 1) {

                    $existing = fetchRow("SELECT * FROM " . table('daily_attendance') . " WHERE employee_id=? AND attendance_date=? LIMIT 1", [$empid, $d]);

                    $now = date('Y-m-d H:i:s');

                    if ($existing) {

                        if ((int)$existing['manual_adjusted'] === 0) {

                            q("UPDATE " . table('daily_attendance') . " SET status_code=?, calculation_status='CALCULATED', calculated_at=? WHERE id=?", [$statusCode, $now, $existing['id']]);

                            $attId = $existing['id'];

                        } else { $attId = $existing['id']; }

                    } else {

                        $rguid = bin2hex(random_bytes(16));

                        q("INSERT INTO " . table('daily_attendance') . " (rguid,employee_id,attendance_date,schedule_id,scheduled_start,scheduled_end,status_code,calculation_status,calculated_at,dtadded,addedbyid,addedby,addedbyname,addedbynamear,omitted) VALUES (?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,0)",

                            [$rguid,$empid,$d,$sch['schedule_id'],$sch['start_time'],$sch['end_time'],$statusCode,'CALCULATED',$now,1,'System','System','System']);

                        $attId = lastInsertId();

                    }

                    $linkRguid = bin2hex(random_bytes(16));

                    q("INSERT INTO " . table('att_permission_attendance') . " (request_id,attendance_id,applied_minutes,application_status,applied_at) VALUES (?,?,0,'APPLIED',NOW())", [$requestId, $attId]);

                }

            }

            $cur = $cur->modify('+1 day');

        }

    }

}

// ---------- VIEW ----------

if ($action === 'view' && $id) {

    require_login();

    $req = fetchRow("SELECT r.*, t.code, t.name_ar AS type_ar, t.name_en AS type_en, t.category, e.empfullname, e.empfullnamear, e.nid FROM " . table('att_permission_requests') . " r JOIN " . table('att_permission_types') . " t ON t.id=r.type_id JOIN " . table('employeesmain') . " e ON e.id=r.employee_id WHERE r.id=? AND r.omitted=0 LIMIT 1", [$id]);

    if (!$req) { set_flash('error', $isAr?'غير موجود':'Not found'); header('Location: ?module=attpermissions'); exit; }

    if (!is_app_staff()) {

        $emp = fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE nid=? AND omitted=0 LIMIT 1", [current_user()['username'] ?? '']);

        $isOwner = $emp && (int)$emp['id'] === (int)$req['employee_id'];

        $isAppr = fetchRow("SELECT id FROM " . table('att_permission_approvals') . " WHERE request_id=? AND approver_user_id=? LIMIT 1", [$id, current_user_id()]);

        if (!$isOwner && !$isAppr) { set_flash('error', $isAr?'غير مصرح':'Not allowed'); header('Location: ?module=attpermissions'); exit; }

    }

    $approvals = fetchAll("SELECT a.*, u.username FROM " . table('att_permission_approvals') . " a LEFT JOIN " . table('users') . " u ON u.id=a.approver_user_id WHERE a.request_id=? AND a.omitted=0 ORDER BY a.approval_level", [$id]);

    $attLinks = fetchAll("SELECT a.*, da.attendance_date, da.status_code FROM " . table('att_permission_attendance') . " a JOIN " . table('daily_attendance') . " da ON da.id=a.attendance_id WHERE a.request_id=? ORDER BY da.attendance_date", [$id]);

    echo '<div class="d-flex justify-content-between align-items-center mb-3">

            <h2>'.($isAr ? 'طلب إذن' : 'Permission Request').' #'. $id .'</h2>

            <a href="?module=attpermissions" class="btn btn-secondary btn-sm">'.($isAr?'رجوع':'Back').'</a>

          </div>';

    echo '<div class="card mb-3"><div class="card-body">

        <p><strong>'.($isAr?'الموظف':'Employee').':</strong> '. htmlspecialchars($isAr ? $req['empfullnamear'] : $req['empfullname']) .'</p>

        <p><strong>'.($isAr?'النوع':'Type').':</strong> '. htmlspecialchars($isAr ? $req['type_ar'] : $req['type_en']) .'</p>

        <p><strong>'.($isAr?'من':'From').':</strong> '. htmlspecialchars($req['start_date']) .' '. htmlspecialchars($req['start_time'] ?? '') .'</p>

        <p><strong>'.($isAr?'إلى':'To').':</strong> '. htmlspecialchars($req['end_date']) .' '. htmlspecialchars($req['end_time'] ?? '') .'</p>

        <p><strong>'.($isAr?'السبب':'Reason').':</strong> '. htmlspecialchars($req['reason'] ?? '') .'</p>';

    if ($req['destination']): echo '<p><strong>'.($isAr?'الوجهة':'Destination').':</strong> '. htmlspecialchars($req['destination']) .'</p>'; endif;

    if ($req['task_description']): echo '<p><strong>'.($isAr?'وصف المهمة':'Task').':</strong> '. htmlspecialchars($req['task_description']) .'</p>'; endif;

    if ($req['official_reference']): echo '<p><strong>'.($isAr?'المرجع':'Reference').':</strong> '. htmlspecialchars($req['official_reference']) .'</p>'; endif;

    echo '<p><strong>'.($isAr?'الحالة':'Status').':</strong> '. htmlspecialchars($req['status']) .'</p>

    </div></div>';

    echo '<div class="card mb-3"><div class="card-header">'.($isAr?'سجل الاعتمادات':'Approval History').'</div><div class="card-body">

        <table class="table table-sm"><thead><tr><th>'.($isAr?'المستوى':'Level').'</th><th>'.($isAr?'المعتمد':'Approver').'</th><th>'.($isAr?'القرار':'Decision').'</th><th>'.($isAr?'التعليق':'Comments').'</th><th>'.($isAr?'التاريخ':'Date').'</th></tr></thead><tbody>';

    foreach ($approvals as $a):

    echo '<tr><td>'. (int)$a['approval_level'] .'</td><td>'. htmlspecialchars($a['username'] ?? '') .'</td><td>'. htmlspecialchars($a['decision']) .'</td><td>'. htmlspecialchars($a['comments'] ?? '') .'</td><td>'. htmlspecialchars($a['decided_at'] ?? '') .'</td></tr>';

    endforeach;

    echo '</tbody></table></div></div>';

    if (!empty($attLinks)) {

    echo '<div class="card mb-3"><div class="card-header">'.($isAr?'تأثير الإذن على الحضور':'Attendance Impact').'</div><div class="card-body">

        <table class="table table-sm"><thead><tr><th>'.($isAr?'التاريخ':'Date').'</th><th>'.($isAr?'الحالة':'Status').'</th><th>'.($isAr?'الدقائق المطبقة':'Applied Minutes').'</th></tr></thead><tbody>';

    foreach ($attLinks as $al):

    echo '<tr><td>'. htmlspecialchars($al['attendance_date']) .'</td><td>'. htmlspecialchars($al['status_code']) .'</td><td>'. (int)$al['applied_minutes'] .'</td></tr>';

    endforeach;

    echo '</tbody></table></div></div>';

    }

    $uid = current_user_id();

    $canAct = fetchRow("SELECT id FROM " . table('att_permission_approvals') . " WHERE request_id=? AND approver_user_id=? AND decision='PENDING' LIMIT 1", [$id, $uid]);

    $minLvl = fetchRow("SELECT MIN(approval_level) lvl FROM " . table('att_permission_approvals') . " WHERE request_id=? AND decision='PENDING'", [$id]);

    $isCurrent = $canAct && (int)$canAct['approval_level'] === (int)($minLvl['lvl'] ?? 0);

    if ($req['status'] === 'PENDING' && $isCurrent) {

    ?>

    <form method="post" action="?module=attpermissions&a=approve" class="mb-2">

        <input type="hidden" name="request_id" value="<?= $id ?>">

        <div class="mb-2"><textarea name="comments" class="form-control form-control-sm" placeholder="<?= $isAr?'تعليق':'Comments' ?>"></textarea></div>

        <button class="btn btn-success btn-sm"><?= $isAr?'اعتماد':'Approve' ?></button>

    </form>

    <form method="post" action="?module=attpermissions&a=reject">

        <input type="hidden" name="request_id" value="<?= $id ?>">

        <div class="mb-2"><textarea name="comments" class="form-control form-control-sm" placeholder="<?= $isAr?'سبب الرفض':'Rejection reason' ?>"></textarea></div>

        <button class="btn btn-danger btn-sm"><?= $isAr?'رفض':'Reject' ?></button>

    </form>

    <?php

    }

    if ($req['status'] === 'PENDING') {

        $emp = fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE nid=? LIMIT 1", [current_user()['username'] ?? '']);

        if ($emp && (int)$emp['id'] === (int)$req['employee_id']) { ?>

    <form method="post" action="?module=attpermissions&a=cancel&id=<?= $id ?>" style="display:inline;">

        <button class="btn btn-outline-secondary btn-sm" onclick="return confirm('<?= $isAr?'إلغاء؟':'Cancel?' ?>')"><?= $isAr?'إلغاء الطلب':'Cancel Request' ?></button>

    </form>

    <?php

        }

    }

    return;

}

// ---------- REQUEST ADD FORM ----------

if ($action === 'request_add') {

    require_login();

    $empid = (int)($_GET['empid'] ?? 0);

    if (!is_app_staff()) {

        $own = fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE nid=? AND omitted=0 LIMIT 1", [current_user()['username'] ?? '']);

        $empid = $own ? (int)$own['id'] : 0;

    }

    $types = fetchAll("SELECT * FROM " . table('att_permission_types') . " WHERE is_active=1 AND omitted=0 ORDER BY name_en");

    echo '<div class="d-flex justify-content-between align-items-center mb-3">

            <h2>'.($isAr ? 'طلب إذن جديد' : 'New Permission Request').'</h2>

            <a href="?module=attpermissions" class="btn btn-secondary btn-sm">'.($isAr?'رجوع':'Back').'</a>

          </div>';

    echo '<form method="post" action="?module=attpermissions&a=submit">

        <input type="hidden" name="empid" value="'. $empid .'">

        <div class="mb-3">

            <label class="form-label">'.($isAr?'نوع الإذن':'Permission Type').'</label>

            <select name="type_id" id="typeSelect" class="form-select" required onchange="toggleFields()">

                <option value="">-- '.($isAr?'اختر':'Select').' --</option>';

    foreach ($types as $t) {

        echo '<option value="'. $t['id'] .'" data-cat="'. htmlspecialchars($t['category']) .'">'. htmlspecialchars($isAr ? $t['name_ar'] : $t['name_en']) .'</option>';

    }

    echo '</select>

        </div>

        <div id="timeFields" style="display:none;">

            <div class="row">

                <div class="col-md-4 mb-3"><label class="form-label">'.($isAr?'التاريخ':'Date').'</label><input type="date" name="start_date" class="form-control"></div>

                <div class="col-md-4 mb-3"><label class="form-label">'.($isAr?'وقت البدء':'Start Time').'</label><input type="time" name="start_time" class="form-control"></div>

                <div class="col-md-4 mb-3"><label class="form-label">'.($isAr?'وقت الانتهاء':'End Time').'</label><input type="time" name="end_time" class="form-control"></div>

            </div>

        </div>

        <div id="fullFields" style="display:none;">

            <div class="row">

                <div class="col-md-6 mb-3"><label class="form-label">'.($isAr?'تاريخ البدء':'Start Date').'</label><input type="date" name="start_date" class="form-control"></div>

                <div class="col-md-6 mb-3"><label class="form-label">'.($isAr?'تاريخ الانتهاء':'End Date').'</label><input type="date" name="end_date" class="form-control"></div>

            </div>

            <div class="mb-3"><label class="form-label">'.($isAr?'الوجهة':'Destination').'</label><input type="text" name="destination" class="form-control"></div>

            <div class="mb-3"><label class="form-label">'.($isAr?'وصف المهمة':'Task Description').'</label><textarea name="task_description" class="form-control"></textarea></div>

            <div class="mb-3"><label class="form-label">'.($isAr?'المرجع الرسمي':'Official Reference').'</label><input type="text" name="official_reference" class="form-control"></div>

        </div>

        <div class="mb-3"><label class="form-label">'.($isAr?'السبب':'Reason').'</label><textarea name="reason" class="form-control"></textarea></div>

        <button class="btn btn-primary">'.($isAr?'تقديم':'Submit').'</button>

    </form>

    <script>

    function toggleFields() {

        var sel = document.getElementById(\'typeSelect\');

        var opt = sel.options[sel.selectedIndex];

        var cat = opt ? opt.getAttribute(\'data-cat\') : \'\';

        document.getElementById(\'timeFields\').style.display = (cat === \'TIME_BASED\') ? \'\' : \'none\';

        document.getElementById(\'fullFields\').style.display = (cat === \'FULL_DAY\') ? \'\' : \'none\';

    }

    </script>';

}

// ---------- PERMISSION TYPES LIST ----------

if ($action === 'types') {

    require_login();

    $rows = fetchAll("SELECT * FROM " . table('att_permission_types') . " WHERE omitted=0 ORDER BY name_en ASC");

    echo '<div class="d-flex justify-content-between align-items-center mb-3">

            <h2>'.($isAr ? 'أنواع الإذن' : 'Permission Types').'</h2>

            <a href="?module=attpermissions&a=types_add" class="btn btn-primary btn-sm">'.($isAr ? 'إضافة نوع' : 'Add Type').'</a>

          </div>';

    echo '<div class="table-responsive"><table class="table table-sm table-bordered">

            <thead class="table-light"><tr>

              <th>'.($isAr?'الكود':'Code').'</th>

              <th>'.($isAr?'الاسم (AR)':'Name (AR)').'</th>

              <th>'.($isAr?'الاسم (EN)':'Name (EN)').'</th>

              <th>'.($isAr?'الفئة':'Category').'</th>

              <th>'.($isAr?'فعال':'Active').'</th>

              <th></th>

            </tr></thead><tbody>';

    foreach ($rows as $r) {

        echo '<tr>

          <td>'. htmlspecialchars($r['code']) .'</td>

          <td>'. htmlspecialchars($r['name_ar']) .'</td>

          <td>'. htmlspecialchars($r['name_en']) .'</td>

          <td>'.($isAr ? ($r['category']==='TIME_BASED'?'مبني على الوقت':'يوم كامل') : ($r['category']==='TIME_BASED'?'Time-based':'Full-day')) .'</td>

          <td>'. ($r['is_active'] ? '✓' : '') .'</td>

          <td>

            <a href="?module=attpermissions&a=types_edit&id=' . $r['id'] . '" class="btn btn-sm btn-outline-secondary">' . ($isAr?'تعديل':'Edit') . '</a>

            <a href="?module=attpermissions&a=types_del&id=' . $r['id'] . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\''.($isAr?'حذف؟':'Delete?').'\')">' . ($isAr?'حذف':'Del') . '</a>

          </td>

        </tr>';

    }

    echo '</tbody></table></div>';

    if (empty($rows)) echo '<div class="alert alert-info">' . ($isAr ? 'لا توجد أنواع.' : 'No types.') . '</div>';

    return;

}

// ---------- TYPE ADD/EDIT POST HANDLER ----------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['types_add','types_edit'], true)) {

    require_login();

    $id = (int)($_POST['id'] ?? 0);

    $code = trim($_POST['code'] ?? '');

    $nameAr = trim($_POST['name_ar'] ?? '');

    $nameEn = trim($_POST['name_en'] ?? '');

    $descAr = trim($_POST['description_ar'] ?? '');

    $descEn = trim($_POST['description_en'] ?? '');

    $category = trim($_POST['category'] ?? 'FULL_DAY');

    $isActive = !empty($_POST['is_active']) ? 1 : 0;

    $errors = [];

    if ($code === '') $errors[] = $isAr ? 'الكود مطلوب' : 'Code is required';

    if ($nameAr === '') $errors[] = $isAr ? 'الاسم بالعربية مطلوب' : 'Name (Arabic) is required';

    if ($nameEn === '') $errors[] = $isAr ? 'الاسم بالإنجليزي مطلوب' : 'Name (English) is required';

    if (!in_array($category, ['TIME_BASED','FULL_DAY'], true)) $errors[] = $isAr ? 'الفئة غير صالحة' : 'Invalid category';

    if ($code !== '') {

        $existing = fetchRow("SELECT id FROM " . table('att_permission_types') . " WHERE code=? AND omitted=0 AND id<>? LIMIT 1", [$code, $id]);

        if ($existing) $errors[] = $isAr ? 'الكود موجود بالفعل' : 'Code already exists';

    }

    if (!empty($errors)) {

        set_flash('error', implode(' | ', $errors));

        header('Location: ?module=attpermissions&a=' . $action . ($action==='types_edit' ? '&id=' . $id : ''));

        exit;

    }

    $u = current_user(); $an = $u ? ($u['username_ar'] ?? $u['username']) : 'System';

    $now = date('Y-m-d H:i:s');

    if ($action === 'types_add') {

        $rguid = bin2hex(random_bytes(16));

        q("INSERT INTO " . table('att_permission_types') . " (rguid,code,name_ar,name_en,description_ar,description_en,category,is_active,dtadded,addedbyid,addedby,addedbyname,addedbynamear,omitted) VALUES (?,?,?,?,?,?,?,?,NOW(),?,?,?,?,0)",

            [$rguid,$code,$nameAr,$nameEn,$descAr,$descEn,$category,$isActive,$u ? $u['id'] : 1,$u ? $u['username'] : 'System',$an,$an]);

    } else {

        q("UPDATE " . table('att_permission_types') . " SET code=?,name_ar=?,name_en=?,description_ar=?,description_en=?,category=?,is_active=?,lastedit=NOW(),editedbyid=?,editedby=?,editedbyname=?,editedbynamear=? WHERE id=?",

            [$code,$nameAr,$nameEn,$descAr,$descEn,$category,$isActive,$u ? $u['id'] : 1,$u ? $u['username'] : 'System',$an,$an,$id]);

    }

    set_flash('success', $isAr ? 'تم حفظ النوع.' : 'Type saved.');

    header('Location: ?module=attpermissions&a=types'); exit;

}

// ---------- TYPE ADD/EDIT FORM ----------

if (in_array($action, ['types_add','types_edit'], true)) {

    require_login();

    $row = null;

    if ($action === 'types_edit' && $id) {

        $row = fetchRow("SELECT * FROM " . table('att_permission_types') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);

        if (!$row) { set_flash('error', $isAr?'غير موجود':'Not found'); header('Location: ?module=attpermissions&a=types'); exit; }

    }

    echo '<div class="d-flex justify-content-between align-items-center mb-3">

            <h2>'.($isAr ? ($action==='types_add'?'إضافة نوع':'تعديل نوع') : ($action==='types_add'?'Add Type':'Edit Type')).'</h2>

            <a href="?module=attpermissions&a=types" class="btn btn-secondary btn-sm">'.($isAr?'رجوع':'Back').'</a>

          </div>';

    echo '<form method="post" action="?module=attpermissions&a=' . $action . ($action==='types_edit'?'&id='.$id:'') . '">';

    echo '<div class="row"><div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'الكود':'Code') . '</label><input type="text" name="code" class="form-control" value="' . htmlspecialchars($row['code'] ?? '') . '" required></div>

          <div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'الاسم (عربي)':'Name (Arabic)') . '</label><input type="text" name="name_ar" class="form-control" value="' . htmlspecialchars($row['name_ar'] ?? '') . '" required></div></div>';

    echo '<div class="row"><div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'الاسم (إنجليزي)':'Name (English)') . '</label><input type="text" name="name_en" class="form-control" value="' . htmlspecialchars($row['name_en'] ?? '') . '" required></div>

          <div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'الوصف (عربي)':'Description (Arabic)') . '</label><textarea name="description_ar" class="form-control">' . htmlspecialchars($row['description_ar'] ?? '') . '</textarea></div></div>';

    echo '<div class="row"><div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'الوصف (إنجليزي)':'Description (English)') . '</label><textarea name="description_en" class="form-control">' . htmlspecialchars($row['description_en'] ?? '') . '</textarea></div>

          <div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'الفئة':'Category') . '</label><select name="category" class="form-select" required>

            <option value="TIME_BASED"' . ((($row['category'] ?? '')==='TIME_BASED')?' selected':'') . '>' . ($isAr?'مبني على الوقت':'Time-based') . '</option>

            <option value="FULL_DAY"' . ((($row['category'] ?? '')==='FULL_DAY')?' selected':'') . '>' . ($isAr?'يوم كامل':'Full-day') . '</option>

          </select></div></div>';

    echo '<div class="row"><div class="col-md-4 mb-3"><div class="form-check mt-4"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"' . (($row['is_active'] ?? 1) ? ' checked' : '') . '><label class="form-check-label" for="is_active">' . ($isAr?'فعال':'Active') . '</label></div></div></div>';

    echo '<button class="btn btn-primary">' . ($isAr?'حفظ':'Save') . '</button></form>';

    return;

}

// ---------- TYPE DELETE ----------

if ($action === 'types_del' && $id) {

    require_login();

    q("UPDATE " . table('att_permission_types') . " SET omitted=1 WHERE id=?", [$id]);

    set_flash('success', $isAr ? 'تم الحذف.' : 'Deleted.');

    header('Location: ?module=attpermissions&a=types'); exit;

}

// ---------- POLICIES LIST ----------

if ($action === 'policies') {

    require_login();

    $rows = fetchAll("SELECT p.*, t.name_ar AS type_ar, t.name_en AS type_en FROM " . table('att_permission_policies') . " p JOIN " . table('att_permission_types') . " t ON t.id=p.type_id WHERE p.omitted=0 ORDER BY t.name_en ASC");

    echo '<div class="d-flex justify-content-between align-items-center mb-3">

            <h2>'.($isAr ? 'سياسات الإذن' : 'Permission Policies').'</h2>

            <a href="?module=attpermissions&a=policies_add" class="btn btn-primary btn-sm">'.($isAr ? 'إضافة سياسة' : 'Add Policy').'</a>

          </div>';

    echo '<div class="table-responsive"><table class="table table-sm table-bordered">

            <thead class="table-light"><tr>

              <th>'.($isAr?'الاسم (AR)':'Name (AR)').'</th>

              <th>'.($isAr?'الاسم (EN)':'Name (EN)').'</th>

              <th>'.($isAr?'النوع':'Type').'</th>

              <th>'.($isAr?'النطاق':'Scope').'</th>

              <th>'.($isAr?'الوحدة':'Unit').'</th>

              <th>'.($isAr?'الفترة':'Period').'</th>

              <th>'.($isAr?'الحد الأقصى':'Max').'</th>

              <th>'.($isAr?'فعال':'Active').'</th>

              <th></th>

            </tr></thead><tbody>';

    foreach ($rows as $r) {

        $max = '';

        if ($r['limit_unit']==='REQUEST_COUNT') $max = $r['max_requests'] ?? '—';

        elseif ($r['limit_unit']==='MINUTES') $max = $r['max_minutes'] ?? '—';

        elseif ($r['limit_unit']==='DAYS') $max = $r['max_days'] ?? '—';

        echo '<tr>

          <td>'. htmlspecialchars($r['name_ar']) .'</td>

          <td>'. htmlspecialchars($r['name_en']) .'</td>

          <td>'. htmlspecialchars($isAr ? $r['type_ar'] : $r['type_en']) .'</td>

          <td>'. htmlspecialchars($r['scope_type'] ?? '') .' '. ($r['scope_id'] ?? '') .'</td>

          <td>'. htmlspecialchars($r['limit_unit'] ?? '') .'</td>

          <td>'. htmlspecialchars($r['period_type'] ?? '') .'</td>

          <td>'. htmlspecialchars($max) .'</td>

          <td>'. ($r['is_active'] ? '✓' : '') .'</td>

          <td>

            <a href="?module=attpermissions&a=policies_edit&id=' . $r['id'] . '" class="btn btn-sm btn-outline-secondary">' . ($isAr?'تعديل':'Edit') . '</a>

            <a href="?module=attpermissions&a=policies_del&id=' . $r['id'] . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\''.($isAr?'حذف؟':'Delete?').'\')">' . ($isAr?'حذف':'Del') . '</a>

          </td>

        </tr>';

    }

    echo '</tbody></table></div>';

    if (empty($rows)) echo '<div class="alert alert-info">' . ($isAr ? 'لا توجد سياسات.' : 'No policies.') . '</div>';

    return;

}

// ---------- POLICY ADD/EDIT POST HANDLER ----------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['policies_add','policies_edit'], true)) {

    require_login();

    $id = (int)($_POST['id'] ?? 0);

    $typeId = (int)($_POST['type_id'] ?? 0);

    $scopeType = trim($_POST['scope_type'] ?? 'GLOBAL');

    $scopeId = (int)($_POST['scope_id'] ?? 0);

    $limitUnit = trim($_POST['limit_unit'] ?? 'REQUEST_COUNT');

    $periodType = trim($_POST['period_type'] ?? 'MONTHLY');

    $maxRequests = (int)($_POST['max_requests'] ?? 0);

    $maxMinutes = (int)($_POST['max_minutes'] ?? 0);

    $maxDays = (int)($_POST['max_days'] ?? 0);

    $maxSingleRequest = (int)($_POST['max_single_request'] ?? 0);

    $minimumNotice = (int)($_POST['minimum_notice'] ?? 0);

    $allowRetroactive = !empty($_POST['allow_retroactive']) ? 1 : 0;

    $maxRetroactiveDays = (int)($_POST['max_retroactive_days'] ?? 0);

    $requireReason = !empty($_POST['require_reason']) ? 1 : 0;

    $requireAttachment = !empty($_POST['require_attachment']) ? 1 : 0;

    $pendingReserve = !empty($_POST['pending_reserve']) ? 1 : 0;

    $effectiveFrom = trim($_POST['effective_from'] ?? '');

    $effectiveTo = trim($_POST['effective_to'] ?? '');

    $isActive = !empty($_POST['is_active']) ? 1 : 0;

    $nameAr = trim($_POST['name_ar'] ?? '');

    $nameEn = trim($_POST['name_en'] ?? '');

    $descAr = trim($_POST['description_ar'] ?? '');

    $descEn = trim($_POST['description_en'] ?? '');

    $errors = [];

    if ($typeId <= 0) $errors[] = $isAr ? 'النوع مطلوب' : 'Permission type is required';

    if ($nameAr === '') $errors[] = $isAr ? 'الاسم بالعربية مطلوب' : 'Name (Arabic) is required';

    if ($nameEn === '') $errors[] = $isAr ? 'الاسم بالإنجليزي مطلوب' : 'Name (English) is required';

    if (!in_array($scopeType, ['GLOBAL','DEPARTMENT','GROUP','INDIVIDUAL'], true)) $errors[] = $isAr ? 'النطاق غير صالح' : 'Invalid scope type';

    if (!in_array($limitUnit, ['REQUEST_COUNT','MINUTES','DAYS','UNLIMITED'], true)) $errors[] = $isAr ? 'الوحدة غير صالحة' : 'Invalid limit unit';

    if (!in_array($periodType, ['MONTHLY','QUARTERLY','YEARLY','CUSTOM','UNLIMITED'], true)) $errors[] = $isAr ? 'الفترة غير صالحة' : 'Invalid period type';

    if (!empty($errors)) {

        set_flash('error', implode(' | ', $errors));

        header('Location: ?module=attpermissions&a=' . $action . ($action==='policies_edit' ? '&id=' . $id : ''));

        exit;

    }

    $u = current_user(); $an = $u ? ($u['username_ar'] ?? $u['username']) : 'System';

    $now = date('Y-m-d H:i:s');

    if ($action === 'policies_add') {

        $rguid = bin2hex(random_bytes(16));

        q("INSERT INTO " . table('att_permission_policies') . " (rguid,type_id,name_ar,name_en,description_ar,description_en,scope_type,scope_id,limit_unit,period_type,max_requests,max_minutes,max_days,max_single_request,minimum_notice,allow_retroactive,max_retroactive_days,require_reason,require_attachment,pending_reserve,effective_from,effective_to,is_active,dtadded,addedbyid,addedby,addedbyname,addedbynamear,omitted) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,0)",

            [$rguid,$typeId,$nameAr,$nameEn,$descAr,$descEn,$scopeType,$scopeId,$limitUnit,$periodType,$maxRequests,$maxMinutes,$maxDays,$maxSingleRequest,$minimumNotice,$allowRetroactive,$maxRetroactiveDays,$requireReason,$requireAttachment,$pendingReserve,$effectiveFrom,$effectiveTo,$isActive,$u ? $u['id'] : 1,$u ? $u['username'] : 'System',$an,$an]);

    } else {

        q("UPDATE " . table('att_permission_policies') . " SET type_id=?,name_ar=?,name_en=?,description_ar=?,description_en=?,scope_type=?,scope_id=?,limit_unit=?,period_type=?,max_requests=?,max_minutes=?,max_days=?,max_single_request=?,minimum_notice=?,allow_retroactive=?,max_retroactive_days=?,require_reason=?,require_attachment=?,pending_reserve=?,effective_from=?,effective_to=?,is_active=?,lastedit=NOW(),editedbyid=?,editedby=?,editedbyname=?,editedbynamear=? WHERE id=?",

            [$typeId,$nameAr,$nameEn,$descAr,$descEn,$scopeType,$scopeId,$limitUnit,$periodType,$maxRequests,$maxMinutes,$maxDays,$maxSingleRequest,$minimumNotice,$allowRetroactive,$maxRetroactiveDays,$requireReason,$requireAttachment,$pendingReserve,$effectiveFrom,$effectiveTo,$isActive,$u ? $u['id'] : 1,$u ? $u['username'] : 'System',$an,$an,$id]);

    }

    set_flash('success', $isAr ? 'تم حفظ السياسة.' : 'Policy saved.');

    header('Location: ?module=attpermissions&a=policies'); exit;

}

// ---------- POLICY ADD/EDIT FORM ----------

if (in_array($action, ['policies_add','policies_edit'], true)) {

    require_login();

    $row = null;

    if ($action === 'policies_edit' && $id) {

        $row = fetchRow("SELECT * FROM " . table('att_permission_policies') . " WHERE id=? AND omitted=0 LIMIT 1", [$id]);

        if (!$row) { set_flash('error', $isAr?'غير موجود':'Not found'); header('Location: ?module=attpermissions&a=policies'); exit; }

    }

    $types = fetchAll("SELECT id,name_ar,name_en FROM " . table('att_permission_types') . " WHERE omitted=0 AND is_active=1 ORDER BY name_en");

    echo '<div class="d-flex justify-content-between align-items-center mb-3">

            <h2>'.($isAr ? ($action==='policies_add'?'إضافة سياسة':'تعديل سياسة') : ($action==='policies_add'?'Add Policy':'Edit Policy')).'</h2>

            <a href="?module=attpermissions&a=policies" class="btn btn-secondary btn-sm">'.($isAr?'رجوع':'Back').'</a>

          </div>';

    echo '<form method="post" action="?module=attpermissions&a=' . $action . ($action==='policies_edit'?'&id='.$id:'') . '">';

    echo '<div class="row"><div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'النوع':'Permission Type') . '</label><select name="type_id" class="form-select" required><option value="">-- ' . ($isAr?'اختر':'Select') . ' --</option>';

    foreach ($types as $t) echo '<option value="' . $t['id'] . '"' . (($row['type_id'] ?? '')==$t['id']?' selected':'') . '>' . htmlspecialchars($isAr ? $t['name_ar'] : $t['name_en']) . '</option>';

    echo '</select></div>

          <div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'النطاق':'Scope Type') . '</label><select name="scope_type" class="form-select" required>

            <option value="GLOBAL"' . ((($row['scope_type'] ?? '')==='GLOBAL')?' selected':'') . '>' . ($isAr?'عام':'Global') . '</option>

            <option value="DEPARTMENT"' . ((($row['scope_type'] ?? '')==='DEPARTMENT')?' selected':'') . '>' . ($isAr?'قسم':'Department') . '</option>

            <option value="GROUP"' . ((($row['scope_type'] ?? '')==='GROUP')?' selected':'') . '>' . ($isAr?'مجموعة':'Group') . '</option>

            <option value="INDIVIDUAL"' . ((($row['scope_type'] ?? '')==='INDIVIDUAL')?' selected':'') . '>' . ($isAr?'فردي':'Individual') . '</option>

          </select></div></div>';

    echo '<div class="row"><div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'معرف النطاق (اختياري)':'Scope ID (optional)') . '</label><input type="number" name="scope_id" class="form-control" value="' . htmlspecialchars($row['scope_id'] ?? '') . '"></div>

          <div class="col-md-6 mb-3"><label class="form-label">' . ($isAr?'وحدة الحد':'Limit Unit') . '</label><select name="limit_unit" class="form-select" required>

            <option value="REQUEST_COUNT"' . ((($row['limit_unit'] ?? '')==='REQUEST_COUNT')?' selected':'') . '>' . ($isAr?'عدد الطلبات':'Request Count') . '</option>

            <option value="MINUTES"' . ((($row['limit_unit'] ?? '')==='MINUTES')?' selected':'') . '>' . ($isAr?'الدقائق':'Minutes') . '</option>

            <option value="DAYS"' . ((($row['limit_unit'] ?? '')==='DAYS')?' selected':'') . '>' . ($isAr?'الأيام':'Days') . '</option>

            <option value="UNLIMITED"' . ((($row['limit_unit'] ?? '')==='UNLIMITED')?' selected':'') . '>' . ($isAr?'غير محدود':'Unlimited') . '</option>

          </select></div></div>';

    echo '<div class="row"><div class="col-md-4 mb-3"><label class="form-label">' . ($isAr?'الفترة':'Period Type') . '</label><select name="period_type" class="form-select" required>

            <option value="MONTHLY"' . ((($row['period_type'] ?? '')==='MONTHLY')?' selected':'') . '>' . ($isAr?'شهري':'Monthly') . '</option>

            <option value="QUARTERLY"' . ((($row['period_type'] ?? '')==='QUARTERLY')?' selected':'') . '>' . ($isAr?'ربعي':'Quarterly') . '</option>

            <option value="YEARLY"' . ((($row['period_type'] ?? '')==='YEARLY')?' selected':'') . '>' . ($isAr?'سنوي':'Yearly') . '</option>

            <option value="CUSTOM"' . ((($row['period_type'] ?? '')==='CUSTOM')?' selected':'') . '>' . ($isAr?'مخصص':'Custom') . '</option>

            <option value="UNLIMITED"' . ((($row['period_type'] ?? '')==='UNLIMITED')?' selected':'') . '>' . ($isAr?'غير محدود':'Unlimited') . '</option>

          </select></div>

          <div class="col-md-4 mb-3"><label class="form-label">' . ($isAr?'الحد الأقصى للطلبات':'Max Requests') . '</label><input type="number" name="max_requests" class="form-control" value="' . htmlspecialchars($row['max_requests'] ?? '') . '"></div>

          <div class="col-md-4 mb-3"><label class="form-label">' . ($isAr?'الحد الأقصى للدقائق':'Max Minutes') . '</label><input type="number" name="max_minutes" class="form-control" value="' . htmlspecialchars($row['max_minutes'] ?? '') . '"></div>

          <div class="col-md-4 mb-3"><label class="form-label">' . ($isAr?'الحد الأقصى للأيام':'Max Days') . '</label><input type="number" name="max_days" class="form-control" value="' . htmlspecialchars($row['max_days'] ?? '') . '"></div></div>';

    echo '<div class="row"><div class="col-md-4 mb-3"><label class="form-label">' . ($isAr?'الحد الأقصى لطلب واحد':'Max Single Request') . '</label><input type="number" name="max_single_request" class="form-control" value="' . htmlspecialchars($row['max_single_request'] ?? '') . '"></div>

          <div class="col-md-4 mb-3"><label class="form-label">' . ($isAr?'الإشعار المسبق (أيام)':'Minimum Notice (days)') . '</label><input type="number" name="minimum_notice" class="form-control" value="' . htmlspecialchars($row['minimum_notice'] ?? '') . '"></div>

          <div class="col-md-4 mb-3"><div class="form-check mt-4"><input type="checkbox" name="allow_retroactive" value="1" class="form-check-input" id="allow_retroactive"' . (($row['allow_retroactive'] ?? 0) ? ' checked' : '') . '><label class="form-check-label" for="allow_retroactive">' . ($isAr?'يسمح بأثر رجعي':'Allow Retroactive') . '</label></div></div>

          <div class="col-md-4 mb-3"><div class="form-check mt-4"><input type="checkbox" name="require_reason" value="1" class="form-check-input" id="require_reason"' . (($row['require_reason'] ?? 1) ? ' checked' : '') . '><label class="form-check-label" for="require_reason">' . ($isAr?'السبب مطلوب':'Require Reason') . '</label></div></div>

          <div class="col-md-4 mb-3"><div class="form-check mt-4"><input type="checkbox" name="require_attachment" value="1" class="form-check-input" id="require_attachment"' . (($row['require_attachment'] ?? 0) ? ' checked' : '') . '><label class="form-check-label" for="require_attachment">' . ($isAr?'المرفق مطلوب':'Require Attachment') . '</label></div></div></div>';

    echo '<div class="row"><div class="col-md-4 mb-3"><label class="form-label">' . ($isAr?'الحد الأقصى للأيام بأثر رجعي':'Max Retroactive Days') . '</label><input type="number" name="max_retroactive_days" class="form-control" value="' . htmlspecialchars($row['max_retroactive_days'] ?? '') . '"></div>

          <div class="col-md-4 mb-3"><div class="form-check mt-4"><input type="checkbox" name="pending_reserve" value="1" class="form-check-input" id="pending_reserve"' . (($row['pending_reserve'] ?? 1) ? ' checked' : '') . '><label class="form-check-label" for="pending_reserve">' . ($isAr?'احتفظ بالطلبات المعلقة':'Reserve Pending') . '</label></div></div>

          <div class="col-md-4 mb-3"><label class="form-label">' . ($isAr?'ساري من':'Effective From') . '</label><input type="date" name="effective_from" class="form-control" value="' . htmlspecialchars($row['effective_from'] ?? date('Y-01-01')) . '"></div>

          <div class="col-md-4 mb-3"><label class="form-label">' . ($isAr?'ساري إلى (اختياري)':'Effective To (optional)') . '</label><input type="date" name="effective_to" class="form-control" value="' . htmlspecialchars($row['effective_to'] ?? '') . '"></div>

          <div class="col-md-4 mb-3"><div class="form-check mt-4"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active_pol"' . (($row['is_active'] ?? 1) ? ' checked' : '') . '><label class="form-check-label" for="is_active_pol">' . ($isAr?'فعال':'Active') . '</label></div></div></div>';

    echo '<button class="btn btn-primary">' . ($isAr?'حفظ':'Save') . '</button></form>';

    return;

}

// ---------- POLICY DELETE ----------

if ($action === 'policies_del' && $id) {

    require_login();

    q("UPDATE " . table('att_permission_policies') . " SET omitted=1 WHERE id=?", [$id]);

    set_flash('success', $isAr ? 'تم الحذف.' : 'Deleted.');

    header('Location: ?module=attpermissions&a=policies'); exit;

}

// ---------- APPROVALS LIST (read-only) ----------

if ($action === 'approvals') {

    require_login();

    $rows = fetchAll("SELECT a.*, r.start_date, r.end_date, r.start_time, r.end_time, e.empfullname, e.empfullnamear, t.name_ar AS type_ar, t.name_en AS type_en, u.username FROM " . table('att_permission_approvals') . " a

        JOIN " . table('att_permission_requests') . " r ON r.id=a.request_id

        JOIN " . table('employeesmain') . " e ON e.id=r.employee_id

        JOIN " . table('att_permission_types') . " t ON t.id=r.type_id

        LEFT JOIN " . table('users') . " u ON u.id=a.approver_user_id

        WHERE a.omitted=0 ORDER BY a.id DESC LIMIT 200");

    echo '<div class="d-flex justify-content-between align-items-center mb-3">

            <h2>'.($isAr ? 'سجل الاعتمادات' : 'Approval History').'</h2>

          </div>';

    echo '<div class="table-responsive"><table class="table table-sm table-bordered">

            <thead class="table-light"><tr>

              <th>#</th><th>'.($isAr?'الموظف':'Employee').'</th><th>'.($isAr?'النوع':'Type').'</th>

              <th>'.($isAr?'المستوى':'Level').'</th><th>'.($isAr?'المعتمد':'Approver').'</th>

              <th>'.($isAr?'القرار':'Decision').'</th><th>'.($isAr?'التاريخ':'Date').'</th>

              <th>'.($isAr?'التعليق':'Comments').'</th>

            </tr></thead><tbody>';

    foreach ($rows as $r) {

        echo '<tr>

          <td>'. (int)$r['id'] .'</td>

          <td>'. htmlspecialchars($isAr ? $r['empfullnamear'] : $r['empfullname']) .'</td>

          <td>'. htmlspecialchars($isAr ? $r['type_ar'] : $r['type_en']) .'</td>

          <td>'. (int)$r['approval_level'] .'</td>

          <td>'. htmlspecialchars($r['username'] ?? '') .'</td>

          <td>'. htmlspecialchars($r['decision']) .'</td>

          <td>'. htmlspecialchars($r['decided_at'] ?? '') .'</td>

          <td>'. htmlspecialchars($r['comments'] ?? '') .'</td>

        </tr>';

    }

    echo '</tbody></table></div>';

    if (empty($rows)) echo '<div class="alert alert-info">' . ($isAr ? 'لا توجد سجلات.' : 'No records.') . '</div>';

    return;

}

// ---------- PERMISSION ATTENDANCE LIST (read-only) ----------

if ($action === 'attendance') {

    require_login();

    $rows = fetchAll("SELECT a.*, r.start_date, r.end_date, e.empfullname, e.empfullnamear, t.name_ar AS type_ar, t.name_en AS type_en, da.attendance_date, da.status_code FROM " . table('att_permission_attendance') . " a

        JOIN " . table('att_permission_requests') . " r ON r.id=a.request_id

        JOIN " . table('employeesmain') . " e ON e.id=r.employee_id

        JOIN " . table('att_permission_types') . " t ON t.id=r.type_id

        JOIN " . table('daily_attendance') . " da ON da.id=a.attendance_id

        WHERE a.omitted=0 ORDER BY da.attendance_date DESC LIMIT 200");

    echo '<div class="d-flex justify-content-between align-items-center mb-3">

            <h2>'.($isAr ? 'تأثير الإذن على الحضور' : 'Permission Attendance Impact').'</h2>

          </div>';

    echo '<div class="table-responsive"><table class="table table-sm table-bordered">

            <thead class="table-light"><tr>

              <th>#</th><th>'.($isAr?'الموظف':'Employee').'</th><th>'.($isAr?'النوع':'Type').'</th>

              <th>'.($isAr?'التاريخ':'Date').'</th><th>'.($isAr?'الحالة':'Status').'</th>

              <th>'.($isAr?'الدقائق المطبقة':'Applied Minutes').'</th>

            </tr></thead><tbody>';

    foreach ($rows as $r) {

        echo '<tr>

          <td>'. (int)$r['id'] .'</td>

          <td>'. htmlspecialchars($isAr ? $r['empfullnamear'] : $r['empfullname']) .'</td>

          <td>'. htmlspecialchars($isAr ? $r['type_ar'] : $r['type_en']) .'</td>

          <td>'. htmlspecialchars($r['attendance_date']) .'</td>

          <td>'. htmlspecialchars($r['status_code']) .'</td>

          <td>'. (int)$r['applied_minutes'] .'</td>

        </tr>';

    }

    echo '</tbody></table></div>';

    if (empty($rows)) echo '<div class="alert alert-info">' . ($isAr ? 'لا توجد سجلات.' : 'No records.') . '</div>';

    return;

}

// ---------- LIST ----------

require_login();

$uid = current_user_id();

$emp = null;

if (!is_app_staff()) {

    $emp = fetchRow("SELECT id FROM " . table('employeesmain') . " WHERE nid=? AND omitted=0 LIMIT 1", [current_user()['username'] ?? '']);

}

if (is_app_staff()) {

    $rows = fetchAll("SELECT r.*, t.code, t.name_ar AS type_ar, t.name_en AS type_en, e.empfullname, e.empfullnamear FROM " . table('att_permission_requests') . " r JOIN " . table('att_permission_types') . " t ON t.id=r.type_id JOIN " . table('employeesmain') . " e ON e.id=r.employee_id WHERE r.omitted=0 ORDER BY r.id DESC LIMIT 200");

} elseif ($emp) {

    $rows = fetchAll("SELECT r.*, t.code, t.name_ar AS type_ar, t.name_en AS type_en, e.empfullname, e.empfullnamear FROM " . table('att_permission_requests') . " r JOIN " . table('att_permission_types') . " t ON t.id=r.type_id JOIN " . table('employeesmain') . " e ON e.id=r.employee_id WHERE r.omitted=0 AND (r.employee_id=? OR r.id IN (SELECT request_id FROM " . table('att_permission_approvals') . " WHERE approver_user_id=?)) ORDER BY r.id DESC", [(int)$emp['id'], $uid]);

} else {

    $rows = [];

}

// Sync any legacy permissionid into the junction table (one-time migration)

foreach ($rows as $r) {

    if (!empty($r['permissionid'])) {

        $exists = fetchRow("SELECT id FROM " . table('user_permissions') . " WHERE user_id=? AND permission_id=? AND omitted=0 LIMIT 1", [(int)$r['id'], (int)$r['permissionid']]);

        if (!$exists) {

            q("INSERT INTO " . table('user_permissions') . " (rguid,user_id,permission_id,dtadded,addedbyid,addedby,addedbyname,addedbynamear,omitted) VALUES (?,?,?,NOW(),?,?,?,?,0)",

                [bin2hex(random_bytes(16)), (int)$r['id'], (int)$r['permissionid'], 1, 'System', 'System', 'System']);

        }

    }

}

?>

<div class="d-flex justify-content-between align-items-center mb-3">

    <h2><i class="bi bi-table me-2"></i><?= $isAr ? 'إذونات الحضور' : 'Attendance Permissions' ?></h2>

    <div class="d-flex gap-2">

      <a href="?module=attpermissions&a=request_add" class="btn btn-primary btn-sm"><?= $isAr ? 'طلب جديد' : 'New Request' ?></a>

      <a href="?module=attpermissions&a=types" class="btn btn-outline-secondary btn-sm"><?= $isAr ? 'أنواع الإذن' : 'Permission Types' ?></a>

      <a href="?module=attpermissions&a=policies" class="btn btn-outline-secondary btn-sm"><?= $isAr ? 'سياسات الإذن' : 'Permission Policies' ?></a>

      <a href="?module=attpermissions&a=approvals" class="btn btn-outline-secondary btn-sm"><?= $isAr ? 'سجل الاعتمادات' : 'Approval History' ?></a>

      <a href="?module=attpermissions&a=attendance" class="btn btn-outline-secondary btn-sm"><?= $isAr ? 'تأثير الحضور' : 'Attendance Impact' ?></a>

    </div>

</div>

<div class="table-responsive">

<table class="table table-sm table-bordered">

    <thead class="table-light"><tr>

        <th><?= $isAr?'#':'#' ?></th>

        <th><?= $isAr?'الموظف':'Employee' ?></th>

        <th><?= $isAr?'النوع':'Type' ?></th>

        <th><?= $isAr?'من':'From' ?></th>

        <th><?= $isAr?'إلى':'To' ?></th>

        <th><?= $isAr?'الحالة':'Status' ?></th>

        <th></th>

    </tr></thead><tbody>

    <?php foreach ($rows as $r): ?>

    <tr>

        <td><?= (int)$r['id'] ?></td>

        <td><?= htmlspecialchars($isAr ? $r['empfullnamear'] : $r['empfullname']) ?></td>

        <td><?= htmlspecialchars($isAr ? $r['type_ar'] : $r['type_en']) ?></td>

        <td><?= htmlspecialchars($r['start_date']) ?> <?= htmlspecialchars($r['start_time'] ?? '') ?></td>

        <td><?= htmlspecialchars($r['end_date']) ?> <?= htmlspecialchars($r['end_time'] ?? '') ?></td>

        <td><?= htmlspecialchars($r['status']) ?></td>

        <td><a href="?module=attpermissions&a=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary"><?= $isAr?'عرض':'View' ?></a></td>

    </tr>

    <?php endforeach; ?>

    </tbody></table>

</div>

<?php if (empty($rows)): ?><div class="alert alert-info"><?= $isAr ? 'لا توجد طلبات' : 'No requests' ?></div><?php endif; ?>
