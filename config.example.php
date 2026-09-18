<?php
// Copy this file to config.php and fill in real values. config.php is gitignored because
// it holds real secrets (DB password, VAPID private key) — never commit it.

// A distinct session name (not the default PHPSESSID) keeps this app's login session
// from colliding with any other PHP app running on the same localhost/cookie domain.
if (session_status() === PHP_SESSION_NONE) {
    session_name('bp_session');
    session_start();
}

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

function _json_error_response($message) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'שגיאת שרת: ' . $message], JSON_UNESCAPED_UNICODE);
}
set_exception_handler(function ($e) {
    _json_error_response($e->getMessage());
});
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        _json_error_response($error['message']);
    }
});

define('DB_HOST', 'localhost');
define('DB_NAME', 'booking_platform');
define('DB_USER', 'root');
define('DB_PASS', ''); // default XAMPP mysql root has no password

// Media lives per-tenant under images/{tenant_id}/ and videos/{tenant_id}/ so two
// businesses can never collide on filenames or see each other's uploads.
define('IMAGES_BASE_DIR', __DIR__ . '/images/');
define('VIDEOS_BASE_DIR', __DIR__ . '/videos/');
define('IMAGES_BASE_URL', 'images/');
define('VIDEOS_BASE_URL', 'videos/');

function images_dir($tenantId) { return IMAGES_BASE_DIR . $tenantId . '/'; }
function videos_dir($tenantId) { return VIDEOS_BASE_DIR . $tenantId . '/'; }
function images_url($tenantId) { return IMAGES_BASE_URL . $tenantId . '/'; }
function videos_url($tenantId) { return VIDEOS_BASE_URL . $tenantId . '/'; }

define('MAX_IMAGES', 20);
define('MAX_VIDEOS', 5);

define('MAX_IMAGE_UPLOAD_BYTES', 15 * 1024 * 1024); // 15MB raw upload before resizing
define('MAX_IMAGE_WIDTH', 1600);                     // resized down to this max width
define('IMAGE_JPEG_QUALITY', 82);

define('MAX_VIDEO_UPLOAD_BYTES', 60 * 1024 * 1024); // 60MB per video
define('ALLOWED_VIDEO_EXT', ['mp4', 'webm', 'mov']);

// ── Web push (browser notifications to a tenant's admin on new/changed bookings) ──────────
// Generate a real pair once via `php bin/gen_vapid.php` and paste the results here.
// Regenerating these invalidates every device that already enabled push.
define('VAPID_PUBLIC_KEY', '');
define('VAPID_PRIVATE_KEY', '');

// XAMPP's PHP on Windows can't find OpenSSL's config file at runtime (needed to generate the
// one-time-use encryption key each push send requires), which makes openssl_pkey_new() fail
// silently. Point it at whichever bundled openssl.cnf actually exists on this machine.
// (Not needed on most Linux hosting — openssl_pkey_new() works there without this.)
define('OPENSSL_CNF_PATH', (function () {
    foreach ([
        'C:/xampp/php/extras/openssl/openssl.cnf',
        'C:/xampp/php/extras/ssl/openssl.cnf',
        'C:/xampp/apache/conf/openssl.cnf',
    ] as $path) {
        if (is_file($path)) return $path;
    }
    return null;
})());

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['error' => 'שגיאת חיבור למסד הנתונים. ודאו שהרצתם את database.sql וש-config.php תואם ל-XAMPP שלכם.']));
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function body_json() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/* ── Auth / roles ────────────────────────────────────────────────────────
   Session shape after login: admin_id, role ('super_admin'|'admin'), tenant_id
   (NULL for super_admin, required for a tenant admin). */

function require_admin() {
    if (empty($_SESSION['admin_id'])) {
        json_out(['error' => 'unauthorized'], 401);
    }
}

// For endpoints only a tenant's own admin may call. Also scopes writes: callers
// should use session_tenant_id(), never a tenant_id/slug supplied by the client,
// so a logged-in admin can only ever touch their own tenant's data.
function require_tenant_admin() {
    require_admin();
    if (empty($_SESSION['tenant_id'])) {
        json_out(['error' => 'forbidden'], 403);
    }
}

function require_super_admin() {
    require_admin();
    if (($_SESSION['role'] ?? '') !== 'super_admin') {
        json_out(['error' => 'forbidden'], 403);
    }
}

function session_tenant_id() {
    return isset($_SESSION['tenant_id']) ? (int) $_SESSION['tenant_id'] : null;
}

// Resolves a public ?slug= param to an active tenant row, or ends the request
// with a clear error. Used by every unauthenticated (public-site) endpoint.
function resolve_public_tenant($pdo) {
    $slug = isset($_GET['slug']) ? $_GET['slug'] : (isset($_POST['slug']) ? $_POST['slug'] : null);
    if (!$slug) {
        $input = body_json();
        $slug = $input['slug'] ?? null;
    }
    if (!$slug) json_out(['error' => 'חסר מזהה אתר (slug)'], 400);
    $stmt = $pdo->prepare('SELECT * FROM tenants WHERE slug = ?');
    $stmt->execute([$slug]);
    $tenant = $stmt->fetch();
    if (!$tenant) json_out(['error' => 'האתר המבוקש לא נמצא'], 404);
    if ($tenant['status'] !== 'active') json_out(['error' => 'האתר אינו זמין כעת'], 403);
    return $tenant;
}
