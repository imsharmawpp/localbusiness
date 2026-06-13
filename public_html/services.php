<?php
/**
 * services.php
 *
 * Services_List_Page — the public listing of every active service (Req 12.3).
 *
 * Request lifecycle (per design.md): the page sets its per-page SEO variables,
 * loads the shared engine (config, db, functions, settings), includes the
 * shared header, renders its body using the main.css design-system classes,
 * then includes the shared footer.
 *
 * Behaviour (Req 12.3, 6.5):
 *   - Fetches every active service ordered by sort_order via a prepared
 *     statement, ascending (Req 12.3).
 *   - Renders each service as a .service-card showing its image (when set),
 *     name, short description, a "From {price}" line when price_from is not
 *     null, and a clean-URL link to /services/{slug} (Req 6.5).
 *   - Provides a section intro heading and a Book Appointment CTA.
 *   - Every dynamic value is escaped through e() (Req 6.5).
 *
 * Requirements: 12.3, 6.5.
 */

declare(strict_types=1);

// --- Shared engine (request lifecycle order) --------------------------------
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/settings.php';

// --- Per-page SEO -----------------------------------------------------------
$site_name  = setting('site_name');
$page_title = 'Our Services' . ($site_name !== '' ? ' | ' . $site_name : '');
$page_desc  = 'Explore the dental and wellness services we offer'
    . ($site_name !== '' ? ' at ' . $site_name : '')
    . '. Book your appointment today.';

// --- Data: all active services, ordered by sort order (Req 12.3) ------------
// A prepared statement is used even though there are no bound parameters, to
// keep every query path consistent and parameterisable.
$stmt = db()->prepare(
    'SELECT id, name, slug, short_desc, image, price_from
       FROM services
      WHERE is_active = 1
   ORDER BY sort_order ASC, id ASC'
);
$stmt->execute();
$services = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
  <section class="section">
    <div class="container">

      <div class="section__head">
        <span class="eyebrow">What we offer</span>
        <h1>Our Services</h1>
        <p>From routine check-ups to specialist treatments, explore the care we
           provide and book the visit that's right for you.</p>
        <p>
          <a class="btn btn--primary" href="/appointment">Book Appointment</a>
        </p>
      </div>

<?php if (count($services) === 0): ?>
      <p class="section__empty">Our services will be listed here soon. Please
         <a href="/contact">contact us</a> for details.</p>
<?php else: ?>
      <div class="grid service-grid">
<?php foreach ($services as $service): ?>
<?php
        $name      = (string) ($service['name'] ?? '');
        $slug      = (string) ($service['slug'] ?? '');
        $shortDesc = (string) ($service['short_desc'] ?? '');
        $image     = (string) ($service['image'] ?? '');
        $priceFrom = $service['price_from'] ?? null;
        $serviceUrl = '/services/' . $slug;
?>
        <article class="service-card">
<?php if ($image !== ''): ?>
          <div class="service-card__media">
            <img src="<?= e(rtrim(UPLOAD_URL, '/') . '/' . ltrim($image, '/')) ?>"
                 alt="<?= e($name) ?>" width="640" height="400" loading="lazy">
          </div>
<?php endif; ?>
          <div class="service-card__body">
            <h3><a href="<?= e($serviceUrl) ?>"><?= e($name) ?></a></h3>
<?php if ($shortDesc !== ''): ?>
            <p class="service-card__desc"><?= e($shortDesc) ?></p>
<?php endif; ?>
            <div class="service-card__foot">
<?php if ($priceFrom !== null && $priceFrom !== ''): ?>
              <span class="service-card__price">From <?= e(number_format((float) $priceFrom, 2)) ?></span>
<?php else: ?>
              <span></span>
<?php endif; ?>
              <a class="service-card__link" href="<?= e($serviceUrl) ?>">
                Learn more
                <span class="visually-hidden"> about <?= e($name) ?></span>
              </a>
            </div>
          </div>
        </article>
<?php endforeach; ?>
      </div>
<?php endif; ?>

    </div>
  </section>
<?php
require __DIR__ . '/includes/footer.php';
