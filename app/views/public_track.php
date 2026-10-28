<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Track';
$q = trim($_GET['q'] ?? ($tracking ?? ''));

$parcel = null; $history = [];
if ($q !== '') {
    $parcel = db()->table('parcels')
        ->select('id, tracking_number, status, pickup_address, recipient_address, created_at, updated_at')
        ->where('tracking_number', $q)->get();
    if ($parcel) {
        $history = db()->table('parcel_history')->select('status, notes, created_at')->where('parcel_id', $parcel['id'])->order_by('created_at', 'DESC')->get_all();
    }
}

/**
 * Mask an address to a rough area so public viewers don't see the full street.
 * Pulls tokens after the last comma (usually city/district).
 */
function mask_address(?string $addr): string {
    if (!$addr) return '—';
    $parts = array_values(array_filter(array_map('trim', explode(',', $addr)), fn($s) => $s !== ''));
    if (count($parts) <= 1) return '(area hidden)';
    // Take the last 2 parts for a "District, City" approximation.
    return implode(', ', array_slice($parts, -2));
}

include __DIR__ . '/partials/head.php';
?>
<main class="mx-auto max-w-md px-4 pt-8 pb-16">
  <a href="<?= url('/') ?>" class="flex items-center gap-3 mb-6 select-none" data-admin-logo>
    <img src="<?= url('resources/weblogo.png') ?>" alt="Drop IT" class="w-10 h-10 rounded-xl ring-1 ring-white/30 bg-white/10 p-1">
    <span class="font-bold tracking-tight text-lg">Drop IT · Tracker</span>
  </a>

  <h1 class="text-2xl font-bold">Track a parcel</h1>
  <p class="text-sm text-brand-mist mt-1">No login needed. Paste the tracking number.</p>

  <form method="get" action="<?= url('track') ?>" class="mt-4 flex gap-2">
    <input name="q" value="<?= esc($q) ?>" class="input flex-1" placeholder="DROPIT00042" required autofocus>
    <button class="btn btn-primary">Track</button>
  </form>

  <?php if ($q === ''): ?>
    <div class="mt-6 glass rounded-2xl p-5 text-sm text-brand-mist">
      Already have an account? <a href="<?= url('login') ?>" class="text-brand-sky hover:text-white">Login</a> for full details + live driver map.
    </div>
  <?php elseif (!$parcel): ?>
    <div class="mt-6 glass-strong rounded-3xl p-8 text-center">
      <div class="text-4xl">🔎</div>
      <div class="mt-2 font-semibold">No parcel found</div>
      <div class="text-sm text-brand-mist mt-1">Check the tracking number and try again.</div>
    </div>
  <?php else: ?>
    <div class="mt-5 glass-strong rounded-3xl p-5 shadow-glass">
      <div class="flex items-center justify-between">
        <div class="font-mono text-sm font-semibold"><?= esc($parcel['tracking_number']) ?></div>
        <?= status_chip($parcel['status']) ?>
      </div>
      <div class="mt-4 text-sm space-y-1.5">
        <div><span class="text-brand-mist">Origin:</span> <?= esc(mask_address($parcel['pickup_address'])) ?></div>
        <div><span class="text-brand-mist">Destination:</span> <?= esc(mask_address($parcel['recipient_address'])) ?></div>
        <div><span class="text-brand-mist">Placed:</span> <?= date('M j, Y g:ia', strtotime($parcel['created_at'])) ?></div>
        <div><span class="text-brand-mist">Last update:</span> <?= date('M j, Y g:ia', strtotime($parcel['updated_at'])) ?></div>
      </div>

      <?php
        $stages = ['Pending','Picked Up','In Transit','Arrived','Delivered'];
        $idx = array_search($parcel['status'], $stages);
        if ($idx === false) $idx = 0;
        if ($parcel['status'] !== 'Cancelled'):
      ?>
        <div class="mt-4 flex items-center gap-1.5">
          <?php foreach ($stages as $i=>$_): ?>
            <div class="h-1.5 flex-1 rounded-full <?= $i<=$idx ? 'bg-brand-sky' : 'bg-white/15' ?>"></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <h2 class="mt-6 text-sm font-semibold text-brand-mist uppercase tracking-wider">Timeline</h2>
    <div class="mt-3 space-y-2">
      <?php foreach ($history as $h): ?>
        <div class="glass rounded-xl p-3 flex items-start gap-3">
          <div class="w-2 h-2 rounded-full bg-brand-sky mt-2"></div>
          <div class="flex-1">
            <div class="flex items-center justify-between">
              <div class="font-medium text-sm"><?= esc($h['status']) ?></div>
              <div class="text-[11px] text-brand-mist"><?= date('M j, g:ia', strtotime($h['created_at'])) ?></div>
            </div>
            <?php if ($h['notes']): ?>
              <div class="text-xs text-brand-mist mt-0.5"><?= esc($h['notes']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-6 text-center text-[11px] text-brand-mist">
      Sender + recipient names and exact addresses hidden for privacy.<br>
      <a href="<?= url('login') ?>" class="text-brand-sky hover:text-white">Login as the customer</a> to see the full map and driver contact.
    </div>
  <?php endif; ?>
</main>
</body></html>
