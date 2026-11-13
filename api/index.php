<?php
/**
 * UnitHR REST/JSON API
 * Base URL: /UnitHR/api/?action=...
 *
 * Authentication (one of):
 *   - HTTP Basic Auth:  Authorization: Basic base64(username:password)
 *   - Bearer token:     Authorization: Bearer <token>  (from action=login)
 *   - API key:          X-API-Key: <key>  (configured in api/config.php or env API_KEY)
 *
 * Standard response: { "success": bool, "message": str, "data": mixed, "meta": {...} }
 */
declare(strict_types=1);

// ---------- Bootstrap ----------
$root = dirname(__DIR__);
require_once $root . '/includes/db.php';

// Minimal config defaults (avoid session dependency)
$DB_HOST = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '127.0.0.1';
$DB_PORT = (int)($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? 3306);
$DB_USER = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? 'AdminNewp@ss01';
$DB_NAME = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'unithr';
$DB_PREFIX = $_ENV['DB_PREFIX'] ?? $_SERVER['DB_PREFIX'] ?? 'uhr_';
$API_KEY  = $_ENV['API_KEY']  ?? $_SERVER['API_KEY']  ?? '';

if (!function_exists('app')) {
    function app($key, $default = null) { return $default; }
}
if (!function_exists('prefix')) {
    function prefix() { global $DB_PREFIX; return $DB_PREFIX; }
}
if (!function_exists('table')) {
    function table($name) { return prefix() . $name; }
}

// DB connection
try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    json_out(false, 'Database connection failed: ' . $e->getMessage(), null, 500);
}

// ---------- Helpers ----------
function json_out($success, $message = '', $data = null, $code = 200, $meta = null) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
    $out = ['success' => (bool)$success, 'message' => $message, 'data' => $data];
    if ($meta !== null) $out['meta'] = $meta;
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_out(true, 'OK');
}

function get_input() {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) return $json;
    return $_POST + $_GET;
}

function param($key, $default = null) {
    $in = get_input();
    return $in[$key] ?? $_GET[$key] ?? $_POST[$key] ?? $default;
}

function fetch_all($sql, $params = []) {
    global $pdo;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function fetch_one($sql, $params = []) {
    global $pdo;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();
    return $r ?: null;
}

// ---------- Auth ----------
function request_headers() {
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        $norm = [];
        foreach ((array)$h as $k => $v) {
            $norm[strtolower($k)] = $v;
        }
        return $norm;
    }
    $norm = [];
    foreach ($_SERVER as $k => $v) {
        if (stripos($k, 'HTTP_') === 0) {
            $norm[strtolower(substr($k, 5))] = $v;
        }
    }
    return $norm;
}

function get_authorization_header() {
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) return $_SERVER['HTTP_AUTHORIZATION'];
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    $h = request_headers();
    return $h['authorization'] ?? '';
}

function get_bearer_token() {
    $h = get_authorization_header();
    if (stripos($h, 'Bearer ') === 0) return trim(substr($h, 7));
    return null;
}

function get_basic_creds() {
    $h = get_authorization_header();
    if (stripos($h, 'Basic ') === 0) {
        $dec = base64_decode(substr($h, 6), true);
        if ($dec !== false && strpos($dec, ':') !== false) {
            [$u, $p] = explode(':', $dec, 2);
            return [$u, $p];
        }
    }
    return null;
}

function get_api_key() {
    if (isset($_SERVER['HTTP_X_API_KEY'])) return $_SERVER['HTTP_X_API_KEY'];
    $h = request_headers();
    return $h['x-api-key'] ?? '';
}

function verify_user($username, $password) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM " . table('users') . " WHERE username = ? AND omitted = 0 LIMIT 1");
    $st->execute([$username]);
    $u = $st->fetch();
    if (!$u) return null;
    // passwords stored as bcrypt hash
    if (password_verify($password, $u['password'])) {
        return $u;
    }
    // fallback: plaintext compare (legacy)
    if ($u['password'] === $password) return $u;
    return null;
}

function issue_token($user) {
    $payload = json_encode(['id' => $user['id'], 'username' => $user['username'], 'ts' => time()]);
    $secret = $_ENV['API_SECRET'] ?? $_SERVER['API_SECRET'] ?? 'UnitHR-API-Secret-Change-Me';
    $sig = hash_hmac('sha256', $payload, $secret);
    return base64_encode($payload . '.' . $sig);
}

function verify_token($token) {
    $secret = $_ENV['API_SECRET'] ?? $_SERVER['API_SECRET'] ?? 'UnitHR-API-Secret-Change-Me';
    $dec = base64_decode($token, true);
    if ($dec === false) return null;
    $pos = strrpos($dec, '.');
    if ($pos === false) return null;
    $payload = substr($dec, 0, $pos);
    $sig = substr($dec, $pos + 1);
    if (!hash_equals(hash_hmac('sha256', $payload, $secret), $sig)) return null;
    $data = json_decode($payload, true);
    if (!is_array($data) || empty($data['id'])) return null;
    // token expiry: 24h
    if (time() - ($data['ts'] ?? 0) > 86400) return null;
    return $data;
}

