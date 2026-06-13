<?php
/**
 * admin/logout.php
 *
 * Admin_Logout — destroys the authenticated admin session and returns the
 * administrator to the login page (Req 5.6).
 *
 * auth.php starts the hardened session first. Because this script is not the
 * login page, the guard would normally require an authenticated session, but
 * logging out is harmless when unauthenticated and still ends at the login.
 *
 * Requirements: 5.6.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';

// Clear all session data.
$_SESSION = [];

// Remove the session cookie itself so no stale identifier lingers in the
// browser, reusing the active session's cookie parameters.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

// Tear down the session on the server.
session_destroy();

// Back to the login (Req 5.6).
header('Location: index.php');
exit;
