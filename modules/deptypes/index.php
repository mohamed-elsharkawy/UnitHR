<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

require_once __DIR__ . '/../../includes/crud_engine.php';

function module_deptypes_before_save(&$data, $isAdd, $id = 0, $old = []) {
    if (!empty($data['deptypeparentid'])) {
        $parent = fetchRow("SELECT deptypename, deptypenamear FROM " . table('deptypes') . " WHERE id = ? LIMIT 1", [$data['deptypeparentid']]);
        if ($parent) {
            $data['deptypeparentname'] = $parent['deptypename'];
            $data['deptypeparentnamear'] = $parent['deptypenamear'];
        }
    } else {
        $data['deptypeparentname'] = '';
        $data['deptypeparentnamear'] = '';
    }
}

echo '<script>
function updateDeptParentNames(select) {
    var parentId = select.value;
    if (!parentId) {
        document.querySelector("input[name=\"deptypeparentname\"]").value = "";
        document.querySelector("input[name=\"deptypeparentnamear\"]").value = "";
        return;
    }
    fetch("?module=deptypes&a=getparent&parentid=" + parentId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.deptypename) document.querySelector("input[name=\"deptypeparentname\"]").value = data.deptypename;
            if (data.deptypenamear) document.querySelector("input[name=\"deptypeparentnamear\"]").value = data.deptypenamear;
        });
}
</script>';

if (isset($_GET['a']) && $_GET['a'] === 'getparent' && isset($_GET['parentid'])) {
    $parent = fetchRow("SELECT deptypename, deptypenamear FROM " . table('deptypes') . " WHERE id = ? LIMIT 1", [(int)$_GET['parentid']]);
    header('Content-Type: application/json');
    echo json_encode($parent ?: ['deptypename' => '', 'deptypenamear' => '']);
    exit;
}

run_table_module([
    'table'        => 'deptypes',
    'module'       => 'deptypes',
    'pk'           => 'id',
    'title'        => 'Department Types',
    'title_ar'     => 'أنواع الأقسام',
    'order_by'     => 'deptypename ASC',
    'search_fields'=> ['deptypename','deptypenamear'],
    'fields'       => [
        ['name'=>'deptypename','label'=>'Department Type Name','label_ar'=>'اسم نوع القسم','type'=>'text','required'=>true],
        ['name'=>'deptypenamear','label'=>'Department Type Name (Arabic)','label_ar'=>'اسم نوع القسم بالعربية','type'=>'text','required'=>true],
        ['name'=>'deptypeparentid','label'=>'Parent Type','label_ar'=>'النوع الأصل','type'=>'select','options'=>['table'=>'deptypes','value'=>'id','label'=>'deptypename','label_ar'=>'deptypenamear','where'=>['omitted'=>0]], 'allow_empty'=>true,'empty_label'=>'-- None --', 'extra'=>'onchange="updateDeptParentNames(this)"'],
        ['name'=>'deptypeparentname','label'=>'Parent Type Name','label_ar'=>'اسم النوع الأصل','type'=>'hidden'],
        ['name'=>'deptypeparentnamear','label'=>'Parent Type Name (Arabic)','label_ar'=>'اسم النوع الأصل بالعربية','type'=>'hidden'],
    ],
]);
