# 04 — Hospitality (Restaurants, Cafes)

Builds on `00-SHARED-ARCHITECTURE.md`. Only the deltas are here.

Target client: restaurants, cafes, bakeries, bars, cloud kitchens, small
hotels. Buyer cares about menu, reservations, and looking appetising.

Schema `@type`: `Restaurant`, `CafeOrCoffeeShop`, or `FoodEstablishment`.

---

## Conversion Goal

Reservation, call, or order link. Drive footfall. The site must look
delicious and load fast on mobile, since most traffic is phone-based.

---

## Pages

- Home: full-bleed hero image, tagline, Reserve a Table + View Menu,
  featured dishes, ambience, hours + location, reviews, Instagram strip.
- Menu: categorised (Starters, Mains, Drinks, Desserts), items with price,
  veg/spicy/chef-special tags, optional photos.
- Gallery: food and interior photos.
- About: story, chef, sourcing, ambience.
- Reservations: form (date, time, party size, contact, notes).
- Contact: map, hours, parking, delivery-platform links (Zomato/Swiggy/etc.).
- Events / Offers (optional): specials, happy hours, private dining.

---

## Editable Modules (admin)

Shared modules plus:

- Menu categories (CRUD): name, slug, sort, active.
- Menu items (CRUD): category, name, desc, price, tags (veg/spicy/special),
  photo, sort, active.
- Reservations (inbox): view, status, export CSV.
- Gallery (CRUD): image, caption, sort.
- Offers / events (CRUD, optional): title, body, image, date range.
- Settings: opening hours per day, delivery links, reservation on/off toggle.

---

## Vertical Tables

```sql
CREATE TABLE menu_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(170) UNIQUE NOT NULL,
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(180) NOT NULL,
  description VARCHAR(400),
  price DECIMAL(10,2),
  is_veg TINYINT(1) DEFAULT 0,
  is_spicy TINYINT(1) DEFAULT 0,
  is_special TINYINT(1) DEFAULT 0,
  photo VARCHAR(255),
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  email VARCHAR(190),
  res_date DATE NOT NULL,
  res_time VARCHAR(20) NOT NULL,
  party_size INT DEFAULT 2,
  notes TEXT,
  status ENUM('new','confirmed','seated','cancelled') DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE gallery (
  id INT AUTO_INCREMENT PRIMARY KEY,
  image VARCHAR(255) NOT NULL,
  caption VARCHAR(200),
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE offers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  body TEXT,
  image VARCHAR(255),
  starts_on DATE,
  ends_on DATE,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Palette + Feel

- Warm and appetising: deep `#6b1f1f` wine, `#1c1c1c` charcoal + gold,
  or earthy `#7a5230`. Palette follows the cuisine and brand.
- Big imagery, generous, photographic. Let food carry the design.
- Elegant headings (serif or display), clean body. Not cluttered.
- Show open/closed status, hours, and a map prominently.

---

## UX Notes

- Hero is a single strong food/ambience image, fast-loaded and optimised.
- Reserve a Table is the primary CTA. Menu is the most-visited page, make
  it easy to scan and fast (no PDF menus).
- Menu items render from DB grouped by category with veg/spicy/special tags.
- Delivery-platform buttons (Zomato, Swiggy, etc.) from settings.
- Floating WhatsApp/Call for quick bookings.
- Reservation form writes to `reservations` and a `leads` row.
- Compress all food photos hard. Mobile speed is the whole game here.

---

## Seed (demo)

4 menu categories, 12 menu items, 6 gallery images, 4 testimonials,
1 offer. Replace via admin per client.
