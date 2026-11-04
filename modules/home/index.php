<?php
// UnitHR - Dashboard (content only; header/footer wrapped by index.php)
require_login();
$stats = [];
if (db_available()) {
    $count = function ($tbl) {
        $row = fetchRow("SELECT COUNT(*) c FROM " . table($tbl) . " WHERE omitted=0");
        return (int)(($row && isset($row['c'])) ? $row['c'] : 0);
    };
    $stats['departments'] = $count('depstructure');
    $stats['employees']   = $count('employeesmain');
    $stats['leaves']      = $count('empleaves');
    $stats['users']       = $count('users');
    $stats['leavetypes']  = $count('leavestypes');
} else {
    $stats = ['departments'=>0,'employees'=>0,'leaves'=>0,'users'=>0,'leavetypes'=>0];
}
$isAr = lang() === 'ar';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-speedometer2 me-2"></i><?= $isAr ? 'لوحة التحكم' : 'Dashboard' ?></h2>
</div>
<div class="row">
<?php foreach ([
    ['label'=>$isAr?'الأقسام':'Departments','value'=>$stats['departments'],'color'=>'info'],
    ['label'=>$isAr?'الموظفون':'Employees','value'=>$stats['employees'],'color'=>'success'],
    ['label'=>$isAr?'إجازات':'Leaves','value'=>$stats['leaves'],'color'=>'warning'],
    ['label'=>$isAr?'المستخدمون':'Users','value'=>$stats['users'],'color'=>'primary'],
    ['label'=>$isAr?'أنواع الإجازات':'Leave Types','value'=>$stats['leavetypes'],'color'=>'secondary'],
] as $card): ?>
<div class="col-md-3 mb-3">
    <div class="card text-white bg-<?= htmlspecialchars($card['color']) ?>">
        <div class="card-body">
            <h5 class="card-title"><?= htmlspecialchars($card['label']) ?></h5>
            <p class="display-4 fw-bold"><?= $card['value'] ?></p>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<!--
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><?= $isAr ? 'الوحدات المتاحة' : 'Available Modules' ?></div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                <?php foreach (get_routes() as $r): ?>
                <li class="list-group-item"><a href="<?= htmlspecialchars($r['url']) ?>"><?= htmlspecialchars($r['name']) ?></a></li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><?= $isAr ? 'ملاحظة' : 'Note' ?></div>
            <div class="card-body">
                <p><?= $isAr ? 'النظام يدعم العربية والإنجليزية بشكل كامل. السجلات لا تُحذف بل تُعدّ (omitted) وتُحفظ نسخة منها في سجل التعديلات قبل كل تحديث.' : 'The system fully supports Arabic and English. Records are never deleted; they are soft-omitted and versioned in the audit log before every update.' ?></p>
            </div>
        </div>
    </div>
</div>
-->