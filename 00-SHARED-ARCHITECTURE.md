# 00 — Shared Architecture (All Verticals)

This doc is the reusable engine. The four vertical docs (01–04) only add
pages, content modules, DB tables, and seed data on top of this.

Read this first. Build it once. Clone per client.

---

## 1. Stack

- Frontend: HTML5, CSS3, vanilla JS. No build step.
- Backend: PHP 8.2 (PDO), MySQL.
- No Composer. No framework. Runs on Hostinger shared hosting as-is.
- Free libs via CDN only where needed (no npm).

Reason: Hostinger shared (Single/Premium) has no SSH on cheap plans and
no Composer. Pure PHP + PDO deploys by file upload. Zero install steps.

---

## 2. Folder Structure

```
/public_html
  /assets
    /css
      main.css            # design tokens + base + components
      vertical.css        # per-vertical overrides
    /js
      main.js             # nav, forms, sliders
    /img
    /uploads              # admin-uploaded images (chmod 755)
  /includes
    config.php            # DB creds, site constants
    db.php                # PDO connection
    functions.php         # helpers: e(), slug(), upload(), flash()
    settings.php          # loads site_settings into $SETTINGS
    header.php
    footer.php
  /admin
    /assets               # admin-only css/js
    index.php             # login
    dashboard.php
    logout.php
    auth.php              # session guard, include at top of every admin page
    [module].php          # one CRUD file per editable module
  index.php               # home
  [page].php              # public pages
  contact.php             # form handler + page
  sitemap.php             # dynamic XML
  .htaccess
  robots.txt
/db
  schema.sql              # shared tables (this doc)
  seed.sql                # demo content
```

Keep `/db` outside `public_html` if the plan allows. On Single plans it can
sit inside; it is not web-critical after import.

---

## 3. Shared Database Tables

Every vertical inherits these. Vertical docs add their own tables.

