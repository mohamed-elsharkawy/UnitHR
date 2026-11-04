<?php
/**
 * UnitHR - Database Installer / Schema Setup
 * Creates the MySQL database, all prefixed tables (uhr_*), the audit shadow
 * log, reference data, and a default SuperAdmin account.
 * Run via browser: http://localhost/UnitHR/install.php
 * or via CLI: php install.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$P = prefix();
$DB = app('dbname');
$messages = [];

$isCli = php_sapi_name() === 'cli';

// Total steps for progress calculation
$totalSteps = 15;
$currentStep = 0;

function progress_bar($percent, $label = '') {
    if (php_sapi_name() === 'cli') return;
    echo '<script>updateProgress(' . (int)$percent . ', ' . json_encode($label) . ');</script>' . "\n";
    ob_flush();
    flush();
}

function msg($m) {
    global $messages;
    $messages[] = $m;
    if (php_sapi_name() === 'cli') {
        echo $m . "\n";
    } else {
        echo '<div class="install-msg">' . $m . '</div>' . "\n";
        ob_flush();
        flush();
    }
}

if (!$isCli) {
    // HTML header with progress bar
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>UnitHR Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
        .installer-card { max-width: 700px; margin: 40px auto; }
        .progress { height: 30px; border-radius: 15px; }
        .progress-bar { transition: width 0.3s ease; font-weight: bold; }
        .install-msg { padding: 8px 12px; margin: 4px 0; border-left: 3px solid #0d6efd; background: #f8f9fa; }
        .install-msg.success { border-left-color: #198754; background: #d1e7dd; }
        .install-msg.error { border-left-color: #dc3545; background: #f8d7da; }
        .install-log { max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px; background: #fff; }
        .step-counter { font-size: 0.9rem; color: #6c757d; }
    </style>
</head>
<body>
<div class="container">
    <div class="installer-card">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center py-3">
                <h3 class="mb-0"><i class="bi bi-gear me-2"></i>UnitHR Database Installer</h3>
                <small>Setting up your HR Management System</small>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="step-counter" id="stepLabel">Initializing...</span>
                        <span class="step-counter" id="percentLabel">0%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                             id="progressBar" role="progressbar" style="width: 0%" 
                             aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                </div>
                <div class="install-log" id="installLog"></div>
                <div class="text-center mt-3" id="completionArea" style="display: none;">
                    <a href="index.php" class="btn btn-success btn-lg"><i class="bi bi-box-arrow-in-right me-2"></i>Go to Application</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function updateProgress(percent, label) {
    var bar = document.getElementById("progressBar");
    var pctLabel = document.getElementById("percentLabel");
    var stepLabel = document.getElementById("stepLabel");
    bar.style.width = percent + "%";
    bar.setAttribute("aria-valuenow", percent);
    bar.textContent = percent + "%";
    pctLabel.textContent = percent + "%";
    if (label) stepLabel.textContent = label;
    if (percent >= 100) {
        bar.classList.remove("progress-bar-animated");
        bar.classList.add("bg-success");
    }
}
function addMessage(msg, type) {
    var log = document.getElementById("installLog");
    var div = document.createElement("div");
    div.className = "install-msg " + (type || "");
    div.innerHTML = msg;
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
}
</script>
';
    ob_start();
    ob_implicit_flush(true);
}

function step($label, $callback) {
    global $currentStep, $totalSteps;
    $currentStep++;
    $percent = (int)(($currentStep / $totalSteps) * 100);
    progress_bar($percent, $label);
    $callback();
}

try {
    step('Connecting to database...', function() {});
    $pdo = new PDO("mysql:host=".app('dbhost').";port=".app('dbport')."", app('dbuser'), app('dbpass'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    step('Creating database...', function() use ($pdo, $DB) {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$DB` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$DB`");
    });
    msg("Database `$DB` ready.");

    step('Reading schema file...', function() {});
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) {
        msg("schema.sql not found. Create it first.");
        exit;
    }

    step('Creating tables...', function() use ($pdo, $sql) {
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $sql = preg_replace('/(--[^\n]*|#[^\n]*)/', '', $sql);
        $stmts = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($stmts as $stmt) {
            if ($stmt === '') continue;
            $pdo->exec($stmt);
        }
    });
    msg("All tables created with prefix `$P`.");

    $deptypeCount = (int)$pdo->query("SELECT COUNT(*) FROM `{$P}deptypes`")->fetchColumn();
    if ($deptypeCount > 0) {
        msg("Existing data detected; skipped seed inserts.");
        msg("<b>Installation complete.</b> You can now open the application.");
        progress_bar(100, 'Complete!');
        if (!$isCli) {
            echo '<script>document.getElementById("completionArea").style.display = "block";</script>';
            echo '<br><a href="index.php" class="btn btn-success btn-lg"><i class="bi bi-box-arrow-in-right me-2"></i>Go to Application</a>';
        }
        exit;
    }

    step('Inserting department types...', function() use ($pdo, $P) {
        $pdo->prepare("INSERT INTO `{$P}deptypes` (rguid, deptypename, deptypenamear, deptypeparentid, deptypeparentname, deptypeparentnamear, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,NULL,NULL,NULL,1,'System','System','نظام',NOW(),1,'System','System','نظام',0)")->execute([bin2hex(random_bytes(16)), 'Administrative', 'إداري']);
        $pdo->prepare("INSERT INTO `{$P}deptypes` (rguid, deptypename, deptypenamear, deptypeparentid, deptypeparentname, deptypeparentnamear, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,NULL,NULL,NULL,1,'System','System','نظام',NOW(),1,'System','System','نظام',0)")->execute([bin2hex(random_bytes(16)), 'Medical', 'طبي']);
        $pdo->prepare("INSERT INTO `{$P}deptypes` (rguid, deptypename, deptypenamear, deptypeparentid, deptypeparentname, deptypeparentnamear, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,NULL,NULL,NULL,1,'System','System','نظام',NOW(),1,'System','System','نظام',0)")->execute([bin2hex(random_bytes(16)), 'Scientific', 'علمي']);
    });
    msg("Department types inserted.");

    step('Inserting employee groups...', function() use ($pdo, $P) {
        foreach (['Administrative Staff'=>'إداريين','Scientific Staff'=>'علميين'] as $en => $ar) {
            $pdo->prepare("INSERT INTO `{$P}employeesgroup` (rguid, empgroupname, empgroupnamear, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,1,'System','System','نظام',NOW(),1,'System','System','نظام',0)")->execute([bin2hex(random_bytes(16)), $en, $ar]);
        }
    });
    msg("Employee groups inserted.");

    step('Inserting employee types...', function() use ($pdo, $P) {
        $types = ['Appointed Employee'=>'موظف معين','Seconded Employee from outside'=>'موظف منتدب من جهة','Seconded Employee to us'=>'موظف معار إلى جهة','Resigned'=>'مستقيل','Let Go / Dismissed'=>'مفصول','On a Leave'=>'في أجازة','Loaned employee from outside'=>'موظف معار من جهة','Loaned employee to us'=>'موظف معار إلى جهة','Contract-based employee'=>'موظف بتعاقد'];
        foreach ($types as $en => $ar) {
            $pdo->prepare("INSERT INTO `{$P}employeestypes` (rguid, emptypename, emptypenamear, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,1,'System','System','نظام',NOW(),1,'System','System','نظام',0)")->execute([bin2hex(random_bytes(16)), $en, $ar]);
        }
    });
    msg("Employee types inserted.");

    step('Inserting leave types...', function() use ($pdo, $P) {
        $leaves = ['Recursive Leave'=>'أجازة إعتيادية','Emergency Leave'=>'أجازة عارضة','Sick Leave'=>'أجازة مرضية','Hajj Leave'=>'أجازة حج','Umrah Leave'=>'أجازة عمرة','Annual Leave'=>'أجازة سنوية'];
        foreach ($leaves as $en => $ar) {
            $pdo->prepare("INSERT INTO `{$P}leavestypes` (rguid, leavetypename, leavetypenamear, tpel, tpey, incwe, incholidays, transferable, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,0,0,0,0,0,1,'System','System','نظام',NOW(),1,'System','System','نظام',0)")->execute([bin2hex(random_bytes(16)), $en, $ar]);
        }
    });
    msg("Leave types inserted.");

    step('Inserting employee degrees...', function() use ($pdo, $P) {
        $degrees = ['Sixth Degree'=>'الدرجة السادسة','Fifth Degree'=>'الدرجة الخامسة','Fourth Degree'=>'الدرجة الرابعة','Third Degree C'=>'الدرجة الثالثة ج','Third Degree B'=>'الدرجة الثالثة ب','Third Degree A'=>'الدرجة الثالثة أ','Second Degree B'=>'الدرجة الثانية ب','Second Degree A'=>'الدرجة الثانية أ','First Degree B'=>'الدرجة الأولى ب','First Degree A'=>'الدرجة الأولى أ','Manager'=>'مدير عام','Director'=>'رئيس إدارة مركزية','Head of Sector'=>'رئيس قطاع','Consultant'=>'مستشار'];
        foreach ($degrees as $en => $ar) {
            $pdo->prepare("INSERT INTO `{$P}empdegrees` (rguid, degreename, degreenamear, interimperiod, reqexam, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,0,0,1,'System','System','نظام',NOW(),1,'System','System','نظام',0)")->execute([bin2hex(random_bytes(16)), $en, $ar]);
        }
    });
    msg("Employee degrees inserted.");

    step('Inserting employee statuses...', function() use ($pdo, $P) {
        $statuses = ['On Job'=>'على رأس العمل','Leave'=>'أجازة','Seconded to another organization'=>'منتدب إلى جهة','Loaned to an organization'=>'معار إلى جهة','Pension'=>'معاش','Dismissed'=>'مفصول','Exempted under Law 167'=>'عجز طبي بقانون ١٦٧','Assignment (temporary delegation of duties)'=>'تكليف','Called up for military service'=>'مستدعى للتجنيد','Suspended'=>'موقوف','Deceased'=>'وفاه','Stateless'=>'فاقد الجنسية'];
        $payable = [1,1,1,1,0,0,0,1,1,0,0,0];
        $i = 0;
        foreach ($statuses as $en => $ar) {
            $pay = $payable[$i++] ?? 1;
            $pdo->prepare("INSERT INTO `{$P}empstatus` (rguid, empstatusname, empstatusnamear, ispayable, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,?,1,'System','System','نظام',NOW(),1,'System','System','نظام',0)")->execute([bin2hex(random_bytes(16)), $en, $ar, $pay]);
        }
    });
    msg("Employee statuses inserted.");

    step('Inserting retirement plan...', function() use ($pdo, $P) {
        $plan = [[1971,60],[1972,61],[1973,61],[1974,62],[1975,62],[1976,63],[1977,63],[1978,64],[1979,64],[1980,65],[1981,65]];
        foreach ($plan as $s) {
            list($yb, $sy) = $s;
            $pdo->prepare("INSERT INTO `{$P}retireplan` (rguid, yearofbirth, serviceyears, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,1,'System','System','نظام',NOW(),1,'System','System','نظام',0)")->execute([bin2hex(random_bytes(16)), $yb, $sy]);
        }
    });
    msg("Retirement plan inserted.");

    step('Inserting fiscal years...', function() use ($pdo, $P) {
        $pdo->prepare("INSERT INTO `{$P}fiscalyears` (rguid, fstartdate, fenddate, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,1,'System','System','نظام',NOW(),1,'System','System','نظام',0)")->execute([bin2hex(random_bytes(16)), '2024-01-01', '2024-12-31']);
        $pdo->prepare("INSERT INTO `{$P}fiscalyears` (rguid, fstartdate, fenddate, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,1,'System','System','نظام',NOW(),1,'System','System','نظام',0)")->execute([bin2hex(random_bytes(16)), '2025-01-01', '2025-12-31']);
    });
    msg("Fiscal years inserted.");

    step('Creating SuperAdmin user...', function() use ($pdo, $P) {
        $encPass = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT IGNORE INTO `{$P}users` (rguid, username, email, password, departmentid, departmentname, departmentnamear, permissionid, lastlogin, otp_secret, otp_enabled, is_admin, is_superadmin, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,?,NULL,'System','نظام',NULL,NULL,NULL,0,1,1,1,'System','System','نظام',NOW(),1,'System','System','نظام',0)")->execute([bin2hex(random_bytes(16)), 'admin', 'admin@unithr.local', $encPass]);
    });
    msg("SuperAdmin user created.");

    step('Creating permissions...', function() use ($pdo, $P) {
        $modules = ['appmodules','deptypes','depstructure','users','permissions','accesslog','employeesgroup','employeestypes','leavestypes','empdegrees','empstatus','retireplan','fiscalyears','employeesmain','leavesbalances','empleaves','reports','ess'];
        $modAr = ['appmodules'=>'وحدات التطبيق','deptypes'=>'أنواع الأقسام','depstructure'=>'هيكلة الأقسام','users'=>'المستخدمون','permissions'=>'الصلاحيات','accesslog'=>'سجل الدخول','employeesgroup'=>'مجموعات الموظفين','employeestypes'=>'أنواع الموظفين','leavestypes'=>'أنواع الإجازات','empdegrees'=>'الدرجات الوظيفية','empstatus'=>'الحالة الوظيفية','retireplan'=>'خطة التقاعد','fiscalyears'=>'السنة المالية','employeesmain'=>'الموظفون الرئيسيون','leavesbalances'=>'أرصدة الإجازات','empleaves'=>'إجازات الموظفين','reports'=>'التقارير','ess'=>'خدمة الموظف الذاتية'];
        $permId = null;
        foreach ($modules as $m) {
            $pdo->prepare("INSERT INTO `{$P}permissions` (rguid, modulename, modulenamear, isadmin, canadd, canedit, candel, ishradmin, ishruser, addedbyid, addedby, addedbyname, addedbynamear, lastedit, editedbyid, editedby, editedbyname, editedbynamear, omitted) VALUES (?,?,?,1,1,1,1,1,1,1,'System','System','نظام',NOW(),1,'System','System','نظام',0)")->execute([bin2hex(random_bytes(16)), $m, $modAr[$m] ?? $m]);
            $r = $pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
            if ($permId === null) $permId = (int)$r;
        }
        $pdo->prepare("UPDATE `{$P}users` SET permissionid = ? WHERE username = 'admin'")->execute([$permId]);
    });
    msg("Permissions created and linked to SuperAdmin.");

    step('Finalizing...', function() {});
    progress_bar(100, 'Installation Complete!');
    msg("Default SuperAdmin: username <b>admin</b> / password <b>admin123</b>");
    msg("Please change the password and enable OTP for the admin account after first login.");
    msg("<b>Installation complete.</b> You can now remove install.php or keep it for re-setup.");
    
    if (!$isCli) {
        echo '<script>document.getElementById("completionArea").style.display = "block";</script>';
    }

} catch (PDOException $e) {
    progress_bar(100, 'Error!');
    msg("ERROR: " . $e->getMessage());
    if (!$isCli) echo '<br><a href="index.php" class="btn btn-secondary">Go to app</a>';
}

if (!$isCli) {
    echo '</body></html>';
}
?>