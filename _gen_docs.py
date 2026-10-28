# -*- coding: utf-8 -*-
"""
Drop IT documentation generator (v2).
Writes:
  - "drop it docu and info.docx"  — Drop IT-specific PHP walkthrough (framework code excluded).
  - "DropIT SQL.docx"             — Every SQL query + joins + full CREATE TABLE + ERD + use-case.
Output: C:\\Users\\LENOVO\\OneDrive\\Documents\\Jolly roy's files\\proj webdev\\
"""

from pathlib import Path
from docx import Document
from docx.shared import Pt, RGBColor, Inches
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

OUT_DIR = Path(r"C:\Users\LENOVO\OneDrive\Documents\Jolly roy's files\proj webdev")
ERD_PNG = Path(r"C:\Users\LENOVO\OneDrive\Documents\WebDev\_docs_tmp\erd.png")
UC_PNG  = Path(r"C:\Users\LENOVO\OneDrive\Documents\WebDev\_docs_tmp\usecase.png")

# ---------- helpers ----------
def set_cell_shading(cell, color_hex):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement('w:shd'); shd.set(qn('w:fill'), color_hex); tc_pr.append(shd)

def heading(doc, text, level=1):
    p = doc.add_heading(text, level=level)
    for r in p.runs: r.font.color.rgb = RGBColor(0x0B, 0x1E, 0x3F)
    return p

def para(doc, text, bold=False, italic=False, size=11, color=None):
    p = doc.add_paragraph(); r = p.add_run(text)
    r.bold = bold; r.italic = italic; r.font.size = Pt(size); r.font.name='Calibri'
    if color: r.font.color.rgb = RGBColor(*color)
    return p

def label_value(doc, label, value):
    p = doc.add_paragraph()
    r1 = p.add_run(label + ": "); r1.bold=True; r1.font.size=Pt(10)
    r1.font.color.rgb = RGBColor(0x12,0x30,0x6B)
    r2 = p.add_run(value); r2.font.size = Pt(10)
    return p

def code_block(doc, code):
    tbl = doc.add_table(rows=1, cols=1); tbl.autofit=False
    tbl.columns[0].width = Inches(6.5)
    cell = tbl.cell(0,0); cell.width=Inches(6.5)
    set_cell_shading(cell, 'F3F6FB')
    cell.paragraphs[0].paragraph_format.space_after = Pt(0)
    cell.paragraphs[0].paragraph_format.space_before = Pt(0)
    first = True
    for line in code.splitlines():
        p = cell.paragraphs[0] if first else cell.add_paragraph()
        first = False
        p.paragraph_format.space_after = Pt(0)
        p.paragraph_format.space_before = Pt(0)
        r = p.add_run(line if line else ' ')
        r.font.name='Consolas'; r.font.size=Pt(9)
        r.font.color.rgb = RGBColor(0x10,0x20,0x3A)
    doc.add_paragraph()

def file_section(doc, path, purpose, code):
    heading(doc, path, level=3)
    label_value(doc, "Purpose", purpose)
    para(doc, "Code (logic only — UI markup trimmed):", italic=True, size=10, color=(0x3B,0x5A,0x8A))
    code_block(doc, code)

def section_title(doc, title):
    doc.add_page_break(); heading(doc, title, level=1)

def sql_block(doc, title, desc, sql):
    heading(doc, title, level=3); para(doc, desc); code_block(doc, sql)


# =====================================================
#  DOC 1 — "drop it docu and info.docx"
# =====================================================
def build_main_doc():
    doc = Document()
    for s in doc.sections:
        s.left_margin=s.right_margin=s.top_margin=s.bottom_margin = Inches(0.7)

    t = doc.add_heading("Drop IT — Code & Feature Documentation", level=0)
    t.alignment = WD_ALIGN_PARAGRAPH.CENTER

    para(doc, "Parcel delivery web app (J&T / Lalamove-style). Three roles (customer, "
              "driver, admin) plus a public guest tracker. Email-only notifications "
              "(no SMS). Built on top of the LavaLite PHP micro-framework — only "
              "Drop IT's own code is documented here; framework internals are not.",
         italic=True)
    para(doc, "")
    label_value(doc, "Project root", r"C:\Users\LENOVO\OneDrive\Documents\WebDev")
    label_value(doc, "Database", "MariaDB/MySQL @ XAMPP — db `dropit`")
    label_value(doc, "Admin login", "admin@drop-it.dev / DropITJACS")
    label_value(doc, "Fare model", "Tricycle: ₱30 base + ₱3/km (min ₱30). Split 90% driver / 10% platform.")
    label_value(doc, "Email", "SMTP AUTH LOGIN via STARTTLS (Brevo by default). No SMS.")
    label_value(doc, "Run local", "php -S localhost:8080 index.php")

    _entry_and_config(doc)
    _drop_it_helpers_section(doc)
    _database_section(doc)
    _middlewares_section(doc)
    _actions_section(doc)
    _api_section(doc)
    _admin_views_section(doc)
    _customer_views_section(doc)
    _driver_views_section(doc)
    _partials_section(doc)
    _entry_views_section(doc)
    _flow_section(doc)

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    path = OUT_DIR / "drop it docu and info.docx"
    doc.save(str(path))
    return path


def _entry_and_config(doc):
    section_title(doc, "1. Entry Point & Configuration")

    file_section(doc, "index.php",
        "App bootstrap. Sets security headers, loads config + Drop IT services "
        "(Notifier, PayMongo, helpers), then dispatches via the Router.",
"""if (php_sapi_name() === 'cli-server') {
    $_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($_uri !== '/' && is_file(__DIR__ . $_uri)) return false;  // serve static files
}
define('APP_ROOT', __DIR__);

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header_remove('X-Powered-By');

require_once 'config.php';
require_once __DIR__ . '/scheme/Database.php';   // framework — not documented here
require_once __DIR__ . '/scheme/helpers.php';
require_once __DIR__ . '/scheme/Notifier.php';
require_once __DIR__ . '/scheme/PayMongo.php';
require_once __DIR__ . '/scheme/Router.php';     // framework — not documented here

$router = new Router();
require_once  __DIR__ . '/routes.php';
$router->run();""")

    file_section(doc, "config.php",
        "Dev flag, app branding, and MySQL connection constants (XAMPP defaults).",
"""define('IS_DEV', true);
define('APP_NAME', 'Drop IT');
define('APP_TAGLINE', 'Your parcel, our priority.');
define('DB_DRIVER', 'mysql');
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'dropit');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_CHARSET', 'utf8mb4');""")

    file_section(doc, "routes.php",
        "Every URL → handler mapping. Groups customer/driver/admin routes with "
        "their middleware. New since last rev: public /track, /customer/cash-in, "
        "/driver/transit-photo/{id}, /admin/inbox.",
"""// Public
$router->get('/', 'app/views/landing');
$router->get('/track',            'app/views/public_track');
$router->get('/track/{tracking}', 'app/views/public_track');
$router->get('/login',  'app/views/login');
$router->post('/login', 'app/views/actions/login_action');
$router->post('/logout','app/views/actions/logout', ['auth']);

// Signup + email OTP (SMS gone)
$router->get('/signup',           'app/views/signup/choose');
$router->get('/signup/customer',  fn()=>include APP_ROOT.'/app/views/signup/form.php');
$router->post('/signup/customer', fn()=>include APP_ROOT.'/app/views/actions/signup_submit.php');
$router->post('/signup/otp/send',   'app/views/actions/otp_send');
$router->post('/signup/otp/verify', 'app/views/actions/otp_verify');

// Customer group (middleware=customer)
$router->group(['prefix'=>'customer','middleware'=>['customer']], function($r){
    $r->get('/dashboard',...);  $r->post('/order', 'app/views/actions/customer_order');
    $r->get('/cash-in',...);    $r->post('/cash-in', 'app/views/actions/customer_cash_in');
    $r->get('/cash-in/return',  'app/views/actions/customer_cash_in_return');
    $r->post('/rate/{id}',      'app/views/actions/rate_submit');
    /* ...12 more routes... */
});

// Driver group
$router->group(['prefix'=>'driver','middleware'=>['driver']], function($r){
    $r->get('/dashboard',     'app/views/driver/dashboard');
    $r->post('/accept/{id}',  'app/views/actions/driver_accept');
    $r->post('/update/{id}',  'app/views/actions/driver_update_status');
    $r->post('/pod/{id}',     'app/views/actions/pod_submit');
    $r->post('/transit-photo/{id}', 'app/views/actions/transit_photo');
    /* quick-accept, cod, messages, analytics, parcel_detail ... */
});

// Admin group
$router->group(['prefix'=>'admin','middleware'=>['admin']], function($r){
    $r->get('/dashboard','app/views/admin/dashboard');
    $r->get('/inbox',    'app/views/admin/inbox');  // NEW — categorized notifications
    /* accounts/walkin/assign/parcel/drivers-map/analytics/reports/settings/notifications... */
});

// Payment + JSON API
$router->post('/pay/gcash/create/{id}', 'app/views/actions/pay_gcash_create', ['customer']);
$router->get('/pay/gcash/return/{id}',  'app/views/actions/pay_gcash_return',  ['customer']);
$router->post('/api/driver/location',   'app/views/api/driver_location', ['driver']);
$router->get('/api/parcel/{tracking}/track', 'app/views/api/parcel_tracking', ['auth']);
// + messages, notifications_unread, messages_unread, theme_toggle, sos, availability""")


def _drop_it_helpers_section(doc):
    section_title(doc, "2. Drop IT Services — /scheme")
    para(doc, "Framework files (Router.php, Database.php) are LavaLite — not covered "
              "here. The files below are Drop IT-specific.", italic=True)

    file_section(doc, "scheme/helpers.php",
        "Drop IT-specific helpers — fare math, ETA, token signing, rating rollup, "
        "settings cache, and the new sweep_stale_dispatches() auto-recycler. "
        "(LavaLite-origin helpers like base_url, url, esc, segment are inherited "
        "from the framework and not re-documented.)",
"""// Great-circle distance (km) — feeds compute_fare_php.
function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1); $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)**2;
    return 2 * $r * asin(min(1.0, sqrt($a)));
}

// Tricycle pricing; 10/90 split.
function compute_fare_php(float $distance_km, string $vehicle='Tricycle'): array {
    $base=30.00; $per_km=3.00;
    $cost  = max(round($base + $per_km * max(0,$distance_km), 2), $base);
    $admin = round($cost * 0.10, 2);
    return [
        'distance_km'     => round($distance_km, 3),
        'amount'          => $cost,
        'admin_fee'       => $admin,
        'driver_earnings' => round($cost - $admin, 2),
        'base'            => $base, 'per_km' => $per_km,
    ];
}

// ETA using tricycle average 22 km/h.
function compute_eta_minutes(?float $drvLat, ?float $drvLng, ?float $dLat, ?float $dLng,
                              float $avgKmh=22.0): ?int {
    if ($drvLat===null || $dLat===null) return null;
    $km = haversine_km($drvLat, $drvLng, $dLat, $dLng);
    return $km <= 0 ? 1 : max(1, (int)ceil(($km / $avgKmh) * 60));
}

// Settings cache — 1 query per request.
function setting(string $key, $default=null) {
    static $cache = null;
    if ($cache === null) {
        $rows = db()->table('settings')->select('setting_key, setting_value')->get_all();
        $cache = [];
        foreach ($rows as $r) $cache[$r['setting_key']] = $r['setting_value'];
    }
    return $cache[$key] ?? $default;
}
function set_setting(string $key, $value): void {
    $existing = db()->table('settings')->select('setting_key')->where('setting_key',$key)->get();
    if ($existing) db()->table('settings')->where('setting_key',$key)->update(['setting_value'=>$value]);
    else           db()->table('settings')->insert(['setting_key'=>$key,'setting_value'=>$value]);
}

// Signed tokens for 1-click driver-accept emails.
function sign_token(string $data): string {
    $secret = (string) setting('app_secret', 'drop-it-fallback-secret');
    return substr(hash_hmac('sha256', $data, $secret), 0, 20);
}
function verify_token(string $data, string $token): bool {
    return hash_equals(sign_token($data), $token);
}

// Rating roll-up — called after rate_submit inserts a row.
function refresh_driver_rating(int $driver_id): void {
    $row = db()->raw("SELECT AVG(stars) a, COUNT(*) c FROM ratings WHERE driver_id = ?",
                     [$driver_id])->fetch();
    db()->table('users')->where('id', $driver_id)->update([
        'rating_avg'   => round((float)($row['a'] ?? 0), 2),
        'rating_count' => (int)($row['c'] ?? 0),
    ]);
}

function peso(float $n): string { return '₱' . number_format($n, 2); }

// NEW: auto-revert Dispatched parcels sitting idle ≥ N min → Pending.
// Called lazily from driver/dashboard.php — no cron needed.
function sweep_stale_dispatches(int $minutes=15): int {
    $stale = db()->raw(
        "SELECT id, tracking_number, driver_id FROM parcels
          WHERE status='Dispatched' AND updated_at < (NOW() - INTERVAL ? MINUTE)",
        [$minutes]
    )->fetchAll();
    if (!$stale) return 0;
    foreach ($stale as $p) {
        db()->table('parcels')->where('id',$p['id'])->update(['driver_id'=>null,'status'=>'Pending']);
        db()->table('parcel_history')->insert([
            'parcel_id'=>$p['id'], 'status'=>'Pending',
            'notes'=>"Auto-revert: no driver response within {$minutes} min. Returned to dispatch queue.",
            'updated_by'=>null,
        ]);
    }
    // One consolidated ping to every admin.
    $admins = db()->table('users')->select('id')->where('role','admin')->where('is_active',1)->get_all();
    foreach ($admins as $a) {
        Notifier::in_app((int)$a['id'], "⏰ Dispatch timeout",
            count($stale)." parcel(s) returned to the queue (no driver reply in {$minutes} min).", null);
    }
    return count($stale);
}""")

    file_section(doc, "scheme/Notifier.php",
        "Notification dispatcher — email (via custom SMTP AUTH LOGIN + STARTTLS, "
        "Brevo-compatible) + in-app. SMS removed; phone is kept purely for "
        "display-formatting. HTML emails wrap the plain body with an inline logo "
        "template. Each high-level helper logs every attempt to notification_log.",
"""// Phone normalization — for UI display only (no SMS).
public static function normalize_phone_ph(string $raw): string { /* 09XX → 639XX... */ }
public static function display_phone(string $raw): string { /* '+639...' */ }

// SMS removed — the stub exists only so older callers don't crash.
public static function sms(string $phone, string $message): string { return 'disabled'; }

public static function in_app(int $user_id, string $title, string $message, ?int $parcel_id=null): void {
    db()->table('notifications')->insert([
        'user_id'=>$user_id, 'parcel_id'=>$parcel_id, 'title'=>$title, 'message'=>$message,
    ]);
    self::log('in_app', (string)$user_id, $title, $message, 'sent', null);
}

public static function email(string $to, string $subject, string $body): string {
    if ($to === '') return 'no_recipient';
    $host=setting('smtp_host',''); $port=(int)setting('smtp_port',587);
    $user=setting('smtp_user',''); $pass=setting('smtp_pass','');
    $from=setting('smtp_from','Drop IT <no-reply@dropit.app>');
    if ($host==='' || $user==='' || $pass==='') {
        self::log('email', $to, $subject, $body, 'disabled', 'SMTP not configured');
        return 'disabled';
    }
    $html = self::render_email_html($subject, $body);
    $logo = self::load_inline_logo();
    [$ok, $note] = self::smtp_send($host, $port, $user, $pass, $from, $to, $subject, $body, $html, $logo);
    self::log('email', $to, $subject, $body, $ok?'sent':'failed', $note);
    return $ok ? 'sent' : 'failed';
}

// smtp_send() — custom implementation: tcp socket → EHLO → STARTTLS → AUTH LOGIN
// → MAIL FROM / RCPT TO / DATA. Supports multipart/related with inline CID image
// (so the Drop IT logo renders inline in every email client).
// render_email_html() wraps the plain body with a branded HTML template.

// --- high-level helpers ---
public static function notify_admin_new_order(array $parcel, array $customer): void { /* email admins + in-app */ }
public static function notify_driver_assigned(array $driver, array $parcel): void {
    // in-app + email with 1-click accept/decline links:
    $accept_link = url("driver/quick-accept/{$parcel['id']}?t={$parcel['accept_token']}");
    /* ... self::email($driver['email'], ...) */
}
public static function notify_driver_message(array $driver, array $parcel, string $from, string $body): void {
    // in-app only — keeps message threads out of the inbox spam folder.
}
public static function notify_customer_status(array $customer, array $parcel, string $status, string $message): void {
    self::in_app($customer['id'], "Parcel {$status}", $message, $parcel['id']);
    if (!empty($customer['email'])) self::email($customer['email'], "[Drop IT] {$parcel['tracking_number']} — {$status}", ...);
}
public static function notify_sos(int $driver_id, array $driver, ?array $parcel, ?float $lat, ?float $lng): void {
    // in-app + email every admin; body has an OSM map link to the driver's lat/lng.
}
// NEW:
public static function notify_admin_new_report(array $reporter, array $report): void {
    // Called from report_submit — emails every admin with the feedback content.
}
public static function notify_email_verification(string $to, string $code): string {
    // Single-purpose wrapper used by otp_send for signup — returns status code.
    return self::email($to, 'Your Drop IT verification code', "Your OTP: {$code}");
}""")

    file_section(doc, "scheme/PayMongo.php",
        "GCash sandbox via PayMongo's /v1/sources API. If no key is configured, "
        "returns a FAKE checkout URL (appends ?sim=1) that the return page treats "
        "as auto-paid. Used by both customer_order.php (pay order) and "
        "customer_cash_in.php (wallet top-up).",
"""public static function is_configured(): bool {
    return trim((string) setting('paymongo_secret_key', '')) !== '';
}

public static function create_gcash_source(float $amount_php, string $success_url,
                                           string $failed_url, string $desc='Drop IT delivery'): array {
    if (!self::is_configured()) {
        return ['ok'=>true,
                'id'=>'sim_'.bin2hex(random_bytes(6)),
                'checkout_url'=>$success_url.(str_contains($success_url,'?')?'&':'?').'sim=1',
                'simulated'=>true];
    }
    // POST https://api.paymongo.com/v1/sources with Basic $key: auth
    // Body: {data: {attributes: {amount(cents), currency:PHP, type:gcash,
    //               redirect:{success,failed}, description}}}
    // Response: {data: {id, attributes: {redirect: {checkout_url}}}}
    return ['ok'=>true, 'id'=>$d['data']['id'],
            'checkout_url'=>$d['data']['attributes']['redirect']['checkout_url']];
}

public static function fetch_source(string $source_id): ?array {
    // GET /v1/sources/{id} — used by return pages to verify chargeable/paid.
}""")


