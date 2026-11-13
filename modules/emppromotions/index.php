<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

require_once __DIR__ . '/../../includes/crud_engine.php';

function module_emppromotions_before_save(&$data, $isAdd, $id = 0, $old = []) {
    // Populate employee names
    if (!empty($data['empid'])) {
        $emp = fetchRow("SELECT empfullname, empfullnamear FROM " . table('employeesmain') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$data['empid']]);
        if ($emp) {
            $data['empfullname'] = $emp['empfullname'];
            $data['empfullnamear'] = $emp['empfullnamear'];
        }
    }
    // Populate degree names
    if (!empty($data['degreeid'])) {
        $deg = fetchRow("SELECT degreename, degreenamear FROM " . table('empdegrees') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$data['degreeid']]);
        if ($deg) {
            $data['degreename'] = $deg['degreename'];
            $data['degreenamear'] = $deg['degreenamear'];
        }
    }
    // Default seniority date to degree acquired date if not provided
    if (empty($data['senioritydate']) && !empty($data['degreeacquireddate'])) {
        $data['senioritydate'] = $data['degreeacquireddate'];
    }

    // Handle Decision of Promotion file upload (save to /uploads)
    $allowed = ['tiff','tif','pdf','jpg','jpeg','jpe','png'];
    if (isset($_FILES['decisionfile']) && is_array($_FILES['decisionfile'])
        && ($_FILES['decisionfile']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $origName = (string)($_FILES['decisionfile']['name'] ?? '');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed, true)) {
            $uploadDir = __DIR__ . '/../../uploads';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            if (is_dir($uploadDir) && is_writable($uploadDir)) {
                $newName = 'decision_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $target = $uploadDir . DIRECTORY_SEPARATOR . $newName;
                if (move_uploaded_file($_FILES['decisionfile']['tmp_name'], $target)) {
                    $data['decisionfilepath'] = 'uploads/' . $newName;
                }
            }
        }
    }
    // If no new upload, keep existing path (already in $data from hidden field)
    if (empty($data['decisionfilepath'])) {
        $data['decisionfilepath'] = null;
    }
}

$isAr = lang() === 'ar';

$list_cols = $isAr
    ? ['empfullnamear', 'degreenamear', 'promotiondate', 'degreeacquireddate', 'decisionnumber', 'decisiondate', 'senioritydate']
    : ['empfullname', 'degreename', 'promotiondate', 'degreeacquireddate', 'decisionnumber', 'decisiondate', 'senioritydate'];

$list_labels = $isAr ? [
    'empfullnamear'      => ['en' => 'Employee', 'ar' => 'الموظف'],
    'degreenamear'       => ['en' => 'Degree Acquired', 'ar' => 'الدرجة المكتسبة'],
    'promotiondate'      => ['en' => 'Promotion Date', 'ar' => 'تاريخ الترقية'],
    'degreeacquireddate' => ['en' => 'Degree Acquired Date', 'ar' => 'تاريخ الحصول على الدرجة'],
    'decisionnumber'     => ['en' => 'Decision Number', 'ar' => 'رقم قرار الترقي'],
    'decisiondate'       => ['en' => 'Decision Date', 'ar' => 'تاريخ القرار'],
    'senioritydate'      => ['en' => 'Seniority Date', 'ar' => 'تاريخ الأقدمية'],
] : [
    'empfullname'        => ['en' => 'Employee', 'ar' => 'الموظف'],
    'degreename'         => ['en' => 'Degree Acquired', 'ar' => 'الدرجة المكتسبة'],
    'promotiondate'      => ['en' => 'Promotion Date', 'ar' => 'تاريخ الترقية'],
    'degreeacquireddate' => ['en' => 'Degree Acquired Date', 'ar' => 'تاريخ الحصول على الدرجة'],
    'decisionnumber'     => ['en' => 'Decision Number', 'ar' => 'رقم قرار الترقي'],
    'decisiondate'       => ['en' => 'Decision Date', 'ar' => 'تاريخ القرار'],
    'senioritydate'      => ['en' => 'Seniority Date', 'ar' => 'تاريخ الأقدمية'],
];

run_table_module([
    'table'        => 'emppromotions',
    'module'       => 'emppromotions',
    'title'        => 'Manual Promotions',
    'title_ar'     => 'ترقية موظف',
    'order_by'     => 'id DESC',
    'search_fields'=> ['empfullname','empfullnamear','degreename','degreenamear','decisionnumber'],
    'list_cols'    => $list_cols,
    'list_labels'  => $list_labels,
    'fields'       => [
        ['name'=>'empid','label'=>'Employee','label_ar'=>'الموظف','type'=>'select','options'=>['table'=>'employeesmain','value'=>'id','label'=>'empfullname','label_ar'=>'empfullnamear','where'=>['omitted'=>0]], 'required'=>true],
        ['name'=>'empfullname','label'=>'Employee','label_ar'=>'الموظف','type'=>'hidden'],
        ['name'=>'empfullnamear','label'=>'Employee (Arabic)','label_ar'=>'الموظف (عربي)','type'=>'hidden'],
        ['name'=>'promotiondate','label'=>'Promotion Date','label_ar'=>'تاريخ الترقية','type'=>'date','required'=>true],
        ['name'=>'degreeid','label'=>'Degree Acquired','label_ar'=>'الدرجة المكتسبة','type'=>'select','options'=>['table'=>'empdegrees','value'=>'id','label'=>'degreename','label_ar'=>'degreenamear','where'=>['omitted'=>0]], 'required'=>true],
        ['name'=>'degreename','label'=>'Degree Name','label_ar'=>'اسم الدرجة','type'=>'hidden'],
        ['name'=>'degreenamear','label'=>'Degree Name (Arabic)','label_ar'=>'اسم الدرجة بالعربية','type'=>'hidden'],
        ['name'=>'degreeacquireddate','label'=>'Degree Acquired Date','label_ar'=>'تاريخ الحصول على الدرجة','type'=>'date','required'=>true],
        ['name'=>'decisionnumber','label'=>'Decision Number','label_ar'=>'رقم قرار الترقي','type'=>'text'],
        ['name'=>'decisiondate','label'=>'Decision Date','label_ar'=>'تاريخ القرار','type'=>'date'],
        ['name'=>'senioritydate','label'=>'Seniority Date','label_ar'=>'تاريخ الأقدمية','type'=>'date'],
        ['name'=>'decisionfile','label'=>'Decision of Promotion','label_ar'=>'قرار الترقي','type'=>'file','accept'=>'TIFF,PDF,JPG,JPEG,JPE,PNG','current_path_field'=>'decisionfilepath','required'=>false],
        ['name'=>'decisionfilepath','label'=>'Decision File Path','label_ar'=>'مسار ملف القرار','type'=>'hidden'],
    ],
]);