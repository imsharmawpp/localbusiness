# Design Document

## Overview

The Local Business Website System is a reusable, build-free PHP 8.2 (PDO + MySQL) website engine for Hostinger shared hosting. This first pass delivers the **shared engine**, the **shared frontend design system**, and the **Healthcare/Wellness vertical** exactly as specified in `00-SHARED-ARCHITECTURE.md` and `01-healthcare-wellness.md`.

The architecture is a **flat-PHP page model** (no front controller, no router class, no framework). Each public page and each admin module is a standalone PHP file that includes the shared engine, queries the database through prepared statements, and renders HTML escaped through a single `e()` helper. Admin modules follow a single, copy-paste CRUD pattern driven by `?action=` and `?id=`.

Design goals, in priority order:

1. **Deploy by file upload** — zero install steps, no Composer, no npm, no SSH dependency.
2. **Conversion-first healthcare UX** — phone (click-to-call), WhatsApp, and Book Appointment reachable from every screen.
3. **Local SEO is the product** — LocalBusiness JSON-LD, clean URLs, dynamic sitemap, per-page metadata.
4. **Secure by default** — prepared statements everywhere, output escaping, CSRF on every POST, hardened sessions, hardened includes/uploads.
5. **Reusable** — one token set, per-vertical palette swap; one CRUD pattern, one table per module.

This design introduces no out-of-scope verticals (home-services, professional-services, hospitality), no blog/Pro features, no live booking calendar, and no payments. The only addition beyond the literal docs is the `schema_type` setting key, which Requirement 20.3 and Assumption 7 explicitly call for.

## Architecture

### Request Lifecycle (Public Page)

```
Browser request
  -> .htaccess (HTTPS force redirect, /services/{slug} rewrite, includes/uploads deny)
  -> {page}.php
       require includes/config.php      (constants, DB creds)
       require includes/db.php          (PDO singleton)
       require includes/functions.php   (e, slug, upload, flash, csrf_*, is_open)
       require includes/settings.php    (loads $SETTINGS from site_settings)
       [POST?] csrf_verify -> validate -> prepared INSERT -> flash -> redirect (PRG)
       prepared SELECT for page data
       require includes/header.php      (SEO head, JSON-LD, sticky nav, click-to-call, WhatsApp)
         page body (escaped via e())
       require includes/footer.php      (address, hours, open/closed, social, GBP)
```

### Request Lifecycle (Admin Module)

```
Browser request
  -> admin/{module}.php
       require auth.php   (session_set_cookie_params -> session_start -> guard; redirect to index.php if not logged in)
       require ../includes/{config,db,functions}.php
       switch (?action=)
         list   (default): prepared SELECT -> table view
         add/edit (GET):   render form with csrf_token() hidden field
         save   (POST):    csrf_verify -> validate -> prepared INSERT/UPDATE -> flash -> redirect
         delete (POST):    csrf_verify -> prepared DELETE -> flash -> redirect
```

PRG (Post/Redirect/Get) is used after every mutation so flash messages display once and refreshes do not re-submit.

### Deploy Tree (per `00-SHARED-ARCHITECTURE.md` §2)

```
/public_html
  /assets
    /css
      main.css            # design tokens + base + components
      vertical.css        # healthcare teal overrides
    /js
      main.js             # nav toggle, form handling, slider, accordion
    /img                  # static theme images
    /uploads              # admin-uploaded images (chmod 755), .htaccess denies PHP exec
  /includes
    config.php            # DB creds + site constants (gitignored)
    db.php                # PDO singleton
    functions.php         # e(), slug(), upload(), flash(), csrf_token(), csrf_verify(), is_open()
    settings.php          # loads site_settings into $SETTINGS
    header.php            # <head> SEO + JSON-LD + sticky header/nav
    footer.php            # footer + closing scripts
  /admin
    /assets               # admin-only css/js
    auth.php              # session guard (included at top of every admin page)
    index.php             # login (password_verify)
    dashboard.php         # module navigation
    logout.php            # destroy session -> redirect to login
    settings.php          # Settings editor (shared)
    content_blocks.php    # CRUD (shared)
    testimonials.php      # CRUD (shared)
    leads.php             # inbox: mark-read / delete / CSV (shared)
    services.php          # CRUD (healthcare)
    doctors.php           # CRUD (healthcare)
    appointments.php      # inbox: status / CSV (healthcare)
    faqs.php              # CRUD (healthcare)
  index.php               # Home
  about.php               # About
  services.php            # Services list
  service.php             # Service detail by slug (?slug= fallback)
  doctors.php             # Doctors / Team
  gallery.php             # Gallery
  appointment.php         # Appointment form (dual-write)
  contact.php             # Contact form + page (lead capture)
  sitemap.php             # dynamic XML
  .htaccess               # HTTPS, rewrites, hardening
  robots.txt              # references sitemap
/db
  schema.sql              # shared + healthcare tables
  seed.sql                # demo content + settings + admin user
```