def _database_section(doc):
    section_title(doc, "3. Database — /database")
    file_section(doc, "database/schema.sql",
        "Initial schema (v1 + v2 baked in). Tables: users, parcels, parcel_history, "
        "notifications, reports, activity_logs, driver_locations, notification_log, "
        "settings. Default seeds SMTP via Brevo credentials. The notification_log "
        "column is still ENUM('email','sms','in_app') for backward compat but 'sms' "
        "is never written anymore.",
"""-- Core tables
CREATE TABLE users          (id, username UK, email UK, password, role ENUM, full_name, phone, avatar, is_active, created_at);
CREATE TABLE parcels        (id, tracking_number UK, customer_id FK, driver_id FK,
                             sender_name, sender_address,
                             pickup_address/lat/lng, recipient_name/phone/address,
                             dropoff_lat/lng, weight, notes,
                             is_customer_order, status, created_at, updated_at);
CREATE TABLE parcel_history (id, parcel_id FK, status, notes, updated_by, created_at);
CREATE TABLE notifications  (id, user_id, parcel_id, title, message, is_read, created_at);
CREATE TABLE reports        (id, user_id, subject, message, status, created_at);
CREATE TABLE activity_logs  (id, user_id, action, details, created_at);
CREATE TABLE driver_locations (driver_id PK, lat, lng, accuracy, updated_at);
CREATE TABLE notification_log (id, channel ENUM('email','sms','in_app'),
                               recipient, subject, body, status, response, created_at);
CREATE TABLE settings       (setting_key PK, setting_value);

-- Default seeds (email-only; Brevo SMTP preconfigured).
INSERT INTO settings VALUES
  ('admin_email','dropittestacc@gmail.com'),
  ('admin_phone','09000000000'),
  ('smtp_host','smtp-relay.brevo.com'), ('smtp_port','587'),
  ('smtp_user','<brevo smtp login>'),  ('smtp_pass','<brevo api key>'),
  ('smtp_from','Drop IT <dropittestacc@gmail.com>');""")

    file_section(doc, "database/migration_v2.sql",
        "Bolts customer orders + live tracking onto v1. Adds pickup/dropoff geo "
        "columns to parcels, creates driver_locations + notification_log + settings.",
"""ALTER TABLE parcels
  ADD COLUMN pickup_address/lat/lng, dropoff_lat/lng, is_customer_order;
CREATE TABLE IF NOT EXISTS driver_locations, notification_log, settings;""")

    file_section(doc, "database/migration_v3.sql",
        "Signup + email OTP + distance-based fare + wallet simulation. Adds "
        "wallet_balance/phone_verified/email_verified to users. Adds fare + payment "
        "columns to parcels. Creates otps + wallet_transactions. Seeds every "
        "existing customer with ₱500 starter wallet.",
"""ALTER TABLE users ADD wallet_balance, phone_verified, email_verified;
ALTER TABLE parcels ADD product_name, product_description, vehicle_type, distance_km,
                       amount, admin_fee, driver_earnings, payment_status;
CREATE TABLE otps                 (id, purpose ENUM, identifier, code, session_id, expires_at, verified);
CREATE TABLE wallet_transactions  (id, user_id FK, parcel_id, amount, type, description,
                                   balance_after, created_at);
UPDATE users SET wallet_balance=500.00 WHERE role='customer' AND wallet_balance=0;""")

    file_section(doc, "database/migration_v4.sql",
        "Chat, COD/GCash payments, ratings, POD, favorites, availability, "
        "referrals, analytics. Adds availability/rating/theme to users, eta/pod/"
        "accept_token to parcels, and 5 new tables.",
"""ALTER TABLE users   ADD availability, referral_code, rating_avg, rating_count, theme;
ALTER TABLE parcels ADD payment_method, payment_ref, eta_minutes, is_walkin,
                       pod_photo, pod_signature, pod_receiver, accept_token;
CREATE TABLE IF NOT EXISTS messages, ratings, favorite_addresses, payments, sos_alerts;
INSERT IGNORE INTO settings VALUES ('paymongo_secret_key',''), ('paymongo_public_key',''),
                                    ('app_secret','');
UPDATE settings SET setting_value = SUBSTRING(MD5(RAND()), 1, 32)
  WHERE setting_key='app_secret' AND (setting_value IS NULL OR setting_value='');
UPDATE users SET referral_code = CONCAT('DROP', LPAD(id, 4, '0')) WHERE referral_code IS NULL;
UPDATE parcels SET accept_token = SUBSTRING(MD5(CONCAT(id, RAND())), 1, 32) WHERE accept_token IS NULL;""")

    file_section(doc, "database/apply_brevo_smtp.php",
        "One-time utility. Run after a fresh schema import to set the Brevo SMTP "
        "credentials in the settings table. Runs outside of the app.",
"""$rows = [
    'smtp_host' => 'smtp-relay.brevo.com',
    'smtp_port' => '587',
    'smtp_user' => '<brevo smtp login>',
    'smtp_pass' => '<brevo api key>',
    'smtp_from' => 'Drop IT <dropittestacc@gmail.com>',
    'admin_email' => 'dropittestacc@gmail.com',
];
foreach ($rows as $k => $v) {
    $existing = $db->table('settings')->select('setting_key')->where('setting_key',$k)->get();
    if ($existing) $db->table('settings')->where('setting_key',$k)->update(['setting_value'=>$v]);
    else           $db->table('settings')->insert(['setting_key'=>$k,'setting_value'=>$v]);
}""")

    file_section(doc, "database/seed.php",
        "Wipes and re-seeds admin + 3 drivers + 2 customers (each ₱500 wallet). "
        "Now writes email-only settings (Semaphore keys removed in this update).",
"""$admin_pw = password_hash('DropITJACS', PASSWORD_DEFAULT);
$pw       = password_hash('password123', PASSWORD_DEFAULT);

$db->raw('SET FOREIGN_KEY_CHECKS=0');
foreach (['wallet_transactions','otps','notification_log','driver_locations',
          'activity_logs','notifications','reports','parcel_history','parcels','users'] as $t)
    $db->raw("TRUNCATE TABLE $t");
$db->raw('SET FOREIGN_KEY_CHECKS=1');

$db->table('users')->insert([
    'username'=>'admin_dropit', 'email'=>'admin@drop-it.dev', 'password'=>$admin_pw,
    'role'=>'admin', 'full_name'=>'Drop IT Admin',
    'phone_verified'=>1, 'email_verified'=>1,
]);
// + 3 drivers + 2 customers (wallet_balance=500).

$settings = [
    'admin_email' => 'admin@drop-it.dev',
    'admin_phone' => '09000000000',
    'smtp_host'=>'', 'smtp_port'=>'587',
    'smtp_user'=>'', 'smtp_pass'=>'',
    'smtp_from' => 'no-reply@drop-it.dev',
];  // Semaphore/SMS keys removed.""")


def _middlewares_section(doc):
    section_title(doc, "4. Middlewares — /app/middlewares")
    para(doc, "Each middleware returns a closure. Returning false / redirecting stops "
              "the request chain before the handler runs.", italic=True)
    for name, role in [('auth','any logged-in user'),
                       ('admin','admin only (role=admin)'),
                       ('customer','customer only'),
                       ('driver','driver only')]:
        file_section(doc, f"app/middlewares/{name}.php",
            f"Redirect to /login if session does not satisfy {role}.",
f"""return function () {{
    if (empty($_SESSION['user_id']){'' if name=='auth' else " || ($_SESSION['role'] ?? '') !== '"+name+"'"}) {{
        header('Location: ' . url('login')); exit;
    }}
    return true;
}};""")


