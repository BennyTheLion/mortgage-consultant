<?php
// Per-tenant marketing content for site.php, stored as one JSON blob in settings.site_content.
// Defaults are deliberately neutral: no statistics, testimonials or credentials are invented
// for a tenant — those sections stay hidden until the tenant fills them in.

function default_site_content() {
    return [
        'hero_badge'       => 'פגישת ייעוץ ראשונית',
        'hero_lead'        => 'ליווי אישי, שקוף ומקצועי לאורך כל התהליך — מהפגישה הראשונה ועד הסיום.',
        'stats'            => [],
        'services_heading' => 'ליווי מקצועי בכל שלב בדרך לבית',
        'services_desc'    => 'מתכנון ראשוני ועד חתימה בבנק — הבדיקה, ההשוואה והמשא ומתן מתבצעים בשבילכם.',
        'service_cards'    => [
            ['title' => 'משכנתא לדירה ראשונה', 'desc' => 'בדיקת זכאות, בניית תמהיל ומו״מ מול הבנקים מההתחלה ועד החתימה.', 'image' => 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=500&q=70'],
            ['title' => 'מיחזור משכנתא', 'desc' => 'בדיקה האם משתלם למחזר כיום, וכמה בדיוק אפשר לחסוך לאורך זמן.', 'image' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?w=500&q=70'],
            ['title' => 'ליווי מול הבנק', 'desc' => 'ליווי בכל שיחה ומסמך, כדי שלא תישארו לבד מול הבנק.', 'image' => 'https://images.unsplash.com/photo-1582407947304-fd86f028f716?w=500&q=70'],
            ['title' => 'משקיעים ונכס שני', 'desc' => 'תכנון מימון לרכישת נכס נוסף, כולל השפעת המשכנתא הקיימת.', 'image' => 'https://images.unsplash.com/photo-1560520653-9e0e4c89eb11?w=500&q=70'],
        ],
        'about_badge_title' => '',
        'about_badge_sub'   => '',
        'credentials'       => [],
        'process'           => [
            ['title' => 'פגישת ייעוץ ראשונית', 'desc' => 'מכירים, ממפים את המצב הכלכלי ואת המטרה — פרונטלית, בזום או בטלפון.'],
            ['title' => 'בדיקת זכאות ותמהיל', 'desc' => 'בונים את תמהיל המשכנתא המתאים ומגישים לבנקים לקבלת הצעות.'],
            ['title' => 'מו״מ מול הבנקים', 'desc' => 'משווים בין ההצעות ומנהלים מו״מ על הריבית והתנאים.'],
            ['title' => 'חתימה וקבלת המפתח', 'desc' => 'ליווי עד לחתימה הסופית בבנק.'],
        ],
        'show_calculator'   => true,
        'testimonials'      => [],
        'faq'               => [
            ['q' => 'איך קובעים פגישה?', 'a' => 'בוחרים שירות, תאריך ושעה פנויה בטופס באתר, ומקבלים אישור מיידי. אין צורך בהרשמה.'],
            ['q' => 'אפשר לשנות או לבטל פגישה?', 'a' => 'כן. בתחתית האתר, בכרטיס "ניהול פגישה קיימת", מזינים את מספר הטלפון שאיתו נקבעה הפגישה ואפשר לעדכן מועד או לבטל.'],
            ['q' => 'איך אפשר ליצור קשר?', 'a' => 'פרטי הקשר מופיעים בסעיף "יצירת קשר" בתחתית האתר.'],
        ],
        'cta_title'         => 'מוכנים לקבוע פגישה?',
        'cta_text'          => 'בלי הרשמה, בלי המתנה בטלפון — בוחרים שירות וזמן פנוי ומקבלים אישור מיידי.',
        'footer_blurb'      => 'ליווי אישי ומקצועי בתהליך המשכנתא, משלב הבדיקה הראשונית ועד החתימה בבנק.',
    ];
}

function _sc_str($v, $max) {
    $v = is_string($v) ? trim($v) : '';
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}

function _sc_image($v) {
    $v = _sc_str($v, 500);
    return preg_match('#^(https://|images/)#i', $v) ? $v : '';
}

function _sc_list($v, $limit, callable $item) {
    $out = [];
    if (!is_array($v)) return $out;
    foreach ($v as $row) {
        if (count($out) >= $limit) break;
        $clean = $item($row);
        if ($clean !== null) $out[] = $clean;
    }
    return $out;
}

// Validates untrusted admin input into the exact stored shape. Unknown keys are dropped.
function sanitize_site_content($in) {
    $in = is_array($in) ? $in : [];
    $d = default_site_content();
    $out = [];
    foreach (['hero_badge' => 120, 'hero_lead' => 400, 'services_heading' => 160, 'services_desc' => 400,
              'about_badge_title' => 120, 'about_badge_sub' => 160, 'cta_title' => 160, 'cta_text' => 400,
              'footer_blurb' => 400] as $k => $max) {
        $out[$k] = array_key_exists($k, $in) ? _sc_str($in[$k], $max) : $d[$k];
    }
    $out['show_calculator'] = array_key_exists('show_calculator', $in) ? (bool) $in['show_calculator'] : true;

    $out['stats'] = _sc_list($in['stats'] ?? [], 3, function ($r) {
        $num = _sc_str($r['num'] ?? '', 20); $label = _sc_str($r['label'] ?? '', 60);
        return ($num === '' || $label === '') ? null : ['num' => $num, 'label' => $label];
    });
    $out['service_cards'] = _sc_list($in['service_cards'] ?? [], 8, function ($r) {
        $title = _sc_str($r['title'] ?? '', 80);
        return $title === '' ? null : ['title' => $title, 'desc' => _sc_str($r['desc'] ?? '', 300), 'image' => _sc_image($r['image'] ?? '')];
    });
    $out['credentials'] = _sc_list($in['credentials'] ?? [], 6, function ($r) {
        $t = _sc_str($r, 80);
        return $t === '' ? null : $t;
    });
    $out['process'] = _sc_list($in['process'] ?? [], 6, function ($r) {
        $title = _sc_str($r['title'] ?? '', 80);
        return $title === '' ? null : ['title' => $title, 'desc' => _sc_str($r['desc'] ?? '', 300)];
    });
    $out['testimonials'] = _sc_list($in['testimonials'] ?? [], 10, function ($r) {
        $quote = _sc_str($r['quote'] ?? '', 500); $name = _sc_str($r['name'] ?? '', 60);
        return ($quote === '' || $name === '') ? null : ['quote' => $quote, 'name' => $name, 'meta' => _sc_str($r['meta'] ?? '', 60)];
    });
    $out['faq'] = _sc_list($in['faq'] ?? [], 15, function ($r) {
        $q = _sc_str($r['q'] ?? '', 200); $a = _sc_str($r['a'] ?? '', 800);
        return ($q === '' || $a === '') ? null : ['q' => $q, 'a' => $a];
    });
    return $out;
}

// Saved content overrides defaults key-by-key, so a tenant who empties a list really gets an
// empty (hidden) section, while tenants that never saved anything get the defaults.
function get_site_content($pdo, $tenantId) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'site_content'");
    $stmt->execute([$tenantId]);
    $saved = json_decode((string) $stmt->fetchColumn(), true);
    $content = default_site_content();
    if (is_array($saved)) {
        foreach ($saved as $k => $v) {
            if (array_key_exists($k, $content)) $content[$k] = $v;
        }
    }
    return $content;
}

// Public, non-secret business facts for one tenant — the single source for the JSON-LD block
// and llms.txt, so what agents read always matches what the site shows.
function public_profile($pdo, $tenantId) {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE tenant_id = ? AND setting_key IN
        ('owner_name','tagline','about_text','phone','whatsapp_phone','email','address','instagram_url','facebook_url','tiktok_url','working_hours')");
    $stmt->execute([$tenantId]);
    $p = [];
    foreach ($stmt->fetchAll() as $r) $p[$r['setting_key']] = trim((string) $r['setting_value']);
    $p['working_hours'] = json_decode($p['working_hours'] ?? '', true) ?: [];
    $s = $pdo->prepare('SELECT name, duration_minutes, price FROM services WHERE tenant_id = ? AND active = 1 ORDER BY sort_order, id');
    $s->execute([$tenantId]);
    $p['services'] = $s->fetchAll();
    return $p;
}

function site_base_url() {
    if (empty($_SERVER['HTTP_HOST'])) return '';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(str_replace(chr(92), '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
}

function tenant_jsonld($tenant, $p, $siteUrl) {
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $ld = ['@context' => 'https://schema.org', '@type' => 'ProfessionalService', 'name' => $tenant['name']];
    if ($siteUrl !== '') $ld['url'] = $siteUrl;
    if (($p['tagline'] ?? '') !== '') $ld['description'] = $p['tagline'];
    if (($p['phone'] ?? '') !== '') $ld['telephone'] = $p['phone'];
    if (($p['email'] ?? '') !== '') $ld['email'] = $p['email'];
    if (($p['address'] ?? '') !== '') $ld['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $p['address']];
    $same = array_values(array_filter([$p['instagram_url'] ?? '', $p['facebook_url'] ?? '', $p['tiktok_url'] ?? ''], function ($u) { return preg_match('#^https?://#i', $u); }));
    if ($same) $ld['sameAs'] = $same;
    $hours = [];
    foreach ($p['working_hours'] as $i => $d) {
        if (!isset($days[(int) $i]) || !empty($d['closed']) || empty($d['open']) || empty($d['close'])) continue;
        $hours[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $days[(int) $i], 'opens' => $d['open'], 'closes' => $d['close']];
    }
    if ($hours) $ld['openingHoursSpecification'] = $hours;
    if ($p['services']) {
        $ld['makesOffer'] = array_map(function ($s) {
            return ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $s['name']]];
        }, $p['services']);
    }
    return json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}
