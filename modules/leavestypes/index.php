<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

require_once __DIR__ . '/../../includes/crud_engine.php';
run_table_module([
    'table'        => 'leavestypes',
    'module'       => 'leavestypes',
    'title'        => 'Leave Types',
    'title_ar'     => 'أنواع الإجازات',
    'order_by'     => 'leavetypename ASC',
    'search_fields'=> ['leavetypename','leavetypenamear'],
    'extra_select' => ', r1.label AS incwe_label, r1.labelar AS incwe_labelar, r2.label AS incholidays_label, r2.labelar AS incholidays_labelar, r3.label AS transferable_label, r3.labelar AS transferable_labelar',
    'extra_from'   => 'LEFT JOIN ' . table('reqexam') . ' r1 ON r1.id = incwe LEFT JOIN ' . table('reqexam') . ' r2 ON r2.id = incholidays LEFT JOIN ' . table('reqexam') . ' r3 ON r3.id = transferable',
    'list_cols'    => ['id', 'leavetypename', 'leavetypenamear', 'tpel', 'tpey'],
    'list_labels'  => [
        'incwe_label'          => ['en' => 'Include Weekends', 'ar' => 'يشمل عطلات نهاية الأسبوع'],
        'incwe_labelar'        => ['en' => 'Include Weekends (Arabic)', 'ar' => 'يشمل عطلات نهاية الأسبوع (عربي)'],
        'incholidays_label'    => ['en' => 'Include Holidays', 'ar' => 'يشمل العطلات الرسمية'],
        'incholidays_labelar'  => ['en' => 'Include Holidays (Arabic)', 'ar' => 'يشمل العطلات الرسمية (عربي)'],
        'transferable_label'   => ['en' => 'Transferable', 'ar' => 'قابلة للنقل'],
        'transferable_labelar' => ['en' => 'Transferable (Arabic)', 'ar' => 'قابلة للنقل (عربي)'],
    ],
    'fields'       => [
        ['name'=>'leavetypename','label'=>'Leave Type Name','label_ar'=>'اسم نوع الإجازة','type'=>'text','required'=>true],
        ['name'=>'leavetypenamear','label'=>'Leave Type Name (Arabic)','label_ar'=>'اسم نوع الإجازة بالعربية','type'=>'text','required'=>true],
        ['name'=>'tpel','label'=>'Times per Employee Life (0=unlimited)','label_ar'=>'عدد المرات مدى الحياة (0=غير محدود)','type'=>'number','required'=>true],
        ['name'=>'tpey','label'=>'Times per Employee per Year (0=unlimited)','label_ar'=>'عدد المرات في السنة (0=غير محدود)','type'=>'number','required'=>true],
        ['name'=>'incwe','label'=>'Include Weekends','label_ar'=>'يشمل عطلات نهاية الأسبوع','type'=>'select','options'=>['table'=>'reqexam','value'=>'id','label'=>'label','label_ar'=>'labelar','where'=>[]], 'required'=>true],
        ['name'=>'incholidays','label'=>'Include Holidays','label_ar'=>'يشمل العطلات الرسمية','type'=>'select','options'=>['table'=>'reqexam','value'=>'id','label'=>'label','label_ar'=>'labelar','where'=>[]], 'required'=>true],
        ['name'=>'transferable','label'=>'Transferable to Next Year','label_ar'=>'قابلة للنقل للسنة التالية','type'=>'select','options'=>['table'=>'reqexam','value'=>'id','label'=>'label','label_ar'=>'labelar','where'=>[]], 'required'=>true],
        ['name'=>'incwe_label','label'=>'Include Weekends Label','label_ar'=>'','type'=>'hidden'],
        ['name'=>'incwe_labelar','label'=>'Include Weekends Label (Arabic)','label_ar'=>'','type'=>'hidden'],
        ['name'=>'incholidays_label','label'=>'Include Holidays Label','label_ar'=>'','type'=>'hidden'],
        ['name'=>'incholidays_labelar','label'=>'Include Holidays Label (Arabic)','label_ar'=>'','type'=>'hidden'],
        ['name'=>'transferable_label','label'=>'Transferable Label','label_ar'=>'','type'=>'hidden'],
        ['name'=>'transferable_labelar','label'=>'Transferable Label (Arabic)','label_ar'=>'','type'=>'hidden'],
    ],
]);
