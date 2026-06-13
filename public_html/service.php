<?php
/**
 * service.php
 *
 * Service detail page (Frontend) — resolves a single active service by slug and
 * renders its name, body, image and price-from, or returns a 404 when the slug
 * does not match an active service.
 *
 * Routing (Req 21.3, 21.4): the clean URL `/services/{slug}` is rewritten by
 * .htaccess to `service.php?slug={slug}`. When URL rewriting is unavailable the
 * equivalent query-string form `service.php?slug={slug}` works identically, so
 * the slug is always read from $_GET['slug'].
 *
 * Behaviour:
 *   - Found    : set $page_title / $page_desc, include header.php, render the
 *                service (name, body, image, "From {price}" when price_from is
 *                set, and a Book Appointment CTA), include footer.php. (Req 12.4)
 *   - Not found: http_response_code(404), include header.php, render a brief
 *                not-found message, include footer.php, exit. (Req 12.5)
 *
 * All dynamic output is escaped through e() (Req 6.5).
 *
 * Requirements: 12.4, 12.5, 6.5, 21.3, 21.4.
 */

declare(strict_types=1);

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/settings.php';

// Slug from the rewrite or the query-string fallback (Req 21.3, 21.4).
$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';

// Resolve the active service by slug via a prepared statement (Req 6.4).
$service = null;
if ($slug !== '') {
    $stmt = db()->prepare('SELECT * FROM services WHERE slug = ? AND is_active = 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if ($row !== false) {
        $service = $row;
    }
}

/* --------------------------------------------------------------------------
   Not found — emit a 404 within the shared header/footer (Req 12.5).
   A sibling owns the shared 404.php view; here we render an inline message.
   -------------------------------------------------------------------------- */
if ($service === null) {
    http_response_code(404);
    $page_title = 'Page not found';
    $page_desc  = 'The page you are looking for could not be found.';
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="section">
      <div class="container">
        <h1>Page not found</h1>
        <p>Sorry, we couldn&rsquo;t find the service you were looking for.</p>
        <p>
          <a class="btn btn--primary" href="/services">Browse all services</a>
          <a class="btn btn--ghost" href="/">Return home</a>
        </p>
      </div>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* --------------------------------------------------------------------------
   Found — render the service detail (Req 12.4).
   -------------------------------------------------------------------------- */
$page_title = (string) ($service['name'] ?? '');
$page_desc  = (string) ($service['short_desc'] ?? '');

// Resolve a bare image filename to its public uploads URL.
$serviceImage = (string) ($service['image'] ?? '');
$serviceImageUrl = '';
if ($serviceImage !== '') {
    if (preg_match('#^https?://#i', $serviceImage) || str_starts_with($serviceImage, '/')) {
        $serviceImageUrl = $serviceImage;
    } else {
        $serviceImageUrl = rtrim(UPLOAD_URL, '/') . '/' . ltrim($serviceImage, '/');
    }
}

// Price-from is rendered only when present (NULL means no published price).
$priceFrom = $service['price_from'] ?? null;
$hasPrice  = ($priceFrom !== null && $priceFrom !== '');

require __DIR__ . '/includes/header.php';
?>
    <article class="section service-detail">
      <div class="container">
        <h1 class="service-detail__title"><?= e((string) $service['name']) ?></h1>

<?php if ($hasPrice): ?>
        <p class="service-detail__price">From <?= e(number_format((float) $priceFrom, 2)) ?></p>
<?php endif; ?>

<?php if ($serviceImageUrl !== ''): ?>
        <figure class="service-detail__media">
          <img src="<?= e($serviceImageUrl) ?>" alt="<?= e((string) $service['name']) ?>"
               width="800" height="500" loading="lazy">
        </figure>
<?php endif; ?>

<?php if ((string) ($service['body'] ?? '') !== ''): ?>
        <div class="service-detail__body">
<?php
            // Preserve author line breaks while still escaping all markup.
            echo nl2br(e((string) $service['body']), false);
?>
        </div>
<?php endif; ?>

        <p class="service-detail__cta">
          <a class="btn btn--primary" href="/appointment">Book Appointment</a>
        </p>
      </div>
    </article>
<?php
require __DIR__ . '/includes/footer.php';
