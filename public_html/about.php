<?php
/**
 * about.php
 *
 * Healthcare public "About" page (Req 12.2). Renders the editable `about`
 * content block (title / body / image) managed through the Content_Blocks_Module
 * when present, followed by a static clinic introduction section.
 *
 * Page contract (see includes/header.php / includes/footer.php):
 *   - set $page_title and $page_desc for per-page SEO,
 *   - load the shared engine (config, db, functions, settings),
 *   - include header.php, render the body, then footer.php.
 *
 * Security: the content block is fetched with a prepared statement (Req 6.4)
 * and every dynamic value is escaped with e() on output (Req 6.5). The block
 * image, when set, resolves to UPLOAD_URL . '/' . image and carries
 * width/height/loading="lazy"/alt per Req 11.4.
 *
 * Requirements: 12.2, 11.4.
 */

declare(strict_types=1);

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/settings.php';

// --- Editable "about" content block (prepared statement, Req 6.4) -----------
$stmt = db()->prepare(
    'SELECT title, body, image FROM content_blocks WHERE block_key = ? LIMIT 1'
);
$stmt->execute(['about']);
$about = $stmt->fetch() ?: null;

// Resolve the optional block image to a public URL (Req 11.4 markup downstream).
$aboutImageUrl = '';
if ($about !== null && (string) ($about['image'] ?? '') !== '') {
    $aboutImageUrl = rtrim(UPLOAD_URL, '/') . '/' . ltrim((string) $about['image'], '/');
}

$siteName = setting('site_name');
$tagline  = setting('tagline');

// Per-page SEO (Req 20.1).
$page_title = ($siteName !== '' ? $siteName . ' — ' : '') . 'About Us';
$page_desc  = $tagline !== ''
    ? $tagline
    : 'Learn about our clinic, our patient-first philosophy, and the care we provide.';

require __DIR__ . '/includes/header.php';
?>

  <?php if ($about !== null): ?>
  <section class="section">
    <div class="container">
      <div class="hero__grid">
        <div>
          <h1><?= e((string) ($about['title'] ?? 'About Us')) ?></h1>
          <?php if ((string) ($about['body'] ?? '') !== ''): ?>
            <div class="about-body">
              <?php
              // Preserve paragraph breaks from the stored copy while still escaping.
              foreach (preg_split('/\R{2,}/', trim((string) $about['body'])) as $para):
                  $para = trim($para);
                  if ($para === '') {
                      continue;
                  }
              ?>
                <p><?= nl2br(e($para)) ?></p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($aboutImageUrl !== ''): ?>
          <div class="hero__media">
            <img src="<?= e($aboutImageUrl) ?>"
                 width="640" height="480" loading="lazy"
                 alt="<?= e((string) ($about['title'] ?? $siteName) . ' at ' . $siteName) ?>">
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="section section--surface">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow">Our clinic</span>
        <h2>Caring for your smile, every visit</h2>
        <p>
          <?php if ($tagline !== ''): ?>
            <?= e($tagline) ?>
          <?php else: ?>
            We combine modern dentistry with a warm, patient-first approach so
            every appointment feels calm, clear, and comfortable.
          <?php endif; ?>
        </p>
      </div>

      <div class="grid feature-grid">
        <div class="feature">
          <span class="feature__icon" aria-hidden="true">&#9733;</span>
          <h3>Patient-first care</h3>
          <p>Gentle, unhurried treatment with clear explanations at every step.</p>
        </div>
        <div class="feature">
          <span class="feature__icon" aria-hidden="true">&#10010;</span>
          <h3>Modern treatments</h3>
          <p>Up-to-date techniques and equipment for precise, lasting results.</p>
        </div>
        <div class="feature">
          <span class="feature__icon" aria-hidden="true">&#9742;</span>
          <h3>Easy to reach</h3>
          <p>Book online, call us, or message on WhatsApp — whatever suits you.</p>
        </div>
      </div>
    </div>
  </section>

<?php
require __DIR__ . '/includes/footer.php';