def _actions_section(doc):
    section_title(doc, "5. Actions — /app/views/actions")
    para(doc, "POST (and a few GET) handlers. They mutate DB state then redirect or "
              "return JSON. Invoked by the router via routes.php. Listed in "
              "alphabetical order of path.", italic=True)

    file_section(doc, "app/views/actions/account_delete.php",
        "Admin deletes a user. Refuses to delete self. Records the deletion in "
        "activity_logs. FK constraints (ON DELETE SET NULL) detach the user's "
        "parcels instead of cascading.",
"""$uid = (int)($id ?? 0);
if ($uid <= 0 || $uid === (int)$_SESSION['user_id']) { set_flash('error','Cannot delete this.'); /* redirect */ }
$user = db()->table('users')->select('username')->where('id', $uid)->get();
if ($user) {
    db()->table('users')->where('id', $uid)->delete();
    db()->table('activity_logs')->insert([
        'user_id'=>$_SESSION['user_id'], 'action'=>'account_delete',
        'details'=>"Admin deleted account '{$user['username']}' (id {$uid}).",
    ]);
}""")

    file_section(doc, "app/views/actions/account_save.php",
        "Admin create OR update user. Validates role whitelist + unique email + "
        "password strength. Hashes password on insert, only re-hashes on update "
        "if a new one was entered.",
"""$is_update = isset($id);
$data = ['username'=>..., 'email'=>..., 'full_name'=>..., 'phone'=>...,
         'role'=>$_POST['role'] ?? 'customer', 'is_active'=>(int)($_POST['is_active'] ?? 1)];

$q = db()->table('users')->select('id')->where('email', $data['email']);
if ($is_update) $q = $q->where('id', '!=', (int)$id);
if ($q->get()) { set_flash('error','Email in use.'); /* redirect */ }

if (!$is_update) {
    $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    db()->table('users')->insert($data);
} else {
    if ($_POST['password'] !== '') $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    db()->table('users')->where('id', (int)$id)->update($data);
}""")

    file_section(doc, "app/views/actions/admin_notify_driver.php",
        "Admin sends a custom nudge to a parcel's driver. Email-only now (SMS "
        "removed) + in-app. Returns JSON with per-channel success booleans.",
"""$channels = $_POST['channels'] ?? ['in_app','email'];
if ($custom === '') {
    $custom = "Drop IT reminder: Parcel {$p['tracking_number']} is still pending. Accept: "
            . url("driver/quick-accept/{$p['id']}?t={$p['accept_token']}");
}
$custom = mb_substr($custom, 0, 480);

$sent = ['in_app'=>false, 'email'=>false];
if (in_array('in_app', $channels, true)) { Notifier::in_app($drv['id'], 'Admin message', $custom, $p['id']); $sent['in_app']=true; }
if (in_array('email',  $channels, true) && !empty($drv['email'])) {
    Notifier::email($drv['email'], "[Drop IT] {$p['tracking_number']}", $custom);
    $sent['email'] = true;
}
json_response(['ok'=>true, 'sent'=>$sent, 'driver'=>$drv['full_name']]);""")

    file_section(doc, "app/views/actions/customer_cash_in.php",
        "NEW. Customer wallet top-up (₱20–₱50,000). Creates a PayMongo GCash source "
        "and stashes the amount + ref in the session; redirects to the checkout URL.",
"""$amount = round((float)($_POST['amount'] ?? 0), 2);
if ($amount < 20 || $amount > 50000) { /* reject */ }

$topup_ref = 'TOPUP-' . bin2hex(random_bytes(6));
$_SESSION['pending_topup'] = ['ref'=>$topup_ref, 'amount'=>$amount];

$success = url('customer/cash-in/return?ref=' . urlencode($topup_ref));
$failed  = url('customer/cash-in?failed=1');
$res = PayMongo::create_gcash_source($amount, $success, $failed, "Drop IT wallet top-up ({$topup_ref})");
if (!$res['ok']) { unset($_SESSION['pending_topup']); /* flash + redirect */ }
$_SESSION['pending_topup']['provider_ref'] = $res['id'];
header('Location: ' . $res['checkout_url']);""")

    file_section(doc, "app/views/actions/customer_cash_in_return.php",
        "NEW. PayMongo return handler. Verifies the source is chargeable/paid (or "
        "?sim=1 for sandbox), credits the wallet, writes a wallet_transactions "
        "row (type='topup') + activity_log, and emails a receipt.",
"""$pending = $_SESSION['pending_topup'] ?? null;
if (!$pending || $pending['ref'] !== $ref) { /* session expired */ }

$paid = $simulated || (PayMongo::fetch_source($pending['provider_ref'])['attributes']['status'] ?? '') in ['chargeable','paid'];
if (!$paid) { /* retry */ }

$newBalance = round((float)$me['wallet_balance'] + $amount, 2);
db()->table('users')->where('id',$uid)->update(['wallet_balance'=>$newBalance]);
db()->table('wallet_transactions')->insert([
    'user_id'=>$uid, 'amount'=>$amount, 'type'=>'topup',
    'description'=>"GCash top-up · {$pending['ref']}".($simulated?' (sandbox)':''),
    'balance_after'=>$newBalance,
]);
db()->table('activity_logs')->insert([...]);
unset($_SESSION['pending_topup']);

if (!empty($me['email'])) Notifier::email($me['email'],
    "[Drop IT] Wallet top-up confirmed — ₱".number_format($amount,2),
    "Your Drop IT wallet was topped-up...");""")

    file_section(doc, "app/views/actions/customer_order.php",
        "Customer places an order. Validates required fields + map pins, computes "
        "fare, checks wallet balance if paying from wallet, inserts parcels with "
        "TMP tracking → rewrites to DROPIT##### after insert_id. Writes payments + "
        "parcel_history + wallet_transactions + activity_logs + Notifier::"
        "notify_admin_new_order. If GCash, redirects to pay_gcash_create.",
"""$fare = compute_fare_php(haversine_km((float)$data['pickup_lat'], (float)$data['pickup_lng'],
                                       (float)$data['dropoff_lat'], (float)$data['dropoff_lng']),
                          'Tricycle');

$payment_status = match ($pay_method) {
    'wallet' => 'held',    // escrow
    'gcash'  => 'unpaid',  // → 'held' on PayMongo confirm
    'cod'    => 'unpaid',  // → 'settled' on driver collect
};

$pid = db()->table('parcels')->insert(array_merge($data, [
    'tracking_number' => 'TMP-'.bin2hex(random_bytes(6)),
    'customer_id'     => $uid, 'status' => 'Pending',
    'is_customer_order'=>1, 'vehicle_type'=>'Tricycle',
    'distance_km'=>$fare['distance_km'], 'amount'=>$fare['amount'],
    'admin_fee'=>$fare['admin_fee'], 'driver_earnings'=>$fare['driver_earnings'],
    'payment_status'=>$payment_status, 'payment_method'=>$pay_method,
    'accept_token'=>bin2hex(random_bytes(16)),
]));
$tracking = 'DROPIT' . str_pad((string)$pid, 5, '0', STR_PAD_LEFT);
db()->table('parcels')->where('id',$pid)->update(['tracking_number'=>$tracking]);

db()->table('payments')->insert(['parcel_id'=>$pid, 'method'=>$pay_method,
    'amount'=>$fare['amount'], 'status'=>$pay_method==='wallet'?'paid':'pending']);

if ($pay_method === 'wallet') {
    $new_bal = round((float)$me['wallet_balance'] - $fare['amount'], 2);
    db()->table('users')->where('id',$uid)->update(['wallet_balance'=>$new_bal]);
    db()->table('wallet_transactions')->insert([
        'user_id'=>$uid, 'parcel_id'=>$pid,
        'amount'=>-1*$fare['amount'], 'type'=>'delivery_charge',
        'description'=>"Order {$tracking} — held in escrow", 'balance_after'=>$new_bal,
    ]);
}

Notifier::notify_admin_new_order($parcel, $me);
if ($pay_method === 'gcash') header('Location: '.url('pay/gcash/create/'.$pid));""")

    file_section(doc, "app/views/actions/driver_accept.php",
        "Driver accept/decline a dispatch (via form). Accept → status='Accepted' + "
        "auto-post a Taglish welcome message into the chat thread. Decline → "
        "status='Pending', driver_id=null. Notifies the customer (or admins for "
        "walk-ins).",
"""if (!$parcel || (int)$parcel['driver_id'] !== $uid || $parcel['status'] !== 'Dispatched') { /* stale offer */ }

if ($decision === 'accept') {
    db()->table('parcels')->where('id', $pid)->update(['status' => 'Accepted']);
    db()->table('parcel_history')->insert(['parcel_id'=>$pid, 'status'=>'Accepted',
        'notes'=>"Driver accepted.", 'updated_by'=>$uid]);
    db()->table('messages')->insert([
        'parcel_id'=>$pid, 'sender_id'=>$uid, 'sender_role'=>'driver',
        'body'=>"Hi! Si {$driver_name} po, driver mo. Accepted ko na 'to — heading to pickup. 🚚",
    ]);
}
if ($decision === 'decline') {
    db()->table('parcels')->where('id', $pid)->update(['driver_id'=>null, 'status'=>'Pending']);
    /* notify admins */
}""")

    file_section(doc, "app/views/actions/driver_quick_accept.php",
        "Same behavior as driver_accept but via a GET link carrying ?t=<accept_token>. "
        "Used by the 1-click links in the dispatch emails. If not logged in as the "
        "assigned driver, stashes the URL and bounces to /login.",
"""$decision = strpos($_SERVER['REQUEST_URI'], 'quick-decline') !== false ? 'decline' : 'accept';
if (!$p || $p['accept_token'] !== $token || $token === '') {
    http_response_code(403); echo "Invalid or expired link."; exit;
}
if ((int)($_SESSION['user_id'] ?? 0) !== (int)$p['driver_id']) {
    $_SESSION['redirect_after_login'] = url('driver/quick-accept/'.$pid.'?t='.$token);
    header('Location: ' . url('login')); exit;
}
// Then same accept/decline logic as driver_accept.php.""")

    file_section(doc, "app/views/actions/driver_update_status.php",
        "The big one. Status machine: Picked Up → In Transit → Arrived → Delivered / "
        "Cancelled. UPDATED: 'Picked Up' now REQUIRES a pickup photo upload (stored "
        "under resources/transit/ and logged as an [IMG] parcel_history row). On "
        "Delivered with escrowed payment, releases wallet 90% to driver + 10% to "
        "first admin via wallet_transactions. On Cancelled with escrow, refunds "
        "customer.",
"""$allowed = ['Picked Up','In Transit','Arrived','Delivered','Cancelled'];
if (!in_array($status, $allowed)) { /* reject */ }
if ((int)$parcel['driver_id'] !== (int)$_SESSION['user_id']) { /* reject */ }

// 'Picked Up' requires a pickup-proof photo (multipart POST).
$pickup_photo_rel = null;
if ($status === 'Picked Up') {
    if (empty($_FILES['pickup_photo']['tmp_name']) || !is_uploaded_file($_FILES['pickup_photo']['tmp_name'])) {
        set_flash('error','Photo is required when marking Picked Up.'); /* redirect */
    }
    $fname = 'pickup_' . $pid . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.jpg';
    move_uploaded_file($_FILES['pickup_photo']['tmp_name'], APP_ROOT.'/resources/transit/'.$fname);
    $pickup_photo_rel = 'resources/transit/' . $fname;
}

db()->table('parcels')->where('id', $pid)->update(['status' => $status]);
db()->table('parcel_history')->insert([...]);
if ($pickup_photo_rel) db()->table('parcel_history')->insert([
    'parcel_id'=>$pid, 'status'=>$status,
    'notes'=>"[IMG]{$pickup_photo_rel}|Proof of pickup", 'updated_by'=>$_SESSION['user_id'],
]);

// Settlement on Delivered (escrow release)
if ($status === 'Delivered' && $parcel['payment_status'] === 'held' && (float)$parcel['amount'] > 0) {
    $drvCut = (float)$parcel['driver_earnings']; $adminCut = (float)$parcel['admin_fee'];
    // credit driver
    db()->table('users')->where('id', $drv['id'])->update(['wallet_balance' => $drvNew]);
    db()->table('wallet_transactions')->insert([... 'type'=>'delivery_earning' ...]);
    // credit platform (first admin row)
    db()->table('users')->where('id',$admin['id'])->update(['wallet_balance'=>$adNew]);
    db()->table('wallet_transactions')->insert([... 'type'=>'admin_commission' ...]);
    db()->table('parcels')->where('id', $pid)->update(['payment_status' => 'settled']);
}
// Refund branch on Cancelled when payment_status='held'
if ($status === 'Cancelled' && $parcel['payment_status'] === 'held' && $parcel['customer_id']) {
    /* wallet_transactions type='refund', payment_status='refunded' */
}

Notifier::notify_customer_status($customer, $parcel, $status, $msg);""")

    file_section(doc, "app/views/actions/favorites.php",
        "Dual-purpose: URL pattern /customer/favorites/delete/{id} deletes; "
        "otherwise POST inserts. Ownership enforced with WHERE user_id = me.",
"""$act = strpos($_SERVER['REQUEST_URI'] ?? '', '/delete/') !== false ? 'delete' : 'create';
if ($act === 'delete') {
    db()->table('favorite_addresses')->where('id',(int)$id)->where('user_id',$uid)->delete();
    /* flash + redirect */
}
db()->table('favorite_addresses')->insert([
    'user_id'=>$uid, 'label'=>$label, 'address'=>$addr, 'lat'=>$lat, 'lng'=>$lng,
]);""")

    file_section(doc, "app/views/actions/login_action.php",
        "Validates email+password, checks is_active, writes session keys, logs "
        "the login, routes to the role-matching dashboard.",
"""$user = db()->table('users')
    ->select('id, username, email, password, role, full_name, is_active')
    ->where('email', $email)->get();
if (!$user || !password_verify($password, $user['password'])) { /* reject */ }
if ((int)$user['is_active'] !== 1) { /* disabled */ }

$_SESSION['user_id'] = $user['id'];
$_SESSION['username']  = $user['username'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'];

$dest = match ($user['role']) {
    'admin' => 'admin/dashboard', 'driver'=>'driver/dashboard',
    'customer' => 'customer/dashboard', default => '/',
};
header('Location: ' . url($dest));""")

    file_section(doc, "app/views/actions/logout.php",
        "Distinguishes idle-timeout vs user-clicked logout in activity_logs. "
        "Drivers flip availability=offline before session destroy.",
"""$reason = ($_POST['reason'] ?? '') === 'idle' ? 'idle_logout' : 'logout';
db()->table('activity_logs')->insert([...$reason...]);
if (($_SESSION['role'] ?? '') === 'driver')
    db()->table('users')->where('id', $_SESSION['user_id'])->update(['availability'=>'offline']);
$_SESSION = []; session_destroy();""")

    file_section(doc, "app/views/actions/otp_send.php",
        "Email-only OTP. Validates format, rate-limits (5/10 min per session + "
        "identifier), wipes older unverified codes, inserts a new 6-digit code, "
        "and calls Notifier::notify_email_verification. The phone branch is now "
        "blocked — returns 400 if called with purpose=phone.",
"""$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

db()->table('otps')  // invalidate older unverified codes
    ->where('session_id', $sid)->where('identifier', $identifier)
    ->where('purpose', $purpose)->where('verified', 0)->delete();

db()->table('otps')->insert([
    'purpose'=>$purpose, 'identifier'=>$identifier, 'code'=>$code,
    'session_id'=>$sid, 'expires_at'=>date('Y-m-d H:i:s', time() + 600),
]);

if ($purpose === 'phone') {  // NEW: hard-block phone OTP path
    json_response(['ok'=>false,'error'=>'Phone OTP is disabled. Verify email only.'], 400);
}

$send_status = Notifier::notify_email_verification($identifier, $code);
if ($send_status !== 'sent') json_response(['ok'=>false,'error'=>'Failed to send email.'], 502);
json_response(['ok'=>true]);""")

    file_section(doc, "app/views/actions/otp_verify.php",
        "Verifies a 6-digit code against the newest unverified otps row. "
        "Timing-safe hash_equals; 410 if expired; 400 on wrong code.",
"""$row = db()->table('otps')->select('id, code, expires_at')
    ->where('session_id', $sid)->where('identifier', $identifier)
    ->where('purpose', $purpose)->where('verified', 0)
    ->order_by('id', 'DESC')->limit(1)->get();

if (!$row)                                       json_response(['ok'=>false,'error'=>'No OTP'], 404);
if (strtotime($row['expires_at']) < time())      json_response(['ok'=>false,'error'=>'Expired'], 410);
if (!hash_equals($row['code'], $code))           json_response(['ok'=>false,'error'=>'Wrong'], 400);

db()->table('otps')->where('id', $row['id'])->update(['verified'=>1]);
json_response(['ok'=>true]);""")

    file_section(doc, "app/views/actions/parcel_assign.php",
        "Admin dispatches (or un-assigns) a parcel. Sets status='Dispatched' and "
        "regenerates accept_token. Calls Notifier::notify_driver_assigned (in-app + "
        "email with 1-click accept link) and notify_customer_status.",
"""$new_driver = $driver_id !== '' ? (int)$driver_id : null;
$new_status = $new_driver ? 'Dispatched' : 'Pending';
$token = $parcel['accept_token'] ?: bin2hex(random_bytes(16));

db()->table('parcels')->where('id', $pid)->update([
    'driver_id'=>$new_driver, 'status'=>$new_status, 'accept_token'=>$token,
]);
db()->table('parcel_history')->insert([...]);
if ($driver_row) Notifier::notify_driver_assigned($driver_row, $parcel);
if ($parcel['customer_id']) Notifier::notify_customer_status($customer, $parcel, $new_status, $msg);""")

    file_section(doc, "app/views/actions/parcel_cancel.php",
        "Admin cancels. Blocks Delivered/Cancelled. Refunds wallet if escrowed. "
        "In-app notifies both customer and driver.",
"""db()->table('parcels')->where('id', $pid)->update(['status'=>'Cancelled']);
db()->table('parcel_history')->insert([...'Admin cancel: '.$reason...]);

if ($p['customer_id'] && $p['payment_status']==='held' && $p['payment_method']!=='cod') {
    $new = round((float)$cust['wallet_balance'] + (float)$p['amount'], 2);
    db()->table('users')->where('id',$cust['id'])->update(['wallet_balance'=>$new]);
    db()->table('wallet_transactions')->insert([... 'type'=>'refund' ...]);
    db()->table('parcels')->where('id',$pid)->update(['payment_status'=>'refunded']);
    Notifier::notify_customer_status($cust, $p, 'Cancelled', "...Fare refunded.");
}""")

    file_section(doc, "app/views/actions/pay_cod_collect.php",
        "Driver confirms COD cash received. Flips payments.status=paid + parcels."
        "payment_status=settled + history log.",
"""db()->table('payments')->where('parcel_id',$pid)->where('method','cod')->update(['status'=>'paid']);
db()->table('parcels')->where('id',$pid)->update(['payment_status'=>'settled']);
db()->table('parcel_history')->insert([..., 'status'=>'COD Collected', ...]);""")

    file_section(doc, "app/views/actions/pay_gcash_create.php",
        "Customer clicks 'Pay GCash' on an order → creates PayMongo source → "
        "inserts payments row with checkout_url + provider_ref → redirects.",
"""$success = url('pay/gcash/return/' . $pid);
$failed  = url('customer/dashboard');
$res = PayMongo::create_gcash_source((float)$p['amount'], $success, $failed, "Drop IT {$p['tracking_number']}");

db()->table('payments')->insert([
    'parcel_id'=>$pid, 'method'=>'gcash', 'amount'=>(float)$p['amount'],
    'status'=>'pending', 'provider_ref'=>$res['id'], 'checkout_url'=>$res['checkout_url'],
]);
db()->table('parcels')->where('id',$pid)->update(['payment_method'=>'gcash', 'payment_ref'=>$res['id']]);
header('Location: ' . $res['checkout_url']);""")

    file_section(doc, "app/views/actions/pay_gcash_return.php",
        "PayMongo return URL. If ?sim=1 or source status is chargeable/paid, "
        "flips payment_status to 'held' (escrow — same slot as wallet).",
"""$paid = $simulated || (in_array(PayMongo::fetch_source($p['payment_ref'])['attributes']['status'] ?? '', ['chargeable','paid']));
if ($paid) {
    db()->table('parcels')->where('id',$pid)->update(['payment_status'=>'held']);
    db()->table('payments')->where('parcel_id',$pid)->where('method','gcash')->update(['status'=>'paid']);
    db()->table('parcel_history')->insert([..., 'GCash payment confirmed'.$simulated? ' (sandbox)' : '.', ...]);
}""")

    file_section(doc, "app/views/actions/pod_submit.php",
        "Driver marks Delivered with Proof of Delivery. Uploads photo to "
        "resources/pod/, stores signature as a data URL, updates parcel. Same "
        "escrow-settlement logic as driver_update_status. For COD: accumulates "
        "into users.cash_on_hand with a wallet_transactions ledger row.",
"""$fname = 'pod_' . $pid . '_' . time() . '.' . $ext;
move_uploaded_file($_FILES['photo']['tmp_name'], APP_ROOT.'/resources/pod/'.$fname);

db()->table('parcels')->where('id',$pid)->update([
    'pod_photo' => 'resources/pod/'.$fname,
    'pod_signature' => $_POST['signature'],   // data URL from canvas.toDataURL()
    'pod_receiver'  => $receiver,
    'status'        => 'Delivered',
]);

// Same escrow-release logic as driver_update_status when payment was 'held'.
// COD branch: add the cash amount to users.cash_on_hand.
if ($p['payment_method'] === 'cod' && !empty($_POST['cod_collected'])) {
    db()->table('users')->where('id', $uid)->update(['cash_on_hand' => $newCash]);
    db()->table('wallet_transactions')->insert([... 'type'=>'cod_collected' ...]);
}""")

    file_section(doc, "app/views/actions/profile_update.php",
        "Customer/driver updates their own profile. Role-aware redirect. "
        "Uniqueness check on email excludes self. Password only re-hashed if "
        "a new one was entered.",
"""$dup = db()->table('users')->select('id')
    ->where('email', $fields['email'])->where('id', '!=', $uid)->get();
if ($dup) { /* reject */ }
if ($pw !== '') $fields['password'] = password_hash($pw, PASSWORD_DEFAULT);
db()->table('users')->where('id', $uid)->update($fields);
$_SESSION['username'] = $fields['username']; $_SESSION['full_name'] = $fields['full_name'];""")

    file_section(doc, "app/views/actions/rate_submit.php",
        "Customer rates driver 1-5 stars + optional review. Upsert on ratings "
        "(parcel_id UNIQUE). refresh_driver_rating() recomputes rating_avg/"
        "rating_count on the driver. Pings the driver in-app.",
"""$stars = max(1, min(5, (int)($_POST['stars'] ?? 0)));
if ($p['status'] !== 'Delivered') { /* reject */ }

$exists = db()->table('ratings')->select('id')->where('parcel_id',$pid)->get();
if ($exists) db()->table('ratings')->where('parcel_id',$pid)->update(['stars'=>$stars,'review'=>$review]);
else          db()->table('ratings')->insert([...]);

refresh_driver_rating((int)$p['driver_id']);
Notifier::in_app((int)$p['driver_id'], "New rating", "You got {$stars}★.", $pid);""")

    file_section(doc, "app/views/actions/report_status.php",
        "Admin changes a report's status (Open / In Review / Resolved / Closed).",
"""if (!in_array($status, ['Open','In Review','Resolved','Closed'])) { /* reject */ }
db()->table('reports')->where('id', (int)$id)->update(['status'=>$status]);""")

    file_section(doc, "app/views/actions/report_submit.php",
        "Customer submits a support/feedback ticket — inserts into reports + "
        "also calls Notifier::notify_admin_new_report so admins get an email.",
"""db()->table('reports')->insert([
    'user_id'=>$_SESSION['user_id'], 'subject'=>$subject, 'message'=>$message,
]);
$reporter = db()->table('users')->select('id, full_name, username, email, role')
    ->where('id', $_SESSION['user_id'])->get();
Notifier::notify_admin_new_report($reporter, ['subject'=>$subject, 'message'=>$message]);""")

    file_section(doc, "app/views/actions/return_submit.php",
        "Customer files a return on a Delivered parcel. Inserts returns row "
        "(status='Requested'), auto-posts a chat message with the reason, and "
        "in-app notifies every admin. Admins respond with /confirm or /reject "
        "commands handled by the messages API.",
"""if ($p['status'] !== 'Delivered') { /* reject */ }
if (db()->table('returns')->where('parcel_id',$pid)->count() > 0) { /* dup */ }

db()->table('returns')->insert(['parcel_id'=>$pid, 'customer_id'=>$uid, 'reason'=>$reason, 'status'=>'Requested']);
db()->table('messages')->insert([
    'parcel_id'=>$pid, 'sender_id'=>$uid, 'sender_role'=>'customer',
    'body'=>"↩️ RETURN REQUESTED\\nReason: {$reason}\\n\\n(Admin: reply /confirm or /reject.)",
]);
// in-app every admin""")

    file_section(doc, "app/views/actions/settings_save.php",
        "Admin saves notification + PayMongo settings. Whitelisted keys; "
        "semaphore_* keys removed (SMS feature gone).",
"""$keys = ['admin_email','admin_phone','smtp_host','smtp_port','smtp_user','smtp_pass',
         'smtp_from','paymongo_secret_key','paymongo_public_key'];
foreach ($keys as $k) if (array_key_exists($k, $_POST)) set_setting($k, trim((string)$_POST[$k]));""")

    file_section(doc, "app/views/actions/signup_submit.php",
        "Customer/driver signup. Validates name/username regex, passwords match, "
        "uniqueness on email/username/phone, requires email OTP (no phone OTP "
        "anymore). Hashes password, seeds ₱500 wallet for customers, auto-logs-in.",
"""if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) { /* reject */ }
if (strlen($pw) < 6 || $pw !== $pw2)                     { /* reject */ }

// uniqueness checks on email / username / phone...
// Email-only OTP required (SMS removed — phone OTP never happens).
$email_ok = db()->table('otps')->where('session_id', session_id())
    ->where('identifier', $email)->where('purpose','email')->where('verified',1)->count();
if (!$email_ok) { /* reject */ }

$new_id = db()->table('users')->insert([
    'username'=>$username, 'email'=>$email,
    'password'=>password_hash($pw, PASSWORD_DEFAULT),
    'role'=>$role, 'full_name'=>$full_name, 'phone'=>$phone,
    'phone_verified'=>1, 'email_verified'=>1,
    'wallet_balance'=>$role==='customer' ? 500.00 : 0.00,
]);

db()->table('otps')->where('session_id', session_id())->delete();

// Auto-login
$_SESSION['user_id']=$new_id; $_SESSION['username']=$username;
$_SESSION['full_name']=$full_name; $_SESSION['role']=$role;""")

    file_section(doc, "app/views/actions/transit_photo.php",
        "NEW. Driver posts a mid-transit photo to the parcel's timeline (not POD). "
        "Saves under resources/transit/; encodes as '[IMG]<path>|<caption>' in "
        "parcel_history.notes so both the admin parcel_detail view and the "
        "customer track page render it inline.",
"""$allowed_statuses = ['Accepted','Picked Up','In Transit','Arrived'];
if (!in_array($p['status'], $allowed_statuses)) { /* not in flight */ }

$fname = 'transit_' . $pid . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.jpg';
move_uploaded_file($_FILES['photo']['tmp_name'], APP_ROOT.'/resources/transit/'.$fname);
$photo_rel = 'resources/transit/' . $fname;

$caption = mb_substr(trim($_POST['caption'] ?? ''), 0, 160);
$note = "[IMG]{$photo_rel}" . ($caption !== '' ? "|{$caption}" : '');

db()->table('parcel_history')->insert([
    'parcel_id'=>$pid, 'status'=>$p['status'],  // keep current status; this is just an image
    'notes'=>$note, 'updated_by'=>$uid,
]);

if ($p['customer_id']) Notifier::in_app((int)$p['customer_id'],
    "📷 Driver sent a photo",
    "New transit update on {$p['tracking_number']}" . ($caption !== '' ? ": {$caption}" : '.'),
    $pid);""")

    file_section(doc, "app/views/actions/walkin_create.php",
        "Admin creates a walk-in parcel. Same fare math as customer_order but "
        "is_walkin=1, customer_id NULL. Optional immediate dispatch → "
        "status='Dispatched' + notify_driver_assigned.",
"""$fare = compute_fare_php(haversine_km(...), 'Tricycle');
$pid = db()->table('parcels')->insert(array_merge($data, [
    'tracking_number'=>'TMP-'.bin2hex(random_bytes(6)),
    'driver_id'  => $driver_id !== '' ? (int)$driver_id : null,
    'status'     => $driver_id !== '' ? 'Dispatched' : 'Pending',
    'is_walkin'  => 1, 'is_customer_order'=>0,
    'distance_km'=>$fare['distance_km'], 'amount'=>$fare['amount'],
    'admin_fee'=>$fare['admin_fee'], 'driver_earnings'=>$fare['driver_earnings'],
    'payment_status'=>$data['payment_method']==='paid_cash'?'settled':'unpaid',
    'accept_token'=>bin2hex(random_bytes(16)),
]));
$tracking = 'DROPIT' . str_pad((string)$pid, 5, '0', STR_PAD_LEFT);
db()->table('parcels')->where('id',$pid)->update(['tracking_number'=>$tracking]);
db()->table('payments')->insert([...]);
if ($driver_id !== '') Notifier::notify_driver_assigned($drv, $p);""")


