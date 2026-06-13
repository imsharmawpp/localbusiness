<?php
/**
 * includes/header.php
 *
 * Header_Component + Schema_Generator (Req 4.1, 4.2, 11.4, 11.7, 20.1-20.4, 12.6).
 *
 * Shared public <head> + sticky header opening markup, included by every public
 * page AFTER the page has required config.php, db.php, functions.php and
 * settings.php and after it has set its per-page SEO variables:
 *
 *     $page_title  string  unique page title          (falls back to site_name)
 *     $page_desc   string  unique meta description     (falls back to meta_default)
 *     $og_image    string  optional social share image (falls back to the logo)
 *     $canonical   string  optional canonical URL      (falls back to the request URL)
 *
 * Output contract:
 *   - Emits the document head with SEO/OG/Twitter tags, Google Fonts preconnect,
 *     and the LocalBusiness JSON-LD produced by localbusiness_jsonld($SETTINGS).
 *   - Opens <body> with an inline `--c-primary` custom property when the
 *     `primary_color` setting is non-empty (per the vertical.css convention).
 *   - Renders the sticky .site-header (brand, hamburger, primary nav, click-to-call,
 *     Book Appointment CTA) and the floating WhatsApp button.
 *   - Opens <main id="main" class="site-main"> — footer.php (task 7.5) closes it.
 *
 * Every dynamic value is escaped through e(). The markup uses the class names
 * defined in assets/css/main.css and the data-* hooks documented in
 * assets/js/main.js (data-nav-toggle / aria-controls / data-nav-menu).
 */

declare(strict_types=1);

/* Safety net: a page should already have loaded the engine, but guard the
   includes header.php depends on so a stray include cannot fatal-error. */
if (!function_exists('e')) {
    require_once __DIR__ . '/functions.php';
}
if (!function_exists('setting')) {
    require_once __DIR__ . '/settings.php';
}

if (!function_exists('hours_to_schema')) {
    /**
     * Convert the `hours` setting JSON into schema.org `openingHours` strings.
     *
     * The hours JSON maps a three-letter lowercase weekday (mon..sun) to a list
     * of [open, close] "HH:MM" intervals (the same shape consumed by is_open()).
     * schema.org expects entries such as "Mo 09:00-13:00", so one string is
     * emitted per interval using two-letter day codes.
     *
     * @param string $hoursJson JSON hours map.
     * @return array<int,string> List of openingHours specification strings.
     */
    function hours_to_schema(string $hoursJson): array
    {
        $map = json_decode($hoursJson, true);
        if (!is_array($map)) {
            return [];
        }

        $dayCodes = [
            'mon' => 'Mo', 'tue' => 'Tu', 'wed' => 'We', 'thu' => 'Th',
            'fri' => 'Fr', 'sat' => 'Sa', 'sun' => 'Su',
        ];

        $out = [];
        foreach ($dayCodes as $key => $code) {
            $intervals = $map[$key] ?? [];
            if (!is_array($intervals)) {
                continue;
            }
            foreach ($intervals as $iv) {
                if (!is_array($iv) || count($iv) < 2) {
                    continue;
                }
                $open  = (string) $iv[0];
                $close = (string) $iv[1];
                if ($open === '' || $close === '') {
                    continue;
                }
                $out[] = $code . ' ' . $open . '-' . $close;
            }
        }

        return $out;
    }
}

