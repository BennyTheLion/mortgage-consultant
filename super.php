<?php
require __DIR__ . '/config.php';
$loggedIn = !empty($_SESSION['admin_id']);
$isSuperAdmin = $loggedIn && ($_SESSION['role'] ?? '') === 'super_admin';

// A tenant admin has no business here — send them to their own dashboard instead.
if ($loggedIn && !$isSuperAdmin) {
    header('Location: admin.php');
    exit;
}
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>פאנל מנהל-על — הפלטפורמה</title>
<meta name="theme-color" content="#f5f8fb">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>

<?php if (!$isSuperAdmin): ?>
  <div style="display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px;">
    <div class="card" style="max-width:360px; width:100%; padding:26px 22px;">
      <div style="font-size:19px; font-weight:800; margin-bottom:18px;">כניסת מנהל-על</div>
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
      <button class="btn btn-primary" onclick="superLogin()">כניסה</button>
    </div>
  </div>
  <script>
    function superLogin() {
      var username = document.getElementById('login-username').value.trim();
      var password = document.getElementById('login-password').value;
      fetch('api/login.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({username:username, password:password}) })
        .then(function(r){ return r.json().then(function(d){ return {ok:r.ok, data:d}; }); })
        .then(function(res){
          if (res.ok && res.data.role === 'super_admin') location.reload();
          else if (res.ok) document.getElementById('login-error').textContent = 'המשתמש הזה אינו מנהל-על';
          else document.getElementById('login-error').textContent = res.data.error || 'שגיאה';
        });
    }
  </script>

<?php else: ?>

  <div class="site-header">
    <div class="brand"><span class="logo-dot">●</span> פאנל מנהל-על</div>
    <button class="icon-btn" onclick="superLogout()" aria-label="יציאה" title="יציאה">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
    </button>
  </div>

  <div class="wrap">
    <div class="admin-menu-bar">
      <div class="admin-menu-dropdown" style="display:flex; flex-direction:row; flex-wrap:wrap; gap:8px; background:transparent; border:none; padding:0; margin:0;">
        <div class="admin-menu-item active" data-tab="tenants" onclick="showSuperTab('tenants')" style="background:rgba(10,37,64,.06);">אתרים</div>
        <div class="admin-menu-item" data-tab="bookings" onclick="showSuperTab('bookings')" style="background:rgba(10,37,64,.06);">פגישות בכל האתרים</div>
      </div>
    </div>

    <!-- TENANTS -->
    <div class="admin-section" data-panel="tenants">
      <div class="card" style="padding:16px; margin-bottom:20px;">
        <div class="section-title">יצירת אתר חדש</div>
        <div class="field"><label>שם העסק</label><input type="text" id="nt-name" oninput="suggestSlug()" placeholder="לדוגמה: ייעוץ משכנתאות — דנה כהן"></div>
        <div class="field"><label>כתובת (slug) — אותיות אנגליות קטנות, ספרות ומקפים בלבד</label><input type="text" id="nt-slug" placeholder="dana-cohen"></div>
        <div class="field"><label>שם משתמש למנהל האתר</label><input type="text" id="nt-admin-username"></div>
        <div class="field">
          <label>סיסמה למנהל האתר (6+ תווים)</label>
          <div class="password-wrap">
            <input type="password" id="nt-admin-password">
            <button type="button" class="password-toggle" onclick="togglePasswordVisibility('nt-admin-password', this)" aria-label="הצג/הסתר סיסמה">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <div class="field">
          <label>צבע הדגשה של האתר</label>
          <div style="display:flex; align-items:center; gap:10px;">
            <input type="color" id="nt-accent-color" value="#cda15c" style="width:52px; height:44px; padding:4px; cursor:pointer;">
            <input type="text" id="nt-accent-color-hex" value="#cda15c" maxlength="7" style="flex:1;">
          </div>
        </div>
        <div id="nt-error" style="color:oklch(0.6 0.19 25); font-size:13px; margin-bottom:10px;"></div>
        <button class="btn btn-primary" onclick="createTenant()">יצירת אתר</button>
      </div>

      <div class="section-title">כל האתרים</div>
      <div id="tenants-list"></div>
    </div>

    <!-- CROSS-TENANT BOOKINGS -->
    <div class="admin-section hidden" data-panel="bookings">
      <div id="all-bookings-list"></div>
    </div>
  </div>

<?php endif; ?>

<script src="assets/super.js?v=<?= @filemtime(__DIR__ . '/assets/super.js') ?>"></script>
</body>
</html>
