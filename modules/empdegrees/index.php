<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

require_once __DIR__ . '/../../includes/crud_engine.php';

function module_empdegrees_before_save(&$data, $isAdd, $id = 0, $old = []) {
    // Populate next degree name
    if (!empty($data['nextdegreeid'])) {
        $nd = fetchRow("SELECT degreename, degreenamear FROM " . table('empdegrees') . " WHERE id = ? LIMIT 1", [$data['nextdegreeid']]);
        if ($nd) {
            $data['nextdegreename'] = $nd['degreename'];
            $data['nextdegreenamear'] = $nd['degreenamear'];
        }
    } else {
        $data['nextdegreename'] = null;
        $data['nextdegreenamear'] = null;
    }
    // Ensure employeesgroup_id is set
    if (empty($data['employeesgroup_id'])) {
        $data['employeesgroup_id'] = 1; // Default to Administrative Staff
    }
    // Auto-populate employeesgroup and employeesgroupar from employeesgroup table
    if (!empty($data['employeesgroup_id'])) {
        $eg = fetchRow("SELECT empgroupname, empgroupnamear FROM " . table('employeesgroup') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$data['employeesgroup_id']]);
        if ($eg) {
            $data['employeesgroup'] = $eg['empgroupname'];
            $data['employeesgroupar'] = $eg['empgroupnamear'];
        }
    }
}

// Determine language - use GET param first (for initial request), then session
$isAr = (isset($_GET['lang']) && $_GET['lang'] === 'ar') || (isset($_SESSION[UHR_SESSION_KEY]['lang']) && $_SESSION[UHR_SESSION_KEY]['lang'] === 'ar');

// Set list_cols based on current language - show only the appropriate language column
$list_cols = [
    'id', 'degreename', 'degreenamear', 'interimperiod', 'nextdegreename', 'nextdegreenamear'
];
if ($isAr) {
    array_unshift($list_cols, 'employeesgroupar');
    $list_labels = [
        'employeesgroupar'  => ['en' => 'Employee Group', 'ar' => 'مجموعة الموظفين'],
        'nextdegreename'    => ['en' => 'Next Degree', 'ar' => 'الدرجة التالية'],
        'reqexam_label'     => ['en' => 'Require Exam', 'ar' => 'يتطلب اختبار'],
        'reqexam_labelar'   => ['en' => 'Require Exam (Arabic)', 'ar' => 'يتطلب اختبار (عربي)'],
    ];
} else {
    array_unshift($list_cols, 'employeesgroup');
    $list_labels = [
        'employeesgroup'    => ['en' => 'Employee Group', 'ar' => 'مجموعة الموظفين'],
        'nextdegreename'    => ['en' => 'Next Degree', 'ar' => 'الدرجة التالية'],
        'reqexam_label'     => ['en' => 'Require Exam', 'ar' => 'يتطلب اختبار'],
        'reqexam_labelar'   => ['en' => 'Require Exam (Arabic)', 'ar' => 'يتطلب اختبار (عربي)'],
    ];
}

run_table_module([
    'table'        => 'empdegrees',
    'module'       => 'empdegrees',
    'title'        => 'Employee Degrees',
    'title_ar'     => 'الدرجات الوظيفية',
    'order_by'     => "employeesgroup_id ASC, CASE degreename WHEN 'President of INP' THEN 1 WHEN 'Vice President of the Institute' THEN 2 WHEN 'Secretary-General' THEN 3 WHEN 'Director' THEN 4 WHEN 'Manager' THEN 5 WHEN 'First Degree A' THEN 6 WHEN 'First Degree B' THEN 7 WHEN 'Second Degree A' THEN 8 WHEN 'Second Degree B' THEN 9 WHEN 'Third Degree A' THEN 10 WHEN 'Third Degree B' THEN 11 WHEN 'Third Degree C' THEN 12 WHEN 'Fourth Degree' THEN 13 WHEN 'Fifth Degree' THEN 14 WHEN 'Sixth Degree' THEN 15 ELSE 99 END ASC",
    'search_fields'=> ['degreename','degreenamear','empgroupname','empgroupnamear'],
    'extra_select' => ', re.label AS reqexam_label, re.labelar AS reqexam_labelar',
    'extra_from'   => 'LEFT JOIN ' . table('reqexam') . ' re ON re.id = reqexam',
    'list_cols'    => $list_cols,
    'list_labels'  => $list_labels,
    'fields'       => [
        ['name'=>'employeesgroup_id','label'=>'Employee Group','label_ar'=>'مجموعة الموظفين','type'=>'select','options'=>['table'=>'employeesgroup','value'=>'id','label'=>'empgroupname','label_ar'=>'empgroupnamear','where'=>['omitted'=>0]], 'required'=>true],
        ['name'=>'employeesgroup','label'=>'Employee Group','label_ar'=>'مجموعة الموظفين','type'=>'hidden'],
        ['name'=>'employeesgroupar','label'=>'Employee Group','label_ar'=>'مجموعة الموظفين','type'=>'hidden'],
        ['name'=>'degreename','label'=>'Degree Name','label_ar'=>'اسم الدرجة','type'=>'text','required'=>true],
        ['name'=>'degreenamear','label'=>'Degree Name (Arabic)','label_ar'=>'اسم الدرجة بالعربية','type'=>'text','required'=>true],
        ['name'=>'interimperiod','label'=>'Interim Period (Years)','label_ar'=>'فترة الانتقال (سنوات)','type'=>'number','required'=>true],
        ['name'=>'nextdegreeid','label'=>'Next Degree','label_ar'=>'الدرجة التالية','type'=>'select','options'=>['table'=>'empdegrees','value'=>'id','label'=>'degreename','label_ar'=>'degreenamear','where'=>['omitted'=>0]], 'allow_empty'=>true],
        ['name'=>'nextdegreename','label'=>'Next Degree Name','label_ar'=>'اسم الدرجة التالية','type'=>'hidden'],
        ['name'=>'nextdegreenamear','label'=>'Next Degree Name (Arabic)','label_ar'=>'اسم الدرجة التالية بالعربية','type'=>'hidden'],
        ['name'=>'reqexam','label'=>'Require Exam','label_ar'=>'هل يتطلب امتحان','type'=>'select','options'=>['table'=>'reqexam','value'=>'id','label'=>'label','label_ar'=>'labelar','where'=>[]], 'required'=>true],
        ['name'=>'reqexam_label','label'=>'Require Exam Label','label_ar'=>'','type'=>'hidden'],
        ['name'=>'reqexam_labelar','label'=>'Require Exam Label (Arabic)','label_ar'=>'','type'=>'hidden'],
    ],
]);
