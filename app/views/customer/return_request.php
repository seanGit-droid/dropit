<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$pid = (int)($id ?? 0);
$uid = (int)$_SESSION['user_id'];

$p = db()->table('parcels')
    ->select('id, tracking_number, status, customer_id, product_name, recipient_name, recipient_address, amount')
    ->where('id',$pid)->get();
if (!$p || (int)$p['customer_id'] !== $uid) {
    set_flash('error','Parcel not found.'); header('Location: ' . url('customer/dashboard')); exit;
}
if ($p['status'] !== 'Delivered') {
    set_flash('info', 'You can request a return only for Delivered parcels.'); header('Location: ' . url('customer/track?q=' . urlencode($p['tracking_number']))); exit;
}

$existing = db()->table('returns')->select('id, status')->where('parcel_id',$pid)->get();

$page_title = 'Return Product';
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('customer/track?q='.urlencode($p['tracking_number'])) ?>" class="text-sm text-brand-mist">← Back</a>
  <h1 class="text-2xl font-bold mt-2">↩️ Request Return</h1>
  <p class="text-sm text-brand-mist">Filing this opens a chat with admin. They'll reply with <span class="font-mono">/confirm</span> or <span class="font-mono">/reject</span>.</p>

  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <div class="mt-4 glass rounded-2xl p-4 text-sm">
    <div><span class="text-brand-mist">Parcel:</span> <span class="font-mono"><?= esc($p['tracking_number']) ?></span></div>
    <div><span class="text-brand-mist">Item:</span> <?= esc($p['product_name'] ?: 'Package') ?></div>
    <div><span class="text-brand-mist">Delivered to:</span> <?= esc($p['recipient_name']) ?></div>
  </div>

  <?php if ($existing): ?>
    <div class="mt-5 glass-strong rounded-2xl p-5 text-center">
      <div class="text-3xl">↩️</div>
      <div class="mt-2 font-semibold">Return already filed</div>
      <div class="text-xs text-brand-mist mt-1">Current status: <span class="chip chip-transit"><?= esc($existing['status']) ?></span></div>
      <a href="<?= url('customer/track?q='.urlencode($p['tracking_number'])) ?>" class="btn btn-primary mt-4">Open chat with admin</a>
    </div>
  <?php else: ?>
    <form method="post" action="<?= url('customer/return/'.$pid) ?>" class="mt-5 glass rounded-2xl p-5 space-y-3">
      <?php (new Router())->csrf_field(); ?>
      <div>
        <label class="label">Reason for return</label>
        <textarea class="input" name="reason" rows="4" required maxlength="500" placeholder="e.g. Damaged item, wrong product received, changed mind…"></textarea>
      </div>
      <button class="btn btn-primary w-full">File return + open chat</button>
    </form>
  <?php endif; ?>
</main>
</body></html>