if (!function_exists('localbusiness_jsonld')) {
    /**
     * Schema_Generator — build the LocalBusiness JSON-LD document (Req 20.2, 20.3).
     *
     * The `@type` resolves from the `schema_type` setting, defaulting to
     * `Dentist` when the setting is empty or absent (Req 20.3). The structured
     * data is built entirely from settings: name, telephone, address, url, and
     * openingHours derived from the hours JSON (Req 20.2).
     *
     * Encoded with JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE so the URL
     * and any Unicode content stay human-readable inside the script tag.
     *
     * @param array<string,string> $S The $SETTINGS map.
     * @return string                 A JSON-LD document string.
     */
    function localbusiness_jsonld(array $S): string
    {
        $type = (isset($S['schema_type']) && $S['schema_type'] !== '')
            ? (string) $S['schema_type']
            : 'Dentist';

        $data = [
            '@context'  => 'https://schema.org',
            '@type'     => $type,
            'name'      => (string) ($S['site_name'] ?? ''),
            'telephone' => (string) ($S['phone'] ?? ''),
            'address'   => (string) ($S['address'] ?? ''),
            'url'       => SITE_URL,
        ];

        if (isset($S['email']) && $S['email'] !== '') {
            $data['email'] = (string) $S['email'];
        }

        $hours = hours_to_schema((string) ($S['hours'] ?? ''));
        if (!empty($hours)) {
            $data['openingHours'] = $hours;
        }

        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

/* --------------------------------------------------------------------------
   Per-page SEO resolution (Req 20.1)
   -------------------------------------------------------------------------- */
$title = (isset($page_title) && $page_title !== '') ? $page_title : setting('site_name');
$desc  = (isset($page_desc) && $page_desc !== '')   ? $page_desc   : setting('meta_default');

/* Resolve the logo to a public URL: a bare filename lives under UPLOAD_URL,
   while an absolute URL or rooted path is used as-is. */
$logo    = setting('logo');
$logoUrl = '';
if ($logo !== '') {
    if (preg_match('#^https?://#i', $logo) || str_starts_with($logo, '/')) {
        $logoUrl = $logo;
    } else {
        $logoUrl = rtrim(UPLOAD_URL, '/') . '/' . ltrim($logo, '/');
    }
}

/* Open Graph / Twitter share image: explicit $og_image, else the logo (Req 20.4). */
$ogImage = (isset($og_image) && $og_image !== '') ? $og_image : $logoUrl;
if ($ogImage !== '' && !preg_match('#^https?://#i', $ogImage)) {
    $ogImage = rtrim(SITE_URL, '/') . '/' . ltrim($ogImage, '/');
}

/* Canonical URL: explicit override, else SITE_URL + the request path (no query). */
if (isset($canonical) && $canonical !== '') {
    $canonicalUrl = $canonical;
} else {
    $reqPath      = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
    $canonicalUrl = rtrim(SITE_URL, '/') . ($reqPath !== false ? $reqPath : '/');
}

/* Contact actions (Req 4.1, 4.2). tel: keeps digits/plus only; wa.me digits only. */
$phone     = setting('phone');
$telHref   = $phone !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $phone) : '';
$whatsapp  = setting('whatsapp');
$waDigits  = $whatsapp !== '' ? preg_replace('/\D+/', '', $whatsapp) : '';

$siteName  = setting('site_name');
$primary   = setting('primary_color');

/* Active-link detection for aria-current on the primary nav. */
$currentFile = basename(strtok((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '?'));

/* Primary navigation: Home, About, Services, Doctors, Gallery, Contact, Book. */
$navLinks = [
    ['href' => '/',            'label' => 'Home',     'file' => 'index.php'],
    ['href' => '/about',       'label' => 'About',    'file' => 'about.php'],
    ['href' => '/services',    'label' => 'Services', 'file' => 'services.php'],
    ['href' => '/doctors',     'label' => 'Doctors',  'file' => 'doctors.php'],
    ['href' => '/gallery',     'label' => 'Gallery',  'file' => 'gallery.php'],
    ['href' => '/contact',     'label' => 'Contact',  'file' => 'contact.php'],
    ['href' => '/appointment', 'label' => 'Book',     'file' => 'appointment.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($desc) ?>">
  <link rel="canonical" href="<?= e($canonicalUrl) ?>">

  <!-- Open Graph (Req 20.4) -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= e($siteName) ?>">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($desc) ?>">
  <meta property="og:url" content="<?= e($canonicalUrl) ?>">
<?php if ($ogImage !== ''): ?>
  <meta property="og:image" content="<?= e($ogImage) ?>">
<?php endif; ?>

  <!-- Twitter card (Req 20.4) -->
  <meta name="twitter:card" content="<?= $ogImage !== '' ? 'summary_large_image' : 'summary' ?>">
  <meta name="twitter:title" content="<?= e($title) ?>">
  <meta name="twitter:description" content="<?= e($desc) ?>">
<?php if ($ogImage !== ''): ?>
  <meta name="twitter:image" content="<?= e($ogImage) ?>">
<?php endif; ?>

  <!-- Google Fonts: preconnect + Inter / Plus Jakarta Sans (Req 11.7) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">

  <!-- Design system: base tokens/components, then the healthcare skin -->
  <link rel="stylesheet" href="/assets/css/main.css">
  <link rel="stylesheet" href="/assets/css/vertical.css">

  <!-- LocalBusiness structured data (Req 20.2, 20.3) -->
  <script type="application/ld+json"><?= localbusiness_jsonld($SETTINGS ?? []) ?></script>
</head>
<?php /* Inline --c-primary overrides the stylesheet default when configured (vertical.css convention). */ ?>
<body<?= $primary !== '' ? ' style="--c-primary: ' . e($primary) . ';"' : '' ?>>
  <a class="skip-link" href="#main">Skip to content</a>

  <header class="site-header">
    <div class="container">
      <div class="site-header__bar">
        <a class="brand" href="/">
<?php if ($logoUrl !== ''): ?>
          <img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>" width="160" height="40" loading="eager">
<?php else: ?>
          <span class="brand__text"><?= e($siteName) ?></span>
<?php endif; ?>
        </a>

        <button type="button" class="nav-toggle" data-nav-toggle
                aria-controls="primary-nav" aria-expanded="false">
          <span class="nav-toggle__bars" aria-hidden="true"></span>
          <span class="visually-hidden">Toggle navigation</span>
        </button>

        <nav id="primary-nav" class="primary-nav" data-nav-menu aria-label="Primary navigation">
<?php foreach ($navLinks as $link): ?>
          <a href="<?= e($link['href']) ?>"<?= $link['file'] === $currentFile ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
<?php endforeach; ?>
        </nav>

        <div class="header-actions">
<?php if ($telHref !== ''): ?>
          <a class="call-link" href="<?= e($telHref) ?>">
            <span aria-hidden="true">&#9742;</span>
            <span class="call-link__text"><?= e($phone) ?></span>
            <span class="visually-hidden">Call <?= e($phone) ?></span>
          </a>
<?php endif; ?>
          <a class="btn btn--primary" href="/appointment">Book Appointment</a>
        </div>
      </div>
    </div>
  </header>

<?php if ($waDigits !== ''): ?>
  <a class="whatsapp-fab" href="https://wa.me/<?= e($waDigits) ?>"
     target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.33 4.97L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 1.82c2.16 0 4.18.84 5.71 2.37a8.03 8.03 0 0 1 2.37 5.72c0 4.46-3.63 8.09-8.09 8.09-1.48 0-2.93-.4-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.05 8.05 0 0 1-1.23-4.27c0-4.46 3.63-8.09 8.09-8.09Zm-2.67 4.4c-.21 0-.55.08-.84.39-.29.31-1.1 1.08-1.1 2.62 0 1.55 1.13 3.04 1.28 3.25.16.21 2.19 3.35 5.32 4.57 2.6 1.02 3.13.82 3.7.77.57-.05 1.83-.75 2.09-1.47.26-.72.26-1.34.18-1.47-.08-.13-.29-.21-.6-.36-.31-.16-1.83-.9-2.11-1-.28-.1-.49-.16-.7.16-.21.31-.8 1-.98 1.21-.18.21-.36.23-.67.08-.31-.16-1.31-.48-2.5-1.54-.92-.82-1.54-1.84-1.72-2.15-.18-.31-.02-.48.14-.63.14-.14.31-.36.47-.54.16-.18.21-.31.31-.52.1-.21.05-.39-.03-.54-.08-.16-.7-1.69-.96-2.31-.25-.61-.51-.53-.7-.54-.18-.01-.39-.01-.6-.01Z"/>
    </svg>
  </a>
<?php endif; ?>

  <main id="main" class="site-main">
