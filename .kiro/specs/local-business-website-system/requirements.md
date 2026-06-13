# Requirements Document

## Introduction

This document specifies the requirements for the **Local Business Website System — First Pass**, a reusable PHP 8.2 (PDO + MySQL) website engine designed for deployment on Hostinger shared hosting with no Composer, no framework, and no npm/build step. The first pass delivers two layers:

1. A **Shared Engine** (per `00-SHARED-ARCHITECTURE.md`): configuration, database access, helper functions, settings loader, shared header/footer, admin authentication and dashboard, and shared admin CRUD modules (Site Settings, Content Blocks, Testimonials, Leads inbox).
2. A **Shared Frontend** design system plus the **Healthcare/Wellness Vertical** (per `01-healthcare-wellness.md`): public pages, healthcare admin modules, vertical database tables, teal palette, LocalBusiness JSON-LD schema, dual-write appointment capture, and computed open/closed state.

The system also delivers SEO and security baselines, plus deployment deliverables (schema.sql, seed.sql, README). The conversion goal of the healthcare vertical is to drive the visitor to book an appointment or call, with phone, WhatsApp, and a Book Appointment action reachable from every screen.

This is the **first pass only**. The other verticals (home-services, professional-services, hospitality), blog/Pro-tier features, live booking calendars, and payment processing are explicitly out of scope (see Out of Scope section).

## Glossary

- **System**: The complete Local Business Website System comprising the shared engine, shared frontend, and the healthcare vertical.
- **Shared_Engine**: The reusable backend layer composed of config.php, db.php, functions.php, settings.php, header.php, and footer.php.
- **Config_Loader**: The `includes/config.php` component holding database credentials and site constants.
- **DB_Connector**: The `includes/db.php` component providing a single shared PDO connection instance.
- **Helper_Library**: The `includes/functions.php` component providing helper functions `e()`, `slug()`, `upload()`, `flash()`, `csrf_token()`, and `csrf_verify()`.
- **Settings_Loader**: The `includes/settings.php` component that loads rows from the `site_settings` table into the `$SETTINGS` array.
- **Header_Component**: The `includes/header.php` shared markup component.
- **Footer_Component**: The `includes/footer.php` shared markup component.
- **Auth_Guard**: The `admin/auth.php` component enforcing an authenticated admin session.
- **Admin_Login**: The `admin/index.php` login component.
- **Admin_Dashboard**: The `admin/dashboard.php` component.
- **Admin_Logout**: The `admin/logout.php` component.
- **Settings_Editor**: The shared admin module for editing `site_settings`.
- **Content_Blocks_Module**: The shared admin CRUD module for `content_blocks`.
- **Testimonials_Module**: The shared admin CRUD module for `testimonials`.
- **Leads_Inbox**: The shared admin module for listing, marking read, deleting, and CSV-exporting `leads`.
- **Frontend**: The public-facing website rendered to site visitors.
- **Services_Module**: The healthcare admin CRUD module for `services`.
- **Doctors_Module**: The healthcare admin CRUD module for `doctors`.
- **Appointments_Inbox**: The healthcare admin module for listing appointment requests, changing status, and CSV-exporting `appointments`.
- **FAQ_Module**: The healthcare admin CRUD module for `faqs`.
- **Appointment_Form**: The public form on the Appointment page that captures appointment requests.
- **Contact_Form**: The public form on the Contact page that captures leads.
- **Schema_Generator**: The component that injects LocalBusiness JSON-LD structured data from settings.
- **Sitemap_Generator**: The `sitemap.php` component producing dynamic XML.
- **Open_Closed_Calculator**: The PHP logic that computes open/closed state from the `hours` setting.
- **CSRF_Token**: A per-session anti-cross-site-request-forgery token.
- **Lead**: A row in the `leads` table representing a contact/lead submission.
- **Appointment**: A row in the `appointments` table representing an appointment request.
- **$SETTINGS**: The associative array of site settings keyed by `setting_key`.

## Requirements

### Requirement 1: Configuration and Database Connection

**User Story:** As a site operator, I want centralized configuration and a single database connection, so that the site connects reliably and credentials stay in one place.

#### Acceptance Criteria

