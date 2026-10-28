<?php
defined('APP_ROOT') OR exit('No direct script access allowed');
/**
 * Global Helper Functions
 */
session_start();

//get base url
function base_url(): string
{
    $scheme = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ) ? 'https' : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');

    return $scheme . '://' . $host . ($path ? $path : '') . '/';
}


//generate url based on BASE_URL
function url(string $path = ''): string
{
    return rtrim(base_url(), '/') . '/' . ltrim($path, '/');
}

//generate csrf field
function csrf_field() {
    $router = new Router();
    return $router->csrf_field();
}

//set session flash data
function set_flash(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

//get session flash data
function get_flash(string $key): ?string {
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

//json response
function json_response($data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

//Database connection
function db() {
    $db = new Database();
    return $db;
}

//Read a key/value setting (cached per request)
function setting(string $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows = db()->table('settings')->select('setting_key, setting_value')->get_all();
            $cache = [];
            foreach ($rows as $r) $cache[$r['setting_key']] = $r['setting_value'];
        } catch (Throwable $e) { $cache = []; }
    }
    return $cache[$key] ?? $default;
}

//Haversine great-circle distance in km
function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
    return 2 * $r * asin(min(1.0, sqrt($a)));
}

//Drop IT fare computation (Tricycle): base + per_km/km, configurable in admin settings. Minimum = base.
function compute_fare_php(float $distance_km, string $vehicle = 'Tricycle'): array {
    $base = max(0.0, (float) setting('fare_base', 30.00));
    $per_km = max(0.0, (float) setting('fare_per_km', 3.00));
    $distance_km = max(0, $distance_km);
    $cost = round($base + ($per_km * $distance_km), 2);
    $cost = max($cost, $base);
    $admin_fee = round($cost * 0.10, 2);
    $driver_earnings = round($cost - $admin_fee, 2);
    return [
        'distance_km'     => round($distance_km, 3),
        'amount'          => $cost,
        'admin_fee'       => $admin_fee,
        'driver_earnings' => $driver_earnings,
        'base'            => $base,
        'per_km'          => $per_km,
    ];
}

function set_setting(string $key, $value): void {
    $existing = db()->table('settings')->select('setting_key')->where('setting_key', $key)->get();
    if ($existing) {
        db()->table('settings')->where('setting_key', $key)->update(['setting_value' => $value]);
    } else {
        db()->table('settings')->insert(['setting_key' => $key, 'setting_value' => $value]);
    }
}

// Inline an SVG from resources/svgs/<name>.svg with currentColor fill,
// so theme CSS can colour all icons in one place. Returns '' if file is missing.
function icon(string $name, string $class = 'w-5 h-5'): string {
    static $cache = [];
    $name = preg_replace('/[^a-z0-9_-]/i', '', $name);
    if ($name === '') return '';
    if (!array_key_exists($name, $cache)) {
        $path = APP_ROOT . '/resources/svgs/' . $name . '.svg';
        if (!is_file($path)) {
            $cache[$name] = '';
        } else {
            $svg = file_get_contents($path);
            $svg = preg_replace('/<\?xml[^>]*\?>/', '', $svg);
            $svg = preg_replace('/\s+(width|height)="[^"]*"/i', '', $svg);
            $cache[$name] = trim($svg);
        }
    }
    if ($cache[$name] === '') return '';
    $attr = ' fill="currentColor" class="dropit-icon ' . htmlspecialchars($class, ENT_QUOTES) . '" aria-hidden="true"';
    return preg_replace('/<svg\b/', '<svg' . $attr, $cache[$name], 1);
}

//escape output
function esc($var, $double_encode = TRUE): string|array
{
    if ($var === null || $var === '' || $var === false)
    {
        return '';
    }

    if (is_array($var))
    {
        foreach (array_keys($var) as $key)
        {
            $var[$key] = esc($var[$key], $double_encode);
        }

        return $var;
    }

    return htmlspecialchars((string)$var, ENT_QUOTES, 'utf-8', $double_encode);
}

//get segments
function segment($seg)
{
    $parts = is_int($seg) ? explode('/', $_SERVER['REQUEST_URI']) : FALSE;
    return isset($parts[$seg]) ? $parts[$seg] : false;
}

// ETA in minutes from driver's current location to dropoff (tricycle avg 22 km/h)
function compute_eta_minutes(?float $drvLat, ?float $drvLng, ?float $dLat, ?float $dLng, float $avgKmh = 22.0): ?int {
    if ($drvLat === null || $drvLng === null || $dLat === null || $dLng === null) return null;
    $km = haversine_km($drvLat, $drvLng, $dLat, $dLng);
    if ($km <= 0) return 1;
    return max(1, (int)ceil(($km / $avgKmh) * 60));
}

// Sign arbitrary data with app_secret (used for 1-click accept links)
function sign_token(string $data): string {
    $secret = (string) setting('app_secret', 'drop-it-fallback-secret');
    return substr(hash_hmac('sha256', $data, $secret), 0, 20);
}
function verify_token(string $data, string $token): bool {
    return hash_equals(sign_token($data), $token);
}

// CSRF token (for JSON fetch() POST calls)
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

// Recalculate driver rating averages after a new rating
function refresh_driver_rating(int $driver_id): void {
    $row = db()->raw("SELECT AVG(stars) a, COUNT(*) c FROM ratings WHERE driver_id = ?", [$driver_id])->fetch();
    if ($row) {
        db()->table('users')->where('id', $driver_id)->update([
            'rating_avg'   => round((float)($row['a'] ?? 0), 2),
            'rating_count' => (int)($row['c'] ?? 0),
        ]);
    }
}

// Format peso
function peso(float $n): string { return '₱' . number_format($n, 2); }

/**
 * Auto-revert stale Dispatched parcels back to Pending so admins can re-assign.
 * Lazily called from admin/driver hot paths — no cron required.
 * Returns the number of parcels reverted.
 */
function sweep_stale_dispatches(int $minutes = 15): int {
    $stale = db()->raw(
        "SELECT id, tracking_number, driver_id FROM parcels
          WHERE status = 'Dispatched' AND updated_at < (NOW() - INTERVAL ? MINUTE)",
        [$minutes]
    )->fetchAll();
    if (!$stale) return 0;

    foreach ($stale as $p) {
        db()->table('parcels')->where('id', $p['id'])->update([
            'driver_id' => null,
            'status'    => 'Pending',
        ]);
        db()->table('parcel_history')->insert([
            'parcel_id' => $p['id'],
            'status'    => 'Pending',
            'notes'     => "Auto-revert: no driver response within {$minutes} min. Returned to dispatch queue.",
            'updated_by'=> null,
        ]);
    }

    // One consolidated in-app ping for every admin.
    try {
        $n = count($stale);
        $admins = db()->table('users')->select('id')->where('role','admin')->where('is_active',1)->get_all();
        foreach ($admins as $a) {
            Notifier::in_app(
                (int)$a['id'],
                "⏰ Dispatch timeout",
                "{$n} " . ($n === 1 ? 'parcel was' : 'parcels were') . " returned to the dispatch queue (no driver reply in {$minutes} min).",
                null
            );
        }
    } catch (Throwable $e) {}

    return count($stale);
}