function authenticate() {
    global $API_KEY;
    // API key
    $key = get_api_key();
    if ($key !== '' && $API_KEY !== '' && hash_equals($API_KEY, $key)) {
        return ['id' => 0, 'username' => 'api', 'auth' => 'apikey'];
    }
    // Bearer token
    $tok = get_bearer_token();
    if ($tok) {
        $data = verify_token($tok);
        if ($data) return array_merge($data, ['auth' => 'token']);
    }
    // Basic auth
    $creds = get_basic_creds();
    if ($creds) {
        $u = verify_user($creds[0], $creds[1]);
        if ($u) return array_merge($u, ['auth' => 'basic']);
    }
    return null;
}

// ---------- Router ----------
$action = $_GET['action'] ?? param('action', 'index');

// Public endpoints
if ($action === 'login' || $action === 'bind' || $action === 'auth') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_out(false, 'POST required', null, 405);
    }
    $in = get_input();
    $u = verify_user($in['username'] ?? $in['login'] ?? '', $in['password'] ?? '');
    if (!$u) json_out(false, 'Invalid credentials', null, 401);
    $token = issue_token($u);
    json_out(true, 'Authenticated', [
        'token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => 86400,
        'user' => [
            'id' => (int)$u['id'],
            'username' => $u['username'],
            'username_ar' => $u['username_ar'] ?? $u['username'],
            'is_admin' => (int)($u['is_admin'] ?? 0),
            'is_superadmin' => (int)($u['is_superadmin'] ?? 0),
        ],
    ]);
}

if ($action === 'index' || $action === '') {
    json_out(true, 'UnitHR API', [
        'version' => '2.3.0',
        'endpoints' => [
            'POST login/bind/auth  {username,password} -> token',
            'GET  me',
            'GET  employees  ?search=&dept=&status=&page=&limit=',
            'GET  employee   ?id=',
            'GET  departments',
            'GET  deptypes',
            'GET  degrees',
            'GET  groups',
            'GET  statuses',
            'GET  jobgroups',
            'GET  leaves     ?empid=',
            'GET  attendance ?empid=&from=&to=',
            'GET  archives   ?search=&empid=',
            'GET  promotions ?empid=',
            'GET  documenttypes',
        ],
    ]);
}

// Protected endpoints
$auth = authenticate();
if (!$auth) {
    json_out(false, 'Authentication required (Basic, Bearer, or X-API-Key)', null, 401);
}

