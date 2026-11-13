<?php
/**
 * UnitHR - Helper Functions
 * Reversible encryption, TOTP OTP, Arabic normalization, permissions,
 * audit shadow logging, and offline export (CSV / XLSX / Word / PDF).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/autoload.php';

/**
 * Calculate seniority date for promotion.
 * When an employee spends more time in a degree than the interim period,
 * the excess time carries over to the new degree.
 * 
 * @param string $degreeAcquiredDate Date the current degree was acquired
 * @param int $interimPeriod Years required for promotion
 * @param string $promotionDate Date of promotion to new degree
 * @return string Seniority date in the new degree
 */
function calculate_seniority_date($degreeAcquiredDate, $interimPeriod, $promotionDate) {
    if (!$degreeAcquiredDate || !$promotionDate || $interimPeriod <= 0) {
        return $promotionDate;
    }
    $acquired = new DateTime($degreeAcquiredDate);
    $promotion = new DateTime($promotionDate);
    $diff = $acquired->diff($promotion);
    $monthsInDegree = ($diff->y * 12) + $diff->m;
    $interimMonths = $interimPeriod * 12;
    $excessMonths = $monthsInDegree - $interimMonths;
    if ($excessMonths <= 0) {
        return $promotionDate;
    }
    // Seniority date = promotion date - excess months
    $seniorityDate = clone $promotion;
    $seniorityDate->modify("-{$excessMonths} months");
    return $seniorityDate->format('Y-m-d');
}

/**
 * Check if an employee is eligible for promotion.
 * 
 * @param int $empdegreeId Current degree ID
 * @param string $seniorityDate Seniority date in current degree
 * @return bool Whether the employee is eligible for promotion
 */
function is_eligible_for_promotion($empdegreeId, $seniorityDate) {
    if (!$empdegreeId || !$seniorityDate) return false;
    $degree = fetchRow("SELECT interimperiod, nextdegreeid FROM " . table('empdegrees') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$empdegreeId]);
    if (!$degree || empty($degree['nextdegreeid'])) return false;
    // Check if interim period has elapsed
    $seniority = new DateTime($seniorityDate);
    $now = new DateTime();
    $diff = $seniority->diff($now);
    $monthsElapsed = ($diff->y * 12) + $diff->m;
    $interimMonths = (int)$degree['interimperiod'] * 12;
    return $monthsElapsed >= $interimMonths;
}

/**
 * Get a translated string by key.
 * Falls back to the key itself if not found.
 * Language file is loaded dynamically based on current session language.
 */
function L($key, ...$args) {
    static $loadedLang = null;
    static $strings = [];

    $currentLang = $_SESSION[UHR_SESSION_KEY]['lang'] ?? 'ar';
    if ($loadedLang !== $currentLang) {
        $langFile = __DIR__ . '/../lang/' . ($currentLang === 'ar' ? 'ar' : 'en') . '.php';
        $strings = [];
        if (file_exists($langFile)) {
            $lang = [];
            include $langFile;
            $strings = $lang ?? [];
        }
        $loadedLang = $currentLang;
        $_SESSION[UHR_SESSION_KEY]['lang_strings'] = $strings;
    }

    $str = $strings[$key] ?? $key;
    if (!empty($args)) {
        $str = sprintf($str, ...$args);
    }
    return $str;
}

// -------------------------------------------------------------------------
// Reversible encryption (ARC4 stream cipher + base64)
// Passwords are "encrypted by a function and can be decrypted if needed".
// -------------------------------------------------------------------------
function rc4($data, $key) {
    $key = $key ?: 'UnitHR';
    $keylen = strlen($key);
    $S = range(0, 255);
    $j = 0;
    for ($i = 0; $i < 256; $i++) {
        $j = ($j + $S[$i] + ord($key[$i % $keylen])) % 256;
        $tmp = $S[$i]; $S[$i] = $S[$j]; $S[$j] = $tmp;
    }
    $i = $j = 0;
    $out = '';
    for ($y = 0; $y < strlen($data); $y++) {
        $i = ($i + 1) % 256;
        $j = ($j + $S[$i]) % 256;
        $tmp = $S[$i]; $S[$i] = $S[$j]; $S[$j] = $tmp;
        $out .= chr(ord($data[$y]) ^ $S[($S[$i] + $S[$j]) % 256]);
    }
    return $out;
}

function encrypt($plain, $key = null) {
    $key = $key ?? app('encrypt_key') ?? 'UnitHR-Secret-Key-2026';
    if ($plain === '') return '';
    return base64_encode(rc4((string)$plain, $key));
}

function decrypt($cipher, $key = null) {
    $key = $key ?? app('encrypt_key') ?? 'UnitHR-Secret-Key-2026';
    if ($cipher === '') return '';
    $data = @base64_decode($cipher, true);
    if ($data === false) {
        return $cipher;
    }
    return rc4($data, $key);
}

