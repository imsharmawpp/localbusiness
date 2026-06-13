<?php
/**
 * admin/auth.php
 *
 * Auth_Guard — the session guard included at the very top of every admin page.
 *
 * Responsibilities:
 *   1. Configure the session cookie BEFORE the session starts so the hardened
 *      cookie flags actually apply to the session cookie (Req 5.3, 5.4):
 *        - httponly = true            (cookie unreadable from JavaScript)
 *        - samesite = 'Lax'           (mitigates cross-site request forgery)
 *        - secure   = HTTPS only       (set only when the request is over HTTPS)
 *        - path     = '/'             (cookie valid across the whole site)
 *   2. Start the session.
 *   3. Redirect any unauthenticated request that reaches an admin page other
 *      than the login (admin/index.php) back to the login (Req 5.5).
 *
 * This file MUST be required before includes/functions.php (and any other code
 * that may start a session), because the session-backed helpers there call
 * session_start() lazily via ensure_session(). By configuring the cookie params
 * and starting the session here first, those helpers reuse this already-active
 * session and the hardened cookie flags are preserved.
 *
 * Requirements: 5.3, 5.4, 5.5.
 */

declare(strict_types=1);

// Configure the session cookie and start the session. Guard against an
// already-active session so including this file more than once (or after a
// helper has started the session) does not emit a warning.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        // Secure flag only when the request is genuinely served over HTTPS
        // (Req 5.4); on plain HTTP the cookie would otherwise never be sent.
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'path'     => '/',
    ]);
    session_start();
}

// Guard every admin page except the login itself. The login page is
// admin/index.php, where an unauthenticated visitor MUST be allowed through so
// they can sign in; redirecting there would cause a loop (Req 5.5).
$adminScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($adminScript !== 'index.php' && empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
