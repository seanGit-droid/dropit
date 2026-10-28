<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'History';
$active = 'history';
$uid = $_SESSION['user_id'];

$parcels = db()->table('parcels')
    ->select('id, tracking_number, status, recipient_address, created_at, updated_at')
    ->where('customer_id', $uid)
    ->order_by('created_at', 'DESC')
    ->get_all();

include __DIR__ . '/../partials/head.php';
?>
<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('customer/dashboard') ?>" class="text-sm text-brand-mist">← Back</a>
  <h1 class="text-2xl font-bold mt-2">Parcel History</h1>
  <p class="text-sm text-brand-mist">All parcels linked to your account.</p>

  <div class="mt-5 space-y-3">
    <?php if (empty($parcels)): ?>
      <div class="glass rounded-2xl p-8 text-center">
        <div class="text-4xl">📭</div>
        <div class="mt-2 text-sm text-brand-mist">No parcels yet.</div>
      </div>
    <?php else: foreach ($parcels as $p): ?>
      <a href="<?= url('customer/track?q='.urlencode($p['tracking_number'])) ?>" class="block glass rounded-2xl p-4 hover:bg-white/15 transition">
        <div class="flex items-center justify-between">
          <div class="font-mono text-sm font-semibold"><?= esc($p['tracking_number']) ?></div>
          <?= status_chip($p['status']) ?>
        </div>
        <div class="text-xs text-brand-mist mt-1 truncate">To: <?= esc($p['recipient_address']) ?></div>
        <div class="text-[11px] text-brand-mist mt-1"><?= date('M j, Y', strtotime($p['created_at'])) ?></div>
      </a>
    <?php endforeach; endif; ?>
  </div>
</main>
<?php include __DIR__ . '/../partials/mobile_nav.php'; ?>
</body></html>
