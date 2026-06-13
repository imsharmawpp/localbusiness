-- ============================================================================
-- Local Business Website System — Database Schema
-- PHP 8.2 / MySQL (PDO) — Hostinger shared hosting, no framework
--
-- Import order: schema.sql first, then seed.sql (via phpMyAdmin).
-- All tables use ENGINE=InnoDB DEFAULT CHARSET=utf8mb4.
--
-- Sections:
--   1. Shared tables   (per 00-SHARED-ARCHITECTURE.md §3)
--   2. Healthcare tables (per 01-healthcare-wellness.md)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Shared tables (00-SHARED-ARCHITECTURE.md §3)
-- ----------------------------------------------------------------------------

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

-- ----------------------------------------------------------------------------
-- 2. Healthcare / Wellness tables (01-healthcare-wellness.md)
--    NOTE: `services` is created before `appointments` because
--    `appointments.service_id` references `services(id)`.
-- ----------------------------------------------------------------------------

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