// -------------------------------------------------------------------------
// TOTP OTP (offline authenticator compatible: Google/Microsoft/FreeOTP/Oracle)
// Implemented with hash_hmac (SHA-1) - no openssl required.
// -------------------------------------------------------------------------
function otp_generate_secret($length = 16) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    $cl = strlen($chars);
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, $cl - 1)];
    }
    return $secret;
}

function otp_base32_decode($secret) {
    $secret = strtoupper((string)$secret);
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $map = [];
    for ($i = 0; $i < strlen($chars); $i++) {
        $map[$chars[$i]] = $i;
    }
    $bin = '';
    $val = 0;
    $bits = 0;
    for ($i = 0; $i < strlen($secret); $i++) {
        $c = $secret[$i];
        if (!isset($map[$c])) {
            continue;
        }
        $val = ($val << 5) | $map[$c];
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $bin .= chr(($val >> $bits) & 0xFF);
        }
    }
    return $bin;
}

function otp_generate_code($secret, $time = null) {
    $time = $time ?? (int)floor(time() / 30);
    $key = otp_base32_decode($secret);
    $msg = '';
    for ($i = 7; $i >= 0; $i--) {
        $msg .= chr(($time >> ($i * 8)) & 0xFF);
    }
    $hash = hash_hmac('sha1', $msg, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $binary = substr($hash, $offset, 4);
    $unpacked = unpack('N', $binary);
    $binary = ($unpacked[1] & 0x7FFFFFFF);
    $code = $binary % 1000000;
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

function otp_verify($secret, $code, $window = 1) {
    if (empty($secret) || $code === '') {
        return false;
    }
    $now = (int)floor(time() / 30);
    $code = (string)$code;
    for ($i = -$window; $i <= $window; $i++) {
        if (otp_generate_code($secret, $now + $i) === $code) {
            return true;
        }
    }
    return false;
}

function otp_provisioning_uri($secret, $username, $issuer = 'UnitHR') {
    return sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s',
        urlencode($issuer),
        urlencode($username),
        urlencode($secret),
        urlencode($issuer)
    );
}

// -------------------------------------------------------------------------
// Arabic text normalization for search ("أ" and "ا" equivalence, etc.)
// -------------------------------------------------------------------------
function arabic_normalize($text) {
    if ($text === null) {
        return '';
    }
    $text = (string)$text;
    $replace = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا',
        'ة' => 'ه',
        'ى' => 'ي',
        'ؤ' => 'و', 'ئ' => 'ي',
    ];
    return str_replace(array_keys($replace), array_values($replace), $text);
}

function arabic_search_pattern($text) {
    $n = arabic_normalize($text);
    $n = addcslashes($n, '%_\\');
    return '%' . $n . '%';
}

// -------------------------------------------------------------------------
// Current user / session helpers
// -------------------------------------------------------------------------
function current_user() {
    return $_SESSION[UHR_SESSION_KEY]['user'] ?? null;
}

function current_user_id() {
    return (int)($_SESSION[UHR_SESSION_KEY]['user_id'] ?? 0);
}

function is_logged_in() {
    return !empty($_SESSION[UHR_SESSION_KEY]['user_id']);
}

function is_superadmin() {
    $u = current_user();
    return $u && !empty($u['is_superadmin']);
}

function is_app_staff() {
    $u = current_user();
    if (!$u) {
        return false;
    }
    $perms = user_permissions();
    return !empty($u['is_admin'])
        || !empty($u['is_superadmin'])
        || !empty($u['is_hradmin'])
        || !empty($u['is_hruser'])
        || !empty($perms['_is_hradmin'])
        || !empty($perms['_is_hruser']);
}

function ajax_output($body, $contentType = 'text/html; charset=utf-8') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: ' . $contentType);
    echo $body;
    exit;
}

function user_permissions() {
    return $_SESSION[UHR_SESSION_KEY]['user_permissions'] ?? [];
}

/**
 * Check whether the current user can perform $action on $module.
 * Superadmins have all permissions.
 * $action: view, add, edit, del, admin
 */
function user_can($module, $action = 'view') {
    if (is_superadmin()) {
        return true;
    }
    $u = current_user();
    if ($u && !empty($u['is_admin'])) {
        return true;
    }
    $perms = user_permissions();
    if (!empty($perms['all_modules'])) {
        return true;
    }
    if (isset($perms[$module])) {
        $p = $perms[$module];
        switch ($action) {
            case 'add':     return !empty($p['canadd']);
            case 'edit':    return !empty($p['canedit']);
            case 'del':
            case 'delete':  return !empty($p['candel']);
            case 'admin':   return !empty($p['isadmin']);
            default:        return true;
        }
    }
    return false;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ?action=login');
        exit;
    }
}

function require_otp() {
    require_login();
    if (!is_app_staff()) {
        return;
    }
    if (empty($_SESSION[UHR_SESSION_KEY]['otp_verified'])) {
        // Re-authenticate instead of looping on a non-existent 'otp' action
        header('Location: ?action=login');
        exit;
    }
}

function require_permission($module, $action = 'view') {
    require_login();
    if (!user_can($module, $action)) {
        set_flash('error', 'You do not have permission to ' . $action . ' in module: ' . htmlspecialchars($module));
        header('Location: ?module=home');
        exit;
    }
}

