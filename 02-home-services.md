# 02 — Home Services (Plumbers, Contractors)

Builds on `00-SHARED-ARCHITECTURE.md`. Only the deltas are here.

Target client: plumbers, electricians, HVAC, builders, painters, pest control,
cleaning, handyman. Buyer cares about fast quotes and call-now.

Schema `@type`: `Plumber`, `Electrician`, `HomeAndConstructionBusiness`,
or `GeneralContractor`.

---

## Conversion Goal

Phone call or quote request, fast. These visitors often have an urgent problem.
Click-to-call must be the loudest element on mobile.

---

## Pages

- Home: hero with Call Now + Get Free Quote, services grid, service areas,
  why-choose (licensed, insured, fast), recent jobs, reviews, CTA band.
- Services: list + single service detail (e.g. Leak Repair, Rewiring).
- Service Areas: list of neighbourhoods/cities served (local SEO gold).
- Projects / Work: gallery of completed jobs with before/after.
- Quote: form (service, address area, urgency, photos, details).
- About: license, insurance, team, years in business.
- Contact: phone, WhatsApp, hours, emergency availability.

---

## Editable Modules (admin)

Shared modules plus:

- Services (CRUD): name, slug, short desc, body, icon, image, sort, active.
- Service areas (CRUD): area name, slug, blurb. Used for area landing pages.
- Projects (CRUD): title, service, before image, after image, location, body.
- Quote requests (inbox): view, status, export CSV.
- Trust badges in settings: licensed no., insured, emergency 24/7 toggle.

---

## Vertical Tables

```sql
CREATE TABLE services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  slug VARCHAR(190) UNIQUE NOT NULL,
  short_desc VARCHAR(300),
  body TEXT,
  icon VARCHAR(120),
  image VARCHAR(255),
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE service_areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(190) UNIQUE NOT NULL,
  blurb TEXT,
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  service_id INT NULL,
  location VARCHAR(150),
  before_image VARCHAR(255),
  after_image VARCHAR(255),
  body TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE quotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  email VARCHAR(190),
  service_id INT NULL,
  area VARCHAR(150),
  urgency ENUM('emergency','this_week','flexible') DEFAULT 'flexible',
  details TEXT,
  photo VARCHAR(255),
  status ENUM('new','quoted','won','lost') DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Palette + Feel

- Primary strong and industrial: `#1f4e79` deep blue or `#e8590c` safety orange.
- Accent the other of the pair. Bold, confident, high contrast.
- Imagery: real vans, uniforms, tools, finished work. Avoid stock cliches.
- Big trust strip: Licensed, Insured, Years, Jobs done, Rating.

---

## UX Notes

- Sticky Call Now bar on mobile, tap-to-dial. Phone in header always.
- Get Free Quote secondary CTA everywhere. Quote form allows a photo upload.
- Emergency 24/7 banner when toggled on in settings.
- Service-area pages double as local SEO landing pages (one per area).
- Quote form writes to `quotes` and a `leads` row.

---

## Seed (demo)

6 services, 5 service areas, 4 projects with before/after, 4 testimonials.
Replace via admin per client.
