<?php
/**
 * 404.php
 *
 * Shared not-found view (Req 12.5). Rendered for any unknown route via the
 * root .htaccess `ErrorDocument 404` directive (task 13.3) and reused by
 * service.php when a `?slug=` does not match an active service.
 *
 * It boots the shared engine (config, db, functions, settings), sends an
 * explicit HTTP 404 status so crawlers and clients see the correct response,
 * then renders a friendly not-found section inside the standard public
 * header/footer chrome. Every dynamic value is escaped through e().
 *
 * Requirements: 12.5.
 */

declare(strict_types=1);

// Shared engine bootstrap (same order every public page uses).
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/settings.php';

// Signal the not-found status before any output is emitted (Req 12.5).
http_response_code(404);

// Per-page SEO metadata consumed by includes/header.php.
$page_title = 'Page not found';
$page_desc  = 'Sorry, the page you were looking for could not be found.';

require __DIR__ . '/includes/header.php';
?>

      <section class="section section--not-found">
        <div class="container">
          <p class="not-found__code">404</p>
          <h1 class="not-found__title">We couldn&rsquo;t find that page</h1>
          <p class="not-found__message">
            The page you&rsquo;re looking for may have moved or no longer exists.
            Let&rsquo;s get you back on track.
          </p>
          <div class="not-found__actions">
            <a class="btn btn--primary" href="/appointment"><?= e('Book Appointment') ?></a>
            <a class="btn btn--secondary" href="/"><?= e('Back to Home') ?></a>
          </div>
        </div>
      </section>

<?php
require __DIR__ . '/includes/footer.php';
