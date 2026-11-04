<?php
require_once __DIR__ . '/../../includes/crud_engine.php';
run_table_module([
    'table'        => 'fiscalyears',
    'module'       => 'fiscalyears',
    'title'        => 'Fiscal Years',
    'title_ar'     => 'السنة المالية',
    'order_by'     => 'fstartdate DESC',
    'search_fields'=> ['fstartdate','fenddate'],
    'fields'       => [
        ['name'=>'fstartdate','label'=>'Start Date','label_ar','tipo'=>'','type'=>'date','required'=>true],
        ['name'=>'fenddate','label'=>'End Date','label_ar','tipo'=>'','type'=>'date','required'=>true],
    ],
]);
