<?php
/**
 * includes/config.sample.php
 *
 * Committed template for the site configuration (Config_Loader).
 *
 * Deployment: copy this file to `includes/config.php`, then fill in the real
 * database credentials and site constants for the target environment. The
 * working `includes/config.php` is excluded from version control (see the
 * repository .gitignore) so that credentials are never committed (Req 1.5).
 *
 * Requirements: 1.1 (define DB + site constants), 22.4 (production error flags).
 */

declare(strict_types=1);

// --- Database credentials (Req 1.1) -----------------------------------------
define('DB_NAME', 'u123_clinic');        // Hostinger database name
define('DB_USER', 'u123_clinic');        // Hostinger database user
define('DB_PASS', 'change-me');          // database password
define('DB_HOST', 'localhost');          // Hostinger MySQL host

// --- Site constants (Req 1.1) -----------------------------------------------
define('SITE_URL', 'https://example.com');           // canonical site URL, no trailing slash
define('BASE_PATH', dirname(__DIR__));               // filesystem root of public_html
define('UPLOAD_DIR', BASE_PATH . '/assets/uploads'); // upload storage directory
define('UPLOAD_URL', '/assets/uploads');             // public URL path for uploads
define('UPLOAD_MAX_BYTES', 3 * 1024 * 1024);         // 3 MB upload size cap
define('UPLOAD_ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// --- Environment (Req 22.4) -------------------------------------------------
// 'production' | 'development'
define('ENVIRONMENT', 'production');

// --- Error reporting bootstrap (Req 22.4) -----------------------------------
// In production: never display PHP errors to visitors; log them instead.
// In development: surface errors to aid local work.
if (ENVIRONMENT === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', dirname(BASE_PATH) . '/php-error.log'); // outside the web root
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}
