<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

require_once __DIR__ . '/../../includes/crud_engine.php';
run_table_module([
    'table'        => 'appmodules',
    'module'       => 'appmodules',
    'title'        => 'Application Modules',
    'title_ar'     => 'وحدات التطبيق',
    'order_by'     => 'menu_order ASC, modulename ASC',
    'search_fields'=> ['modulename','modulenamear','modulefolder'],
    'fields'       => [
        ['name'=>'modulename','label'=>'Module Name','label_ar'=>'اسم الوحدة','type'=>'text','required'=>true],
        ['name'=>'modulenamear','label'=>'Module Name (Arabic)','label_ar'=>'اسم الوحدة بالعربية','type'=>'text','required'=>true],
        ['name'=>'modulefolder','label'=>'Module Folder','label_ar'=>'مجلد الوحدة','type'=>'text','required'=>true],
        ['name'=>'menu_order','label'=>'Menu Order','label_ar'=>'ترتيب القائمة','type'=>'number','required'=>true],
        ['name'=>'description','label'=>'Description','label_ar'=>'الوصف','type'=>'textarea'],
    ],
]);