Per Assumption 5, `/db` sits inside the deploy tree (shared plans may not allow placement outside `public_html`); it is not web-critical after import.

### Component Inventory

| Component | File | Responsibility | Requirements |
|---|---|---|---|
| Config_Loader | `includes/config.php` | DB creds, site constants, env flag | 1.1, 1.5 |
| DB_Connector | `includes/db.php` | PDO singleton, utf8mb4, exceptions, fail-safe logging | 1.2, 1.3, 1.4 |
| Helper_Library | `includes/functions.php` | `e/slug/upload/flash/csrf_token/csrf_verify/is_open` | 2.x, 6.x, 15.x |
| Settings_Loader | `includes/settings.php` | `$SETTINGS` from `site_settings` | 3.x |
| Header_Component | `includes/header.php` | SEO head, JSON-LD, sticky nav, click-to-call, WhatsApp | 4.1, 4.2, 11.x, 20.x |
| Footer_Component | `includes/footer.php` | address, hours, open/closed, social, GBP | 4.3, 4.4, 15.4 |
| Auth_Guard | `admin/auth.php` | session cookie flags, login guard | 5.3, 5.4, 5.5 |
| Admin modules | `admin/*.php` | CRUD + inbox per pattern | 7–10, 16–19 |
| Public pages | `*.php` | render + form handling | 12–15 |
| Schema_Generator | in `header.php` | LocalBusiness JSON-LD | 20.2, 20.3 |
| Sitemap_Generator | `sitemap.php` | dynamic XML | 21.1 |

## Components and Interfaces

### config.php (Config_Loader)

Defines DB credentials and site constants. Excluded from version control via `.gitignore` (Req 1.5). A committed `config.sample.php` documents the shape.

```php
<?php
// includes/config.php  (gitignored; copy from config.sample.php)
define('DB_NAME', 'u123_clinic');
define('DB_USER', 'u123_clinic');
define('DB_PASS', 'change-me');
define('DB_HOST', 'localhost');

define('SITE_URL', 'https://example.com');
define('BASE_PATH', dirname(__DIR__));            // filesystem root of public_html
define('UPLOAD_DIR', BASE_PATH . '/assets/uploads');
define('UPLOAD_URL', '/assets/uploads');
define('UPLOAD_MAX_BYTES', 3 * 1024 * 1024);      // 3 MB size cap
define('UPLOAD_ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

define('ENVIRONMENT', 'production');              // 'production' | 'development'
```

In production the bootstrap sets `display_errors=0`, `log_errors=1`, and an `error_log` path (Req 22.4).

### db.php (DB_Connector)

Single shared PDO instance returned by `db()`. utf8mb4 + `ERRMODE_EXCEPTION`. On connection failure, logs and shows a generic message — never the driver error (Req 1.2–1.4).

```php
<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    } catch (PDOException $ex) {
        error_log('[DB] connection failed: ' . $ex->getMessage());
        http_response_code(500);
        exit('Service temporarily unavailable.');   // no details leaked
    }
    return $pdo;
}
```

### functions.php (Helper_Library)

```php
function e(?string $s): string;                 // htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')
function slug(string $s): string;               // lowercase, ASCII transliterate, [^a-z0-9]+->'-', trim '-'
function upload(array $file, string $base): array; // ['ok'=>bool,'filename'?,'error'?]
function flash(?string $msg = null): ?string;   // set when $msg given; else read-and-clear
function csrf_token(): string;                  // session token, created if absent
function csrf_verify(?string $token): bool;     // hash_equals against session token
function is_open(string $hoursJson, ?DateTimeInterface $now = null): array; // ['open'=>bool,'label'=>string]
```