1. THE Config_Loader SHALL define the database name, database user, database password, database host, and site constants in `includes/config.php`.
2. THE DB_Connector SHALL provide a single shared PDO connection instance to all components that request a database connection.
3. THE DB_Connector SHALL configure the PDO connection with the utf8mb4 character set and exception-based error reporting.
4. IF the PDO connection cannot be established, THEN THE DB_Connector SHALL log the connection error and SHALL prevent display of the error details to the visitor.
5. THE Config_Loader SHALL be excluded from version control so that credentials are not committed.

### Requirement 2: Helper Library

**User Story:** As a developer, I want a shared set of helper functions, so that output escaping, slugs, uploads, flash messages, and CSRF handling behave consistently across the system.

#### Acceptance Criteria

1. THE Helper_Library SHALL provide a function `e()` that escapes a string for HTML output using htmlspecialchars.
2. THE Helper_Library SHALL provide a function `slug()` that converts a string into a URL-safe slug.
3. WHEN a file is submitted to the `upload()` function, THE Helper_Library SHALL validate the file MIME type against an image allowlist, validate the file size against the configured maximum, rename the file to a `time()_slug.ext` pattern, and store the file in `/assets/uploads`.
4. IF an uploaded file fails MIME-type validation or exceeds the configured size limit, THEN THE Helper_Library SHALL reject the upload and SHALL return an error indication to the caller.
5. WHEN a flash message is set through `flash()`, THE Helper_Library SHALL store the message in the session and SHALL return and clear the message on the next retrieval.
6. THE Helper_Library SHALL provide a function `csrf_token()` that returns the current session CSRF_Token, creating one if none exists.
7. WHEN `csrf_verify()` is called with a submitted token, THE Helper_Library SHALL return a success indication only if the submitted token matches the session CSRF_Token.

### Requirement 3: Settings Loader

**User Story:** As a site operator, I want site settings loaded from the database into a single array, so that contact details, hours, colors, and social links are available to every page.

#### Acceptance Criteria

1. WHEN a page is requested, THE Settings_Loader SHALL load all rows from the `site_settings` table into the `$SETTINGS` array keyed by `setting_key`.
2. THE Settings_Loader SHALL make the following keys available: `site_name`, `tagline`, `phone`, `whatsapp`, `email`, `address`, `map_embed`, `hours`, `logo`, `primary_color`, `facebook`, `instagram`, `google_business_url`, and `meta_default`.
3. IF a requested setting key is absent from the `site_settings` table, THEN THE Settings_Loader SHALL return an empty value for that key.

### Requirement 4: Shared Header and Footer

**User Story:** As a visitor, I want consistent navigation and contact actions on every page, so that I can call, message, or navigate from anywhere on the site.

#### Acceptance Criteria

1. THE Header_Component SHALL render a sticky header containing the site logo, primary navigation, and a click-to-call phone action populated from the `phone` setting.
2. THE Header_Component SHALL render a floating WhatsApp action populated from the `whatsapp` setting.
3. THE Footer_Component SHALL render the address, hours, social links, and a Google Business Profile link populated from the corresponding settings.
4. WHERE the `google_business_url` setting is empty, THE Footer_Component SHALL omit the Google Business Profile link.

### Requirement 5: Admin Authentication

**User Story:** As an admin, I want a secure login that protects every admin page, so that only authenticated users can edit content.

#### Acceptance Criteria

1. WHEN an administrator submits the login form with an email and password, THE Admin_Login SHALL verify the password against the `admin_users.password_hash` value using password_verify.
2. IF the submitted credentials do not match a stored admin user, THEN THE Admin_Login SHALL reject the login attempt and SHALL display a generic authentication-failure message.
3. WHEN an admin session is started, THE Auth_Guard SHALL configure the session cookie with the httponly flag and the samesite=Lax attribute.
4. WHILE the request is served over HTTPS, THE Auth_Guard SHALL set the session cookie secure flag.
5. WHEN an unauthenticated request reaches any admin page other than the Admin_Login, THE Auth_Guard SHALL redirect the request to the Admin_Login.
6. WHEN an administrator triggers the Admin_Logout, THE Admin_Logout SHALL destroy the admin session and SHALL redirect to the Admin_Login.
7. WHEN an authenticated administrator opens the Admin_Dashboard, THE Admin_Dashboard SHALL display navigation links to each available admin module.