// -------------------------------------------------------------------------
// Audit shadow log: before a record is updated, save a snapshot
// with extra columns identifying who edited and when.
// -------------------------------------------------------------------------
function shadow_log($tableName, $originalId, array $data, $action = 'update') {
    $u = current_user();
    $byId   = $u['id'] ?? 0;
    $byName = $u['username'] ?? 'system';
    $byNameAr = $u['username_ar'] ?? $byName;
    $sql = "INSERT INTO " . table('shadow_log') . "
        (table_name, original_id, action, changedat, changedbyid, changedby, changedbyname, changedbynamear, snapshot)
        VALUES (?,?,?,?,?,?,?,?,?)";
    q($sql, [
        $tableName,
        (int)$originalId,
        $action,
        date('Y-m-d H:i:s'),
        $byId,
        $byName,
        $byNameAr,
        $byNameAr,
        json_encode($data, JSON_UNESCAPED_UNICODE),
    ]);
}

// -------------------------------------------------------------------------
// Minimal uncompressed ZIP writer (no ZipArchive extension required).
// Used to build offline XLSX and Word (docx) packages.
// -------------------------------------------------------------------------
class ZipWriter {
    private $files = [];
    public function add($name, $content) {
        $this->files[str_replace('\\', '/', $name)] = $content;
    }
    public function save() {
        $datablock = '';
        $cd = '';
        $offset = 0;
        foreach ($this->files as $name => $content) {
            $crc = crc32($content);
            $len = strlen($content);
            $time = $this->dosTime();
            $lh = "\x50\x4b\x03\x04\x14\x00\x00\x00\x00\x00";
            $lh .= pack('V', $time);
            $lh .= pack('V', $crc);
            $lh .= pack('V', $len);
            $lh .= pack('V', $len);
            $lh .= pack('v', strlen($name));
            $lh .= pack('v', 0);
            $datablock .= $lh . $name . $content;
            $cd .= "\x50\x4b\x01\x02\x1e\x03\x14\x00\x00\x00\x00\x00";
            $cd .= pack('V', $time);
            $cd .= pack('V', $crc);
            $cd .= pack('V', $len);
            $cd .= pack('V', $len);
            $cd .= pack('v', strlen($name));
            $cd .= pack('v', 0);
            $cd .= pack('v', 0);
            $cd .= pack('v', 0);
            $cd .= pack('v', 0);
            $cd .= pack('V', 0);
            $cd .= pack('V', $offset);
            $cd .= $name;
            $offset += strlen($lh) + strlen($name) + $len;
        }
        $eocd = "\x50\x4b\x05\x06";
        $eocd .= pack('v', 0);
        $eocd .= pack('v', 0);
        $eocd .= pack('v', count($this->files));
        $eocd .= pack('v', count($this->files));
        $eocd .= pack('V', strlen($cd));
        $eocd .= pack('V', $offset);
        $eocd .= pack('v', 0);
        return $datablock . $cd . $eocd;
    }
    private function dosTime($t = null) {
        $t = $t ?: time();
        $d = getdate($t);
        return (($d['year'] - 1980) << 25) | ($d['mon'] << 21) | ($d['mday'] << 16)
            | ($d['hours'] << 11) | ($d['minutes'] << 5) | ($d['seconds'] >> 1);
    }
}

// -------------------------------------------------------------------------
// Offline exports
// -------------------------------------------------------------------------
/**
 * Discard the page layout HTML that the front controller buffered before the
 * export ran. Without this, the downloaded file starts with "<!DOCTYPE html>"
 * and is corrupted for every format.
 */
function export_clean_buffers() {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
}

function esc_csv($v) {
    $v = (string)$v;
    if (preg_match('/[",\r\n]/', $v)) {
        return '"' . str_replace('"', '""', $v) . '"';
    }
    return $v;
}

function export_csv($filename, $headers, $rows) {
    export_clean_buffers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($out, $row, ',', '"', '\\');
    }
    fclose($out);
    exit;
}

function xlsx_col_letter($index) {
    $index = (int)$index;
    $letter = '';
    while ($index >= 0) {
        $letter = chr(65 + ($index % 26)) . $letter;
        $index = intdiv($index, 26) - 1;
    }
    return $letter;
}