- **`e()`** — wraps `htmlspecialchars` with `ENT_QUOTES | ENT_SUBSTITUTE` and UTF-8 (Req 2.1, 6.5).
- **`slug()`** — transliterates to ASCII, lowercases, collapses non-alphanumerics to single hyphens, trims hyphens (Req 2.2).
- **`upload()`** — validates real MIME via `finfo` against `UPLOAD_ALLOWED_MIME`, enforces `UPLOAD_MAX_BYTES`, renames to `time() . '_' . slug(basename) . '.' . ext`, moves into `UPLOAD_DIR`. Returns an error array on any failure; never throws to the page (Req 2.3, 2.4, 22.3).
- **`flash()`** — `flash('saved')` stores in `$_SESSION['flash']`; `flash()` returns and unsets it so it shows exactly once (Req 2.5).
- **`csrf_token()` / `csrf_verify()`** — token stored in session, created on first call; verification uses `hash_equals` (timing-safe) and returns true only on exact match (Req 2.6, 2.7, 6.2, 6.3).
- **`is_open()`** — Open_Closed_Calculator. Parses the `hours` setting (JSON map of weekday -> list of `[open,close]` HH:MM intervals) and reports open iff `$now` falls within an interval for the current weekday (Req 15.1–15.3).

`hours` setting format:

```json
{"mon":[["09:00","13:00"],["14:00","18:00"]],"tue":[["09:00","18:00"]],
 "wed":[["09:00","18:00"]],"thu":[["09:00","18:00"]],"fri":[["09:00","17:00"]],
 "sat":[["10:00","14:00"]],"sun":[]}
```

### settings.php (Settings_Loader)

Loads every `site_settings` row into `$SETTINGS` keyed by `setting_key`. A `setting($key)` accessor returns `''` for absent keys so templates never hit undefined-index notices (Req 3.1–3.3).

```php
$SETTINGS = [];
foreach (db()->query('SELECT setting_key, setting_value FROM site_settings') as $row) {
    $SETTINGS[$row['setting_key']] = $row['setting_value'];
}
function setting(string $key): string {
    global $SETTINGS;
    return isset($SETTINGS[$key]) ? (string)$SETTINGS[$key] : '';
}
```

Required keys (Req 3.2): `site_name`, `tagline`, `phone`, `whatsapp`, `email`, `address`, `map_embed`, `hours`, `logo`, `primary_color`, `facebook`, `instagram`, `google_business_url`, `meta_default`, plus the added `schema_type` (default resolved to `Dentist` at render time).

### header.php (Header_Component) + SEO

Renders `<head>` with per-page SEO variables, the LocalBusiness JSON-LD, OG/Twitter tags, Google Fonts preconnect, and the sticky header with logo, primary nav, click-to-call, and floating WhatsApp button. Pages set `$page_title`, `$page_desc`, and optional `$og_image` before including the header.

```php
$title = $page_title ?? setting('site_name');
$desc  = $page_desc  ?? setting('meta_default');
```

**Schema_Generator** (inline builder):

```php
function localbusiness_jsonld(array $S): string {
    $type = ($S['schema_type'] ?? '') !== '' ? $S['schema_type'] : 'Dentist';
    $data = [
        '@context' => 'https://schema.org',
        '@type'    => $type,
        'name'     => $S['site_name']  ?? '',
        'telephone'=> $S['phone']      ?? '',
        'address'  => $S['address']    ?? '',
        'url'      => SITE_URL,
        'openingHours' => hours_to_schema($S['hours'] ?? ''),
    ];
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
```

The `@type` resolves from `schema_type`, defaulting to `Dentist` (Req 20.2, 20.3). OG/Twitter tags are populated from settings (Req 20.4). Fonts load via Google Fonts CDN with `<link rel="preconnect">` (Req 11.7).

### footer.php (Footer_Component)

Renders address, hours with computed open/closed badge via `is_open()`, social links, and the Google Business Profile link — omitted entirely when `google_business_url` is empty (Req 4.3, 4.4, 15.4). Closes with `main.js`.

### admin/auth.php (Auth_Guard)

Configures the session cookie before `session_start()` and guards every admin page except the login.

```php
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'path'     => '/',
]);
session_start();
if (basename($_SERVER['SCRIPT_NAME']) !== 'index.php' && empty($_SESSION['admin_id'])) {
    header('Location: index.php'); exit;
}
```

Httponly + SameSite=Lax always; Secure only on HTTPS (Req 5.3–5.5).

