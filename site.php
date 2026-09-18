<?php
require __DIR__ . '/config.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    http_response_code(400);
    die('חסרה כתובת אתר. הקישור הנכון הוא /site/{slug}/');
}
$stmt = $pdo->prepare('SELECT * FROM tenants WHERE slug = ?');
$stmt->execute([$slug]);
$tenant = $stmt->fetch();

if (!$tenant) {
    http_response_code(404);
    die('האתר המבוקש לא נמצא.');
}
if ($tenant['status'] !== 'active') {
    http_response_code(503);
    die('האתר אינו זמין כעת. אנא פנו לבעל העסק.');
}
$tenantId = (int) $tenant['id'];
$logoLetter = function_exists('mb_substr') ? mb_substr($tenant['name'], 0, 1) : substr($tenant['name'], 0, 1);
$logoUrl = null;
foreach (['png', 'jpg', 'webp'] as $logoExt) {
    $logoCandidate = __DIR__ . '/images/' . $tenantId . '/logo.' . $logoExt;
    if (is_file($logoCandidate)) {
        $logoUrl = 'images/' . $tenantId . '/logo.' . $logoExt . '?v=' . @filemtime($logoCandidate);
        break;
    }
}
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<!-- Reached via the pretty /site/{slug}/ URL, which is an internal rewrite to this same
     script — the browser still resolves relative URLs against /site/{slug}/, so pin
     every relative link/fetch back to the app's real root with <base>. -->
<base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/') ?>">
<title><?= htmlspecialchars($tenant['name']) ?> — קביעת פגישה</title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0a2540">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($tenant['name']) ?>">
<link rel="apple-touch-icon" href="images/icon-192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/site.css?v=<?= @filemtime(__DIR__ . '/assets/site.css') ?>">
<script>window.TENANT_SLUG = <?= json_encode($slug) ?>; window.TENANT_ID = <?= json_encode($tenantId) ?>;</script>
</head>
<body>
<div id="confetti-layer"></div>

<!-- HEADER -->
<div class="site-header" id="site-header">
  <a href="#top" class="brand">
    <?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl) ?>" alt="" class="brand-logo"><?php else: ?><span class="logo-mark" aria-hidden="true"><?= htmlspecialchars($logoLetter) ?></span><?php endif; ?>
    <span><?= htmlspecialchars($tenant['name']) ?></span>
  </a>
  <ul class="nav-links" id="nav-links">
    <li><a href="#services">שירותים</a></li>
    <li><a href="#about">אודות</a></li>
    <li><a href="#calculator">מחשבון</a></li>
    <li><a href="#testimonials">המלצות</a></li>
    <li><a href="#faq">שאלות נפוצות</a></li>
    <li><a href="#contact">צור קשר</a></li>
  </ul>
  <button class="btn btn-primary nav-cta" onclick="revealBooking()">קביעת פגישה</button>
  <button id="admin-login-btn" class="icon-btn hidden" onclick="openLoginModal()" aria-label="כניסת מנהל" title="כניסת מנהל">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
  </button>
  <a id="admin-panel-btn" href="admin.php" class="icon-btn hidden" aria-label="פאנל ניהול" title="פאנל ניהול" style="text-decoration:none;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.34 1.87l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.7 1.7 0 00-1.87-.34 1.7 1.7 0 00-1 1.55V21a2 2 0 01-4 0v-.09a1.7 1.7 0 00-1-1.55 1.7 1.7 0 00-1.87.34l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.7 1.7 0 00.34-1.87 1.7 1.7 0 00-1.55-1H3a2 2 0 010-4h.09a1.7 1.7 0 001.55-1 1.7 1.7 0 00-.34-1.87l-.06-.06a2 2 0 112.83-2.83l.06.06a1.7 1.7 0 001.87.34H9a1.7 1.7 0 001-1.55V3a2 2 0 014 0v.09a1.7 1.7 0 001 1.55 1.7 1.7 0 001.87-.34l.06-.06a2 2 0 112.83 2.83l-.06.06a1.7 1.7 0 00-.34 1.87V9a1.7 1.7 0 001.55 1H21a2 2 0 010 4h-.09a1.7 1.7 0 00-1.55 1z"/></svg>
  </a>
  <button class="burger" id="burger" aria-label="פתח תפריט" aria-expanded="false" aria-controls="nav-links">
    <span></span><span></span><span></span>
  </button>
</div>

