<?php
/**
 * gallery.php
 *
 * Healthcare public "Gallery" page (Req 12.2). Renders a responsive
 * .gallery-grid of facility / clinic photos. There is no gallery database
 * table, so a small set of static placeholder images under /assets/img is used;
 * each entry carries width, height, loading="lazy", and a descriptive alt
 * attribute per Req 11.4.
 *
 * Page contract (see includes/header.php / includes/footer.php):
 *   - set $page_title and $page_desc for per-page SEO,
 *   - load the shared engine (config, db, functions, settings),
 *   - include header.php, render the body, then footer.php.
 *
 * All dynamic output is escaped with e() (Req 6.5).
 *
 * Requirements: 12.2, 11.4.
 */

declare(strict_types=1);

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/settings.php';

$siteName = setting('site_name');

/**
 * Static facility images. No gallery table exists (Req 12.2 scope), so these
 * placeholder entries live under /assets/img. Each carries explicit dimensions
 * and a descriptive alt for accessibility and layout stability (Req 11.4).
 *
 * @var array<int,array{src:string,alt:string}> $galleryImages
 */
$galleryImages = [
    ['src' => '/assets/img/gallery-reception.jpg',  'alt' => 'Bright, welcoming clinic reception area'],
    ['src' => '/assets/img/gallery-treatment.jpg',  'alt' => 'Modern dental treatment room with up-to-date equipment'],
    ['src' => '/assets/img/gallery-waiting.jpg',     'alt' => 'Comfortable patient waiting lounge'],
    ['src' => '/assets/img/gallery-team.jpg',        'alt' => 'Our friendly dental team at work'],
    ['src' => '/assets/img/gallery-equipment.jpg',   'alt' => 'Advanced imaging and sterilisation equipment'],
    ['src' => '/assets/img/gallery-consult.jpg',     'alt' => 'Private consultation room for treatment planning'],
];

// Per-page SEO (Req 20.1).
$page_title = ($siteName !== '' ? $siteName . ' — ' : '') . 'Gallery';
$page_desc  = 'Take a look inside our clinic — our reception, treatment rooms, and facilities.';

require __DIR__ . '/includes/header.php';
?>

  <section class="section">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow">Gallery</span>
        <h1>Inside our clinic</h1>
        <p>A look at the space and facilities designed for your comfort and care.</p>
      </div>

      <div class="grid gallery-grid">
        <?php foreach ($galleryImages as $img): ?>
          <img src="<?= e($img['src']) ?>"
               width="600" height="600" loading="lazy"
               alt="<?= e($img['alt']) ?>">
        <?php endforeach; ?>
      </div>
    </div>
  </section>

<?php
require __DIR__ . '/includes/footer.php';