### Admin Module Pattern (one file per module)

Every module is a single file with this skeleton (Req 6–10, 16–19):

```php
require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) { http_response_code(400); exit('Bad request'); }
    // validate -> prepared INSERT/UPDATE/DELETE -> flash() -> header('Location: module.php'); exit;
}
// GET: render list (default) or add/edit form; every form embeds csrf_token()
```

Module map:

| Module | Table | List columns | Special actions |
|---|---|---|---|
| settings | `site_settings` | key/value editor (single form) | logo `upload()` -> `logo` |
| content_blocks | `content_blocks` | block_key, title | duplicate `block_key` guard |
| testimonials | `testimonials` | author, rating, is_active | photo `upload()` -> `photo` |
| leads | `leads` | name, contact, source_page, is_read | mark-read, delete, CSV export |
| services | `services` | name, slug, sort_order, is_active | duplicate `slug` guard, image `upload()` |
| doctors | `doctors` | name, specialty, sort_order, is_active | photo `upload()` -> `photo` |
| appointments | `appointments` | patient, phone, service, date, slot, status | status update (enum), CSV export |
| faqs | `faqs` | question, sort_order, is_active | — |

**CSV export** (leads, appointments): sets `Content-Type: text/csv` and `Content-Disposition: attachment`, writes a header row then one row per record via `fputcsv` (Req 10.4, 18.3).

**Duplicate-key guards** (content_blocks `block_key`, services `slug`): before insert/update, a prepared `SELECT id WHERE key = ? AND id <> ?` detects a clash on a different row and rejects with a flash error (Req 8.4, 16.4).

**Status update** (appointments): the submitted status is validated against the allowlist `['new','confirmed','done','cancelled']`; anything else is rejected before the prepared UPDATE (Req 18.2).

### Public Pages

| Page | Data | Notes |
|---|---|---|
| `index.php` | content_blocks(hero), services(active), doctors(active), testimonials(active), faqs(active) | hero primary CTA = Book Appointment (Req 12.1) |
| `about.php` | content_blocks(about) | Req 12.2 |
| `services.php` | services WHERE is_active ORDER BY sort_order | list (Req 12.3) |
| `service.php` | service WHERE slug=? AND is_active | 404 when no match (Req 12.4, 12.5) |
| `doctors.php` | doctors WHERE is_active ORDER BY sort_order | Req 12.2/17 |
| `gallery.php` | static/img or content_blocks | Req 12.2 |
| `appointment.php` | services(active) for dropdown | dual-write (Req 13) |
| `contact.php` | settings + map_embed + GBP | lead capture (Req 14) |
| `sitemap.php` | active services | XML (Req 21.1) |

**Service detail** (`service.php`) reads `?slug=` (rewrite or query fallback), runs a prepared `SELECT ... WHERE slug = ? AND is_active = 1`; an empty result sends `http_response_code(404)` and renders the 404 view (Req 12.4, 12.5).

**Appointment dual-write** (`appointment.php`): on a valid, CSRF-verified POST with all required fields, a single transaction inserts one `appointments` row (status `new`) and one corresponding `leads` row (with `source_page='appointment'`), then PRG to a confirmation (Req 13.3–13.6, Assumption 2). Missing required fields produce a field-specific validation error and no inserts (Req 13.4). Service and slot are dropdowns (Req 13.2).

**Contact capture** (`contact.php`): valid CSRF-verified POST inserts one `leads` row with `source_page='contact'` (Req 14.1); missing fields yield a field-specific error (Req 14.2); success shows a confirmation (Req 14.3).

## Data Models

All tables `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`. Shared tables are reproduced verbatim from `00-SHARED-ARCHITECTURE.md` §3; healthcare tables verbatim from `01-healthcare-wellness.md`.

### Shared Tables

**admin_users** — `id PK`, `email VARCHAR(190) UNIQUE NOT NULL`, `password_hash VARCHAR(255) NOT NULL`, `name VARCHAR(120) NOT NULL`, `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`.

**site_settings** — `setting_key VARCHAR(100) PRIMARY KEY`, `setting_value TEXT`.

