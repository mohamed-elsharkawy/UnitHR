<?php
/**
 * UnitHR - Application Configuration
 * Built on OpenFrame PHP Framework (https://github.com/mohamed-elsharkawy/OpenFrame)
 * Stores application information in session variables.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------------------------------------------
// Per-application session namespace.
// A single server may host several OpenFrame-based applications. PHP shares
// one session (file + cookie) across the whole host, so flat keys such as
// $_SESSION['user_id'] would collide between apps. Every value therefore
// lives inside a nested array:
//
//     $_SESSION[UHR_SESSION_KEY]['user_id']
//
// The key is derived from the application folder name (/UnitHR/ -> "unithr"),
// so each hosted copy is isolated automatically. It can be overridden by
// defining UHR_SESSION_KEY before this file is loaded.
// -------------------------------------------------------------------------
if (!defined('UHR_SESSION_KEY')) {
    $uhrNsDir = basename(rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\'));
    $uhrNsKey = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', (string)$uhrNsDir));
    if ($uhrNsKey === '' || $uhrNsKey === '_') {
        $uhrNsKey = 'unithr'; // fallback: web-root install or CLI
    }
    define('UHR_SESSION_KEY', $uhrNsKey);
}
$_SESSION[UHR_SESSION_KEY] = $_SESSION[UHR_SESSION_KEY] ?? [];

// -------------------------------------------------------------------------
// Database table prefix (defined as a session variable per requirements)
// All tables use the 4-char prefix "uhr_" followed by underscore.
// -------------------------------------------------------------------------
if (empty($_SESSION[UHR_SESSION_KEY]['dbprefix'])) {
    $_SESSION[UHR_SESSION_KEY]['dbprefix'] = 'uhr_';
}
if (empty($_SESSION[UHR_SESSION_KEY]['dbname'])) {
    $_SESSION[UHR_SESSION_KEY]['dbname'] = 'unithr';
}

// -------------------------------------------------------------------------
// Application information
// -------------------------------------------------------------------------
$_SESSION[UHR_SESSION_KEY]['appname']        = 'UnitHR';
$_SESSION[UHR_SESSION_KEY]['apppath']        = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
$_SESSION[UHR_SESSION_KEY]['applogo']        = 'assets/img/UnitHR.png';
$_SESSION[UHR_SESSION_KEY]['appversion']     = '2.3.0';
$_SESSION[UHR_SESSION_KEY]['appauthor']      = 'Mohamed El-Sharkawy';
$_SESSION[UHR_SESSION_KEY]['appemail']       = 'pro.msharkawy@gmail.com';

// Default language is Arabic per requirements ("fully in Arabic (Default Language)").
if (empty($_SESSION[UHR_SESSION_KEY]['lang'])) {
    $_SESSION[UHR_SESSION_KEY]['lang'] = 'ar';
}

// Encryption key for reversible password encryption (function can decrypt if needed).
if (empty($_SESSION[UHR_SESSION_KEY]['encrypt_key'])) {
    $_SESSION[UHR_SESSION_KEY]['encrypt_key'] = 'UnitHR-Secret-Key-2026-Change-In-Production';
}

// Database configuration (MySQL/MariaDB, per requirements)
// Override via environment variables for production; defaults suit local WAMP/dev.
// WAMP: MySQL on 3306 with root/AdminNewp@ss01
$_SESSION[UHR_SESSION_KEY]['dbhost']     = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '127.0.0.1';
$_SESSION[UHR_SESSION_KEY]['dbport']     = (int)($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? 3306);
$_SESSION[UHR_SESSION_KEY]['dbuser']     = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root';
$_SESSION[UHR_SESSION_KEY]['dbpass']     = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? 'AdminNewp@ss01';

/**
 * Helper to quickly access app settings stored in the nested session array.
 */
function app($key, $default = null) {
    return $_SESSION[UHR_SESSION_KEY][$key] ?? $default;
}

/**
 * Return the configured table prefix (session variable).
 */
function prefix() {
    return $_SESSION[UHR_SESSION_KEY]['dbprefix'] ?? 'uhr_';
}

/**
 * Return a fully-qualified table name with prefix.
 */
function table($name) {
    return prefix() . $name;
}

/**
 * Current application language ('ar' default, or 'en').
 */
function lang() {
    return $_SESSION[UHR_SESSION_KEY]['lang'] ?? 'ar';
}

function is_rtl() {
    if (function_exists('L')) {
        $dir = L('dir');
        if ($dir === 'rtl' || $dir === 'ltr') {
            return $dir === 'rtl';
        }
    }
    return lang() === 'ar';
}

/**
 * Direction attribute value for the html tag.
 */
function dir_attr() {
    return is_rtl() ? 'rtl' : 'ltr';
}
