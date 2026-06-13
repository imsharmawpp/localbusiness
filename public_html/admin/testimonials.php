<?php
/**
 * admin/testimonials.php
 *
 * Testimonials_Module — CRUD for customer testimonials / trust signals.
 *
 * Follows the shared admin module pattern (design.md): require auth.php then
 * config.php, db.php, functions.php; routing via ?action= and ?id=.
 *
 * Behaviour:
 *   - list (default): all testimonials with author, rating, and active state
 *     (Req 9.1).
 *   - add / edit (GET): render the create/edit form with a CSRF token.
 *   - save (POST): persist author, role, quote, rating, photo, sort_order, and
 *     active state (Req 9.2). An uploaded photo is processed through upload()
 *     and the resulting filename stored in `photo` (Req 9.4).
 *   - delete (POST): remove the testimonial row (Req 9.3).
 *
 * Security: prepared statements only (Req 6.4); output escaped via e()
 * (Req 6.5); CSRF embedded + verified, 400 on failure (Req 6.1-6.3); PRG with
 * a read-once flash.
 *
 * Requirements: 9.1, 9.2, 9.3, 9.4, 6.4, 6.5.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// ---------------------------------------------------------------------------
// POST: CSRF gate, then save or delete and PRG (Req 6.1-6.3, 9.2-9.4).
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad request');
    }

    $postAction = (string) ($_POST['action'] ?? '');
    $postId     = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($postAction === 'delete' && $postId > 0) {
        // Req 9.3 — delete the testimonial.
        $stmt = db()->prepare('DELETE FROM testimonials WHERE id = ?');
        $stmt->execute([$postId]);
        flash('Testimonial deleted.');
        header('Location: testimonials.php');
        exit;
    }

    if ($postAction === 'save') {
        $author    = trim((string) ($_POST['author'] ?? ''));
        $role      = trim((string) ($_POST['role'] ?? ''));
        $quote     = (string) ($_POST['quote'] ?? '');
        $rating    = (int) ($_POST['rating'] ?? 5);
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        // Clamp the rating to the sensible 1-5 range.
        if ($rating < 1) { $rating = 1; }
        if ($rating > 5) { $rating = 5; }

        // Photo handling (Req 9.4). Default to the existing stored value so an
        // edit without a new file keeps the current photo.
        $photo = trim((string) ($_POST['existing_photo'] ?? ''));
        if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $result = upload($_FILES['photo'], $author !== '' ? $author : 'testimonial');
            if ($result['ok']) {
                $photo = $result['filename'];
            } else {
                flash('Photo upload failed: ' . ($result['error'] ?? 'unknown error'));
                $back = $postId > 0 ? ('?action=edit&id=' . $postId) : '?action=add';
                header('Location: testimonials.php' . $back);
                exit;
            }
        }

        if ($postId > 0) {
            $stmt = db()->prepare(
                'UPDATE testimonials
                    SET author = ?, role = ?, quote = ?, rating = ?, photo = ?, sort_order = ?, is_active = ?
                  WHERE id = ?'
            );
            $stmt->execute([$author, $role, $quote, $rating, $photo, $sortOrder, $isActive, $postId]);
            flash('Testimonial updated.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO testimonials (author, role, quote, rating, photo, sort_order, is_active)
                      VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$author, $role, $quote, $rating, $photo, $sortOrder, $isActive]);
            flash('Testimonial created.');
        }

        header('Location: testimonials.php');
        exit;
    }

    header('Location: testimonials.php');
    exit;
}

// ---------------------------------------------------------------------------
// GET: render the add/edit form or the list (Req 9.1).
// ---------------------------------------------------------------------------
$csrf   = csrf_token();
$notice = flash();

if ($action === 'add' || $action === 'edit') {
    $t = ['id' => 0, 'author' => '', 'role' => '', 'quote' => '', 'rating' => 5, 'photo' => '', 'sort_order' => 0, 'is_active' => 1];
    if ($action === 'edit' && $id > 0) {
        $stmt = db()->prepare('SELECT id, author, role, quote, rating, photo, sort_order, is_active FROM testimonials WHERE id = ?');
        $stmt->execute([$id]);
        $found = $stmt->fetch();
        if (!$found) {
            flash('That testimonial no longer exists.');
            header('Location: testimonials.php');
            exit;
        }
        $t = $found;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title><?= $action === 'edit' ? 'Edit' : 'New' ?> Testimonial &middot; Admin</title>
        <style>
            :root { --c-primary:#0e7c7b; --c-ink:#14181f; --c-muted:#5b6573; --c-surface:#f6f8fa; --c-line:#e2e7ec; }
            * { box-sizing: border-box; }
            body { margin: 0; background: var(--c-surface); color: var(--c-ink); font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
            header.bar { display: flex; align-items: center; justify-content: space-between; background: #fff; padding: 1rem 1.5rem; box-shadow: 0 1px 0 rgba(20,24,31,.06); }
            header.bar h1 { font-size: 1.2rem; margin: 0; }
            header.bar a { color: var(--c-primary); text-decoration: none; font-weight: 600; min-height: 44px; display: inline-flex; align-items: center; }
            main { max-width: 680px; margin: 0 auto; padding: 1.5rem; }
            .notice { background: #fdecec; color: #a12626; padding: .7rem 1rem; border-radius: 10px; margin: 0 0 1.25rem; }
            .card { background: #fff; padding: 1.5rem; border-radius: 14px; box-shadow: 0 10px 30px rgba(20,24,31,.06); }
            .field { margin: 0 0 1.25rem; }
            label { display: block; font-size: .85rem; color: var(--c-muted); margin: 0 0 .35rem; font-weight: 600; }
            input[type=text], input[type=number], textarea { width: 100%; padding: .65rem .8rem; border: 1px solid var(--c-line); border-radius: 10px; font-size: 1rem; font-family: inherit; }
            textarea { min-height: 120px; resize: vertical; }
            .inline { display: flex; gap: 1rem; }
            .inline .field { flex: 1; }
            .check { display: flex; align-items: center; gap: .5rem; }
            .check input { width: auto; }
            input:focus-visible, textarea:focus-visible, button:focus-visible { outline: 3px solid rgba(14,124,123,.4); outline-offset: 2px; }
            .photo-current { display: block; max-height: 70px; margin: .35rem 0; border-radius: 8px; }
            button { min-height: 44px; padding: .65rem 1.4rem; border: 0; border-radius: 10px; background: var(--c-primary); color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; }
        </style>
    </head>
    <body>
        <header class="bar">
            <h1><?= $action === 'edit' ? 'Edit' : 'New' ?> Testimonial</h1>
            <a href="testimonials.php">&larr; Back to list</a>
        </header>
        <main>
            <?php if ($notice !== null): ?>
                <p class="notice" role="alert"><?= e($notice) ?></p>
            <?php endif; ?>
            <form class="card" method="post" action="testimonials.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= e((string) $t['id']) ?>">
                <input type="hidden" name="existing_photo" value="<?= e((string) $t['photo']) ?>">

                <div class="field">
                    <label for="f_author">Author</label>
                    <input type="text" id="f_author" name="author" value="<?= e((string) $t['author']) ?>" required>
                </div>
                <div class="field">
                    <label for="f_role">Role</label>
                    <input type="text" id="f_role" name="role" value="<?= e((string) $t['role']) ?>">
                </div>
                <div class="field">
                    <label for="f_quote">Quote</label>
                    <textarea id="f_quote" name="quote"><?= e((string) $t['quote']) ?></textarea>
                </div>
                <div class="inline">
                    <div class="field">
                        <label for="f_rating">Rating (1-5)</label>
                        <input type="number" id="f_rating" name="rating" min="1" max="5" value="<?= e((string) $t['rating']) ?>">
                    </div>
                    <div class="field">
                        <label for="f_sort">Sort order</label>
                        <input type="number" id="f_sort" name="sort_order" value="<?= e((string) $t['sort_order']) ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="f_photo">Photo</label>
                    <?php if ((string) $t['photo'] !== ''): ?>
                        <img class="photo-current" src="<?= e(UPLOAD_URL . '/' . $t['photo']) ?>" alt="Current photo of <?= e((string) $t['author']) ?>">
                        <small><?= e((string) $t['photo']) ?></small>
                    <?php endif; ?>
                    <input type="file" id="f_photo" name="photo" accept="image/*">
                </div>
                <div class="field check">
                    <input type="checkbox" id="f_active" name="is_active" value="1" <?= (int) $t['is_active'] === 1 ? 'checked' : '' ?>>
                    <label for="f_active" style="margin:0;">Active</label>
                </div>

                <button type="submit"><?= $action === 'edit' ? 'Update testimonial' : 'Create testimonial' ?></button>
            </form>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Default: list view (Req 9.1).
$stmt = db()->prepare('SELECT id, author, rating, is_active FROM testimonials ORDER BY sort_order ASC, id ASC');
$stmt->execute();
$testimonials = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Testimonials &middot; Admin</title>
    <style>
        :root { --c-primary:#0e7c7b; --c-ink:#14181f; --c-muted:#5b6573; --c-surface:#f6f8fa; --c-line:#e2e7ec; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--c-surface); color: var(--c-ink); font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
        header.bar { display: flex; align-items: center; justify-content: space-between; background: #fff; padding: 1rem 1.5rem; box-shadow: 0 1px 0 rgba(20,24,31,.06); }
        header.bar h1 { font-size: 1.2rem; margin: 0; }
        header.bar a { color: var(--c-primary); text-decoration: none; font-weight: 600; min-height: 44px; display: inline-flex; align-items: center; }
        main { max-width: 900px; margin: 0 auto; padding: 1.5rem; }
        .notice { background: #e8f6f5; color: #0e5c5b; padding: .7rem 1rem; border-radius: 10px; margin: 0 0 1.25rem; }
        .toolbar { display: flex; gap: .75rem; margin: 0 0 1.25rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: .55rem 1rem; border: 0; border-radius: 10px; background: var(--c-primary); color: #fff; font-weight: 600; cursor: pointer; text-decoration: none; font-size: .95rem; }
        .btn.secondary { background: #fff; color: var(--c-ink); border: 1px solid var(--c-line); }
        .btn.danger { background: #fdecec; color: #a12626; }
        .btn:focus-visible { outline: 3px solid rgba(14,124,123,.4); outline-offset: 2px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(20,24,31,.06); }
        th, td { text-align: left; padding: .75rem 1rem; border-bottom: 1px solid var(--c-line); font-size: .92rem; }
        th { background: #fafbfc; color: var(--c-muted); text-transform: uppercase; letter-spacing: .04em; font-size: .75rem; }
        .pill { display: inline-block; padding: .2rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; }
        .pill.on { background: #e8f6f5; color: #0e5c5b; }
        .pill.off { background: #eef1f4; color: var(--c-muted); }
        .row-actions { display: flex; gap: .4rem; }
        .row-actions form { margin: 0; }
        .empty { background: #fff; border-radius: 14px; padding: 2rem; text-align: center; color: var(--c-muted); box-shadow: 0 10px 30px rgba(20,24,31,.06); }
    </style>
</head>
<body>
    <header class="bar">
        <h1>Testimonials</h1>
        <a href="dashboard.php">&larr; Dashboard</a>
    </header>
    <main>
        <?php if ($notice !== null): ?>
            <p class="notice" role="status"><?= e($notice) ?></p>
        <?php endif; ?>

        <div class="toolbar">
            <a class="btn" href="testimonials.php?action=add">New testimonial</a>
        </div>

        <?php if (count($testimonials) === 0): ?>
            <div class="empty">No testimonials yet.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Author</th><th>Rating</th><th>Active</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($testimonials as $t): ?>
                        <tr>
                            <td><?= e((string) $t['author']) ?></td>
                            <td><?= e(str_repeat('★', max(0, min(5, (int) $t['rating'])))) ?> (<?= e((string) $t['rating']) ?>)</td>
                            <td>
                                <?php if ((int) $t['is_active'] === 1): ?>
                                    <span class="pill on">Active</span>
                                <?php else: ?>
                                    <span class="pill off">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn secondary" href="testimonials.php?action=edit&id=<?= e((string) $t['id']) ?>">Edit</a>
                                    <form method="post" action="testimonials.php" onsubmit="return confirm('Delete this testimonial?');">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e((string) $t['id']) ?>">
                                        <button type="submit" class="btn danger">Delete</button>
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
