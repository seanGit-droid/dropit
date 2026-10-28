<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$data = [
    'sender_name'       => trim($_POST['sender_name'] ?? ''),
    'sender_address'    => trim($_POST['pickup_address'] ?? ''),
    'pickup_address'    => trim($_POST['pickup_address'] ?? ''),
    'pickup_lat'        => $_POST['pickup_lat'] ?? null,
    'pickup_lng'        => $_POST['pickup_lng'] ?? null,
    'recipient_name'    => trim($_POST['recipient_name'] ?? ''),
    'recipient_phone'   => trim($_POST['recipient_phone'] ?? ''),
    'recipient_address' => trim($_POST['recipient_address'] ?? ''),
    'dropoff_lat'       => $_POST['dropoff_lat'] ?? null,
    'dropoff_lng'       => $_POST['dropoff_lng'] ?? null,
    'weight'            => (float)($_POST['weight'] ?? 1),
    'notes'             => trim($_POST['notes'] ?? ''),
    'product_name'      => trim($_POST['product_name'] ?? ''),
    'payment_method'    => $_POST['payment_method'] ?? 'cod',
];
$driver_id = $_POST['driver_id'] ?? '';

foreach (['sender_name','pickup_address','pickup_lat','pickup_lng','recipient_name','recipient_phone','recipient_address','dropoff_lat','dropoff_lng','product_name'] as $f) {
    if (empty($data[$f])) {
        set_flash('error','Please complete all fields and pin pickup + dropoff.');
        header('Location: ' . url('admin/shipments/walkin')); exit;
    }
}

$fare = compute_fare_php(haversine_km(
    (float)$data['pickup_lat'], (float)$data['pickup_lng'],
    (float)$data['dropoff_lat'], (float)$data['dropoff_lng']
));

$temp_tracking = 'TMP-' . bin2hex(random_bytes(6));
$token = bin2hex(random_bytes(16));

$row = array_merge($data, [
    'tracking_number'   => $temp_tracking,
    'driver_id'         => $driver_id !== '' ? (int)$driver_id : null,
    'status'            => $driver_id !== '' ? 'Dispatched' : 'Pending',
    'is_customer_order' => 0,
    'is_walkin'         => 1,
    'vehicle_type'      => 'Tricycle',
    'distance_km'       => $fare['distance_km'],
    'amount'            => $fare['amount'],
    'admin_fee'         => $fare['admin_fee'],
    'driver_earnings'   => $fare['driver_earnings'],
    'payment_status'    => $data['payment_method'] === 'paid_cash' ? 'settled' : 'unpaid',
    'accept_token'      => $token,
]);

$pid = db()->table('parcels')->insert($row);
$tracking = 'DROPIT' . str_pad((string)$pid, 5, '0', STR_PAD_LEFT);
db()->table('parcels')->where('id', $pid)->update(['tracking_number' => $tracking]);

db()->table('payments')->insert([
    'parcel_id' => $pid,
    'method' => $data['payment_method'],
    'amount' => $fare['amount'],
    'status' => $data['payment_method'] === 'paid_cash' ? 'paid' : 'pending',
]);

db()->table('parcel_history')->insert([
    'parcel_id'=>$pid, 'status'=>$row['status'], 'updated_by'=>$_SESSION['user_id'],
    'notes'=>"Walk-in parcel created. Fare ₱{$fare['amount']} · payment {$data['payment_method']}.",
]);

if ($driver_id !== '') {
    $drv = db()->table('users')->select('id, full_name, username, email, phone')->where('id',(int)$driver_id)->get();
    if ($drv) {
        $p = db()->table('parcels')->select('id, tracking_number, pickup_address, recipient_address, accept_token')->where('id',$pid)->get();
        Notifier::notify_driver_assigned($drv, $p);
    }
}

db()->table('activity_logs')->insert([
    'user_id'=>$_SESSION['user_id'], 'action'=>'walkin_create',
    'details'=>"Walk-in {$tracking} created, fare ₱{$fare['amount']}.",
]);

set_flash('success', "Walk-in parcel {$tracking} created.");
header('Location: ' . url('admin/shipments/assign'));
exit;