def _api_section(doc):
    section_title(doc, "6. JSON API — /app/views/api")
    para(doc, "All endpoints return JSON. Protected by route middleware. Called via "
              "fetch() from the frontend JS (DropIT.post + pollers).", italic=True)

    file_section(doc, "app/views/api/driver_location.php",
        "POST driver GPS. Upsert (SELECT → INSERT-or-UPDATE) on driver_locations.",
"""$existing = db()->table('driver_locations')->select('driver_id')->where('driver_id', $uid)->get();
if ($existing) db()->table('driver_locations')->where('driver_id', $uid)->update(['lat'=>$lat,'lng'=>$lng,'accuracy'=>$acc]);
else           db()->table('driver_locations')->insert(['driver_id'=>$uid,'lat'=>$lat,'lng'=>$lng,'accuracy'=>$acc]);""")

    file_section(doc, "app/views/api/driver_availability.php",
        "Driver toggles online/offline/break. Whitelist enforced.",
"""if (!in_array($state, ['online','offline','break'])) json_response(['ok'=>false], 400);
db()->table('users')->where('id',$uid)->update(['availability' => $state]);""")

    file_section(doc, "app/views/api/driver_sos.php",
        "SOS. Inserts sos_alerts + calls Notifier::notify_sos() — emails + in-app "
        "pings every admin with an OSM map link.",
"""db()->table('sos_alerts')->insert(['driver_id'=>$uid, 'parcel_id'=>$pid, 'lat'=>$lat, 'lng'=>$lng, 'note'=>$note]);
Notifier::notify_sos($uid, $drv, $parcel, $lat, $lng);""")

    file_section(doc, "app/views/api/drivers_locations.php",
        "Admins + drivers see other drivers' pins. Only rows updated in the last "
        "5 min are returned.",
"""if (!in_array($role, ['driver','admin'])) json_response(['ok'=>false], 403);
$rows = db()->table('driver_locations')->select('driver_id, lat, lng, updated_at')
    ->where('updated_at', '>', date('Y-m-d H:i:s', time() - 300))->get_all();
json_response(['ok'=>true, 'drivers'=>$rows]);""")

    file_section(doc, "app/views/api/parcel_tracking.php",
        "Admin / owning customer / assigned driver only. Returns latest status + "
        "driver GPS for a tracking number.",
"""$allowed = $role === 'admin'
        || ($role==='customer' && (int)$parcel['customer_id'] === $uid)
        || ($role==='driver'   && (int)$parcel['driver_id']   === $uid);
if (!$allowed) json_response(['ok'=>false,'error'=>'forbidden'], 403);

$driver_loc = $parcel['driver_id'] ? db()->table('driver_locations')
    ->select('lat, lng, accuracy, updated_at')->where('driver_id', $parcel['driver_id'])->get() : null;

json_response(['ok'=>true, 'status'=>$parcel['status'],
    'pickup_lat'=>..., 'dropoff_lat'=>..., 'driver_location'=>$driver_loc,
    'updated_at'=>$parcel['updated_at']]);""")

    file_section(doc, "app/views/api/messages.php",
        "GET: messages since cursor. POST: send new message. Access enforced: "
        "admin/owning-customer/assigned-driver. Admin's /confirm + /reject chat "
        "commands mutate the returns row and push parcel to 'Return Pending'. "
        "Marks messages from the other side as read on every GET.",
"""// POST — insert message
$mid = db()->table('messages')->insert(['parcel_id'=>$pid, 'sender_id'=>$uid, 'sender_role'=>$role, 'body'=>$body]);

// Admin chat commands for returns
if ($role === 'admin') {
    $cmd = strtolower(explode(' ', $body)[0] ?? '');
    if (in_array($cmd, ['/confirm','/reject'])) {
        $new_status = $cmd === '/confirm' ? 'Approved' : 'Rejected';
        db()->table('returns')->where('id',$ret['id'])->update(['status'=>$new_status, 'admin_note'=>$body]);
        if ($cmd === '/confirm') db()->table('parcels')->where('id',$pid)->update(['status'=>'Return Pending']);
    }
}

// Notify the other party(ies) in-app
foreach ($targets as $t) Notifier::notify_driver_message($t, $parcel, $from, $body);

// GET — messages since ?since=
$rows = db()->table('messages')
    ->select('messages.id, sender_id, sender_role, body, created_at, users.full_name, users.username')
    ->left_join('users', 'users.id = messages.sender_id')
    ->where('messages.parcel_id', $pid)->where('messages.id', '>', $since)
    ->order_by('messages.id', 'ASC')->limit(100)->get_all();

db()->raw("UPDATE messages SET is_read=1 WHERE parcel_id=? AND sender_id<>? AND is_read=0", [$pid, $uid]);""")

    file_section(doc, "app/views/api/messages_unread.php",
        "Unread message count. Separate query for admins (sees walk-ins + driver "
        "msgs) vs customer/driver (their own peers).",
"""if ($role === 'admin') {
    $row = db()->raw("
        SELECT COUNT(*) c FROM messages m JOIN parcels p ON p.id = m.parcel_id
        WHERE m.is_read = 0 AND m.sender_id <> ?
          AND (p.is_walkin = 1 OR p.customer_id IS NULL OR m.sender_role = 'driver')
    ", [$uid])->fetch();
} else {
    $row = db()->raw("
        SELECT COUNT(*) c FROM messages m JOIN parcels p ON p.id = m.parcel_id
        WHERE m.is_read = 0 AND m.sender_id <> ?
          AND ( (p.customer_id = ? AND m.sender_role IN ('driver','admin'))
             OR (p.driver_id   = ? AND m.sender_role IN ('customer','admin')) )
    ", [$uid, $uid, $uid])->fetch();
}""")

    file_section(doc, "app/views/api/notifications_unread.php",
        "Latest 10 + unread count. Powers the bell badge + toast poller.",
"""$items = db()->table('notifications')
    ->select('id, title, message, is_read, created_at, parcel_id')
    ->where('user_id', $uid)->order_by('id','DESC')->limit(10)->get_all();
json_response(['ok'=>true, 'items'=>array_reverse($items), 'latest_id'=>$latest, 'unread_count'=>$unread]);""")

    file_section(doc, "app/views/api/theme_toggle.php",
        "Dark/light theme. Stores in session + users.theme column if v4 migration "
        "ran (graceful SHOW COLUMNS check).",
"""$theme = in_array($_POST['theme'] ?? '', ['dark','light']) ? $_POST['theme'] : 'dark';
$_SESSION['theme'] = $theme;
try {
    $cols = db()->raw("SHOW COLUMNS FROM users LIKE 'theme'")->fetch();
    if ($cols) db()->table('users')->where('id',$uid)->update(['theme'=>$theme]);
} catch (Throwable $e) {}""")


