<?php
/**
 * UnitHR REST/JSON API
 * Base URL: /UnitHR/api/?action=...
 *
 * Authentication (one of):
 *   - API token:  X-API-Token: <api_token>  (recommended; persistent per user)
 *   - Bearer:     Authorization: Bearer <api_token | signed token from action=login>
 *   - Basic:      Authorization: Basic base64(username:password)  (legacy clients)
 *   - API key:    X-API-Key: <key>  (optional service key; no user context)
 *
 * Every account has a persistent token in uhr_users.api_token. It starts as the
 * account rguid and can be rotated from My Profile or action=regenerate_token.
 * A rotated token immediately invalidates the old API token and every signed
 * bearer token issued before the rotation.
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
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-API-Token');
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
    if (isset($_SERVER['HTTP_X_API_KEY'])) return (string)$_SERVER['HTTP_X_API_KEY'];
    $h = request_headers();
    return (string)($h['x-api-key'] ?? '');
}

/**
 * Persistent per-user token sent in X-API-Token (X-Auth-Token is accepted as
 * an alias for proxies that reserve the former header).
 */
function get_api_token() {
    $h = request_headers();
    foreach (['x-api-token', 'x-auth-token'] as $name) {
        if (!empty($h[$name])) {
            return trim((string)$h[$name]);
        }
    }
    return '';
}

function api_token_hash($token) {
    return substr(hash('sha256', (string)$token), 0, 32);
}

function find_user_by_api_token($token) {
    global $pdo;
    $token = trim((string)$token);
    if ($token === '') return null;
    // BINARY keeps token comparison case-sensitive even though the users
    // table collation is case-insensitive.
    $st = $pdo->prepare("SELECT * FROM " . table('users') . " WHERE BINARY api_token = ? AND omitted = 0 LIMIT 1");
    $st->execute([$token]);
    $u = $st->fetch();
    return $u ?: null;
}

/**
 * Make sure the account has an API token. Existing accounts created before
 * the column existed start with their rguid, as required.
 */
function ensure_api_token($user) {
    global $pdo;
    $token = trim((string)($user['api_token'] ?? ''));
    if ($token !== '') return $token;
    $token = trim((string)($user['rguid'] ?? ''));
    if ($token === '') $token = bin2hex(random_bytes(32));
    $st = $pdo->prepare("SELECT id FROM " . table('users') . " WHERE BINARY api_token = ? AND id <> ? LIMIT 1");
    $st->execute([$token, (int)$user['id']]);
    if ($st->fetch()) $token = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE " . table('users') . " SET api_token = ? WHERE id = ?")
        ->execute([$token, (int)$user['id']]);
    return $token;
}

function verify_user($username, $password) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM " . table('users') . " WHERE username = ? AND omitted = 0 LIMIT 1");
    $st->execute([$username]);
    $u = $st->fetch();
    if (!$u) return null;
    // Passwords must be stored with password_hash(). Plaintext fallback was
    // removed because it defeats the purpose of hashing credentials.
    return password_verify((string)$password, (string)$u['password']) ? $u : null;
}

function issue_token($user) {
    $payload = json_encode([
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'ts' => time(),
        'tk' => api_token_hash($user['api_token'] ?? ''),
    ]);
    $secret = $_ENV['API_SECRET'] ?? $_SERVER['API_SECRET'] ?? 'UnitHR-API-Secret-Change-Me';
    $sig = hash_hmac('sha256', $payload, $secret);
    return base64_encode($payload . '.' . $sig);
}

function verify_token($token) {
    global $pdo;
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
    // The signed token is only valid while the account's current API token
    // matches the one it was issued against. Rotating the token revokes it.
    $st = $pdo->prepare("SELECT id, username, api_token FROM " . table('users') . " WHERE id = ? AND omitted = 0 LIMIT 1");
    $st->execute([(int)$data['id']]);
    $user = $st->fetch();
    if (!$user || empty($user['api_token'])) return null;
    if (!hash_equals(api_token_hash($user['api_token']), (string)($data['tk'] ?? ''))) return null;
    $data['username'] = $user['username'];
    return $data;
}

