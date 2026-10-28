<?php defined('APP_ROOT') OR exit('No direct script access allowed');
/**
 * POST /api/parcel/{id}/price-adjust
 * Admin proposes a new fare for a pending parcel before dispatching.
 * Body: driver_id, new_amount, reason
 */
if ($_SESSION['role'] !== 'admin') { json_response(['ok'=>false,'error'=>'Forbidden'], 403); }

$pid       = (int)($id ?? 0);
$driver_id = (int)($_POST['driver_id'] ?? 0);
$new_amt   = round((float)($_POST['new_amount'] ?? 0), 2);
$reason    = trim($_POST['reason'] ?? '');

if (!$pid || !$driver_id || $new_amt <= 0 || $reason === '') {
    json_response(['ok'=>false,'error'=>'Missing fields.']);
}

$parcel = db()->table('parcels')
    ->select('id, tracking_number, customer_id, status, amount, admin_fee, driver_earnings')
    ->where('id', $pid)->get();
if (!$parcel) {
    json_response(['ok'=>false,'error'=>'Parcel not found.']);
}
if (!in_array($parcel['status'], ['Pending', 'PriceReview'])) {
    json_response(['ok'=>false,'error'=>'Parcel must be Pending or awaiting review (status: '.$parcel['status'].').']);
}

// Recompute fee split on the new amount (10% admin / 90% driver)
$new_admin_fee       = round($new_amt * 0.10, 2);
$new_driver_earnings = round($new_amt - $new_admin_fee, 2);

// Mark parcel as awaiting customer's price approval
db()->table('parcels')->where('id', $pid)->update([
    'status'              => 'PriceReview',
    'pending_amount'      => $new_amt,
    'price_adjust_reason' => $reason,
    'price_review_status' => 'waiting',
    'pending_driver_id'   => $driver_id,
]);

db()->table('parcel_history')->insert([
    'parcel_id'  => $pid,
    'status'     => 'PriceReview',
    'notes'      => "Admin adjusted fare from ₱{$parcel['amount']} to ₱{$new_amt}. Reason: {$reason}",
    'updated_by' => $_SESSION['user_id'],
]);

// Notify customer in-app + email
if ($parcel['customer_id']) {
    $customer = db()->table('users')->select('id, full_name, phone, email')->where('id', $parcel['customer_id'])->get();
    if ($customer) {
        Notifier::in_app(
            (int)$customer['id'],
            '💰 Fare adjustment for ' . $parcel['tracking_number'],
            "Admin adjusted your fare from ₱" . number_format($parcel['amount'],2) . " to ₱" . number_format($new_amt,2) . ". Reason: {$reason}. Please accept or decline.",
            $pid
        );
    }
}

db()->table('activity_logs')->insert([
    'user_id' => $_SESSION['user_id'],
    'action'  => 'price_adjust',
    'details' => "Proposed ₱{$new_amt} for {$parcel['tracking_number']}. Reason: {$reason}",
]);

json_response(['ok'=>true, 'new_amount'=>$new_amt, 'reason'=>$reason]);
