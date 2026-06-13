<?php
/**
 * index.php
 *
 * Healthcare Home page (Home_Page component). Renders the public landing page
 * inside the shared header/footer chrome (Req 12.1, 12.6, 19.4):
 *
 *   - Hero with headline + subcopy and the primary "Book Appointment" CTA.
 *     Headline/subcopy come from the `hero` content block when present, and
 *     fall back to the site_name / tagline settings otherwise.
 *   - Feature grid ("why choose us" / trust signals) — a few static cards.
 *   - Services grid of active services (ORDER BY sort_order), each linking to
 *     /services/{slug}; images carry width/height/loading="lazy"/alt.
 *   - Doctor introduction: active doctors with a brief profile.
 *   - Testimonials slider, with markup wired to the assets/js/main.js slider
 *     data-* contract (data-slider / data-slider-track / data-slider-slide /
 *     data-slider-prev / data-slider-next / data-slider-dots).
 *   - FAQ accordion of active FAQs (ORDER BY sort_order), wired to the
 *     main.js accordion data-* contract with unique aria-controls ids.
 *   - Location block (contact-block) with address, an open/closed badge from
 *     is_open(), and the configured map_embed.
 *
 * Per the design.md public-page request lifecycle the page sets its SEO
 * variables, requires config/db/functions/settings, runs its prepared SELECTs,
 * then includes header.php (body) and footer.php. Every database read uses a
 * prepared statement (Req 6.4) and every dynamic value is escaped with e()
 * (Req 6.5); the admin-managed map embed is the single intentional exception
 * because it is raw iframe markup.
 *
 * Requirements: 12.1, 12.6, 19.4.
 */

declare(strict_types=1);

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/settings.php';

/* --------------------------------------------------------------------------
   Page data — prepared SELECTs (Req 6.4)
   -------------------------------------------------------------------------- */

// Hero content block (optional). Falls back to settings when absent (Req 12.1).
$heroStmt = db()->prepare('SELECT title, body, image FROM content_blocks WHERE block_key = ? LIMIT 1');
$heroStmt->execute(['hero']);
$hero = $heroStmt->fetch() ?: null;

$heroTitle = ($hero && trim((string) $hero['title']) !== '')
    ? (string) $hero['title']
    : setting('site_name');
$heroLead = ($hero && trim((string) $hero['body']) !== '')
    ? (string) $hero['body']
    : setting('tagline');

// Active services ordered by sort_order (Req 12.1).
$servicesStmt = db()->prepare(
    'SELECT id, name, slug, short_desc, image, price_from
       FROM services
      WHERE is_active = 1
      ORDER BY sort_order ASC, id ASC'
);
$servicesStmt->execute();
$services = $servicesStmt->fetchAll();

// Active doctors ordered by sort_order (Req 12.1, 17.1).
$doctorsStmt = db()->prepare(
    'SELECT id, name, qualification, specialty, photo, bio
       FROM doctors
      WHERE is_active = 1
      ORDER BY sort_order ASC, id ASC'
);
$doctorsStmt->execute();
$doctors = $doctorsStmt->fetchAll();

// Active testimonials ordered by sort_order (Req 12.1).
$testimonialsStmt = db()->prepare(
    'SELECT id, author, role, quote, rating, photo
       FROM testimonials
      WHERE is_active = 1
      ORDER BY sort_order ASC, id ASC'
);
$testimonialsStmt->execute();
$testimonials = $testimonialsStmt->fetchAll();

// Active FAQs ordered by sort_order (Req 19.4).
$faqsStmt = db()->prepare(
    'SELECT id, question, answer
       FROM faqs
      WHERE is_active = 1
      ORDER BY sort_order ASC, id ASC'
);
$faqsStmt->execute();
$faqs = $faqsStmt->fetchAll();

/* --------------------------------------------------------------------------
   Location data for the contact block
   -------------------------------------------------------------------------- */
