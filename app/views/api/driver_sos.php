<?php defined('APP_ROOT') OR exit('No direct script access allowed');
header('Content-Type: application/json');

$uid = (int)($_SESSION['user_id'] ?? 0);
$drv = db()->table('users')->select('id, full_name, username, phone')->where('id',$uid)->get();
if (!$drv) json_response(['ok'=>false], 401);

$lat  = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng  = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$note = trim($_POST['note'] ?? '');
$pid  = isset($_POST['parcel_id']) ? (int)$_POST['parcel_id'] : null;

db()->table('sos_alerts')->insert([
    'driver_id'=>$uid, 'parcel_id'=>$pid, 'lat'=>$lat, 'lng'=>$lng, 'note'=>$note,
]);

$parcel = $pid ? db()->table('parcels')->select('id, tracking_number')->where('id',$pid)->get() : null;
Notifier::notify_sos($uid, $drv, $parcel, $lat, $lng);

json_response(['ok'=>true]);