```sql
-- Admin users
CREATE TABLE admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Global site settings (key/value)
CREATE TABLE site_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reusable page blocks (hero text, about, etc.)
CREATE TABLE content_blocks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  block_key VARCHAR(100) UNIQUE NOT NULL,
  title VARCHAR(255),
  body TEXT,
  image VARCHAR(255),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contact / lead submissions
CREATE TABLE leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150),
  email VARCHAR(190),
  phone VARCHAR(40),
  message TEXT,
  source_page VARCHAR(120),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_read TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Testimonials (shared, most verticals use it)
CREATE TABLE testimonials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  author VARCHAR(150),
  role VARCHAR(150),
  quote TEXT,
  rating TINYINT DEFAULT 5,
  photo VARCHAR(255),
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`site_settings` keys used by every site: `site_name`, `tagline`, `phone`,
`whatsapp`, `email`, `address`, `map_embed`, `hours`, `logo`,
`primary_color`, `facebook`, `instagram`, `google_business_url`, `meta_default`.

---

## 4. Admin Panel Pattern

One pattern, reused for every module. Copy a module file, swap the table.

- `auth.php` starts the session, redirects to `index.php` if not logged in.
  Included at the top of every admin page except the login itself.
- Login: `password_verify()` against `admin_users.password_hash`.
- Each module page = list view + add/edit form + delete, all in one file,
  controlled by `?action=` and `?id=`.
- CSRF token in every form, checked on POST.
- Image upload via `upload()` helper: validates mime + size, renames to
  `time()_slug.ext`, stores in `/assets/uploads`.
- Flash messages via session, shown once.

Editable in admin for every site:
- Site settings (contact, hours, social, colors, logo).
- Content blocks (hero, about, CTA copy).
- Testimonials.
- Leads inbox (read, mark read, delete, export CSV).
- Plus the per-vertical modules listed in each doc.

Password hashing: generate the first admin hash with a throwaway script
`password_hash('yourpass', PASSWORD_DEFAULT)`, paste into seed, then delete
the script.

---

## 5. Frontend Design System

Goal: clean, modern, top UI/UX, 100% responsive. One token set, per-vertical
palette swap.

Design tokens in `main.css` `:root`:
```css
:root{
  --c-primary:#0e7c7b;      /* overridden per vertical */
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

Rules:
- Mobile-first. Test 360px, 768px, 1024px, 1440px.
- CSS Grid for layouts, Flexbox for components. No frameworks.
- Fluid type with `clamp()`. No fixed pixel font sizes for headings.
- Every image: `width`, `height`, `loading="lazy"`, `alt`.
- Sticky header, click-to-call phone, floating WhatsApp button.
- One clear primary CTA per page (Book / Quote / Call / Reserve).
- Fonts via Google Fonts CDN, preconnect in head.
- Hit targets min 44px. Visible focus states. Reduced-motion media query.

Reusable components: header/nav, hero, feature grid, service cards,
testimonial slider, FAQ accordion, contact block with map, footer.
Build these once in shared CSS. Verticals restyle, not rebuild.

---

## 6. SEO + Local Listing Fit

These sites are sold via local business listings, so local SEO is the product.

- Per-page `<title>` and `meta description`, editable via admin where it matters.
- LocalBusiness JSON-LD schema injected from `site_settings`
  (name, phone, address, hours, geo, url). Vertical docs specify the
  correct schema `@type` (Dentist, Plumber, Attorney, Restaurant, etc.).
- Dynamic `sitemap.php` and `robots.txt`.
- Clean URLs via `.htaccess` rewrite (`/services/x` not `service.php?id=x`).
- Open Graph + Twitter card tags from settings.
- Google Business Profile link in footer and contact page.
- Fast: inline critical CSS optional, lazy images, no render-blocking JS.

---

## 7. Security Baseline

- All queries via PDO prepared statements. No string-built SQL.
- Escape all output with `e()` (htmlspecialchars).
- CSRF tokens on every POST form.
- Sessions: httponly, samesite=Lax, secure when HTTPS.
- Block direct access to `/includes` via `.htaccess`.
- Upload validation: mime allowlist, size cap, random filename, no execute
  in `/uploads` (`.htaccess` denies php there).
- `config.php` holds creds, never committed to any shared link.
- Disable PHP errors display in production, log instead.

---

## 8. Hostinger Shared Hosting Deployment

Tested against Single / Premium shared (hPanel).

1. hPanel > Databases > MySQL. Create database + user. Note name, user, pass.
   (Hostinger prefixes them, e.g. `u123_clinic`.)
2. hPanel > phpMyAdmin > import `schema.sql`, then `seed.sql`.
3. hPanel > PHP Configuration > set PHP 8.2.
4. Edit `includes/config.php` with the DB name/user/pass and `localhost` host.
5. Upload site files: File Manager or FTP into `public_html`.
   - Use File Manager upload-zip-then-extract, or FTP the folder.
   - (Note: this is the deploy archive, not a code deliverable to you.)
6. Set permissions: folders 755, files 644, `/assets/uploads` 755 writable.
7. hPanel > SSL > install free SSL (Let's Encrypt) for the domain.
8. Force HTTPS via `.htaccess`.
9. Point domain / set document root if needed.
10. Log into `/admin`, change the admin password, fill site settings.
11. Submit sitemap to Google Search Console, verify Google Business Profile.

Cron (optional, Premium+): daily lead-CSV email or DB backup via
hPanel > Cron Jobs calling a guarded PHP script.

`.htaccess` essentials:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# pretty URLs handled per page
# deny includes
RewriteRule ^includes/ - [F,L]
```

---

## 9. Build Sequence (per client)

1. Import shared schema + vertical schema.
2. Wire `config.php`, confirm DB connects.
3. Build shared includes (db, functions, settings, header, footer).
4. Build admin auth + dashboard + shared modules.
5. Build shared frontend components and tokens.
6. Layer vertical pages, modules, palette, schema type.
7. Seed demo content, then replace with real client content via admin.
8. QA: responsive breakpoints, forms, lead capture, SSL, speed, schema.
9. Deploy per section 8.

---

## 10. Productization Notes

- One codebase, four vertical skins. New client = clone + reseed + recolor.
- Keep a `clients/` local folder per client, never edit the master in place.
- Sell tiers: Starter (settings + leads only), Standard (full modules),
  Pro (blog + booking + analytics).
- Recurring revenue: hosting + edits retainer, since admin is yours to support.
