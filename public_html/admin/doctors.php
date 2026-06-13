<?php
/**
 * admin/doctors.php
 *
 * Doctors_Module — CRUD for doctor / team-member profiles.
 *
 * Follows the shared admin module pattern (design.md): require auth.php then
 * config.php, db.php, functions.php; routing via ?action= and ?id=.
 *
 * Behaviour:
 *   - list (default): all doctors with name, specialty, sort order, and active
 *     state (Req 17.1).
 *   - add / edit (GET): render the create/edit form with a CSRF token.
 *   - save (POST): persist name, qualification, specialty, photo, bio,
 *     sort_order, and active state (Req 17.2). An uploaded photo is processed
 *     through upload() and the resulting filename stored in `photo` (Req 17.4).
 *   - delete (POST): remove the doctor row (Req 17.3).
 *
 * Security: prepared statements only (Req 6.4); output escaped via e()
 * (Req 6.5); CSRF embedded + verified, 400 on failure (Req 6.1-6.3); PRG with
 * a read-once flash.
 *
 * Requirements: 17.1, 17.2, 17.3, 17.4, 6.4, 6.5.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// ---------------------------------------------------------------------------
// POST: CSRF gate, then save or delete and PRG (Req 6.1-6.3, 17.2-17.4).
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad request');
    }

    $postAction = (string) ($_POST['action'] ?? '');
    $postId     = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($postAction === 'delete' && $postId > 0) {
        // Req 17.3 — delete the doctor.
        $stmt = db()->prepare('DELETE FROM doctors WHERE id = ?');
        $stmt->execute([$postId]);
        flash('Doctor deleted.');
        header('Location: doctors.php');
        exit;
    }

    if ($postAction === 'save') {
        $name          = trim((string) ($_POST['name'] ?? ''));
        $qualification = trim((string) ($_POST['qualification'] ?? ''));
        $specialty     = trim((string) ($_POST['specialty'] ?? ''));
        $bio           = (string) ($_POST['bio'] ?? '');
        $sortOrder     = (int) ($_POST['sort_order'] ?? 0);
        $isActive      = isset($_POST['is_active']) ? 1 : 0;

        // Photo handling (Req 17.4). Default to the existing stored value so an
        // edit without a new file keeps the current photo.
        $photo = trim((string) ($_POST['existing_photo'] ?? ''));
        if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $result = upload($_FILES['photo'], $name !== '' ? $name : 'doctor');
            if ($result['ok']) {
                $photo = $result['filename'];
            } else {
                flash('Photo upload failed: ' . ($result['error'] ?? 'unknown error'));
                $back = $postId > 0 ? ('?action=edit&id=' . $postId) : '?action=add';
                header('Location: doctors.php' . $back);
                exit;
            }
        }

        if ($postId > 0) {
            $stmt = db()->prepare(
                'UPDATE doctors
                    SET name = ?, qualification = ?, specialty = ?, photo = ?, bio = ?, sort_order = ?, is_active = ?
                  WHERE id = ?'
            );
            $stmt->execute([$name, $qualification, $specialty, $photo, $bio, $sortOrder, $isActive, $postId]);
            flash('Doctor updated.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO doctors (name, qualification, specialty, photo, bio, sort_order, is_active)
                      VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $qualification, $specialty, $photo, $bio, $sortOrder, $isActive]);
            flash('Doctor created.');
        }

        header('Location: doctors.php');
        exit;
    }

    header('Location: doctors.php');
    exit;
}

// ---------------------------------------------------------------------------
// GET: render the add/edit form or the list (Req 17.1).
// ---------------------------------------------------------------------------
$csrf   = csrf_token();
$notice = flash();

if ($action === 'add' || $action === 'edit') {
    $d = ['id' => 0, 'name' => '', 'qualification' => '', 'specialty' => '', 'photo' => '', 'bio' => '', 'sort_order' => 0, 'is_active' => 1];
    if ($action === 'edit' && $id > 0) {
        $stmt = db()->prepare('SELECT id, name, qualification, specialty, photo, bio, sort_order, is_active FROM doctors WHERE id = ?');
        $stmt->execute([$id]);
        $found = $stmt->fetch();
        if (!$found) {
            flash('That doctor no longer exists.');
            header('Location: doctors.php');
            exit;
        }
        $d = $found;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title><?= $action === 'edit' ? 'Edit' : 'New' ?> Doctor &middot; Admin</title>
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
            <h1><?= $action === 'edit' ? 'Edit' : 'New' ?> Doctor</h1>
            <a href="doctors.php">&larr; Back to list</a>
        </header>
        <main>
            <?php if ($notice !== null): ?>
                <p class="notice" role="alert"><?= e($notice) ?></p>
            <?php endif; ?>
            <form class="card" method="post" action="doctors.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= e((string) $d['id']) ?>">
                <input type="hidden" name="existing_photo" value="<?= e((string) $d['photo']) ?>">

                <div class="field">
                    <label for="f_name">Name</label>
                    <input type="text" id="f_name" name="name" value="<?= e((string) $d['name']) ?>" required>
                </div>
                <div class="inline">
                    <div class="field">
                        <label for="f_qualification">Qualification</label>
                        <input type="text" id="f_qualification" name="qualification" value="<?= e((string) $d['qualification']) ?>">
                    </div>
                    <div class="field">
                        <label for="f_specialty">Specialty</label>
                        <input type="text" id="f_specialty" name="specialty" value="<?= e((string) $d['specialty']) ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="f_bio">Bio</label>
                    <textarea id="f_bio" name="bio"><?= e((string) $d['bio']) ?></textarea>
                </div>
                <div class="field">
                    <label for="f_sort">Sort order</label>
                    <input type="number" id="f_sort" name="sort_order" value="<?= e((string) $d['sort_order']) ?>">
                </div>
                <div class="field">
                    <label for="f_photo">Photo</label>
                    <?php if ((string) $d['photo'] !== ''): ?>
                        <img class="photo-current" src="<?= e(UPLOAD_URL . '/' . $d['photo']) ?>" alt="Current photo of <?= e((string) $d['name']) ?>">
                        <small><?= e((string) $d['photo']) ?></small>
                    <?php endif; ?>
                    <input type="file" id="f_photo" name="photo" accept="image/*">
                </div>
                <div class="field check">
                    <input type="checkbox" id="f_active" name="is_active" value="1" <?= (int) $d['is_active'] === 1 ? 'checked' : '' ?>>
                    <label for="f_active" style="margin:0;">Active</label>
                </div>

                <button type="submit"><?= $action === 'edit' ? 'Update doctor' : 'Create doctor' ?></button>
            </form>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Default: list view (Req 17.1).
$stmt = db()->prepare('SELECT id, name, specialty, sort_order, is_active FROM doctors ORDER BY sort_order ASC, id ASC');
$stmt->execute();
$doctors = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Doctors &middot; Admin</title>
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
        <h1>Doctors</h1>
        <a href="dashboard.php">&larr; Dashboard</a>
    </header>
    <main>
        <?php if ($notice !== null): ?>
            <p class="notice" role="status"><?= e($notice) ?></p>
        <?php endif; ?>

        <div class="toolbar">
            <a class="btn" href="doctors.php?action=add">New doctor</a>
        </div>

        <?php if (count($doctors) === 0): ?>
            <div class="empty">No doctors yet.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Name</th><th>Specialty</th><th>Sort</th><th>Active</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $d): ?>
                        <tr>
                            <td><?= e((string) $d['name']) ?></td>
                            <td><?= e((string) $d['specialty']) ?></td>
                            <td><?= e((string) $d['sort_order']) ?></td>
                            <td>
                                <?php if ((int) $d['is_active'] === 1): ?>
                                    <span class="pill on">Active</span>
                                <?php else: ?>
                                    <span class="pill off">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn secondary" href="doctors.php?action=edit&id=<?= e((string) $d['id']) ?>">Edit</a>
                                    <form method="post" action="doctors.php" onsubmit="return confirm('Delete this doctor?');">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e((string) $d['id']) ?>">
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
