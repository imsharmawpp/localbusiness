<?php
/**
 * admin/settings.php
 *
 * Settings_Editor — the shared admin module for editing the site_settings
 * table. It presents every defined setting key in a single form, persists the
 * submitted values, and processes an optional logo upload.
 *
 * Flow (admin module pattern from design.md):
 *   - auth.php starts the hardened session and guards the page (Req 5.5).
 *   - config.php / db.php / functions.php provide constants, the PDO singleton,
 *     and the helper library.
 *   - GET: render the form showing the current value of each defined key
 *     (Req 7.1). Every form embeds a CSRF hidden field (Req 6.1) and all output
 *     is escaped via e() (Req 6.5).
 *   - POST: verify the CSRF token first; a missing/mismatched token yields a
 *     400 and changes nothing (Req 6.2, 6.3). Each submitted value is persisted
 *     to site_settings through a prepared UPSERT (Req 6.4, 7.2). An uploaded
 *     logo is processed through upload() and the stored filename is saved in the
 *     'logo' setting (Req 7.3). A success flash is set and the request is
 *     redirected (PRG) so the message shows exactly once (Req 7.4).
 *
 * Requirements: 7.1, 7.2, 7.3, 7.4, 6.4, 6.5.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

/**
 * The defined site_settings keys and how each is edited (Req 3.2, 7.1).
 *
 * Each entry is [key => [label, type]] where `type` selects the input control:
 *   - 'text'     a single-line text input
 *   - 'email'    an email input
 *   - 'url'      a URL input
 *   - 'textarea' a multi-line textarea (map_embed, hours, meta_default)
 *   - 'color'    a colour picker (primary_color)
 *   - 'file'     a file input whose upload becomes the 'logo' value
 *
 * @var array<string,array{0:string,1:string}> $fields
 */
$fields = [
    'site_name'           => ['Site name',              'text'],
    'tagline'             => ['Tagline',                'text'],
    'phone'               => ['Phone',                  'text'],
    'whatsapp'            => ['WhatsApp number',        'text'],
    'email'               => ['Email',                  'email'],
    'address'             => ['Address',                'textarea'],
    'map_embed'           => ['Map embed (iframe)',     'textarea'],
    'hours'               => ['Hours (JSON)',           'textarea'],
    'logo'                => ['Logo image',             'file'],
    'primary_color'       => ['Primary colour',         'color'],
    'facebook'            => ['Facebook URL',           'url'],
    'instagram'           => ['Instagram URL',          'url'],
    'google_business_url' => ['Google Business URL',    'url'],
    'meta_default'        => ['Default meta description', 'textarea'],
    'schema_type'         => ['Schema @type',           'text'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF gate before any work (Req 6.2, 6.3): a missing or mismatched token
    // rejects the request and changes nothing.
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad request');
    }

    // Single prepared UPSERT reused for every key (Req 6.4, 7.2). The primary
    // key on setting_key makes ON DUPLICATE KEY UPDATE an insert-or-update.
    $upsert = db()->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)'
        . ' ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    foreach ($fields as $key => [$label, $type]) {
        if ($type === 'file') {
            // The logo is handled below via upload(); it has no text value.
            continue;
        }
        $value = (string) ($_POST[$key] ?? '');
        $upsert->execute([$key, $value]);
    }

    // Process an optional logo upload (Req 7.3). Only persist a new 'logo'
    // value when a file was actually submitted and validated successfully; an
    // empty file input leaves the existing logo untouched.
    $logoError = null;
    if (isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $result = upload($_FILES['logo'], 'logo');
        if ($result['ok']) {
            $upsert->execute(['logo', $result['filename']]);
        } else {
            $logoError = $result['error'];
        }
    }

    // Flash a result message and redirect (PRG, Req 7.4) so the message shows
    // once and a refresh does not re-submit.
    if ($logoError !== null) {
        flash('Settings saved, but the logo was not updated: ' . $logoError);
    } else {
        flash('Settings saved.');
    }

    header('Location: settings.php');
    exit;
}

