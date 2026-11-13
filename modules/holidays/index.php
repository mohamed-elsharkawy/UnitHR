<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

require_once __DIR__ . '/../../includes/crud_engine.php';
run_table_module([
    'table'         => 'holidays',
    'module'        => 'holidays',
    'title'         => 'Official Holidays',
    'title_ar'      => 'العطلات الرسمية',
    'order_by'      => 'holidaydate DESC',
    'search_fields' => ['holidayname', 'holidaynamear', 'holidaydate'],
    'fields'        => [
        ['name' => 'holidayname',   'label' => 'Holiday Name',          'label_ar' => 'اسم العطلة',            'type' => 'text',   'required' => true],
        ['name' => 'holidaynamear', 'label' => 'Holiday Name (Arabic)', 'label_ar' => 'اسم العطلة بالعربية',   'type' => 'text',   'required' => true],
        ['name' => 'holidaydate',   'label' => 'Holiday Date',          'label_ar' => 'تاريخ العطلة',          'type' => 'date',   'required' => true],
        ['name' => 'isrecurring',   'label' => 'Recurring Yearly',      'label_ar' => 'متكرر سنوياً',           'type' => 'select', 'required' => true,
            'options' => ['table' => 'reqexam', 'value' => 'id', 'label' => 'label', 'label_ar' => 'labelar', 'where' => []]],
    ],
]);
