<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$pid = (int)($id ?? 0);
$status = $_POST['status'] ?? '';
$allowed = ['Picked Up','In Transit','Arrived','Delivered','Cancelled'];
if (!in_array($status, $allowed)) {
    set_flash('error','Invalid status.');
    header('Location: ' . url('driver/dashboard')); exit;
}

$parcel = db()->table('parcels')
    ->select('id, tracking_number, driver_id, customer_id, status, amount, admin_fee, driver_earnings, payment_status')
    ->where('id', $pid)->get();
if (!$parcel || $parcel['driver_id'] != $_SESSION['user_id']) {
    set_flash('error','Parcel not found or not assigned to you.');
    header('Location: ' . url('driver/dashboard')); exit;
}

// "Picked Up" requires a proof-of-pickup photo — no silent transition.
$pickup_photo_rel = null;
if ($status === 'Picked Up') {
    if (empty($_FILES['pickup_photo']['tmp_name']) || !is_uploaded_file($_FILES['pickup_photo']['tmp_name'])) {
        set_flash('error','Photo is required when marking Picked Up.');
        header('Location: ' . url('driver/parcel/'.$pid)); exit;
    }
    $dir = APP_ROOT . '/resources/transit';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $ext = strtolower(pathinfo($_FILES['pickup_photo']['name'], PATHINFO_EXTENSION) ?: 'jpg');
    if (!in_array($ext, ['jpg','jpeg','png','webp','heic'])) $ext = 'jpg';
    $fname = 'pickup_' . $pid . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['pickup_photo']['tmp_name'], $dir . '/' . $fname)) {
        set_flash('error','Pickup photo upload failed. Try again.');
        header('Location: ' . url('driver/parcel/'.$pid)); exit;
    }
    $pickup_photo_rel = 'resources/transit/' . $fname;
}

db()->table('parcels')->where('id', $pid)->update(['status' => $status]);

$history_notes = "Driver updated: {$status}.";
if ($pickup_photo_rel) {
    // Store the status-change note first, then a separate IMG row so both render correctly.
    db()->table('parcel_history')->insert([
        'parcel_id'  => $pid,
        'status'     => $status,
        'notes'      => $history_notes,
        'updated_by' => $_SESSION['user_id'],
    ]);
    db()->table('parcel_history')->insert([
        'parcel_id'  => $pid,
        'status'     => $status,
        'notes'      => "[IMG]{$pickup_photo_rel}|Proof of pickup",
        'updated_by' => $_SESSION['user_id'],
    ]);
} else {
    db()->table('parcel_history')->insert([
        'parcel_id'  => $pid,
        'status'     => $status,
        'notes'      => $history_notes,
        'updated_by' => $_SESSION['user_id'],
    ]);
}

// Payment simulation: on Delivered, release escrow → 10% admin, 90% driver
if ($status === 'Delivered' && $parcel['payment_status'] === 'held' && (float)$parcel['amount'] > 0) {
    $amount   = (float)$parcel['amount'];
    $adminCut = (float)$parcel['admin_fee'];
    $drvCut   = (float)$parcel['driver_earnings'];

    // credit driver
    $drv = db()->table('users')->select('id, wallet_balance')->where('id', $parcel['driver_id'])->get();
    $drvNew = round((float)$drv['wallet_balance'] + $drvCut, 2);
    db()->table('users')->where('id', $drv['id'])->update(['wallet_balance' => $drvNew]);
    db()->table('wallet_transactions')->insert([
        'user_id' => $drv['id'], 'parcel_id' => $pid,
        'amount'  => $drvCut, 'type' => 'delivery_earning',
        'description'   => "Earnings for {$parcel['tracking_number']} (90%).",
        'balance_after' => $drvNew,
    ]);

    // credit admin(s) — pick first admin account
    $admin = db()->table('users')->select('id, wallet_balance')->where('role','admin')->order_by('id','ASC')->limit(1)->get();
    if ($admin) {
        $adNew = round((float)$admin['wallet_balance'] + $adminCut, 2);
        db()->table('users')->where('id', $admin['id'])->update(['wallet_balance' => $adNew]);
        db()->table('wallet_transactions')->insert([
            'user_id' => $admin['id'], 'parcel_id' => $pid,
            'amount'  => $adminCut, 'type' => 'admin_commission',
            'description'   => "Platform fee for {$parcel['tracking_number']} (10%).",
            'balance_after' => $adNew,
        ]);
    }
    db()->table('parcels')->where('id', $pid)->update(['payment_status' => 'settled']);
}

// Customer refund if Cancelled before delivery
if ($status === 'Cancelled' && $parcel['payment_status'] === 'held' && $parcel['customer_id']) {
    $cust = db()->table('users')->select('id, wallet_balance')->where('id', $parcel['customer_id'])->get();
    $cnew = round((float)$cust['wallet_balance'] + (float)$parcel['amount'], 2);
    db()->table('users')->where('id', $cust['id'])->update(['wallet_balance' => $cnew]);
    db()->table('wallet_transactions')->insert([
        'user_id' => $cust['id'], 'parcel_id' => $pid,
        'amount'  => (float)$parcel['amount'], 'type' => 'refund',
        'description'   => "Refund — {$parcel['tracking_number']} cancelled.",
        'balance_after' => $cnew,
    ]);
    db()->table('parcels')->where('id', $pid)->update(['payment_status' => 'refunded']);
}

if ($parcel['customer_id']) {
    $customer = db()->table('users')
        ->select('id, full_name, phone, email')->where('id', $parcel['customer_id'])->get();
    if ($customer) {
        $msg = match ($status) {
            'Picked Up'   => "Your parcel {$parcel['tracking_number']} has been picked up.",
            'In Transit'  => "Your parcel {$parcel['tracking_number']} is on the way.",
            'Arrived'     => "Your driver has arrived with parcel {$parcel['tracking_number']}.",
            'Delivered'   => "Your parcel {$parcel['tracking_number']} has been delivered. Thank you!",
            'Cancelled'   => "Your parcel {$parcel['tracking_number']} was cancelled — fare refunded.",
            default       => "Parcel {$parcel['tracking_number']} status: {$status}.",
        };
        Notifier::notify_customer_status($customer, $parcel, $status, $msg);
    }
}

db()->table('activity_logs')->insert([
    'user_id' => $_SESSION['user_id'],
    'action'  => 'driver_update',
    'details' => "Driver {$_SESSION['username']} set {$parcel['tracking_number']} → {$status}.",
]);

set_flash('success', "Marked {$status}.");
header('Location: ' . url($status === 'Delivered' ? 'driver/dashboard' : 'driver/parcel/'.$pid));
exit;
