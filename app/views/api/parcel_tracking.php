<?php defined('APP_ROOT') OR exit('No direct script access allowed');
header('Content-Type: application/json');

$tracking = $tracking ?? '';
if ($tracking === '') json_response(['ok' => false, 'error' => 'missing tracking'], 400);

$parcel = db()->table('parcels')
    ->select('id, tracking_number, status, customer_id, driver_id, pickup_lat, pickup_lng, dropoff_lat, dropoff_lng, pickup_address, recipient_address, updated_at')
    ->where('tracking_number', $tracking)
    ->get();

if (!$parcel) json_response(['ok' => false, 'error' => 'not found'], 404);

// access control: admin, owning customer, or assigned driver
$role = $_SESSION['role'] ?? '';
$uid  = (int)($_SESSION['user_id'] ?? 0);
$allowed = $role === 'admin'
        || ($role === 'customer' && (int)$parcel['customer_id'] === $uid)
        || ($role === 'driver'   && (int)$parcel['driver_id']   === $uid);
if (!$allowed) json_response(['ok' => false, 'error' => 'forbidden'], 403);

$driver_loc = null;
if ($parcel['driver_id']) {
    $driver_loc = db()->table('driver_locations')
        ->select('lat, lng, accuracy, updated_at')
        ->where('driver_id', $parcel['driver_id'])
        ->get();
}

json_response([
    'ok' => true,
    'status'           => $parcel['status'],
    'pickup_lat'       => $parcel['pickup_lat'],
    'pickup_lng'       => $parcel['pickup_lng'],
    'dropoff_lat'      => $parcel['dropoff_lat'],
    'dropoff_lng'      => $parcel['dropoff_lng'],
    'pickup_address'   => $parcel['pickup_address'],
    'dropoff_address'  => $parcel['recipient_address'],
    'driver_location'  => $driver_loc,
    'updated_at'       => $parcel['updated_at'],
]);
