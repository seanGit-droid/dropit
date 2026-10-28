<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Dashboard';
$active = 'dashboard';
$uid = $_SESSION['user_id'];

$active_parcels = db()->table('parcels')
    ->select('id, tracking_number, status, recipient_address, updated_at')
    ->where('customer_id', $uid)
    ->where('status', '!=', 'Delivered')
    ->where('status', '!=', 'Cancelled')
    ->order_by('updated_at', 'DESC')
    ->limit(3)
    ->get_all();

$unread = db()->table('notifications')
    ->where('user_id', $uid)
    ->where('is_read', 0)
    ->count();

$wallet = db()->table('users')->select('wallet_balance')->where('id',$uid)->get();

$price_reviews = db()->raw("
    SELECT id, tracking_number, amount, pending_amount, price_adjust_reason, recipient_address
    FROM parcels
    WHERE customer_id = ? AND status = 'PriceReview' AND price_review_status = 'waiting'
    ORDER BY updated_at DESC LIMIT 5
", [$uid])->fetchAll();

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<main class="mx-auto max-w-md px-4 pt-6 pb-28" id="live-dashboard">
  <!-- header -->
  <div class="flex items-center justify-between mb-5">
    <div class="flex items-center gap-3">
      <img src="<?= url('resources/weblogo.png') ?>" alt="" class="w-9 h-9 rounded-xl ring-1 ring-white/30 bg-white/10 p-1">
      <div>
        <div class="text-xs text-brand-mist">Hi,</div>
        <div class="font-semibold"><?= esc($_SESSION['full_name'] ?: $_SESSION['username']) ?> 👋</div>
      </div>
    </div>
    <a href="<?= url('customer/notifications') ?>" class="relative glass rounded-xl p-2">
      <?= icon('bell', 'w-5 h-5') ?>
      <span data-notif-badge class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1.5 py-0.5 <?= $unread ? '' : 'hidden' ?>"><?= (int)$unread ?></span>
    </a>
  </div>

  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <?php if (!empty($price_reviews)): foreach ($price_reviews as $_pr): ?>
  <div class="mb-3 glass rounded-2xl p-4 border border-amber-400/40 bg-amber-400/10"
       style="cursor:pointer" onclick="DropIT.startPriceReviewPoll && DropIT._showPriceReviewById(<?= (int)$_pr['id'] ?>)">
    <div class="flex items-start gap-3">
      <div class="text-2xl flex-shrink-0">💰</div>
      <div class="flex-1 min-w-0">
        <div class="font-bold text-sm text-amber-300">Fare adjustment — action needed</div>
        <div class="text-xs text-brand-mist font-mono mt-0.5"><?= esc($_pr['tracking_number']) ?></div>
        <div class="flex items-center gap-2 mt-1.5">
          <span class="text-xs line-through opacity-60">₱<?= number_format((float)$_pr['amount'],2) ?></span>
          <span class="text-sm font-bold text-emerald-300">₱<?= number_format((float)$_pr['pending_amount'],2) ?></span>
        </div>
        <div class="text-xs text-brand-mist mt-1">Reason: <?= esc($_pr['price_adjust_reason']) ?></div>
        <div class="flex gap-2 mt-3">
          <button onclick="event.stopPropagation();_prRespond(<?= (int)$_pr['id'] ?>,'decline')" class="flex-1 py-2 rounded-xl text-xs font-bold" style="background:rgba(239,68,68,.18);color:#fca5a5;border:1px solid rgba(239,68,68,.35);">Decline</button>
          <button onclick="event.stopPropagation();_prRespond(<?= (int)$_pr['id'] ?>,'accept')" class="flex-1 py-2 rounded-xl text-xs font-bold" style="background:linear-gradient(135deg,#22c55e,#15803d);color:#fff;">Accept</button>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; endif; ?>

  <!-- wallet -->
  <div class="glass rounded-2xl px-4 py-3 flex items-center justify-between mb-3">
    <div>
      <div class="text-xs text-brand-mist">Wallet</div>
      <div class="text-2xl font-bold text-emerald-300">₱<?= number_format((float)$wallet['wallet_balance'], 2) ?></div>
    </div>
    <a href="<?= url('customer/cash-in') ?>" class="btn btn-primary !py-2 !px-3 text-xs"><?= icon('card', 'w-4 h-4 dropit-icon-active') ?> Cash in</a>
  </div>

  <!-- active tracking hero -->
  <div class="glass-strong rounded-3xl p-5 shadow-glass">
    <div class="flex items-center justify-between">
      <div class="text-xs text-brand-mist">Active Tracking</div>
      <div class="text-xs text-brand-mist"><?= count($active_parcels) ?> parcel(s)</div>
    </div>
    <?php if (empty($active_parcels)): ?>
      <div class="text-center py-8">
        <div class="text-4xl">📭</div>
        <div class="mt-2 text-sm text-brand-mist">No active parcels yet.</div>
      </div>
    <?php else: foreach ($active_parcels as $p): ?>
      <a href="<?= url('customer/track?q=' . urlencode($p['tracking_number'])) ?>" class="block mt-4 glass rounded-2xl p-4 hover:bg-white/15 transition">
        <div class="flex items-center justify-between">
          <div class="font-mono text-sm font-semibold"><?= esc($p['tracking_number']) ?></div>
          <?= status_chip($p['status']) ?>
        </div>
        <div class="mt-2 text-xs text-brand-mist">To: <?= esc($p['recipient_address']) ?></div>
        <div class="mt-3 flex items-center gap-1.5">
          <?php
            $stages = ['Pending','Picked Up','In Transit','Arrived','Delivered'];
            $idx = array_search($p['status'], $stages);
            if ($idx === false) $idx = 0;
            foreach ($stages as $i=>$_): ?>
              <div class="h-1.5 flex-1 rounded-full <?= $i<=$idx ? 'bg-brand-sky' : 'bg-white/15' ?>"></div>
          <?php endforeach; ?>
        </div>
        <div class="mt-2 text-[11px] text-brand-sky text-right">Tap to track →</div>
      </a>
    <?php endforeach; endif; ?>
  </div>

  <!-- place order CTA -->
  <a href="<?= url('customer/order') ?>" class="mt-5 flex items-center gap-4 glass-strong rounded-2xl p-5 shadow-glow hover:bg-white/18 transition">
    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-azure to-brand-blue flex items-center justify-center"><?= icon('transit', 'w-7 h-7 dropit-icon-active') ?></div>
    <div class="flex-1">
      <div class="font-semibold">Place a new order</div>
      <div class="text-xs text-brand-mist">Set pickup + dropoff on the map.</div>
    </div>
    <div class="text-xl">→</div>
  </a>

  <!-- actions grid -->
  <div class="grid grid-cols-2 gap-3 mt-4">
    <a href="<?= url('customer/track') ?>" class="glass rounded-2xl p-4 hover:bg-white/15 transition">
      <?= icon('magnifying', 'w-7 h-7') ?>
      <div class="font-semibold mt-2">Track Parcel</div>
      <div class="text-xs text-brand-mist">Find by tracking no.</div>
    </a>
    <a href="<?= url('customer/notifications') ?>" class="glass rounded-2xl p-4 hover:bg-white/15 transition">
      <?= icon('alert', 'w-7 h-7') ?>
      <div class="font-semibold mt-2">Alerts</div>
      <div class="text-xs text-brand-mist">Driver updates</div>
    </a>
    <a href="<?= url('customer/history') ?>" class="glass rounded-2xl p-4 hover:bg-white/15 transition">
      <?= icon('history', 'w-7 h-7') ?>
      <div class="font-semibold mt-2">History</div>
      <div class="text-xs text-brand-mist">Past parcels</div>
    </a>
    <a href="<?= url('customer/profile') ?>" class="glass rounded-2xl p-4 hover:bg-white/15 transition">
      <?= icon('profile', 'w-7 h-7') ?>
      <div class="font-semibold mt-2">Profile</div>
      <div class="text-xs text-brand-mist">Update details</div>
    </a>
  </div>
</main>

<?php include __DIR__ . '/../partials/mobile_nav.php'; ?>
<script>
DropIT.autoRefresh(['live-dashboard'], 12000);
DropIT.startNotifPoll();

async function _prRespond(parcelId, decision) {
  const btn = event?.target;
  if (btn) { btn.disabled = true; btn.textContent = decision === 'accept' ? 'Accepting...' : 'Declining...'; }
  const d = await DropIT.post('<?= url('api/parcel/') ?>' + parcelId + '/price-respond', { decision });
  if (d.ok) {
    DropIT.toast(decision === 'accept' ? 'Fare accepted! Driver will be dispatched.' : 'Fare declined. Parcel returned to queue.', 'info');
    setTimeout(() => location.reload(), 1200);
  } else {
    DropIT.toast(d.error || 'Failed. Please try again.', 'error');
    if (btn) { btn.disabled = false; btn.textContent = decision === 'accept' ? 'Accept' : 'Decline'; }
  }
}
</script>
</body></html>
