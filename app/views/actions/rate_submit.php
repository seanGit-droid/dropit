<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$pid = (int)($id ?? 0);
$uid = (int)$_SESSION['user_id'];
$stars = max(1, min(5, (int)($_POST['stars'] ?? 0)));
$review = trim($_POST['review'] ?? '');

$p = db()->table('parcels')->select('id, customer_id, driver_id, status')->where('id',$pid)->get();
if (!$p || (int)$p['customer_id'] !== $uid || !$p['driver_id'] || $p['status'] !== 'Delivered') {
    set_flash('error','Cannot rate.'); header('Location: ' . url('customer/dashboard')); exit;
}

$exists = db()->table('ratings')->select('id')->where('parcel_id',$pid)->get();
if ($exists) {
    db()->table('ratings')->where('parcel_id',$pid)->update(['stars'=>$stars, 'review'=>$review]);
} else {
    db()->table('ratings')->insert([
        'parcel_id'=>$pid, 'driver_id'=>$p['driver_id'], 'customer_id'=>$uid,
        'stars'=>$stars, 'review'=>$review,
    ]);
}

refresh_driver_rating((int)$p['driver_id']);
Notifier::in_app((int)$p['driver_id'], "New rating", "You got {$stars}★ for a recent delivery.", $pid);

set_flash('success','Thanks for rating!');
header('Location: ' . url('customer/dashboard')); exit;
