<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$pid   = (int)($id ?? 0);
$token = $_GET['t'] ?? '';
$decision = strpos($_SERVER['REQUEST_URI'] ?? '', 'quick-decline') !== false ? 'decline' : 'accept';

$p = db()->table('parcels')->select('id, tracking_number, driver_id, accept_token, status, customer_id')->where('id',$pid)->get();
if (!$p || $p['accept_token'] !== $token || $token === '') {
    http_response_code(403);
    echo "Invalid or expired link."; exit;
}

if ((int)($_SESSION['user_id'] ?? 0) !== (int)$p['driver_id']) {
    // Not logged in as driver — send to login, come back after
    $_SESSION['redirect_after_login'] = url('driver/quick-accept/'.$pid.'?t='.$token);
    header('Location: ' . url('login')); exit;
}

if ($decision === 'accept') {
    db()->table('parcels')->where('id',$pid)->update(['status'=>'Accepted']);
    db()->table('parcel_history')->insert(['parcel_id'=>$pid, 'status'=>'Accepted', 'notes'=>'Driver accepted (quick link).', 'updated_by'=>$_SESSION['user_id']]);
    $driver_name = $_SESSION['full_name'] ?: $_SESSION['username'];
    db()->table('messages')->insert([
        'parcel_id'   => $pid,
        'sender_id'   => (int)$_SESSION['user_id'],
        'sender_role' => 'driver',
        'body'        => "Hi! Si {$driver_name} po, driver mo. Accepted ko na 'to — heading to pickup now. 🚚",
    ]);
    if ($p['customer_id']) {
        $cust = db()->table('users')->select('id, full_name, phone, email')->where('id',$p['customer_id'])->get();
        if ($cust) {
            Notifier::notify_customer_status($cust, $p, 'Accepted', "Driver accepted {$p['tracking_number']}.");
            Notifier::in_app($cust['id'], "Driver accepted", "{$driver_name} accepted your delivery and messaged you.", $pid);
        }
    } else {
        $admins = db()->table('users')->select('id')->where('role','admin')->where('is_active',1)->get_all();
        foreach ($admins as $a) {
            Notifier::in_app($a['id'], "Driver accepted", "{$driver_name} accepted walk-in parcel {$p['tracking_number']} and posted a message.", $pid);
        }
    }
    set_flash('success', 'Accepted. Proceed to pickup.');
} else {
    db()->table('parcels')->where('id',$pid)->update(['status'=>'Pending', 'driver_id'=>null]);
    db()->table('parcel_history')->insert(['parcel_id'=>$pid, 'status'=>'Pending', 'notes'=>'Driver declined (quick link).', 'updated_by'=>$_SESSION['user_id']]);
    $admins = db()->table('users')->select('id')->where('role','admin')->where('is_active',1)->get_all();
    foreach ($admins as $a) Notifier::in_app($a['id'], 'Driver declined', "Driver declined {$p['tracking_number']}. Reassign needed.", $pid);
    set_flash('info', 'Declined. Returned to admin.');
}

header('Location: ' . url('driver/dashboard')); exit;
