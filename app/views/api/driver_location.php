<?php defined('APP_ROOT') OR exit('No direct script access allowed');
header('Content-Type: application/json');

$uid = (int)($_SESSION['user_id'] ?? 0);
$lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$acc = isset($_POST['accuracy']) ? (float)$_POST['accuracy'] : null;

if (!$lat || !$lng) json_response(['ok' => false, 'error' => 'missing lat/lng'], 400);
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) json_response(['ok' => false, 'error' => 'out of range'], 400);

$existing = db()->table('driver_locations')->select('driver_id')->where('driver_id', $uid)->get();
if ($existing) {
    db()->table('driver_locations')->where('driver_id', $uid)->update([
        'lat' => $lat, 'lng' => $lng, 'accuracy' => $acc,
    ]);
} else {
    db()->table('driver_locations')->insert([
        'driver_id' => $uid, 'lat' => $lat, 'lng' => $lng, 'accuracy' => $acc,
    ]);
}
json_response(['ok' => true]);
