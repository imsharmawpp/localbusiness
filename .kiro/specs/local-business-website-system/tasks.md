# Implementation Plan: Local Business Website System — First Pass

## Overview

This plan converts the design into incremental PHP 8.2 (PDO + MySQL) coding steps for Hostinger shared hosting — no Composer, no framework, no npm/build. Work proceeds in the build order of `00-SHARED-ARCHITECTURE.md` §9: database schema + seed, shared includes, admin auth + shared modules, shared frontend, healthcare admin modules, healthcare public pages, SEO/infra, standalone property tests, and finally the deployment README. Each task builds on prior tasks and ends by wiring components into the running site, leaving no orphaned code. Only the shared engine, shared frontend, and the Healthcare/Wellness vertical are in scope — the other three verticals are excluded.

Tasks marked with `*` are optional (tests). The implementation language is **PHP 8.2** throughout (no language-selection step is needed).

## Tasks

- [x] 1. Database schema and seed deliverables
  - [x] 1.1 Write `db/schema.sql` with shared and healthcare tables
    - Define shared tables `admin_users`, `site_settings`, `content_blocks`, `leads`, `testimonials` verbatim per `00` §3
    - Define healthcare tables `services`, `doctors`, `appointments` (with `service_id` FK `ON DELETE SET NULL`), `faqs` verbatim per `01`
    - All tables `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`
    - _Requirements: 23.1, 23.2_

  - [x] 1.2 Write `db/seed.sql` with demo content, settings, and admin user
    - Seed 5 services (Dental Checkup, Cleaning, Root Canal, Braces, Whitening), 2 doctors, 6 FAQs, 4 testimonials
    - Seed all `site_settings` keys from `00` §3 plus `schema_type` (default `Dentist`); set `hours` as JSON in the `is_open()` format and `primary_color` `#0e7c7b`
    - Seed one `admin_users` row with a pre-generated `PASSWORD_DEFAULT` hash; add a SQL comment noting the throwaway `password_hash()` generator script and that it must not be committed
    - _Requirements: 23.3, 23.4, 23.5_