def _admin_views_section(doc):
    section_title(doc, "7. Admin Views — /app/views/admin")
    para(doc, "UI markup trimmed. Only the data-fetch + decision PHP is shown.", italic=True)

    file_section(doc, "app/views/admin/dashboard.php",
        "Admin home. Role+status counts on users/parcels. Latest 10 activity_logs. "
        "Live-refreshes via DropIT.refresh every 8s.",
"""$total_users    = db()->table('users')->where('role','!=','admin')->count();
$total_drivers  = db()->table('users')->where('role','driver')->where('is_active',1)->count();
$total_parcels  = db()->table('parcels')->count();
$pending        = db()->table('parcels')->where('status','Pending')->count();
$in_transit     = db()->table('parcels')->where('status','In Transit')->count();
$delivered      = db()->table('parcels')->where('status','Delivered')->count();
$recent = db()->table('activity_logs')->select('user_id, action, details, created_at')
    ->order_by('created_at','DESC')->limit(10)->get_all();""")

    file_section(doc, "app/views/admin/accounts.php",
        "User list (admins excluded). Role filter via ?role=. Edit + Delete per row.",
"""$q = db()->table('users')->select('id, username, email, role, full_name, phone, is_active, created_at')
    ->where('role', '!=', 'admin');
if (in_array($_GET['role'] ?? '', ['customer','driver'])) $q = $q->where('role', $_GET['role']);
$users = $q->order_by('created_at', 'DESC')->get_all();""")

    file_section(doc, "app/views/admin/account_form.php",
        "Shared New + Edit form (posts to account_save.php). Loads by id if present.",
"""if (isset($id)) {
    $user = db()->table('users')->select('id, username, email, role, full_name, phone, is_active')
        ->where('id', $id)->get();
    if (!$user) { /* redirect */ }
}""")

    file_section(doc, "app/views/admin/walkin.php",
        "New-parcel form. Loads active drivers for the dispatch dropdown.",
"""$drivers = db()->table('users')->select('id, username, full_name, availability')
    ->where('role','driver')->where('is_active',1)->order_by('full_name','ASC')->get_all();""")

    file_section(doc, "app/views/admin/assign_driver.php",
        "Dispatch cockpit. Pending list + active parcels + drivers + live fleet "
        "map (polls /api/drivers/locations). Each card has inline assign / cancel.",
"""$pending        = db()->table('parcels')->select(...)->where('status','Pending')->order_by('created_at','DESC')->get_all();
$active_parcels = db()->table('parcels')->select(...)->where('status','!=','Pending')
                                                    ->where('status','!=','Delivered')->limit(100)->get_all();
$drivers = db()->table('users')->select('id, username, full_name, availability, rating_avg, rating_count')
    ->where('role','driver')->where('is_active',1)->order_by('full_name','ASC')->get_all();""")

    file_section(doc, "app/views/admin/parcel_detail.php",
        "Single-parcel detail: timeline + map + chat bubble + notify-driver composer "
        "(email + in-app channels). UPDATED: timeline now renders [IMG] "
        "parcel_history rows as inline transit/pickup photos.",
"""$p = db()->table('parcels')->select(...big list including pod_*...)->where('id', $pid)->get();
$driver   = $p['driver_id']   ? db()->table('users')->select(...)->where('id',$p['driver_id'])->get() : null;
$customer = $p['customer_id'] ? db()->table('users')->select(...)->where('id',$p['customer_id'])->get() : null;
$history  = db()->table('parcel_history')->select('status, notes, created_at')
    ->where('parcel_id',$pid)->order_by('created_at','DESC')->get_all();

// Timeline render: notes starting with '[IMG]<path>|<caption>' display inline.
foreach ($history as $h) {
    $is_img = is_string($h['notes'] ?? '') && str_starts_with($h['notes'], '[IMG]');
    if ($is_img) { /* parse + render <img> */ }
}""")

    file_section(doc, "app/views/admin/drivers_map.php",
        "Fleet map. LEFT JOIN driver_locations so drivers with no GPS still show. "
        "TIMESTAMPDIFF computes mins_ago for freshness chip (live/recent/stale).",
"""$drivers = db()->raw("
    SELECT u.id, u.username, u.full_name, u.phone, u.availability, u.rating_avg, u.rating_count,
           dl.lat, dl.lng, dl.accuracy, dl.updated_at AS loc_updated,
           TIMESTAMPDIFF(MINUTE, dl.updated_at, NOW()) AS mins_ago
    FROM users u
    LEFT JOIN driver_locations dl ON dl.driver_id = u.id
    WHERE u.role='driver' AND u.is_active=1
    ORDER BY dl.updated_at DESC
")->fetchAll();""")

    file_section(doc, "app/views/admin/messages.php",
        "Admin inbox of chat threads. INNER JOIN messages via MAX(id) subquery to "
        "pull one row per parcel with the latest message. Unread is a correlated subquery.",
"""$threads = db()->raw("
    SELECT p.id, p.tracking_number, p.is_walkin, p.status, p.customer_id, p.driver_id,
           m.body AS last_body, m.created_at AS last_at, m.sender_role AS last_role,
           (SELECT COUNT(*) FROM messages mm WHERE mm.parcel_id = p.id
                AND mm.is_read = 0 AND mm.sender_id <> ?) AS unread,
           (SELECT u.full_name FROM users u WHERE u.id = p.driver_id)   AS driver_name,
           (SELECT u.full_name FROM users u WHERE u.id = p.customer_id) AS customer_name
    FROM parcels p
    INNER JOIN messages m ON m.id = (SELECT MAX(id) FROM messages WHERE parcel_id = p.id)
    ORDER BY m.id DESC LIMIT 200
", [(int)$_SESSION['user_id']])->fetchAll();""")

    file_section(doc, "app/views/admin/analytics.php",
        "Range selector (days / single-date / from-to) + 4 charts + top-10 drivers. "
        "All grouping in raw SQL.",
"""$rows = db()->raw("
    SELECT DATE(created_at) d, COUNT(*) c, SUM(amount) rev,
           SUM(CASE WHEN status='Delivered' THEN 1 ELSE 0 END) delivered,
           SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END) cancelled
    FROM parcels WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at) ORDER BY d ASC
", [$from, $to])->fetchAll();

$pay_split = db()->raw("SELECT payment_method, COUNT(*) c, SUM(amount) rev
    FROM parcels WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY payment_method", [$from, $to])->fetchAll();

$top_drivers = db()->raw("SELECT u.id, u.full_name, u.username, COUNT(p.id) trips,
    SUM(p.driver_earnings) earnings, u.rating_avg, u.rating_count
    FROM users u LEFT JOIN parcels p ON p.driver_id = u.id
      AND p.status='Delivered' AND DATE(p.created_at) BETWEEN ? AND ?
    WHERE u.role='driver' AND u.is_active=1
    GROUP BY u.id, u.full_name, u.username, u.rating_avg, u.rating_count
    ORDER BY trips DESC LIMIT 10", [$from, $to])->fetchAll();""")

    file_section(doc, "app/views/admin/analytics_export.php",
        "CSV export of parcels. UTF-8 BOM for Excel. Two LEFT JOINs aliased u and d.",
"""$rows = db()->raw("
    SELECT p.tracking_number, p.status, p.payment_method, p.payment_status,
           p.amount, p.admin_fee, p.driver_earnings, p.distance_km,
           p.created_at, p.updated_at, u.full_name AS customer, d.full_name AS driver
    FROM parcels p
    LEFT JOIN users u ON u.id = p.customer_id
    LEFT JOIN users d ON d.id = p.driver_id
    WHERE DATE(p.created_at) BETWEEN ? AND ?
    ORDER BY p.created_at DESC
", [$from, $to])->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
fwrite($out, "\\xEF\\xBB\\xBF");
fputcsv($out, ['Tracking','Status',...]); foreach ($rows as $r) fputcsv($out, [...]);""")

    file_section(doc, "app/views/admin/report_print.php",
        "Printable 4-variant business report (summary/drivers/revenue/parcels). "
        "KPI totals header for all four, then the body-specific query.",
"""$tot = db()->raw("
    SELECT COUNT(*) c, SUM(amount) rev, SUM(admin_fee) fee, SUM(driver_earnings) drv,
           SUM(CASE WHEN status='Delivered' THEN 1 ELSE 0 END) delivered,
           SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END) cancelled
    FROM parcels WHERE DATE(created_at) BETWEEN ? AND ?", [$from, $to])->fetch();

// type='parcels' variant:
$parcels = db()->raw("SELECT p.tracking_number, p.status, p.payment_method, p.amount,
    p.driver_earnings, p.created_at, u.full_name AS customer, d.full_name AS driver
    FROM parcels p LEFT JOIN users u ON u.id = p.customer_id
                   LEFT JOIN users d ON d.id = p.driver_id
    WHERE DATE(p.created_at) BETWEEN ? AND ?
    ORDER BY p.created_at DESC", [$from, $to])->fetchAll();""")

    file_section(doc, "app/views/admin/history.php",
        "Tabs: Shipments + Activity. Shipments = recent 500 parcels with LEFT "
        "JOIN customer + driver names + optional status filter. Activity = last "
        "300 activity_logs rows grouped by day.",
"""$parcels = db()->raw("
  SELECT p.id, p.tracking_number, p.status, p.amount, p.payment_method, p.payment_status,
         p.recipient_name, p.recipient_address, p.created_at, p.updated_at, p.is_walkin,
         c.full_name AS customer_name, d.full_name AS driver_name
  FROM parcels p LEFT JOIN users c ON c.id = p.customer_id
                 LEFT JOIN users d ON d.id = p.driver_id
  " . ($status ? "WHERE p.status = ?" : "") . "
  ORDER BY p.created_at DESC LIMIT 500
", $status ? [$status] : [])->fetchAll();

$logs = db()->raw("SELECT a.action, a.details, a.created_at, u.username, u.full_name
  FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id
  ORDER BY a.created_at DESC LIMIT 300")->fetchAll();""")

    file_section(doc, "app/views/admin/inbox.php",
        "NEW. Categorized notifications view — filters by category (SOS / Support "
        "/ Timeouts / Orders / Drivers / Messages). Auto-marks all as read on "
        "load. Each card links to the relevant destination: Support → "
        "/admin/reports; SOS → /admin/drivers-map; Timeout → /admin/shipments/"
        "assign; otherwise → /admin/parcel/{id}.",
"""db()->table('notifications')->where('user_id',$uid)->where('is_read',0)->update(['is_read'=>1]);
$notifs = db()->table('notifications')->select('id, title, message, parcel_id, created_at')
    ->where('user_id',$uid)->order_by('created_at','DESC')->limit(300)->get_all();

function inbox_category(array $n): string {
    $t = strtolower($n['title'] ?? '');
    if (str_contains($t,'sos'))                                   return 'sos';
    if (str_contains($t,'report') || str_contains($t,'support'))  return 'support';
    if (str_contains($t,'timeout'))                               return 'timeout';
    if (str_contains($t,'accept') || str_contains($t,'decline'))  return 'driver';
    if (preg_match('/deliver|picked|transit|arrived|order|cancel/', $t)) return 'order';
    if (str_contains($t,'message'))                               return 'message';
    return 'other';
}

function inbox_link(array $n, string $cat): ?string {
    switch ($cat) {
        case 'support': return url('admin/reports');
        case 'sos':     return url('admin/drivers-map');
        case 'timeout': return url('admin/shipments/assign');
    }
    return !empty($n['parcel_id']) ? url('admin/parcel/' . (int)$n['parcel_id']) : null;
}""")

    file_section(doc, "app/views/admin/reports.php",
        "User-submitted reports with status-change form. Reporter names fetched "
        "in a second query via IN().",
"""$reports = db()->table('reports')->select('id, user_id, subject, message, status, created_at')
    ->order_by('created_at','DESC')->get_all();
$ids = array_filter(array_unique(array_column($reports,'user_id')));
if ($ids) {
    $rows = db()->table('users')->select('id, username, full_name, role')->in('id', $ids)->get_all();
    foreach ($rows as $r) $names[$r['id']] = $r;
}""")

    file_section(doc, "app/views/admin/settings.php",
        "Form over the settings key/value store. UPDATED: Semaphore SMS section "
        "removed. Three sections: Admin notifications (email target), SMTP (now "
        "tuned for Brevo's smtp-relay.brevo.com:587 + API key), PayMongo.",
"""$keys = ['admin_email','admin_phone','smtp_host','smtp_port','smtp_user',
         'smtp_pass','smtp_from','paymongo_secret_key','paymongo_public_key'];
foreach ($keys as $k) $vals[$k] = setting($k, '');""")

    file_section(doc, "app/views/admin/notifications_log.php",
        "Notification_log rows. UPDATED: SMS tab removed; channel filter is All / "
        "In-app / Email.",
"""$q = db()->table('notification_log')->select(...)
    ->where('status', '!=', 'disabled')->where('status', '!=', 'dry_run');
if (in_array($filter, ['email','in_app'])) $q = $q->where('channel', $filter);
if ($search !== '') $q = $q->like('recipient', '%'.$search.'%');
$logs = $q->order_by('created_at','DESC')->limit(200)->get_all();""")


def _customer_views_section(doc):
    section_title(doc, "8. Customer Views — /app/views/customer")

    file_section(doc, "app/views/customer/dashboard.php",
        "Mobile-first customer home. Active (non-Delivered, non-Cancelled) "
        "parcels, unread count, wallet + NEW '💳 Cash in' button linking to "
        "/customer/cash-in.",
"""$active_parcels = db()->table('parcels')
    ->select('id, tracking_number, status, recipient_address, updated_at')
    ->where('customer_id', $uid)
    ->where('status', '!=', 'Delivered')->where('status', '!=', 'Cancelled')
    ->order_by('updated_at', 'DESC')->limit(3)->get_all();

$unread = db()->table('notifications')->where('user_id', $uid)->where('is_read', 0)->count();
$wallet = db()->table('users')->select('wallet_balance')->where('id',$uid)->get();""")

    file_section(doc, "app/views/customer/cash_in.php",
        "NEW. Simple top-up form (min ₱20, max ₱50,000) with quick-pick amount "
        "buttons. Posts to /customer/cash-in. Shows sandbox warning if PayMongo "
        "is not configured.",
"""$me = db()->table('users')->select('wallet_balance')->where('id',$uid)->get();
// Form: amount input + [100][300][500][1000] quick buttons. POSTs to customer_cash_in action.
if (!PayMongo::is_configured()) echo '⚠️ PayMongo sandbox mode — payments auto-simulate.';""")

    file_section(doc, "app/views/customer/order.php",
        "Place-order form. Loads wallet + favorite addresses. Real action in "
        "customer_order.php.",
"""$me = db()->table('users')->select('id, full_name, phone, wallet_balance')->where('id', $uid)->get();
$favorites = db()->table('favorite_addresses')->select('id, label, address, lat, lng')
    ->where('user_id', $uid)->order_by('id','DESC')->get_all();""")

    file_section(doc, "app/views/customer/track.php",
        "UPDATED: Active / Past tabs when no tracking number is entered. Detail "
        "view with live map (polled every 6-30s with backoff, smooth marker "
        "animation, stale detection > 2 min). Timeline now renders [IMG] rows as "
        "inline transit/pickup photos.",
"""$parcel = db()->table('parcels')->select(...)->where('tracking_number', $q)->get();
if ($parcel && (int)$parcel['customer_id'] !== $uid) $parcel = null;   // not yours

// List view (no q): Active tab
$active_list = db()->table('parcels')->select(...)
    ->where('customer_id', $uid)->where('status','!=','Delivered')->where('status','!=','Cancelled')
    ->order_by('updated_at', 'DESC')->get_all();
// Past tab
$past_list = db()->raw(
    "SELECT id, tracking_number, status, recipient_address, updated_at FROM parcels
      WHERE customer_id = ? AND status IN ('Delivered','Cancelled')
      ORDER BY updated_at DESC LIMIT 50",
    [$uid]
)->fetchAll();

// Detail — seeded initial driver GPS so the map renders instantly.
if ($parcel['driver_id']) $initial_driver_loc = db()->table('driver_locations')
    ->select('lat, lng, updated_at')->where('driver_id', $parcel['driver_id'])->get();""")

    file_section(doc, "app/views/customer/history.php",
        "All the customer's parcels, newest first.",
"""$parcels = db()->table('parcels')
    ->select('id, tracking_number, status, recipient_address, created_at, updated_at')
    ->where('customer_id', $uid)->order_by('created_at', 'DESC')->get_all();""")

    file_section(doc, "app/views/customer/notifications.php",
        "Auto-marks unread as read on load. Lists latest 100 grouped by day.",
"""db()->table('notifications')->where('user_id', $uid)->where('is_read', 0)->update(['is_read' => 1]);
$notifs = db()->table('notifications')->select('id, title, message, parcel_id, created_at')
    ->where('user_id', $uid)->order_by('created_at', 'DESC')->limit(100)->get_all();""")

    file_section(doc, "app/views/customer/messages.php",
        "Chat inbox scoped to the customer's parcels. Uses EXISTS() + subqueries "
        "for last message + unread count.",
"""$threads = db()->raw("
    SELECT p.id, p.tracking_number, p.status,
           (SELECT body       FROM messages WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_body,
           (SELECT created_at FROM messages WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_at,
           (SELECT sender_role FROM messages WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_role,
           (SELECT COUNT(*) FROM messages WHERE parcel_id = p.id AND is_read = 0 AND sender_id <> ?) AS unread,
           (SELECT u.full_name FROM users u WHERE u.id = p.driver_id) AS driver_name
    FROM parcels p
    WHERE p.customer_id = ? AND EXISTS (SELECT 1 FROM messages WHERE parcel_id = p.id)
    ORDER BY last_at DESC
", [$uid, $uid])->fetchAll();""")

    file_section(doc, "app/views/customer/profile.php",
        "Loads user with gracefully-detected v4 columns (SHOW COLUMNS before "
        "selecting). Theme toggle hits /api/theme.",
"""try {
    $has = db()->raw("SHOW COLUMNS FROM users LIKE 'referral_code'")->fetch();
    if ($has) $extra_cols = ', referral_code, theme';
} catch (Throwable $e) {}
$me = db()->table('users')
    ->select('id, username, email, full_name, phone, avatar, created_at' . $extra_cols)
    ->where('id', $uid)->get();""")

    file_section(doc, "app/views/customer/favorites.php",
        "Saved addresses list + create form. POST → favorites action.",
"""$list = db()->table('favorite_addresses')->select('id, label, address, lat, lng')
    ->where('user_id',$uid)->order_by('id','DESC')->get_all();""")

    file_section(doc, "app/views/customer/rate.php",
        "Star-rating UI — loads existing rating to allow edits.",
"""$existing = db()->table('ratings')->select('id, stars, review')->where('parcel_id',$pid)->get();
$driver   = $p['driver_id'] ? db()->table('users')
    ->select('full_name, username, rating_avg, rating_count')->where('id',$p['driver_id'])->get() : null;""")

    file_section(doc, "app/views/customer/receipt.php",
        "Print-ready receipt. Access: admin / owner / assigned driver only.",
"""$allowed = $role === 'admin'
        || ($role === 'customer' && (int)$p['customer_id'] === $uid)
        || ($role === 'driver'   && (int)$p['driver_id']   === $uid);
if (!$allowed) { http_response_code(403); exit; }""")

    file_section(doc, "app/views/customer/report.php",
        "Simple subject + message form; posts to report_submit.",
"""// Pure UI; inserts into reports via actions/report_submit.php""")

    file_section(doc, "app/views/customer/return_request.php",
        "Return form shown only for Delivered parcels. Shows existing status if "
        "already filed.",
"""if ($p['status'] !== 'Delivered') { /* not allowed */ }
$existing = db()->table('returns')->select('id, status')->where('parcel_id',$pid)->get();""")

    file_section(doc, "app/views/customer/support.php",
        "Virtual 'SUPPORT-*' parcel used as a chat thread with admin. First "
        "visit inserts the parcel + welcome admin message; subsequent visits reuse it.",
"""$support = db()->table('parcels')->select('id, tracking_number')
    ->where('customer_id', $uid)->like('tracking_number', 'SUPPORT-%')->get();
if (!$support) {
    $sid = db()->table('parcels')->insert(['tracking_number'=>'SUPPORT-'.$uid.'-'.bin2hex(random_bytes(3)),
        'customer_id'=>$uid, 'status'=>'Support', 'amount'=>0]);
    $admin = db()->table('users')->select('id')->where('role','admin')->order_by('id','ASC')->limit(1)->get();
    db()->table('messages')->insert(['parcel_id'=>$sid, 'sender_id'=>$admin['id'],
        'sender_role'=>'admin', 'body'=>"Hi! Welcome to Drop IT Customer Service. 👋"]);
}""")


