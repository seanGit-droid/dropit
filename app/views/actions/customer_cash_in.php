<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$uid = (int)$_SESSION['user_id'];
$amount = round((float)($_POST['amount'] ?? 0), 2);

if ($amount < 20 || $amount > 50000) {
    set_flash('error', 'Enter an amount between ₱20 and ₱50,000.');
    header('Location: ' . url('customer/cash-in')); exit;
}

// Stash the intent in the session so the return handler knows the amount.
$topup_ref = 'TOPUP-' . bin2hex(random_bytes(6));
$_SESSION['pending_topup'] = [
    'ref'    => $topup_ref,
    'amount' => $amount,
];

$success = url('customer/cash-in/return?ref=' . urlencode($topup_ref));
$failed  = url('customer/cash-in?failed=1');

$res = PayMongo::create_gcash_source($amount, $success, $failed, "Drop IT wallet top-up ({$topup_ref})");
if (!$res['ok']) {
    unset($_SESSION['pending_topup']);
    set_flash('error', 'Payment setup failed: ' . ($res['error'] ?? 'unknown'));
    header('Location: ' . url('customer/cash-in')); exit;
}

$_SESSION['pending_topup']['provider_ref'] = $res['id'];
header('Location: ' . $res['checkout_url']); exit;
