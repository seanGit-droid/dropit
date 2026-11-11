<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$pid = (int)($id ?? 0);
$uid = (int)$_SESSION['user_id'];

require_once APP_ROOT . '/scheme/DeliveryCapture.php';
$json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
$db = db(); $saved_file = null;
$db->transaction();
try {
if (!(new Router())->is_csrf_valid()) throw new InvalidArgumentException('Your form expired. Reload and try again.');
$p = $db->raw('SELECT * FROM parcels WHERE id=? FOR UPDATE', [$pid])->fetch();
if (!$p || (int)$p['driver_id'] !== $uid) throw new InvalidArgumentException('Not your parcel.');
if (in_array($p['status'], ['Delivered','Cancelled','Return Pending','Returned'], true)) throw new InvalidArgumentException('This parcel is already completed.');

$proof = DeliveryCapture::validate($_POST, $_FILES['photo'] ?? []);
$signature = $proof['signature'];
$receiver = $proof['receiver'];
$pod_dir = APP_ROOT . '/resources/pod';
if (!is_dir($pod_dir) && !mkdir($pod_dir,0775,true) && !is_dir($pod_dir)) throw new RuntimeException('Photo storage unavailable.');
$photo_rel = 'resources/pod/pod_' . $pid . '_' . bin2hex(random_bytes(12)) . '.' . $proof['extension'];
$saved_file = APP_ROOT . '/' . $photo_rel;
if (!move_uploaded_file($_FILES['photo']['tmp_name'], $saved_file)) throw new RuntimeException('Photo could not be saved.');

DeliveryCapture::update($db, 'parcels', ['id'=>$pid], [
    'pod_photo'     => $photo_rel,
    'pod_latitude'  => $proof['lat'],
    'pod_longitude' => $proof['lng'],
    'pod_accuracy'  => $proof['accuracy'],
    'pod_captured_at' => $proof['captured_at'],
    'pod_location_at' => $proof['location_at'],
    'pod_saved_at' => gmdate('Y-m-d H:i:s'),
    'pod_signature' => $signature,
    'pod_receiver'  => $receiver,
    'status'        => 'Delivered',
]);

DeliveryCapture::insert($db, 'parcel_history', [
    'parcel_id'=>$pid, 'status'=>'Delivered', 'updated_by'=>$uid,
    'notes'=>"Delivered. POD captured (receiver: {$receiver}).",
]);

// Settle payments/earnings (same as driver_update_status.php Delivered branch)
$amount   = (float)$p['amount'];
$adminCut = (float)$p['admin_fee'];
$drvCut   = (float)$p['driver_earnings'];

if ($p['payment_method'] === 'cod' && !empty($_POST['cod_collected'])) {
    DeliveryCapture::update($db, 'payments', ['parcel_id'=>$pid, 'method'=>'cod'], ['status'=>'paid']);
    DeliveryCapture::update($db, 'parcels', ['id'=>$pid], ['payment_status'=>'settled']);
}

if (in_array($p['payment_method'], ['wallet','gcash']) && $p['payment_status'] === 'held' && $amount > 0) {
    $drv = $db->table('users')->select('id, wallet_balance')->where('id', $uid)->get();
    $drvNew = round((float)$drv['wallet_balance'] + $drvCut, 2);
    DeliveryCapture::update($db, 'users', ['id'=>$uid], ['wallet_balance' => $drvNew]);
    DeliveryCapture::insert($db, 'wallet_transactions', [
        'user_id' => $uid, 'parcel_id' => $pid,
        'amount'  => $drvCut, 'type' => 'delivery_earning',
        'description'   => "Earnings for {$p['tracking_number']} (90%).",
        'balance_after' => $drvNew,
    ]);
    $admin = $db->table('users')->select('id, wallet_balance')->where('role','admin')->order_by('id','ASC')->limit(1)->get();
    if ($admin) {
        $adNew = round((float)$admin['wallet_balance'] + $adminCut, 2);
        DeliveryCapture::update($db, 'users', ['id'=>$admin['id']], ['wallet_balance' => $adNew]);
        DeliveryCapture::insert($db, 'wallet_transactions', [
            'user_id' => $admin['id'], 'parcel_id' => $pid,
            'amount'  => $adminCut, 'type' => 'admin_commission',
            'description'   => "Platform fee for {$p['tracking_number']} (10%).",
            'balance_after' => $adNew,
        ]);
    }
    DeliveryCapture::update($db, 'parcels', ['id'=>$pid], ['payment_status' => 'settled']);
}

// For COD: driver holds physical cash from recipient. Track it on cash_on_hand,
// NOT on wallet_balance. Driver converts to wallet via "Cash In" later.
if ($p['payment_method'] === 'cod' && !empty($_POST['cod_collected'])) {
    $drv = $db->table('users')->select('id, cash_on_hand')->where('id', $uid)->get();
    $newCash = round((float)($drv['cash_on_hand'] ?? 0) + $amount, 2);
    DeliveryCapture::update($db, 'users', ['id'=>$uid], ['cash_on_hand' => $newCash]);
    DeliveryCapture::insert($db, 'wallet_transactions', [
        'user_id' => $uid, 'parcel_id' => $pid,
        'amount'  => $amount, 'type' => 'cod_collected',
        'description'   => "COD cash collected — {$p['tracking_number']} (₱" . number_format($amount,2) . "). Cash in to convert to wallet.",
        'balance_after' => $newCash,
    ]);
}

$db->commit();
} catch (Throwable $e) {
    $db->roll_back();
    if ($saved_file && is_file($saved_file)) unlink($saved_file);
    $message = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Photo and location could not be saved. Delivery was not completed. Please retry.';
    if (!($e instanceof InvalidArgumentException)) error_log('Delivery capture: '.$e->getMessage());
    if ($json) json_response(['ok'=>false,'error'=>$message],422);
    set_flash('error',$message);
    header('Location: '.url('driver/pod/'.$pid)); exit;
}

try {
if ($p['customer_id']) {
    $cust = $db->table('users')->select('id, full_name, phone, email')->where('id',$p['customer_id'])->get();
    if ($cust) Notifier::notify_customer_status($cust, $p, 'Delivered',
        "Parcel {$p['tracking_number']} delivered. Rate your driver: " . url('customer/rate/'.$pid));
}

} catch (Throwable $e) { error_log('Delivery notification: '.$e->getMessage()); }

set_flash('success', 'Marked Delivered.');
if ($json) json_response(['ok'=>true,'redirect'=>url('driver/pod/'.$pid)]);
header('Location: ' . url('driver/pod/'.$pid));
exit;
