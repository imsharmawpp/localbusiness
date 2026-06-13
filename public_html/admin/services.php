<?php
/**
 * admin/services.php
 *
 * Services_Module — the healthcare admin CRUD module for the `services` table.
 * A single file controlled by ?action= (list | add | edit | save | delete)
 * and ?id=, following the shared admin module skeleton in design.md (see
 * admin/content_blocks.php for the canonical conventions).
 *
 * Behaviour (Req 16.1-16.4, 6.4, 6.5):
 *   - list   : show every service with name, slug, sort_order and is_active,
 *              plus edit/delete actions (Req 16.1).
 *   - add    : render an empty create form.
 *   - edit   : render the form populated from the selected row.
 *   - save   : POST only. On a valid CSRF token, persist name, slug,
 *              short_desc, body, icon, image, price_from, sort_order and
 *              is_active (Req 16.2). When the slug field is left blank it is
 *              auto-generated from the name via slug() (Req 16.3). A duplicate
 *              slug on a *different* row is rejected with an error flash and
 *              nothing is written (Req 16.4). An uploaded image is processed
 *              through upload() and the resulting filename stored in `image`.
 *              price_from is stored as NULL when blank, otherwise as a decimal.
 *   - delete : POST only. On a valid CSRF token, remove the row.
 *
 * Security: prepared statements only (Req 6.4); values escaped with e() on
 * output (Req 6.5); CSRF embedded and verified, 400 on failure (Req 6.1-6.3);
 * PRG with a read-once flash.
 *
 * Requirements: 16.1, 16.2, 16.3, 16.4, 6.4, 6.5.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/**
 * Fetch a single service by id, or null.
 *
 * @return array<string,mixed>|null
 */
function service_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM services WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Return true when $slug already exists on a row other than $excludeId
 * (Req 16.4). Uses a prepared statement (Req 6.4).
 */
function service_slug_taken(string $slug, int $excludeId): bool
{
    $stmt = db()->prepare('SELECT id FROM services WHERE slug = ? AND id <> ? LIMIT 1');
    $stmt->execute([$slug, $excludeId]);
    return (bool) $stmt->fetch();
}

