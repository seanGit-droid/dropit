<?php defined('APP_ROOT') OR exit('No direct script access allowed');
// Local simulation completes during checkout. A return URL must never grant free payment.
$pid=(int)($id ?? 0); $uid=(int)$_SESSION['user_id'];
$parcel=db()->table('parcels')->select('tracking_number,customer_id,payment_method,payment_status')->where('id',$pid)->get();
if (!$parcel || (int)$parcel['customer_id']!==$uid || $parcel['payment_method']!=='gcash') {
    set_flash('error','GCash booking not found.'); header('Location: '.url('customer/dashboard')); exit;
}
if (in_array($parcel['payment_status'],['held','settled'],true)) set_flash('success','PAID (Via GCash Simulation). Your pickup request is confirmed.');
else set_flash('error','GCash simulated payment has not been completed.');
header('Location: '.url('customer/track?q='.urlencode($parcel['tracking_number']))); exit;
