<?php
/**
 * admin/leads.php
 *
 * Leads_Inbox — the shared admin module for reviewing, managing, and exporting
 * contact/lead submissions from the `leads` table. Single file controlled by
 * ?action= (list | read | delete | export) and ?id=, per the design.md module
 * skeleton.
 *
 * Behaviour (Req 10.1-10.4, 6.4, 6.5):
 *   - list   : list all leads ordered by creation time (newest first) with
 *              name, contact details, source page, and read state (Req 10.1).
 *   - read   : POST only. On a valid CSRF token, set is_read = 1 for the lead
 *              (Req 10.2).
 *   - delete : POST only. On a valid CSRF token, remove the row (Req 10.3).
 *   - export : stream a CSV file — a header row followed by one row per lead,
 *              written with fputcsv (Req 10.4).
 *
 * Security: prepared statements only (Req 6.4); values escaped with e() on
 * output (Req 6.5); CSRF embedded and verified for every state-changing POST,
 * 400 on failure (Req 6.1-6.3). State changes use Post/Redirect/Get + flash().
 *
 * Requirements: 10.1, 10.2, 10.3, 10.4, 6.4, 6.5.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// --------------------------------------------------------------------------
// CSV export (Req 10.4). Read-only: streamed on GET before any HTML output.
// Writes a header row then one row per lead via fputcsv.
// --------------------------------------------------------------------------
if ($action === 'export') {
    $rows = db()->query(
        'SELECT id, name, email, phone, message, source_page, is_read, created_at
           FROM leads ORDER BY created_at DESC, id DESC'
    )->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Ymd-His') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Message', 'Source Page', 'Read', 'Created At']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['name'],
            $r['email'],
            $r['phone'],
            $r['message'],
            $r['source_page'],
            ((int) $r['is_read'] === 1) ? 'yes' : 'no',
            $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// --------------------------------------------------------------------------
// POST: mark-read and delete.
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad request');
    }

    if ($action === 'read') {
        $stmt = db()->prepare('UPDATE leads SET is_read = 1 WHERE id = ?');
        $stmt->execute([$id]);
        flash('Lead marked as read.');
        header('Location: leads.php');
        exit;
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM leads WHERE id = ?');
        $stmt->execute([$id]);
        flash('Lead deleted.');
        header('Location: leads.php');
        exit;
    }

    http_response_code(400);
    exit('Bad request');
}

// --------------------------------------------------------------------------
// GET: list view (Req 10.1).
// --------------------------------------------------------------------------
$csrf   = csrf_token();
$notice = flash();
$rows   = db()->query(
    'SELECT id, name, email, phone, source_page, is_read, created_at
       FROM leads ORDER BY created_at DESC, id DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Leads</title>
    <style>
        :root { --c-primary:#0e7c7b; --c-ink:#14181f; --c-muted:#5b6573; --c-surface:#f6f8fa; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--c-surface); color:var(--c-ink); font-family: system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
        header.bar { display:flex; align-items:center; justify-content:space-between; background:#fff; padding:1rem 1.5rem; box-shadow:0 1px 0 rgba(20,24,31,.06); }
        header.bar h1 { font-size:1.2rem; margin:0; }
        header.bar a { color:var(--c-primary); text-decoration:none; font-weight:600; min-height:44px; display:inline-flex; align-items:center; }
        main { max-width:1080px; margin:0 auto; padding:1.5rem; }
        .notice { background:#e8f6f5; color:#0e5c5b; padding:.7rem 1rem; border-radius:10px; margin:0 0 1.25rem; }
        .toolbar { display:flex; justify-content:space-between; align-items:center; margin:0 0 1rem; gap:1rem; }
        a.btn { min-height:44px; padding:.6rem 1.1rem; display:inline-flex; align-items:center; border-radius:10px; background:var(--c-primary); color:#fff; text-decoration:none; font-weight:600; }
        table { width:100%; border-collapse:collapse; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 10px 30px rgba(20,24,31,.06); }
        th, td { text-align:left; padding:.8rem 1rem; border-bottom:1px solid #eef1f4; vertical-align:top; }
        th { font-size:.8rem; text-transform:uppercase; letter-spacing:.05em; color:var(--c-muted); }
        tr:last-child td { border-bottom:0; }
        tr.unread td { background:#fbfdfd; font-weight:600; }
        .contact { font-weight:400; font-size:.9rem; color:var(--c-muted); }
        .badge { display:inline-block; padding:.2rem .6rem; border-radius:999px; font-size:.8rem; font-weight:600; }
        .badge.read { background:#eef1f4; color:#5b6573; }
        .badge.unread { background:#e8f6f5; color:#0e5c5b; }
        .actions { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
        .actions form { margin:0; }
        .actions button { min-height:44px; padding:.4rem .9rem; border:0; border-radius:10px; font-weight:600; cursor:pointer; }
        .actions button.read { background:#e8f6f5; color:#0e5c5b; }
        .actions button.delete { background:#fdecec; color:#a12626; }
        button:focus-visible, a:focus-visible { outline:3px solid rgba(14,124,123,.4); outline-offset:2px; }
        .empty { background:#fff; padding:2rem; border-radius:14px; text-align:center; color:var(--c-muted); box-shadow:0 10px 30px rgba(20,24,31,.06); }
    </style>
</head>
<body>
    <header class="bar">
        <h1>Leads</h1>
        <a href="dashboard.php">&larr; Dashboard</a>
    </header>
    <main>
        <?php if ($notice !== null): ?>
            <p class="notice" role="status"><?= e($notice) ?></p>
        <?php endif; ?>

        <div class="toolbar">
            <span><?= count($rows) ?> lead<?= count($rows) === 1 ? '' : 's' ?></span>
            <a class="btn" href="leads.php?action=export">Export CSV</a>
        </div>

        <?php if (count($rows) === 0): ?>
            <p class="empty">No leads yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Source</th>
                        <th scope="col">Received</th>
                        <th scope="col">State</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $unread = (int) $row['is_read'] !== 1; ?>
                        <tr class="<?= $unread ? 'unread' : '' ?>">
                            <td><?= e($row['name']) ?></td>
                            <td class="contact">
                                <?php if (($row['email'] ?? '') !== ''): ?>
                                    <?= e($row['email']) ?><br>
                                <?php endif; ?>
                                <?= e($row['phone'] ?? '') ?>
                            </td>
                            <td><?= e($row['source_page']) ?></td>
                            <td class="contact"><?= e($row['created_at']) ?></td>
                            <td>
                                <?php if ($unread): ?>
                                    <span class="badge unread">Unread</span>
                                <?php else: ?>
                                    <span class="badge read">Read</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <?php if ($unread): ?>
                                        <form method="post" action="leads.php?action=read&id=<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                            <button type="submit" class="read">Mark read</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="leads.php?action=delete&id=<?= (int) $row['id'] ?>"
                                          onsubmit="return confirm('Delete this lead?');">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <button type="submit" class="delete">Delete</button>
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
