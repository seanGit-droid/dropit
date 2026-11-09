<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$uid = $_SESSION['user_id'];

$data = [
    'sender_name'         => trim($_POST['sender_name'] ?? ''),
    'pickup_address'      => trim($_POST['pickup_address'] ?? ''),
    'pickup_lat'          => $_POST['pickup_lat'] ?? null,
    'pickup_lng'          => $_POST['pickup_lng'] ?? null,
    'recipient_name'      => trim($_POST['recipient_name'] ?? ''),
    'recipient_phone'     => trim($_POST['recipient_phone'] ?? ''),
    'recipient_address'   => trim($_POST['recipient_address'] ?? ''),
    'dropoff_lat'         => $_POST['dropoff_lat'] ?? null,
    'dropoff_lng'         => $_POST['dropoff_lng'] ?? null,
    'weight'              => (float)($_POST['weight'] ?? 0),
    'notes'               => trim($_POST['notes'] ?? ''),
    'product_name'        => trim($_POST['product_name'] ?? ''),
    'product_description' => trim($_POST['product_description'] ?? ''),
];
$pay_method = in_array($_POST['payment_method'] ?? 'wallet', ['wallet','cod','gcash']) ? $_POST['payment_method'] : 'wallet';

$required = ['pickup_address','pickup_lat','pickup_lng','recipient_name','recipient_phone','recipient_address','dropoff_lat','dropoff_lng','product_name'];
foreach ($required as $f) {
    if (empty($data[$f])) {
        set_flash('error', 'Please complete all fields and pin pickup + dropoff on the maps.');
        header('Location: ' . url('customer/order')); exit;
    }
}

$fare = compute_fare_php(
    haversine_km((float)$data['pickup_lat'], (float)$data['pickup_lng'],
                 (float)$data['dropoff_lat'], (float)$data['dropoff_lng']),
    'Tricycle'
);

// GCash checkout uses the same simulated account balance, with one atomic deduction.
if ($pay_method === 'gcash') {
    require_once APP_ROOT.'/scheme/GCashSimulation.php';
    try {
        if (!(new Router())->is_csrf_valid()) throw new InvalidArgumentException('Your checkout form expired. Please retry.');
        $booking=GCashSimulation::book(db(),(int)$uid,$data,$fare);
    } catch (Throwable $e) {
        if (!($e instanceof InvalidArgumentException)) error_log('GCash simulation checkout failed: '.get_class($e));
        set_flash('error',$e instanceof InvalidArgumentException?$e->getMessage():'GCash simulated checkout could not be saved. Your balance was not deducted. Please retry.');
        header('Location: '.url('customer/order')); exit;
    }
    try { Notifier::notify_admin_new_order($booking['parcel'],$booking['customer']); }
    catch (Throwable $e) { error_log('GCash booking notification failed: '.get_class($e)); }
    set_flash('success','Order placed. Tracking '.$booking['tracking'].' · PAID (Via GCash Simulation).');
    header('Location: '.url('customer/track?q='.urlencode($booking['tracking']))); exit;
}

$me = db()->table('users')->select('id, full_name, phone, email, wallet_balance')->where('id',$uid)->get();

// Only wallet method needs upfront balance.
if ($pay_method === 'wallet' && (float)$me['wallet_balance'] < $fare['amount']) {
    set_flash('error', sprintf('Insufficient wallet balance. Need ₱%s, have ₱%s.', number_format($fare['amount'],2), number_format($me['wallet_balance'],2)));
    header('Location: ' . url('customer/order')); exit;
}

$temp_tracking = 'TMP-' . bin2hex(random_bytes(6));
$token = bin2hex(random_bytes(16));

$payment_status = match ($pay_method) {
    'wallet' => 'held',
    'gcash'  => 'held', // handled atomically by the local simulation above
    'cod'    => 'unpaid', // settles when driver collects
};

$row = array_merge($data, [
    'tracking_number'   => $temp_tracking,
    'customer_id'       => $uid,
    'sender_address'    => $data['pickup_address'],
    'status'            => 'Pending',
    'is_customer_order' => 1,
    'vehicle_type'      => 'Tricycle',
    'distance_km'       => $fare['distance_km'],
    'amount'            => $fare['amount'],
    'admin_fee'         => $fare['admin_fee'],
    'driver_earnings'   => $fare['driver_earnings'],
    'payment_status'    => $payment_status,
    'payment_method'    => $pay_method,
    'accept_token'      => $token,
]);

$pid = db()->table('parcels')->insert($row);
$tracking = 'DROPIT' . str_pad((string)$pid, 5, '0', STR_PAD_LEFT);
db()->table('parcels')->where('id', $pid)->update(['tracking_number' => $tracking]);

db()->table('payments')->insert([
    'parcel_id' => $pid, 'method' => $pay_method,
    'amount' => $fare['amount'],
    'status' => $pay_method === 'wallet' ? 'paid' : 'pending',
]);

if ($pay_method === 'wallet') {
    $new_balance = round((float)$me['wallet_balance'] - (float)$fare['amount'], 2);
    db()->table('users')->where('id',$uid)->update(['wallet_balance' => $new_balance]);
    db()->table('wallet_transactions')->insert([
        'user_id'=>$uid, 'parcel_id'=>$pid,
        'amount'=>-1*$fare['amount'], 'type'=>'delivery_charge',
        'description'=>"Order {$tracking} — held in escrow",
        'balance_after'=>$new_balance,
    ]);
}

db()->table('parcel_history')->insert([
    'parcel_id'=>$pid, 'status'=>'Pending', 'updated_by'=>$uid,
    'notes'=>"Customer placed order. Fare ₱" . number_format($fare['amount'], 2) . " · {$pay_method}.",
]);

db()->table('activity_logs')->insert([
    'user_id' => $uid, 'action'  => 'order_placed',
    'details' => "Customer placed order {$tracking} — ₱{$fare['amount']} ({$pay_method}).",
]);

$parcel = db()->table('parcels')
    ->select('id, tracking_number, pickup_address, recipient_address, recipient_name, recipient_phone, notes, amount')
    ->where('id', $pid)->get();
Notifier::notify_admin_new_order($parcel, $me);

set_flash('success', "Order placed. Tracking {$tracking} · " . ($pay_method === 'cod' ? 'Pay on delivery.' : '₱' . number_format($fare['amount'],2) . ' held from wallet.'));
header('Location: ' . url('customer/track?q=' . urlencode($tracking)));
exit;
