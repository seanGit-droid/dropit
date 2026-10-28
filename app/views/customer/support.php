<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Customer Service';
$active = 'profile';
$uid = (int)$_SESSION['user_id'];

// Find or create a "support parcel" row for this customer. Use a virtual parcel
// (tracking prefix SUPPORT-) that's not dispatchable but supports chat + history.
$support = db()->table('parcels')->select('id, tracking_number')->where('customer_id', $uid)->like('tracking_number', 'SUPPORT-%')->get();
if (!$support) {
    $temp = 'SUPPORT-' . $uid . '-' . bin2hex(random_bytes(3));
    $sid = db()->raw("
        INSERT INTO parcels
            (tracking_number, customer_id, sender_name, recipient_name, recipient_address,
             pickup_address, status, is_customer_order, is_walkin, amount, payment_method)
        VALUES (?, ?, ?, 'Drop IT Support', 'Customer Support', 'Support Thread',
                'Pending', 1, 0, 0, 'cod')
    ", [
        $temp,
        $uid,
        $_SESSION['full_name'] ?: $_SESSION['username'],
    ])->lastInsertId ?? 0;
    // Fallback: try plain insert if raw fails
    if (!$sid) {
        $sid = db()->table('parcels')->insert([
            'tracking_number'  => $temp,
            'customer_id'      => $uid,
            'sender_name'      => $_SESSION['full_name'] ?: $_SESSION['username'],
            'recipient_name'   => 'Drop IT Support',
            'recipient_address'=> 'Customer Support',
            'pickup_address'   => 'Support Thread',
            'status'           => 'Pending',
            'is_customer_order'=> 1,
            'is_walkin'        => 0,
            'amount'           => 0,
            'payment_method'   => 'cod',
        ]);
    }
    if (!$sid) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Could not open support thread. Please try again.'];
        header('Location: ' . url('customer/profile')); exit;
    }
    $support = ['id' => (int)$sid, 'tracking_number' => $temp];
    // Welcome message from admin
    $admin = db()->table('users')->select('id')->where('role','admin')->order_by('id','ASC')->limit(1)->get();
    if ($admin) {
        db()->table('messages')->insert([
            'parcel_id' => $sid,
            'sender_id' => (int)$admin['id'],
            'sender_role' => 'admin',
            'body' => "Hi! Welcome to Drop IT Customer Service. Paki-describe po yung concern niyo dito, sasagutin ka namin ASAP. 👋",
        ]);
    }
}

$admin = db()->table('users')->select('id, full_name')->where('role','admin')->order_by('id','ASC')->limit(1)->get();

// Wire chat bubble for this support thread
$chat_parcel_id = (int)$support['id'];
$chat_peer_name = 'Customer Service';
$chat_self_role = 'customer';
$chat_peer_user_id = $admin ? (int)$admin['id'] : 0;

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('customer/profile') ?>" class="text-sm text-brand-mist">← Back</a>
  <h1 class="text-2xl font-bold mt-2 flex items-center gap-2"><?= icon('report', 'w-6 h-6') ?> Customer Service</h1>
  <p class="text-sm text-brand-mist">Chat directly with Drop IT support — 24/7 in-app.</p>

  <div class="mt-5 glass-strong rounded-3xl p-6 text-center shadow-glass">
    <div class="flex justify-center"><?= icon('messages', 'w-12 h-12') ?></div>
    <div class="mt-3 font-semibold">Chat with admin</div>
    <div class="text-sm text-brand-mist mt-1">Ready na — tap the floating message button below to start.</div>
    <div class="flex gap-2 mt-4 justify-center flex-wrap">
      <button type="button" id="openSupportChat" class="btn btn-primary"><?= icon('messages', 'w-4 h-4 dropit-icon-active') ?> Open chat</button>
      <a href="<?= url('customer/report') ?>" class="btn btn-ghost"><?= icon('reports', 'w-4 h-4') ?> File a formal report</a>
    </div>
  </div>

  <div class="mt-5 glass rounded-2xl p-4 text-sm">
    <div class="font-semibold mb-2">Quick tips</div>
    <ul class="space-y-1 text-brand-mist text-xs">
      <li>• For returns, go to <strong>Delivered parcel</strong> → <span class="text-red-300">↩️ Return</span></li>
      <li>• For payment issues, include parcel tracking number in chat</li>
      <li>• For emergencies (driver), use the driver's <strong>🚨 SOS</strong></li>
    </ul>
  </div>
</main>

<?php include __DIR__ . '/../partials/chat_bubble.php'; ?>
<?php include __DIR__ . '/../partials/voice_call.php'; ?>
<?php include __DIR__ . '/../partials/mobile_nav.php'; ?>
<script>
document.getElementById('openSupportChat')?.addEventListener('click', () => {
  document.getElementById('chatOpen')?.click();
});
</script>
</body></html>
