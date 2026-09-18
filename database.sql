-- Run this once in phpMyAdmin (XAMPP) to create the database.
-- Multi-tenant booking platform: one shared app + database serving many client
-- business sites, each identified by a URL slug (e.g. /site/roi-avraham/).
CREATE DATABASE IF NOT EXISTS booking_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE booking_platform;

CREATE TABLE IF NOT EXISTS tenants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) UNIQUE NOT NULL,
  name VARCHAR(150) NOT NULL,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NULL,                         -- NULL for super_admin, required for admin
  role ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS settings (
  tenant_id INT NOT NULL,
  setting_key VARCHAR(100) NOT NULL,
  setting_value LONGTEXT,
  PRIMARY KEY (tenant_id, setting_key),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  duration_minutes INT NOT NULL DEFAULT 30,
  price VARCHAR(50) DEFAULT '',
  sort_order INT DEFAULT 0,
  active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS gallery_media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  type ENUM('image','video') NOT NULL,
  filename VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  service_id INT NOT NULL,
  customer_name VARCHAR(150) NOT NULL,
  customer_phone VARCHAR(30) NOT NULL,
  customer_email VARCHAR(150) NULL,
  booking_date DATE NOT NULL,
  booking_time TIME NOT NULL,
  status ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS push_subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  endpoint TEXT NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  p256dh VARCHAR(255) NOT NULL,
  auth VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY endpoint_hash_unique (endpoint_hash),
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

-- Seed the first tenant: the mortgage consultant site built earlier.
INSERT INTO tenants (id, slug, name, status) VALUES
(1, 'roi-avraham', 'ייעוץ משכנתאות — רועי אברהם', 'active');

INSERT INTO services (tenant_id, name, duration_minutes, price, sort_order, active) VALUES
(1, 'פגישת ייעוץ ראשונה', 45, 'ללא עלות', 1, 1),
(1, 'בדיקת זכאות ותמהיל', 30, '', 2, 1),
(1, 'ליווי מו״מ מול הבנק', 60, '', 3, 1),
(1, 'ייעוץ למיחזור משכנתא', 45, '', 4, 1),
(1, 'ייעוץ למשקיעים', 45, '', 5, 1);

INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES
(1, 'owner_name', 'רועי אברהם'),
(1, 'tagline', 'יועץ משכנתאות עצמאי'),
(1, 'about_text', 'אני מלווה משפחות וזוגות צעירים בתהליך המשכנתא כבר למעלה מעשור — מהבדיקה הכלכלית הראשונית, דרך בניית תמהיל נכון, ועד למשא ומתן מול הבנקים לתנאים הכי טובים שיש. אני לא עובד עבור אף בנק — רק עבורכם.'),
(1, 'accent_color', '#cda15c'),
(1, 'phone', ''),
(1, 'whatsapp_phone', ''),
(1, 'email', ''),
(1, 'address', ''),
(1, 'instagram_url', ''),
(1, 'facebook_url', ''),
(1, 'tiktok_url', ''),
(1, 'slot_interval_minutes', '30'),
(1, 'working_hours', '{"0":{"closed":false,"open":"10:00","close":"20:00"},"1":{"closed":false,"open":"10:00","close":"20:00"},"2":{"closed":false,"open":"10:00","close":"20:00"},"3":{"closed":false,"open":"10:00","close":"20:00"},"4":{"closed":false,"open":"10:00","close":"20:00"},"5":{"closed":false,"open":"09:00","close":"15:00"},"6":{"closed":true,"open":"","close":""}}'),
(1, 'legal_privacy_text', 'טיוטת מדיניות פרטיות — יש לערוך בפאנל הניהול.'),
(1, 'legal_terms_text', 'טיוטת תקנון האתר — יש לערוך בפאנל הניהול.'),
(1, 'admin_notification_email', ''),
(1, 'mail_enabled', '0'),
(1, 'smtp_host', ''),
(1, 'smtp_port', '587'),
(1, 'smtp_username', ''),
(1, 'smtp_password', ''),
(1, 'smtp_secure', 'tls'),
(1, 'smtp_from_email', ''),
(1, 'smtp_from_name', '');