def _driver_views_section(doc):
    section_title(doc, "9. Driver Views — /app/views/driver")

    file_section(doc, "app/views/driver/dashboard.php",
        "Driver home. UPDATED: calls sweep_stale_dispatches(15) on every load "
        "(auto-returns abandoned dispatches to queue). Shows availability, wallet, "
        "rating, new dispatch offers, active deliveries, SOS. The COD cash-in "
        "button that used to be here has been REMOVED — the wallet top-up flow is "
        "now on the customer side.",
"""sweep_stale_dispatches(15);   // lazy cron — runs on every driver-dashboard hit

$me = db()->table('users')->select('wallet_balance, availability, rating_avg, rating_count')
    ->where('id',$uid)->get();

$offers = db()->table('parcels')->select(...)
    ->where('driver_id', $uid)->where('status', 'Dispatched')
    ->order_by('updated_at','DESC')->get_all();

$active_tasks = db()->table('parcels')->select(...)
    ->where('driver_id', $uid)
    ->where('status', '!=', 'Delivered')->where('status', '!=', 'Dispatched')
    ->where('status', '!=', 'Cancelled')->order_by('updated_at','DESC')->get_all();

$done = db()->table('parcels')->where('driver_id', $uid)->where('status', 'Delivered')->count();""")

    file_section(doc, "app/views/driver/parcel_detail.php",
        "Active parcel page. UPDATED: transit photo form (POSTs to /driver/"
        "transit-photo/{id}) shown when status is in flight. Status update form "
        "is now multipart/form-data — when the driver selects 'Picked Up', a "
        "pickup-photo upload field appears and becomes required. ETA via "
        "compute_eta_minutes().",
"""$next_options = match ($p['status']) {
    'Accepted'=>['Picked Up','Cancelled'], 'Picked Up'=>['In Transit','Cancelled'],
    'In Transit'=>['Arrived','Cancelled'], 'Arrived'=>['Delivered','Cancelled'],
    default=>['Picked Up','In Transit','Arrived','Delivered','Cancelled'],
};

$drv_loc = db()->table('driver_locations')->select('lat, lng, updated_at')
    ->where('driver_id', $uid)->get();
$eta_min = $drv_loc && $p['dropoff_lat']
    ? compute_eta_minutes((float)$drv_loc['lat'], (float)$drv_loc['lng'],
                          (float)$p['dropoff_lat'], (float)$p['dropoff_lng'])
    : null;

// Chat is automatically closed when parcel is Delivered or Cancelled.
$chat_closed = in_array($p['status'], ['Delivered','Cancelled']);""")

    file_section(doc, "app/views/driver/pod.php",
        "POD capture. Photo + signature canvas + COD collected checkbox. POSTs "
        "to pod_submit.php as multipart/form-data.",
"""$p = db()->table('parcels')->select('id, tracking_number, status, driver_id, recipient_name, recipient_address, amount, payment_method, payment_status, pod_photo')
    ->where('id',$pid)->get();
if (!$p || (int)$p['driver_id'] !== $uid) { /* reject */ }""")

    file_section(doc, "app/views/driver/analytics.php",
        "Driver's own last-30-days earnings + lifetime totals + 5 most recent reviews.",
"""$daily = db()->raw("
    SELECT DATE(updated_at) d, COUNT(*) c, SUM(driver_earnings) earn, SUM(distance_km) km
    FROM parcels WHERE driver_id=? AND status='Delivered' AND DATE(updated_at) >= ?
    GROUP BY DATE(updated_at) ORDER BY d ASC", [$uid, $since])->fetchAll();

$total = db()->raw("SELECT COUNT(*) c, SUM(driver_earnings) e, SUM(distance_km) km
    FROM parcels WHERE driver_id=? AND status='Delivered'", [$uid])->fetch();

$recent_reviews = db()->raw("SELECT r.stars, r.review, r.created_at, u.full_name
    FROM ratings r LEFT JOIN users u ON u.id = r.customer_id
    WHERE r.driver_id=? ORDER BY r.id DESC LIMIT 5", [$uid])->fetchAll();""")

    file_section(doc, "app/views/driver/messages.php",
        "Chat inbox scoped to the driver's parcels. Same shape as customer/messages.",
"""$threads = db()->raw("
    SELECT p.id, p.tracking_number, p.status, p.is_walkin, p.customer_id,
           (SELECT body FROM messages WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_body,
           (SELECT created_at FROM messages WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_at,
           (SELECT sender_role FROM messages WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_role,
           (SELECT COUNT(*) FROM messages WHERE parcel_id = p.id AND is_read = 0 AND sender_id <> ?) AS unread,
           (SELECT u.full_name FROM users u WHERE u.id = p.customer_id) AS customer_name
    FROM parcels p
    WHERE p.driver_id = ? AND EXISTS (SELECT 1 FROM messages WHERE parcel_id = p.id)
    ORDER BY last_at DESC
", [$uid, $uid])->fetchAll();""")

    file_section(doc, "app/views/driver/profile.php",
        "Basic profile edit — same pattern as customer profile.",
"""$me = db()->table('users')->select('id, username, email, full_name, phone, created_at')
    ->where('id', $uid)->get();""")


def _partials_section(doc):
    section_title(doc, "10. Partials — /app/views/partials")

    file_section(doc, "app/views/partials/head.php",
        "Every page's <head>. Tailwind + glassmorphism CSS + session-driven "
        "theme class. Defines PHP helper status_chip() used across views.",
"""function status_chip($status){
    $s = strtolower($status);
    $cls = 'chip-pending';
    if (str_contains($s,'transit') || str_contains($s,'picked'))    $cls='chip-transit';
    elseif (str_contains($s,'arrived'))                              $cls='chip-arrived';
    elseif (str_contains($s,'delivered'))                            $cls='chip-delivered';
    elseif (str_contains($s,'cancel'))                               $cls='chip-cancelled';
    return '<span class="chip '.$cls.'">'.esc($status).'</span>';
}""")

    file_section(doc, "app/views/partials/live.php",
        "DropIT JS namespace — partial page refresh, toast, Web-Audio beeps, "
        "notification + message pollers, CSRF-aware POST helper, 3-min idle auto-"
        "logout.",
"""window.DropIT = (function(){
    const csrf = <?= json_encode(csrf_token()) ?>;
    async function refresh(ids){ /* fetch same URL, swap innerHTML by id */ }
    async function pollNotifications(){ /* /api/notifications/unread — toast on new */ }
    async function post(path, body){
        const fd = new URLSearchParams(body || {}); fd.set('csrf_token', csrf);
        return fetch(path, {method:'POST', body: fd, credentials:'same-origin'}).then(r=>r.json());
    }
    function startIdleLogout(minutes){ /* 3-min no-input → POST /logout with reason='idle' */ }
    return {sound, toast, refresh, autoRefresh, startNotifPoll, post, csrf, startIdleLogout};
})();""")

    file_section(doc, "app/views/partials/flash.php",
        "Reads success / error flash messages from session and renders them.",
"""$ok = get_flash('success'); $err = get_flash('error');
// echoes glass-styled banners""")

    file_section(doc, "app/views/partials/sidebar.php",
        "Admin left nav. UPDATED: new '📥 Inbox' menu item for /admin/inbox. "
        "Pending count is pulled once per page so Dispatch shows a live badge. "
        "Auto-includes live.php + global_bubbles.php.",
"""$pending_count = (int) db()->table('parcels')->where('status','Pending')->count();
$menu = [ /* dashboard, accounts, walkin, assign(badge), drivers_map, messages,
           inbox (NEW), analytics, history, reports, notifications, settings */ ];""")

    file_section(doc, "app/views/partials/mobile_nav.php",
        "Bottom nav for customer / driver. 5 tabs for customers; 2 for drivers.",
"""if ($role === 'customer') $items = [ /* dashboard, track, alerts, history, profile */ ];
elseif ($role === 'driver') $items = [ /* tasks, profile */ ];""")

    file_section(doc, "app/views/partials/global_bubbles.php",
        "Floating 💬 button + PeerJS voice-call widget on every authenticated "
        "page (unless the page already has a parcel-specific chat bubble).",
"""if (empty($_SESSION['user_id'])) return;
if (!empty($GLOBALS['__dropit_global_bubbles_rendered'])) return;
$GLOBALS['__dropit_global_bubbles_rendered'] = true;

$_msg_link = match ($_SESSION['role'] ?? '') {
    'admin'=>url('admin/messages'), 'customer'=>url('customer/messages'),
    'driver'=>url('driver/messages'),
};
if (empty($GLOBALS['__dropit_voicecall_included'])) include __DIR__ . '/voice_call.php';""")

    file_section(doc, "app/views/partials/chat_bubble.php",
        "Per-parcel chat bubble. Requires $chat_parcel_id, $chat_peer_name, "
        "$chat_self_role to be set by the including page. Polls /api/parcel/{id}"
        "/messages every 6s and plays a soft beep for new incoming messages. "
        "Supports $chat_closed mode to freeze the composer after Delivered/Cancelled.",
"""const PID = <?= $_cp ?>;
const SELF_ROLE = <?= json_encode($chat_self_role) ?>;
async function poll(){
    const r = await fetch(`<?= url('api/parcel/') ?>${PID}/messages?since=${lastId}`);
    const d = await r.json();
    for (const m of d.items){ bubble(m); lastId = Math.max(lastId, m.id);
        if (m.sender_role !== SELF_ROLE) DropIT.sound.message(); }
}
form.onsubmit = async (e) => {
    e.preventDefault();
    const d = await DropIT.post(`<?= url('api/parcel/') ?>${PID}/messages`, {body: input.value});
    if (d.ok) poll();
};
setInterval(poll, 6000);""")

    file_section(doc, "app/views/partials/voice_call.php",
        "PeerJS-cloud browser-to-browser voice call. Peer id = 'dropit-user-<uid>'. "
        "Handles outgoing + incoming + alt-id fallback + 30s no-answer timeout + "
        "mute + timer + drag-to-move popup. Releases mic on hang-up / pagehide.",
"""const selfId = `dropit-user-${MY_UID}`;
async function callUser(targetUid, targetName){
    const stream = await navigator.mediaDevices.getUserMedia({audio:true});
    const call = peer.call('dropit-user-' + targetUid, stream, {metadata:{from:meName}});
    call.on('stream', r => { remote.srcObject = r; startTimer(); });
    call.on('close', () => endCall());
    setTimeout(() => { if (!answered) call.close(); }, 30000);  // no-answer timeout
}""")

    file_section(doc, "app/views/partials/admin_trigger.php",
        "NEW. Hidden admin access trigger loaded on /login and /. Arms when "
        "the user enters the Konami code (↑↑↓↓←→←→) AND double-taps any element "
        "tagged data-admin-logo, both within a 10s window. On success it opens "
        "a modal with an admin email/password form that POSTs to /login.",
"""const KONAMI = ['ArrowUp','ArrowUp','ArrowDown','ArrowDown','ArrowLeft','ArrowRight','ArrowLeft','ArrowRight'];
const COMBO_WINDOW_MS = 10_000;
let konamiArmed=false, logoArmed=false;

function armKonami(){ konamiArmed=true; setTimeout(()=>konamiArmed=false, COMBO_WINDOW_MS); tryOpen(); }
function armLogo()  { logoArmed=true;   setTimeout(()=>logoArmed=false,   COMBO_WINDOW_MS); tryOpen(); }
function tryOpen() {
    if (konamiArmed && logoArmed) { showAdminModal(); }
}
// Arrow-key capture for Konami; click-count on data-admin-logo for logo-tap.""")


def _entry_views_section(doc):
    section_title(doc, "11. Entry & Public Views")

    file_section(doc, "app/views/landing.php",
        "Public home. UPDATED: sticky nav, mobile hamburger, responsive hero, "
        "and an INLINE public tracking form (POSTs to /track). Includes "
        "admin_trigger partial so the Konami+logo combo works here too.",
"""// Landing now embeds a public search form that takes any tracking number
// and routes to /track (handled by public_track.php).
// Also includes partials/admin_trigger.php.""")

    file_section(doc, "app/views/public_track.php",
        "NEW. Public parcel tracker — no login required. Returns masked status + "
        "origin/destination area (last 2 comma-separated tokens only) + timeline. "
        "Deliberately omits customer/driver names, full addresses, and live GPS "
        "map. Invites users to log in for full details.",
"""$q = trim($_GET['q'] ?? ($tracking ?? ''));
if ($q !== '') {
    $parcel = db()->table('parcels')
        ->select('id, tracking_number, status, pickup_address, recipient_address, created_at, updated_at')
        ->where('tracking_number', $q)->get();
    if ($parcel) $history = db()->table('parcel_history')
        ->select('status, notes, created_at')->where('parcel_id', $parcel['id'])
        ->order_by('created_at', 'DESC')->get_all();
}

function mask_address(?string $addr): string {
    // Return the last 2 comma-separated tokens (usually 'District, City').
    $parts = array_values(array_filter(array_map('trim', explode(',', $addr ?? '')), fn($s)=>$s!==''));
    return count($parts) <= 1 ? '(area hidden)' : implode(', ', array_slice($parts, -2));
}""")

    file_section(doc, "app/views/login.php",
        "Login form. admin_trigger partial loaded here too (both triggers work "
        "on the login page).",
        "// UI form — POSTs to actions/login_action.php.\n// admin_trigger.php "
        "is included for the hidden admin entry flow.")

    file_section(doc, "app/views/signup/choose.php",
        "Static fork: customer vs driver.",
        "// /signup links to /signup/customer or /signup/driver.")

    file_section(doc, "app/views/signup/form.php",
        "UPDATED: email-only OTP. The Send OTP + Verify calls hit "
        "/signup/otp/send and /signup/otp/verify with purpose=email. Submit stays "
        "disabled until email is verified. Phone is collected but never OTP-"
        "verified (since SMS is gone).",
"""// Send email OTP via POST /signup/otp/send { purpose:'email', identifier }
// Verify via POST /signup/otp/verify { purpose:'email', identifier, code }
// Phone input kept as user data but no OTP step.""")

    file_section(doc, "app/views/errors/403.php",
        "Pretty 403 page. Prints the $error string set by router middleware path.",
        "<p><?= $error ?></p>")
    file_section(doc, "app/views/errors/404.php",
        "Static 404 page.",
        "<h1>404 Page Not Found</h1>")
    file_section(doc, "app/views/errors/500.php",
        "500 page with $error text from exception handlers (dev-only useful).",
        "<p><?= $error ?></p>")


def _flow_section(doc):
    section_title(doc, "12. End-to-end Order Flow")
    para(doc, "Customer places, ships, and confirms a parcel:", bold=True)
    for line in [
        "1. Customer fills /customer/order — Leaflet pins pickup + dropoff, "
        "Nominatim reverse-geocodes, JS previews fare live.",
        "2. Form POSTs to actions/customer_order.php → fare calc, wallet debit "
        "(if method='wallet'), parcels + payments + parcel_history + "
        "wallet_transactions + activity_logs inserts + "
        "Notifier::notify_admin_new_order.",
        "3. If GCash, redirects to actions/pay_gcash_create → PayMongo → return "
        "handler flips payment_status='held'.",
        "4. Admin opens /admin/shipments/assign → actions/parcel_assign.php sets "
        "status='Dispatched' + fresh accept_token.",
        "5. Notifier::notify_driver_assigned emails the driver a 1-click accept "
        "link (/driver/quick-accept/{id}?t=<token>) + in-app ping.",
        "6. Driver Accepts → status='Accepted' + auto Taglish welcome chat "
        "message. Browser watchPosition() pushes GPS to /api/driver/location "
        "every 8s → driver_locations upsert.",
        "7. Customer's /customer/track polls /api/parcel/{tn}/track every 6-30s "
        "(backoff on errors) for live marker + ETA.",
        "8. Driver runs the status machine via /driver/update/{id} — multipart "
        "form, pickup photo required for 'Picked Up'. Mid-transit updates post "
        "to /driver/transit-photo/{id} (inserted as [IMG] rows in parcel_history).",
        "9. On Delivered via actions/pod_submit.php: photo + signature captured, "
        "escrow released (driver 90% wallet + admin 10% wallet via "
        "wallet_transactions), or cash_on_hand credited for COD.",
        "10. Customer notified at every flip via Notifier::notify_customer_status "
        "(in-app + email). Can rate at /customer/rate/{id}, request a return at "
        "/customer/return/{id}, or view /customer/receipt/{tn}.",
        "11. Dispatch safety net: sweep_stale_dispatches() runs on every "
        "driver/dashboard hit — any parcel stuck in 'Dispatched' for ≥15 min "
        "rolls back to 'Pending' with a consolidated admin in-app ping.",
    ]:
        doc.add_paragraph(line, style='List Bullet')


# =====================================================
#  DOC 2 — "DropIT SQL.docx"
# =====================================================
def build_sql_doc():
    doc = Document()
    for s in doc.sections:
        s.left_margin=s.right_margin=s.top_margin=s.bottom_margin = Inches(0.7)

    t = doc.add_heading("Drop IT — SQL Reference & Query Walkthrough", level=0)
    t.alignment = WD_ALIGN_PARAGRAPH.CENTER
    para(doc, "Every Drop IT table, every query, every join. Database `dropit` "
              "(MariaDB/MySQL, utf8mb4). All queries use prepared statements with "
              "? placeholders.", italic=True)

    _sql_schema(doc)
    _sql_queries(doc)
    _sql_create_table(doc)
    _sql_erd(doc)
    _sql_use_case(doc)

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    path = OUT_DIR / "DropIT SQL.docx"
    doc.save(str(path))
    return path


def _sql_schema(doc):
    section_title(doc, "1. Schema Map")
    for line in [
        "users              — auth + profile + wallet + availability + rating",
        "parcels            — one row per delivery; FK customer_id + driver_id → users",
        "parcel_history     — status timeline per parcel (CASCADE); now also holds [IMG] transit/pickup photos",
        "driver_locations   — 1 row per driver, upserted GPS; FK driver_id → users (CASCADE)",
        "messages           — chat per parcel",
        "ratings            — one per parcel (UNIQUE parcel_id)",
        "favorite_addresses — customer saved pins",
        "payments           — one row per payment attempt",
        "wallet_transactions— money ledger (CASCADE)",
        "otps               — email verification codes, 10-min TTL (phone purpose disabled)",
        "notifications      — in-app pings per user",
        "notification_log   — outbound comms audit (email + in_app — SMS unused now)",
        "reports            — user support tickets",
        "returns            — customer return requests",
        "sos_alerts         — driver emergency pings",
        "activity_logs      — audit trail",
        "settings           — key/value runtime config",
    ]:
        doc.add_paragraph(line, style='List Bullet')

    para(doc, ""); para(doc, "Foreign keys:", bold=True)
    for line in [
        "parcels.customer_id → users.id (ON DELETE SET NULL)",
        "parcels.driver_id   → users.id (ON DELETE SET NULL)",
        "parcel_history.parcel_id → parcels.id (ON DELETE CASCADE)",
        "driver_locations.driver_id → users.id (ON DELETE CASCADE)",
        "wallet_transactions.user_id → users.id (ON DELETE CASCADE)",
    ]:
        doc.add_paragraph(line, style='List Bullet')


