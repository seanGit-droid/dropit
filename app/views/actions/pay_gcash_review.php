<?php defined('APP_ROOT') OR exit('No direct script access allowed');
// Old checkout links must remain read-only. Only an explicit CSRF-protected POST pays.
$pid=(int)($id ?? 0);$uid=(int)$_SESSION['user_id'];
$parcel=db()->table('parcels')->select('id,tracking_number,customer_id,payment_method,payment_status')->where('id',$pid)->get();
if (!$parcel || (int)$parcel['customer_id']!==$uid || $parcel['payment_method']!=='gcash') {
    set_flash('error','GCash booking not found.');header('Location: '.url('customer/dashboard'));exit;
}
header('Location: '.url('customer/track?q='.urlencode($parcel['tracking_number'])));exit;
