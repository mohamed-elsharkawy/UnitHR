<?php
if (!defined('UNITHR_ENTRY')) { header('Location: ../../'); exit; }

require_once __DIR__ . '/../../includes/crud_engine.php';

function module_emparchives_before_save(&$data, $isAdd, $id = 0, $old = []) {
    // Populate employee names and NID
    if (!empty($data['empid'])) {
        $emp = fetchRow("SELECT empfullname, empfullnamear, nid FROM " . table('employeesmain') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$data['empid']]);
        if ($emp) {
            $data['empfullname'] = $emp['empfullname'];
            $data['empfullnamear'] = $emp['empfullnamear'];
            $data['nid'] = $emp['nid'];
        }
    }
    // Populate document type names
    if (!empty($data['documenttypeid'])) {
        $dt = fetchRow("SELECT doctypename, doctypenamear FROM " . table('documenttypes') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$data['documenttypeid']]);
        if ($dt) {
            $data['documenttypename'] = $dt['doctypename'];
            $data['documenttypenamear'] = $dt['doctypenamear'];
        }
    }
    // Handle document file upload (save to /uploads)
    $allowed = ['tiff','tif','pdf','jpg','jpeg','jpe','png'];
    if (isset($_FILES['documentfile']) && is_array($_FILES['documentfile'])
        && ($_FILES['documentfile']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $origName = (string)($_FILES['documentfile']['name'] ?? '');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed, true)) {
            $uploadDir = __DIR__ . '/../../uploads';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            if (is_dir($uploadDir) && is_writable($uploadDir)) {
                $newName = 'archive_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $target = $uploadDir . DIRECTORY_SEPARATOR . $newName;
                if (move_uploaded_file($_FILES['documentfile']['tmp_name'], $target)) {
                    $data['documentpath'] = 'uploads/' . $newName;
                }
            }
        }
    }
    if (empty($data['documentpath'])) {
        $data['documentpath'] = null;
    }
}

$isAr = lang() === 'ar';

$list_cols = $isAr
    ? ['empfullnamear', 'documenttypenamear', 'documentname', 'nid']
    : ['empfullname', 'documenttypename', 'documentname', 'nid'];

$list_labels = [
    'empfullname'          => ['en' => 'Employee', 'ar' => 'الموظف'],
    'empfullnamear'        => ['en' => 'Employee (Arabic)', 'ar' => 'الموظف (عربي)'],
    'documenttypename'     => ['en' => 'Document Type', 'ar' => 'نوع المستند'],
    'documenttypenamear'   => ['en' => 'Document Type (Arabic)', 'ar' => 'نوع المستند (عربي)'],
    'documentname'         => ['en' => 'Document Name', 'ar' => 'اسم المستند'],
    'nid'                  => ['en' => 'NID', 'ar' => 'رقم الهوية'],
];

run_table_module([
    'table'        => 'emparchives',
    'module'       => 'emparchives',
    'title'        => 'Employees Archives',
    'title_ar'     => 'أرشيف الموظفين',
    'order_by'     => 'id DESC',
    'search_fields'=> ['documentname', 'empfullname', 'empfullnamear', 'nid'],
    'list_cols'    => $list_cols,
    'list_labels'  => $list_labels,
    'fields'       => [
        ['name'=>'empid','label'=>'Employee','label_ar'=>'الموظف','type'=>'select','options'=>['table'=>'employeesmain','value'=>'id','label'=>'empfullname','label_ar'=>'empfullnamear','where'=>['omitted'=>0]], 'required'=>true],
        ['name'=>'empfullname','label'=>'Employee','label_ar'=>'الموظف','type'=>'hidden'],
        ['name'=>'empfullnamear','label'=>'Employee (Arabic)','label_ar'=>'الموظف (عربي)','type'=>'hidden'],
        ['name'=>'nid','label'=>'NID','label_ar'=>'رقم الهوية','type'=>'hidden'],
        ['name'=>'documenttypeid','label'=>'Document Type','label_ar'=>'نوع المستند','type'=>'select','options'=>['table'=>'documenttypes','value'=>'id','label'=>'doctypename','label_ar'=>'doctypenamear','where'=>['omitted'=>0]], 'required'=>true],
        ['name'=>'documenttypename','label'=>'Document Type','label_ar'=>'نوع المستند','type'=>'hidden'],
        ['name'=>'documenttypenamear','label'=>'Document Type (Arabic)','label_ar'=>'نوع المستند (عربي)','type'=>'hidden'],
        ['name'=>'documentname','label'=>'Document Name','label_ar'=>'اسم المستند','type'=>'text','required'=>true],
        ['name'=>'documentfile','label'=>'Document File','label_ar'=>'ملف المستند','type'=>'file','accept'=>'TIFF,PDF,JPG,JPEG,JPE,PNG','current_path_field'=>'documentpath','required'=>false],
        ['name'=>'documentpath','label'=>'Document Path','label_ar'=>'مسار المستند','type'=>'hidden'],
        ['name'=>'notes','label'=>'Notes','label_ar'=>'ملاحظات','type'=>'textarea','rows'=>3],
    ],
]);