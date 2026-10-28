<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$pid = (int)($id ?? 0);
$decision = $_POST['decision'] ?? '';
$uid = (int)$_SESSION['user_id'];

$parcel = db()->table('parcels')
    ->select('id, tracking_number, customer_id, driver_id, status, is_walkin')
    ->where('id', $pid)->get();

if (!$parcel || (int)$parcel['driver_id'] !== $uid || $parcel['status'] !== 'Dispatched') {
    set_flash('error', 'Offer no longer available.');
    header('Location: ' . url('driver/dashboard')); exit;
}

if ($decision === 'accept') {
    db()->table('parcels')->where('id', $pid)->update(['status' => 'Accepted']);
    db()->table('parcel_history')->insert([
        'parcel_id'  => $pid,
        'status'     => 'Accepted',
        'notes'      => "Driver accepted the offer.",
        'updated_by' => $uid,
    ]);
    // Auto-post a friendly chat message from driver to customer.
    $driver_name = $_SESSION['full_name'] ?: $_SESSION['username'];
    db()->table('messages')->insert([
        'parcel_id'   => $pid,
        'sender_id'   => $uid,
        'sender_role' => 'driver',
        'body'        => "Hi! Si {$driver_name} po, driver mo for this parcel. Accepted ko na 'to — heading to pickup now. 🚚",
    ]);
    if ($parcel['customer_id']) {
        $customer = db()->table('users')->select('id, full_name, phone, email')->where('id', $parcel['customer_id'])->get();
        if ($customer) {
            Notifier::notify_customer_status($customer, $parcel, 'Accepted',
                "Your driver accepted {$parcel['tracking_number']}. Heading to pickup.");
            Notifier::in_app($customer['id'], "Driver accepted", "{$driver_name} accepted your delivery and messaged you.", $pid);
        }
    } else {
        // Walk-in / admin-dispatched: notify admins instead
        $admins = db()->table('users')->select('id')->where('role','admin')->where('is_active',1)->get_all();
        foreach ($admins as $a) {
            Notifier::in_app($a['id'], "Driver accepted", "{$driver_name} accepted walk-in parcel {$parcel['tracking_number']} and posted a message.", $pid);
        }
    }
    db()->table('activity_logs')->insert([
        'user_id' => $uid,
        'action'  => 'driver_accept',
        'details' => "Driver {$_SESSION['username']} accepted {$parcel['tracking_number']}.",
    ]);
    set_flash('success', 'Offer accepted. Proceed to pickup.');
    header('Location: ' . url('driver/parcel/'.$pid)); exit;
}

if ($decision === 'decline') {
    db()->table('parcels')->where('id', $pid)->update(['driver_id' => null, 'status' => 'Pending']);
    db()->table('parcel_history')->insert([
        'parcel_id'  => $pid,
        'status'     => 'Pending',
        'notes'      => "Driver declined. Returning to dispatch queue.",
        'updated_by' => $uid,
    ]);
    db()->table('activity_logs')->insert([
        'user_id' => $uid,
        'action'  => 'driver_decline',
        'details' => "Driver {$_SESSION['username']} declined {$parcel['tracking_number']}.",
    ]);
    // notify admin in-app
    $admins = db()->table('users')->select('id')->where('role','admin')->where('is_active',1)->get_all();
    foreach ($admins as $a) {
        Notifier::in_app($a['id'], 'Driver declined offer', "{$parcel['tracking_number']} returned to pending queue.", $pid);
    }
    set_flash('success', 'Offer declined.');
    header('Location: ' . url('driver/dashboard')); exit;
}

header('Location: ' . url('driver/dashboard')); exit;
