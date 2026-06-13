<?php
/**
 * includes/db.php
 *
 * DB_Connector — a single shared PDO connection instance for the whole site.
 *
 * The connection is created lazily on first call and cached in a function
 * static, so every component that calls db() receives the same PDO object
 * (Req 1.2). The connection uses the utf8mb4 charset and exception-based error
 * reporting (Req 1.3). If the connection cannot be established, the driver
 * message is written to the error log only and the visitor receives a generic
 * HTTP 500 message with no driver detail leaked (Req 1.4).
 *
 * Pages normally require includes/config.php before this file (see design.md
 * request lifecycle). As a safety net, the config is required here when its
 * constants are not yet defined.
 *
 * Requirements: 1.2, 1.3, 1.4.
 */

declare(strict_types=1);

// Safety net: ensure configuration constants are available (Req 1.1 deps).
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Return the single shared PDO connection instance.
 *
 * On the first call the connection is opened and cached; subsequent calls
 * return the same instance.
 *
 * @return PDO The shared PDO connection.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn  = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    } catch (PDOException $ex) {
        // Log the real driver message for operators; never expose it to visitors (Req 1.4).
        error_log('[DB] connection failed: ' . $ex->getMessage());
        http_response_code(500);
        exit('Service temporarily unavailable.');
    }

    return $pdo;
}