// GET: load the current value of every setting for display (Req 7.1).
$current = [];
foreach (db()->query('SELECT setting_key, setting_value FROM site_settings') as $row) {
    $current[$row['setting_key']] = (string) $row['setting_value'];
}

$notice = flash();
$csrf   = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Site Settings</title>
    <style>
        :root { --c-primary:#0e7c7b; --c-ink:#14181f; --c-muted:#5b6573; --c-surface:#f6f8fa; }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--c-surface); color: var(--c-ink);
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        }
        header.bar {
            display: flex; align-items: center; justify-content: space-between;
            background: #fff; padding: 1rem 1.5rem; box-shadow: 0 1px 0 rgba(20,24,31,.06);
        }
        header.bar h1 { font-size: 1.2rem; margin: 0; }
        header.bar a { color: var(--c-primary); text-decoration: none; font-weight: 600; min-height: 44px; display: inline-flex; align-items: center; }
        main { max-width: 760px; margin: 0 auto; padding: 1.5rem; }
        .notice { background: #e8f6f5; color: #0e5c5b; padding: .7rem 1rem; border-radius: 10px; margin: 0 0 1.25rem; }
        form { background: #fff; padding: 1.5rem; border-radius: 14px; box-shadow: 0 10px 30px rgba(20,24,31,.06); }
        .field { margin: 0 0 1.25rem; }
        label { display: block; font-size: .85rem; color: var(--c-muted); margin: 0 0 .35rem; font-weight: 600; }
        input[type=text], input[type=email], input[type=url], textarea {
            width: 100%; padding: .7rem .8rem; border: 1px solid #d4dae1; border-radius: 10px; font-size: 1rem; font-family: inherit;
        }
        textarea { min-height: 110px; resize: vertical; }
        input[type=color] { width: 64px; height: 44px; padding: 2px; border: 1px solid #d4dae1; border-radius: 10px; background: #fff; }
        input[type=file] { font-size: .95rem; }
        .logo-current { font-size: .85rem; color: var(--c-muted); margin: .35rem 0 0; }
        .logo-current img { display: block; max-height: 64px; margin-top: .4rem; border-radius: 8px; }
        button {
            min-height: 44px; padding: .7rem 1.5rem; border: 0; border-radius: 10px;
            background: var(--c-primary); color: #fff; font-size: 1rem; cursor: pointer; font-weight: 600;
        }
        button:focus-visible, input:focus-visible, textarea:focus-visible {
            outline: 3px solid rgba(14,124,123,.4); outline-offset: 2px;
        }
    </style>
</head>
<body>
    <header class="bar">
        <h1>Site Settings</h1>
        <a href="dashboard.php">&larr; Dashboard</a>
    </header>

    <main>
        <?php if ($notice !== null): ?>
            <p class="notice" role="status"><?= e($notice) ?></p>
        <?php endif; ?>

        <form method="post" action="settings.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

            <?php foreach ($fields as $key => [$label, $type]): ?>
                <?php $value = $current[$key] ?? ''; ?>
                <div class="field">
                    <label for="f_<?= e($key) ?>"><?= e($label) ?></label>

                    <?php if ($type === 'textarea'): ?>
                        <textarea id="f_<?= e($key) ?>" name="<?= e($key) ?>"><?= e($value) ?></textarea>

                    <?php elseif ($type === 'color'): ?>
                        <input type="color" id="f_<?= e($key) ?>" name="<?= e($key) ?>"
                               value="<?= e($value !== '' ? $value : '#0e7c7b') ?>">

                    <?php elseif ($type === 'file'): ?>
                        <input type="file" id="f_<?= e($key) ?>" name="<?= e($key) ?>" accept="image/*">
                        <?php if ($value !== ''): ?>
                            <p class="logo-current">
                                Current: <?= e($value) ?>
                                <img src="<?= e(UPLOAD_URL . '/' . $value) ?>" alt="Current logo">
                            </p>
                        <?php endif; ?>

                    <?php else: ?>
                        <input type="<?= e($type) ?>" id="f_<?= e($key) ?>" name="<?= e($key) ?>"
                               value="<?= e($value) ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit">Save settings</button>
        </form>
    </main>
</body>
</html>
