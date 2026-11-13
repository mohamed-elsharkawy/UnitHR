<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

require_once __DIR__ . '/../../includes/crud_engine.php';
$boolOpts = ['table'=>'reqexam','value'=>'id','label'=>'label','label_ar'=>'labelar','where'=>[]];
run_table_module([
    'table'        => 'permissions',
    'module'       => 'permissions',
    'title'        => 'Permissions',
    'title_ar'     => 'الصلاحيات',
    'order_by'     => 'modulename ASC',
    'search_fields'=> ['modulename','modulenamear'],
    'fields'       => [
        ['name'=>'modulename','label'=>'Module Name','label_ar'=>'اسم الوحدة','type'=>'text','required'=>true],
        ['name'=>'modulenamear','label'=>'Module Name (Arabic)','label_ar'=>'اسم الوحدة بالعربية','type'=>'text','required'=>true],
        ['name'=>'isadmin','label'=>'Is Admin','label_ar'=>'مدير','type'=>'select','options'=>$boolOpts,'required'=>true],
        ['name'=>'canadd','label'=>'Can Add','label_ar'=>'إضافة','type'=>'select','options'=>$boolOpts,'required'=>true],
        ['name'=>'canedit','label'=>'Can Edit','label_ar'=>'تعديل','type'=>'select','options'=>$boolOpts,'required'=>true],
        ['name'=>'candel','label'=>'Can Omit (Delete)','label_ar'=>'حذف','type'=>'select','options'=>$boolOpts,'required'=>true],
        ['name'=>'ishradmin','label'=>'Is HR Admin','label_ar'=>'مدير موارد بشرية','type'=>'select','options'=>$boolOpts,'required'=>true],
        ['name'=>'ishruser','label'=>'Is HR Employee','label_ar'=>'موظف موارد بشرية','type'=>'select','options'=>$boolOpts,'required'=>true],
    ],
]);