- [x] 2. Shared includes (config, DB, helpers, settings loader)
  - [x] 2.1 Create `includes/config.sample.php` and gitignore `config.php`
    - Define `DB_NAME/DB_USER/DB_PASS/DB_HOST`, `SITE_URL`, `BASE_PATH`, `UPLOAD_DIR`, `UPLOAD_URL`, `UPLOAD_MAX_BYTES`, `UPLOAD_ALLOWED_MIME`, `ENVIRONMENT`
    - Set production error flags (`display_errors=0`, `log_errors=1`, `error_log` path) when `ENVIRONMENT==='production'`
    - Add `includes/config.php` to `.gitignore`; commit `config.sample.php` documenting the shape
    - _Requirements: 1.1, 1.5, 22.4_

  - [x] 2.2 Implement `includes/db.php` PDO singleton
    - Provide `db(): PDO` returning a single shared instance with utf8mb4 DSN, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`
    - On connection failure, `error_log` the driver message and emit a generic HTTP 500 message with no detail leaked
    - _Requirements: 1.2, 1.3, 1.4_

  - [x] 2.3 Implement `includes/functions.php` helper library
    - Implement `e()` (htmlspecialchars `ENT_QUOTES|ENT_SUBSTITUTE`, UTF-8), `slug()` (ASCII transliterate, lowercase, collapse non-alphanumerics to single hyphen, trim), `flash()` (set / read-and-clear via session), `csrf_token()` (session token, create if absent), `csrf_verify()` (timing-safe `hash_equals`)
    - Implement `upload()` validating real MIME via `finfo` against `UPLOAD_ALLOWED_MIME` and size against `UPLOAD_MAX_BYTES`, renaming to `time()_slug.ext`, moving into `UPLOAD_DIR`, returning an error array on failure (never throws)
    - Implement `is_open(hoursJson, now)` parsing the weekday→intervals JSON and reporting open/closed label
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 6.2, 6.3, 15.1, 15.2, 15.3, 22.3_

  - [ ]* 2.4 Write property tests for pure helpers
    - **Property 1: HTML escaping neutralizes markup** — Validates Requirements 2.1, 6.5
    - **Property 2: Slugs are URL-safe and idempotent** — Validates Requirements 2.2
    - **Property 4: Flash messages are read-once** — Validates Requirements 2.5
    - **Property 5: CSRF verification matches only the session token** — Validates Requirements 2.6, 2.7, 6.1, 6.2, 6.3
    - Minimum 100 randomized iterations per property; tag each "Feature: local-business-website-system, Property N"
    - _Requirements: 2.1, 2.2, 2.5, 2.6, 2.7, 6.5_

  - [ ]* 2.5 Write property test for upload validation
    - **Property 3: Upload validation accepts exactly the allowed files** — Validates Requirements 2.3, 2.4, 22.3
    - Run against a temp directory over generated `(mime, size)` pairs; clean up after; minimum 100 iterations
    - _Requirements: 2.3, 2.4, 22.3_

  - [ ]* 2.6 Write property test for open/closed calculation
    - **Property 14: Open/closed state reflects configured hours** — Validates Requirements 15.1, 15.2, 15.3
    - Generate hours configs and reference date-times; minimum 100 iterations
    - _Requirements: 15.1, 15.2, 15.3_

  - [x] 2.7 Implement `includes/settings.php` settings loader
    - Load every `site_settings` row into `$SETTINGS` keyed by `setting_key`; provide `setting($key)` returning `''` for absent keys
    - Ensure all required keys are accessible: `site_name`, `tagline`, `phone`, `whatsapp`, `email`, `address`, `map_embed`, `hours`, `logo`, `primary_color`, `facebook`, `instagram`, `google_business_url`, `meta_default`, `schema_type`
    - _Requirements: 3.1, 3.2, 3.3_

  - [ ]* 2.8 Write property test for settings loader
    - **Property 6: Settings load faithfully with empty defaults** — Validates Requirements 3.1, 3.2, 3.3
    - Use small in-memory/fixture datasets; minimum 100 iterations
    - _Requirements: 3.1, 3.2, 3.3_

- [x] 3. Checkpoint - shared includes
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Shared admin: authentication and dashboard
  - [x] 4.1 Implement `admin/auth.php` session guard
    - Configure session cookie (`httponly`, `samesite=Lax`, `secure` only on HTTPS) before `session_start()`
    - Redirect unauthenticated requests to `index.php` on every admin page except the login
    - _Requirements: 5.3, 5.4, 5.5_

  - [x] 4.2 Implement `admin/index.php` login
    - Verify submitted email/password against `admin_users.password_hash` via `password_verify`
    - On failure, display a generic authentication-failure message; embed and verify a CSRF token
    - _Requirements: 5.1, 5.2, 6.1, 6.2_

  - [x] 4.3 Implement `admin/dashboard.php` and `admin/logout.php`
    - Dashboard displays navigation links to each available admin module
    - Logout destroys the session and redirects to the login
    - _Requirements: 5.6, 5.7_

- [x] 5. Shared admin CRUD modules
  - [x] 5.1 Implement `admin/settings.php` settings editor
    - Display current value of each defined `site_settings` key; persist submitted values on valid CSRF; process logo through `upload()` into the `logo` setting; show confirmation flash
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 6.4, 6.5_

  - [x] 5.2 Implement `admin/content_blocks.php` CRUD
    - List blocks with key/title; create/edit/delete on valid CSRF; reject duplicate `block_key` on a different row with an error message
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 6.4, 6.5_

  - [ ]* 5.3 Write property test for unique-key conflict rejection
    - **Property 9: Unique-key submissions are rejected on conflict** — Validates Requirements 8.4, 16.4
    - Exercise against disposable test schema with rolled-back transactions; minimum 100 iterations
    - _Requirements: 8.4, 16.4_

  - [x] 5.4 Implement `admin/testimonials.php` CRUD
    - List with author/rating/active; persist author, role, quote, rating, photo, sort order, active on valid CSRF; process photo through `upload()`
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 6.4, 6.5_

  - [x] 5.5 Implement `admin/leads.php` inbox with CSV export
    - List leads by creation time with name/contact/source/read state; mark-read sets `is_read=1`; delete removes the row; CSV export writes a header row then one row per lead via `fputcsv`
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 6.4, 6.5_

  - [ ]* 5.6 Write property tests for injection safety and CSV structure
    - **Property 8: Injection-bearing input is stored verbatim and harmlessly** — Validates Requirements 6.4
    - **Property 10: CSV export is structurally consistent** — Validates Requirements 10.4, 18.3
    - Use disposable test schema and prepared statements; minimum 100 iterations each
    - _Requirements: 6.4, 10.4, 18.3_

- [x] 6. Checkpoint - shared admin
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Shared frontend design system
  - [x] 7.1 Implement `assets/css/main.css` tokens, base, and components
    - Define `:root` tokens verbatim per `00` §5; mobile-first base/reset; fluid `clamp()` typography
    - Build reusable components (header/nav, hero, feature grid, service cards, testimonial slider, FAQ accordion, contact block, footer); min 44px hit targets; visible `:focus-visible`; `prefers-reduced-motion` block
    - _Requirements: 11.1, 11.2, 11.3, 11.5, 11.6_

  - [x] 7.2 Implement `assets/css/vertical.css` healthcare teal overrides
    - Apply teal palette override and clinic-specific component restyles (restyle, not rebuild)
    - _Requirements: 11.1, 11.3_

  - [x] 7.3 Implement `assets/js/main.js` interactions
    - Nav hamburger toggle with `aria-expanded`; client-side required-field hints + disable-on-submit; testimonial slider (prev/next, dots, auto-advance respecting reduced motion); FAQ accordion (single-open, keyboard accessible)
    - _Requirements: 11.3, 11.5, 11.6, 19.4_

  - [x] 7.4 Implement `includes/header.php` with SEO, JSON-LD, and sticky nav
    - Render `<head>` per-page `$page_title`/`$page_desc`, OG/Twitter tags from settings, Google Fonts preconnect
    - Inline `localbusiness_jsonld()` Schema_Generator building from `$SETTINGS` with `@type` from `schema_type` defaulting to `Dentist`
    - Sticky header: logo, primary nav, click-to-call from `phone`, Book Appointment CTA, floating WhatsApp from `whatsapp`
    - _Requirements: 4.1, 4.2, 11.4, 11.7, 20.1, 20.2, 20.3, 20.4, 12.6_

  - [x] 7.5 Implement `includes/footer.php`
    - Render address, hours with computed open/closed badge via `is_open()`, social links; render Google Business Profile link only when `google_business_url` is non-empty; close with `main.js`
    - _Requirements: 4.3, 4.4, 15.4_

  - [ ]* 7.6 Write property tests for footer GBP link and JSON-LD
    - **Property 7: Google Business link rendered iff configured** — Validates Requirements 4.4
    - **Property 17: JSON-LD is valid and resolves @type correctly** — Validates Requirements 20.2, 20.3
    - Use in-memory settings fixtures; minimum 100 iterations each
    - _Requirements: 4.4, 20.2, 20.3_

- [x] 8. Checkpoint - shared frontend
  - Ensure all tests pass, ask the user if questions arise.

- [x] 9. Healthcare admin modules
  - [x] 9.1 Implement `admin/services.php` CRUD
    - List with name/slug/sort/active; persist name, slug, short desc, body, icon, image, price-from, sort, active on valid CSRF; process image through `upload()`; reject duplicate `slug` on a different row with an error message
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 6.4, 6.5_

  - [x] 9.2 Implement `admin/doctors.php` CRUD
    - List with name/specialty/sort/active; persist name, qualification, specialty, photo, bio, sort, active on valid CSRF; process photo through `upload()`
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 6.4, 6.5_

  - [x] 9.3 Implement `admin/appointments.php` inbox with status and CSV
    - List by creation time with patient/phone/service/date/slot/status; update status only to allowlisted `new|confirmed|done|cancelled` on valid CSRF; CSV export with header row via `fputcsv`
    - _Requirements: 18.1, 18.2, 18.3, 6.4, 6.5_

  - [ ]* 9.4 Write property test for appointment status allowlist
    - **Property 15: Appointment status stays within the allowed set** — Validates Requirements 18.2
    - Generate valid and invalid status values; minimum 100 iterations
    - _Requirements: 18.2_

  - [x] 9.5 Implement `admin/faqs.php` CRUD
    - List with question/sort/active; persist question, answer, sort, active on valid CSRF; delete on valid CSRF
    - _Requirements: 19.1, 19.2, 19.3, 6.4, 6.5_

- [x] 10. Checkpoint - healthcare admin
  - Ensure all tests pass, ask the user if questions arise.

- [x] 11. Healthcare public pages
  - [x] 11.1 Implement `index.php` home page
    - Render hero with Book Appointment primary CTA, services grid (active), doctor intro (active), testimonials (active), FAQ accordion (active, sort order), and location with hours
    - _Requirements: 12.1, 12.6, 19.4_

  - [x] 11.2 Implement `about.php`, `doctors.php`, and `gallery.php`
    - About renders content blocks; Doctors lists active doctors by sort order; Gallery renders facility/static images with `width`/`height`/`loading`/`alt`
    - _Requirements: 12.2, 11.4, 17.1_

  - [x] 11.3 Implement `services.php` list page
    - Display all active services ordered by sort order, escaped via `e()`
    - _Requirements: 12.3, 6.5_

  - [x] 11.4 Implement `service.php` detail page with slug lookup and 404
    - Read `?slug=` (rewrite or query fallback), prepared `SELECT ... WHERE slug=? AND is_active=1`; render name, body, image, price-from when present; emit 404 view on no match
    - _Requirements: 12.4, 12.5, 6.5_

  - [ ]* 11.5 Write property test for service detail resolution
    - **Property 11: Service detail resolves by slug or 404s** — Validates Requirements 12.4, 12.5
    - Use fixture services dataset; minimum 100 iterations
    - _Requirements: 12.4, 12.5_

  - [x] 11.6 Implement `appointment.php` form with dual write
    - Collect patient name, phone, email, service (dropdown), preferred date, preferred slot (dropdown), notes; on valid CSRF + required fields, insert one `appointments` row (status `new`) and one corresponding `leads` row (`source_page='appointment'`) in a transaction; field-specific validation error and no inserts on missing field; confirmation on success
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 6.1, 6.2_

  - [ ]* 11.7 Write property test for appointment dual-write
    - **Property 12: Appointment submission dual-writes atomically** — Validates Requirements 13.3, 13.4, 13.5
    - Exercise against disposable test schema with rolled-back transactions; minimum 100 iterations
    - _Requirements: 13.3, 13.4, 13.5_

  - [x] 11.8 Implement `contact.php` form with lead capture
    - On valid CSRF + required fields, insert one `leads` row with `source_page='contact'`; field-specific validation error and no insert on missing field; confirmation on success; render Google Business Profile link from `google_business_url`
    - _Requirements: 14.1, 14.2, 14.3, 20.5, 6.1, 6.2_

  - [ ]* 11.9 Write property test for contact lead capture
    - **Property 13: Contact submission captures one lead** — Validates Requirements 14.1, 14.2
    - Exercise against disposable test schema; minimum 100 iterations
    - _Requirements: 14.1, 14.2_

  - [x] 11.10 Implement `404.php` shared not-found view
    - Render the 404 view within the shared header/footer for unknown routes and unmatched service slugs
    - _Requirements: 12.5_

  - [ ]* 11.11 Write property test for FAQ sort order rendering
    - **Property 16: FAQs render in sort order** — Validates Requirements 19.4
    - Generate FAQ sets with varied sort orders; minimum 100 iterations
    - _Requirements: 19.4_

- [x] 12. Checkpoint - public pages
  - Ensure all tests pass, ask the user if questions arise.

- [x] 13. SEO and server infrastructure
  - [x] 13.1 Implement `sitemap.php` dynamic XML
    - Emit `Content-Type: application/xml`; list static public pages plus one `<url>` per active service (`/services/{slug}`)
    - _Requirements: 21.1_

  - [ ]* 13.2 Write property test for sitemap coverage
    - **Property 18: Sitemap covers every active service** — Validates Requirements 21.1
    - Generate service sets (active/inactive); assert well-formed XML covers active only; minimum 100 iterations
    - _Requirements: 21.1_

  - [x] 13.3 Create `robots.txt` and root `.htaccess`
    - `robots.txt` references `Sitemap: https://.../sitemap.php`
    - `.htaccess`: force HTTPS 301; rewrite `^services/([a-z0-9-]+)/?$` → `service.php?slug=$1` (with query fallback equivalence); deny `/includes`; `ErrorDocument 404` → `404.php`
    - _Requirements: 21.2, 21.3, 21.4, 22.1_

  - [x] 13.4 Create `assets/uploads/.htaccess` to block PHP execution
    - `php_flag engine off` plus handler removal so uploaded files cannot execute
    - _Requirements: 22.2_