$address   = setting('address');
$phone     = setting('phone');
$email     = setting('email');
$mapEmbed  = setting('map_embed');
$openState = is_open(setting('hours'));
$isOpenNow = !empty($openState['open']);
$openClass = $isOpenNow ? 'open-badge--open' : 'open-badge--closed';
$openLabel = (string) ($openState['label'] ?? ($isOpenNow ? 'Open now' : 'Closed'));
$telHref   = $phone !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $phone) : '';

/**
 * Build the public URL for an uploaded media filename. A bare filename lives
 * under UPLOAD_URL; an absolute URL or rooted path is returned unchanged.
 */
$mediaUrl = static function (string $file): string {
    if ($file === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $file) || str_starts_with($file, '/')) {
        return $file;
    }
    return rtrim(UPLOAD_URL, '/') . '/' . ltrim($file, '/');
};

// Static trust signals ("why choose us") — these are presentation copy.
$features = [
    ['icon' => "\u{2714}",  'title' => 'Experienced team',       'text' => 'Qualified professionals dedicated to your care and comfort.'],
    ['icon' => "\u{1F4C5}", 'title' => 'Easy online booking',    'text' => 'Request an appointment in seconds and we will confirm with you.'],
    ['icon' => "\u{1F46A}", 'title' => 'Patient-first approach',  'text' => 'Personalised treatment plans tailored to every visitor.'],
    ['icon' => "\u{1F3E5}", 'title' => 'Modern facility',         'text' => 'A clean, welcoming space equipped with up-to-date technology.'],
];

/* --------------------------------------------------------------------------
   SEO variables, then the shared chrome (design.md lifecycle)
   -------------------------------------------------------------------------- */
$page_title = setting('site_name') !== ''
    ? setting('site_name') . ' | ' . ($heroLead !== '' ? $heroLead : 'Book your appointment online')
    : 'Home';
$page_desc = setting('meta_default') !== '' ? setting('meta_default') : $heroLead;

require __DIR__ . '/includes/header.php';
?>

  <!-- Hero (Req 12.1) -->
  <section class="hero">
    <div class="container">
      <div class="hero__grid">
        <div class="hero__content">
          <h1 class="hero__title"><?= e($heroTitle) ?></h1>
<?php if ($heroLead !== ''): ?>
          <p class="hero__lead"><?= e($heroLead) ?></p>
<?php endif; ?>
          <div class="hero__cta">
            <a class="btn btn--primary btn--lg" href="/appointment">Book Appointment</a>
            <a class="btn btn--outline btn--lg" href="/services">Explore services</a>
          </div>
        </div>
<?php if ($hero && (string) $hero['image'] !== ''): ?>
        <div class="hero__media">
          <img src="<?= e($mediaUrl((string) $hero['image'])) ?>" alt="<?= e($heroTitle) ?>"
               width="640" height="480" loading="eager">
        </div>
<?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Feature grid: why choose us / trust signals -->
  <section class="section">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow">Why choose us</span>
        <h2>Care you can trust</h2>
        <p>A few of the reasons our patients keep coming back.</p>
      </div>
      <div class="grid feature-grid">
<?php foreach ($features as $feature): ?>
        <div class="feature">
          <span class="feature__icon" aria-hidden="true"><?= e($feature['icon']) ?></span>
          <h3><?= e($feature['title']) ?></h3>
          <p><?= e($feature['text']) ?></p>
        </div>
<?php endforeach; ?>
      </div>
    </div>
  </section>

<?php if (!empty($services)): ?>
  <!-- Services grid (active, by sort order) — Req 12.1 -->
  <section class="section section--surface">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow">What we offer</span>
        <h2>Our services</h2>
        <p>Explore the treatments and services we provide.</p>
      </div>
      <div class="grid service-grid">
<?php foreach ($services as $service): ?>
<?php $serviceImg = $mediaUrl((string) ($service['image'] ?? '')); ?>
        <article class="service-card">
