-- ============================================================================
-- Local Business Website System — Seed Data (Healthcare / Wellness demo)
-- PHP 8.2 / MySQL (PDO) — Hostinger shared hosting, no framework
--
-- Import order: schema.sql FIRST, then this file (seed.sql) via phpMyAdmin.
-- Populates: 5 services, 2 doctors, 6 FAQs, 4 testimonials, all site_settings
-- keys (00-SHARED-ARCHITECTURE.md §3 + schema_type), and one admin user.
--
-- Columns referenced here are defined verbatim in schema.sql. All string
-- literals use single quotes; embedded single quotes are escaped by doubling
-- them ('') per SQL standard so this file imports cleanly.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Services (healthcare) — name, slug, short_desc, body, icon, image,
--                          price_from, sort_order, is_active
-- ----------------------------------------------------------------------------
INSERT INTO services (name, slug, short_desc, body, icon, image, price_from, sort_order, is_active) VALUES
('Dental Checkup', 'dental-checkup',
 'Comprehensive exam to keep your smile healthy and catch issues early.',
 'Our routine dental checkup includes a full oral examination, digital X-rays when needed, and a personalised oral-health plan. We screen for cavities, gum disease, and early signs of oral cancer so small problems are caught before they become painful or costly. Every visit ends with practical advice tailored to your daily routine.',
 'tooth', 'service-checkup.jpg', 40.00, 1, 1),

('Teeth Cleaning', 'teeth-cleaning',
 'Professional scaling and polishing for fresh breath and healthy gums.',
 'A professional cleaning removes plaque and tartar that brushing alone cannot reach. We gently scale above and below the gumline, polish away surface stains, and finish with a fluoride treatment. Regular cleanings help prevent gum disease, reduce bad breath, and keep your teeth bright.',
 'sparkle', 'service-cleaning.jpg', 55.00, 2, 1),

('Root Canal Treatment', 'root-canal',
 'Pain-relieving treatment that saves a badly infected or damaged tooth.',
 'When the inside of a tooth becomes infected, a root canal removes the damaged pulp, disinfects the canal, and seals it to stop the pain and save the tooth. Using modern techniques and effective anaesthesia, we make the procedure comfortable. Most patients return to normal activities the same day.',
 'shield', 'service-rootcanal.jpg', 180.00, 3, 1),

('Braces & Orthodontics', 'braces',
 'Straighten your teeth with metal, ceramic, or clear-aligner options.',
 'We offer a full range of orthodontic options to correct crowding, gaps, and bite problems. After a detailed assessment we recommend traditional braces, tooth-coloured ceramic braces, or clear aligners to fit your lifestyle and budget. Treatment plans include regular progress reviews and retainer guidance.',
 'grid', 'service-braces.jpg', 1200.00, 4, 1),

('Teeth Whitening', 'teeth-whitening',
 'Brighten your smile safely with in-clinic professional whitening.',
 'Our supervised whitening treatment lightens stains from coffee, tea, and ageing for a noticeably brighter smile. We protect your gums, apply a professional-grade gel, and can provide custom take-home trays for top-ups. Results are visible after a single session for most patients.',
 'star', 'service-whitening.jpg', 120.00, 5, 1);

-- ----------------------------------------------------------------------------
-- Doctors — name, qualification, specialty, photo, bio, sort_order, is_active
-- ----------------------------------------------------------------------------
INSERT INTO doctors (name, qualification, specialty, photo, bio, sort_order, is_active) VALUES
('Dr. Aisha Rahman', 'BDS, MDS', 'General & Cosmetic Dentistry', 'doctor-aisha.jpg',
 'Dr. Rahman has over 12 years of experience in family and cosmetic dentistry. She is known for her gentle approach and her commitment to pain-free care, and she leads the clinic''s smile-makeover programme. Patients value her clear explanations and patient-first philosophy.',
 1, 1),

('Dr. Daniel Okafor', 'BDS, MSc Orthodontics', 'Orthodontics & Root Canal', 'doctor-daniel.jpg',
 'Dr. Okafor specialises in orthodontics and endodontic (root canal) treatment. He combines modern technology with a calm, reassuring manner to deliver precise, lasting results. He has helped hundreds of patients straighten their smiles with braces and clear aligners.',
 2, 1);

-- ----------------------------------------------------------------------------
-- FAQs — question, answer, sort_order, is_active
-- ----------------------------------------------------------------------------
INSERT INTO faqs (question, answer, sort_order, is_active) VALUES
('How often should I visit the dentist?',
 'For most people we recommend a checkup and professional cleaning every six months. If you have gum disease, braces, or other concerns, your dentist may suggest more frequent visits.',
 1, 1),

