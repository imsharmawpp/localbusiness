<?php
/**
 * doctors.php
 *
 * Healthcare public "Doctors / Team" page (Req 12.2, 17.1). Lists every active
 * doctor ordered by sort order and renders each as a card with photo, name,
 * qualification, specialty, and bio.
 *
 * Page contract (see includes/header.php / includes/footer.php):
 *   - set $page_title and $page_desc for per-page SEO,
 *   - load the shared engine (config, db, functions, settings),
 *   - include header.php, render the body, then footer.php.
 *
 * Security & quality:
 *   - active doctors are selected with a prepared statement (Req 6.4),
 *   - every dynamic value is escaped with e() on output (Req 6.5),
 *   - each photo resolves to UPLOAD_URL . '/' . photo and carries
 *     width/height/loading="lazy"/alt per Req 11.4.
 *
 * Requirements: 12.2, 17.1, 11.4.
 */

declare(strict_types=1);

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/settings.php';

// --- Active doctors ordered by sort order (prepared statement, Req 6.4) -----
$stmt = db()->prepare(
    'SELECT name, qualification, specialty, photo, bio
       FROM doctors
      WHERE is_active = 1
   ORDER BY sort_order ASC, id ASC'
);
$stmt->execute();
$doctors = $stmt->fetchAll();

$siteName = setting('site_name');

// Per-page SEO (Req 20.1).
$page_title = ($siteName !== '' ? $siteName . ' — ' : '') . 'Meet Our Doctors';
$page_desc  = 'Meet our experienced, patient-first dental team and find the right specialist for your care.';

require __DIR__ . '/includes/header.php';
?>

  <section class="section">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow">Our team</span>
        <h1>Meet our doctors</h1>
        <p>Experienced, friendly clinicians dedicated to gentle, high-quality care.</p>
      </div>

      <?php if (count($doctors) === 0): ?>
        <p>Our team details are coming soon. Please check back shortly.</p>
      <?php else: ?>
        <div class="grid feature-grid">
          <?php foreach ($doctors as $doc): ?>
            <?php
            $name          = (string) ($doc['name'] ?? '');
            $qualification = (string) ($doc['qualification'] ?? '');
            $specialty     = (string) ($doc['specialty'] ?? '');
            $bio           = (string) ($doc['bio'] ?? '');
            $photo         = (string) ($doc['photo'] ?? '');
            $photoUrl      = $photo !== ''
                ? rtrim(UPLOAD_URL, '/') . '/' . ltrim($photo, '/')
                : '';
            ?>
            <article class="feature doctor-card">
              <?php if ($photoUrl !== ''): ?>
                <img class="doctor-card__photo"
                     src="<?= e($photoUrl) ?>"
                     width="320" height="320" loading="lazy"
                     alt="Portrait of <?= e($name) ?>">
              <?php endif; ?>

              <h3 class="doctor-card__name"><?= e($name) ?></h3>

              <?php if ($qualification !== ''): ?>
                <p class="doctor-card__qualification"><?= e($qualification) ?></p>
              <?php endif; ?>

              <?php if ($specialty !== ''): ?>
                <p class="doctor-card__specialty"><strong><?= e($specialty) ?></strong></p>
              <?php endif; ?>

              <?php if ($bio !== ''): ?>
                <p class="doctor-card__bio"><?= nl2br(e($bio)) ?></p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

<?php
require __DIR__ . '/includes/footer.php';