- [x] 14. Standalone test harness and QA reference
  - [x] 14.1 Implement `tests/run_tests.php` harness and wire property tests
    - Plain-PHP runner loading `includes/functions.php`; `assert_true($cond, $label)` counting pass/fail and exiting non-zero on any failure
    - Aggregate all property tests authored in earlier tasks (Properties 1–18); ensure each runs minimum 100 randomized iterations and is tagged "Feature: local-business-website-system, Property N"
    - Add a manual QA checklist reference (comment block) mirroring `00` §8/§9 for infrastructure/UI checks not amenable to property testing
    - _Requirements: 2.1, 2.2, 2.5, 2.6, 2.7, 3.1, 3.2, 3.3, 4.4, 6.3, 6.4, 8.4, 10.4, 12.4, 12.5, 13.3, 14.1, 15.1, 16.4, 18.2, 18.3, 19.4, 20.2, 21.1_

- [x] 15. Deployment documentation
  - [x] 15.1 Write `README.md` with Hostinger deploy steps
    - Document the `00` §8 steps: create DB + user, phpMyAdmin import of `schema.sql` then `seed.sql`, set PHP 8.2, edit `config.php`, upload files, set permissions (folders 755 / files 644 / `/assets/uploads` 755), install SSL, force HTTPS, point domain, log into `/admin` and change password, submit sitemap + verify GBP
    - Document the placeholder admin password matching the seeded hash and instruct change-on-first-login
    - Document the throwaway `password_hash()` script procedure and that it must not be committed
    - _Requirements: 24.1, 24.2, 24.3, 24.4_