<!-- HERO -->
<div class="hero" id="top">
  <div class="wrap" style="padding-bottom:0;">
    <div class="hero-inner">
      <div>
        <span class="hero-badge">✓ פגישת ייעוץ ראשונה ללא עלות</span>
        <h1 id="hero-title"><span id="owner-name"><?= htmlspecialchars($tenant['name']) ?></span></h1>
        <div class="tagline" id="owner-tagline"></div>
        <p class="lead">ליווי אישי, שקוף ומקצועי לאורך כל התהליך — מהפגישה הראשונה ועד הסיום.</p>
        <div class="hero-actions">
          <button id="hero-cta" class="btn btn-primary hero-shake" onclick="revealBooking()">קביעת פגישה</button>
          <a href="#calculator" class="btn btn-ghost" style="border-color:rgba(255,255,255,.4); color:#fff;">חישוב משכנתא</a>
        </div>
        <div class="hero-stats">
          <div><div class="stat-num">12+</div><div class="stat-label">שנות ניסיון</div></div>
          <div><div class="stat-num">600+</div><div class="stat-label">עסקאות בליווי</div></div>
          <div><div class="stat-num">0 ₪</div><div class="stat-label">עלות פגישה ראשונה</div></div>
        </div>
      </div>

      <div class="hero-card-wrap">
        <!-- BOOKING CARD -->
        <div id="booking-card" class="card" style="padding:26px 22px; display:flex; flex-direction:column; gap:20px;">
          <div id="confirmed-view" class="hidden" style="display:flex; flex-direction:column; align-items:center; gap:14px; padding:12px 4px 4px; text-align:center;">
            <div class="check-circle" style="width:56px; height:56px; border-radius:50%; background:var(--accent-soft); display:flex; align-items:center; justify-content:center;">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--accent-dark)" stroke-width="2.4"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>
            </div>
            <div style="font-size:19px; font-weight:800;">הפגישה נקבעה בהצלחה!</div>
            <div id="summary-text" style="font-size:14.5px; color:var(--gray); line-height:1.6;"></div>
            <button class="btn btn-ghost" style="margin-top:6px;" onclick="resetBooking()">קביעת פגישה נוספת</button>
          </div>

          <div id="booking-form">
            <h3 style="margin:0 0 4px; font-size:18px; font-weight:800;">בדיקת התאמה מהירה</h3>
            <div style="font-size:13px; color:var(--gray); margin-bottom:18px;">בחרו שירות וזמן פנוי — אישור מיידי</div>

            <div style="margin-bottom:18px;">
              <div class="section-title">סוג הפגישה</div>
              <select id="service-list"></select>
            </div>

            <div class="field">
              <label>תאריך</label>
              <input type="date" id="date-input">
            </div>

            <div style="margin-bottom:18px;">
              <div class="section-title">שעות פנויות</div>
              <div id="slot-list" style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:8px;"></div>
            </div>

            <div class="field"><label>שם מלא</label><input type="text" id="cust-name" placeholder="השם שלך"></div>
            <div class="field"><label>טלפון</label><input type="tel" id="cust-phone" placeholder="050-0000000"></div>
            <div class="field"><label>אימייל (לקבלת אישור, אופציונלי)</label><input type="email" id="cust-email" placeholder="you@example.com"></div>

            <button id="confirm-btn" class="btn btn-primary" disabled onclick="confirmBooking()">אישור הפגישה</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="wrap">

  <!-- SERVICES -->
  <div class="observe section" id="services">
    <div class="section-head">
      <span class="section-tag">השירותים שלי</span>
      <h2 class="section-heading">ליווי מקצועי בכל שלב בדרך לבית</h2>
      <p class="section-desc">מתכנון ראשוני ועד חתימה בבנק — הבדיקה, ההשוואה והמשא ומתן מתבצעים בשבילכם.</p>
    </div>
    <div class="service-grid">
      <div class="service-card">
        <div class="service-image">
          <img src="https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=500&q=70" alt="" loading="lazy">
          <div class="service-icon-badge"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/></svg></div>
        </div>
        <div class="service-body">
          <div class="title">משכנתא לדירה ראשונה</div>
          <div class="meta">בדיקת זכאות, בניית תמהיל ומו״מ מול הבנקים מההתחלה ועד החתימה.</div>
        </div>
      </div>
      <div class="service-card">
        <div class="service-image">
          <img src="https://images.unsplash.com/photo-1554224155-6726b3ff858f?w=500&q=70" alt="" loading="lazy">
          <div class="service-icon-badge"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></div>
        </div>
        <div class="service-body">
          <div class="title">מיחזור משכנתא</div>
          <div class="meta">בדיקה האם משתלם למחזר כיום, וכמה בדיוק תחסכו לאורך זמן.</div>
        </div>
      </div>
      <div class="service-card">
        <div class="service-image">
          <img src="https://images.unsplash.com/photo-1582407947304-fd86f028f716?w=500&q=70" alt="" loading="lazy">
          <div class="service-icon-badge"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg></div>
        </div>
        <div class="service-body">
          <div class="title">ליווי מול הבנק</div>
          <div class="meta">אני יושב בצד שלכם בכל שיחה ומסמך, כדי שלא תישארו לבד מול הפקיד.</div>
        </div>
      </div>
      <div class="service-card">
        <div class="service-image">
          <img src="https://images.unsplash.com/photo-1560520653-9e0e4c89eb11?w=500&q=70" alt="" loading="lazy">
          <div class="service-icon-badge"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/></svg></div>
        </div>
        <div class="service-body">
          <div class="title">משקיעים ונכס שני</div>
          <div class="meta">תכנון מימון לרכישת נכס נוסף, כולל השפעת המשכנתא הקיימת.</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ABOUT -->
  <div class="observe section" id="about">
    <div class="about-flex">
      <div class="about-image-wrap">
        <img src="images/<?= $tenantId ?>/advisor-portrait.jpg?v=<?= @filemtime(__DIR__ . '/images/' . $tenantId . '/advisor-portrait.jpg') ?>" alt="" onerror="this.closest('.about-image-wrap').style.display='none'">
        <div class="about-badge">
          <div class="about-badge-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/></svg></div>
          <div><strong>בעל רישיון יועץ משכנתאות</strong><span>ליווי מעל 600 עסקאות</span></div>
        </div>
      </div>
      <div>
        <span class="section-tag">קצת עליי</span>
        <h2 class="section-heading">מי מלווה אתכם בתהליך</h2>
        <div id="about-text" style="font-size:15px; line-height:1.8; color:var(--gray); margin-bottom:18px;"></div>
        <div class="about-credentials" style="margin-bottom:24px;">
          <span class="credential-pill">בעל רישיון יועץ משכנתאות</span>
          <span class="credential-pill">ללא ניגוד עניינים עם הבנקים</span>
        </div>
        <button class="btn btn-primary" style="width:auto;" onclick="revealBooking()">בואו נדבר</button>
      </div>
    </div>
  </div>

  <!-- PROCESS -->
  <div class="observe section">
    <div class="section-head">
      <span class="section-tag">התהליך</span>
      <h2 class="section-heading">איך זה עובד</h2>
    </div>
    <div class="process-list">
      <div class="process-step">
        <div class="process-num">1</div>
        <div class="process-body"><div class="title">פגישת ייעוץ ראשונית</div><div class="desc">מכירים, ממפים את המצב הכלכלי ואת המטרה — פרונטלית, בזום או בטלפון.</div></div>
      </div>
      <div class="process-step">
        <div class="process-num">2</div>
        <div class="process-body"><div class="title">בדיקת זכאות ותמהיל</div><div class="desc">בונים את תמהיל המשכנתא המתאים לכם ומגישים לבנקים לקבלת הצעות.</div></div>
      </div>
      <div class="process-step">
        <div class="process-num">3</div>
        <div class="process-body"><div class="title">מו״מ מול הבנקים</div><div class="desc">משווים בין ההצעות ומנהלים מו״מ על הריבית והתנאים בשמכם.</div></div>
      </div>
      <div class="process-step">
        <div class="process-num">4</div>
        <div class="process-body"><div class="title">חתימה וקבלת המפתח</div><div class="desc">ליווי עד לחתימה הסופית בבנק — והמפתח שלכם ביד.</div></div>
      </div>
    </div>
  </div>

  <!-- CALCULATOR -->
  <div class="observe section" id="calculator">
    <div class="section-head">
      <span class="section-tag">מחשבון משכנתא</span>
      <h2 class="section-heading">כמה תשלמו בחודש?</h2>
      <p class="section-desc">הזיזו את הסליידרים וקבלו אומדן מיידי. רוצים תמהיל מדויק? קבעו פגישה.</p>
    </div>
    <div class="calc-wrap">
      <div class="calc-form">
        <div class="field">
          <label>סכום המשכנתא</label>
          <div class="range-input">
            <input type="range" id="loanAmount" min="200000" max="3000000" step="50000" value="1000000">
            <span class="range-value" id="loanAmountVal">₪1,000,000</span>
          </div>
        </div>
        <div class="field">
          <label>הון עצמי</label>
          <div class="range-input">
            <input type="range" id="equity" min="0" max="1500000" step="25000" value="300000">
            <span class="range-value" id="equityVal">₪300,000</span>
          </div>
        </div>
        <div class="calc-row">
          <div class="field">
            <label>תקופה (שנים)</label>
            <select id="years">
              <option value="10">10</option>
              <option value="15">15</option>
              <option value="20">20</option>
              <option value="25" selected>25</option>
              <option value="30">30</option>
            </select>
          </div>
          <div class="field">
            <label>ריבית שנתית (%)</label>
            <input type="number" id="rate" value="4.5" min="0.1" max="15" step="0.1" inputmode="decimal">
          </div>
        </div>
      </div>
      <div class="calc-result">
        <h3>החזר חודשי משוער</h3>
        <div class="payment-big" id="monthlyPayment">₪5,561</div>
        <div class="payment-sub">תשלום חודשי קבוע (ללא הצמדה)</div>
        <div class="calc-rows">
          <div class="calc-result-row"><span>סכום ההלוואה</span><span id="rLoan">₪1,000,000</span></div>
          <div class="calc-result-row"><span>סה״כ תשלומים</span><span id="rTotal">₪1,668,300</span></div>
          <div class="calc-result-row"><span>סה״כ ריבית</span><span id="rInterest">₪668,300</span></div>
          <div class="calc-result-row"><span>אחוז מימון</span><span id="rLtv">77%</span></div>
        </div>
        <button class="btn btn-primary" style="width:100%;" onclick="revealBooking()">קבלת תמהיל מדויק ←</button>
      </div>
    </div>
  </div>

  <!-- TESTIMONIALS -->
  <div class="observe section" id="testimonials">
    <div class="section-head">
      <span class="section-tag">לקוחות מספרים</span>
      <h2 class="section-heading">מה אומרים עליי</h2>
    </div>
    <div class="testimonial-scroll">
      <div class="testimonial-card">
        <div class="stars">★★★★★</div>
        <div class="quote">חסך לנו המון כסף וזמן. ליווה אותנו צעד-צעד ותמיד היה זמין לשאלות, גם בערבים.</div>
        <div class="author">
          <span class="author-avatar" style="overflow:hidden;"><img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&q=70" alt="" style="width:100%;height:100%;object-fit:cover;"></span>
          <div><div class="author-name">מיכל ואיתי</div><div class="author-meta">ראשון לציון</div></div>
        </div>
      </div>
      <div class="testimonial-card">
        <div class="stars">★★★★★</div>
        <div class="quote">הגענו אחרי שדחו אותנו בבנק, ובזכות התמהיל שבנה לנו קיבלנו אישור עקרוני תוך שבוע.</div>
        <div class="author">
          <span class="author-avatar" style="overflow:hidden;"><img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=80&q=70" alt="" style="width:100%;height:100%;object-fit:cover;"></span>
          <div><div class="author-name">דנה כהן</div><div class="author-meta">פתח תקווה</div></div>
        </div>
      </div>
      <div class="testimonial-card">
        <div class="stars">★★★★★</div>
        <div class="quote">מקצועי, ישר, ולא מוכר לך שום דבר שאתה לא צריך. ממליץ בחום לכל מי שקונה דירה ראשונה.</div>
        <div class="author">
          <span class="author-avatar" style="overflow:hidden;"><img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=80&q=70" alt="" style="width:100%;height:100%;object-fit:cover;"></span>
          <div><div class="author-name">אבי לוי</div><div class="author-meta">מודיעין</div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- FAQ -->
  <div class="observe card section" id="faq" style="padding:20px 20px;">
    <div class="section-title">שאלות נפוצות</div>
    <details class="faq-item">
      <summary>כמה עולה הייעוץ?
        <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
      </summary>
      <div class="faq-answer">הפגישה הראשונה ללא עלות. מעבר לכך, שכר הטרחה נקבע לפי היקף הליווי ומוסכם מראש, לפני שמתחילים לעבוד יחד.</div>
    </details>
    <details class="faq-item">
      <summary>אתם עובדים מול כל הבנקים?
        <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
      </summary>
      <div class="faq-answer">כן — אני בלתי תלוי ולא מקבל עמלה מאף בנק, כך שההמלצה שתקבלו מבוססת אך ורק על מה שהכי משתלם לכם.</div>
    </details>
    <details class="faq-item">
      <summary>כמה זמן לוקח התהליך?
        <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
      </summary>
      <div class="faq-answer">בממוצע 3-5 שבועות מהפגישה הראשונה ועד לאישור העקרוני, תלוי בבנק ובמסמכים הנדרשים.</div>
    </details>
    <details class="faq-item">
      <summary>אני כבר באמצע תהליך מול בנק — אפשר להצטרף עכשיו?
        <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
      </summary>
      <div class="faq-answer">בהחלט. אפשר להיכנס בכל שלב — גם רק לבדוק שההצעה שקיבלתם הוגנת, וגם לקחת את המו״מ לידיים.</div>
    </details>
  </div>

  <!-- GALLERY (certificates / office photos) -->
  <div id="gallery-section" class="observe hidden section">
    <div class="section-title">תעודות ותמונות מהמשרד</div>
    <div class="gallery-wrap">
      <div class="gallery-scroll" id="gallery-scroll"></div>
      <button class="gallery-arrow gallery-arrow-left" id="gallery-arrow-left" aria-label="תמונה הבאה">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 6l-6 6 6 6"/></svg>
      </button>
      <button class="gallery-arrow gallery-arrow-right" id="gallery-arrow-right" aria-label="תמונה קודמת">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 6l6 6-6 6"/></svg>
      </button>
    </div>
    <div class="gallery-dots" id="gallery-dots"></div>
  </div>

  <!-- GALLERY (videos) -->
  <div id="video-section" class="observe hidden section">
    <div class="section-title">סרטונים</div>
    <div class="gallery-scroll" id="video-scroll"></div>
  </div>

  <!-- CONTACT -->
  <div class="observe section" id="contact">
    <div class="section-head">
      <span class="section-tag">יצירת קשר</span>
      <h2 class="section-heading">בואו נדבר</h2>
      <p class="section-desc">זמין עבורכם בכל שאלה — לפני, במהלך ואחרי התהליך.</p>
    </div>
    <div class="contact-grid">
      <div>
        <div id="contact-items"></div>
      </div>
      <div>
        <div class="card" style="padding:20px; margin-bottom:16px;">
          <div class="section-title">שעות פעילות</div>
          <div id="working-hours-list"></div>
        </div>
        <div id="manage-card" class="observe card" style="padding:20px;">
          <div class="section-title">ניהול פגישה קיימת</div>
          <div style="font-size:13.5px; color:var(--gray); margin-bottom:14px; line-height:1.6;">
            הזינו את הטלפון שאיתו קבעתם את הפגישה, כדי לעדכן מועד או לבטל.
          </div>
          <div style="display:flex; gap:8px; align-items:flex-end;">
            <div class="field" style="margin-bottom:0; flex:1;"><label>טלפון</label><input type="tel" id="manage-phone" placeholder="050-0000000"></div>
            <button class="btn btn-ghost btn-auto" onclick="lookupMyBookings()">חיפוש</button>
          </div>
          <div id="manage-list" style="margin-top:16px;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- CTA -->
  <div class="observe section">
    <div class="cta-banner">
      <h2>מוכנים לקבוע פגישה?</h2>
      <p>בלי הרשמה, בלי המתנה בטלפון — בוחרים שירות וזמן פנוי ומקבלים אישור מיידי.</p>
      <div class="cta-actions">
        <button class="btn btn-primary" onclick="revealBooking()">קביעת פגישה</button>
      </div>
    </div>
  </div>