### Requirement 6: CSRF Protection and Secure Data Handling

**User Story:** As a site operator, I want all forms and queries protected, so that the site resists cross-site request forgery, SQL injection, and output injection.

#### Acceptance Criteria

1. THE System SHALL include a CSRF_Token field in every POST form.
2. WHEN a POST request is received, THE System SHALL verify the submitted CSRF_Token using `csrf_verify()` before processing the request.
3. IF a POST request arrives with a missing or mismatched CSRF_Token, THEN THE System SHALL reject the request and SHALL prevent the requested data change.
4. THE System SHALL execute all database queries through PDO prepared statements.
5. THE System SHALL escape all dynamic values rendered to HTML using the `e()` function.

### Requirement 7: Site Settings Editor

**User Story:** As an admin, I want to edit site settings, so that I can update contact details, hours, social links, colors, and logo without code changes.

#### Acceptance Criteria

1. WHEN an authenticated administrator opens the Settings_Editor, THE Settings_Editor SHALL display the current value of each defined `site_settings` key.
2. WHEN an administrator submits the Settings_Editor form with a valid CSRF_Token, THE Settings_Editor SHALL persist each submitted value to the `site_settings` table.
3. WHEN a logo image is uploaded through the Settings_Editor, THE Settings_Editor SHALL process the file through the `upload()` function and SHALL store the resulting filename in the `logo` setting.
4. WHEN the Settings_Editor saves successfully, THE Settings_Editor SHALL display a confirmation flash message.

### Requirement 8: Content Blocks Management

**User Story:** As an admin, I want to manage reusable content blocks, so that I can edit hero, about, and CTA copy from the admin panel.

#### Acceptance Criteria

1. WHEN an authenticated administrator opens the Content_Blocks_Module, THE Content_Blocks_Module SHALL list all content blocks with their block key and title.
2. WHEN an administrator submits the content block create or edit form with a valid CSRF_Token, THE Content_Blocks_Module SHALL persist the block key, title, body, and image to the `content_blocks` table.
3. WHEN an administrator deletes a content block with a valid CSRF_Token, THE Content_Blocks_Module SHALL remove the corresponding row from the `content_blocks` table.
4. IF an administrator submits a content block with a block key that already exists on a different row, THEN THE Content_Blocks_Module SHALL reject the submission and SHALL display a duplicate-key error message.

### Requirement 9: Testimonials Management

**User Story:** As an admin, I want to manage testimonials, so that I can display customer trust signals on the site.

#### Acceptance Criteria

1. WHEN an authenticated administrator opens the Testimonials_Module, THE Testimonials_Module SHALL list all testimonials with author, rating, and active state.
2. WHEN an administrator submits the testimonial create or edit form with a valid CSRF_Token, THE Testimonials_Module SHALL persist the author, role, quote, rating, photo, sort order, and active state to the `testimonials` table.
3. WHEN an administrator deletes a testimonial with a valid CSRF_Token, THE Testimonials_Module SHALL remove the corresponding row from the `testimonials` table.
4. WHEN a testimonial photo is uploaded, THE Testimonials_Module SHALL process the file through the `upload()` function and SHALL store the resulting filename in the `photo` column.

### Requirement 10: Leads Inbox

**User Story:** As an admin, I want a leads inbox, so that I can review, manage, and export contact submissions.

#### Acceptance Criteria

1. WHEN an authenticated administrator opens the Leads_Inbox, THE Leads_Inbox SHALL list all leads ordered by creation time with name, contact details, source page, and read state.
2. WHEN an administrator marks a lead as read with a valid CSRF_Token, THE Leads_Inbox SHALL set the `is_read` value of that lead to 1.
3. WHEN an administrator deletes a lead with a valid CSRF_Token, THE Leads_Inbox SHALL remove the corresponding row from the `leads` table.
4. WHEN an administrator requests a CSV export, THE Leads_Inbox SHALL produce a CSV file containing the lead fields with a header row.

### Requirement 11: Shared Frontend Design System

**User Story:** As a visitor, I want a clean, fast, responsive interface, so that the site works well on any device.

#### Acceptance Criteria