def _sql_queries(doc):
    section_title(doc, "2. Auth & Signup Queries")
    sql_block(doc, "Login lookup — login_action.php",
        "Single-row email lookup (UNIQUE index → O(1) seek).",
"""SELECT id, username, email, password, role, full_name, is_active
FROM users WHERE email = ?;""")

    sql_block(doc, "Signup uniqueness triple-check — signup_submit.php",
        "Three separate SELECTs so the error tells the user which field collided.",
"""SELECT id FROM users WHERE email    = ?;
SELECT id FROM users WHERE username = ?;
SELECT id FROM users WHERE phone    = ?;""")

    sql_block(doc, "Email OTP verified check — signup_submit.php",
        "Must be at least one verified row for this browser session before insert.",
"""SELECT COUNT(*) FROM otps
WHERE session_id = ? AND identifier = ? AND purpose = 'email' AND verified = 1;""")

    sql_block(doc, "Create new user — signup_submit.php", "",
"""INSERT INTO users (username, email, password, role, full_name, phone,
                     phone_verified, email_verified, wallet_balance)
VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?);  -- 500.00 for customer else 0.00""")

    sql_block(doc, "Clean up OTPs after signup", "", "DELETE FROM otps WHERE session_id = ?;")

    sql_block(doc, "Rate-limit OTP sends — otp_send.php",
        "Max 5 per (session, identifier, purpose) per 10 minutes.",
"""SELECT COUNT(*) FROM otps
WHERE session_id = ? AND identifier = ? AND purpose = ?
  AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE);""")

    sql_block(doc, "Insert new OTP — otp_send.php",
        "Delete older unverified codes first; phone purpose blocked at app layer.",
"""DELETE FROM otps
 WHERE session_id = ? AND identifier = ? AND purpose = ? AND verified = 0;

INSERT INTO otps (purpose, identifier, code, session_id, expires_at)
VALUES ('email', ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE));""")

    sql_block(doc, "Verify OTP — otp_verify.php",
        "Latest unverified row; timing-safe compare.",
"""SELECT id, code, expires_at
FROM otps
WHERE session_id = ? AND identifier = ? AND purpose = ? AND verified = 0
ORDER BY id DESC LIMIT 1;

UPDATE otps SET verified = 1 WHERE id = ?;""")

    section_title(doc, "3. Parcel Lifecycle Queries")

    sql_block(doc, "Create parcel (customer order)",
        "Two-step — temp tracking first because MySQL can't reference new id "
        "inside the same INSERT.",
"""INSERT INTO parcels (tracking_number, customer_id, sender_name, sender_address,
    pickup_address, pickup_lat, pickup_lng,
    recipient_name, recipient_phone, recipient_address, dropoff_lat, dropoff_lng,
    weight, notes, product_name, product_description,
    status, is_customer_order, vehicle_type,
    distance_km, amount, admin_fee, driver_earnings,
    payment_status, payment_method, accept_token)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        'Pending', 1, 'Tricycle',
        ?, ?, ?, ?,
        ?, ?, ?);

UPDATE parcels SET tracking_number = CONCAT('DROPIT', LPAD(?, 5, '0')) WHERE id = ?;""")

    sql_block(doc, "Admin dispatch", "Always refreshes accept_token for 1-click email.",
"""UPDATE parcels SET driver_id = ?, status = ?, accept_token = ? WHERE id = ?;""")

    sql_block(doc, "Parcel history — every status change", "Append-only.",
"""INSERT INTO parcel_history (parcel_id, status, notes, updated_by)
VALUES (?, ?, ?, ?);""")

    sql_block(doc, "Transit / pickup photo — NEW",
        "Photos piggyback on parcel_history. notes starts with '[IMG]<path>|<caption>' "
        "which both the admin parcel_detail view and customer track page detect and "
        "render inline.",
"""INSERT INTO parcel_history (parcel_id, status, notes, updated_by)
VALUES (?, ?, '[IMG]resources/transit/pickup_42_1714.jpg|Proof of pickup', ?);""")

    sql_block(doc, "Auto-revert stale Dispatched — NEW (sweep_stale_dispatches)",
        "Lazy cron called from driver dashboard. Parcels idle ≥ N min bounce back to Pending.",
"""SELECT id, tracking_number, driver_id FROM parcels
 WHERE status = 'Dispatched' AND updated_at < (NOW() - INTERVAL ? MINUTE);

UPDATE parcels SET driver_id = NULL, status = 'Pending' WHERE id = ?;

INSERT INTO parcel_history (parcel_id, status, notes, updated_by)
VALUES (?, 'Pending', 'Auto-revert: no driver response within N min.', NULL);""")

    sql_block(doc, "Cancel parcel with refund — parcel_cancel.php", "",
"""UPDATE parcels SET status = 'Cancelled' WHERE id = ?;

-- If fare was in escrow:
UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?;
INSERT INTO wallet_transactions (user_id, parcel_id, amount, type, description, balance_after)
VALUES (?, ?, ?, 'refund', ?, ?);
UPDATE parcels SET payment_status = 'refunded' WHERE id = ?;""")

    sql_block(doc, "Driver accept — driver_accept.php",
        "Flip status, append history, auto-post welcome message.",
"""UPDATE parcels SET status = 'Accepted' WHERE id = ?;
INSERT INTO parcel_history (...) VALUES (?, 'Accepted', 'Driver accepted.', ?);
INSERT INTO messages (parcel_id, sender_id, sender_role, body) VALUES (?, ?, 'driver', ?);""")

    sql_block(doc, "Driver update status + settlement",
        "Picked Up branch (NEW) requires a pickup photo → second history row "
        "with [IMG]. Delivered branch releases escrow; Cancelled refunds.",
"""UPDATE parcels SET status = ? WHERE id = ?;
INSERT INTO parcel_history (parcel_id, status, notes, updated_by) VALUES (?, ?, ?, ?);
-- Extra [IMG] history row for pickup photo when status='Picked Up'.

-- On Delivered with escrow:
UPDATE users SET wallet_balance = ? WHERE id = ?;  -- credit driver 90%
INSERT INTO wallet_transactions (..., type, ...) VALUES (..., 'delivery_earning', ...);

UPDATE users SET wallet_balance = ? WHERE id = ?;  -- credit admin 10%
INSERT INTO wallet_transactions (..., type, ...) VALUES (..., 'admin_commission', ...);

UPDATE parcels SET payment_status = 'settled' WHERE id = ?;""")

    sql_block(doc, "POD submit", "Same settlement logic + photo & signature stored.",
"""UPDATE parcels
SET pod_photo = ?, pod_signature = ?, pod_receiver = ?, status = 'Delivered'
WHERE id = ?;

-- COD branch adds cash to users.cash_on_hand + ledger row type='cod_collected'.""")

    sql_block(doc, "COD collect — pay_cod_collect.php", "",
"""UPDATE payments SET status = 'paid' WHERE parcel_id = ? AND method = 'cod';
UPDATE parcels SET payment_status = 'settled' WHERE id = ?;""")

    sql_block(doc, "GCash create — pay_gcash_create.php", "",
"""INSERT INTO payments (parcel_id, method, amount, status, provider_ref, checkout_url)
VALUES (?, 'gcash', ?, 'pending', ?, ?);
UPDATE parcels SET payment_method = 'gcash', payment_ref = ? WHERE id = ?;""")

    sql_block(doc, "GCash paid — pay_gcash_return.php", "",
"""UPDATE parcels SET payment_status = 'held' WHERE id = ?;
UPDATE payments SET status = 'paid' WHERE parcel_id = ? AND method = 'gcash';""")

    sql_block(doc, "Wallet top-up — NEW customer_cash_in_return.php",
        "PayMongo-confirmed wallet credit + ledger + (optional) email receipt.",
"""UPDATE users SET wallet_balance = ? WHERE id = ?;
INSERT INTO wallet_transactions (user_id, amount, type, description, balance_after)
VALUES (?, ?, 'topup', ?, ?);
INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'wallet_topup', ?);""")

    section_title(doc, "4. Driver GPS & Fleet Queries")
    sql_block(doc, "Upsert driver location", "",
"""SELECT driver_id FROM driver_locations WHERE driver_id = ?;
UPDATE driver_locations SET lat=?, lng=?, accuracy=? WHERE driver_id = ?;
-- else:
INSERT INTO driver_locations (driver_id, lat, lng, accuracy) VALUES (?, ?, ?, ?);""")

    sql_block(doc, "Recent driver pins (last 5 min)", "",
"""SELECT driver_id, lat, lng, updated_at FROM driver_locations
WHERE updated_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE);""")

    sql_block(doc, "Drivers roster + GPS freshness — admin/drivers_map.php",
        "LEFT JOIN so drivers without a GPS row still show.",
"""SELECT u.id, u.username, u.full_name, u.phone, u.availability,
       u.rating_avg, u.rating_count,
       dl.lat, dl.lng, dl.accuracy, dl.updated_at AS loc_updated,
       TIMESTAMPDIFF(MINUTE, dl.updated_at, NOW()) AS mins_ago
FROM users u
LEFT JOIN driver_locations dl ON dl.driver_id = u.id
WHERE u.role = 'driver' AND u.is_active = 1
ORDER BY dl.updated_at DESC;""")

    sql_block(doc, "Toggle availability", "",
"""UPDATE users SET availability = ? WHERE id = ?;""")

    section_title(doc, "5. Chat & Messaging Queries")
    sql_block(doc, "Messages since cursor — /api/parcel/{id}/messages (GET)", "",
"""SELECT messages.id, messages.sender_id, messages.sender_role,
       messages.body, messages.created_at,
       users.full_name, users.username
FROM messages
LEFT JOIN users ON users.id = messages.sender_id
WHERE messages.parcel_id = ? AND messages.id > ?
ORDER BY messages.id ASC LIMIT 100;""")

    sql_block(doc, "Mark read on poll",
        "Every GET flips is_read=1 for messages not sent by me.",
"""UPDATE messages SET is_read = 1
WHERE parcel_id = ? AND sender_id <> ? AND is_read = 0;""")

    sql_block(doc, "Insert message (POST)", "",
"""INSERT INTO messages (parcel_id, sender_id, sender_role, body) VALUES (?, ?, ?, ?);""")

    sql_block(doc, "Admin thread list — admin/messages.php",
        "INNER JOIN with MAX(id) subquery for one row per parcel with latest msg.",
"""SELECT p.id, p.tracking_number, p.is_walkin, p.status, p.customer_id, p.driver_id,
       m.body AS last_body, m.created_at AS last_at, m.sender_role AS last_role,
       (SELECT COUNT(*) FROM messages mm WHERE mm.parcel_id = p.id
              AND mm.is_read = 0 AND mm.sender_id <> ?) AS unread,
       (SELECT u.full_name FROM users u WHERE u.id = p.driver_id)   AS driver_name,
       (SELECT u.full_name FROM users u WHERE u.id = p.customer_id) AS customer_name
FROM parcels p
INNER JOIN messages m ON m.id = (SELECT MAX(id) FROM messages WHERE parcel_id = p.id)
ORDER BY m.id DESC LIMIT 200;""")

    sql_block(doc, "Customer thread list — customer/messages.php",
        "EXISTS + subqueries instead of JOIN — simpler for \"one row per parcel\".",
"""SELECT p.id, p.tracking_number, p.status,
       (SELECT body       FROM messages WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_body,
       (SELECT created_at FROM messages WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_at,
       (SELECT sender_role FROM messages WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_role,
       (SELECT COUNT(*) FROM messages WHERE parcel_id = p.id AND is_read = 0 AND sender_id <> ?) AS unread,
       (SELECT u.full_name FROM users u WHERE u.id = p.driver_id) AS driver_name
FROM parcels p
WHERE p.customer_id = ? AND EXISTS (SELECT 1 FROM messages WHERE parcel_id = p.id)
ORDER BY last_at DESC;""")

    sql_block(doc, "Driver thread list — driver/messages.php",
        "Mirror of customer version; joins customer name instead.",
"""-- Same shape as customer thread list but: WHERE p.driver_id = ?
-- and: (SELECT u.full_name FROM users u WHERE u.id = p.customer_id) AS customer_name""")

    sql_block(doc, "Admin unread count", "",
"""SELECT COUNT(*) FROM messages m JOIN parcels p ON p.id = m.parcel_id
WHERE m.is_read = 0 AND m.sender_id <> ?
  AND (p.is_walkin = 1 OR p.customer_id IS NULL OR m.sender_role = 'driver');""")

    sql_block(doc, "Customer/driver unread count", "",
"""SELECT COUNT(*) FROM messages m JOIN parcels p ON p.id = m.parcel_id
WHERE m.is_read = 0 AND m.sender_id <> ?
  AND ( (p.customer_id = ? AND m.sender_role IN ('driver','admin'))
     OR (p.driver_id   = ? AND m.sender_role IN ('customer','admin')) );""")

    sql_block(doc, "Return /confirm or /reject — messages.php POST",
        "Admin typing '/confirm' or '/reject' mutates the return row.",
"""UPDATE returns SET status = ?, admin_note = ? WHERE id = ?;
INSERT INTO messages (parcel_id, sender_id, sender_role, body) VALUES (?, ?, 'admin', ?);
-- On /confirm:
UPDATE parcels SET status = 'Return Pending' WHERE id = ?;""")

    section_title(doc, "6. Tracking & Analytics Queries")
    sql_block(doc, "Parcel + history + driver GPS — customer/track.php", "",
"""SELECT id, tracking_number, status, sender_name,
       recipient_name, recipient_address,
       pickup_address, pickup_lat, pickup_lng, dropoff_lat, dropoff_lng,
       driver_id, customer_id, created_at, updated_at,
       amount, payment_method, payment_status
FROM parcels WHERE tracking_number = ?;

SELECT status, notes, created_at FROM parcel_history
WHERE parcel_id = ? ORDER BY created_at DESC;

SELECT lat, lng, accuracy, updated_at FROM driver_locations WHERE driver_id = ?;""")

    sql_block(doc, "Public track (guest) — public_track.php",
        "No joins. Minimal fields; addresses masked in PHP layer.",
"""SELECT id, tracking_number, status,
       pickup_address, recipient_address, created_at, updated_at
FROM parcels WHERE tracking_number = ?;""")

    sql_block(doc, "Customer dashboard active list", "",
"""SELECT id, tracking_number, status, recipient_address, updated_at
FROM parcels WHERE customer_id = ?
  AND status <> 'Delivered' AND status <> 'Cancelled'
ORDER BY updated_at DESC LIMIT 3;""")

    sql_block(doc, "Customer track — Past tab (NEW)", "",
"""SELECT id, tracking_number, status, recipient_address, updated_at
FROM parcels
WHERE customer_id = ? AND status IN ('Delivered','Cancelled')
ORDER BY updated_at DESC LIMIT 50;""")

    sql_block(doc, "Admin daily analytics", "GROUP BY day, CASE WHEN folds.",
"""SELECT DATE(created_at) d, COUNT(*) c, SUM(amount) rev,
       SUM(CASE WHEN status='Delivered' THEN 1 ELSE 0 END) delivered,
       SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END) cancelled
FROM parcels WHERE DATE(created_at) BETWEEN ? AND ?
GROUP BY DATE(created_at) ORDER BY d ASC;""")

    sql_block(doc, "Payment mix", "",
"""SELECT payment_method, COUNT(*) c, SUM(amount) rev
FROM parcels WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY payment_method;""")

    sql_block(doc, "Top drivers leaderboard",
        "LEFT JOIN so drivers with zero trips still appear. Filter in ON clause, "
        "not WHERE, so inactive drivers aren't silently dropped.",
"""SELECT u.id, u.full_name, u.username,
       COUNT(p.id) trips, SUM(p.driver_earnings) earnings,
       u.rating_avg, u.rating_count
FROM users u
LEFT JOIN parcels p
  ON p.driver_id = u.id
 AND p.status = 'Delivered'
 AND DATE(p.created_at) BETWEEN ? AND ?
WHERE u.role = 'driver' AND u.is_active = 1
GROUP BY u.id, u.full_name, u.username, u.rating_avg, u.rating_count
ORDER BY trips DESC LIMIT 10;""")

    sql_block(doc, "Printable full parcel log — report_print.php / export CSV",
        "Two LEFT JOINs aliased u (customer) and d (driver).",
"""SELECT p.tracking_number, p.status, p.payment_method, p.payment_status,
       p.amount, p.admin_fee, p.driver_earnings, p.distance_km,
       p.created_at, p.updated_at,
       u.full_name AS customer, d.full_name AS driver
FROM parcels p
LEFT JOIN users u ON u.id = p.customer_id
LEFT JOIN users d ON d.id = p.driver_id
WHERE DATE(p.created_at) BETWEEN ? AND ?
ORDER BY p.created_at DESC;""")

    sql_block(doc, "Admin dashboard counters", "",
"""SELECT COUNT(*) FROM users   WHERE role <> 'admin';
SELECT COUNT(*) FROM users   WHERE role = 'driver'   AND is_active = 1;
SELECT COUNT(*) FROM parcels WHERE status = 'Pending';
SELECT COUNT(*) FROM parcels WHERE status = 'In Transit';
SELECT COUNT(*) FROM parcels WHERE status = 'Delivered';""")

    sql_block(doc, "Admin history — shipments tab", "Optional status filter via concat.",
"""SELECT p.id, p.tracking_number, p.status, p.amount, p.payment_method, p.payment_status,
       p.recipient_name, p.recipient_address, p.created_at, p.updated_at, p.is_walkin,
       c.full_name AS customer_name, d.full_name AS driver_name
FROM parcels p
LEFT JOIN users c ON c.id = p.customer_id
LEFT JOIN users d ON d.id = p.driver_id
[WHERE p.status = ?]
ORDER BY p.created_at DESC LIMIT 500;""")

    sql_block(doc, "Driver own analytics — driver/analytics.php", "",
"""SELECT DATE(updated_at) d, COUNT(*) c, SUM(driver_earnings) earn, SUM(distance_km) km
FROM parcels WHERE driver_id = ? AND status = 'Delivered'
  AND DATE(updated_at) >= ?
GROUP BY DATE(updated_at) ORDER BY d ASC;

SELECT COUNT(*) c, SUM(driver_earnings) e, SUM(distance_km) km
FROM parcels WHERE driver_id = ? AND status = 'Delivered';

SELECT r.stars, r.review, r.created_at, u.full_name
FROM ratings r LEFT JOIN users u ON u.id = r.customer_id
WHERE r.driver_id = ? ORDER BY r.id DESC LIMIT 5;""")

    sql_block(doc, "Refresh driver rating — helper", "",
"""SELECT AVG(stars) a, COUNT(*) c FROM ratings WHERE driver_id = ?;
UPDATE users SET rating_avg = ?, rating_count = ? WHERE id = ?;""")

    section_title(doc, "7. Notifications, Reports, Misc")
    sql_block(doc, "In-app notify", "",
"""INSERT INTO notifications (user_id, parcel_id, title, message) VALUES (?, ?, ?, ?);""")

    sql_block(doc, "Recent + unread — /api/notifications/unread", "",
"""SELECT id, title, message, is_read, created_at, parcel_id
FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 10;""")

    sql_block(doc, "Auto-mark read on inbox view (NEW) / customer notifications", "",
"""UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0;""")

    sql_block(doc, "Notification log insert (Notifier::log)", "",
"""INSERT INTO notification_log (channel, recipient, subject, body, status, response)
VALUES (?, ?, ?, ?, ?, ?);""")

    sql_block(doc, "Notification log browser — admin/notifications_log.php",
        "Filter only email + in_app now (SMS removed).",
"""SELECT id, channel, recipient, subject, body, status, response, created_at
FROM notification_log
WHERE status <> 'disabled' AND status <> 'dry_run'
  [AND channel IN ('email','in_app')]
  [AND recipient LIKE ?]
ORDER BY created_at DESC LIMIT 200;""")

    sql_block(doc, "Submit + list reports", "",
"""INSERT INTO reports (user_id, subject, message) VALUES (?, ?, ?);

SELECT id, user_id, subject, message, status, created_at
FROM reports ORDER BY created_at DESC;

SELECT id, username, full_name, role FROM users WHERE id IN (?, ?, ...);

UPDATE reports SET status = ? WHERE id = ?;""")

    sql_block(doc, "Favorites CRUD", "",
"""INSERT INTO favorite_addresses (user_id, label, address, lat, lng) VALUES (?, ?, ?, ?, ?);
DELETE FROM favorite_addresses WHERE id = ? AND user_id = ?;      -- own rows only
SELECT id, label, address, lat, lng FROM favorite_addresses WHERE user_id = ? ORDER BY id DESC;""")

    sql_block(doc, "Settings upsert — set_setting()", "",
"""SELECT setting_key FROM settings WHERE setting_key = ?;
UPDATE settings SET setting_value = ? WHERE setting_key = ?;
-- or
INSERT INTO settings (setting_key, setting_value) VALUES (?, ?);""")

    sql_block(doc, "SOS alert", "",
"""INSERT INTO sos_alerts (driver_id, parcel_id, lat, lng, note) VALUES (?, ?, ?, ?, ?);""")

    sql_block(doc, "Return request", "",
"""INSERT INTO returns (parcel_id, customer_id, reason, status)
VALUES (?, ?, ?, 'Requested');
INSERT INTO messages (parcel_id, sender_id, sender_role, body) VALUES (?, ?, 'customer', ?);""")

    section_title(doc, "8. Seed & Reset")
    sql_block(doc, "Seed (wipe + re-insert) — database/seed.php",
        "UPDATED: Semaphore/SMS keys removed from the default $settings array.",
"""SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE wallet_transactions; TRUNCATE TABLE otps;
TRUNCATE TABLE notification_log; TRUNCATE TABLE driver_locations;
TRUNCATE TABLE activity_logs; TRUNCATE TABLE notifications;
TRUNCATE TABLE reports; TRUNCATE TABLE parcel_history;
TRUNCATE TABLE parcels; TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO users (...admin...);
-- + 3 drivers + 2 customers (wallet_balance = 500).

TRUNCATE TABLE settings;
INSERT INTO settings VALUES
  ('admin_email','admin@drop-it.dev'),
  ('admin_phone','09000000000'),
  ('smtp_host',''), ('smtp_port','587'), ('smtp_user',''), ('smtp_pass',''),
  ('smtp_from','no-reply@drop-it.dev');""")

    section_title(doc, "9. JOIN cheat sheet")
    para(doc, "Drop IT uses four JOIN shapes:", italic=True)
    for line in [
        "(a) No join — single-row lookup by PK/UNIQUE (users by id, parcels by "
        "tracking_number).",
        "(b) Two-LEFT-JOIN enrichment — parcels + customer name + driver name. "
        "Used by history, analytics export, report_print, admin/messages header.",
        "(c) LEFT JOIN users → driver_locations — keep drivers with no GPS on "
        "the fleet map.",
        "(d) INNER JOIN messages ↔ parcels with MAX(id) correlated subquery for "
        "the admin inbox.",
    ]:
        doc.add_paragraph(line, style='List Bullet')

    para(doc, ""); para(doc, "LEFT vs INNER — why it matters:", bold=True)
    for line in [
        "LEFT JOIN users c ON c.id = p.customer_id — keeps walk-in parcels "
        "(customer_id IS NULL). INNER would drop them.",
        "LEFT JOIN driver_locations dl — keeps drivers with no pin.",
        "LEFT JOIN parcels p ON p.driver_id = u.id AND p.status='Delivered' — "
        "filter is in the ON clause (not WHERE) so drivers with zero deliveries "
        "still appear on the leaderboard.",
        "INNER JOIN messages m ON m.id = (SELECT MAX(id) FROM messages WHERE "
        "parcel_id = p.id) — only parcels that have at least one message show "
        "up in the admin inbox.",
    ]:
        doc.add_paragraph(line, style='List Bullet')


