<?php
require_once __DIR__ . '/../../includes/crud_engine.php';

function module_empdegrees_before_save(&$data, $isAdd, $id = 0, $old = []) {
    // Populate Arabic/English counterparts for emptypename
    if (!empty($data['emptypename'])) {
        $lt = fetchRow("SELECT emptypename, emptypenamear FROM " . table('employeestypes') . " WHERE emptypename = ? OR emptypenamear = ? LIMIT 1", [$data['emptypename'], $data['emptypename']]);
        if ($lt) {
            $data['emptypename'] = $lt['emptypename'];
            $data['emptypenamear'] = $lt['emptypenamear'];
        }
    }
    // Populate Arabic/English counterparts for empgroupname
    if (!empty($data['empgroupname'])) {
        $grp = fetchRow("SELECT empgroupname, empgroupnamear FROM " . table('employeesgroup') . " WHERE empgroupname = ? OR empgroupnamear = ? LIMIT 1", [$data['empgroupname'], $data['empgroupname']]);
        if ($grp) {
            $data['empgroupname'] = $grp['empgroupname'];
            $data['empgroupnamear'] = $grp['empgroupnamear'];
        }
    }
}
run_table_module([
    'table'        => 'empdegrees',
    'module'       => 'empdegrees',
    'title'        => 'Employee Degrees',
    'title_ar'     => 'الدرجات الوظيفية',
    'order_by'     => 'degreename ASC',
    'search_fields'=> ['degreename','degreenamear'],
    'fields'       => [
        ['name'=>'degreename','label'=>'Degree Name','label_ar'=>'اسم الدرجة','type'=>'text','required'=>true],
        ['name'=>'degreenamear','label'=>'Degree Name (Arabic)','label_ar'=>'اسم الدرجة بالعربية','type'=>'text','required'=>true],
        ['name'=>'interimperiod','label'=>'Interim Period','label_ar'=>'فترة الانتقال','type'=>'number','required'=>true],
        ['name'=>'reqexam','label'=>'Require Exam','label_ar'=>'هل يتطلب امتحان','type'=>'select','options'=>['table'=>'reqexam','value'=>'id','label'=>'label','label_ar'=>'labelar','where'=>[]], 'required'=>true],
        ['name'=>'emptypeid','label'=>'Employee Type','label_ar'=>'نوع الموظف','tipo'=>'','type'=>'select','options'=>['table'=>'employeestypes','value'=>'id','label'=>'emptypename','label_ar'=>'emptypenamear','where'=>['omitted'=>0]], 'allow_empty'=>true],
        ['name'=>'emptypename','label'=>'Employee Type Name','label_ar'=>'اسم نوع الموظف','type'=>'select','options'=>['table'=>'employeestypes','value'=>'emptypename','label'=>'emptypename','label_ar'=>'emptypenamear','where'=>['omitted'=>0]], 'allow_empty'=>true],
        ['name'=>'employeesgroupid','label'=>'Employee Group','label_ar'=>'مجموعة الموظفين','tipo'=>'','type'=>'select','options'=>['table'=>'employeesgroup','value'=>'id','label'=>'empgroupname','label_ar'=>'empgroupnamear','where'=>['omitted'=>0]], 'allow_empty'=>true],
        ['name'=>'empgroupname','label'=>'Employee Group Name','label_ar'=>'اسم مجموعة الموظفين','type'=>'select','options'=>['table'=>'employeesgroup','value'=>'empgroupname','label'=>'empgroupname','label_ar'=>'empgroupnamear','where'=>['omitted'=>0]], 'allow_empty'=>true],
    ],
]);