<?php if ($serviceImg !== ''): ?>
          <div class="service-card__media">
            <img src="<?= e($serviceImg) ?>" alt="<?= e((string) $service['name']) ?>"
                 width="640" height="400" loading="lazy">
          </div>
<?php endif; ?>
          <div class="service-card__body">
            <h3><?= e((string) $service['name']) ?></h3>
<?php if (trim((string) ($service['short_desc'] ?? '')) !== ''): ?>
            <p class="service-card__desc"><?= e((string) $service['short_desc']) ?></p>
<?php endif; ?>
            <div class="service-card__foot">
<?php if ($service['price_from'] !== null && $service['price_from'] !== ''): ?>
              <span class="service-card__price">From <?= e(number_format((float) $service['price_from'], 2)) ?></span>
<?php else: ?>
              <span></span>
<?php endif; ?>
              <a class="service-card__link" href="/services/<?= e((string) $service['slug']) ?>">
                Learn more
                <span class="visually-hidden">about <?= e((string) $service['name']) ?></span>
              </a>
            </div>
          </div>
        </article>
<?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if (!empty($doctors)): ?>
  <!-- Doctor introduction (active, brief) — Req 12.1 -->
  <section class="section">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow">Meet the team</span>
        <h2>Our doctors</h2>
        <p>Friendly, experienced clinicians ready to help.</p>
      </div>
      <div class="grid feature-grid">
<?php foreach ($doctors as $doctor): ?>
<?php $doctorImg = $mediaUrl((string) ($doctor['photo'] ?? '')); ?>
        <article class="feature">
<?php if ($doctorImg !== ''): ?>
          <img src="<?= e($doctorImg) ?>" alt="<?= e((string) $doctor['name']) ?>"
               width="96" height="96" loading="lazy"
               style="width:96px;height:96px;border-radius:var(--radius-pill);object-fit:cover;">
<?php endif; ?>
          <h3><?= e((string) $doctor['name']) ?></h3>
<?php
          $doctorMetaParts = array_filter([
              trim((string) ($doctor['qualification'] ?? '')),
              trim((string) ($doctor['specialty'] ?? '')),
          ], static fn ($v) => $v !== '');
          // Escape each part with e(), then join with an HTML entity separator.
          $doctorMeta = implode(' &middot; ', array_map('e', $doctorMetaParts));
?>
<?php if ($doctorMeta !== ''): ?>
          <p class="testimonial__role"><?= $doctorMeta ?></p>
<?php endif; ?>
<?php if (trim((string) ($doctor['bio'] ?? '')) !== ''): ?>
          <p><?= e((string) $doctor['bio']) ?></p>
<?php endif; ?>
        </article>
<?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if (!empty($testimonials)): ?>
  <!-- Testimonials slider — wired to assets/js/main.js (Req 12.1) -->
  <section class="section section--surface">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow">Kind words</span>
        <h2>What our patients say</h2>
      </div>
      <div class="testimonials">
        <div class="slider" data-slider data-slider-interval="6000">
          <div class="slider__track" data-slider-track>
<?php foreach ($testimonials as $testimonial): ?>
<?php
            $rating      = max(0, min(5, (int) ($testimonial['rating'] ?? 0)));
            $authorImg   = $mediaUrl((string) ($testimonial['photo'] ?? ''));
            $authorName  = (string) $testimonial['author'];
?>
            <div class="slider__slide" data-slider-slide>
              <figure class="testimonial">
<?php if ($rating > 0): ?>
                <div class="testimonial__rating" aria-label="<?= e($rating . ' out of 5 stars') ?>">
                  <span aria-hidden="true"><?= e(str_repeat('★', $rating) . str_repeat('☆', 5 - $rating)) ?></span>
                </div>
<?php endif; ?>
                <blockquote class="testimonial__quote"><?= e((string) $testimonial['quote']) ?></blockquote>
                <figcaption class="testimonial__author">
<?php if ($authorImg !== ''): ?>
                  <img src="<?= e($authorImg) ?>" alt="<?= e($authorName) ?>" width="48" height="48" loading="lazy">
