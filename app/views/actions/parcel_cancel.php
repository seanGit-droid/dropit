<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$pid = (int)($id ?? 0);
$p = db()->table('parcels')
    ->select('id, tracking_number, customer_id, driver_id, status, payment_status, payment_method, amount, is_walkin')
    ->where('id', $pid)->get();
if (!$p) { set_flash('error','Parcel not found.'); header('Location: ' . url('admin/shipments/assign')); exit; }

if (in_array($p['status'], ['Delivered','Cancelled'])) {
    set_flash('error', 'Cannot cancel a ' . strtolower($p['status']) . ' parcel.');
    header('Location: ' . url('admin/shipments/assign')); exit;
}

$reason = trim($_POST['reason'] ?? '') ?: 'Admin cancelled the dispatch.';

db()->table('parcels')->where('id', $pid)->update([
    'status'    => 'Cancelled',
]);

db()->table('parcel_history')->insert([
    'parcel_id' => $pid,
    'status'    => 'Cancelled',
    'notes'     => 'Admin cancel: ' . $reason,
    'updated_by' => $_SESSION['user_id'],
]);

// Refund customer if wallet fare is still held in escrow.
if ($p['customer_id'] && $p['payment_status'] === 'held' && $p['payment_method'] !== 'cod' && (float)$p['amount'] > 0) {
    $cust = db()->table('users')->select('id, wallet_balance, full_name, phone, email')->where('id',$p['customer_id'])->get();
    if ($cust) {
        $new = round((float)$cust['wallet_balance'] + (float)$p['amount'], 2);
        db()->table('users')->where('id',$cust['id'])->update(['wallet_balance' => $new]);
        db()->table('wallet_transactions')->insert([
            'user_id' => $cust['id'], 'parcel_id' => $pid,
            'amount'  => (float)$p['amount'], 'type' => 'refund',
            'description'  => "Refund - {$p['tracking_number']} cancelled by admin.",
            'balance_after' => $new,
        ]);
        db()->table('parcels')->where('id',$pid)->update(['payment_status' => 'refunded']);
        Notifier::notify_customer_status($cust, $p, 'Cancelled',
            "Your parcel {$p['tracking_number']} was cancelled by admin. Fare refunded.");
    }
}

// Notify driver if assigned.
if ($p['driver_id']) {
    $drv = db()->table('users')->select('id, full_name, phone, email')->where('id',$p['driver_id'])->get();
    if ($drv) Notifier::in_app($drv['id'], 'Dispatch cancelled',
        "Admin cancelled parcel {$p['tracking_number']}. Reason: " . mb_substr($reason, 0, 180), $pid);
}

db()->table('activity_logs')->insert([
    'user_id' => $_SESSION['user_id'],
    'action'  => 'parcel_cancel',
    'details' => "Admin cancelled {$p['tracking_number']} - {$reason}.",
]);

set_flash('success', 'Parcel cancelled.');
header('Location: ' . url($_POST['from'] ?? 'admin/shipments/assign'));
exit;
