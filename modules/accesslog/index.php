<?php
/**
 * AccessLog - read-only list of login attempts.
 */
require_login();
require_once __DIR__ . '/../../includes/helpers.php';
$module = 'accesslog';
if (!user_can($module, 'view')) {
    require_permission($module, 'view');
}
$isAr = lang() === 'ar';
$search = trim($_GET['search'] ?? '');
$where = "omitted = 0";
$params = [];
if ($search !== '') {
    $norm = arabic_normalize($search);
    $where .= " AND (username LIKE ? OR ip LIKE ? OR reason LIKE ?)";
    $params = ['%'.$search.'%', '%'.$search.'%', '%'.$search.'%'];
}
$rows = fetchAll("SELECT * FROM " . table('accesslog') . " WHERE $where ORDER BY login_time DESC, id DESC LIMIT 200", $params);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-journal-text me-2"></i><?= $isAr ? 'سجل الدخول' : 'Access Log' ?></h2>
    <a href="index.php?module=<?= $module ?>&a=export&fmt=csv" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-csv me-1"></i>CSV</a>
    <a href="index.php?module=<?= $module ?>&a=export&fmt=xlsx" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>XLSX</a>
</div>
<form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="module" value="accesslog">
    <div class="col-md-4"><input type="search" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Search (Arabic/English)..."></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
    <div class="col-md-2"><a href="index.php?module=accesslog" class="btn btn-outline-secondary w-100">Reset</a></div>
</form>
<?php if (count($rows) === 0): ?>
<div class="alert alert-info">No access records found.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-striped table-hover align-middle table-sm">
<thead class="table-dark"><tr>
<th>#</th><th><?= $isAr?'المستخدم':'User' ?></th><th><?= $isAr?'العنوان':'IP' ?></th><th><?= $isAr?'وقت الدخول':'Login Time' ?></th><th><?= $isAr?'ناجح':'Success' ?></th><th><?= $isAr?'السبب':'Reason' ?></th>
</tr></thead>
<tbody>
<?php foreach ($rows as $i => $r): ?>
<tr>
<td><?= $i+1 ?></td>
<td><?= htmlspecialchars($r['username'] ?? '') ?></td>
<td><?= htmlspecialchars($r['ip'] ?? '') ?></td>
<td><?= htmlspecialchars($r['login_time'] ?? '') ?></td>
<td><?= !empty($r['success']) ? ($isAr?'نعم':'Yes') : ($isAr?'لا':'No') ?></td>
<td><?= htmlspecialchars($r['reason'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
