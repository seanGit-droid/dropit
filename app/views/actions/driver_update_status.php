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

// Finalized deliveries cannot be reopened or settled again through this endpoint.
if (in_array($parcel['status'], ['Delivered', 'Cancelled', 'Return Pending', 'Returned'], true)) {
    set_flash('error', 'This parcel is already finalized.');
    header('Location: ' . url('driver/parcel/'.$pid)); exit;
}

// Completion must use the existing atomic photo/GPS proof submission.
if ($status === 'Delivered') {
    header('Location: ' . url('driver/pod/'.$pid)); exit;
}

// Pickup proof and status are saved together; notification behavior stays below.
$pickup_photo_rel = null;
if ($status === 'Picked Up') {
    require_once APP_ROOT . '/scheme/ProofStorage.php';
    require_once APP_ROOT . '/scheme/DeliveryCapture.php';
    $pickup_db = db(); $photo_storage = null;
    $pickup_db->transaction();
    try {
        $locked = $pickup_db->raw('SELECT driver_id,status FROM parcels WHERE id=? FOR UPDATE', [$pid])->fetch();
        if (!$locked || (int)$locked['driver_id'] !== (int)$_SESSION['user_id'] ||
            in_array($locked['status'], ['Delivered','Cancelled','Return Pending','Returned'], true)) {
            throw new InvalidArgumentException('This parcel cannot be updated.');
        }
        if (empty($_FILES['pickup_photo']['tmp_name'])) throw new InvalidArgumentException('Photo is required when marking Picked Up.');
        $ext = strtolower(pathinfo($_FILES['pickup_photo']['name'], PATHINFO_EXTENSION) ?: 'jpg');
        if (!in_array($ext, ['jpg','jpeg','png','webp','heic'])) $ext = 'jpg';
        $path = 'resources/transit/pickup_' . $pid . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $photo_storage = new ProofStorage();
        $pickup_photo_rel = $photo_storage->saveUpload($_FILES['pickup_photo'], $path);
        DeliveryCapture::update($pickup_db, 'parcels', ['id'=>$pid], ['status'=>$status]);
        foreach (["Driver updated: {$status}.", "[IMG]{$pickup_photo_rel}|Proof of pickup"] as $note) {
            DeliveryCapture::insert($pickup_db, 'parcel_history', ['parcel_id'=>$pid, 'status'=>$status,
                'notes'=>$note, 'updated_by'=>$_SESSION['user_id']]);
        }
        $pickup_db->commit();
    } catch (Throwable $e) {
        $pickup_db->roll_back();
        if ($pickup_photo_rel && $photo_storage) {
            try { $photo_storage->discard($pickup_photo_rel); }
            catch (Throwable $cleanup) { error_log('Pickup rollback cleanup failed: '.get_class($cleanup)); }
        }
        if (!($e instanceof InvalidArgumentException)) error_log('Pickup proof save failed: '.get_class($e));
        set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Pickup photo could not be saved. Status was not changed. Please retry.');
        header('Location: ' . url('driver/parcel/'.$pid)); exit;
    }
} else {
    db()->table('parcels')->where('id', $pid)->update(['status' => $status]);
    db()->table('parcel_history')->insert([
        'parcel_id'=>$pid, 'status'=>$status,
        'notes'=>"Driver updated: {$status}.", 'updated_by'=>$_SESSION['user_id'],
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