</div>

<!-- FOOTER -->
<div class="footer-band">
  <div class="footer-grid">
    <div class="footer-about">
      <div class="footer-brand">
        <?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl) ?>" alt="" class="brand-logo"><?php else: ?><span class="logo-mark" aria-hidden="true"><?= htmlspecialchars($logoLetter) ?></span><?php endif; ?>
        <span><?= htmlspecialchars($tenant['name']) ?></span>
      </div>
      <p>ליווי אישי ומקצועי בתהליך המשכנתא, משלב הבדיקה הראשונית ועד החתימה בבנק.</p>
    </div>
    <div>
      <h4>ניווט</h4>
      <ul>
        <li><a href="#services">שירותים</a></li>
        <li><a href="#about">אודות</a></li>
        <li><a href="#calculator">מחשבון</a></li>
        <li><a href="#testimonials">המלצות</a></li>
      </ul>
    </div>
    <div>
      <h4>שירותים</h4>
      <ul>
        <li><a href="#services">משכנתא לדירה ראשונה</a></li>
        <li><a href="#services">מיחזור משכנתא</a></li>
        <li><a href="#services">ליווי מול הבנק</a></li>
        <li><a href="#services">משקיעים ונכס שני</a></li>
      </ul>
    </div>
    <div>
      <h4>יצירת קשר</h4>
      <div class="site-footer" style="padding:0; margin:0;">
        <div>
          <span id="footer-contact"></span>
          <a id="footer-email" href="#" class="hidden" style="margin-inline-start:8px;"></a>
        </div>
        <div class="socials" id="footer-socials"></div>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="legal" style="margin-bottom:8px;">
      <a href="privacy.php?slug=<?= urlencode($slug) ?>">מדיניות פרטיות</a>
      <a href="terms.php?slug=<?= urlencode($slug) ?>">תקנון</a>
    </div>
    © <?= date('Y') ?> <?= htmlspecialchars($tenant['name']) ?>. כל הזכויות שמורות.
  </div>