function export_xlsx($filename, $sheetName, $headers, $rows) {
    export_clean_buffers();
    // Use PhpSpreadsheet (bundled in assets/)
    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($sheetName, 0, 31));
        
        // Header row
        $col = 1;
        foreach ($headers as $h) {
            $cellCoord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1';
            $sheet->setCellValue($cellCoord, $h);
            $sheet->getStyle($cellCoord)->getFont()->setBold(true);
            $col++;
        }
        
        // Data rows
        $row = 2;
        foreach ($rows as $r) {
            $col = 1;
            foreach ($r as $val) {
                $cellCoord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
                $sheet->setCellValue($cellCoord, $val);
                $col++;
            }
            $row++;
        }
        
        // Auto-size columns
        $highestCol = $sheet->getHighestColumn();
        for ($c = 1; $c <= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol); $c++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        $writer->save('php://output');
        exit;
    } catch (\Throwable $e) {
        // Discard any partial output, then fall back to the built-in ZipWriter
        while (ob_get_level() > 0) { @ob_end_clean(); }
    }
    // Original implementation below (fallback)
    $sheet = htmlspecialchars((string)$sheetName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $strings = [];
    $si = function ($v) use (&$strings) {
        $v = (string)$v;
        if (!isset($strings[$v])) {
            $strings[$v] = count($strings);
        }
        return $strings[$v];
    };
    $cellXml = function ($colIdx, $rowIdx, $val) use ($si) {
        $ref = xlsx_col_letter($colIdx) . $rowIdx;
        if ($val === null || $val === '') {
            return '<c r="' . $ref . '"/>';
        }
        if (is_int($val) || is_float($val)) {
            return '<c r="' . $ref . '" t="n"><v>' . htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</v></c>';
        }
        return '<c r="' . $ref . '" t="s"><v>' . $si((string)$val) . '</v></c>';
    };

    $headerXml = '';
    $c = 0;
    foreach ($headers as $h) {
        $headerXml .= $cellXml($c, 1, $h);
        $c++;
    }
    $rowsXml = '';
    $r = 2;
    foreach ($rows as $row) {
        $rowsXml .= '<row r="' . $r . '">';
        $c = 0;
        foreach ($row as $val) {
            $rowsXml .= $cellXml($c, $r, $val);
            $c++;
        }
        $rowsXml .= '</row>';
        $r++;
    }
    $ssXml = '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
    foreach (array_keys($strings) as $s) {
        $ssXml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</t></si>';
    }
    $ssXml .= '</sst>';
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">' . $headerXml . '</row>
    ' . $rowsXml . '
  </sheetData>
</worksheet>';

    $zip = new ZipWriter();
    $zip->add('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');
    $zip->add('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');
    $zip->add('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');
    $zip->add('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="' . $sheet . '" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>');
    $zip->add('xl/sharedStrings.xml', $ssXml);
    $zip->add('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styles xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts><font><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/></font></fonts>
  <fills><fill><patternFill patternType="none"/></fill></fills>
  <borders><border><left/><right/><top/><bottom/></border></borders>
  <cellStyleXfs><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styles>');
    $zip->add('xl/worksheets/sheet1.xml', $sheetXml);

    $data = $zip->save();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Content-Length: ' . strlen($data));
    echo $data;
    exit;
}

function export_docx($filename, $title, $headers, $rows) {
    export_clean_buffers();
    $title = htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // Build table rows
    $tr = '';
    $tr .= '<w:tr>';
    foreach ($headers as $h) {
        $tr .= '<w:tc><w:tcPr><w:tcW w:w="' . (1200 + strlen((string)$h) * 40) . '" w:type="dxa"/></w:tcPr><w:p><w:r><w:t xml:space="preserve">' . htmlspecialchars((string)$h, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</w:t></w:r></w:p></w:tc>';
    }
    $tr .= '</w:tr>';
    foreach ($rows as $row) {
        $tr .= '<w:tr>';
        foreach ($row as $val) {
            $tr .= '<w:tc><w:p><w:r><w:t xml:space="preserve">' . htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</w:t></w:r></w:p></w:tc>';
        }
        $tr .= '</w:tr>';
    }
    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:b/><w:t xml:space="preserve">' . $title . '</w:t></w:r></w:p>
    <w:tbl>
      <w:tblPr><w:tblW w:w="9000" w:type="dxa"/><w:tblBorders>
        <w:top w:val="single" w:sz="4"/><w:left w:val="single" w:sz="4"/>
        <w:bottom w:val="single" w:sz="4"/><w:right w:val="single" w:sz="4"/>
        <w:insideH w:val="single" w:sz="4"/><w:insideV w:val="single" w:sz="4"/>
      </w:tblBorders></w:tblPr>
      <w:tblGrid>';
    foreach ($headers as $h) {
        $document .= '<w:gridCol w:w="' . (1200 + strlen($h) * 40) . '"/>';
    }
    $document .= '</w:tblGrid>' . $tr . '
    </w:tbl>
  </w:body>
</w:document>';

    $zip = new ZipWriter();
    $zip->add('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>');
    $zip->add('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');
    $zip->add('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>
');
    $zip->add('word/document.xml', $document);

    $data = $zip->save();
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '.docx"');
    header('Content-Length: ' . strlen($data));
    echo $data;
    exit;
}

// -------------------------------------------------------------------------
// Arabic text preparation for DomPDF
//
// DomPDF has no Arabic contextual shaping and no Unicode bidi algorithm, so
// Arabic text is rendered as disconnected, left-to-right letters. We convert
// each letter to its correct isolated/initial/medial/final presentation form
// (U+FE70..U+FEFF, present in the bundled DejaVu Sans font), then reorder the
// shaped runs for right-to-left display.
// -------------------------------------------------------------------------
function uhr_arabic_forms() {
    static $forms = null;
    if ($forms !== null) return $forms;
    $forms = [
        0x0621 => [0xFE80],
        0x0622 => [0xFE81, 0xFE82],
        0x0623 => [0xFE83, 0xFE84],
        0x0624 => [0xFE85, 0xFE86],
        0x0625 => [0xFE87, 0xFE88],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0xFE8D, 0xFE8E],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA],
        0x0630 => [0xFEAB, 0xFEAC],
        0x0631 => [0xFEAD, 0xFEAE],
        0x0632 => [0xFEAF, 0xFEB0],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0640 => [0x0640, 0x0640, 0x0640, 0x0640],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE],
        0x0649 => [0xFEEF, 0xFEF0],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
    ];
    return $forms;
}