**content_blocks** — `id PK`, `block_key VARCHAR(100) UNIQUE NOT NULL`, `title VARCHAR(255)`, `body TEXT`, `image VARCHAR(255)`, `updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.

**leads** — `id PK`, `name VARCHAR(150)`, `email VARCHAR(190)`, `phone VARCHAR(40)`, `message TEXT`, `source_page VARCHAR(120)`, `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`, `is_read TINYINT(1) DEFAULT 0`.

**testimonials** — `id PK`, `author VARCHAR(150)`, `role VARCHAR(150)`, `quote TEXT`, `rating TINYINT DEFAULT 5`, `photo VARCHAR(255)`, `sort_order INT DEFAULT 0`, `is_active TINYINT(1) DEFAULT 1`.

### Healthcare Tables

**services** — `id PK`, `name VARCHAR(180) NOT NULL`, `slug VARCHAR(190) UNIQUE NOT NULL`, `short_desc VARCHAR(300)`, `body TEXT`, `icon VARCHAR(120)`, `image VARCHAR(255)`, `price_from DECIMAL(10,2) NULL`, `sort_order INT DEFAULT 0`, `is_active TINYINT(1) DEFAULT 1`.

**doctors** — `id PK`, `name VARCHAR(150) NOT NULL`, `qualification VARCHAR(190)`, `specialty VARCHAR(150)`, `photo VARCHAR(255)`, `bio TEXT`, `sort_order INT DEFAULT 0`, `is_active TINYINT(1) DEFAULT 1`.

**appointments** — `id PK`, `patient_name VARCHAR(150) NOT NULL`, `phone VARCHAR(40) NOT NULL`, `email VARCHAR(190)`, `service_id INT NULL`, `preferred_date DATE`, `preferred_slot VARCHAR(40)`, `notes TEXT`, `status ENUM('new','confirmed','done','cancelled') DEFAULT 'new'`, `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`, `FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL`.

The `service_id` FK with `ON DELETE SET NULL` means deleting a service preserves historical appointments while clearing the dangling reference (Req 16.3, data model in `01`).

**faqs** — `id PK`, `question VARCHAR(300) NOT NULL`, `answer TEXT NOT NULL`, `sort_order INT DEFAULT 0`, `is_active TINYINT(1) DEFAULT 1`.

### site_settings Keys (seed values, Req 23.4)

`site_name`, `tagline`, `phone`, `whatsapp`, `email`, `address`, `map_embed`, `hours` (JSON per `is_open()` format), `logo`, `primary_color` (`#0e7c7b`), `facebook`, `instagram`, `google_business_url`, `meta_default`, and `schema_type` (default `Dentist`; overridable to `MedicalClinic` / `Physician`, Req 20.3, Assumption 7).

### Seed Content (Req 23.3, 23.5)

5 services (Dental Checkup, Cleaning, Root Canal, Braces, Whitening), 2 doctors, 6 FAQs, 4 testimonials, all settings keys, and one `admin_users` row whose `password_hash` is a pre-generated `PASSWORD_DEFAULT` hash (plaintext documented in README, changed on first login).

## Frontend Design System

### Design Tokens — `main.css` `:root` (verbatim from `00` §5, Req 11.1)

```css
:root{
  --c-primary:#0e7c7b;
  --c-accent:#f4a259;
  --c-ink:#14181f;
  --c-muted:#5b6573;
  --c-bg:#ffffff;
  --c-surface:#f6f8fa;
  --radius:14px;
  --shadow:0 10px 30px rgba(20,24,31,.08);
  --maxw:1180px;
  --space:clamp(16px,4vw,40px);
  --font:'Inter',system-ui,sans-serif;
  --font-head:'Plus Jakarta Sans',var(--font);
}
```

### CSS Architecture (mobile-first)

- **`main.css`** — tokens, base/reset, typography (fluid `clamp()` headings), and shared components.
- **`vertical.css`** — healthcare teal palette override and any clinic-specific component restyles (restyle, not rebuild).

Layout uses CSS Grid; components use Flexbox. No CSS frameworks. Verified at 360 / 768 / 1024 / 1440 px (Req 11.2). Every content image carries `width`, `height`, `loading="lazy"`, and `alt` (Req 11.4). Interactive controls are min 44px with visible `:focus-visible` outlines (Req 11.5). A `@media (prefers-reduced-motion: reduce)` block disables non-essential animation (slider auto-advance, transitions) (Req 11.6).

### Components (Req 11.3)