def _sql_create_table(doc):
    section_title(doc, "10. Full CREATE TABLE — the whole database")
    para(doc, "Copy-paste ready DDL (schema.sql + v2+v3+v4 migrations consolidated).",
         italic=True)
    code_block(doc, """CREATE DATABASE IF NOT EXISTS dropit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dropit;

CREATE TABLE users (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    username        VARCHAR(100) UNIQUE NOT NULL,
    email           VARCHAR(150) UNIQUE NOT NULL,
    password        VARCHAR(255) NOT NULL,
    role            ENUM('admin','customer','driver') NOT NULL DEFAULT 'customer',
    full_name       VARCHAR(150),
    phone           VARCHAR(30),
    avatar          VARCHAR(255),
    is_active       TINYINT(1) DEFAULT 1,
    wallet_balance  DECIMAL(10,2) DEFAULT 0,
    cash_on_hand    DECIMAL(10,2) DEFAULT 0,
    phone_verified  TINYINT(1) DEFAULT 0,
    email_verified  TINYINT(1) DEFAULT 0,
    availability    VARCHAR(20) DEFAULT 'offline',
    referral_code   VARCHAR(20) NULL,
    referred_by     INT NULL,
    rating_avg      DECIMAL(3,2) DEFAULT 0,
    rating_count    INT DEFAULT 0,
    theme           VARCHAR(10) DEFAULT 'dark',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE parcels (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    tracking_number     VARCHAR(40) UNIQUE NOT NULL,
    customer_id         INT NULL,
    driver_id           INT NULL,
    sender_name         VARCHAR(150),
    sender_address      TEXT,
    pickup_address      TEXT NULL,
    pickup_lat          DECIMAL(10,7) NULL,
    pickup_lng          DECIMAL(10,7) NULL,
    recipient_name      VARCHAR(150),
    recipient_phone     VARCHAR(30),
    recipient_address   TEXT,
    dropoff_lat         DECIMAL(10,7) NULL,
    dropoff_lng         DECIMAL(10,7) NULL,
    weight              DECIMAL(8,2) DEFAULT 0,
    notes               TEXT,
    product_name        VARCHAR(200) NULL,
    product_description TEXT NULL,
    vehicle_type        VARCHAR(30) DEFAULT 'Tricycle',
    distance_km         DECIMAL(8,3) DEFAULT 0,
    amount              DECIMAL(10,2) DEFAULT 0,
    admin_fee           DECIMAL(10,2) DEFAULT 0,
    driver_earnings     DECIMAL(10,2) DEFAULT 0,
    payment_status      VARCHAR(30) DEFAULT 'unpaid',
    payment_method      VARCHAR(20) DEFAULT 'wallet',
    payment_ref         VARCHAR(120) NULL,
    eta_minutes         INT NULL,
    is_walkin           TINYINT(1) DEFAULT 0,
    is_customer_order   TINYINT(1) DEFAULT 0,
    pod_photo           VARCHAR(255) NULL,
    pod_signature       MEDIUMTEXT NULL,
    pod_receiver        VARCHAR(150) NULL,
    accept_token        VARCHAR(64) NULL,
    status              VARCHAR(50) NOT NULL DEFAULT 'Pending',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_parcel_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_parcel_driver   FOREIGN KEY (driver_id)   REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE parcel_history (
    -- notes may contain '[IMG]<path>|<caption>' for transit / pickup photos.
    id          INT PRIMARY KEY AUTO_INCREMENT,
    parcel_id   INT NOT NULL,
    status      VARCHAR(50) NOT NULL,
    notes       TEXT,
    updated_by  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_parcel FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE
);

CREATE TABLE driver_locations (
    driver_id   INT PRIMARY KEY,
    lat         DECIMAL(10,7) NOT NULL,
    lng         DECIMAL(10,7) NOT NULL,
    accuracy    DECIMAL(8,2) NULL,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_drvloc_user FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE messages (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    parcel_id   INT NOT NULL,
    sender_id   INT NOT NULL,
    sender_role VARCHAR(20) NOT NULL,
    body        TEXT NOT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_parcel  (parcel_id),
    INDEX idx_created (created_at)
);

CREATE TABLE ratings (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    parcel_id   INT NOT NULL UNIQUE,
    driver_id   INT NOT NULL,
    customer_id INT NOT NULL,
    stars       TINYINT NOT NULL,
    review      TEXT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_driver (driver_id)
);

CREATE TABLE payments (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    parcel_id     INT NOT NULL,
    method        VARCHAR(20) NOT NULL,
    amount        DECIMAL(10,2) NOT NULL,
    status        VARCHAR(30) DEFAULT 'pending',
    provider_ref  VARCHAR(120) NULL,
    checkout_url  TEXT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_parcel (parcel_id)
);

CREATE TABLE wallet_transactions (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    user_id       INT NOT NULL,
    parcel_id     INT NULL,
    amount        DECIMAL(10,2) NOT NULL,
    type          VARCHAR(40),
    description   TEXT,
    balance_after DECIMAL(10,2),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    CONSTRAINT fk_wtx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE otps (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    purpose     ENUM('phone','email') NOT NULL,  -- only 'email' is used now
    identifier  VARCHAR(200) NOT NULL,
    code        VARCHAR(10) NOT NULL,
    session_id  VARCHAR(64),
    expires_at  TIMESTAMP,
    verified    TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session    (session_id),
    INDEX idx_identifier (identifier, purpose)
);

CREATE TABLE notifications (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    user_id     INT NOT NULL,
    parcel_id   INT NULL,
    title       VARCHAR(200),
    message     TEXT,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notification_log (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    channel     ENUM('email','sms','in_app') NOT NULL,  -- 'sms' kept for back-compat; never written
    recipient   VARCHAR(200),
    subject     VARCHAR(200),
    body        TEXT,
    status      VARCHAR(30) DEFAULT 'queued',
    response    TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE favorite_addresses (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    user_id     INT NOT NULL,
    label       VARCHAR(60) NOT NULL,
    address     TEXT NOT NULL,
    lat         DECIMAL(10,7) NULL,
    lng         DECIMAL(10,7) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
);

CREATE TABLE reports (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    user_id     INT NOT NULL,
    subject     VARCHAR(200),
    message     TEXT,
    status      VARCHAR(30) DEFAULT 'Open',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE returns (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    parcel_id    INT NOT NULL,
    customer_id  INT NOT NULL,
    reason       TEXT,
    status       VARCHAR(30) DEFAULT 'Requested',
    admin_note   TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sos_alerts (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    driver_id   INT NOT NULL,
    parcel_id   INT NULL,
    lat         DECIMAL(10,7) NULL,
    lng         DECIMAL(10,7) NULL,
    note        TEXT NULL,
    status      VARCHAR(20) DEFAULT 'open',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE activity_logs (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    user_id     INT NULL,
    action      VARCHAR(255),
    details     TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value TEXT
);

-- Email-only defaults (no Semaphore / SMS keys).
INSERT INTO settings (setting_key, setting_value) VALUES
  ('admin_email',         'admin@drop-it.dev'),
  ('admin_phone',         '09000000000'),
  ('smtp_host',           ''),
  ('smtp_port',           '587'),
  ('smtp_user',           ''),
  ('smtp_pass',           ''),
  ('smtp_from',           'no-reply@drop-it.dev'),
  ('paymongo_secret_key', ''),
  ('paymongo_public_key', ''),
  ('app_secret',          SUBSTRING(MD5(RAND()), 1, 32));""")


def _sql_erd(doc):
    section_title(doc, "11. Entity Relationship Diagram (ERD)")
    para(doc, "Arrows point from 1 to N. Walk-in parcels have customer_id NULL; "
              "drivers with no GPS row simply don't appear on the map (modeled "
              "as optional 1:1).", italic=True)
    if ERD_PNG.exists():
        doc.add_picture(str(ERD_PNG), width=Inches(7.0))
    para(doc, ""); para(doc, "Relationships:", bold=True)
    for line in [
        "users 1 → N parcels (via customer_id AND driver_id) — ON DELETE SET NULL",
        "parcels 1 → N parcel_history — CASCADE. Now includes [IMG] pickup/transit photos.",
        "users 1 → 1 driver_locations — upserted GPS",
        "parcels 1 → N messages — chat threads",
        "parcels 1 → 1 ratings — UNIQUE parcel_id",
        "parcels 1 → N payments — multiple attempts allowed",
        "users 1 → N wallet_transactions — ledger (CASCADE)",
        "users 1 → N notifications / favorite_addresses / sos_alerts / activity_logs",
        "parcels 1 → 1 returns",
    ]:
        doc.add_paragraph(line, style='List Bullet')


def _sql_use_case(doc):
    section_title(doc, "12. Use-Case Diagram")
    para(doc, "Four internal actors + one external (PayMongo). Guest actor has "
              "one public use case: masked parcel tracking.", italic=True)
    if UC_PNG.exists():
        doc.add_picture(str(UC_PNG), width=Inches(7.0))
    para(doc, ""); para(doc, "Use cases by actor:", bold=True)

    for actor, items in [
        ("Guest (public)", [
            "Public parcel tracking — masked addresses, no driver map, no chat.",
        ]),
        ("Customer", [
            "Sign up + verify email OTP (email only — no SMS)",
            "Login / Logout",
            "Place an order (map pins + live fare preview)",
            "Track parcel — active / past tabs + live driver marker",
            "Cash in wallet via GCash (NEW)",
            "Pay (wallet / COD / GCash)",
            "Chat + voice call with driver",
            "Rate driver after delivery",
            "Request return post-delivery",
            "Report issue / contact support",
            "Manage favorite addresses + profile + theme",
        ]),
        ("Driver", [
            "Accept / decline dispatch (in-app or 1-click email link)",
            "Update status (Picked Up requires proof photo)",
            "Send mid-transit photos to the customer (NEW)",
            "Capture POD + collect COD",
            "Toggle availability online/break/offline",
            "Send SOS alert",
            "View own analytics",
            "Chat + voice call customer",
        ]),
        ("Admin", [
            "Manage user accounts",
            "Create walk-in parcel",
            "Dispatch driver + cancel (auto-refund)",
            "Notify driver via in-app + email (no SMS)",
            "View fleet live map",
            "Inbox — categorized notifications (NEW)",
            "Read + reply any chat thread",
            "Confirm / reject returns via chat commands",
            "Analytics + CSV export + printable reports",
            "Handle user support tickets (emails the admin team)",
            "Configure SMTP (Brevo) + PayMongo settings",
            "Audit activity log",
        ]),
        ("PayMongo (external)", [
            "Process GCash source creation (order pay + wallet top-up)",
            "Confirm GCash payment status via return URL",
        ]),
    ]:
        para(doc, actor, bold=True)
        for i in items:
            doc.add_paragraph(i, style='List Bullet')
        para(doc, "")


if __name__ == "__main__":
    p1 = build_main_doc();  print("wrote", p1)
    p2 = build_sql_doc();   print("wrote", p2)
