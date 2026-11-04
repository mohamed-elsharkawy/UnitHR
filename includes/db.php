<?php
/**
 * UnitHR - Database Connection (MySQL only, per requirements)
 * Lazy PDO singleton. Requires the pdo_mysql extension.
 */

require_once __DIR__ . '/config.php';

$db = null;

function db() {
    global $db;
    if ($db === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            app('dbhost'),
            app('dbport'),
            app('dbname')
        );
        try {
            $db = new PDO($dsn, app('dbuser'), app('dbpass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            $db = false; // mark as unavailable
            $_SESSION['db_error'] = $e->getMessage();
        }
    }
    return $db;
}

function db_available() {
    $pdo = db();
    return $pdo !== false && $pdo !== null;
}

function db_error() {
    return $_SESSION['db_error'] ?? null;
}

/**
 * Execute a prepared statement. Returns PDOStatement or false on failure.
 */
function q($sql, $params = []) {
    $pdo = db();
    if (!$pdo) {
        return false;
    }
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        $_SESSION['db_error'] = $e->getMessage();
        return false;
    }
}

function fetchAll($sql, $params = []) {
    $stmt = q($sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

function fetchRow($sql, $params = []) {
    $stmt = q($sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

function lastInsertId() {
    $pdo = db();
    return $pdo ? (int)$pdo->lastInsertId() : 0;
}
