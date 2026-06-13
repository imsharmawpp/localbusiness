# 03 — Professional Services (Lawyers, Accountants)

Builds on `00-SHARED-ARCHITECTURE.md`. Only the deltas are here.

Target client: law firms, solo attorneys, CA/accounting firms, tax advisors,
consultants, financial planners. Buyer cares about credibility and a
qualified consultation enquiry.

Schema `@type`: `Attorney`, `LegalService`, `AccountingService`, or
`ProfessionalService`.

---

## Conversion Goal

Book a consultation. Fewer, higher-intent leads. Tone is authority and trust,
not urgency. Lead qualification matters more than volume.

---

## Pages

- Home: hero with Book a Consultation, practice areas grid, why-choose
  (experience, results, discretion), partner intro, testimonials, CTA.
- Practice Areas / Services: list + single detail (e.g. Tax Filing,
  Corporate Law, Estate Planning).
- About / Firm: history, values, credentials, memberships, bar/ICAI numbers.
- Team / Attorneys: profiles with photo, title, qualifications, focus areas.
- Case Results / Insights (optional): results or articles for authority + SEO.
- Consultation: form (service, preferred contact, brief matter description).
- Contact: office address, map, hours, by-appointment note.

---

## Editable Modules (admin)

Shared modules plus:

- Practice areas (CRUD): name, slug, short desc, body, icon, sort, active.
- Team (CRUD): name, title, qualification, photo, bio, focus areas, sort.
- Insights / articles (CRUD, optional Pro): title, slug, body, image, date.
- Consultation requests (inbox): view, status, export CSV.
- Credentials in settings: bar/registration numbers, memberships, awards.

---

## Vertical Tables

```sql
CREATE TABLE practice_areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  slug VARCHAR(190) UNIQUE NOT NULL,
  short_desc VARCHAR(300),
  body TEXT,
  icon VARCHAR(120),
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE team_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  title VARCHAR(150),
  qualification VARCHAR(200),
  focus_areas VARCHAR(255),
  photo VARCHAR(255),
  bio TEXT,
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE insights (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(220) NOT NULL,
  slug VARCHAR(230) UNIQUE NOT NULL,
  excerpt VARCHAR(300),
  body TEXT,
  image VARCHAR(255),
  published_at DATE,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE consultations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40),
  email VARCHAR(190) NOT NULL,
  practice_area_id INT NULL,
  preferred_contact ENUM('call','email','whatsapp') DEFAULT 'call',
  matter TEXT,
  status ENUM('new','scheduled','closed') DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (practice_area_id) REFERENCES practice_areas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Palette + Feel

- Primary deep and serious: `#13294b` navy, `#1a3c34` forest, or `#5a2a27`
  oxblood. Gold/brass accent for premium feel.
- Serif or high-contrast sans for headings. Restrained, editorial layout.
- Generous whitespace, strong typographic hierarchy, minimal decoration.
- Imagery: office, handshake-free real portraits, books, clean desk.

---

## UX Notes

- One clear consultation CTA. No loud popups, no urgency banners.
- Practice-area detail pages are the SEO workhorse, one per service.
- Confidentiality note near the form builds trust.
- Consultation form writes to `consultations` and a `leads` row.
- Keep claims compliant: avoid guaranteeing outcomes in default copy.

---

## Seed (demo)

5 practice areas, 2 team members, 3 insights (optional), 4 testimonials.
Replace via admin per client.
