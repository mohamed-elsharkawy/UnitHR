<?php
require_once __DIR__ . '/../../includes/crud_engine.php';
run_table_module([
    'table'        => 'empstatus',
    'module'       => 'empstatus',
    'title'        => 'Employee Status',
    'title_ar'     => 'الحالة الوظيفية',
    'order_by'     => 'empstatusname ASC',
    'search_fields'=> ['empstatusname','empstatusnamear'],
    'fields'       => [
        ['name'=>'empstatusname','label'=>'Employee Status','label_ar'=>'الحالة الوظيفية','type'=>'text','required'=>true],
        ['name'=>'empstatusnamear','label'=>'Employee Status (Arabic)','label_ar'=>'الحالة الوظيفية بالعربية','type'=>'text','required'=>true],
        ['name'=>'ispayable','label'=>'Payable on Payroll','label_ar'=>' قابلة للدفع على الكشوف الراتبية','type'=>'select','options'=>['table'=>'reqexam','value'=>'id','label'=>'label','label_ar'=>'labelar','where'=>[]], 'required'=>true],
    ],
]);
