<?php
/**
 * admin/faqs.php
 *
 * FAQ_Module — the healthcare admin CRUD module for the `faqs` table. Single
 * file controlled by ?action= (list | add | edit | save | delete) and ?id=,
 * per the design.md module skeleton and following the content_blocks.php
 * conventions.
 *
 * Behaviour (Req 19.1-19.3, 6.4, 6.5):
 *   - list   : list all FAQs with question, sort order, and active state
 *              (Req 19.1).
 *   - add    : render an empty create form.
 *   - edit   : render the form populated from the selected row.
 *   - save   : POST only. On a valid CSRF token, persist question, answer,
 *              sort_order and is_active (Req 19.2).
 *   - delete : POST only. On a valid CSRF token, remove the row (Req 19.3).
 *
 * Security: prepared statements only (Req 6.4); values escaped with e() on
 * output (Req 6.5); CSRF embedded and verified, 400 on failure (Req 6.1-6.3);
 * PRG with a read-once flash.
 *
 * Requirements: 19.1, 19.2, 19.3, 6.4, 6.5.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/**
 * Fetch a single FAQ by id, or null.
 *
 * @return array<string,mixed>|null
 */
function faq_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM faqs WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// --------------------------------------------------------------------------
// POST: save and delete (Req 6.1-6.3, 19.2, 19.3).
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad request');
    }

    if ($action === 'delete') {
        // Req 19.3 — remove the FAQ row.
        $stmt = db()->prepare('DELETE FROM faqs WHERE id = ?');
        $stmt->execute([$id]);
        flash('FAQ deleted.');
        header('Location: faqs.php');
        exit;
    }

    if ($action === 'save') {
        $question  = trim((string) ($_POST['question'] ?? ''));
        $answer    = (string) ($_POST['answer'] ?? '');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        $backUrl = $id > 0 ? 'faqs.php?action=edit&id=' . $id : 'faqs.php?action=add';

        // A question is required to address the FAQ.
        if ($question === '') {
            flash('Question is required.');
            header('Location: ' . $backUrl);
            exit;
        }

        if ($id > 0) {
            $stmt = db()->prepare(
                'UPDATE faqs SET question = ?, answer = ?, sort_order = ?, is_active = ? WHERE id = ?'
            );
            $stmt->execute([$question, $answer, $sortOrder, $isActive, $id]);
            flash('FAQ updated.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO faqs (question, answer, sort_order, is_active) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$question, $answer, $sortOrder, $isActive]);
            flash('FAQ created.');
        }

        header('Location: faqs.php');
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
    $row = $action === 'edit' ? faq_find($id) : null;
    if ($action === 'edit' && $row === null) {
        flash('That FAQ could not be found.');
        header('Location: faqs.php');
        exit;
    }

    $f = [
        'question'   => (string) ($row['question'] ?? ''),
        'answer'     => (string) ($row['answer'] ?? ''),
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
        <title><?= $isEdit ? 'Edit' : 'Add' ?> FAQ</title>
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
            .hint { font-size:.78rem; color:var(--c-muted); margin:.35rem 0 0; }
            .check { display:flex; align-items:center; gap:.5rem; margin-top:1rem; }
            .check input { width:auto; }
            .check label { margin:0; }
            .actions { margin-top:1.5rem; display:flex; gap:.75rem; }
            button { min-height:44px; padding:.7rem 1.25rem; border:0; border-radius:10px; background:var(--c-primary); color:#fff; font-size:1rem; cursor:pointer; }
            a.cancel { min-height:44px; padding:.7rem 1.25rem; display:inline-flex; align-items:center; border-radius:10px; background:#eef1f4; color:var(--c-ink); text-decoration:none; }
            button:focus-visible, a:focus-visible, input:focus-visible, textarea:focus-visible { outline:3px solid rgba(14,124,123,.4); outline-offset:2px; }
        </style>
    </head>
    <body>
        <header class="bar">
            <h1><?= $isEdit ? 'Edit' : 'Add' ?> FAQ</h1>
            <a href="faqs.php">&larr; Back to list</a>
        </header>
        <main>
            <?php if ($notice !== null): ?>
                <p class="notice" role="status"><?= e($notice) ?></p>
            <?php endif; ?>

            <form class="card" method="post"
                  action="faqs.php?action=save<?= $isEdit ? '&id=' . (int) $id : '' ?>" novalidate>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                <label for="question">Question</label>
                <input type="text" id="question" name="question" required maxlength="300" value="<?= e($f['question']) ?>">

                <label for="answer">Answer</label>
                <textarea id="answer" name="answer"><?= e($f['answer']) ?></textarea>

                <label for="sort_order">Sort order</label>
                <input type="number" id="sort_order" name="sort_order" value="<?= e((string) $f['sort_order']) ?>">
                <p class="hint">Lower numbers appear first in the public FAQ accordion.</p>

                <div class="check">
                    <input type="checkbox" id="is_active" name="is_active" value="1" <?= $f['is_active'] === 1 ? 'checked' : '' ?>>
                    <label for="is_active">Active</label>
                </div>

                <div class="actions">
                    <button type="submit"><?= $isEdit ? 'Save changes' : 'Create FAQ' ?></button>
                    <a class="cancel" href="faqs.php">Cancel</a>
                </div>
            </form>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Default: list view (Req 19.1).
$rows = db()->query('SELECT id, question, sort_order, is_active FROM faqs ORDER BY sort_order ASC, id ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>FAQs</title>
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
        <h1>FAQs</h1>
        <a href="dashboard.php">&larr; Dashboard</a>
    </header>
    <main>
        <?php if ($notice !== null): ?>
            <p class="notice" role="status"><?= e($notice) ?></p>
        <?php endif; ?>

        <div class="toolbar">
            <span><?= count($rows) ?> FAQ<?= count($rows) === 1 ? '' : 's' ?></span>
            <a class="btn" href="faqs.php?action=add">+ Add FAQ</a>
        </div>

        <?php if (count($rows) === 0): ?>
            <p class="empty">No FAQs yet. Add your first one.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th scope="col">Question</th>
                        <th scope="col">Sort order</th>
                        <th scope="col">Active</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['question']) ?></td>
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
                                    <a href="faqs.php?action=edit&id=<?= (int) $row['id'] ?>">Edit</a>
                                    <form method="post"
                                          action="faqs.php?action=delete&id=<?= (int) $row['id'] ?>"
                                          onsubmit="return confirm('Delete this FAQ?');">
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
