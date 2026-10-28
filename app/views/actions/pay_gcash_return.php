<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$pid = (int)($id ?? 0);
$uid = (int)$_SESSION['user_id'];
$simulated = !empty($_GET['sim']);

$p = db()->table('parcels')->select('id, tracking_number, customer_id, amount, payment_ref, payment_status')->where('id',$pid)->get();
if (!$p || (int)$p['customer_id'] !== $uid) {
    set_flash('error','Parcel not found.'); header('Location: ' . url('customer/dashboard')); exit;
}

$paid = false;
if ($simulated) {
    $paid = true;
} elseif ($p['payment_ref']) {
    $src = PayMongo::fetch_source($p['payment_ref']);
    if ($src && in_array(($src['attributes']['status'] ?? ''), ['chargeable','paid'])) $paid = true;
}

if ($paid) {
    db()->table('parcels')->where('id',$pid)->update(['payment_status' => 'held']);
    db()->table('payments')->where('parcel_id',$pid)->where('method','gcash')->update(['status' => 'paid']);
    db()->table('parcel_history')->insert([
        'parcel_id'=>$pid, 'status'=>'Pending', 'notes'=>"GCash payment confirmed" . ($simulated ? ' (sandbox simulated).' : '.'),
        'updated_by'=>$uid,
    ]);
    set_flash('success', 'GCash payment confirmed. Your parcel is queued.');
} else {
    set_flash('error', 'Payment not confirmed yet. Please retry.');
}
header('Location: ' . url('customer/track?q=' . urlencode($p['tracking_number'])));
exit;
