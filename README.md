# Local Business Website System

A productized **local-business website engine**: a reusable, configuration-driven
website you can re-skin and re-seed for many small-business clients without
rebuilding it each time.

This build ships three layers:

- **Shared Engine** — configuration, database access (PDO), helper functions,
  settings loader, shared header/footer, admin authentication + dashboard, and
  shared admin CRUD modules (Site Settings, Content Blocks, Testimonials, Leads).
- **Shared Frontend** — the design system (tokens, components, responsive
  layout) used across every vertical.
- **Healthcare / Wellness Vertical** — public pages (home, services, service
  detail, doctors, about, gallery, contact, appointment), healthcare admin
  modules (Services, Doctors, FAQs, Appointments), the teal palette,
  `LocalBusiness`/`Dentist` JSON-LD, dual-write appointment capture, and a
  computed open/closed state.

### Tech stack

- **PHP 8.2** with **PDO** + **MySQL**.
- **No Composer, no framework, no npm/build step.** Frontend is HTML5, CSS3, and
  vanilla JavaScript.
- Designed to run on **Hostinger shared hosting** (Single / Premium, hPanel)
  by plain file upload — there are no install steps to run on the server.

---

## Project structure

```
.
├── public_html/                  # the deploy root (web root on the server)
│   ├── assets/
│   │   ├── css/                  # design-system stylesheet(s)
│   │   ├── js/                   # vanilla JS
│   │   └── uploads/              # admin-uploaded media (runtime; PHP exec denied)
│   │       └── .htaccess         # blocks server-side script execution here
│   ├── includes/
│   │   ├── config.sample.php     # committed config template (copy to config.php)
│   │   ├── config.php            # real credentials (gitignored — never committed)
│   │   ├── db.php                # PDO connection
│   │   ├── functions.php         # helpers (e(), slug(), upload(), csrf, is_open()…)
│   │   ├── settings.php          # site_settings loader (setting())
│   │   ├── header.php            # shared header markup
│   │   └── footer.php            # shared footer markup
│   ├── admin/                    # admin area (auth-guarded)
│   │   ├── auth.php  index.php  dashboard.php  logout.php
│   │   ├── settings.php  content_blocks.php  testimonials.php  leads.php
│   │   └── services.php  doctors.php  faqs.php  appointments.php
│   ├── index.php  services.php  service.php  doctors.php
│   ├── about.php  gallery.php  contact.php  appointment.php
│   ├── sitemap.php  404.php
│   ├── robots.txt
│   └── .htaccess                 # HTTPS force, clean URLs, includes hardening, 404
└── db/
    ├── schema.sql                # table definitions (import FIRST)
    └── seed.sql                  # demo Healthcare content + seeded admin (import SECOND)
```

> **Note on `/db`:** the `db/` directory sits inside the deploy tree because
> shared hosting plans may not allow placing it outside `public_html`. It is
> only needed to import the database and is not web-critical afterward.

---

## Deployment — Hostinger shared hosting

These steps match `00-SHARED-ARCHITECTURE.md` §8 and the actual built structure.
They also work for a local PHP/MySQL setup (skip the SSL/domain steps locally).

1. **Create the database + user.**
   In hPanel go to **Databases > MySQL Databases** and create a database and a
   database user, then assign the user to the database. Note the name, user, and
   password. Hostinger prefixes both with an account id, e.g. `u123_clinic`
   (database name `u123_clinic`, user `u123_clinic`).

2. **Import the schema and seed data.**
   In hPanel open **phpMyAdmin** for the new database and import **`db/schema.sql`
   first**, then **`db/seed.sql`** second. Order matters — `seed.sql` depends on
   the tables created by `schema.sql`.

3. **Set the PHP version.**
   In hPanel go to **PHP Configuration** and select **PHP 8.2**.

4. **Create and edit `config.php`.**
   Copy `public_html/includes/config.sample.php` to
   `public_html/includes/config.php` and edit it:
   - `DB_NAME`, `DB_USER`, `DB_PASS` — the values from step 1.
   - `DB_HOST` — `localhost` on Hostinger.
   - `SITE_URL` — your canonical site URL with no trailing slash (e.g.
     `https://example.com`).
   - `ENVIRONMENT` — set to `production` (this disables on-screen PHP errors and
     logs them to a file outside the web root instead).

