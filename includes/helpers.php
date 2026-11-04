<?php
/**
 * UnitHR - Helper Functions
 * Reversible encryption, TOTP OTP, Arabic normalization, permissions,
 * audit shadow logging, and offline export (CSV / XLSX / Word / PDF).
 */

require_once __DIR__ . '/db.php';

/**
 * Get a translated string by key.
 * Falls back to the key itself if not found.
 * Language file is loaded dynamically based on current session language.
 */
function L($key, ...$args) {
    static $loadedLang = null;
    static $strings = [];

    $currentLang = $_SESSION['lang'] ?? 'ar';
    if ($loadedLang !== $currentLang) {
        $langFile = __DIR__ . '/../lang/' . ($currentLang === 'ar' ? 'ar' : 'en') . '.php';
        $strings = [];
        if (file_exists($langFile)) {
            $lang = [];
            include $langFile;
            $strings = $lang ?? [];
        }
        $loadedLang = $currentLang;
        $_SESSION['lang_strings'] = $strings;
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
    return $_SESSION['user'] ?? null;
}

function current_user_id() {
    return (int)($_SESSION['user_id'] ?? 0);
}

function is_logged_in() {
    return !empty($_SESSION['user_id']);
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
    return $_SESSION['user_permissions'] ?? [];
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
        header('Location: index.php?action=login');
        exit;
    }
}

function require_otp() {
    require_login();
    if (!is_app_staff()) {
        return;
    }
    if (empty($_SESSION['otp_verified'])) {
        // Re-authenticate instead of looping on a non-existent 'otp' action
        header('Location: index.php?action=login');
        exit;
    }
}

function require_permission($module, $action = 'view') {
    require_login();
    if (!user_can($module, $action)) {
        $_SESSION['error'] = 'You do not have permission to ' . $action . ' in module: ' . htmlspecialchars($module);
        header('Location: index.php?module=home');
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
function esc_csv($v) {
    $v = (string)$v;
    if (preg_match('/[",\r\n]/', $v)) {
        return '"' . str_replace('"', '""', $v) . '"';
    }
    return $v;
}

function export_csv($filename, $headers, $rows) {
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
// Minimal PDF generator (best-effort; Latin text renders well, Arabic is
// shown as best-effort glyphs without shaping in offline mode).
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
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function get_flash() {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
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
                .'<textarea name="'.$name.'" class="form-control" rows="'.$rows.'"'.$required.$placeholder.$disabled.$readonly.'>'.$val.'</textarea></div>';
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
                .'<select name="'.$name.'" class="form-select"'.$required.$placeholder.$disabled.$readonly.'>'
                .$empty.$options.'</select></div>';
        case 'password':
            return '<div class="mb-3"><label class="form-label">'.htmlspecialchars($label).$asterisk.'</label>'
                .'<input type="password" name="'.$name.'" class="form-control"'.$placeholder.$disabled.$readonly.'></div>';
        case 'date':
            return '<div class="mb-3"><label class="form-label">'.htmlspecialchars($label).$asterisk.'</label>'
                .'<input type="date" name="'.$name.'" class="form-control" value="'.$val.'"'.$required.$disabled.$readonly.'></div>';
        case 'number':
            return '<div class="mb-3"><label class="form-label">'.htmlspecialchars($label).$asterisk.'</label>'
                .'<input type="number" name="'.$name.'" class="form-control" value="'.$val.'"'.$required.$placeholder.$disabled.$readonly.'></div>';
        case 'hidden':
            return '<input type="hidden" name="'.$name.'" value="'.$val.'">';
        default:
            return '<div class="mb-3"><label class="form-label">'.htmlspecialchars($label).$asterisk.'</label>'
                .'<input type="text" name="'.$name.'" class="form-control" value="'.$val.'"'.$required.$placeholder.$disabled.$readonly.'></div>';
    }
}