<?php endif; ?>
                  <span>
                    <span class="testimonial__name"><?= e($authorName) ?></span>
<?php if (trim((string) ($testimonial['role'] ?? '')) !== ''): ?>
                    <span class="testimonial__role"><?= e((string) $testimonial['role']) ?></span>
<?php endif; ?>
                  </span>
                </figcaption>
              </figure>
            </div>
<?php endforeach; ?>
          </div>
<?php if (count($testimonials) > 1): ?>
          <div class="slider__controls">
            <button type="button" class="slider__btn" data-slider-prev aria-label="Previous testimonial">
              <span aria-hidden="true">&#8249;</span>
            </button>
            <div class="slider__dots" data-slider-dots></div>
            <button type="button" class="slider__btn" data-slider-next aria-label="Next testimonial">
              <span aria-hidden="true">&#8250;</span>
            </button>
          </div>
<?php endif; ?>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if (!empty($faqs)): ?>
  <!-- FAQ accordion (active, by sort order) — wired to main.js (Req 19.4) -->
  <section class="section">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow">Good to know</span>
        <h2>Frequently asked questions</h2>
      </div>
      <div class="accordion" data-accordion>
<?php foreach ($faqs as $faq): ?>
<?php $panelId = 'faq-panel-' . (int) $faq['id']; ?>
        <div class="accordion__item">
          <button type="button" class="accordion__trigger" data-accordion-trigger
                  aria-controls="<?= e($panelId) ?>" aria-expanded="false">
            <span><?= e((string) $faq['question']) ?></span>
            <span class="accordion__icon" aria-hidden="true"></span>
          </button>
          <div id="<?= e($panelId) ?>" class="accordion__panel" data-accordion-panel hidden>
            <div class="accordion__panel-inner">
              <p><?= e((string) $faq['answer']) ?></p>
            </div>
          </div>
        </div>
<?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

  <!-- Location with hours + map (contact-block) — Req 12.1 -->
  <section class="section section--surface" id="location">
    <div class="container">
      <div class="section__head">
        <span class="eyebrow">Find us</span>
        <h2>Visit our clinic</h2>
      </div>
      <div class="contact-block">
        <div class="contact-info">
          <p>
            <span class="open-badge <?= e($openClass) ?>"><?= e($openLabel) ?></span>
          </p>
<?php if ($address !== ''): ?>
          <div class="contact-detail">
            <span class="contact-detail__icon" aria-hidden="true">&#128205;</span>
            <span>
              <span class="contact-detail__label">Address</span>
              <span class="contact-detail__value"><?= e($address) ?></span>
            </span>
          </div>
<?php endif; ?>
<?php if ($phone !== ''): ?>
          <div class="contact-detail">
            <span class="contact-detail__icon" aria-hidden="true">&#9742;</span>
            <span>
              <span class="contact-detail__label">Phone</span>
              <span class="contact-detail__value"><a href="<?= e($telHref) ?>"><?= e($phone) ?></a></span>
            </span>
          </div>
<?php endif; ?>
<?php if ($email !== ''): ?>
          <div class="contact-detail">
            <span class="contact-detail__icon" aria-hidden="true">&#9993;</span>
            <span>
              <span class="contact-detail__label">Email</span>
              <span class="contact-detail__value"><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></span>
            </span>
          </div>
<?php endif; ?>
          <div class="hero__cta">
            <a class="btn btn--primary" href="/appointment">Book Appointment</a>
          </div>
        </div>
        <div class="map-embed">
<?php if (trim($mapEmbed) !== ''): ?>
          <?= $mapEmbed /* admin-managed iframe embed markup; intentionally not escaped */ ?>
<?php else: ?>
          <p class="contact-detail__value" style="padding:1rem;">Map coming soon.</p>
<?php endif; ?>
        </div>
      </div>
    </div>
  </section>

<?php
require __DIR__ . '/includes/footer.php';
