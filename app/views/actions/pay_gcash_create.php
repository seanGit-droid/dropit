<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$pid = (int)($id ?? 0);
$uid = (int)$_SESSION['user_id'];

$p = db()->table('parcels')->select('id, tracking_number, customer_id, amount, payment_status, payment_method')->where('id',$pid)->get();
if (!$p || (int)$p['customer_id'] !== $uid) {
    set_flash('error', 'Parcel not found.'); header('Location: ' . url('customer/dashboard')); exit;
}
if ($p['payment_status'] === 'settled' || $p['payment_status'] === 'held') {
    set_flash('info', 'Already paid.'); header('Location: ' . url('customer/track?q=' . urlencode($p['tracking_number']))); exit;
}

$success = url('pay/gcash/return/' . $pid);
$failed  = url('customer/dashboard');
$res = PayMongo::create_gcash_source((float)$p['amount'], $success, $failed, "Drop IT {$p['tracking_number']}");
if (!$res['ok']) {
    set_flash('error', 'Payment setup failed: ' . ($res['error'] ?? 'unknown'));
    header('Location: ' . url('customer/dashboard')); exit;
}

db()->table('payments')->insert([
    'parcel_id' => $pid,
    'method'    => 'gcash',
    'amount'    => (float)$p['amount'],
    'status'    => 'pending',
    'provider_ref' => $res['id'],
    'checkout_url' => $res['checkout_url'],
]);
db()->table('parcels')->where('id',$pid)->update([
    'payment_method' => 'gcash',
    'payment_ref'    => $res['id'],
]);

header('Location: ' . $res['checkout_url']); exit;
