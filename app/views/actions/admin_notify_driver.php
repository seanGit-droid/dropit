<?php defined('APP_ROOT') OR exit('No direct script access allowed');
header('Content-Type: application/json');

$pid = (int)($id ?? 0);
$p = db()->table('parcels')->select('id, tracking_number, driver_id, pickup_address, recipient_address, accept_token')->where('id',$pid)->get();
if (!$p || !$p['driver_id']) json_response(['ok'=>false,'error'=>'no driver'], 400);

$drv = db()->table('users')->select('id, full_name, username, phone, email')->where('id',$p['driver_id'])->get();
if (!$drv) json_response(['ok'=>false,'error'=>'driver missing'], 404);

$custom = trim($_POST['message'] ?? '');
$channels = $_POST['channels'] ?? ['in_app','email']; // allow selective sending
if (!is_array($channels)) $channels = explode(',', (string)$channels);

if ($custom === '') {
    $custom = "Drop IT reminder: Parcel {$p['tracking_number']} is still pending. Accept: "
            . url("driver/quick-accept/{$p['id']}?t={$p['accept_token']}");
}
$custom = mb_substr($custom, 0, 480);

$sent = ['in_app'=>false, 'email'=>false];
if (in_array('in_app', $channels, true)) { Notifier::in_app($drv['id'], 'Admin message', $custom, $p['id']); $sent['in_app']=true; }
if (in_array('email', $channels, true) && !empty($drv['email'])) { Notifier::email($drv['email'], "[Drop IT] {$p['tracking_number']}", $custom); $sent['email']=true; }

db()->table('activity_logs')->insert([
    'user_id'=>$_SESSION['user_id'], 'action'=>'admin_notify_driver',
    'details'=>"Sent to {$drv['full_name']} re {$p['tracking_number']} via " . implode('/', array_keys(array_filter($sent))) . ": " . mb_substr($custom, 0, 120),
]);

json_response(['ok'=>true, 'sent'=>$sent, 'driver'=>$drv['full_name']]);