function uhr_is_transparent_mark($cp) {
    return ($cp >= 0x0610 && $cp <= 0x061A)
        || ($cp >= 0x064B && $cp <= 0x065F)
        || $cp === 0x0670
        || ($cp >= 0x06D6 && $cp <= 0x06DC)
        || ($cp >= 0x06DF && $cp <= 0x06E4)
        || ($cp >= 0x06E7 && $cp <= 0x06E8)
        || ($cp >= 0x06EA && $cp <= 0x06ED);
}

function uhr_arabic_joins_prev($cp) {
    $forms = uhr_arabic_forms();
    return isset($forms[$cp]) && count($forms[$cp]) >= 2;
}

function uhr_arabic_joins_next($cp) {
    $forms = uhr_arabic_forms();
    return isset($forms[$cp]) && count($forms[$cp]) >= 4;
}

function uhr_shape_arabic($text) {
    if (!preg_match('/[\x{0600}-\x{06FF}]/u', $text)) return $text;

    $forms = uhr_arabic_forms();
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $cps = array_map(function ($ch) { return mb_ord($ch, 'UTF-8'); }, $chars);
    $n = count($chars);
    $out = '';

    for ($i = 0; $i < $n; $i++) {
        $cp = $cps[$i];
        if (!isset($forms[$cp])) {
            $out .= $chars[$i];
            continue;
        }

        // Lam + Alef must become a single ligature glyph.
        if ($cp === 0x0644) {
            $nx = $i + 1;
            while ($nx < $n && uhr_is_transparent_mark($cps[$nx])) $nx++;
            if ($nx < $n && in_array($cps[$nx], [0x0622, 0x0623, 0x0625, 0x0627], true)) {
                $prev = $i - 1;
                while ($prev >= 0 && uhr_is_transparent_mark($cps[$prev])) $prev--;
                $prevJoin = $prev >= 0 && uhr_arabic_joins_next($cps[$prev]);
                $ligatures = [
                    0x0622 => [0xFEF5, 0xFEF6],
                    0x0623 => [0xFEF7, 0xFEF8],
                    0x0625 => [0xFEF9, 0xFEFA],
                    0x0627 => [0xFEFB, 0xFEFC],
                ];
                $lig = $ligatures[$cps[$nx]];
                $out .= mb_chr($prevJoin ? $lig[1] : $lig[0], 'UTF-8');
                for ($m = $i + 1; $m < $nx; $m++) $out .= $chars[$m];
                $i = $nx;
                continue;
            }
        }

        $prev = $i - 1;
        while ($prev >= 0 && uhr_is_transparent_mark($cps[$prev])) $prev--;
        $next = $i + 1;
        while ($next < $n && uhr_is_transparent_mark($cps[$next])) $next++;

        $joinPrev = $prev >= 0 && uhr_arabic_joins_next($cps[$prev]) && uhr_arabic_joins_prev($cp);
        $joinNext = uhr_arabic_joins_next($cp) && $next < $n && uhr_arabic_joins_prev($cps[$next]);

        $f = $forms[$cp];
        if ($joinPrev && $joinNext && isset($f[3])) {
            $out .= mb_chr($f[3], 'UTF-8');
        } elseif ($joinPrev && isset($f[1])) {
            $out .= mb_chr($f[1], 'UTF-8');
        } elseif ($joinNext && isset($f[2])) {
            $out .= mb_chr($f[2], 'UTF-8');
        } else {
            $out .= mb_chr($f[0], 'UTF-8');
        }
    }

    return $out;
}

function uhr_is_rtl_cp($cp) {
    return ($cp >= 0x0600 && $cp <= 0x06FF)
        || ($cp >= 0x0750 && $cp <= 0x077F)
        || ($cp >= 0x08A0 && $cp <= 0x08FF)
        || ($cp >= 0xFB50 && $cp <= 0xFDFF)
        || ($cp >= 0xFE70 && $cp <= 0xFEFF);
}

function uhr_is_arabic_digit($cp) {
    return ($cp >= 0x0660 && $cp <= 0x0669) || ($cp >= 0x06F0 && $cp <= 0x06F9);
}

