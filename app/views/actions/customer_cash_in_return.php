<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$uid = (int)$_SESSION['user_id'];
$ref = trim($_GET['ref'] ?? '');
$simulated = !empty($_GET['sim']);

$pending = $_SESSION['pending_topup'] ?? null;
if (!$pending || $pending['ref'] !== $ref) {
    set_flash('error', 'Top-up session expired. Try again.');
    header('Location: ' . url('customer/cash-in')); exit;
}
$amount = (float)$pending['amount'];

$paid = false;
if ($simulated) {
    $paid = true;
} elseif (!empty($pending['provider_ref'])) {
    $src = PayMongo::fetch_source($pending['provider_ref']);
    if ($src && in_array(($src['attributes']['status'] ?? ''), ['chargeable','paid'])) $paid = true;
}

if (!$paid) {
    set_flash('error', 'Payment not confirmed yet. If you completed GCash, wait a moment and refresh.');
    header('Location: ' . url('customer/cash-in')); exit;
}

$me = db()->table('users')->select('wallet_balance, full_name, username, email')->where('id',$uid)->get();
$newBalance = round((float)$me['wallet_balance'] + $amount, 2);

db()->table('users')->where('id',$uid)->update(['wallet_balance' => $newBalance]);
db()->table('wallet_transactions')->insert([
    'user_id'       => $uid,
    'amount'        => $amount,
    'type'          => 'topup',
    'description'   => "GCash top-up · {$pending['ref']}" . ($simulated ? ' (sandbox)' : ''),
    'balance_after' => $newBalance,
]);
db()->table('activity_logs')->insert([
    'user_id' => $uid, 'action' => 'wallet_topup',
    'details' => "Customer topped-up ₱" . number_format($amount,2) . " via GCash.",
]);

unset($_SESSION['pending_topup']);

// Email the customer a receipt
if (!empty($me['email'])) {
    Notifier::email(
        $me['email'],
        "[Drop IT] Wallet top-up confirmed — ₱" . number_format($amount,2),
        "Hi " . ($me['full_name'] ?: $me['username']) . ",\n\n"
        . "Your Drop IT wallet was topped-up with ₱" . number_format($amount,2) . ".\n"
        . "New balance: ₱" . number_format($newBalance,2) . "\n"
        . "Reference: {$pending['ref']}\n\n"
        . "Thank you!"
    );
}

set_flash('success', 'Top-up confirmed! ₱' . number_format($amount,2) . ' added to your wallet.');
header('Location: ' . url('customer/dashboard')); exit;
