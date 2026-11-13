<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

require_once __DIR__ . '/../../includes/crud_engine.php';
run_table_module([
    'table'        => 'retireplan',
    'module'       => 'retireplan',
    'title'        => 'Retirement Plan',
    'title_ar'     => 'خطة التقاعد',
    'order_by'     => 'yearofbirth ASC',
    'search_fields'=> ['yearofbirth','serviceyears'],
    'fields'       => [
        ['name'=>'yearofbirth','label'=>'Year of Birth','label_ar','tipo'=>'','type'=>'number','required'=>true],
        ['name'=>'serviceyears','label'=>'Service Years to Retire','label_ar','tipo'=>'','type'=>'number','required'=>true],
    ],
]);
