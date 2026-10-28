<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Tasks';
$active = 'dashboard';
$uid = $_SESSION['user_id'];

sweep_stale_dispatches(15);

$me = db()->table('users')->select('wallet_balance, availability, rating_avg, rating_count')->where('id',$uid)->get();

$offers = db()->table('parcels')
    ->select('id, tracking_number, pickup_address, recipient_address, recipient_name, recipient_phone, distance_km, amount, driver_earnings, notes, product_name, payment_method')
    ->where('driver_id', $uid)->where('status', 'Dispatched')
    ->order_by('updated_at','DESC')
    ->get_all();

$active_tasks = db()->table('parcels')
    ->select('id, tracking_number, status, recipient_name, recipient_address, driver_earnings, payment_method')
    ->where('driver_id', $uid)
    ->where('status', '!=', 'Delivered')
    ->where('status', '!=', 'Dispatched')
    ->where('status', '!=', 'Cancelled')
    ->order_by('updated_at','DESC')
    ->get_all();

$done = db()->table('parcels')->where('driver_id', $uid)->where('status', 'Delivered')->count();

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <div class="flex items-center justify-between mb-5">
    <div class="flex items-center gap-3">
      <img src="<?= url('resources/weblogo.png') ?>" alt="" class="w-9 h-9 rounded-xl ring-1 ring-white/30 bg-white/10 p-1">
      <div>
        <div class="text-xs text-brand-mist">Hi driver,</div>
        <div class="font-semibold"><?= esc($_SESSION['full_name'] ?: $_SESSION['username']) ?> 🚚</div>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= url('driver/notifications') ?>" class="relative glass rounded-xl p-2"><?= icon('bell', 'w-5 h-5') ?><span data-notif-badge class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1.5 py-0.5 hidden"></span></a>
      <form method="post" action="<?= url('logout') ?>">
        <?php (new Router())->csrf_field(); ?>
        <button class="btn btn-ghost !py-2 !px-3 text-xs"><?= icon('logout', 'w-4 h-4') ?></button>
      </form>
    </div>
  </div>

  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <!-- Availability toggle -->
  <div class="glass rounded-2xl p-3 flex items-center gap-2">
    <span class="text-xs text-brand-mist">Status:</span>
    <?php foreach (['online'=>'🟢 Online', 'break'=>'☕ Break', 'offline'=>'⚪ Offline'] as $s=>$lbl): ?>
      <button data-avail="<?= $s ?>" class="btn btn-ghost !py-1.5 !px-2 text-xs <?= $me['availability']===$s?'ring-1 ring-white/30 bg-white/15':'' ?>"><?= $lbl ?></button>
    <?php endforeach; ?>
  </div>

  <div class="glass-strong rounded-3xl p-5 shadow-glass mt-3">
    <div class="flex items-center justify-between">
      <div>
        <div class="text-xs text-brand-mist">Wallet</div>
        <div class="text-3xl font-bold text-emerald-300">₱<?= number_format($me['wallet_balance'], 2) ?></div>
      </div>
      <div class="text-right">
        <div class="text-xs text-brand-mist">Delivered</div>
        <div class="text-3xl font-bold"><?= $done ?></div>
        <?php if ((int)$me['rating_count'] > 0): ?>
          <div class="text-xs text-yellow-300">★<?= number_format((float)$me['rating_avg'],2) ?> (<?= $me['rating_count'] ?>)</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="mt-3 flex gap-2">
      <a href="<?= url('driver/analytics') ?>" class="btn btn-ghost !py-1.5 !px-3 text-xs flex-1 text-center"><?= icon('stats', 'w-4 h-4') ?> My stats</a>
      <button id="sosBtn" class="btn btn-danger !py-1.5 !px-3 text-xs">🚨 SOS</button>
    </div>
  </div>

  <div id="live-offers">
  <?php if (!empty($offers)): ?>
  <h2 class="mt-6 text-sm font-semibold uppercase tracking-wider text-amber-300">🔔 New Offers (<?= count($offers) ?>)</h2>
  <div class="mt-3 space-y-3">
    <?php foreach ($offers as $o): ?>
      <div class="glass-strong rounded-2xl p-4 shadow-glow ring-1 ring-amber-300/30">
        <div class="flex items-center justify-between">
          <div class="font-mono text-sm font-semibold"><?= esc($o['tracking_number']) ?></div>
          <div class="text-right">
            <div class="text-xs text-brand-mist">You earn</div>
            <div class="text-xl font-bold text-emerald-300">₱<?= number_format($o['driver_earnings'], 2) ?></div>
          </div>
        </div>
        <div class="mt-2 text-sm">
          <div class="font-medium">📦 <?= esc($o['product_name'] ?: 'Package') ?></div>
          <div class="text-xs text-brand-mist mt-1">From: <?= esc($o['pickup_address']) ?></div>
          <div class="text-xs text-brand-mist">To: <?= esc($o['recipient_address']) ?></div>
          <div class="text-xs text-brand-mist mt-1">📏 <?= number_format((float)$o['distance_km'],2) ?> km · <?= esc($o['payment_method'] ?: 'wallet') ?></div>
          <?php if ($o['notes']): ?><div class="mt-2 text-xs glass rounded-lg p-2">Note: <?= esc($o['notes']) ?></div><?php endif; ?>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2">
          <form method="post" action="<?= url('driver/accept/'.$o['id']) ?>">
            <?php (new Router())->csrf_field(); ?>
            <input type="hidden" name="decision" value="accept">
            <button class="btn btn-success w-full">✓ Accept</button>
          </form>
          <form method="post" action="<?= url('driver/accept/'.$o['id']) ?>">
            <?php (new Router())->csrf_field(); ?>
            <input type="hidden" name="decision" value="decline">
            <button class="btn btn-danger w-full">✗ Decline</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <h2 class="mt-6 text-sm font-semibold uppercase tracking-wider text-brand-mist">Active Deliveries</h2>
  <div class="mt-3 space-y-3">
    <?php if (empty($active_tasks)): ?>
      <div class="glass rounded-2xl p-8 text-center">
        <div class="flex justify-center"><?= empty($offers) ? icon('check', 'w-10 h-10') : '<div class="text-4xl">⏳</div>' ?></div>
        <div class="mt-2 text-sm text-brand-mist"><?= empty($offers) ? 'No pending deliveries.' : 'Accept an offer above to get started.' ?></div>
      </div>
    <?php else: foreach ($active_tasks as $t): ?>
      <a href="<?= url('driver/parcel/'.$t['id']) ?>" class="block glass rounded-2xl p-4 hover:bg-white/15 transition">
        <div class="flex items-center justify-between">
          <div class="font-mono text-sm font-semibold"><?= esc($t['tracking_number']) ?></div>
          <?= status_chip($t['status']) ?>
        </div>
        <div class="mt-1 text-sm font-medium"><?= esc($t['recipient_name']) ?></div>
        <div class="text-xs text-brand-mist truncate">📍 <?= esc($t['recipient_address']) ?></div>
        <div class="text-xs text-emerald-300 mt-1">+ ₱<?= number_format((float)$t['driver_earnings'],2) ?> · <?= esc($t['payment_method'] ?: 'wallet') ?></div>
      </a>
    <?php endforeach; endif; ?>
  </div>
  </div>
