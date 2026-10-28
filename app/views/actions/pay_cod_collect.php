<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$pid = (int)($id ?? 0);
$uid = (int)$_SESSION['user_id'];

$p = db()->table('parcels')->select('id, tracking_number, driver_id, amount, payment_status, payment_method')->where('id',$pid)->get();
if (!$p || (int)$p['driver_id'] !== $uid || ($p['payment_method'] !== 'cod')) {
    set_flash('error', 'Not authorized to collect COD on this parcel.');
    header('Location: ' . url('driver/dashboard')); exit;
}

db()->table('payments')->where('parcel_id',$pid)->where('method','cod')->update(['status' => 'paid']);
db()->table('parcels')->where('id',$pid)->update(['payment_status' => 'settled']);
db()->table('parcel_history')->insert([
    'parcel_id'=>$pid, 'status'=>'COD Collected',
    'notes'=>"Driver collected COD ₱" . number_format((float)$p['amount'], 2),
    'updated_by'=>$uid,
]);
set_flash('success', 'COD marked as collected.');
header('Location: ' . url('driver/parcel/'.$pid)); exit;
