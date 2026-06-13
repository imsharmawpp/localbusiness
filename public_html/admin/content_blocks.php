<?php
/**
 * admin/content_blocks.php
 *
 * Content_Blocks_Module — the shared admin CRUD module for the `content_blocks`
 * table. Single file controlled by ?action= (list | add | edit | save |
 * delete) and ?id=, per the design.md module skeleton.
 *
 * Behaviour (Req 8.1-8.4, 6.4, 6.5):
 *   - list   : show every block with its block_key and title (Req 8.1).
 *   - add    : render an empty create form.
 *   - edit   : render the form populated from the selected row.
 *   - save   : POST only. On a valid CSRF token, persist block_key, title,
 *              body and image (Req 8.2). A duplicate block_key on a *different*
 *              row is rejected with an error flash (Req 8.4). An uploaded image
 *              is processed through upload() and stored in `image`.
 *   - delete : POST only. On a valid CSRF token, remove the row (Req 8.3).
 *
 * Security: prepared statements only (Req 6.4); values escaped with e() on
 * output (Req 6.5); CSRF embedded and verified, 400 on failure (Req 6.1-6.3).
 *
 * Requirements: 8.1, 8.2, 8.3, 8.4, 6.4, 6.5.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/**
 * Fetch a single content block by id, or null.
 *
 * @return array<string,mixed>|null
 */
function block_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM content_blocks WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Return true when block_key already exists on a row other than $excludeId
 * (Req 8.4). Uses a prepared statement (Req 6.4).
 */
function block_key_taken(string $key, int $excludeId): bool
{
    $stmt = db()->prepare('SELECT id FROM content_blocks WHERE block_key = ? AND id <> ? LIMIT 1');
    $stmt->execute([$key, $excludeId]);
    return (bool) $stmt->fetch();
}

// --------------------------------------------------------------------------
// POST: save and delete.
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad request');
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM content_blocks WHERE id = ?');
        $stmt->execute([$id]);
        flash('Content block deleted.');
        header('Location: content_blocks.php');
        exit;
    }

    if ($action === 'save') {
        $blockKey = trim((string) ($_POST['block_key'] ?? ''));
        $title    = trim((string) ($_POST['title'] ?? ''));
        $body     = (string) ($_POST['body'] ?? '');

        $existing = block_find($id);
        $image    = $existing['image'] ?? '';

        $backUrl = $id > 0 ? 'content_blocks.php?action=edit&id=' . $id : 'content_blocks.php?action=add';

        // A block key is required to address the block from templates.
        if ($blockKey === '') {
            flash('Block key is required.');
            header('Location: ' . $backUrl);
            exit;
        }

        // Reject a duplicate key that belongs to a different row (Req 8.4).
        if (block_key_taken($blockKey, $id)) {
            flash('That block key is already in use. Choose a unique key.');
            header('Location: ' . $backUrl);
            exit;
        }

        // Optional image upload.
        if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $result = upload($_FILES['image'], $blockKey);
            if (!$result['ok']) {
                flash('Image upload failed: ' . $result['error']);
                header('Location: ' . $backUrl);
                exit;
            }
            $image = $result['filename'];
        }

        if ($id > 0) {
            $stmt = db()->prepare(
                'UPDATE content_blocks SET block_key = ?, title = ?, body = ?, image = ? WHERE id = ?'
            );
            $stmt->execute([$blockKey, $title, $body, $image, $id]);
            flash('Content block updated.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO content_blocks (block_key, title, body, image) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$blockKey, $title, $body, $image]);
            flash('Content block created.');
        }

        header('Location: content_blocks.php');
        exit;
    }

    http_response_code(400);
    exit('Bad request');
}

// --------------------------------------------------------------------------
// GET: add/edit form or list.
// --------------------------------------------------------------------------
$csrf   = csrf_token();
$notice = flash();

