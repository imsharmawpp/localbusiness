<?php
/**
 * admin/index.php
 *
 * Admin_Login — the admin sign-in page.
 *
 * Flow:
 *   - auth.php starts the hardened session. On the login page the guard does
 *     NOT redirect (basename === 'index.php'), so visitors can authenticate.
 *   - If an administrator is already authenticated, redirect to the dashboard.
 *   - On POST: verify the CSRF token, then look up the submitted email and
 *     verify the password against admin_users.password_hash via password_verify
 *     (Req 5.1). On any failure show a single generic authentication-failure
 *     message so we never reveal whether the email or the password was wrong
 *     (Req 5.2). On success store the admin id in the session and redirect to
 *     the dashboard (PRG).
 *
 * Requirements: 5.1, 5.2, 6.1, 6.2.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

// Already signed in -> straight to the dashboard.
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF gate before doing any work (Req 6.1, 6.2).
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad request');
    }

    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    // Generic message reused for every failure mode (Req 5.2): unknown email,
    // wrong password, or empty input all look identical to the client.
    $genericFailure = 'Invalid email or password.';

    if ($email === '' || $password === '') {
        $error = $genericFailure;
    } else {
        $stmt = db()->prepare(
            'SELECT id, password_hash FROM admin_users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, (string) $user['password_hash'])) {
            // Successful authentication. Regenerate the session id to prevent
            // session fixation, then record the authenticated admin id.
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $user['id'];

            header('Location: dashboard.php');
            exit;
        }

        $error = $genericFailure;
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login</title>
    <style>
        :root { --c-primary:#0e7c7b; --c-ink:#14181f; --c-muted:#5b6573; --c-surface:#f6f8fa; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center;
            justify-content: center; background: var(--c-surface); color: var(--c-ink);
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        }
        .card {
            background: #fff; padding: 2rem; border-radius: 14px; width: 100%;
            max-width: 360px; box-shadow: 0 10px 30px rgba(20,24,31,.08);
        }
        h1 { font-size: 1.4rem; margin: 0 0 1.25rem; }
        label { display: block; font-size: .85rem; color: var(--c-muted); margin: 0 0 .35rem; }
        input[type=email], input[type=password] {
            width: 100%; padding: .7rem .8rem; margin: 0 0 1rem; border: 1px solid #d4dae1;
            border-radius: 10px; font-size: 1rem;
        }
        button {
            width: 100%; min-height: 44px; padding: .7rem 1rem; border: 0; border-radius: 10px;
            background: var(--c-primary); color: #fff; font-size: 1rem; cursor: pointer;
        }
        button:focus-visible, input:focus-visible { outline: 3px solid rgba(14,124,123,.4); outline-offset: 2px; }
        .error { background: #fdecec; color: #a12626; padding: .65rem .8rem; border-radius: 10px; margin: 0 0 1rem; font-size: .9rem; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Admin Login</h1>

        <?php if ($error !== ''): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="index.php" novalidate>
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" autocomplete="username" required
                   value="<?= e($_POST['email'] ?? '') ?>">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>

            <button type="submit">Sign in</button>
        </form>
    </main>
</body>
</html>