try {
switch ($action) {
    case 'me':
        $me = fetch_one("SELECT id, rguid, username, email, departmentid, departmentname, departmentnamear, lastlogin, is_admin, is_superadmin, dtadded FROM " . table('users') . " WHERE id = ? AND omitted = 0 LIMIT 1", [(int)($auth['id'] ?? 0)]);
        if (!$me) $me = ['id' => (int)($auth['id'] ?? 0), 'username' => $auth['username'] ?? ''];
        json_out(true, 'OK', $me);

    case 'employees':
        $search = trim(param('search', ''));
        $dept = param('dept', param('depid', ''));
        $status = param('status', param('empstatusid', ''));
        $page = max(1, (int)param('page', 1));
        $limit = min(500, max(1, (int)param('limit', 50)));
        $offset = ($page - 1) * $limit;
        $where = 'omitted = 0';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (empfullname LIKE ? OR empfullnamear LIKE ? OR nid LIKE ? OR insuranceid LIKE ?)';
            $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]);
        }
        if ($dept !== '') { $where .= ' AND depid = ?'; $params[] = (int)$dept; }
        if ($status !== '') { $where .= ' AND empstatusid = ?'; $params[] = (int)$status; }
        $total = (int)fetch_one("SELECT COUNT(*) AS c FROM " . table('employeesmain') . " WHERE $where", $params)['c'];
        $rows = fetch_all("SELECT id, rguid, empfullname, empfullnamear, email, nid, insuranceid, biometricid,
            depid, depname, depnamear, empstatusid, empstatusname, empstatusnamear,
            currentdegreeid, currentdegreename, currentdegreenamear, empgender,
            dop, dor, degreeacquireddate, senioritydate, empgroup, empgroupname, empgroupnamear,
            emptypeid, emptypename, emptypenamear, jobgroupid, jobgroupname, jobgroupnamear,
            empsuperiorid, empsuperiorname, empsuperiornamear, dtadded, lastedit
            FROM " . table('employeesmain') . " WHERE $where ORDER BY empfullname LIMIT $limit OFFSET $offset", $params);
        json_out(true, 'OK', $rows, 200, ['page' => $page, 'limit' => $limit, 'total' => $total]);

    case 'employee':
        $id = (int)param('id', 0);
        if ($id <= 0) json_out(false, 'id required', null, 400);
        $row = fetch_one("SELECT * FROM " . table('employeesmain') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$id]);
        if (!$row) json_out(false, 'Not found', null, 404);
        json_out(true, 'OK', $row);

    case 'departments':
        $rows = fetch_all("SELECT id, depname, depnamear, depparentid, depparentname, depparentnamear, deptypeid, deptype, deptypear, managerid, managername, managernamear FROM " . table('depstructure') . " WHERE omitted = 0 ORDER BY depname");
        json_out(true, 'OK', $rows);

    case 'deptypes':
        $rows = fetch_all("SELECT id, deptypename, deptypenamear, deptypeparentid, deptypeparentname, deptypeparentnamear FROM " . table('deptypes') . " WHERE omitted = 0 ORDER BY deptypename");
        json_out(true, 'OK', $rows);

    case 'degrees':
        $rows = fetch_all("SELECT id, degreename, degreenamear, employeesgroup_id, employeesgroup, employeesgroupar, interimperiod, nextdegreeid, nextdegreename, nextdegreenamear, reqexam FROM " . table('empdegrees') . " WHERE omitted = 0 ORDER BY degreename");
        json_out(true, 'OK', $rows);

    case 'groups':
        $rows = fetch_all("SELECT id, empgroupname, empgroupnamear FROM " . table('employeesgroup') . " WHERE omitted = 0 ORDER BY empgroupname");
        json_out(true, 'OK', $rows);

    case 'statuses':
        $rows = fetch_all("SELECT id, empstatusname, empstatusnamear FROM " . table('empstatus') . " WHERE omitted = 0 ORDER BY empstatusname");
        json_out(true, 'OK', $rows);

    case 'jobgroups':
        $rows = fetch_all("SELECT id, jobgroupname, jobgroupnamear FROM " . table('jobgroups') . " WHERE omitted = 0 ORDER BY jobgroupname");
        json_out(true, 'OK', $rows);

    case 'types':
    case 'employeestypes':
        $rows = fetch_all("SELECT id, emptypename, emptypenamear FROM " . table('employeestypes') . " WHERE omitted = 0 ORDER BY emptypename");
        json_out(true, 'OK', $rows);

    case 'leaves':
        $empid = (int)param('empid', 0);
        if ($empid <= 0) json_out(false, 'empid required', null, 400);
        $balances = fetch_all("SELECT * FROM " . table('leavesbalances') . " WHERE empid = ? AND omitted = 0", [$empid]);
        $requests = fetch_all("SELECT * FROM " . table('empleaves') . " WHERE empid = ? AND omitted = 0 ORDER BY id DESC", [$empid]);
        json_out(true, 'OK', ['balances' => $balances, 'requests' => $requests]);

    case 'attendance':
        $empid = (int)param('empid', 0);
        $from = param('from', param('date_from', date('Y-m-01')));
        $to = param('to', param('date_to', date('Y-m-d')));
        $where = 'omitted = 0 AND attendance_date BETWEEN ? AND ?';
        $params = [$from, $to];
        if ($empid > 0) { $where .= ' AND employee_id = ?'; $params[] = $empid; }
        $rows = fetch_all("SELECT * FROM " . table('daily_attendance') . " WHERE $where ORDER BY attendance_date, employee_id", $params);
        json_out(true, 'OK', $rows, 200, ['from' => $from, 'to' => $to, 'count' => count($rows)]);

    case 'archives':
        $search = trim(param('search', ''));
        $empid = (int)param('empid', 0);
        $where = 'omitted = 0';
        $params = [];
        if ($empid > 0) { $where .= ' AND empid = ?'; $params[] = $empid; }
        if ($search !== '') {
            $where .= ' AND (documentname LIKE ? OR empfullname LIKE ? OR empfullnamear LIKE ? OR nid LIKE ?)';
            $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]);
        }
        $rows = fetch_all("SELECT * FROM " . table('emparchives') . " WHERE $where ORDER BY id DESC", $params);
        json_out(true, 'OK', $rows, 200, ['count' => count($rows)]);

    case 'promotions':
        $empid = (int)param('empid', 0);
        $where = 'omitted = 0';
        $params = [];
        if ($empid > 0) { $where .= ' AND empid = ?'; $params[] = $empid; }
        $rows = fetch_all("SELECT * FROM " . table('emppromotions') . " WHERE $where ORDER BY id DESC", $params);
        json_out(true, 'OK', $rows, 200, ['count' => count($rows)]);

    case 'documenttypes':
        $rows = fetch_all("SELECT id, doctypename, doctypenamear FROM " . table('documenttypes') . " WHERE omitted = 0 ORDER BY doctypename");
        json_out(true, 'OK', $rows);

    case 'notifications':
        $auth = authenticate();
        // user-specific inbox
        $uid = (int)($auth['id'] ?? 0);
        if ($uid <= 0) json_out(false, 'user context required', null, 400);
        $rows = fetch_all("SELECT * FROM " . table('notifications') . " WHERE recipient_id = ? AND omitted = 0 ORDER BY id DESC", [$uid]);
        json_out(true, 'OK', $rows);

    default:
        json_out(false, 'Unknown action: ' . $action, null, 404);
}
} catch (Throwable $e) {
    json_out(false, 'Server error: ' . $e->getMessage(), null, 500);
}