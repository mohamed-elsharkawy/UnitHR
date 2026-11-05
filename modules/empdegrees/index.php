<?php
require_once __DIR__ . '/../../includes/crud_engine.php';

function module_empdegrees_before_save(&$data, $isAdd, $id = 0, $old = []) {
    // Populate next degree name
    if (!empty($data['nextdegreeid'])) {
        $nd = fetchRow("SELECT degreename, degreenamear FROM " . table('empdegrees') . " WHERE id = ? LIMIT 1", [$data['nextdegreeid']]);
        if ($nd) {
            $data['nextdegreename'] = $nd['degreename'];
            $data['nextdegreenamear'] = $nd['degreenamear'];
        }
    } else {
        $data['nextdegreename'] = null;
        $data['nextdegreenamear'] = null;
    }
}
run_table_module([
    'table'        => 'empdegrees',
    'module'       => 'empdegrees',
    'title'        => 'Employee Degrees',
    'title_ar'     => 'الدرجات الوظيفية',
    'order_by'     => 'degreename ASC',
    'search_fields'=> ['degreename','degreenamear'],
    'extra_select' => ', re.label AS reqexam_label, re.labelar AS reqexam_labelar',
    'extra_from'   => 'LEFT JOIN ' . table('reqexam') . ' re ON re.id = reqexam',
    'list_cols'    => ['id', 'degreename', 'degreenamear', 'interimperiod', 'nextdegreename', 'nextdegreenamear'],
    'list_labels'  => [
        'nextdegreename'    => ['en' => 'Next Degree', 'ar' => 'الدرجة التالية'],
        'reqexam_label'     => ['en' => 'Require Exam', 'ar' => 'يتطلب اختبار'],
        'reqexam_labelar'   => ['en' => 'Require Exam (Arabic)', 'ar' => 'يتطلب اختبار (عربي)'],
    ],
    'fields'       => [
        ['name'=>'degreename','label'=>'Degree Name','label_ar'=>'اسم الدرجة','type'=>'text','required'=>true],
        ['name'=>'degreenamear','label'=>'Degree Name (Arabic)','label_ar'=>'اسم الدرجة بالعربية','type'=>'text','required'=>true],
        ['name'=>'interimperiod','label'=>'Interim Period (Years)','label_ar'=>'فترة الانتقال (سنوات)','type'=>'number','required'=>true],
        ['name'=>'nextdegreeid','label'=>'Next Degree','label_ar'=>'الدرجة التالية','type'=>'select','options'=>['table'=>'empdegrees','value'=>'id','label'=>'degreename','label_ar'=>'degreenamear','where'=>['omitted'=>0]], 'allow_empty'=>true],
        ['name'=>'nextdegreename','label'=>'Next Degree Name','label_ar'=>'اسم الدرجة التالية','type'=>'hidden'],
        ['name'=>'nextdegreenamear','label'=>'Next Degree Name (Arabic)','label_ar'=>'اسم الدرجة التالية بالعربية','type'=>'hidden'],
        ['name'=>'reqexam','label'=>'Require Exam','label_ar'=>'هل يتطلب امتحان','type'=>'select','options'=>['table'=>'reqexam','value'=>'id','label'=>'label','label_ar'=>'labelar','where'=>[]], 'required'=>true],
        ['name'=>'reqexam_label','label'=>'Require Exam Label','label_ar'=>'','type'=>'hidden'],
        ['name'=>'reqexam_labelar','label'=>'Require Exam Label (Arabic)','label_ar'=>'','type'=>'hidden'],

    ],
]);