- [x] 16. Final checkpoint
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional property/unit tests and can be skipped for a faster MVP; core implementation tasks are never optional.
- Each task references specific requirement clauses for traceability, and each test task references the design correctness property it validates.
- Property tests run via `php tests/run_tests.php` (no PHPUnit/Composer/npm), with a minimum of 100 generated iterations per property.
- DB-touching property tests (8, 9, 10, 12, 13, 15, 16, 18) run against a disposable local test schema using prepared statements and rolled-back transactions; `upload()` (Property 3) runs against a temp directory.
- Scope is the shared engine, shared frontend, and Healthcare/Wellness vertical only — the home-services, professional-services, and hospitality verticals, blog/Pro features, live booking calendar, and payments are out of scope.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "7.1", "7.3"] },
    { "id": 1, "tasks": ["1.2", "2.1", "7.2"] },
    { "id": 2, "tasks": ["2.2"] },
    { "id": 3, "tasks": ["2.3"] },
    { "id": 4, "tasks": ["2.4", "2.5", "2.6", "2.7"] },
    { "id": 5, "tasks": ["2.8", "4.1", "7.4", "7.5"] },
    { "id": 6, "tasks": ["4.2", "4.3", "5.1", "5.2", "5.4", "5.5", "7.6"] },
    { "id": 7, "tasks": ["5.3", "5.6", "9.1", "9.2", "9.3", "9.5"] },
    { "id": 8, "tasks": ["9.4", "11.1", "11.2", "11.3", "11.4", "11.6", "11.8", "11.10", "13.1", "13.3", "13.4"] },
    { "id": 9, "tasks": ["11.5", "11.7", "11.9", "11.11", "13.2"] },
    { "id": 10, "tasks": ["14.1"] },
    { "id": 11, "tasks": ["15.1"] }
  ]
}
```