1. THE Frontend SHALL define design tokens in `main.css` `:root` matching the values in `00-SHARED-ARCHITECTURE.md` §5, including `--c-primary`, `--c-accent`, `--c-ink`, `--c-muted`, `--c-bg`, `--c-surface`, `--radius`, `--shadow`, `--maxw`, `--space`, `--font`, and `--font-head`.
2. THE Frontend SHALL apply a mobile-first base stylesheet that renders correctly at the 360px, 768px, 1024px, and 1440px viewport widths.
3. THE Frontend SHALL provide reusable components for header/navigation, hero, feature grid, service cards, testimonial slider, FAQ accordion, contact block with map embed, and footer.
4. THE Frontend SHALL render every content image with `width`, `height`, `loading="lazy"`, and `alt` attributes.
5. THE Frontend SHALL provide interactive controls with a minimum hit-target size of 44 pixels and visible focus states.
6. WHILE the visitor's browser requests reduced motion, THE Frontend SHALL suppress non-essential animations.
7. THE Frontend SHALL load fonts through the Google Fonts CDN with a preconnect directive in the document head.

### Requirement 12: Healthcare Public Pages

**User Story:** As a prospective patient, I want informative pages about the clinic, services, and doctors, so that I can decide to book or call.

#### Acceptance Criteria

1. THE Frontend SHALL render a Home page containing a hero with a primary Book Appointment call to action, a services grid, a doctor introduction, testimonials, an FAQ section, and location with hours.
2. THE Frontend SHALL render an About page, a Doctors/Team page, a Gallery page, and a Contact page.
3. THE Frontend SHALL render a Services list page that displays all active services ordered by sort order.
4. WHEN a visitor opens a service detail page by slug, THE Frontend SHALL display that service's name, body, image, and price-from value when present.
5. IF a visitor requests a service detail page for a slug that does not match an active service, THEN THE Frontend SHALL return a not-found response.
6. THE Frontend SHALL make the Book Appointment action and the click-to-call and WhatsApp actions reachable from every healthcare page.

### Requirement 13: Appointment Capture with Dual Write

**User Story:** As a prospective patient, I want to request an appointment online, so that the clinic can contact me to confirm.

#### Acceptance Criteria

1. THE Appointment_Form SHALL collect patient name, phone, email, service, preferred date, preferred slot, and notes.
2. THE Appointment_Form SHALL present the service selection and the preferred time slot as dropdown controls.
3. WHEN a visitor submits the Appointment_Form with a valid CSRF_Token and the required fields populated, THE System SHALL insert a row into the `appointments` table and SHALL insert a corresponding row into the `leads` table.
4. IF the Appointment_Form is submitted with a missing required field, THEN THE System SHALL reject the submission and SHALL display a validation error identifying the missing field.
5. WHEN an Appointment is created, THE System SHALL set the appointment status to `new`.
6. WHEN the Appointment_Form is submitted successfully, THE System SHALL display a confirmation message to the visitor.

### Requirement 14: Contact Form Lead Capture

**User Story:** As a visitor, I want to send a message through the contact page, so that the clinic receives my enquiry.

#### Acceptance Criteria

1. WHEN a visitor submits the Contact_Form with a valid CSRF_Token and the required fields populated, THE System SHALL insert a row into the `leads` table with the source page recorded.
2. IF the Contact_Form is submitted with a missing required field, THEN THE System SHALL reject the submission and SHALL display a validation error identifying the missing field.
3. WHEN the Contact_Form is submitted successfully, THE System SHALL display a confirmation message to the visitor.

### Requirement 15: Open/Closed State Computation

**User Story:** As a visitor, I want to see whether the clinic is currently open, so that I know when to call or visit.

#### Acceptance Criteria

1. THE Open_Closed_Calculator SHALL compute the current open or closed state in PHP from the `hours` setting.
2. WHILE the current day and time fall within the configured opening hours, THE Open_Closed_Calculator SHALL report the state as open.
3. WHILE the current day and time fall outside the configured opening hours, THE Open_Closed_Calculator SHALL report the state as closed.
4. THE Frontend SHALL display the computed open or closed state alongside the clinic hours.

### Requirement 16: Healthcare Services Management

**User Story:** As an admin, I want to manage clinic services, so that the public services pages stay current.

