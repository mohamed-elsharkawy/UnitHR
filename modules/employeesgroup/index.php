<?php
require_once __DIR__ . '/../../includes/crud_engine.php';
run_table_module([
    'table'        => 'employeesgroup',
    'module'       => 'employeesgroup',
    'title'        => 'Employee Groups',
    'title_ar'     => 'مجموعات الموظفين',
    'order_by'     => 'empgroupname ASC',
    'search_fields'=> ['empgroupname','empgroupnamear'],
    'fields'       => [
        ['name'=>'empgroupname','label'=>'Employee Group Name','label_ar'=>'اسم مجموعة الموظفين','type'=>'text','required'=>true],
        ['name'=>'empgroupnamear','label'=>'Employee Group Name (Arabic)','label_ar'=>'اسم مجموعة الموظفين بالعربية','type'=>'text','required'=>true],
    ],
]);
