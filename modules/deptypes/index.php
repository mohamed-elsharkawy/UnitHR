<?php
require_once __DIR__ . '/../../includes/crud_engine.php';
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
        ['name'=>'deptypeparentid','label'=>'Parent Type','label_ar'=>'النوع الأصل','type'=>'select','options'=>['table'=>'deptypes','value'=>'id','label'=>'deptypename','label_ar'=>'deptypenamear','where'=>['omitted'=>0]], 'allow_empty'=>true,'empty_label'=>'-- None --'],
        ['name'=>'deptypeparentname','label'=>'Parent Type Name','label_ar'=>'اسم النوع الأصل','type'=>'text'],
        ['name'=>'deptypeparentnamear','label'=>'Parent Type Name (Arabic)','label_ar'=>'اسم النوع الأصل بالعربية','type'=>'text'],
    ],
]);
