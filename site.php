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

require_once __DIR__ . '/includes/site_content.php';
$c = get_site_content($pdo, $tenantId);
$aboutStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'about_text'");
$aboutStmt->execute([$tenantId]);
$hasAbout = trim((string) $aboutStmt->fetchColumn()) !== '';
$hasPortrait = is_file(__DIR__ . '/images/' . $tenantId . '/advisor-portrait.jpg');
$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$cardIcons = [
    '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/>',
    '<path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/>',
    '<path d="M12 5v14"/><path d="M5 12h14"/>',
    '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/>',
];
$showAbout = $hasAbout || $hasPortrait || $c['about_badge_title'] !== '' || $c['credentials'];
$showServices = (bool) $c['service_cards'];
$showProcess = (bool) $c['process'];
$showCalc = !empty($c['show_calculator']);
$showTestimonials = (bool) $c['testimonials'];
$showFaq = (bool) $c['faq'];
$profile = public_profile($pdo, $tenantId);
$baseUrl = site_base_url();
$canonicalUrl = $baseUrl !== '' ? $baseUrl . 'site/' . rawurlencode($slug) . '/' : '';
$jsonLd = tenant_jsonld($tenant, $profile, $canonicalUrl);
$metaDesc = trim($profile['tagline'] ?? '') !== '' ? $profile['tagline'] : $tenant['name'] . ' — קביעת פגישת ייעוץ אונליין';
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
<meta name="description" content="<?= $e($metaDesc) ?>">
<?php if ($canonicalUrl): ?><link rel="canonical" href="<?= $e($canonicalUrl) ?>"><?php endif; ?>
<link rel="alternate" type="text/plain" href="site/<?= $e(rawurlencode($slug)) ?>/llms.txt" title="llms.txt">
<script type="application/ld+json"><?= $jsonLd ?></script>
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
    <?php if ($showServices): ?><li><a href="#services">שירותים</a></li><?php endif; ?>
    <?php if ($showAbout): ?><li><a href="#about">אודות</a></li><?php endif; ?>
    <?php if ($showCalc): ?><li><a href="#calculator">מחשבון</a></li><?php endif; ?>
    <?php if ($showTestimonials): ?><li><a href="#testimonials">המלצות</a></li><?php endif; ?>
    <?php if ($showFaq): ?><li><a href="#faq">שאלות נפוצות</a></li><?php endif; ?>
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
        <?php if ($c['hero_badge'] !== ''): ?><span class="hero-badge">✓ <?= $e($c['hero_badge']) ?></span><?php endif; ?>
        <h1 id="hero-title"><span id="owner-name"><?= htmlspecialchars($tenant['name']) ?></span></h1>
        <div class="tagline" id="owner-tagline"></div>
        <?php if ($c['hero_lead'] !== ''): ?><p class="lead"><?= $e($c['hero_lead']) ?></p><?php endif; ?>
        <div class="hero-actions">
          <button id="hero-cta" class="btn btn-primary hero-shake" onclick="revealBooking()">קביעת פגישה</button>
          <?php if ($showCalc): ?><a href="#calculator" class="btn btn-ghost" style="border-color:rgba(255,255,255,.4); color:#fff;">חישוב משכנתא</a><?php endif; ?>
        </div>
        <?php if ($c['stats']): ?>
        <div class="hero-stats" style="grid-template-columns:repeat(<?= count($c['stats']) ?>,1fr);">
          <?php foreach ($c['stats'] as $st): ?>
          <div><div class="stat-num"><?= $e($st['num']) ?></div><div class="stat-label"><?= $e($st['label']) ?></div></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
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
              <label for="service-list" class="section-title" style="display:block;">סוג הפגישה</label>
              <select id="service-list" name="service" aria-required="true"></select>
            </div>

            <div class="field">
              <label for="date-input">תאריך</label>
              <input type="date" id="date-input" name="date" aria-required="true">
            </div>

            <div style="margin-bottom:18px;">
              <div class="section-title" id="slot-list-title">שעות פנויות</div>
              <div id="slot-list" role="group" aria-labelledby="slot-list-title" aria-live="polite" style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:8px;"></div>
            </div>

            <div class="field"><label for="cust-name">שם מלא</label><input type="text" id="cust-name" name="name" autocomplete="name" aria-required="true" placeholder="השם שלך"></div>
            <div class="field"><label for="cust-phone">טלפון</label><input type="tel" id="cust-phone" name="phone" autocomplete="tel" aria-required="true" placeholder="050-0000000"></div>
            <div class="field"><label for="cust-email">אימייל (לקבלת אישור, אופציונלי)</label><input type="email" id="cust-email" name="email" autocomplete="email" placeholder="you@example.com"></div>

            <button id="confirm-btn" type="button" class="btn btn-primary" disabled onclick="confirmBooking()">אישור הפגישה</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="wrap">

  <!-- SERVICES -->
  <?php if ($showServices): ?>
  <div class="observe section" id="services">
    <div class="section-head">
      <span class="section-tag">השירותים</span>
      <?php if ($c['services_heading'] !== ''): ?><h2 class="section-heading"><?= $e($c['services_heading']) ?></h2><?php endif; ?>
      <?php if ($c['services_desc'] !== ''): ?><p class="section-desc"><?= $e($c['services_desc']) ?></p><?php endif; ?>
    </div>
    <div class="service-grid">
      <?php foreach ($c['service_cards'] as $i => $card): ?>
      <div class="service-card">
        <div class="service-image">
          <?php if ($card['image'] !== ''): ?><img src="<?= $e($card['image']) ?>" alt="" loading="lazy"><?php endif; ?>
          <div class="service-icon-badge"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $cardIcons[$i % count($cardIcons)] ?></svg></div>
        </div>
        <div class="service-body">
          <div class="title"><?= $e($card['title']) ?></div>
          <?php if ($card['desc'] !== ''): ?><div class="meta"><?= $e($card['desc']) ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ABOUT -->
  <?php if ($showAbout): ?>
  <div class="observe section" id="about">
    <div class="about-flex">
      <div class="about-image-wrap">
        <img src="images/<?= $tenantId ?>/advisor-portrait.jpg?v=<?= @filemtime(__DIR__ . '/images/' . $tenantId . '/advisor-portrait.jpg') ?>" alt="" onerror="this.closest('.about-image-wrap').style.display='none'">
        <?php if ($c['about_badge_title'] !== ''): ?>
        <div class="about-badge">
          <div class="about-badge-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/></svg></div>
          <div><strong><?= $e($c['about_badge_title']) ?></strong><?php if ($c['about_badge_sub'] !== ''): ?><span><?= $e($c['about_badge_sub']) ?></span><?php endif; ?></div>
        </div>
        <?php endif; ?>
      </div>
      <div>
        <span class="section-tag">אודות</span>
        <h2 class="section-heading">מי מלווה אתכם בתהליך</h2>
        <div id="about-text" style="font-size:15px; line-height:1.8; color:var(--gray); margin-bottom:18px;"></div>
        <?php if ($c['credentials']): ?>
        <div class="about-credentials" style="margin-bottom:24px;">
          <?php foreach ($c['credentials'] as $cred): ?><span class="credential-pill"><?= $e($cred) ?></span><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <button class="btn btn-primary" style="width:auto;" onclick="revealBooking()">בואו נדבר</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- PROCESS -->
  <?php if ($showProcess): ?>
  <div class="observe section">
    <div class="section-head">
      <span class="section-tag">התהליך</span>
      <h2 class="section-heading">איך זה עובד</h2>
    </div>
    <div class="process-list">
      <?php foreach ($c['process'] as $i => $step): ?>
      <div class="process-step">
        <div class="process-num"><?= $i + 1 ?></div>
        <div class="process-body"><div class="title"><?= $e($step['title']) ?></div><?php if ($step['desc'] !== ''): ?><div class="desc"><?= $e($step['desc']) ?></div><?php endif; ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- CALCULATOR -->
  <?php if ($showCalc): ?>
  <div class="observe section" id="calculator">
    <div class="section-head">
      <span class="section-tag">מחשבון משכנתא</span>
      <h2 class="section-heading">כמה תשלמו בחודש?</h2>
      <p class="section-desc">הזיזו את הסליידרים וקבלו אומדן מיידי. רוצים תמהיל מדויק? קבעו פגישה.</p>
    </div>
    <div class="calc-wrap">
      <div class="calc-form">
        <div class="field">
          <label for="loanAmount">סכום המשכנתא</label>
          <div class="range-input">
            <input type="range" id="loanAmount" min="200000" max="3000000" step="50000" value="1000000">
            <span class="range-value" id="loanAmountVal">₪1,000,000</span>
          </div>
        </div>
        <div class="field">
          <label for="equity">הון עצמי</label>
          <div class="range-input">
            <input type="range" id="equity" min="0" max="1500000" step="25000" value="300000">
            <span class="range-value" id="equityVal">₪300,000</span>
          </div>
        </div>
        <div class="calc-row">
          <div class="field">
            <label for="years">תקופה (שנים)</label>
            <select id="years">
              <option value="10">10</option>
              <option value="15">15</option>
              <option value="20">20</option>
              <option value="25" selected>25</option>
              <option value="30">30</option>
            </select>
          </div>
          <div class="field">
            <label for="rate">ריבית שנתית (%)</label>
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
  <?php endif; ?>

  <!-- TESTIMONIALS -->
  <?php if ($showTestimonials): ?>
  <div class="observe section" id="testimonials">
    <div class="section-head">
      <span class="section-tag">לקוחות מספרים</span>
      <h2 class="section-heading">מה אומרים עלינו</h2>
    </div>
    <div class="testimonial-scroll">
      <?php foreach ($c['testimonials'] as $t): ?>
      <div class="testimonial-card">
        <div class="stars">★★★★★</div>
        <div class="quote"><?= $e($t['quote']) ?></div>
        <div class="author">
          <span class="author-avatar" aria-hidden="true"><?= $e(function_exists('mb_substr') ? mb_substr($t['name'], 0, 1) : substr($t['name'], 0, 1)) ?></span>
          <div><div class="author-name"><?= $e($t['name']) ?></div><?php if ($t['meta'] !== ''): ?><div class="author-meta"><?= $e($t['meta']) ?></div><?php endif; ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- FAQ -->
  <?php if ($showFaq): ?>
  <div class="observe card section" id="faq" style="padding:20px 20px;">
    <div class="section-title">שאלות נפוצות</div>
    <?php foreach ($c['faq'] as $item): ?>
    <details class="faq-item">
      <summary><?= $e($item['q']) ?>
        <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
      </summary>
      <div class="faq-answer"><?= $e($item['a']) ?></div>
    </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

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
            <div class="field" style="margin-bottom:0; flex:1;"><label for="manage-phone">טלפון</label><input type="tel" id="manage-phone" name="phone" autocomplete="tel" placeholder="050-0000000"></div>
            <button type="button" class="btn btn-ghost btn-auto" onclick="lookupMyBookings()">חיפוש</button>
          </div>
          <div id="manage-list" aria-live="polite" style="margin-top:16px;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- CTA -->
  <div class="observe section">
    <div class="cta-banner">
      <?php if ($c['cta_title'] !== ''): ?><h2><?= $e($c['cta_title']) ?></h2><?php endif; ?>
      <?php if ($c['cta_text'] !== ''): ?><p><?= $e($c['cta_text']) ?></p><?php endif; ?>
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
      <?php if ($c['footer_blurb'] !== ''): ?><p><?= $e($c['footer_blurb']) ?></p><?php endif; ?>
    </div>
    <div>
      <h4>ניווט</h4>
      <ul>
        <?php if ($showServices): ?><li><a href="#services">שירותים</a></li><?php endif; ?>
        <?php if ($showAbout): ?><li><a href="#about">אודות</a></li><?php endif; ?>
        <?php if ($showCalc): ?><li><a href="#calculator">מחשבון</a></li><?php endif; ?>
        <?php if ($showTestimonials): ?><li><a href="#testimonials">המלצות</a></li><?php endif; ?>
        <li><a href="#contact">צור קשר</a></li>
      </ul>
    </div>
    <?php if ($showServices): ?>
    <div>
      <h4>שירותים</h4>
      <ul>
        <?php foreach ($c['service_cards'] as $card): ?><li><a href="#services"><?= $e($card['title']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
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
    <div class="field"><label for="login-username">שם משתמש</label><input type="text" id="login-username" autocomplete="username"></div>
    <div class="field">
      <label for="login-password">סיסמה</label>
      <div class="password-wrap">
        <input type="password" id="login-password" autocomplete="current-password">
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
