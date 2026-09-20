<?php
require __DIR__ . '/config.php';
$loggedIn = !empty($_SESSION['admin_id']);

// This page is the tenant admin's own dashboard. A super admin has no tenant_id
// and belongs on the cross-tenant platform dashboard instead.
if ($loggedIn && ($_SESSION['role'] ?? '') === 'super_admin') {
    header('Location: super.php');
    exit;
}

$tenantSiteUrl = '#';
if ($loggedIn && !empty($_SESSION['tenant_id'])) {
    $t = $pdo->prepare('SELECT slug FROM tenants WHERE id = ?');
    $t->execute([$_SESSION['tenant_id']]);
    $row = $t->fetch();
    if ($row) $tenantSiteUrl = 'site.php?slug=' . urlencode($row['slug']);
}
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>פאנל ניהול — ייעוץ משכנתאות</title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#f5f8fb">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ייעוץ משכנתאות">
<link rel="apple-touch-icon" href="images/icon-192.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>

<?php if (!$loggedIn): ?>
  <div style="display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px;">
    <div class="card" style="max-width:360px; width:100%; padding:26px 22px;">
      <div style="font-size:19px; font-weight:800; margin-bottom:18px;">כניסת מנהל</div>
      <div class="field"><label>שם משתמש</label><input type="text" id="login-username"></div>
      <div class="field">
        <label>סיסמה</label>
        <div class="password-wrap">
          <input type="password" id="login-password">
          <button type="button" class="password-toggle" onclick="togglePasswordVisibility('login-password', this)" aria-label="הצג/הסתר סיסמה">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>
      <div id="login-error" style="color:oklch(0.6 0.19 25); font-size:13px; margin-bottom:12px;"></div>
      <button class="btn btn-primary" onclick="adminLogin()">כניסה</button>
    </div>
  </div>
  <script>
    function togglePasswordVisibility(inputId, btn) {
      var input = document.getElementById(inputId);
      var isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      btn.innerHTML = isHidden
        ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.94 10.94 0 0112 20c-7 0-11-7-11-7a21.6 21.6 0 015.06-6.06M9.9 4.24A10.94 10.94 0 0112 4c7 0 11 7 11 7a21.6 21.6 0 01-3.22 4.34M1 1l22 22"/><path d="M14.12 14.12a3 3 0 11-4.24-4.24"/></svg>'
        : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
    function adminLogin() {
      var username = document.getElementById('login-username').value.trim();
      var password = document.getElementById('login-password').value;
      fetch('api/login.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({username:username, password:password}) })
        .then(function(r){ return r.json().then(function(d){ return {ok:r.ok, data:d}; }); })
        .then(function(res){
          if (res.ok) location.reload();
          else document.getElementById('login-error').textContent = res.data.error || 'שגיאה';
        });
    }
  </script>

<?php else: ?>

  <div class="site-header">
    <div class="brand"><span class="logo-dot">●</span> פאנל ניהול</div>
    <div style="display:flex; gap:8px;">
      <a href="<?= htmlspecialchars($tenantSiteUrl) ?>" class="icon-btn" aria-label="לאתר" title="לאתר">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>
      </a>
      <button class="icon-btn" onclick="adminLogout()" aria-label="יציאה" title="יציאה">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
      </button>
    </div>
  </div>

  <div class="wrap">

  <div style="padding:16px 20px 4px;">
    <button id="push-toggle-btn" class="btn btn-ghost" style="width:100%; font-size:13.5px;">🔕 הפעלת התראות דחיפה למכשיר זה</button>
  </div>

  <div class="admin-menu-bar">
    <button class="icon-btn" id="admin-menu-btn" onclick="toggleAdminMenu()" aria-label="תפריט" aria-expanded="false">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div class="admin-current-tab" id="admin-current-tab">פרטי עסק</div>
  </div>

  <div class="admin-menu-dropdown hidden" id="admin-menu-dropdown">
    <div class="admin-menu-item active" data-tab="settings" onclick="showTab('settings')">פרטי עסק</div>
    <div class="admin-menu-item" data-tab="hours" onclick="showTab('hours')">שעות פעילות</div>
    <div class="admin-menu-item" data-tab="content" onclick="showTab('content')">תוכן האתר</div>
    <div class="admin-menu-item" data-tab="services" onclick="showTab('services')">שירותי הזמנה</div>
    <div class="admin-menu-item" data-tab="gallery" onclick="showTab('gallery')">גלריה</div>
    <div class="admin-menu-item" data-tab="bookings" onclick="showTab('bookings')">פגישות</div>
    <div class="admin-menu-item" data-tab="mail" onclick="showTab('mail')">מייל</div>
    <div class="admin-menu-item" data-tab="legal" onclick="showTab('legal')">מסמכים משפטיים</div>
  </div>

  <!-- SETTINGS -->
  <div class="admin-section" data-panel="settings">
    <div class="field"><label>שם היועץ</label><input type="text" id="s-owner-name"></div>
    <div class="field"><label>תיאור קצר (מופיע מתחת לשם)</label><input type="text" id="s-tagline"></div>
    <div class="field"><label>קצת עליי (טקסט אודות באתר)</label><textarea id="s-about-text" rows="5"></textarea></div>
    <div class="section-title" style="margin-top:4px;">תמונת היועץ (מוצגת לצד "קצת עליי" באתר)</div>
    <div id="portrait-preview-wrap" class="hidden" style="margin-bottom:12px;">
      <img id="portrait-preview" src="" alt="תמונת היועץ" style="width:200px; aspect-ratio:3/4; height:auto; object-fit:contain; border-radius:14px; border:1px solid var(--admin-border); display:block;">
      <button class="btn btn-ghost btn-sm" style="margin-top:8px;" onclick="deletePortrait()">הסרת תמונה</button>
    </div>
    <label class="upload-box" for="portrait-upload" style="display:block;">לחצו להעלאת תמונת היועץ (JPG/PNG/WEBP)</label>
    <input type="file" id="portrait-upload" accept="image/*" class="hidden" onchange="uploadPortrait(this)">
    <div class="section-title">לוגו האתר (מוצג בכותרת האתר ובתחתיתו)</div>
    <div id="logo-preview-wrap" class="hidden" style="margin-bottom:12px;">
      <img id="logo-preview" src="" alt="לוגו" style="height:64px; max-width:200px; object-fit:contain; border:1px solid var(--admin-border); border-radius:10px; padding:8px; background:#fff; display:block;">
      <button class="btn btn-ghost btn-sm" style="margin-top:8px;" onclick="deleteLogo()">הסרת לוגו</button>
    </div>
    <label class="upload-box" for="logo-upload" style="display:block;">לחצו להעלאת לוגו (PNG/JPG/WEBP — מומלץ PNG עם רקע שקוף)</label>
    <input type="file" id="logo-upload" accept="image/*" class="hidden" onchange="uploadLogo(this)">
    <!-- Accent color is set by the platform's super admin, not by the tenant's own admin. -->
    <div style="display:flex; align-items:center; gap:10px; padding:12px 14px; border-radius:10px; background:var(--admin-bg); border:1px solid var(--admin-border); margin-bottom:16px;">
      <span id="s-accent-swatch" style="width:24px; height:24px; border-radius:50%; flex:0 0 auto; border:1px solid rgba(10,37,64,.2);"></span>
      <span style="font-size:12.5px; color:rgba(10,37,64,.6);">צבע הדגשה של האתר נקבע על ידי מנהל הפלטפורמה</span>
    </div>
    <div class="field"><label>טלפון</label><input type="tel" id="s-phone" placeholder="050-0000000"></div>
    <div class="field"><label>מספר וואטסאפ (עם קידומת מדינה, לדוגמה 9725...)</label><input type="tel" id="s-whatsapp"></div>
    <div class="field"><label>אימייל</label><input type="email" id="s-email"></div>
    <div class="field"><label>כתובת</label><input type="text" id="s-address"></div>
    <div class="field"><label>קישור אינסטגרם</label><input type="url" id="s-instagram" placeholder="https://instagram.com/..."></div>
    <div class="field"><label>קישור פייסבוק</label><input type="url" id="s-facebook" placeholder="https://facebook.com/..."></div>
    <div class="field"><label>קישור טיקטוק</label><input type="url" id="s-tiktok" placeholder="https://tiktok.com/@..."></div>
    <button class="btn btn-primary" onclick="saveSettings()">שמירה</button>
  </div>

  <!-- WORKING HOURS -->
  <div class="admin-section hidden" data-panel="hours">
    <div class="field"><label>משך כל משבצת זמן (בדקות)</label><input type="number" id="s-interval" min="5" step="5" value="30"></div>
    <div id="hours-editor"></div>
    <button class="btn btn-primary" onclick="saveSettings()">שמירה</button>
  </div>

  <!-- SITE CONTENT -->
  <div class="admin-section hidden" data-panel="content">
    <div style="font-size:12.5px; color:rgba(10,37,64,.55); margin-bottom:14px; line-height:1.6;">
      כל הטקסטים שמופיעים באתר הציבורי. אזור שנשאר ריק (המלצות, סטטיסטיקות, שאלות נפוצות וכו') לא יוצג באתר ולא יופיע בתפריט.
    </div>

    <div class="section-title">פתיח (Hero)</div>
    <div class="field"><label>תגית מעל הכותרת (אפשר להשאיר ריק)</label><input type="text" id="c-hero-badge" maxlength="120"></div>
    <div class="field"><label>משפט פתיחה</label><textarea id="c-hero-lead" rows="2" maxlength="400"></textarea></div>
    <div class="section-title">נתונים מספריים (עד 3, לדוגמה: "12+" / "שנות ניסיון")</div>
    <div id="c-stats"></div>
    <button class="btn btn-ghost btn-sm" onclick="addContentRow('stats')">+ הוספת נתון</button>

    <div class="section-title">אזור השירותים</div>
    <div class="field"><label>כותרת</label><input type="text" id="c-services-heading" maxlength="160"></div>
    <div class="field"><label>תיאור</label><input type="text" id="c-services-desc" maxlength="400"></div>
    <div id="c-service_cards"></div>
    <button class="btn btn-ghost btn-sm" onclick="addContentRow('service_cards')">+ הוספת כרטיס שירות</button>
    <div style="font-size:12px; color:rgba(10,37,64,.5); margin-top:6px;">אלו כרטיסי התצוגה באתר. השירותים שאפשר להזמין נערכים בלשונית "שירותי הזמנה".</div>

    <div class="section-title">אזור "אודות" (הטקסט והתמונה נערכים ב"פרטי עסק")</div>
    <div class="field"><label>כותרת תגית על התמונה (לדוגמה: "בעל רישיון יועץ משכנתאות")</label><input type="text" id="c-about-badge-title" maxlength="120"></div>
    <div class="field"><label>שורה משנית בתגית</label><input type="text" id="c-about-badge-sub" maxlength="160"></div>
    <div class="section-title" style="margin-top:8px;">תגיות הסמכה</div>
    <div id="c-credentials"></div>
    <button class="btn btn-ghost btn-sm" onclick="addContentRow('credentials')">+ הוספת תגית</button>

    <div class="section-title">איך זה עובד (שלבי התהליך)</div>
    <div id="c-process"></div>
    <button class="btn btn-ghost btn-sm" onclick="addContentRow('process')">+ הוספת שלב</button>

    <div class="section-title">מחשבון משכנתא</div>
    <div class="field"><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="c-show-calculator" style="width:auto;"> הצגת מחשבון המשכנתא באתר</label></div>

    <div class="section-title">המלצות לקוחות (יש להזין רק המלצות אמיתיות)</div>
    <div id="c-testimonials"></div>
    <button class="btn btn-ghost btn-sm" onclick="addContentRow('testimonials')">+ הוספת המלצה</button>

    <div class="section-title">שאלות נפוצות</div>
    <div id="c-faq"></div>
    <button class="btn btn-ghost btn-sm" onclick="addContentRow('faq')">+ הוספת שאלה</button>

    <div class="section-title">קריאה לפעולה ותחתית האתר</div>
    <div class="field"><label>כותרת באנר הפעולה</label><input type="text" id="c-cta-title" maxlength="160"></div>
    <div class="field"><label>טקסט באנר הפעולה</label><input type="text" id="c-cta-text" maxlength="400"></div>
    <div class="field"><label>טקסט קצר בתחתית האתר</label><textarea id="c-footer-blurb" rows="2" maxlength="400"></textarea></div>

    <button class="btn btn-primary" style="margin-top:8px;" onclick="saveSiteContent()">שמירה</button>
  </div>

  <!-- SERVICES -->
  <div class="admin-section hidden" data-panel="services">
    <div id="services-list" style="margin-bottom:20px;"></div>
    <div class="card" style="padding:16px;">
      <div class="section-title">הוספת / עריכת שירות</div>
      <input type="hidden" id="svc-id">
      <div class="field"><label>שם השירות</label><input type="text" id="svc-name"></div>
      <div class="field"><label>משך (בדקות)</label><input type="number" id="svc-duration" min="5" value="30"></div>
      <div class="field"><label>מחיר (טקסט חופשי, אפשר להשאיר ריק)</label><input type="text" id="svc-price"></div>
      <button class="btn btn-primary" onclick="saveService()">שמירת שירות</button>
      <button class="btn btn-ghost" style="margin-top:8px;" onclick="resetServiceForm()">שירות חדש</button>
    </div>
  </div>

  <!-- GALLERY -->
  <div class="admin-section hidden" data-panel="gallery">
    <div class="section-title">תמונות (מקסימום 20)</div>
    <label class="upload-box" for="image-upload">לחצו להעלאת תמונה (JPG/PNG/WEBP)</label>
    <input type="file" id="image-upload" accept="image/*" multiple class="hidden" onchange="uploadMedia('image', this)">
    <div id="image-grid" class="media-grid"></div>

    <div class="section-title" style="margin-top:26px;">סרטונים (מקסימום 5)</div>
    <label class="upload-box" for="video-upload">לחצו להעלאת סרטון (MP4/WEBM/MOV)</label>
    <input type="file" id="video-upload" accept="video/*" class="hidden" onchange="uploadMedia('video', this)">
    <div id="video-grid" class="media-grid"></div>
  </div>

  <!-- BOOKINGS -->
  <div class="admin-section hidden" data-panel="bookings">
    <div id="bookings-list"></div>
  </div>

  <!-- MAIL -->
  <div class="admin-section hidden" data-panel="mail">
    <div class="field">
      <label style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" id="s-mail-enabled" style="width:auto;"> שליחת מיילים פעילה
      </label>
    </div>
    <div style="font-size:12.5px;color:rgba(10,37,64,.5);margin-bottom:16px;line-height:1.6;">
      כדי לשלוח מיילים אמיתיים דרך XAMPP, מומלץ להשתמש בחשבון Gmail עם "סיסמת אפליקציה" (App Password).
      Host: smtp.gmail.com, פורט: 587, אבטחה: tls.
    </div>
    <div class="field"><label>אימייל לקבלת הודעות על פגישה חדשה (היועץ)</label><input type="email" id="s-admin-email" placeholder="advisor@example.com"></div>
    <div class="field"><label>SMTP Host</label><input type="text" id="s-smtp-host" placeholder="smtp.gmail.com"></div>
    <div class="field"><label>SMTP Port</label><input type="number" id="s-smtp-port" placeholder="587"></div>
    <div class="field"><label>SMTP Username</label><input type="text" id="s-smtp-username"></div>
    <div class="field"><label>SMTP Password (App Password)</label>
      <div class="password-wrap">
        <input type="password" id="s-smtp-password">
        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('s-smtp-password', this)" aria-label="הצג/הסתר">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>
    <div class="field">
      <label>סוג אבטחה</label>
      <select id="s-smtp-secure">
        <option value="tls">TLS</option>
        <option value="ssl">SSL</option>
      </select>
    </div>
    <div class="field"><label>כתובת "שולח" (From)</label><input type="email" id="s-smtp-from-email"></div>
    <div class="field"><label>שם "שולח" (From Name)</label><input type="text" id="s-smtp-from-name" placeholder="ייעוץ משכנתאות"></div>
    <button class="btn btn-primary" onclick="saveSettings()">שמירה</button>

    <div style="margin-top:24px; padding-top:20px; border-top:1px solid rgba(10,37,64,.1);">
      <div class="section-title">בדיקת שליחה</div>
      <div class="field"><label>שלח מייל בדיקה אל</label><input type="email" id="test-mail-to" placeholder="you@example.com"></div>
      <button class="btn btn-ghost" onclick="sendTestMail()">שליחת מייל בדיקה</button>
      <div id="test-mail-result" style="font-size:13px; margin-top:10px; line-height:1.6;"></div>
    </div>
  </div>

  <!-- LEGAL -->
  <div class="admin-section hidden" data-panel="legal">
    <div class="field"><label>מדיניות פרטיות</label><textarea id="s-privacy" rows="10"></textarea></div>
    <div class="field"><label>תקנון האתר</label><textarea id="s-terms" rows="10"></textarea></div>
    <button class="btn btn-primary" onclick="saveSettings()">שמירה</button>
  </div>

  </div><!-- /.wrap -->

  <script>var VAPID_PUBLIC_KEY = <?= json_encode(VAPID_PUBLIC_KEY) ?>;</script>
  <script src="assets/theme.js?v=<?= @filemtime(__DIR__ . '/assets/theme.js') ?>"></script>
  <script src="assets/admin.js?v=<?= @filemtime(__DIR__ . '/assets/admin.js') ?>"></script>
<?php endif; ?>
</body>
</html>