5. **Upload the site files.**
   Upload the **contents of `public_html/`** into the server's `public_html`
   directory. Easiest is **File Manager**: zip the files, upload the zip, and
   extract it into `public_html`. Alternatively use **FTP** to transfer the
   folder.

6. **Set permissions.**
   Folders `755`, files `644`. Ensure `assets/uploads/` is `755` and **writable**
   by the web server so admin image uploads succeed.

7. **Install SSL.**
   In hPanel go to **SSL** and install the free **Let's Encrypt** certificate for
   the domain.

8. **Force HTTPS.**
   This is already handled by the included `public_html/.htaccess`, which 301-
   redirects any plain-HTTP request to its HTTPS equivalent. No extra action
   needed unless you removed it.

9. **Point the domain / document root.**
   If the domain isn't already pointed at this account, set the domain or
   document root so it serves `public_html`.

10. **Log into the admin and secure it.**
    Visit `/admin`, log in with the seeded credentials (below), **change the
    admin password immediately**, then fill in the real site settings (name,
    phone, WhatsApp, email, address, hours, colors, social links, schema type).

11. **Submit to Google.**
    Submit `https://your-domain/sitemap.php` to **Google Search Console**, and
    verify the business on **Google Business Profile**.

> **Optional (Premium+):** schedule a daily lead-CSV email or DB backup via
> hPanel **Cron Jobs** calling a guarded PHP script.

---

## Admin access

The seeded login in `db/seed.sql` is:

- **Email:** `admin@brightsmiledental.example`
- **Password (placeholder):** `admin123`

This placeholder password matches the pre-generated `PASSWORD_DEFAULT` (bcrypt)
hash stored in `seed.sql`. **You must change it on first login** (it is a public,
documented default and is not safe for a live site).

## Rotating the admin password hash

If you prefer to seed a different password instead of changing it through the UI,
generate a fresh hash with a **throwaway one-off script** and paste it into the
database (or into `seed.sql` before importing):

```bash
php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT) . PHP_EOL;"
```

Copy the printed `$2y$...` hash into the `admin_users.password_hash` value, then
**delete the script/command from your shell history and do not commit it.** The
hash-generation script **must not be committed to version control** — it exists
only to mint a single hash.

To update an existing row directly in phpMyAdmin/SQL:

```sql
UPDATE admin_users
   SET password_hash = '<paste-the-generated-hash-here>'
 WHERE email = 'admin@brightsmiledental.example';
```

---

## Notes

- **`config.php` is gitignored.** Only `config.sample.php` is committed; the real
  `config.php` holds credentials and is excluded by `.gitignore` so secrets are
  never committed.
- **Uploads can't run code.** `assets/uploads/.htaccess` denies server-side script
  (PHP/CGI) execution as defence-in-depth, even though `upload()` validates real
  MIME types and sizes.
- **Clean URLs need `mod_rewrite`.** Pretty service URLs (`/services/{slug}`) are
  produced by `.htaccess` rewrites. Where rewriting is unavailable, the
  query-string form (`service.php?slug={slug}`) still works as a fallback.
- **Running tests.** Property tests run with plain PHP — no PHPUnit/Composer/npm:

  ```bash
  php tests/run_tests.php
  ```

  Each property runs a minimum of 100 generated iterations.
- **Customizing for another client.** Re-seed `db/seed.sql` with the client's
  content and adjust the site settings. To change the look and behavior:
  - **Palette:** update the `primary_color` site setting (e.g. the healthcare
    teal `#0e7c7b`) — the design tokens read from settings.
  - **Schema type:** set the `schema_type` site setting to the appropriate
    `LocalBusiness` subtype (defaults to `Dentist`; e.g. `MedicalClinic`,
    `Physician`).
  - Everything else (name, contact, hours, social, map) is editable from
    **`/admin` > Site Settings** without touching code.

---

_Requirements covered: 24.1, 24.2, 24.3, 24.4._