function authenticate() {
    global $API_KEY;
    // 1) Dedicated persistent API token (recommended).
    $apiToken = get_api_token();
    if ($apiToken !== '') {
        $u = find_user_by_api_token($apiToken);
        return $u ? array_merge($u, ['auth' => 'api_token']) : null;
    }
    // 2) Optional service-wide API key (no user context).
    $key = get_api_key();
    if ($key !== '' && $API_KEY !== '' && hash_equals($API_KEY, $key)) {
        return ['id' => 0, 'username' => 'api', 'auth' => 'apikey'];
    }
    // 3) Bearer: short-lived signed token or a persistent API token directly.
    $tok = get_bearer_token();
    if ($tok) {
        $data = verify_token($tok);
        if ($data) return array_merge($data, ['auth' => 'token']);
        $u = find_user_by_api_token($tok);
        if ($u) return array_merge($u, ['auth' => 'api_token']);
    }
    // 4) Backward-compatible HTTP Basic authentication.
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
if ($action === 'login' || $action === 'bind' || $action === 'auth' || $action === 'ldap_bind') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_out(false, 'POST required', null, 405);
    }
    $in = get_input();
    $u = verify_user($in['username'] ?? $in['login'] ?? '', $in['password'] ?? '');
    if (!$u) json_out(false, 'Invalid credentials', null, 401);
    // Start with the account rguid the first time, then reuse the stored token.
    $apiToken = ensure_api_token($u);
    $u['api_token'] = $apiToken;
    $token = issue_token($u);
    $resp = [
        'token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => 86400,
        'api_token' => $apiToken,
        'api_token_header' => 'X-API-Token',
        'user' => [
            'id' => (int)$u['id'],
            'username' => $u['username'],
            'username_ar' => $u['username_ar'] ?? $u['username'],
            'is_admin' => (int)($u['is_admin'] ?? 0),
            'is_superadmin' => (int)($u['is_superadmin'] ?? 0),
        ],
    ];
    if ($action === 'ldap_bind' || $action === 'bind') {
        $resp['dn'] = 'uid=' . $u['username'] . ',ou=users,dc=unithr,dc=local';
        $resp['message'] = 'Bind successful';
    }
    json_out(true, $action === 'ldap_bind' ? 'Bind successful' : 'Authenticated', $resp);
}

