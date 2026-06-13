<?php
/**
 * admin/dashboard.php
 *
 * Admin_Dashboard — the landing page after a successful login. It displays
 * navigation links to each available admin module (Req 5.7): the shared
 * modules (Site Settings, Content Blocks, Testimonials, Leads) and the
 * healthcare modules (Services, Doctors, Appointments, FAQs), plus a logout
 * link.
 *
 * auth.php guards this page: an unauthenticated visitor is redirected to the
 * login before any of this renders (Req 5.5).
 *
 * Requirements: 5.7.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

/**
 * The admin module navigation map, grouped for display. Each entry is
 * [filename, label]. These mirror the module files defined in the design
 * deploy tree.
 *
 * @var array<string,array<int,array{0:string,1:string}>> $moduleGroups
 */
$moduleGroups = [
    'Shared' => [
        ['settings.php',        'Site Settings'],
        ['content_blocks.php',  'Content Blocks'],
        ['testimonials.php',    'Testimonials'],
        ['leads.php',           'Leads'],
    ],
    'Healthcare' => [
        ['services.php',        'Services'],
        ['doctors.php',         'Doctors'],
        ['appointments.php',    'Appointments'],
        ['faqs.php',            'FAQs'],
    ],
];

$notice = flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Dashboard</title>
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
        header.bar a.logout { color: var(--c-primary); text-decoration: none; font-weight: 600; min-height: 44px; display: inline-flex; align-items: center; }
        main { max-width: 980px; margin: 0 auto; padding: 1.5rem; }
        .notice { background: #e8f6f5; color: #0e5c5b; padding: .7rem 1rem; border-radius: 10px; margin: 0 0 1.25rem; }
        section { margin: 0 0 2rem; }
        section h2 { font-size: 1rem; color: var(--c-muted); text-transform: uppercase; letter-spacing: .05em; }
        nav.modules { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
        nav.modules a {
            display: flex; align-items: center; min-height: 64px; padding: 1rem 1.25rem;
            background: #fff; border-radius: 14px; text-decoration: none; color: var(--c-ink);
            font-weight: 600; box-shadow: 0 10px 30px rgba(20,24,31,.06);
        }
        nav.modules a:hover, nav.modules a:focus-visible { outline: 3px solid rgba(14,124,123,.4); outline-offset: 2px; }
    </style>
</head>
<body>
    <header class="bar">
        <h1>Admin Dashboard</h1>
        <a class="logout" href="logout.php">Log out</a>
    </header>

    <main>
        <?php if ($notice !== null): ?>
            <p class="notice" role="status"><?= e($notice) ?></p>
        <?php endif; ?>

        <?php foreach ($moduleGroups as $group => $modules): ?>
            <section>
                <h2><?= e($group) ?></h2>
                <nav class="modules" aria-label="<?= e($group) ?> modules">
                    <?php foreach ($modules as [$file, $label]): ?>
                        <a href="<?= e($file) ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </nav>
            </section>
        <?php endforeach; ?>
    </main>
</body>
</html>