</div>

<!-- FLOATING WHATSAPP -->
<a id="whatsapp-fab" href="#" target="_blank" rel="noopener" class="fab fab-whatsapp hidden" aria-label="שלחו לנו הודעת וואטסאפ">
  <svg width="26" height="26" viewBox="0 0 24 24" fill="white"><path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.29-1.39a9.87 9.87 0 004.75 1.21h.01c5.46 0 9.9-4.44 9.9-9.9S17.5 2 12.04 2zm0 18.03h-.01a8.2 8.2 0 01-4.18-1.14l-.3-.18-3.12.82.84-3.05-.2-.31a8.2 8.2 0 01-1.26-4.37c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.83 2.42a8.19 8.19 0 012.41 5.83c0 4.54-3.7 8.24-8.25 8.24zm4.52-6.16c-.25-.12-1.47-.72-1.7-.81-.23-.08-.4-.12-.56.12-.17.25-.65.81-.79.97-.15.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.39-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.44.12-.15.16-.25.25-.41.08-.17.04-.31-.02-.44-.06-.12-.56-1.36-.77-1.86-.2-.48-.41-.42-.56-.42-.14-.01-.31-.01-.48-.01a.92.92 0 00-.67.31c-.23.25-.87.85-.87 2.08 0 1.23.89 2.42 1.02 2.58.12.17 1.75 2.67 4.24 3.75.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.1-.23-.17-.48-.29z"/></svg>
