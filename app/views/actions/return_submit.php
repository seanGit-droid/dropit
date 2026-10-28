<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$pid = (int)($id ?? 0);
$uid = (int)$_SESSION['user_id'];
$reason = trim($_POST['reason'] ?? '');

$p = db()->table('parcels')->select('id, tracking_number, customer_id, status')->where('id',$pid)->get();
if (!$p || (int)$p['customer_id'] !== $uid || $p['status'] !== 'Delivered') {
    set_flash('error','Cannot file return.'); header('Location: ' . url('customer/dashboard')); exit;
}
if ($reason === '') {
    set_flash('error','Please state a reason.'); header('Location: ' . url('customer/return/'.$pid)); exit;
}

$existing = db()->table('returns')->where('parcel_id',$pid)->count();
if ($existing > 0) {
    set_flash('info','A return is already on file for this parcel.'); header('Location: ' . url('customer/track?q=' . urlencode($p['tracking_number']))); exit;
}

db()->table('returns')->insert([
    'parcel_id' => $pid, 'customer_id' => $uid,
    'reason' => $reason, 'status' => 'Requested',
]);

// Open a chat thread: customer's first message explains the return.
db()->table('messages')->insert([
    'parcel_id' => $pid,
    'sender_id' => $uid,
    'sender_role' => 'customer',
    'body' => "↩️ RETURN REQUESTED\nReason: {$reason}\n\n(Admin: reply /confirm to approve or /reject to deny.)",
]);

// Notify admins
$customer_name = $_SESSION['full_name'] ?: $_SESSION['username'];
$admins = db()->table('users')->select('id')->where('role','admin')->where('is_active',1)->get_all();
foreach ($admins as $a) {
    Notifier::in_app($a['id'], '↩️ Return request', "{$customer_name} filed a return on {$p['tracking_number']}.", $pid);
}

db()->table('activity_logs')->insert([
    'user_id' => $uid, 'action' => 'return_request',
    'details' => "Customer filed return on {$p['tracking_number']} — {$reason}",
]);

set_flash('success', 'Return filed. Chat with admin to confirm.');
header('Location: ' . url('customer/track?q=' . urlencode($p['tracking_number'])));
exit;
