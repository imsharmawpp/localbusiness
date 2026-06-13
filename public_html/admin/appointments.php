<?php
/**
 * admin/appointments.php
 *
 * Appointments_Inbox — the healthcare admin module for reviewing, managing the
 * status of, and exporting appointment requests from the `appointments` table.
 * Single file controlled by ?action= (list | status | export), per the
 * design.md module skeleton and following the admin/leads.php conventions.
 *
 * Behaviour (Req 18.1-18.3, 6.4, 6.5):
 *   - list   : list all appointments ordered by creation time (newest first)
 *              with patient name, phone, service name (LEFT JOIN services),
 *              preferred date, preferred slot, and status (Req 18.1). Status is
 *              shown as a dropdown that POSTs an update.
 *   - status : POST only. On a valid CSRF token, update the `status` value, but
 *              only when the submitted value is in the allowlist
 *              ['new','confirmed','done','cancelled']; anything else is rejected
 *              before the prepared UPDATE so no change occurs (Req 18.2).
 *   - export : stream a CSV file — a header row followed by one row per
 *              appointment, written with fputcsv (Req 18.3).
 *
 * Security: prepared statements only (Req 6.4); values escaped with e() on
 * output (Req 6.5); CSRF embedded and verified for every state-changing POST,
 * 400 on failure (Req 6.1-6.3). State changes use Post/Redirect/Get + flash().
 *
 * Requirements: 18.1, 18.2, 18.3, 6.4, 6.5.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// The only statuses an appointment may ever hold. Used both to validate
// incoming status updates (Req 18.2) and to render the status dropdown.
const APPOINTMENT_STATUSES = ['new', 'confirmed', 'done', 'cancelled'];

// --------------------------------------------------------------------------
// CSV export (Req 18.3). Read-only: streamed on GET before any HTML output.
// Writes a header row then one row per appointment via fputcsv.
// --------------------------------------------------------------------------
if ($action === 'export') {
    $rows = db()->query(
        'SELECT a.id, a.patient_name, a.phone, a.email, s.name AS service_name,
                a.preferred_date, a.preferred_slot, a.status, a.created_at
           FROM appointments a
           LEFT JOIN services s ON s.id = a.service_id
          ORDER BY a.created_at DESC, a.id DESC'
    )->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="appointments-' . date('Ymd-His') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Patient Name', 'Phone', 'Email', 'Service', 'Preferred Date', 'Preferred Slot', 'Status', 'Created At']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['patient_name'],
            $r['phone'],
            $r['email'],
            $r['service_name'],
            $r['preferred_date'],
            $r['preferred_slot'],
            $r['status'],
            $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// --------------------------------------------------------------------------
// POST: status update (Req 18.2).
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad request');
    }

    if ($action === 'status') {
        $status = (string) ($_POST['status'] ?? '');

        // Allowlist validation BEFORE the prepared UPDATE: reject any value not
        // in the permitted set so the stored status can never drift out of the
        // allowed enum (Req 18.2). No database change occurs on rejection.
        if (!in_array($status, APPOINTMENT_STATUSES, true)) {
            flash('Invalid status value; no change was made.');
            header('Location: appointments.php');
            exit;
        }

        $stmt = db()->prepare('UPDATE appointments SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        flash('Appointment status updated.');
        header('Location: appointments.php');
        exit;
    }

    http_response_code(400);
    exit('Bad request');
}

// --------------------------------------------------------------------------
// GET: list view (Req 18.1).
// --------------------------------------------------------------------------
$csrf   = csrf_token();
$notice = flash();
$rows   = db()->query(
    'SELECT a.id, a.patient_name, a.phone, s.name AS service_name,
            a.preferred_date, a.preferred_slot, a.status, a.created_at
       FROM appointments a
       LEFT JOIN services s ON s.id = a.service_id
      ORDER BY a.created_at DESC, a.id DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Appointments</title>
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
        .contact { font-weight:400; font-size:.9rem; color:var(--c-muted); }
        .status-form { display:flex; gap:.5rem; align-items:center; margin:0; flex-wrap:wrap; }
        .status-form select { min-height:44px; padding:.4rem .6rem; border:1px solid #dfe4ea; border-radius:10px; background:#fff; font:inherit; color:inherit; }
        .status-form button { min-height:44px; padding:.4rem .9rem; border:0; border-radius:10px; font-weight:600; cursor:pointer; background:#e8f6f5; color:#0e5c5b; }
        button:focus-visible, a:focus-visible, select:focus-visible { outline:3px solid rgba(14,124,123,.4); outline-offset:2px; }
        .empty { background:#fff; padding:2rem; border-radius:14px; text-align:center; color:var(--c-muted); box-shadow:0 10px 30px rgba(20,24,31,.06); }
    </style>
</head>
<body>
    <header class="bar">
        <h1>Appointments</h1>
        <a href="dashboard.php">&larr; Dashboard</a>
    </header>
    <main>
        <?php if ($notice !== null): ?>
            <p class="notice" role="status"><?= e($notice) ?></p>
        <?php endif; ?>

        <div class="toolbar">
            <span><?= count($rows) ?> appointment<?= count($rows) === 1 ? '' : 's' ?></span>
            <a class="btn" href="appointments.php?action=export">Export CSV</a>
        </div>

        <?php if (count($rows) === 0): ?>
            <p class="empty">No appointment requests yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th scope="col">Patient</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Service</th>
                        <th scope="col">Preferred Date</th>
                        <th scope="col">Slot</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['patient_name']) ?></td>
                            <td class="contact"><?= e($row['phone']) ?></td>
                            <td><?= e($row['service_name'] ?? '') ?></td>
                            <td class="contact"><?= e($row['preferred_date'] ?? '') ?></td>
                            <td class="contact"><?= e($row['preferred_slot'] ?? '') ?></td>
                            <td>
                                <form class="status-form" method="post"
                                      action="appointments.php?action=status&id=<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                    <label class="sr-only" for="status-<?= (int) $row['id'] ?>">Status</label>
                                    <select id="status-<?= (int) $row['id'] ?>" name="status">
                                        <?php foreach (APPOINTMENT_STATUSES as $opt): ?>
                                            <option value="<?= e($opt) ?>"<?= $row['status'] === $opt ? ' selected' : '' ?>>
                                                <?= e(ucfirst($opt)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