#### Acceptance Criteria

1. WHEN an authenticated administrator opens the Services_Module, THE Services_Module SHALL list all services with name, slug, sort order, and active state.
2. WHEN an administrator submits the service create or edit form with a valid CSRF_Token, THE Services_Module SHALL persist name, slug, short description, body, icon, image, price-from, sort order, and active state to the `services` table.
3. WHEN an administrator deletes a service with a valid CSRF_Token, THE Services_Module SHALL remove the corresponding row from the `services` table.
4. IF an administrator submits a service with a slug that already exists on a different row, THEN THE Services_Module SHALL reject the submission and SHALL display a duplicate-slug error message.

### Requirement 17: Doctors Management

**User Story:** As an admin, I want to manage doctor profiles, so that the Doctors/Team page reflects current staff.

#### Acceptance Criteria

1. WHEN an authenticated administrator opens the Doctors_Module, THE Doctors_Module SHALL list all doctors with name, specialty, sort order, and active state.
2. WHEN an administrator submits the doctor create or edit form with a valid CSRF_Token, THE Doctors_Module SHALL persist name, qualification, specialty, photo, bio, sort order, and active state to the `doctors` table.
3. WHEN an administrator deletes a doctor with a valid CSRF_Token, THE Doctors_Module SHALL remove the corresponding row from the `doctors` table.
4. WHEN a doctor photo is uploaded, THE Doctors_Module SHALL process the file through the `upload()` function and SHALL store the resulting filename in the `photo` column.

### Requirement 18: Appointment Requests Inbox

**User Story:** As an admin, I want an appointment requests inbox, so that I can track and export incoming bookings.

#### Acceptance Criteria

1. WHEN an authenticated administrator opens the Appointments_Inbox, THE Appointments_Inbox SHALL list all appointments ordered by creation time with patient name, phone, service, preferred date, preferred slot, and status.
2. WHEN an administrator changes an appointment status with a valid CSRF_Token, THE Appointments_Inbox SHALL update the `status` value to one of `new`, `confirmed`, `done`, or `cancelled`.
3. WHEN an administrator requests a CSV export, THE Appointments_Inbox SHALL produce a CSV file containing the appointment fields with a header row.

### Requirement 19: FAQ Management

**User Story:** As an admin, I want to manage FAQs, so that common patient questions are answered on the site.

#### Acceptance Criteria

1. WHEN an authenticated administrator opens the FAQ_Module, THE FAQ_Module SHALL list all FAQs with question, sort order, and active state.
2. WHEN an administrator submits the FAQ create or edit form with a valid CSRF_Token, THE FAQ_Module SHALL persist question, answer, sort order, and active state to the `faqs` table.
3. WHEN an administrator deletes an FAQ with a valid CSRF_Token, THE FAQ_Module SHALL remove the corresponding row from the `faqs` table.
4. THE Frontend SHALL render active FAQs in an accordion component ordered by sort order.

### Requirement 20: SEO Metadata and Structured Data

**User Story:** As a site operator, I want strong on-page SEO and local structured data, so that the site ranks in local search and listings.

#### Acceptance Criteria

1. THE Frontend SHALL render a unique `<title>` and meta description for each public page.
2. THE Schema_Generator SHALL inject LocalBusiness JSON-LD structured data built from `site_settings`, including name, phone, address, hours, and URL.
3. THE Schema_Generator SHALL set the JSON-LD `@type` from the schema-type setting, defaulting to `Dentist` when the setting is absent.
4. THE Frontend SHALL render Open Graph and Twitter card meta tags populated from settings.
5. THE Frontend SHALL render a Google Business Profile link on the Contact page populated from the `google_business_url` setting.

### Requirement 21: Sitemap, Robots, and Clean URLs

**User Story:** As a site operator, I want a sitemap, robots file, and clean URLs, so that search engines crawl the site effectively.

#### Acceptance Criteria

1. WHEN the Sitemap_Generator is requested, THE Sitemap_Generator SHALL produce a dynamic XML sitemap that includes the public pages and active service detail URLs.
2. THE System SHALL serve a `robots.txt` file that references the sitemap location.
3. THE System SHALL provide clean URLs through `.htaccess` rewrite rules for service detail pages.
4. WHERE URL rewriting is unavailable, THE System SHALL serve the equivalent page through a query-string fallback.

