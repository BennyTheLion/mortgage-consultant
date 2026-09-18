# Mortgage Consultant — Booking App (PHP + MySQL, for XAMPP)

## 1. Install the files
Copy the whole `booking-app` folder into your XAMPP `htdocs` folder, e.g.:
```
C:\xampp\htdocs\booking-app
```

## 2. Create the database
1. Start **Apache** and **MySQL** in the XAMPP control panel.
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
3. Click **Import**, choose `database.sql`, and run it.
   This creates the `mortgage_consultant` database with all tables and seed data.

Copy `config.example.php` to `config.php` (gitignored — it holds real secrets: DB password,
VAPID push private key). It already assumes the default XAMPP MySQL login (`root` / no
password, `localhost`); if your setup differs, edit the constants at the top.

## 3. Create your admin account
Visit:
```
http://localhost/booking-app/setup.php
```
Create your username + password. **Delete `setup.php` from the server afterwards** — it only
works once (while no admin exists) but it's good practice to remove it.

## 4. Open the site
```
http://localhost/booking-app/index.php
```
Log in from the header (person icon) to reach `admin.php`, where you can:
- Edit business info: phone, WhatsApp number, email, address, Instagram/Facebook/TikTok links
- Set working hours per weekday (and the slot length in minutes) — the public booking
  calendar and available time slots are generated automatically from these hours
- Add/edit/delete services (name, duration, price)
- Upload photos (max 20) and videos (max 5) for the galleries — the video gallery
  automatically stays hidden on the site if no videos have been uploaded
- View upcoming booked appointments
- Edit the Privacy Policy and Terms text shown at `privacy.php` / `terms.php`

## Upgrading an existing installation
If you already had this app running and just replaced the files, run `migrate_v2.sql` then
`migrate_v3.sql` in phpMyAdmin (SQL tab) on the `mortgage_consultant` database — they add the
columns/settings needed for email notifications, booking cancel/reschedule, and push
notifications, respectively. Skip both on a brand-new install; `database.sql` already includes
everything.

## Email notifications (booking confirmations, cancellations, reschedules)
Emails are sent via SMTP (XAMPP's local `mail()` function doesn't work without extra setup,
so this uses the PHPMailer library instead — already bundled in `vendor/phpmailer/`, no
Composer needed).