function uhr_bidi_class($ch) {
    $cp = mb_ord($ch, 'UTF-8');
    if (uhr_is_arabic_digit($cp)) return 'L';
    if (uhr_is_rtl_cp($cp)) return 'R';
    if (preg_match('/^[\p{Latin}\p{N}]$/u', $ch)) return 'L';
    if (preg_match('/^[\s\x{00A0}\x{2000}-\x{200B}]$/u', $ch)) return 'N';
    return 'N';
}

function uhr_arabic_clusters($text) {
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $clusters = [];
    foreach ($chars as $ch) {
        $cp = mb_ord($ch, 'UTF-8');
        if (uhr_is_transparent_mark($cp) && $clusters) {
            $clusters[count($clusters) - 1] .= $ch;
        } else {
            $clusters[] = $ch;
        }
    }
    return $clusters;
}

/**
 * Shape and reorder one string so DomPDF draws it as proper RTL Arabic.
 * Latin words and numbers keep their internal left-to-right order.
 */
function uhr_pdf_arabic($text) {
    $text = (string)$text;
    if (!preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text)) {
        return $text;
    }

    $shaped = uhr_shape_arabic($text);
    $chars = preg_split('//u', $shaped, -1, PREG_SPLIT_NO_EMPTY);
    $runs = [];
    foreach ($chars as $ch) {
        $type = uhr_bidi_class($ch);
        if ($runs && $runs[count($runs) - 1]['type'] === $type) {
            $runs[count($runs) - 1]['text'] .= $ch;
        } else {
            $runs[] = ['type' => $type, 'text' => $ch];
        }
    }

    // Absorb neutral runs that sit between two runs of the same direction.
    $count = count($runs);
    for ($i = 1; $i < $count - 1; $i++) {
        if ($runs[$i]['type'] !== 'N') continue;
        $left = $runs[$i - 1]['type'];
        $right = $runs[$i + 1]['type'];
        if ($left === $right && ($left === 'L' || $left === 'R')) {
            $runs[$i]['type'] = $left;
        }
    }

    $merged = [];
    foreach ($runs as $run) {
        if ($merged && $merged[count($merged) - 1]['type'] === $run['type']) {
            $merged[count($merged) - 1]['text'] .= $run['text'];
        } else {
            $merged[] = $run;
        }
    }

    $out = '';
    for ($i = count($merged) - 1; $i >= 0; $i--) {
        $run = $merged[$i];
        if ($run['type'] === 'R') {
            $out .= implode('', array_reverse(uhr_arabic_clusters($run['text'])));
        } elseif ($run['type'] === 'N') {
            // Reverse neutrals too so spaces stay outside mirrored brackets,
            // e.g. " (PDF)" -> ") PDF(" -> mirrored "(PDF) ".
            $chars = preg_split('//u', $run['text'], -1, PREG_SPLIT_NO_EMPTY);
            $out .= implode('', array_reverse($chars));
        } else {
            $out .= $run['text'];
        }
    }

    return strtr($out, [
        '(' => ')', ')' => '(',
        '[' => ']', ']' => '[',
        '{' => '}', '}' => '{',
        '«' => '»', '»' => '«',
    ]);
}

// -------------------------------------------------------------------------
// Minimal PDF generator (fallback when DomPDF is unavailable).
// -------------------------------------------------------------------------
function pdf_escape($s) {
    $s = (string)$s;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\)', '\\)'], $s);
}

function pdf_text($x, $y, $s, $size = 10) {
    $s = pdf_escape($s);
    return sprintf('BT /F1 %d Tf %.2f %.2f Td (%s) Tj ET', $size, $x, $y, $s);
}

