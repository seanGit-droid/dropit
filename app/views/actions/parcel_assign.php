<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$pid = (int)($id ?? 0);
$driver_id = $_POST['driver_id'] ?? '';
$new_driver = $driver_id !== '' ? (int)$driver_id : null;

$parcel = db()->table('parcels')
    ->select('id, tracking_number, customer_id, status, driver_id, pickup_address, recipient_address, amount, accept_token')
    ->where('id', $pid)->get();
if (!$parcel) {
    set_flash('error', 'Parcel not found.');
    header('Location: ' . url('admin/shipments/assign')); exit;
}

// Dispatched = waiting for driver's Accept/Decline. No driver = Pending.
$new_status = $new_driver ? 'Dispatched' : 'Pending';

// Ensure there's an accept_token so one-click email accept works.
$token = $parcel['accept_token'] ?? null;
if (!$token) $token = bin2hex(random_bytes(16));

db()->table('parcels')->where('id', $pid)->update([
    'driver_id'    => $new_driver,
    'status'       => $new_status,
    'accept_token' => $token,
]);
$parcel['accept_token'] = $token;

$driver_row = null;
if ($new_driver) {
    $driver_row = db()->table('users')
        ->select('id, username, full_name, phone, email')->where('id', $new_driver)->get();
}
$driver_name = $driver_row['full_name'] ?? '—';

db()->table('parcel_history')->insert([
    'parcel_id'  => $pid,
    'status'     => $new_status,
    'notes'      => $new_driver ? "Dispatch offered to {$driver_name}." : "Driver unassigned.",
    'updated_by' => $_SESSION['user_id'],
]);

if ($driver_row) Notifier::notify_driver_assigned($driver_row, $parcel);

if ($parcel['customer_id']) {
    $customer = db()->table('users')->select('id, full_name, phone, email')->where('id', $parcel['customer_id'])->get();
    if ($customer) {
        $msg = $new_driver
            ? "We've found a driver ({$driver_name}) for {$parcel['tracking_number']}. Waiting for them to accept."
            : "Your order {$parcel['tracking_number']} is awaiting dispatch.";
        Notifier::notify_customer_status($customer, $parcel, $new_status, $msg);
    }
}

db()->table('activity_logs')->insert([
    'user_id' => $_SESSION['user_id'],
    'action'  => 'parcel_dispatch',
    'details' => "Dispatched {$parcel['tracking_number']} → {$driver_name}.",
]);

set_flash('success', 'Offer sent to driver. Waiting for their accept.');
header('Location: ' . url('admin/shipments/assign'));
exit;