</main>

<script>
(function(){
  const csrf = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
  // Live GPS upload
  if ('geolocation' in navigator) {
    let lastSent = 0;
    navigator.geolocation.watchPosition(pos => {
      if (Date.now() - lastSent < 8000) return;
      lastSent = Date.now();
      fetch('<?= url('api/driver/location') ?>', {
        method:'POST',
        body: new URLSearchParams({lat:pos.coords.latitude, lng:pos.coords.longitude, accuracy:pos.coords.accuracy||'', csrf_token:csrf}),
        credentials:'same-origin'
      }).catch(()=>{});
      window._lastPos = pos;
    }, e=>console.warn(e.message), {enableHighAccuracy:true, maximumAge:5000, timeout:20000});
  }
  // Set offline when page is closed/navigated away (fire-and-forget beacon).
  window.addEventListener('pagehide', () => {
    const fd = new FormData();
    fd.append('state', 'offline');
    fd.append('csrf_token', csrf);
    try { navigator.sendBeacon('<?= url('api/driver/availability') ?>', fd); } catch(e){}
  });

  // Availability toggles
  document.querySelectorAll('[data-avail]').forEach(b => {
    b.onclick = async () => {
      const d = await DropIT.post('<?= url('api/driver/availability') ?>', {state: b.dataset.avail});
      if (d.ok) {
        document.querySelectorAll('[data-avail]').forEach(x => x.classList.remove('ring-1','ring-white/30','bg-white/15'));
        b.classList.add('ring-1','ring-white/30','bg-white/15');
        DropIT.toast('Status: ' + d.state, 'info');
      }
    };
  });

  // SOS
  document.getElementById('sosBtn').onclick = async () => {
    if (!await DropIT.confirm2('Send SOS alert to admin? Only use in emergencies.')) return;
    const pos = window._lastPos;
    const d = await DropIT.post('<?= url('api/driver/sos') ?>', {
      lat: pos ? pos.coords.latitude : '', lng: pos ? pos.coords.longitude : '',
    });
    if (d.ok) { DropIT.sound.error(); DropIT.toast('SOS sent. Admin notified.', 'info'); }
    else DropIT.toast('Failed to send SOS', 'error');
  };

  // Auto partial-refresh offers + notif sound for new dispatch
  let prevOffers = <?= count($offers) ?>;
  setInterval(async () => {
    await DropIT.refresh(['live-offers']);
    const now = document.querySelectorAll('#live-offers .ring-amber-300\\/30').length;
    if (now > prevOffers){ DropIT.sound.dispatch(); DropIT.toast('New dispatch offer!', 'info'); }
    prevOffers = now;
  }, 10000);

  DropIT.startNotifPoll();
})();
</script>

<?php include __DIR__ . '/../partials/global_bubbles.php'; ?>
<nav class="fixed bottom-0 inset-x-0 z-40 pb-[env(safe-area-inset-bottom)]">
  <div class="mx-auto max-w-md m-3 glass-strong rounded-2xl shadow-glass">
    <div class="grid grid-cols-3 text-xs">
      <a href="<?= url('driver/dashboard') ?>" class="flex flex-col items-center py-2.5 text-white">
        <?= icon('tasks', 'w-5 h-5 dropit-icon-active') ?><span class="mt-0.5 font-medium">Tasks</span>
        <span class="mt-1 h-1 w-6 rounded-full bg-brand-sky"></span>
      </a>
      <a href="<?= url('driver/analytics') ?>" class="flex flex-col items-center py-2.5 text-brand-mist/80 hover:text-white">
        <?= icon('stats', 'w-5 h-5') ?><span class="mt-0.5 font-medium">Stats</span>
      </a>
      <a href="<?= url('driver/profile') ?>" class="flex flex-col items-center py-2.5 text-brand-mist/80 hover:text-white">
        <?= icon('profile', 'w-5 h-5') ?><span class="mt-0.5 font-medium">Profile</span>
      </a>
    </div>
  </div>
</nav>
</body></html>
