<?php
require __DIR__ . '/config.php';

// Only allow this page to run while there is no super admin yet. Tenant admins are
// created afterwards from the super admin's dashboard (super.php), one per business.
$count = $pdo->query("SELECT COUNT(*) c FROM admins WHERE role = 'super_admin'")->fetch()['c'];
$done = false;
$error = '';

if ($count == 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (strlen($username) < 3 || strlen($password) < 6) {
        $error = 'שם משתמש (3+ תווים) וסיסמה (6+ תווים) נדרשים.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO admins (role, tenant_id, username, password_hash) VALUES ('super_admin', NULL, ?, ?)");
        $stmt->execute([$username, $hash]);
        $done = true;
    }
}
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>הגדרה ראשונית — יצירת מנהל-על</title>
<style>
  body { font-family: system-ui, sans-serif; background:#12162a; color:#eee; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; padding:20px; }
  .box { max-width:380px; width:100%; background:#1b2238; border-radius:16px; padding:24px; }
  input { width:100%; padding:12px; margin-top:6px; margin-bottom:16px; border-radius:10px; border:1px solid #444; background:#12162a; color:#eee; font-size:16px; box-sizing:border-box; }
  button { width:100%; padding:14px; border-radius:10px; border:none; background:#cda15c; color:#111; font-weight:700; font-size:15px; cursor:pointer; }
  label { font-size:13px; color:#aaa; }
  .err { color:#ff6b6b; margin-bottom:12px; font-size:14px; }
  .ok { color:#cda15c; }
  .password-wrap { position:relative; }
  .password-wrap input { padding-inline-end:44px; }
  .password-toggle {
    position:absolute; inset-inline-end:8px; top:6px; transform:translateY(0);
    width:32px; height:32px; border-radius:8px; border:none; background:transparent;
    color:#aaa; display:flex; align-items:center; justify-content:center; cursor:pointer; padding:0;
  }
  .password-toggle:hover { background:rgba(255,255,255,.08); }
</style>
</head>
<body>
<div class="box">
<?php if ($count > 0 && !$done): ?>
  <p>כבר קיים חשבון מנהל-על במערכת. עבור ל-<a href="super.php" style="color:#cda15c">פאנל הניהול הראשי</a>.</p>
  <p style="color:#f5a623;font-size:13px;">מומלץ למחוק את הקובץ setup.php מהשרת עכשיו מטעמי אבטחה.</p>
<?php elseif ($done): ?>
  <p class="ok">✔ חשבון מנהל-העל נוצר בהצלחה.</p>
  <p><a href="super.php" style="color:#cda15c">כניסה לפאנל הניהול הראשי</a></p>
  <p style="color:#f5a623;font-size:13px;">חשוב: מחקו את הקובץ setup.php מהשרת עכשיו כדי שאף אחד אחר לא יוכל ליצור חשבון מנהל-על נוסף.</p>
<?php else: ?>
  <h2>יצירת חשבון מנהל-על ראשון</h2>
  <p style="font-size:13px; color:#aaa;">מנהל-העל מנהל את כל האתרים בפלטפורמה. אתרים בודדים וההתחברות של בעלי העסקים ייווצרו לאחר מכן מתוך פאנל מנהל-העל.</p>
  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post">
    <label>שם משתמש</label>
    <input type="text" name="username" required>
    <label>סיסמה</label>
    <div class="password-wrap">
      <input type="password" name="password" id="setup-password" required minlength="6">
      <button type="button" class="password-toggle" onclick="togglePasswordVisibility('setup-password', this)" aria-label="הצג/הסתר סיסמה">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
    </div>
    <button type="submit">צור חשבון</button>
  </form>
  <script>
    function togglePasswordVisibility(inputId, btn) {
      var input = document.getElementById(inputId);
      var isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      btn.innerHTML = isHidden
        ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.94 10.94 0 0112 20c-7 0-11-7-11-7a21.6 21.6 0 015.06-6.06M9.9 4.24A10.94 10.94 0 0112 4c7 0 11 7 11 7a21.6 21.6 0 01-3.22 4.34M1 1l22 22"/><path d="M14.12 14.12a3 3 0 11-4.24-4.24"/></svg>'
        : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
  </script>
<?php endif; ?>
</div>
</body>
</html>
