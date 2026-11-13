<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

require_once __DIR__ . '/../../includes/crud_engine.php';
run_table_module([
    'table'        => 'empstatus',
    'module'       => 'empstatus',
    'title'        => 'Employee Status',
    'title_ar'     => 'الحالة الوظيفية',
    'order_by'     => 'empstatusname ASC',
    'search_fields'=> ['empstatusname','empstatusnamear'],
    'extra_select' => ', re.label AS ispayable_label, re.labelar AS ispayable_labelar',
    'extra_from'   => 'LEFT JOIN ' . table('reqexam') . ' re ON re.id = ispayable',
    'list_cols'    => ['id', 'empstatusname', 'empstatusnamear'],
    'list_labels'  => [
        'ispayable_label'   => ['en' => 'Payable on Payroll', 'ar' => 'قابلة للدفع'],
        'ispayable_labelar' => ['en' => 'Payable (Arabic)', 'ar' => 'قابلة للدفع (عربي)'],
    ],
    'fields'       => [
        ['name'=>'empstatusname','label'=>'Employee Status','label_ar'=>'الحالة الوظيفية','type'=>'text','required'=>true],
        ['name'=>'empstatusnamear','label'=>'Employee Status (Arabic)','label_ar'=>'الحالة الوظيفية بالعربية','type'=>'text','required'=>true],
        ['name'=>'ispayable','label'=>'Payable on Payroll','label_ar'=>' قابلة للدفع على الكشوف الراتبية','type'=>'select','options'=>['table'=>'reqexam','value'=>'id','label'=>'label','label_ar'=>'labelar','where'=>[]], 'required'=>true],
        ['name'=>'ispayable_label','label'=>'Payable Label','label_ar'=>'','type'=>'hidden'],
        ['name'=>'ispayable_labelar','label'=>'Payable Label (Arabic)','label_ar'=>'','type'=>'hidden'],
    ],
]);
