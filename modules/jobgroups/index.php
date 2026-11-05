<?php
require_once __DIR__ . '/../../includes/crud_engine.php';
run_table_module([
    'table'        => 'jobgroups',
    'module'       => 'jobgroups',
    'title'        => 'Job Groups',
    'title_ar'     => 'مجموعات الوظائف',
    'order_by'     => 'jobgroupname ASC',
    'search_fields'=> ['jobgroupname','jobgroupnamear','jobgroupcode'],
    'fields'       => [
        ['name'=>'jobgroupname','label'=>'Job Group Name','label_ar'=>'اسم مجموعة الوظائف','type'=>'text','required'=>true],
        ['name'=>'jobgroupnamear','label'=>'Job Group Name (Arabic)','label_ar'=>'اسم مجموعة الوظائف بالعربية','type'=>'text','required'=>true],
        ['name'=>'jobgroupcode','label'=>'Group Code','label_ar'=>'رمز المجموعة','type'=>'text'],
        ['name'=>'description','label'=>'Description','label_ar'=>'الوصف','type'=>'textarea'],
        ['name'=>'descriptionar','label'=>'Description (Arabic)','label_ar'=>'الوصف بالعربية','type'=>'textarea'],
    ],
]);