if ($action === 'index' || $action === '') {
    json_out(true, 'UnitHR API', [
        'version' => '2.7.0',
        'authentication' => [
            'X-API-Token: <api_token> (recommended, persistent)',
            'Authorization: Bearer <api_token | signed token from action=login>',
            'Authorization: Basic base64(username:password) (legacy)',
            'X-API-Key: <key> (optional service key, no user context)',
        ],
        'endpoints' => [
            'POST login/bind/auth/ldap_bind  {username,password} -> bearer + api_token + dn',
            'GET  me',
            'GET  profile',
            'POST change_password {current_password,new_password}',
            'POST regenerate_token -> new api_token',
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
            'GET  payroll_types, payroll_type ?id=',
            'GET  payroll_employee_setup ?empid=',
            'GET  payroll_runs ?year=&status=',
            'GET  payroll_run ?id=',
            'GET  payroll_tax_years, payroll_tax_brackets ?year=',
            'GET  payroll_insurance_rules',
            'GET  payroll_year_end ?year=',
            'GET  search ?base=&filter=&limit=',
            'GET  whoami',
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
        $me = fetch_one("SELECT id, rguid, username, email, departmentid, departmentname, departmentnamear, lastlogin, otp_enabled, is_admin, is_superadmin, dtadded FROM " . table('users') . " WHERE id = ? AND omitted = 0 LIMIT 1", [(int)($auth['id'] ?? 0)]);
        if (!$me) $me = ['id' => (int)($auth['id'] ?? 0), 'username' => $auth['username'] ?? ''];
        json_out(true, 'OK', $me);

    case 'profile':
        $uid = (int)($auth['id'] ?? 0);
        if ($uid <= 0) json_out(false, 'user context required', null, 400);
        $me = fetch_one("SELECT id, rguid, username, email, departmentid, departmentname, departmentnamear, lastlogin, otp_enabled, is_admin, is_superadmin, dtadded FROM " . table('users') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$uid]);
        if (!$me) json_out(false, 'Not found', null, 404);
        $employee = fetch_one("SELECT id, empfullname, empfullnamear, email, nid, insuranceid
            FROM " . table('employeesmain') . "
            WHERE omitted = 0 AND (nid = ? OR insuranceid = ? OR LOWER(email) = ?)
            ORDER BY CASE WHEN nid = ? THEN 0 WHEN insuranceid = ? THEN 1 ELSE 2 END, id
            LIMIT 1",
            [$me['username'], $me['username'], strtolower((string)$me['email']), $me['username'], $me['username']]);
        $me['linked_employee'] = $employee ?: null;
        json_out(true, 'OK', $me);

    case 'change_password':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, 'POST required', null, 405);
        $uid = (int)($auth['id'] ?? 0);
        if ($uid <= 0) json_out(false, 'user context required', null, 400);
        $in = get_input();
        $current = (string)($in['current_password'] ?? '');
        $new = (string)($in['new_password'] ?? '');
        if ($current === '' || $new === '') json_out(false, 'current_password and new_password are required', null, 400);
        if (strlen($new) < 8) json_out(false, 'The new password must be at least 8 characters long', null, 400);
        $u = fetch_one("SELECT id, username, password FROM " . table('users') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$uid]);
        if (!$u || !password_verify($current, (string)$u['password'])) {
            json_out(false, 'Current password is incorrect', null, 401);
        }
        if ($current === $new) json_out(false, 'Choose a password different from the current one', null, 400);
        $pdo->prepare("UPDATE " . table('users') . " SET password = ?, lastedit = NOW(), editedbyid = ?, editedby = ? WHERE id = ?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $uid, $u['username'], $uid]);
        json_out(true, 'Password changed successfully', ['id' => $uid]);

    case 'regenerate_token':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, 'POST required', null, 405);
        $uid = (int)($auth['id'] ?? 0);
        if ($uid <= 0) json_out(false, 'user context required', null, 400);
        $newToken = '';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = bin2hex(random_bytes(32));
            $duplicate = fetch_one("SELECT id FROM " . table('users') . " WHERE BINARY api_token = ? AND id <> ? LIMIT 1", [$candidate, $uid]);
            if (!$duplicate) {
                $newToken = $candidate;
                break;
            }
        }
        if ($newToken === '') json_out(false, 'Could not generate a token', null, 500);
        $pdo->prepare("UPDATE " . table('users') . " SET api_token = ?, lastedit = NOW(), editedbyid = ?, editedby = ? WHERE id = ?")
            ->execute([$newToken, $uid, (string)($auth['username'] ?? 'api'), $uid]);
        json_out(true, 'API token regenerated. The previous token is now invalid.', [
            'api_token' => $newToken,
            'api_token_header' => 'X-API-Token',
        ]);

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

    // ---------- Payroll ----------
    case 'payroll_types':
        $rows = fetch_all("SELECT id, kind, code, name, name_ar, calc_method, rate, amount, before_tax, before_insurance, max_amount, min_amount, sort_order, enabled FROM " . table('payroll_types') . " WHERE omitted = 0 ORDER BY kind, sort_order");
        json_out(true, 'OK', $rows);

    case 'payroll_type':
        $id = (int)param('id', 0);
        if ($id <= 0) json_out(false, 'id required', null, 400);
        $row = fetch_one("SELECT * FROM " . table('payroll_types') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$id]);
        if (!$row) json_out(false, 'Not found', null, 404);
        json_out(true, 'OK', $row);

    case 'payroll_employee_setup':
        $empid = (int)param('empid', param('id', 0));
        if ($empid <= 0) json_out(false, 'empid required', null, 400);
        $settings = fetch_one("SELECT * FROM " . table('payroll_employee_settings') . " WHERE empid = ? AND omitted = 0 ORDER BY id DESC LIMIT 1", [$empid]);
        $assignments = fetch_all("SELECT * FROM " . table('payroll_employee_types') . " WHERE empid = ? AND omitted = 0", [$empid]);
        $values = fetch_all("SELECT * FROM " . table('payroll_employee_type_values') . " WHERE empid = ? AND omitted = 0 ORDER BY payroll_year DESC, payroll_month DESC", [$empid]);
        json_out(true, 'OK', ['settings' => $settings, 'assignments' => $assignments, 'values' => $values]);

    case 'payroll_runs':
        $year = (int)param('year', 0);
        $status = trim(param('status', ''));
        $where = 'omitted = 0';
        $params = [];
        if ($year > 0) { $where .= ' AND payroll_year = ?'; $params[] = $year; }
        if ($status !== '') { $where .= ' AND status = ?'; $params[] = $status; }
        $rows = fetch_all("SELECT id, payroll_year, payroll_month, period_start, period_end, status, employee_count, gross_total, taxable_total, tax_total, insurance_total, employer_insurance_total, deductions_total, net_total, finalized_at, finalizedby FROM " . table('payroll_runs') . " WHERE $where ORDER BY payroll_year DESC, payroll_month DESC", $params);
        json_out(true, 'OK', $rows, 200, ['count' => count($rows)]);

    case 'payroll_run':
        $id = (int)param('id', 0);
        if ($id <= 0) json_out(false, 'id required', null, 400);
        $run = fetch_one("SELECT * FROM " . table('payroll_runs') . " WHERE id = ? AND omitted = 0 LIMIT 1", [$id]);
        if (!$run) json_out(false, 'Not found', null, 404);
        $items = fetch_all("SELECT * FROM " . table('payroll_run_items') . " WHERE run_id = ? AND omitted = 0 ORDER BY empfullname", [$id]);
        foreach ($items as &$it) {
            $it['lines'] = fetch_all("SELECT * FROM " . table('payroll_run_lines') . " WHERE item_id = ? AND omitted = 0 ORDER BY sort_order, id", [(int)$it['id']]);
        }
        unset($it);
        json_out(true, 'OK', ['run' => $run, 'items' => $items]);

    case 'payroll_tax_years':
        $rows = fetch_all("SELECT tax_year, personal_exemption, round_to, enabled FROM " . table('payroll_tax_years') . " WHERE omitted = 0 ORDER BY tax_year");
        json_out(true, 'OK', $rows);

    case 'payroll_tax_brackets':
        $year = (int)param('year', 0);
        if ($year <= 0) json_out(false, 'year required', null, 400);
        $rows = fetch_all("SELECT * FROM " . table('payroll_tax_brackets') . " WHERE tax_year = ? AND omitted = 0 ORDER BY schedule_min, bracket_order", [$year]);
        json_out(true, 'OK', $rows);

    case 'payroll_insurance_rules':
        $rows = fetch_all("SELECT insurance_year, employee_rate, employer_rate, min_base, max_base, enabled FROM " . table('payroll_insurance_rules') . " WHERE omitted = 0 ORDER BY insurance_year");
        json_out(true, 'OK', $rows);

    case 'payroll_year_end':
        $year = (int)param('year', date('Y'));
        $rows = fetch_all("SELECT i.empid, MAX(i.empfullname) AS empfullname, MAX(i.empfullnamear) AS empfullnamear, COUNT(*) AS months, SUM(i.gross_pay) AS gross_total, SUM(i.taxable_base) AS taxable_total, SUM(i.tax_amount) AS tax_total, SUM(i.insurance_base) AS insurance_base_total, SUM(i.insurance_amount) AS insurance_total, SUM(i.employer_insurance) AS employer_insurance_total, SUM(i.deductions_total) AS deductions_total, SUM(i.net_salary) AS net_total FROM " . table('payroll_run_items') . " i INNER JOIN " . table('payroll_runs') . " r ON r.id=i.run_id WHERE r.payroll_year=? AND r.status='final' AND r.omitted=0 AND i.omitted=0 GROUP BY i.empid ORDER BY empfullname", [$year]);
        json_out(true, 'OK', ['year' => $year, 'rows' => $rows]);

    // ---------- LDAP/SSO-style helpers ----------
    case 'ldap_bind':
    case 'bind':
        $in = get_input();
        $u = verify_user($in['username'] ?? $in['login'] ?? $in['dn'] ?? '', $in['password'] ?? '');
        if (!$u) json_out(false, 'Invalid credentials', null, 401);
        $token = issue_token($u);
        json_out(true, 'Bind successful', ['dn' => 'uid=' . $u['username'] . ',ou=users,dc=unithr,dc=local', 'token' => $token, 'token_type' => 'Bearer', 'expires_in' => 86400]);

    case 'search':
        $base = param('base', param('ou', ''));
        $filter = trim(param('filter', param('q', '')));
        $limit = min(500, max(1, (int)param('limit', 50)));
        if (stripos($base, 'employee') !== false) {
            $where = 'omitted = 0';
            $params = [];
            if ($filter !== '') {
                $where .= ' AND (empfullname LIKE ? OR empfullnamear LIKE ? OR nid LIKE ? OR insuranceid LIKE ?)';
                $params = ["%{$filter}%", "%{$filter}%", "%{$filter}%", "%{$filter}%"];
            }
            $rows = fetch_all("SELECT id, empfullname, empfullnamear, nid, insuranceid, depname, depnamear, empstatusname, empstatusnamear, currentdegreename, currentdegreenamear, email FROM " . table('employeesmain') . " WHERE $where ORDER BY empfullname LIMIT $limit", $params);
            json_out(true, 'OK', ['base' => $base, 'filter' => $filter, 'count' => count($rows), 'entries' => $rows]);
        } else {
            $where = 'omitted = 0';
            $params = [];
            if ($filter !== '') {
                $where .= ' AND (username LIKE ? OR email LIKE ?)';
                $params = ["%{$filter}%", "%{$filter}%"];
            }
            $rows = fetch_all("SELECT id, username, email, departmentname, departmentnamear, is_admin, is_superadmin, dtadded FROM " . table('users') . " WHERE $where ORDER BY username LIMIT $limit", $params);
            json_out(true, 'OK', ['base' => $base, 'filter' => $filter, 'count' => count($rows), 'entries' => $rows]);
        }

    case 'whoami':
        $auth = authenticate();
        if (!$auth) json_out(false, 'Authentication required', null, 401);
        $me = fetch_one("SELECT id, username, email, is_admin, is_superadmin FROM " . table('users') . " WHERE id = ? AND omitted = 0 LIMIT 1", [(int)($auth['id'] ?? 0)]);
        json_out(true, 'OK', $me ?: $auth);

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
    // Keep SQL details and stack traces out of API responses.
    error_log('UnitHR API error: ' . $e->getMessage());
    json_out(false, 'Server error', null, 500);
}