('Does a root canal hurt?',
 'Modern root canal treatment is performed under effective local anaesthesia, so the procedure itself is comfortable and similar to having a filling. Most discomfort comes from the infection beforehand, which the treatment relieves.',
 2, 1),

('How long do braces take to work?',
 'Treatment time varies with each case, but most patients wear braces or aligners for 12 to 24 months. We review your progress at every visit and give you a personalised estimate after your first assessment.',
 3, 1),

('Is teeth whitening safe?',
 'Yes. Professional in-clinic whitening is safe when supervised by a dentist. We protect your gums during treatment and use professional-grade products, which is safer and more effective than many over-the-counter kits.',
 4, 1),

('Do you accept walk-in or emergency appointments?',
 'We keep slots available each day for dental emergencies such as severe pain or a broken tooth. Please call us as early as possible and we will do our best to see you the same day.',
 5, 1),

('What payment options are available?',
 'We accept cash and major cards, and we can discuss instalment options for larger treatments such as orthodontics. Ask our front desk for a written estimate before any treatment begins.',
 6, 1);

-- ----------------------------------------------------------------------------
-- Testimonials — author, role, quote, rating, photo, sort_order, is_active
-- ----------------------------------------------------------------------------
INSERT INTO testimonials (author, role, quote, rating, photo, sort_order, is_active) VALUES
('Sara Mensah', 'Patient', 'I''ve always been nervous about dentists, but the team here made me feel completely at ease. My checkup was quick, gentle, and thorough.', 5, 'testimonial-sara.jpg', 1, 1),
('James Whitfield', 'Patient', 'Got my braces here and the whole journey was smooth. Dr. Okafor explained every step and my smile looks fantastic now.', 5, 'testimonial-james.jpg', 2, 1),
('Priya Nair', 'Patient', 'Booked an emergency appointment for a painful tooth and was seen the same day. Professional, caring, and genuinely reassuring.', 5, 'testimonial-priya.jpg', 3, 1),
('Tom Baker', 'Patient', 'The whitening results exceeded my expectations and the clinic is spotless. Friendly staff and zero pressure to upsell.', 4, 'testimonial-tom.jpg', 4, 1);

-- ----------------------------------------------------------------------------
-- Site settings — ALL keys from 00-SHARED-ARCHITECTURE.md §3 plus schema_type.
-- `hours` is stored as a JSON string in the is_open() weekday->intervals format
-- (see design.md). `primary_color` is the healthcare teal #0e7c7b.
-- ----------------------------------------------------------------------------
INSERT INTO site_settings (setting_key, setting_value) VALUES
('site_name',           'BrightSmile Dental Clinic'),
('tagline',             'Gentle, modern dentistry for the whole family'),
('phone',               '+1-555-0142'),
('whatsapp',            '15550142'),
('email',               'hello@brightsmiledental.example'),
('address',             '124 Maple Avenue, Suite 3, Springfield, IL 62704'),
('map_embed',           '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3000!2d-89.65!3d39.78" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>'),
('hours',               '{"mon":[["09:00","13:00"],["14:00","18:00"]],"tue":[["09:00","18:00"]],"wed":[["09:00","18:00"]],"thu":[["09:00","18:00"]],"fri":[["09:00","17:00"]],"sat":[["10:00","14:00"]],"sun":[]}'),
('logo',                'logo.png'),
('primary_color',       '#0e7c7b'),
('facebook',            'https://facebook.com/brightsmiledental'),
('instagram',           'https://instagram.com/brightsmiledental'),
('google_business_url', 'https://g.page/brightsmile-dental'),
('meta_default',        'BrightSmile Dental Clinic offers gentle checkups, cleanings, braces, root canals, and whitening. Book your appointment or call us today.'),
('schema_type',         'Dentist');

-- ----------------------------------------------------------------------------
-- Admin user — single seeded login.
--
-- Plaintext placeholder password (documented per Req 24.2): admin123
-- password_hash below is a pre-generated PASSWORD_DEFAULT (bcrypt $2y$) hash
-- verified against the plaintext 'admin123' using PHP password_verify().
--
-- SECURITY:
--   * Change this password on FIRST login (Req 24.3).
--   * To rotate the hash, run a THROWAWAY one-off script such as:
--         php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT);"
--     paste the result here, and DELETE the script. That generator script
--     MUST NOT be committed to version control (Req 24.4).
-- ----------------------------------------------------------------------------
INSERT INTO admin_users (email, password_hash, name) VALUES
('admin@brightsmiledental.example', '$2y$12$gAYR2xLGI7di0PswPHc8Cuj6bNI63YBblUzn2/SXAj1AbuUbj7qHO', 'Clinic Administrator');