// --------------------------------------------------------------------------
// POST: save and delete (Req 6.1-6.3, 16.2-16.4).
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad request');
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM services WHERE id = ?');
        $stmt->execute([$id]);
        flash('Service deleted.');
        header('Location: services.php');
        exit;
    }

    if ($action === 'save') {
        $name      = trim((string) ($_POST['name'] ?? ''));
        $slugInput = trim((string) ($_POST['slug'] ?? ''));
        $shortDesc = trim((string) ($_POST['short_desc'] ?? ''));
        $body      = (string) ($_POST['body'] ?? '');
        $icon      = trim((string) ($_POST['icon'] ?? ''));
        $priceRaw  = trim((string) ($_POST['price_from'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        $existing = service_find($id);
        $image    = (string) ($existing['image'] ?? '');

        $backUrl = $id > 0
            ? 'services.php?action=edit&id=' . $id
            : 'services.php?action=add';

        // A name is required to label the service.
        if ($name === '') {
            flash('Name is required.');
            header('Location: ' . $backUrl);
            exit;
        }

        // Auto-generate the slug from the name when left blank (Req 16.3).
        $slug = $slugInput !== '' ? slug($slugInput) : slug($name);
        if ($slug === '') {
            flash('A valid slug could not be generated. Please provide one.');
            header('Location: ' . $backUrl);
            exit;
        }

        // Reject a duplicate slug that belongs to a different row; write
        // nothing (Req 16.4).
        if (service_slug_taken($slug, $id)) {
            flash('That slug is already in use. Choose a unique slug.');
            header('Location: ' . $backUrl);
            exit;
        }

        // price_from: NULL when blank, otherwise a decimal value (Req 16.2).
        $priceFrom = $priceRaw === '' ? null : (float) $priceRaw;

        // Optional image upload (Req 16.2). Keep the current image on edit when
        // no new file is provided.
        if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $result = upload($_FILES['image'], $slug);
            if (!$result['ok']) {
                flash('Image upload failed: ' . ($result['error'] ?? 'unknown error'));
                header('Location: ' . $backUrl);
                exit;
            }
            $image = $result['filename'];
        }

        if ($id > 0) {
            $stmt = db()->prepare(
                'UPDATE services
                    SET name = ?, slug = ?, short_desc = ?, body = ?, icon = ?, image = ?, price_from = ?, sort_order = ?, is_active = ?
                  WHERE id = ?'
            );
            $stmt->execute([$name, $slug, $shortDesc, $body, $icon, $image, $priceFrom, $sortOrder, $isActive, $id]);
            flash('Service updated.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO services (name, slug, short_desc, body, icon, image, price_from, sort_order, is_active)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $slug, $shortDesc, $body, $icon, $image, $priceFrom, $sortOrder, $isActive]);
            flash('Service created.');
        }

        header('Location: services.php');
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
    $row = $action === 'edit' ? service_find($id) : null;
    if ($action === 'edit' && $row === null) {
        flash('That service could not be found.');
        header('Location: services.php');
        exit;
    }

    $f = [
        'name'       => (string) ($row['name'] ?? ''),
        'slug'       => (string) ($row['slug'] ?? ''),
        'short_desc' => (string) ($row['short_desc'] ?? ''),
        'body'       => (string) ($row['body'] ?? ''),
        'icon'       => (string) ($row['icon'] ?? ''),
        'image'      => (string) ($row['image'] ?? ''),
        'price_from' => $row['price_from'] ?? null,
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'is_active'  => isset($row['is_active']) ? (int) $row['is_active'] : 1,
    ];
    $isEdit = $action === 'edit';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title><?= $isEdit ? 'Edit' : 'Add' ?> Service</title>
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
            input[type=text], input[type=number], textarea { width:100%; padding:.7rem .8rem; border:1px solid #d4dae1; border-radius:10px; font-size:1rem; font-family:inherit; }
            textarea { min-height:160px; resize:vertical; }
            textarea.short { min-height:80px; }
            .hint { font-size:.78rem; color:var(--c-muted); margin:.35rem 0 0; }
            .inline { display:flex; gap:1rem; }
            .inline > div { flex:1; }
            .check { display:flex; align-items:center; gap:.5rem; margin-top:1rem; }
            .check input { width:auto; }
            .check label { margin:0; }
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
            <h1><?= $isEdit ? 'Edit' : 'Add' ?> Service</h1>
            <a href="services.php">&larr; Back to list</a>
        </header>
        <main>
            <?php if ($notice !== null): ?>
                <p class="notice" role="status"><?= e($notice) ?></p>
            <?php endif; ?>

            <form class="card" method="post"
                  action="services.php?action=save<?= $isEdit ? '&id=' . (int) $id : '' ?>"
                  enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                <label for="name">Name</label>
                <input type="text" id="name" name="name" required value="<?= e($f['name']) ?>">

                <label for="slug">Slug</label>
                <input type="text" id="slug" name="slug" value="<?= e($f['slug']) ?>">
                <p class="hint">Leave blank to auto-generate from the name. Must be unique, e.g. <code>dental-checkup</code>.</p>

                <label for="short_desc">Short description</label>
                <textarea id="short_desc" name="short_desc" class="short"><?= e($f['short_desc']) ?></textarea>

                <label for="body">Body</label>
                <textarea id="body" name="body"><?= e($f['body']) ?></textarea>

                <div class="inline">
                    <div>
                        <label for="icon">Icon</label>
                        <input type="text" id="icon" name="icon" value="<?= e($f['icon']) ?>">
                        <p class="hint">Icon name or class.</p>
                    </div>
                    <div>
                        <label for="price_from">Price from</label>
                        <input type="number" id="price_from" name="price_from" step="0.01" min="0"
                               value="<?= $f['price_from'] === null ? '' : e((string) $f['price_from']) ?>">
                        <p class="hint">Leave blank for no price.</p>
                    </div>
                </div>

                <label for="image">Image</label>
                <input type="file" id="image" name="image" accept="image/*">
                <?php if ($f['image'] !== ''): ?>
                    <p class="current-image">Current: <?= e($f['image']) ?>
                        <img src="<?= e(rtrim(UPLOAD_URL, '/') . '/' . $f['image']) ?>" alt="Current image for <?= e($f['name']) ?>" width="160">
                    </p>
                <?php endif; ?>

                <label for="sort_order">Sort order</label>
                <input type="number" id="sort_order" name="sort_order" value="<?= e((string) $f['sort_order']) ?>">

                <div class="check">
                    <input type="checkbox" id="is_active" name="is_active" value="1" <?= $f['is_active'] === 1 ? 'checked' : '' ?>>
                    <label for="is_active">Active</label>
                </div>

                <div class="actions">
                    <button type="submit"><?= $isEdit ? 'Save changes' : 'Create service' ?></button>
                    <a class="cancel" href="services.php">Cancel</a>
                </div>
            </form>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Default: list view (Req 16.1).
$rows = db()->query(
    'SELECT id, name, slug, sort_order, is_active FROM services ORDER BY sort_order ASC, id ASC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Services</title>
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
        .pill { display:inline-block; padding:.2rem .6rem; border-radius:999px; font-size:.72rem; font-weight:700; }
        .pill.on { background:#e8f6f5; color:#0e5c5b; }
        .pill.off { background:#eef1f4; color:var(--c-muted); }
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
        <h1>Services</h1>
        <a href="dashboard.php">&larr; Dashboard</a>
    </header>
    <main>
        <?php if ($notice !== null): ?>
            <p class="notice" role="status"><?= e($notice) ?></p>
        <?php endif; ?>

        <div class="toolbar">
            <span><?= count($rows) ?> service<?= count($rows) === 1 ? '' : 's' ?></span>
            <a class="btn" href="services.php?action=add">+ Add service</a>
        </div>

        <?php if (count($rows) === 0): ?>
            <p class="empty">No services yet. Add your first one.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Slug</th>
                        <th scope="col">Sort</th>
                        <th scope="col">Active</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e((string) $row['name']) ?></td>
                            <td><code><?= e((string) $row['slug']) ?></code></td>
                            <td><?= e((string) $row['sort_order']) ?></td>
                            <td>
                                <?php if ((int) $row['is_active'] === 1): ?>
                                    <span class="pill on">Active</span>
                                <?php else: ?>
                                    <span class="pill off">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="services.php?action=edit&id=<?= (int) $row['id'] ?>">Edit</a>
                                    <form method="post"
                                          action="services.php?action=delete&id=<?= (int) $row['id'] ?>"
                                          onsubmit="return confirm('Delete this service?');">
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