| Component | Markup/behavior |
|---|---|
| Header / nav | Sticky top bar: logo, nav links, click-to-call (`tel:`), Book Appointment CTA. Mobile hamburger toggled by `main.js`. |
| Floating WhatsApp | Fixed-position button using `https://wa.me/{whatsapp}` (Req 4.2). |
| Hero | Headline + subcopy + primary CTA (Book Appointment), background/image. |
| Feature grid | "Why choose us" / trust signals in responsive Grid. |
| Service cards | Card per active service: icon/image, name, short_desc, link to detail. |
| Testimonial slider | Track of testimonial cards; vanilla-JS prev/next + auto-advance (paused under reduced motion). |
| FAQ accordion | `<button>`-driven expand/collapse; `aria-expanded`; ordered by sort_order. |
| Contact block | Address, hours + open/closed badge, `map_embed` iframe, GBP link. |
| Footer | Address, hours, social, GBP (conditional), copyright. |

### `main.js` (vanilla, no dependencies)

- **Nav toggle** — mobile hamburger open/close with `aria-expanded`.
- **Form handling** — client-side required-field hints and disable-on-submit (server remains source of truth).
- **Slider** — testimonial prev/next, dot indicators, auto-advance respecting `prefers-reduced-motion`.
- **Accordion** — FAQ expand/collapse, single-open behavior, keyboard accessible.

## SEO, Sitemap, and Clean URLs

- **Per-page metadata** — each page sets `$page_title` / `$page_desc` consumed by `header.php` (Req 20.1).
- **JSON-LD** — `localbusiness_jsonld()` builds from `$SETTINGS`; `@type` from `schema_type` (default `Dentist`) (Req 20.2, 20.3).
- **OG/Twitter** — populated from settings (Req 20.4).
- **GBP link** — footer (conditional) and Contact page (Req 20.5, 4.4).
- **`sitemap.php`** — emits `Content-Type: application/xml`; lists static public pages plus one `<url>` per active service (`/services/{slug}`) (Req 21.1).
- **`robots.txt`** — references `Sitemap: https://.../sitemap.php` (Req 21.2).
- **`.htaccess`** — HTTPS force redirect; rewrite `^services/([a-z0-9-]+)/?$` to `service.php?slug=$1`; query-string fallback `service.php?slug=` works identically when rewriting is unavailable; deny `/includes`; deny PHP execution in `/uploads` (Req 21.3, 21.4, 22.1, 22.2).

```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
RewriteRule ^services/([a-z0-9-]+)/?$ service.php?slug=$1 [L,QSA]
RewriteRule ^includes/ - [F,L]
```

`/uploads/.htaccess`: `php_flag engine off` plus a handler removal so uploaded files cannot execute.

## Security

Mapped to `00` §7 and Requirement 6/22:

- **Prepared statements everywhere** — every query uses PDO placeholders; no string-built SQL (Req 6.4). `ATTR_EMULATE_PREPARES=false` for true server-side prepares.
- **Output escaping** — all dynamic HTML passes through `e()` (Req 6.5, 2.1).
- **CSRF** — `csrf_token()` hidden field in every POST form; `csrf_verify()` (timing-safe `hash_equals`) gates every POST handler; mismatch -> 400, no mutation (Req 6.1–6.3).
- **Sessions** — httponly + SameSite=Lax always, Secure on HTTPS (Req 5.3, 5.4).
- **Includes hardening** — `.htaccess` denies direct web access to `/includes` (Req 22.1).
- **Uploads hardening** — MIME allowlist + size cap + random `time()_slug.ext` rename; `/uploads` denies PHP execution (Req 2.3, 22.2, 22.3).
- **Credentials** — `config.php` gitignored (Req 1.5); `config.sample.php` committed.
- **Production errors** — `display_errors=0`, `log_errors=1`; visitors never see PHP errors or DB driver messages (Req 1.4, 22.4).
- **HTTPS** — forced via `.htaccess` 301 redirect.

## Error Handling and 404 Strategy

| Condition | Handling |
|---|---|
| DB connect failure | Log via `error_log`; HTTP 500 generic message; no driver details (Req 1.4) |
| Unknown service slug | `http_response_code(404)` + 404 view (Req 12.5) |
| Unknown public route | Shared 404 view; `.htaccess` `ErrorDocument 404` -> `404.php` |
| Missing required form field | Re-render form with field-specific validation error; no DB write (Req 13.4, 14.2) |
| CSRF mismatch/missing | HTTP 400; no mutation (Req 6.3) |
| Upload validation failure | `upload()` returns error array; module shows flash error; no DB write (Req 2.4) |
| Duplicate block_key / slug | Reject + flash duplicate-key/slug error (Req 8.4, 16.4) |
| Invalid appointment status | Reject before UPDATE (Req 18.2) |
| Unauthenticated admin access | Redirect to `admin/index.php` (Req 5.5) |