if ($action === 'add' || $action === 'edit') {
    $row = $action === 'edit' ? block_find($id) : null;
    if ($action === 'edit' && $row === null) {
        flash('That content block could not be found.');
        header('Location: content_blocks.php');
        exit;
    }

    $f = [
        'block_key' => (string) ($row['block_key'] ?? ''),
        'title'     => (string) ($row['title'] ?? ''),
        'body'      => (string) ($row['body'] ?? ''),
        'image'     => (string) ($row['image'] ?? ''),
    ];
    $isEdit = $action === 'edit';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title><?= $isEdit ? 'Edit' : 'Add' ?> Content Block</title>
        <style>
            :root { --c-primary:#0e7c7b; --c-ink:#14181f; --c-muted:#5b6573; --c-surface:#f6f8fa; }
            * { box-sizing: border-box; }
            body { margin:0; background:var(--c-surface); color:var(--c-ink); font-family: system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
            header.bar { display:flex; align-items:center; justify-content:space-between; background:#fff; padding:1rem 1.5rem; box-shadow:0 1px 0 rgba(20,24,31,.06); }
            header.bar h1 { font-size:1.2rem; margin:0; }
            header.bar a { color:var(--c-primary); text-decoration:none; font-weight:600; min-height:44px; display:inline-flex; align-items:center; }
            main { max-width:680px; margin:0 auto; padding:1.5rem; }
            .notice { background:#e8f6f5; color:#0e5c5b; padding:.7rem 1rem; border-radius:10px; margin:0 0 1.25rem; }
            form.card { background:#fff; padding:1.5rem; border-radius:14px; box-shadow:0 10px 30px rgba(20,24,31,.06); }
            label { display:block; font-size:.85rem; color:var(--c-muted); margin:1rem 0 .35rem; }
            input[type=text], textarea { width:100%; padding:.7rem .8rem; border:1px solid #d4dae1; border-radius:10px; font-size:1rem; font-family:inherit; }
            textarea { min-height:160px; resize:vertical; }
            .hint { font-size:.78rem; color:var(--c-muted); margin:.35rem 0 0; }
            .actions { margin-top:1.5rem; display:flex; gap:.75rem; }
            button { min-height:44px; padding:.7rem 1.25rem; border:0; border-radius:10px; background:var(--c-primary); color:#fff; font-size:1rem; cursor:pointer; }
            a.cancel { min-height:44px; padding:.7rem 1.25rem; display:inline-flex; align-items:center; border-radius:10px; background:#eef1f4; color:var(--c-ink); text-decoration:none; }
            button:focus-visible, a:focus-visible, input:focus-visible, textarea:focus-visible { outline:3px solid rgba(14,124,123,.4); outline-offset:2px; }
            .current-image { margin-top:.5rem; font-size:.85rem; color:var(--c-muted); }
            .current-image img { display:block; max-width:160px; height:auto; border-radius:10px; margin-top:.35rem; }
        </style>
    </head>
    <body>
        <header class="bar">
            <h1><?= $isEdit ? 'Edit' : 'Add' ?> Content Block</h1>
            <a href="content_blocks.php">&larr; Back to list</a>
        </header>
        <main>
            <?php if ($notice !== null): ?>
                <p class="notice" role="status"><?= e($notice) ?></p>
            <?php endif; ?>

            <form class="card" method="post"
                  action="content_blocks.php?action=save<?= $isEdit ? '&id=' . (int) $id : '' ?>"
                  enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                <label for="block_key">Block key</label>
                <input type="text" id="block_key" name="block_key" required value="<?= e($f['block_key']) ?>">
                <p class="hint">Unique identifier used by templates, e.g. <code>hero</code>, <code>about</code>.</p>

                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?= e($f['title']) ?>">

                <label for="body">Body</label>
                <textarea id="body" name="body"><?= e($f['body']) ?></textarea>

                <label for="image">Image</label>
                <input type="file" id="image" name="image" accept="image/*">
                <?php if ($f['image'] !== ''): ?>
                    <p class="current-image">Current: <?= e($f['image']) ?>
                        <img src="<?= e(rtrim(UPLOAD_URL, '/') . '/' . $f['image']) ?>" alt="Current image for <?= e($f['block_key']) ?>" width="160">
                    </p>
                <?php endif; ?>

                <div class="actions">
                    <button type="submit"><?= $isEdit ? 'Save changes' : 'Create block' ?></button>
                    <a class="cancel" href="content_blocks.php">Cancel</a>
                </div>
            </form>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Default: list view (Req 8.1).
$rows = db()->query('SELECT id, block_key, title FROM content_blocks ORDER BY block_key ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Content Blocks</title>
    <style>
        :root { --c-primary:#0e7c7b; --c-ink:#14181f; --c-muted:#5b6573; --c-surface:#f6f8fa; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--c-surface); color:var(--c-ink); font-family: system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
        header.bar { display:flex; align-items:center; justify-content:space-between; background:#fff; padding:1rem 1.5rem; box-shadow:0 1px 0 rgba(20,24,31,.06); }
        header.bar h1 { font-size:1.2rem; margin:0; }
        header.bar a { color:var(--c-primary); text-decoration:none; font-weight:600; min-height:44px; display:inline-flex; align-items:center; }
        main { max-width:980px; margin:0 auto; padding:1.5rem; }
        .notice { background:#e8f6f5; color:#0e5c5b; padding:.7rem 1rem; border-radius:10px; margin:0 0 1.25rem; }
        .toolbar { display:flex; justify-content:space-between; align-items:center; margin:0 0 1rem; gap:1rem; }
        a.btn { min-height:44px; padding:.6rem 1.1rem; display:inline-flex; align-items:center; border-radius:10px; background:var(--c-primary); color:#fff; text-decoration:none; font-weight:600; }
        table { width:100%; border-collapse:collapse; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 10px 30px rgba(20,24,31,.06); }
        th, td { text-align:left; padding:.85rem 1rem; border-bottom:1px solid #eef1f4; }
        th { font-size:.8rem; text-transform:uppercase; letter-spacing:.05em; color:var(--c-muted); }
        tr:last-child td { border-bottom:0; }
        code { background:#eef1f4; padding:.15rem .4rem; border-radius:6px; }
        .actions { display:flex; gap:.5rem; align-items:center; }
        .actions a { color:var(--c-primary); text-decoration:none; font-weight:600; min-height:44px; display:inline-flex; align-items:center; }
        .actions form { margin:0; }
        .actions button { min-height:44px; padding:.4rem .9rem; border:0; border-radius:10px; background:#fdecec; color:#a12626; font-weight:600; cursor:pointer; }
        button:focus-visible, a:focus-visible { outline:3px solid rgba(14,124,123,.4); outline-offset:2px; }
        .empty { background:#fff; padding:2rem; border-radius:14px; text-align:center; color:var(--c-muted); box-shadow:0 10px 30px rgba(20,24,31,.06); }
    </style>
</head>
<body>
    <header class="bar">
        <h1>Content Blocks</h1>
        <a href="dashboard.php">&larr; Dashboard</a>
    </header>
    <main>
        <?php if ($notice !== null): ?>
            <p class="notice" role="status"><?= e($notice) ?></p>
        <?php endif; ?>

        <div class="toolbar">
            <span><?= count($rows) ?> block<?= count($rows) === 1 ? '' : 's' ?></span>
            <a class="btn" href="content_blocks.php?action=add">+ Add block</a>
        </div>

        <?php if (count($rows) === 0): ?>
            <p class="empty">No content blocks yet. Add your first one.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th scope="col">Block key</th>
                        <th scope="col">Title</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><code><?= e($row['block_key']) ?></code></td>
                            <td><?= e($row['title']) ?></td>
                            <td>
                                <div class="actions">
                                    <a href="content_blocks.php?action=edit&id=<?= (int) $row['id'] ?>">Edit</a>
                                    <form method="post"
                                          action="content_blocks.php?action=delete&id=<?= (int) $row['id'] ?>"
                                          onsubmit="return confirm('Delete this content block?');">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <button type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
