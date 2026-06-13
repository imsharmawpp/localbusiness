# 01 — Healthcare & Wellness (Clinics, Dentists)

Builds on `00-SHARED-ARCHITECTURE.md`. Only the deltas are here.

Target client: dental clinics, GP/specialist clinics, physio, derma, eye care,
small wellness/spa centres. Buyer cares about appointments and trust.

Schema `@type`: `Dentist`, `MedicalClinic`, or `Physician`.

---

## Conversion Goal

Get the visitor to book or call. Phone, WhatsApp, and a Book Appointment form
must be reachable from every screen.

---

## Pages

- Home: hero with primary CTA (Book Appointment), services grid, why-choose-us,
  doctor intro, testimonials, FAQ, location + hours.
- About: clinic story, accreditations, facility photos.
- Services: list page + single service detail (e.g. Root Canal, Braces).
- Doctors / Team: profiles with photo, qualification, specialty.
- Appointment: form with preferred date, time slot, service, notes.
- Gallery: before/after or facility (consent-respecting).
- Blog (optional, Pro tier): health tips for local SEO.
- Contact: map, hours, multiple branches if any.

---

## Editable Modules (admin)

Shared modules plus:

- Services (CRUD): name, slug, short desc, body, icon/image, price-from
  (optional), sort, active.
- Doctors (CRUD): name, qualification, specialty, photo, bio, sort, active.
- Appointment requests (inbox): view, mark status, export CSV.
- Clinic hours (per day) and emergency line in settings.
- FAQ (CRUD): question, answer, sort.

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
  price_from DECIMAL(10,2) NULL,
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE doctors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  qualification VARCHAR(190),
  specialty VARCHAR(150),
  photo VARCHAR(255),
  bio TEXT,
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  email VARCHAR(190),
  service_id INT NULL,
  preferred_date DATE,
  preferred_slot VARCHAR(40),
  notes TEXT,
  status ENUM('new','confirmed','done','cancelled') DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE faqs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question VARCHAR(300) NOT NULL,
  answer TEXT NOT NULL,
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Palette + Feel

- Primary `#0e7c7b` (clean teal) or `#1d6fb8` (medical blue). Accent soft.
- High whitespace, calm, trustworthy. Rounded cards, soft shadows.
- Imagery: real clinic photos, smiling staff, clean rooms.
- Trust signals near CTA: years, patients treated, certifications.

---

## UX Notes

- Book Appointment is the single dominant CTA. Sticky button on mobile.
- Slot picker can be simple dropdowns (no live calendar needed for v1).
- Show hours and open/closed state computed from settings.
- Emergency phone prominent if relevant.
- Appointment form writes to `appointments` and also drops a `leads` row.

---

## Seed (demo)

5 services (Dental Checkup, Cleaning, Root Canal, Braces, Whitening),
2 doctors, 6 FAQs, 4 testimonials. Replace via admin per client.
