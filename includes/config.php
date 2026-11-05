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
// Database table prefix (defined as a session variable per requirements)
// All tables use the 4-char prefix "uhr_" followed by underscore.
// -------------------------------------------------------------------------
if (empty($_SESSION['dbprefix'])) {
    $_SESSION['dbprefix'] = 'uhr_';
}
if (empty($_SESSION['dbname'])) {
    $_SESSION['dbname'] = 'unithr';
}

// -------------------------------------------------------------------------
// Application information
// -------------------------------------------------------------------------
$_SESSION['appname']        = 'UnitHR';
$_SESSION['apppath']        = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
$_SESSION['applogo']        = 'assets/img/UnitHR.png';
$_SESSION['appversion']     = '2.0.0';
$_SESSION['appauthor']      = 'UnitHR Team';
$_SESSION['appemail']       = 'hr@unithr.local';

$_SESSION['devtool_disabled'] = 1;

// Default language is Arabic per requirements ("fully in Arabic (Default Language)").
if (empty($_SESSION['lang'])) {
    $_SESSION['lang'] = 'ar';
}

// Encryption key for reversible password encryption (function can decrypt if needed).
if (empty($_SESSION['encrypt_key'])) {
    $_SESSION['encrypt_key'] = 'UnitHR-Secret-Key-2026-Change-In-Production';
}

// Database configuration (MySQL/MariaDB, per requirements)
// Override via environment variables for production; defaults suit local WAMP/dev.
// WAMP: MySQL on 3306 with root/AdminNewp@ss01
$_SESSION['dbhost']     = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '127.0.0.1';
$_SESSION['dbport']     = (int)($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? 3306);
$_SESSION['dbuser']     = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root';
$_SESSION['dbpass']     = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? 'AdminNewp@ss01';

/**
 * Helper to quickly access app settings stored in session.
 */
function app($key, $default = null) {
    return $_SESSION[$key] ?? $default;
}

/**
 * Return the configured table prefix (session variable).
 */
function prefix() {
    return $_SESSION['dbprefix'] ?? 'uhr_';
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
    return $_SESSION['lang'] ?? 'ar';
}

function is_rtl() {
    return lang() === 'ar';
}

/**
 * Direction attribute value for the html tag.
 */
function dir_attr() {
    return is_rtl() ? 'rtl' : 'ltr';
}