function export_pdf($filename, $title, $headers, $rows) {
    export_clean_buffers();
    // Use DomPDF (bundled in assets/)
    try {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', __DIR__ . '/../assets/dompdf/dompdf');
        $dompdf = new \Dompdf\Dompdf($options);

        // Arabic UI documents are laid out right-to-left: reverse the column
        // order and align right. Arabic cell values are always shaped and
        // bidi-reordered, even inside an English-language export.
        $isRtlLayout = (lang() === 'ar');
        if ($isRtlLayout) {
            $headers = array_reverse($headers);
            $rows = array_map('array_reverse', $rows);
        }
        $prep = function ($v) { return uhr_pdf_arabic((string)$v); };
        $title = $prep($title);
        $headers = array_map($prep, $headers);
        $rows = array_map(function ($r) use ($prep) { return array_map($prep, $r); }, $rows);
        $align = $isRtlLayout ? 'right' : 'left';

        $html = '<html><head><meta charset="utf-8"><style>
            body { font-family: DejaVu Sans, sans-serif; text-align: ' . $align . '; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: ' . $align . '; font-size: 10px; }
            th { background-color: #f2f2f2; font-weight: bold; }
            h2 { color: #333; text-align: ' . $align . '; }
        </style></head><body>';
        $html .= '<h2>' . htmlspecialchars($title) . '</h2>';
        $html .= '<table><thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th>' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr>';
            foreach ($r as $val) {
                $html .= '<td>' . htmlspecialchars((string)$val) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        echo $dompdf->output();
        exit;
    } catch (\Throwable $e) {
        // Discard any partial output, then fall back to the built-in generator
        while (ob_get_level() > 0) { @ob_end_clean(); }
    }
    // Original implementation below
    $title = pdf_escape((string)$title);
    $pageW = 595; // A4 width
    $y = 780;
    $content = '';
    $content .= pdf_text(40, $y, $title, 14);
    $y -= 30;
    // header row
    $x = 40;
    $colW = ($pageW - 80) / count($headers);
    foreach ($headers as $h) {
        $content .= pdf_text($x, $y, pdf_escape((string)$h), 9);
        $x += $colW;
    }
    $y -= 14;
    // rule
    $content .= sprintf('40 %d %d %d re S', $y, $pageW - 80, 1);
    $y -= 10;
    foreach ($rows as $row) {
        if ($y < 60) {
            $content .= "showpage\n";
            $y = 780;
        }
        $x = 40;
        foreach ($row as $val) {
            $content .= pdf_text($x, $y, pdf_escape((string)$val), 8);
            $x += $colW;
        }
        $y -= 12;
    }
    $content .= "showpage\n";

    $fonts = '/F1 12 Tf';
    $header = '%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>
endobj
4 0 obj
<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>
endobj
5 0 obj
<< /Length ' . strlen($content) . ' >>
stream
' . $content . '
endstream
endobj
xref
0 6
0000000000 65535 f 
0000000009 00000 n 
0000000074 00000 n 
0000000128 00000 n 
0000000207 00000 n 
0000000278 00000 n 
trailer
<< /Size 6 /Root 1 0 R >>
startxref
' . (278 + strlen($content)) . '
%%EOF';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    header('Content-Length: ' . strlen($header));
    echo $header;
    exit;
}

/**
 * Generic export dispatcher used by report/list pages.
 * $format: csv | xlsx | docx | pdf
 */
function do_export($format, $filename, $title, $headers, $rows) {
    export_clean_buffers();
    switch ($format) {
        case 'csv':  export_csv($filename, $headers, $rows); break;
        case 'xlsx': export_xlsx($filename, $title, $headers, $rows); break;
        case 'docx': export_docx($filename, $title, $headers, $rows); break;
        case 'pdf':  export_pdf($filename, $title, $headers, $rows); break;
        default:     export_csv($filename, $headers, $rows);
    }
}

/**
 * Flash message helpers
 */
function set_flash($type, $msg) {
    $_SESSION[UHR_SESSION_KEY]['flash'] = ['type' => $type, 'msg' => $msg];
}
function get_flash() {
    $f = $_SESSION[UHR_SESSION_KEY]['flash'] ?? null;
    unset($_SESSION[UHR_SESSION_KEY]['flash']);
    return $f;
}

/**
 * HTML-friendly label: pick Arabic or English column based on language.
 */
function label($en, $ar) {
    return is_rtl() ? $ar : $en;
}

/**
 * Filter a list of field definitions to show only the fields appropriate
 * for the current interface language. Paired fields (e.g. name / namear)
 * are collapsed so that only the matching language field is shown; neutral
 * fields (no language counterpart) are always shown.
 */
function fields_for_lang($fields) {
    $names = array_column($fields, 'name');
    $out = [];
    foreach ($fields as $f) {
        $name = $f['name'];
        $isArField = (strlen($name) > 2 && substr($name, -2) === 'ar');
        $base = $isArField ? substr($name, 0, -2) : $name;
        $paired = $isArField
            ? in_array($base, $names, true)
            : in_array($base . 'ar', $names, true);
        if ($paired) {
            if (is_rtl() && $isArField) { $out[] = $f; }
            if (!is_rtl() && !$isArField) { $out[] = $f; }
        } else {
            $out[] = $f;
        }
    }
    return array_values($out);
}

/**
 * Deduce gender from a national ID.
 *
 * Rule: look at the last three digits of the NID.
 *   even -> Male
 *   odd  -> Female
 *
 * @param string $nid National ID (non-digits are ignored)
 * @return string 'Male', 'Female', or '' when fewer than three digits exist
 */
function gender_from_nid($nid) {
    $digits = preg_replace('/\D+/', '', (string)$nid);
    if (strlen($digits) < 3) {
        return '';
    }
    $last3 = (int)substr($digits, -3);
    return ($last3 % 2 === 0) ? 'Male' : 'Female';
}

/**
 * Render a form input for a field definition.
 * Used by crud_engine and custom modules.
 */
function field_input($f, $value = '') {
    $name = $f['name'];
    $type = $f['type'] ?? 'text';
    $required = !empty($f['required']) ? ' required' : '';
    $placeholder = !empty($f['placeholder']) ? ' placeholder="'.htmlspecialchars($f['placeholder']).'"' : '';
    $label = lang() === 'ar' && !empty($f['label_ar']) ? $f['label_ar'] : $f['label'];
    $asterisk = !empty($f['required']) ? ' <span class="text-danger">*</span>' : '';
    $extra = !empty($f['extra']) ? ' ' . $f['extra'] : '';
    $disabled = !empty($f['disabled']) ? ' disabled' : '';
    $readonly = !empty($f['readonly']) ? ' readonly' : '';
    $val = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    switch ($type) {
        case 'textarea':
            $rows = !empty($f['rows']) ? (int)$f['rows'] : 3;
            return '<div class="mb-3"><label class="form-label">'.htmlspecialchars($label).$asterisk.'</label>'
                .'<textarea name="'.$name.'" class="form-control" rows="'.$rows.'"'.$required.$placeholder.$disabled.$readonly.$extra.'>'.$val.'</textarea></div>';
        case 'select':
            $options = '';
            if (!empty($f['options'])) {
                $opt = $f['options'];
                $tblName = (string)($opt['table'] ?? '');
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $tblName)) {
                    $tblName = 'reqexam';
                }
                $optTable = table($tblName);
                $ident = function ($name) {
                    return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string)$name) ? $name : 'id';
                };
                $optValue = $ident($opt['value']);
                $optLabel = $ident((lang() === 'ar' && !empty($opt['label_ar'])) ? $opt['label_ar'] : $opt['label']);
                $optWhere = isset($opt['where']) ? $opt['where'] : ['omitted'=>0];
                $optOrder = isset($opt['order']) ? $opt['order'] : $optValue.' ASC';
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?$/i', $optOrder)) {
                    $optOrder = $optValue . ' ASC';
                }
                $wheres = []; $params = [];
                foreach ($optWhere as $k=>$v) {
                    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string)$k)) continue;
                    $wheres[]="$k=?"; $params[]=$v;
                }
                $whereSql = $wheres ? ' WHERE '.implode(' AND ',$wheres) : '';
                $rows = fetchAll("SELECT `$optValue`, `$optLabel` FROM `$optTable` $whereSql ORDER BY $optOrder", $params);
                foreach ($rows as $r) {
                    $sel = ($value != '' && $r[$optValue] == $value) ? ' selected' : '';
                    $options .= '<option value="'.htmlspecialchars($r[$optValue]).'"'.$sel.'>'
                        . htmlspecialchars($r[$optLabel], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
                }
            }
            $empty = !empty($f['allow_empty']) ? '<option value="">'.htmlspecialchars($f['empty_label'] ?? (lang()==='ar'?'-- اختر --':'-- Select --')).'</option>' : '';
            return '<div class="mb-3"><label class="form-label">'.htmlspecialchars($label).$asterisk.'</label>'
                .'<select name="'.$name.'" class="form-select"'.$required.$placeholder.$disabled.$readonly.$extra.'>'
                .$empty.$options.'</select></div>';
        case 'password':
            return '<div class="mb-3"><label class="form-label">'.htmlspecialchars($label).$asterisk.'</label>'
                .'<input type="password" name="'.$name.'" class="form-control"'.$placeholder.$disabled.$readonly.$extra.'></div>';
        case 'date':
            return '<div class="mb-3"><label class="form-label">'.htmlspecialchars($label).$asterisk.'</label>'
                .'<input type="date" name="'.$name.'" class="form-control" value="'.$val.'"'.$required.$disabled.$readonly.$extra.'></div>';
        case 'number':
            return '<div class="mb-3"><label class="form-label">'.htmlspecialchars($label).$asterisk.'</label>'
                .'<input type="number" name="'.$name.'" class="form-control" value="'.$val.'"'.$required.$placeholder.$disabled.$readonly.$extra.'></div>';
        case 'file':
            $accept = !empty($f['accept']) ? strtoupper(str_replace(' ', '', $f['accept'])) : '';
            $acceptAttr = '';
            if ($accept !== '') {
                $exts = array_map('trim', explode(',', $accept));
                $acceptAttr = ' accept=".' . implode(',.', array_map('strtolower', $exts)) . '"';
            }
            $requiredAttr = !empty($f['required']) ? ' required' : '';
            $html = '<div class="mb-3"><label class="form-label">'.htmlspecialchars($label).$asterisk.'</label>'
                .'<input type="file" name="'.$name.'" class="form-control"'.$acceptAttr.$requiredAttr.$disabled.$extra.'>';
            if ($val !== '') {
                $html .= '<div class="form-text mt-1"><i class="bi bi-paperclip me-1"></i><a href="'.htmlspecialchars($val, ENT_QUOTES).'" target="_blank">'.htmlspecialchars(basename($val)).'</a></div>';
            }
            $html .= '</div>';
            return $html;
        case 'hidden':
            return '<input type="hidden" name="'.$name.'" value="'.$val.'">';
        default:
            return '<div class="mb-3"><label class="form-label">'.htmlspecialchars($label).$asterisk.'</label>'
                .'<input type="text" name="'.$name.'" class="form-control" value="'.$val.'"'.$required.$placeholder.$disabled.$readonly.$extra.'></div>';
    }
}

// Notification helpers are part of the shared runtime so every module,
// including ESS and the navbar, can use them without extra includes.
require_once __DIR__ . '/notifications.php';
