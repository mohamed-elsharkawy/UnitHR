<?php
require_once __DIR__ . '/../../includes/crud_engine.php';
run_table_module([
    'table'        => 'employeestypes',
    'module'       => 'employeestypes',
    'title'        => 'Employee Types',
    'title_ar'     => 'أنواع الموظفين',
    'order_by'     => 'emptypename ASC',
    'search_fields'=> ['emptypename','emptypenamear'],
    'fields'       => [
        ['name'=>'emptypename','label'=>'Employee Type Name','label_ar'=>'اسم نوع الموظف','type'=>'text','required'=>true],
        ['name'=>'emptypenamear','label'=>'Employee Type Name (Arabic)','label_ar'=>'اسم نوع الموظف بالعربية','type'=>'text','required'=>true],
    ],
]);