</a>

<!-- ACCESSIBILITY -->
<button class="fab fab-a11y" onclick="toggleA11yPanel()" aria-label="נגישות">
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="4" r="2"/><path d="M4 8h16M12 8v6M8 22l2-8M16 22l-2-8M8 12l4 2 4-2"/></svg>
</button>
<div id="a11y-panel" class="a11y-panel hidden">
  <button onclick="setA11y('font-lg')">הגדלת טקסט</button>
  <button onclick="setA11y('font-xl')">הגדלת טקסט מקסימלית</button>
  <button onclick="setA11y('contrast')">ניגודיות גבוהה</button>
  <button onclick="setA11y('underline')">קו תחתון לקישורים</button>
  <button onclick="setA11y('reset')">איפוס</button>
</div>

<!-- ADMIN LOGIN MODAL -->
<div id="login-modal" class="modal-backdrop hidden" onclick="if(event.target===this) closeLoginModal()">
  <div class="modal-sheet">
    <div style="font-size:17px; font-weight:800; margin-bottom:16px;">כניסת מנהל</div>
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
    <div id="login-error" style="color:var(--danger); font-size:13px; margin-bottom:10px;"></div>
    <button class="btn btn-primary" onclick="submitLogin()">כניסה</button>
    <button class="btn btn-ghost" style="margin-top:10px;" onclick="closeLoginModal()">ביטול</button>
  </div>
</div>

<script src="assets/theme.js?v=<?= @filemtime(__DIR__ . '/assets/theme.js') ?>"></script>
<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/assets/app.js') ?>"></script>
</body>
</html>
