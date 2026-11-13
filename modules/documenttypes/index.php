<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

require_once __DIR__ . '/../../includes/crud_engine.php';

run_table_module([
    'table'        => 'documenttypes',
    'module'       => 'documenttypes',
    'title'        => 'Document Types',
    'title_ar'     => 'نوع المستند',
    'order_by'     => 'doctypename ASC',
    'search_fields'=> ['doctypename','doctypenamear'],
    'fields'       => [
        ['name'=>'doctypename','label'=>'Document Type','label_ar'=>'نوع المستند','type'=>'text','required'=>true],
        ['name'=>'doctypenamear','label'=>'Document Type (Arabic)','label_ar'=>'نوع المستند (عربي)','type'=>'text','required'=>true],
    ],
]);