A single `404.php` renders within the shared header/footer. PRG ensures flash errors/successes display exactly once.

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: HTML escaping neutralizes markup

*For any* input string, `e()` output contains no unescaped HTML-significant characters (`<`, `>`, `&`, `"`, `'`), and HTML-decoding the output yields the original string.

**Validates: Requirements 2.1, 6.5**

### Property 2: Slugs are URL-safe and idempotent

*For any* input string, `slug()` returns a string containing only lowercase `[a-z0-9-]` with no leading, trailing, or consecutive hyphens, and `slug(slug(x)) == slug(x)`.

**Validates: Requirements 2.2**

### Property 3: Upload validation accepts exactly the allowed files

*For any* `(mime, size)` pair, `upload()` accepts the file if and only if the MIME is in the image allowlist and the size is within the configured cap; every accepted file is renamed to the `time()_slug.ext` pattern and stored in `/assets/uploads`, and every rejected file returns an error with no file written.

**Validates: Requirements 2.3, 2.4, 22.3**

### Property 4: Flash messages are read-once

*For any* message string, setting it via `flash()` then retrieving returns that exact message, and an immediately subsequent retrieval returns empty.

**Validates: Requirements 2.5**

### Property 5: CSRF verification matches only the session token

*For any* candidate token, `csrf_verify()` returns true if and only if the candidate equals the current session token; consequently any POST handler receiving a missing or mismatched token performs no data change.

**Validates: Requirements 2.6, 2.7, 6.1, 6.2, 6.3**

### Property 6: Settings load faithfully with empty defaults

*For any* set of `site_settings` rows, `$SETTINGS[key]` equals the stored value for every present key, and `setting(key)` returns an empty string for any absent key.

**Validates: Requirements 3.1, 3.2, 3.3**

### Property 7: Google Business link rendered iff configured

*For any* settings state, the footer renders the Google Business Profile link if and only if `google_business_url` is non-empty.

**Validates: Requirements 4.4**

### Property 8: Injection-bearing input is stored verbatim and harmlessly

*For any* input string containing SQL metacharacters or keywords, submitting it through a form path stores the value exactly as given and leaves all other table data intact (prepared statements prevent injection).

**Validates: Requirements 6.4**

### Property 9: Unique-key submissions are rejected on conflict

*For any* existing `block_key` (content_blocks) or `slug` (services), submitting the same key/slug on a different row is rejected and the table is left unchanged.

**Validates: Requirements 8.4, 16.4**

### Property 10: CSV export is structurally consistent

*For any* set of leads or appointments, the exported CSV has exactly one header row plus one row per record, and every row has the same column count as the header.

**Validates: Requirements 10.4, 18.3**

### Property 11: Service detail resolves by slug or 404s

*For any* slug, the service detail page renders the matching active service's fields (name, body, image, price-from when present) escaped via `e()` if such a service exists, and returns a 404 response otherwise.

**Validates: Requirements 12.4, 12.5**

### Property 12: Appointment submission dual-writes atomically

*For any* valid appointment submission, exactly one `appointments` row (status `new`) and one corresponding `leads` row (source recorded) are created; a submission missing any required field creates neither row and returns a field-specific error.

**Validates: Requirements 13.3, 13.4, 13.5**

### Property 13: Contact submission captures one lead

*For any* valid contact submission, exactly one `leads` row is inserted with the source page recorded; a submission missing any required field inserts nothing and returns a field-specific error.

**Validates: Requirements 14.1, 14.2**

### Property 14: Open/closed state reflects configured hours

*For any* `hours` configuration and any reference date-time, `is_open()` reports open if and only if the date-time falls within a configured opening interval for that weekday.

**Validates: Requirements 15.1, 15.2, 15.3**

### Property 15: Appointment status stays within the allowed set

*For any* submitted status value, the appointment status is updated only when the value is one of `new`, `confirmed`, `done`, or `cancelled`; any other value is rejected and leaves the stored status unchanged.

