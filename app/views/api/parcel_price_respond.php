<?php defined('APP_ROOT') OR exit('No direct script access allowed');
/**
 * POST /api/parcel/{id}/price-respond
 * Customer accepts or declines an admin-proposed fare change.
 * Body: decision = 'accept' | 'decline'
 */
if ($_SESSION['role'] !== 'customer') { json_response(['ok'=>false,'error'=>'Forbidden'], 403); }

$pid      = (int)($id ?? 0);
$decision = $_POST['decision'] ?? '';
if (!in_array($decision, ['accept','decline'])) json_response(['ok'=>false,'error'=>'Invalid decision.']);

$uid    = (int)$_SESSION['user_id'];
$parcel = db()->table('parcels')
    ->select('id, tracking_number, customer_id, status, price_review_status, pending_amount, price_adjust_reason, pending_driver_id, amount, accept_token')
    ->where('id', $pid)->get();

if (!$parcel || $parcel['customer_id'] != $uid) {
    json_response(['ok'=>false,'error'=>'Not found.']);
}
if ($parcel['status'] !== 'PriceReview' || $parcel['price_review_status'] !== 'waiting') {
    json_response(['ok'=>false,'error'=>'No pending price review for this parcel.']);
}

if ($decision === 'accept') {
    $new_amt   = (float)$parcel['pending_amount'];
    $admin_fee = round($new_amt * 0.10, 2);
    $drv_earn  = round($new_amt - $admin_fee, 2);
    $driver_id = (int)$parcel['pending_driver_id'];
    $token     = $parcel['accept_token'] ?: bin2hex(random_bytes(16));

    // Apply fare + dispatch to driver
    db()->table('parcels')->where('id', $pid)->update([
        'status'              => 'Dispatched',
        'amount'              => $new_amt,
        'admin_fee'           => $admin_fee,
        'driver_earnings'     => $drv_earn,
        'driver_id'           => $driver_id ?: null,
        'price_review_status' => 'accepted',
        'accept_token'        => $token,
    ]);
    db()->table('parcel_history')->insert([
        'parcel_id'  => $pid,
        'status'     => 'Dispatched',
        'notes'      => "Customer accepted adjusted fare ₱{$new_amt}. Dispatched to driver #" . ($driver_id ?: '—') . ".",
        'updated_by' => $uid,
    ]);

    // Notify driver
    if ($driver_id) {
        $driver_row = db()->table('users')->select('id, full_name, phone, email')->where('id', $driver_id)->get();
        $parcel['amount']          = $new_amt;
        $parcel['driver_earnings'] = $drv_earn;
        if ($driver_row) Notifier::notify_driver_assigned($driver_row, $parcel);
    }

    // Notify admin
    $admins = db()->table('users')->select('id')->where('role','admin')->where('is_active',1)->get_all();
    foreach ($admins as $a) {
        Notifier::in_app((int)$a['id'], '✅ Price accepted — ' . $parcel['tracking_number'],
            "Customer accepted the adjusted fare ₱" . number_format($new_amt,2) . ". Driver dispatched.",
            $pid);
    }

    json_response(['ok'=>true, 'decision'=>'accept', 'new_amount'=>$new_amt]);

} else {
    // Declined — return to Pending, clear driver
    db()->table('parcels')->where('id', $pid)->update([
        'status'              => 'Pending',
        'driver_id'           => null,
        'pending_driver_id'   => null,
        'price_review_status' => 'declined',
    ]);
    db()->table('parcel_history')->insert([
        'parcel_id'  => $pid,
        'status'     => 'Pending',
        'notes'      => "Customer declined adjusted fare. Returned to dispatch queue.",
        'updated_by' => $uid,
    ]);

    // Notify admin
    $admins = db()->table('users')->select('id')->where('role','admin')->where('is_active',1)->get_all();
    foreach ($admins as $a) {
        Notifier::in_app((int)$a['id'], '❌ Price declined — ' . $parcel['tracking_number'],
            "Customer declined the fare adjustment. Parcel returned to the dispatch queue.",
            $pid);
    }

    json_response(['ok'=>true, 'decision'=>'decline']);
}