### Requirement 22: Upload and Includes Hardening

**User Story:** As a site operator, I want server-side hardening, so that uploaded files and internal includes cannot be abused.

#### Acceptance Criteria

1. THE System SHALL deny direct web access to the `/includes` directory through `.htaccess`.
2. THE System SHALL deny execution of PHP files within the `/uploads` directory through `.htaccess`.
3. THE System SHALL accept only files matching the image MIME allowlist for upload.
4. WHILE running in the production environment, THE System SHALL log PHP errors and SHALL prevent display of PHP errors to visitors.

### Requirement 23: Database Schema and Seed Deliverables

**User Story:** As a deployer, I want ready-to-import schema and seed files, so that I can stand up a working demo site quickly.

#### Acceptance Criteria

1. THE System SHALL provide a `schema.sql` file defining the shared tables `admin_users`, `site_settings`, `content_blocks`, `leads`, and `testimonials` exactly as specified in `00-SHARED-ARCHITECTURE.md`.
2. THE System SHALL provide in `schema.sql` the healthcare tables `services`, `doctors`, `appointments`, and `faqs` exactly as specified in `01-healthcare-wellness.md`.
3. THE System SHALL provide a `seed.sql` file containing 5 services, 2 doctors, 6 FAQs, and 4 testimonials.
4. THE System SHALL provide in `seed.sql` values for all `site_settings` keys listed in `00-SHARED-ARCHITECTURE.md` §3.
5. THE System SHALL provide in `seed.sql` one seeded admin user whose `password_hash` is a pre-generated PASSWORD_DEFAULT hash.

### Requirement 24: Deployment Documentation

**User Story:** As a deployer, I want a README with Hostinger deploy steps, so that I can deploy the site on shared hosting without guesswork.

#### Acceptance Criteria

1. THE System SHALL provide a README documenting the Hostinger deployment steps that match `00-SHARED-ARCHITECTURE.md` §8.
2. THE README SHALL document the placeholder admin password corresponding to the seeded admin password hash.
3. THE README SHALL instruct the deployer to change the admin password on first login.
4. THE README SHALL document the procedure for generating a new password hash with a throwaway script that is not committed to version control.

## Assumptions

1. **Seeded admin credentials**: `seed.sql` includes one admin user whose `password_hash` is a pre-generated `PASSWORD_DEFAULT` hash. The corresponding plaintext placeholder password is documented in the README, the deployer is instructed to change it on first login, and the throwaway hash-generation script is not committed to the repository.
2. **Appointment dual-write**: Each appointment submission writes one row to `appointments` and one corresponding row to `leads`, so that all enquiries appear in the Leads inbox.
3. **Dropdown slot picker**: The appointment preferred-time slot uses simple dropdown controls. No live calendar or real-time availability is provided in this pass.
4. **Clean URLs with query fallback**: Pretty URLs are provided via `.htaccess` rewrites, with an equivalent query-string fallback when rewriting is unavailable.
5. **/db location**: The `/db` directory containing `schema.sql` and `seed.sql` sits inside the deploy tree, since shared hosting plans may not allow placing it outside `public_html` and it is not web-critical after import.
6. **Open/closed computed in PHP**: The open or closed state is computed in PHP from the `hours` setting on each request, not stored or scheduled.
7. **Schema @type default**: The LocalBusiness JSON-LD `@type` defaults to `Dentist` and is overridable through a site setting (e.g. to `MedicalClinic` or `Physician`).

## Out of Scope

The following are explicitly **not** part of this first pass and SHALL NOT be implemented:

1. **Other verticals**: `02-home-services`, `03-professional-services`, and `04-hospitality`. Only the shared engine, shared frontend, and the healthcare/wellness vertical are in scope.
2. **Blog and Pro-tier features**: The optional blog and any Pro-tier features (booking automation, analytics) noted in the docs.
3. **Live booking calendar**: Real-time availability or calendar-based booking. The appointment picker is dropdown-based only.
4. **Payment processing**: Any online payment, deposit, or billing capability.

No features beyond those described in `00-SHARED-ARCHITECTURE.md` and `01-healthcare-wellness.md` will be invented or added.