**Validates: Requirements 18.2**

### Property 16: FAQs render in sort order

*For any* set of active FAQs, the accordion renders them in non-decreasing `sort_order`.

**Validates: Requirements 19.4**

### Property 17: JSON-LD is valid and resolves @type correctly

*For any* settings state, the generated LocalBusiness JSON-LD parses as valid JSON containing name, phone, address, and URL, and its `@type` equals `schema_type` when set and `Dentist` when absent.

**Validates: Requirements 20.2, 20.3**

### Property 18: Sitemap covers every active service

*For any* set of services, the generated sitemap is well-formed XML containing a URL entry for every active service and none for inactive services.

**Validates: Requirements 21.1**

## Testing Strategy

No framework, Composer, or npm is available, so testing combines a **manual QA checklist** with a **standalone PHP smoke-test script** for pure helper logic. Both are complementary: the smoke script exercises universal properties over many generated inputs; the checklist covers UI, infrastructure, and integration concerns that cannot be asserted as pure properties.

### Property/Unit Testing — Standalone PHP Script

A single committed-but-not-deployed script `tests/run_tests.php` (plain PHP, run with `php tests/run_tests.php`) loads `includes/functions.php` and asserts the correctness properties over randomized inputs. No PHPUnit. A tiny `assert_true($cond, $label)` harness counts pass/fail and exits non-zero on any failure.

- **Minimum 100 generated iterations per property test** (loop with randomized inputs).
- Each property test is tagged with **Feature: local-business-website-system, Property {number}: {property_text}**.
- Pure, side-effect-free helpers are tested directly: `e()` (Property 1), `slug()` (Property 2), `flash()` with an in-memory `$_SESSION` stub (Property 4), `csrf_token()`/`csrf_verify()` (Property 5), `is_open()` (Property 14), `localbusiness_jsonld()` (Property 17).
- DB-touching properties (8, 9, 10, 12, 13, 15, 16, 18) are exercised against a disposable local MySQL test schema using prepared statements and rolled-back transactions where possible; `upload()` (Property 3) runs against a temp directory and is cleaned up after.
- Settings loader (Property 6) and footer GBP rendering (Property 7) and service-detail 404 (Property 11) use small in-memory or fixture datasets.

### Manual QA Checklist

Performed before each deploy (per `00` §8/§9):

- **Responsive** — verify layout at 360, 768, 1024, 1440 px; sticky header, hamburger nav, floating WhatsApp, 44px hit targets, visible focus, reduced-motion.
- **Forms / CSRF** — submit appointment and contact forms with and without a valid CSRF token; confirm rejection on missing/invalid token.
- **Lead capture dual-write** — submit the appointment form; confirm one `appointments` row and one `leads` row appear in the admin inboxes.
- **Validation** — submit each form missing one required field; confirm field-specific error and no DB write.
- **Upload validation** — attempt a non-image and an oversized file; confirm rejection; confirm a valid image is renamed and stored in `/assets/uploads`.
- **SQL injection** — enter quotes/SQL keywords in form fields; confirm verbatim storage and intact data (prepared statements).
- **Schema validation** — run the live page through Google Rich Results Test; confirm LocalBusiness JSON-LD validity and correct `@type`.
- **SSL / HTTPS** — confirm `http://` 301-redirects to `https://`; confirm session cookie `Secure` flag on HTTPS.
- **Hardening** — confirm `/includes/*` returns 403; confirm a PHP file in `/uploads` does not execute.
- **SEO** — confirm unique title/meta per page; `sitemap.php` lists active services; `robots.txt` references the sitemap.
- **Admin auth** — confirm unauthenticated admin pages redirect to login; confirm logout destroys the session.
- **Deploy smoke** — import `schema.sql` + `seed.sql`; confirm seed counts (5 services, 2 doctors, 6 FAQs, 4 testimonials, all settings, 1 admin user) and first-login password change.

### Unit/Integration Balance

Property tests carry the load of input-space coverage for pure logic. Manual integration checks cover infrastructure (SSL, `.htaccess` denials, CloudWatch-equivalent server behavior, MySQL FK `ON DELETE SET NULL`) and UI rendering, which are not amenable to property-based testing. Example-based checks cover login (`password_verify`), generic auth-failure messaging, unauthenticated redirects, and clean-URL/query-fallback equivalence.