In the admin panel → **מייל** tab:
1. Check "שליחת מיילים פעילה" to turn mail on (it's off by default, so nothing tries to send
   until you've configured it).
2. Set the advisor's notification email address.
3. Fill in SMTP details. The easiest option is a Gmail account with an **App Password**
   (Google Account → Security → 2-Step Verification → App Passwords — different from your
   normal Gmail password):
   - Host: `smtp.gmail.com`
   - Port: `587`
   - Security: `TLS`
   - Username: your Gmail address
   - Password: the 16-character App Password
   - From email: your Gmail address

Once configured, every new booking emails both the customer (if they gave an email) and the
advisor. Cancelling or rescheduling a booking from the admin panel's **פגישות** tab emails both
sides again.

## Push notifications (advisor gets notified instantly on new/changed bookings)
In addition to email, the advisor can get a real browser/OS push notification — on desktop or
phone — the moment a customer books, cancels, or reschedules, even if the admin panel tab
isn't open.

This feature uses the `minishlink/web-push` library (installed via Composer — the one
exception to this project's "no Composer needed" approach, because push messages must be
encrypted per the Web Push spec (RFC 8291), which isn't practical to hand-roll safely).

**Setup (already done for this install, keep for reference):**
1. `composer install` — pulls in `vendor/minishlink/web-push` and its dependencies.
2. Generate a VAPID key pair once: `php bin/gen_vapid.php`, then paste the two keys into
   `config.php` (`VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY`).
3. Run `migrate_v3.sql` (or use the updated `database.sql` on a fresh install) — adds the
   `push_subscriptions` table.

**Enabling it as the advisor:** open the admin panel → click **"הפעלת התראות דחיפה למכשיר זה"**
near the top → allow the browser's notification permission prompt. Repeat on every device you
want notified (phone + desktop, for example). Click the button again to turn it off on that
device.

**Windows/XAMPP quirk this app works around:** `openssl_pkey_new()` (needed to encrypt each
push message) can't find OpenSSL's config file under Apache/mod_php or plain CLI on Windows —
it fails silently. `includes/push.php` works around this by sending each push from a short-lived
PHP CLI subprocess (`bin/push_worker.php`) launched with `OPENSSL_CONF` set explicitly in that
subprocess's environment (auto-detected in `config.php` as `OPENSSL_CNF_PATH`). If pushes ever
stop working after moving the app to a different machine, check that a candidate `openssl.cnf`
path in `config.php` actually exists there — on Linux hosting this workaround usually isn't
needed at all (openssl_pkey_new works out of the box there).

**Note:** browsers only allow push subscriptions on `https://` or `http://localhost` — this
works for local XAMPP testing, but a real deployment needs HTTPS for this feature to work for
the advisor.

**iPhone (iOS Safari) needs one extra step:** iOS only exposes the Push API to a site that has
been added to the Home Screen (Share → **הוספה למסך הבית** / "Add to Home Screen") — a regular
Safari tab can't request notification permission at all, no matter what you click. Requires
iOS 16.4+. After adding it, open the app from its **Home Screen icon** (not from a Safari tab),
log into the admin panel from there, and only then tap the push-enable button. `manifest.json`
(with `images/icon-192.png` / `icon-512.png`, generated once via `bin/gen_icons.php`) is what
makes "Add to Home Screen" produce a proper installed app instead of a plain bookmark.

## Managing appointments
**As the advisor** — the admin panel's **פגישות** tab lists all upcoming bookings. Each one has:
- **עדכון מועד** — pick a new date, then choose from the real available time slots for that
  service (the current slot itself is always offered, even though it's "taken" by this same
  booking). Checked server-side against other bookings so you can't double-book.
- **ביטול תור** — cancels it (kept in the list, greyed out, marked "בוטל" — not deleted).

Both send an email/push notification to both sides.

**As the customer** — on the public site, the **"ניהול תור קיים"** card (below the booking form)
lets anyone look up their own upcoming bookings by the phone number they booked with, then
cancel or reschedule the same way (calendar + real available slots). This is intentionally
lightweight (no accounts/passwords) — phone-number lookup, matching the rest of the app's
no-signup design; the server double-checks the phone before allowing any change.

Only genuinely open time slots are ever shown — taken and cancelled-then-reopened slots are
computed server-side (`api/availability.php`), so there's nothing anyone can accidentally pick
that isn't actually available.

## Notes on uploads
- Images are automatically resized (max width 1600px) and compressed to keep file sizes
  reasonable; allowed formats: JPG, PNG, WEBP.
- Videos are size-limited (default 60MB) and format-limited (MP4, WEBM, MOV) but are **not**
  re-compressed (that needs FFmpeg, which isn't included) — ask clients to send reasonably
  sized clips.
- If uploads bigger than a few MB are rejected outright by PHP itself, increase these values
  in `php.ini` (in XAMPP: `xampp/php/php.ini`) and restart Apache:
  ```
  upload_max_filesize = 64M
  post_max_size = 64M
  ```

## Security notes before going live
- Change the default `config.php` DB credentials if you move off `root`/no-password.
- Serve the site over HTTPS in production.
- The `images/` and `videos/` folders already block `.php` execution via `.htaccess`
  so uploaded files can't be used to run code on your server.
- Consider adding login rate-limiting if the admin login will be internet-facing.

## File map
```
index.php            Public site (header, gallery, working hours, booking, footer, WhatsApp, accessibility)
admin.php             Admin login + dashboard
privacy.php / terms.php   Legal pages (editable from admin panel)
setup.php              One-time admin account creation (delete after use)
config.php             DB connection + shared settings (incl. VAPID keys for push)
database.sql           Schema + seed data
sw.js                   Service worker — shows the push notification, handles its click
bin/gen_vapid.php       One-time: generate a VAPID key pair for push
bin/push_worker.php     Sends push notifications (run as a subprocess by includes/push.php)
includes/push.php       Push-sending helper, called from api/booking.php
api/                    JSON endpoints used by the front end
assets/style.css        All styling (original design preserved + new components)
assets/app.js           Public site behaviour
assets/admin.js         Admin dashboard behaviour
images/ , videos/       Uploaded media (protected by .htaccess)
```
