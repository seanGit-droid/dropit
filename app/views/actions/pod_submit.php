<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$pid = (int)($id ?? 0);
$uid = (int)$_SESSION['user_id'];

$p = db()->table('parcels')
    ->select('id, tracking_number, driver_id, customer_id, status, amount, admin_fee, driver_earnings, payment_method, payment_status')
    ->where('id',$pid)->get();
if (!$p || (int)$p['driver_id'] !== $uid) {
    set_flash('error','Not your parcel.'); header('Location: ' . url('driver/dashboard')); exit;
}

// Save photo
$pod_dir = APP_ROOT . '/resources/pod';
if (!is_dir($pod_dir)) @mkdir($pod_dir, 0775, true);
$photo_rel = null;
if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg');
    if (!in_array($ext, ['jpg','jpeg','png','webp','heic'])) $ext = 'jpg';
    $fname = 'pod_' . $pid . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $pod_dir . '/' . $fname)) {
        $photo_rel = 'resources/pod/' . $fname;
    }
}
if (!$photo_rel) {
    set_flash('error','Photo upload failed.'); header('Location: ' . url('driver/pod/'.$pid)); exit;
}

$signature = $_POST['signature'] ?? '';
$receiver  = trim($_POST['receiver_name'] ?? '');
if ($signature === '' || $receiver === '') {
    set_flash('error','Signature and receiver name required.'); header('Location: ' . url('driver/pod/'.$pid)); exit;
}

db()->table('parcels')->where('id',$pid)->update([
    'pod_photo'     => $photo_rel,
    'pod_signature' => $signature,
    'pod_receiver'  => $receiver,
    'status'        => 'Delivered',
]);

db()->table('parcel_history')->insert([
    'parcel_id'=>$pid, 'status'=>'Delivered', 'updated_by'=>$uid,
    'notes'=>"Delivered. POD captured (receiver: {$receiver}).",
]);

// Settle payments/earnings (same as driver_update_status.php Delivered branch)
$amount   = (float)$p['amount'];
$adminCut = (float)$p['admin_fee'];
$drvCut   = (float)$p['driver_earnings'];

if ($p['payment_method'] === 'cod' && !empty($_POST['cod_collected'])) {
    db()->table('payments')->where('parcel_id',$pid)->where('method','cod')->update(['status'=>'paid']);
    db()->table('parcels')->where('id',$pid)->update(['payment_status'=>'settled']);
}

if (in_array($p['payment_method'], ['wallet','gcash']) && $p['payment_status'] === 'held' && $amount > 0) {
    $drv = db()->table('users')->select('id, wallet_balance')->where('id', $uid)->get();
    $drvNew = round((float)$drv['wallet_balance'] + $drvCut, 2);
    db()->table('users')->where('id', $uid)->update(['wallet_balance' => $drvNew]);
    db()->table('wallet_transactions')->insert([
        'user_id' => $uid, 'parcel_id' => $pid,
        'amount'  => $drvCut, 'type' => 'delivery_earning',
        'description'   => "Earnings for {$p['tracking_number']} (90%).",
        'balance_after' => $drvNew,
    ]);
    $admin = db()->table('users')->select('id, wallet_balance')->where('role','admin')->order_by('id','ASC')->limit(1)->get();
    if ($admin) {
        $adNew = round((float)$admin['wallet_balance'] + $adminCut, 2);
        db()->table('users')->where('id', $admin['id'])->update(['wallet_balance' => $adNew]);
        db()->table('wallet_transactions')->insert([
            'user_id' => $admin['id'], 'parcel_id' => $pid,
            'amount'  => $adminCut, 'type' => 'admin_commission',
            'description'   => "Platform fee for {$p['tracking_number']} (10%).",
            'balance_after' => $adNew,
        ]);
    }
    db()->table('parcels')->where('id',$pid)->update(['payment_status' => 'settled']);
}

// For COD: driver holds physical cash from recipient. Track it on cash_on_hand,
// NOT on wallet_balance. Driver converts to wallet via "Cash In" later.
if ($p['payment_method'] === 'cod' && !empty($_POST['cod_collected'])) {
    $drv = db()->table('users')->select('id, cash_on_hand')->where('id', $uid)->get();
    $newCash = round((float)($drv['cash_on_hand'] ?? 0) + $amount, 2);
    db()->table('users')->where('id', $uid)->update(['cash_on_hand' => $newCash]);
    db()->table('wallet_transactions')->insert([
        'user_id' => $uid, 'parcel_id' => $pid,
        'amount'  => $amount, 'type' => 'cod_collected',
        'description'   => "COD cash collected — {$p['tracking_number']} (₱" . number_format($amount,2) . "). Cash in to convert to wallet.",
        'balance_after' => $newCash,
    ]);
}

if ($p['customer_id']) {
    $cust = db()->table('users')->select('id, full_name, phone, email')->where('id',$p['customer_id'])->get();
    if ($cust) Notifier::notify_customer_status($cust, $p, 'Delivered',
        "Parcel {$p['tracking_number']} delivered. Rate your driver: " . url('customer/rate/'.$pid));
}

set_flash('success', 'Marked Delivered.');
header('Location: ' . url('driver/dashboard'));
exit;
