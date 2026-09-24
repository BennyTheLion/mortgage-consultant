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

-- Seed a demo tenant with every section filled in, so the platform root
-- (index.php redirects to /site/demo/) always has a full showcase site to show,
-- without needing a real client's slug. Admin login: demo / demo1234.
INSERT INTO tenants (id, slug, name, status) VALUES
(2, 'demo', 'ייעוץ משכנתאות — אתר הדגמה', 'active');

INSERT INTO admins (tenant_id, role, username, password_hash) VALUES
(2, 'admin', 'demo', '$2y$10$W9H93iqI2OPg952pxgOi3exXlh0aH7HIRYZPvtdynSJWxWljswnXa');

INSERT INTO services (tenant_id, name, duration_minutes, price, sort_order, active) VALUES
(2, 'פגישת ייעוץ ראשונה', 45, 'ללא עלות', 1, 1),
(2, 'בדיקת זכאות ובניית תמהיל', 30, '', 2, 1),
(2, 'ליווי מו״מ מול הבנק', 60, '', 3, 1),
(2, 'ייעוץ למיחזור משכנתא', 45, '', 4, 1),
(2, 'ייעוץ למשקיעים ונכס שני', 45, '', 5, 1);

INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES
(2, 'owner_name', 'דנה כהן'),
(2, 'tagline', 'יועצת משכנתאות מוסמכת | ליווי אישי מהבדיקה הראשונה ועד קבלת המפתח'),
(2, 'about_text', 'אני דנה כהן, יועצת משכנתאות עצמאית עם ניסיון של למעלה מעשור בליווי משפחות, זוגות צעירים ומשקיעים בתהליך המשכנתא.\n\nאני לא עובדת עבור אף בנק — התפקיד שלי הוא לייצג רק אתכם: לבנות את תמהיל המשכנתא הנכון, להשוות בין הצעות ולנהל מו״מ על הריבית והתנאים, כדי שתקבלו את העסקה הכי טובה שיש.'),
(2, 'accent_color', '#2f6fed'),
(2, 'phone', '050-1234567'),
(2, 'whatsapp_phone', '972501234567'),
(2, 'email', 'demo@example.com'),
(2, 'address', 'רוטשילד 1, תל אביב'),
(2, 'instagram_url', ''),
(2, 'facebook_url', ''),
(2, 'tiktok_url', ''),
(2, 'slot_interval_minutes', '30'),
(2, 'working_hours', '{"0":{"closed":false,"open":"09:00","close":"19:00"},"1":{"closed":false,"open":"09:00","close":"19:00"},"2":{"closed":false,"open":"09:00","close":"19:00"},"3":{"closed":false,"open":"09:00","close":"19:00"},"4":{"closed":false,"open":"09:00","close":"14:00"},"5":{"closed":true,"open":"","close":""},"6":{"closed":true,"open":"","close":""}}'),
(2, 'legal_privacy_text', 'זהו אתר הדגמה של הפלטפורמה. טקסט זה הוא טיוטת מדיניות פרטיות לדוגמה בלבד.'),
(2, 'legal_terms_text', 'זהו אתר הדגמה של הפלטפורמה. טקסט זה הוא טיוטת תקנון לדוגמה בלבד.'),
(2, 'admin_notification_email', ''),
(2, 'mail_enabled', '0'),
(2, 'smtp_host', ''),
(2, 'smtp_port', '587'),
(2, 'smtp_username', ''),
(2, 'smtp_password', ''),
(2, 'smtp_secure', 'tls'),
(2, 'smtp_from_email', ''),
(2, 'smtp_from_name', 'ייעוץ משכנתאות — אתר הדגמה'),
(2, 'site_content', '{"hero_badge":"אתר הדגמה — כך נראה אתר לקוח מלא","hero_lead":"ליווי אישי, שקוף ומקצועי לאורך כל התהליך — מהפגישה הראשונה ועד המפתח בבית החדש.","services_heading":"ליווי מקצועי בכל שלב בדרך לבית","services_desc":"מתכנון ראשוני ועד חתימה בבנק — הבדיקה, ההשוואה והמשא ומתן מתבצעים בשבילכם.","about_badge_title":"דנה כהן","about_badge_sub":"יועצת משכנתאות מוסמכת","cta_title":"מוכנים לקבוע פגישה?","cta_text":"בלי הרשמה, בלי המתנה בטלפון — בוחרים שירות וזמן פנוי ומקבלים אישור מיידי.","footer_blurb":"ליווי אישי ומקצועי בתהליך המשכנתא, משלב הבדיקה הראשונית ועד החתימה בבנק.","show_calculator":true,"stats":[{"num":"+500","label":"משפחות ליוויתי"},{"num":"12","label":"שנות ניסיון"},{"num":"4.9","label":"דירוג ממוצע"}],"service_cards":[{"title":"משכנתא לדירה ראשונה","desc":"בדיקת זכאות, בניית תמהיל ומו״מ מול הבנקים מההתחלה ועד החתימה.","image":"https:\\/\\/images.unsplash.com\\/photo-1560518883-ce09059eeffa?w=500&q=70"},{"title":"מיחזור משכנתא","desc":"בדיקה האם משתלם למחזר כיום, וכמה בדיוק אפשר לחסוך לאורך זמן.","image":"https:\\/\\/images.unsplash.com\\/photo-1554224155-6726b3ff858f?w=500&q=70"},{"title":"ליווי מול הבנק","desc":"ליווי בכל שיחה ומסמך, כדי שלא תישארו לבד מול הבנק.","image":"https:\\/\\/images.unsplash.com\\/photo-1582407947304-fd86f028f716?w=500&q=70"},{"title":"משקיעים ונכס שני","desc":"תכנון מימון לרכישת נכס נוסף, כולל השפעת המשכנתא הקיימת.","image":"https:\\/\\/images.unsplash.com\\/photo-1560520653-9e0e4c89eb11?w=500&q=70"}],"credentials":["בעלת רישיון יועץ משכנתאות","חברה בלשכת יועצי המשכנתאות","+500 עסקאות"],"process":[{"title":"פגישת ייעוץ ראשונית","desc":"מכירים, ממפים את המצב הכלכלי ואת המטרה — פרונטלית, בזום או בטלפון."},{"title":"בדיקת זכאות ותמהיל","desc":"בונים את תמהיל המשכנתא המתאים ומגישים לבנקים לקבלת הצעות."},{"title":"מו״מ מול הבנקים","desc":"משווים בין ההצעות ומנהלים מו״מ על הריבית והתנאים."},{"title":"חתימה וקבלת המפתח","desc":"ליווי עד לחתימה הסופית בבנק."}],"testimonials":[{"quote":"דנה ליוותה אותנו מהרגע הראשון ועד החתימה. חסכנו המון כסף בזכות המו״מ שהיא ניהלה.","name":"משפחת לוי","meta":"תל אביב"},{"quote":"מקצועיות, סבלנות וזמינות מלאה. ממליצים בחום על כל התהליך.","name":"יובל ומיכל","meta":"רמת גן"},{"quote":"הסבירה כל שלב בסבלנות והצליחה להשיג לנו ריבית טובה משמעותית ממה שחשבנו.","name":"אורי כהן","meta":"פתח תקווה"}],"faq":[{"q":"איך קובעים פגישה?","a":"בוחרים שירות, תאריך ושעה פנויה בטופס באתר, ומקבלים אישור מיידי. אין צורך בהרשמה."},{"q":"אפשר לשנות או לבטל פגישה?","a":"כן. בתחתית האתר, בכרטיס \\"ניהול פגישה קיימת\\", מזינים את מספר הטלפון שאיתו נקבעה הפגישה ואפשר לעדכן מועד או לבטל."},{"q":"איך אפשר ליצור קשר?","a":"פרטי הקשר מופיעים בסעיף \\"יצירת קשר\\" בתחתית האתר."}]}');
