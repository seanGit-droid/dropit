<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Track Parcel';
$active = 'track';
$uid = (int)$_SESSION['user_id'];
$q = trim($_GET['q'] ?? '');
$tab = $_GET['tab'] ?? 'active';

$parcel = null; $history = []; $driver = null; $initial_driver_loc = null;
if ($q !== '') {
    $parcel = db()->table('parcels')
        ->select('id, tracking_number, status, sender_name, recipient_name, recipient_address, pickup_address, pickup_lat, pickup_lng, dropoff_lat, dropoff_lng, driver_id, customer_id, created_at, updated_at, amount, payment_method, payment_status')
        ->where('tracking_number', $q)->get();
    if ($parcel && (int)$parcel['customer_id'] !== $uid) { $parcel = null; }
    if ($parcel) {
        $history = db()->table('parcel_history')->select('status, notes, created_at')->where('parcel_id', $parcel['id'])->order_by('created_at', 'DESC')->get_all();
        if ($parcel['driver_id']) {
            $driver = db()->table('users')->select('full_name, phone, rating_avg, rating_count')->where('id', $parcel['driver_id'])->get();
            $initial_driver_loc = db()->table('driver_locations')
                ->select('lat, lng, updated_at')->where('driver_id', $parcel['driver_id'])->get();
        }
    }
}
$has_map = $parcel && $parcel['pickup_lat'] && $parcel['dropoff_lat'];
$can_pay_gcash = $parcel && $parcel['payment_method']==='gcash' && $parcel['payment_status']==='unpaid';

if ($parcel) {
    $chat_parcel_id = (int)$parcel['id'];
    $chat_peer_name = $driver['full_name'] ?? 'Awaiting driver';
    $chat_self_role = 'customer';
    $chat_closed = in_array($parcel['status'], ['Delivered','Cancelled']);
    $chat_closed_reason = $parcel['status'] === 'Delivered' ? 'Delivered — chat closed' : 'Cancelled — chat closed';
    if ($parcel['driver_id']) {
        $chat_peer_user_id = (int)$parcel['driver_id'];
        $call_target_user_id = (int)$parcel['driver_id'];
        $call_peer_name = $driver['full_name'] ?? 'Driver';
    }
}

// List views (when no specific parcel is selected)
$active_list = $past_list = [];
if ($q === '') {
    $active_list = db()->table('parcels')
        ->select('id, tracking_number, status, recipient_address, updated_at')
        ->where('customer_id', $uid)
        ->where('status', '!=', 'Delivered')
        ->where('status', '!=', 'Cancelled')
        ->order_by('updated_at', 'DESC')->get_all();
    $past_rows = db()->raw(
        "SELECT id, tracking_number, status, recipient_address, updated_at
           FROM parcels
          WHERE customer_id = ? AND status IN ('Delivered','Cancelled')
          ORDER BY updated_at DESC
          LIMIT 50",
        [$uid]
    )->fetchAll();
    $past_list = $past_rows ?: [];
}

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<?php if ($has_map): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>

<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('customer/dashboard') ?>" class="text-sm text-brand-mist">← Back</a>
  <h1 class="text-2xl font-bold mt-2">Track Parcel</h1>

  <form method="get" action="<?= url('customer/track') ?>" class="mt-4 flex gap-2">
    <input name="q" value="<?= esc($q) ?>" class="input flex-1" placeholder="Paste tracking number (DROPIT…)" required>
    <button class="btn btn-primary">Track</button>
  </form>

  <?php if ($q === ''): /* --- LIST VIEW with Active/Past tabs --- */ ?>

    <div class="mt-5 flex gap-1 p-1 rounded-2xl glass">
      <a href="?tab=active" class="flex-1 text-center text-sm py-2 rounded-xl transition <?= $tab==='active' ? 'bg-white/20 font-semibold' : 'text-brand-mist hover:bg-white/10' ?>">Active <span class="text-[11px] opacity-70">(<?= count($active_list) ?>)</span></a>
      <a href="?tab=past" class="flex-1 text-center text-sm py-2 rounded-xl transition <?= $tab==='past' ? 'bg-white/20 font-semibold' : 'text-brand-mist hover:bg-white/10' ?>">Past <span class="text-[11px] opacity-70">(<?= count($past_list) ?>)</span></a>
    </div>

    <div class="mt-4 space-y-2">
      <?php
        $list = $tab === 'past' ? $past_list : $active_list;
        $empty_msg = $tab === 'past' ? 'No past parcels yet.' : 'No active parcels — start by placing an order.';
        if (empty($list)): ?>
        <div class="glass-strong rounded-3xl p-10 text-center">
          <div class="text-5xl"><?= $tab === 'past' ? '📭' : '🚚' ?></div>
          <div class="mt-3 font-semibold"><?= $empty_msg ?></div>
          <?php if ($tab !== 'past'): ?>
            <a href="<?= url('customer/order') ?>" class="btn btn-primary mt-4 inline-block">Place an order →</a>
          <?php endif; ?>
        </div>
      <?php else: foreach ($list as $p):
        $stages = ['Pending','Picked Up','In Transit','Arrived','Delivered'];
        $idx = array_search($p['status'], $stages);
        if ($idx === false) $idx = 0;
        $is_cancelled = $p['status'] === 'Cancelled';
      ?>
        <a href="?q=<?= urlencode($p['tracking_number']) ?>" class="block glass rounded-2xl p-4 hover:bg-white/15 transition">
          <div class="flex items-center justify-between">
            <div class="font-mono text-sm font-semibold"><?= esc($p['tracking_number']) ?></div>
            <?= status_chip($p['status']) ?>
          </div>
          <div class="mt-1.5 text-xs text-brand-mist truncate">→ <?= esc($p['recipient_address']) ?></div>
          <?php if (!$is_cancelled): ?>
            <div class="mt-2.5 flex items-center gap-1.5">
              <?php foreach ($stages as $i=>$_): ?>
                <div class="h-1.5 flex-1 rounded-full <?= $i<=$idx ? 'bg-brand-sky' : 'bg-white/15' ?>"></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="mt-1.5 text-[10px] text-brand-mist/70"><?= date('M j, g:ia', strtotime($p['updated_at'])) ?></div>
        </a>
      <?php endforeach; endif; ?>
    </div>

  <?php elseif (!$parcel): /* --- NOT FOUND --- */ ?>

    <div class="mt-6 glass rounded-2xl p-6 text-center">
      <div class="text-4xl">🔎</div>
      <div class="mt-2 font-semibold">No parcel found</div>
      <div class="text-sm text-brand-mist mt-1">Check the tracking number and try again.</div>
      <a href="<?= url('customer/track') ?>" class="btn btn-ghost mt-4 inline-block">← Back to all</a>
    </div>

  <?php else: /* --- DETAIL VIEW --- */ ?>

    <div id="live-parcel">
    <div class="mt-5 glass-strong rounded-3xl p-5 shadow-glass">
      <div class="flex items-center justify-between">
        <div class="font-mono text-sm font-semibold"><?= esc($parcel['tracking_number']) ?></div>
        <?= status_chip($parcel['status']) ?>
      </div>
      <div class="mt-3 text-sm space-y-1">
        <div><span class="text-brand-mist">Pickup:</span> <?= esc($parcel['pickup_address'] ?: $parcel['sender_name']) ?></div>
        <div><span class="text-brand-mist">Dropoff:</span> <?= esc($parcel['recipient_address']) ?></div>
        <?php if ($driver): ?>
          <div><span class="text-brand-mist">Driver:</span> <?= esc($driver['full_name']) ?> · <?= esc(Notifier::display_phone($driver['phone'] ?: '')) ?>
          <?php if ((int)$driver['rating_count'] > 0): ?><span class="text-yellow-300">★<?= number_format((float)$driver['rating_avg'],1) ?></span><?php endif; ?>
          </div>
        <?php endif; ?>
        <div><span class="text-brand-mist">Payment:</span> <?= esc($parcel['payment_method']) ?> · <?= esc($parcel['payment_status']) ?></div>
      </div>

      <?php if ($can_pay_gcash): ?>
        <form method="post" action="<?= url('pay/gcash/create/'.$parcel['id']) ?>">
          <?php (new Router())->csrf_field(); ?>
          <button type="submit" class="btn btn-primary w-full mt-3">💳 Pay ₱<?= number_format((float)$parcel['amount'],2) ?> via GCash</button>
        </form>
      <?php endif; ?>

      <?php if (in_array($parcel['status'], ['Delivered','Return Pending'])): ?>
        <div class="mt-3 grid grid-cols-3 gap-2">
          <a href="<?= url('customer/rate/'.$parcel['id']) ?>" class="btn btn-primary !text-xs">⭐ Rate</a>
          <a href="<?= url('customer/receipt/'.urlencode($parcel['tracking_number'])) ?>" class="btn btn-ghost !text-xs">🧾 Receipt</a>
          <a href="<?= url('customer/return/'.$parcel['id']) ?>" class="btn btn-danger !text-xs">↩️ Return</a>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($has_map): ?>
    <div class="mt-5 glass rounded-2xl overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 text-sm">
        <span class="font-semibold">Live map</span>
        <span id="liveBadge" class="chip <?= $initial_driver_loc ? 'chip-delivered' : 'chip-pending' ?>">
          <?= $initial_driver_loc ? '● Driver live' : 'Waiting for driver…' ?>
        </span>
      </div>
      <div id="trackMap" class="h-72 w-full z-0"></div>
      <div id="etaBadge" class="px-4 py-2 text-xs text-brand-mist"></div>
    </div>
    <?php endif; ?>

    <h2 class="mt-6 text-sm font-semibold text-brand-mist uppercase tracking-wider">Timeline</h2>
    <div class="mt-3 space-y-2">
      <?php foreach ($history as $h):
        $is_img = is_string($h['notes'] ?? '') && str_starts_with($h['notes'], '[IMG]');
        $img_src = null; $img_caption = '';
        if ($is_img) {
          $raw = substr($h['notes'], 5);
          if (str_contains($raw, '|')) {
            [$img_src, $img_caption] = explode('|', $raw, 2);
          } else {
            $img_src = $raw;
          }
        }
      ?>
        <div class="glass rounded-xl p-3 flex items-start gap-3">
          <div class="w-2 h-2 rounded-full <?= $is_img ? 'bg-emerald-400' : 'bg-brand-sky' ?> mt-2"></div>
          <div class="flex-1">
            <div class="flex items-center justify-between">
              <div class="font-medium text-sm"><?= $is_img ? '📷 Transit photo' : esc($h['status']) ?></div>
              <div class="text-[11px] text-brand-mist"><?= date('M j, g:ia', strtotime($h['created_at'])) ?></div>
            </div>
            <?php if ($is_img && $img_src): ?>
              <a href="<?= url($img_src) ?>" target="_blank" rel="noopener" class="block mt-2">
                <img src="<?= url($img_src) ?>" alt="Driver update" class="rounded-xl w-full object-cover" style="max-height:320px">
              </a>
              <?php if (!empty($img_caption)): ?>
                <div class="text-xs text-brand-mist mt-1.5 italic">"<?= esc($img_caption) ?>"</div>
              <?php endif; ?>
            <?php elseif ($h['notes']): ?>
              <div class="text-xs text-brand-mist mt-0.5"><?= esc($h['notes']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    </div>

  <?php endif; ?>
</main>

<?php if ($parcel): ?>
  <?php include __DIR__ . '/../partials/chat_bubble.php'; ?>
  <?php if ($parcel['driver_id']) include __DIR__ . '/../partials/voice_call.php'; ?>
<?php endif; ?>

<?php if ($has_map): ?>
<script>
(function(){
  const PICK = [<?= (float)$parcel['pickup_lat'] ?>, <?= (float)$parcel['pickup_lng'] ?>];
  const DROP = [<?= (float)$parcel['dropoff_lat'] ?>, <?= (float)$parcel['dropoff_lng'] ?>];
  const TRACKING  = <?= json_encode($parcel['tracking_number']) ?>;
  const initialLoc = <?= json_encode($initial_driver_loc) ?>;

  // Map — respect reduced motion and touch gestures.
  const map = L.map('trackMap', {
    zoomControl: true,
    tap: true,
    inertia: true,
    preferCanvas: false,   // DOM markers animate smoother than canvas for a single marker
  }).setView([(PICK[0]+DROP[0])/2, (PICK[1]+DROP[1])/2], 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '© OpenStreetMap', crossOrigin: true,
  }).addTo(map);

  const pin = c => L.divIcon({
    className:'',
    html:`<div style="background:${c};width:22px;height:22px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.35)"></div>`,
    iconSize:[26,26], iconAnchor:[13,24],
  });
  L.marker(PICK,{icon:pin('#22c55e')}).addTo(map).bindPopup('Pickup');
  L.marker(DROP,{icon:pin('#ef4444')}).addTo(map).bindPopup('Dropoff');
  L.polyline([PICK,DROP],{color:'#60a5fa',weight:3,opacity:.6,dashArray:'6 8'}).addTo(map);
  map.fitBounds(L.latLngBounds(PICK,DROP).pad(0.35));

  // Two driver icons: live + stale.
  const driverIcon = L.divIcon({
    className:'',
    html:'<div id="drvDot" style="background:#3b82f6;width:30px;height:30px;border-radius:50%;border:4px solid #fff;box-shadow:0 0 0 3px rgba(59,130,246,.35),0 6px 16px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px">🚚</div>',
    iconSize:[34,34], iconAnchor:[17,17],
  });
  const staleIcon = L.divIcon({
    className:'',
    html:'<div style="background:#6b7280;width:30px;height:30px;border-radius:50%;border:4px solid #fff;opacity:.65;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px">🚚</div>',
    iconSize:[34,34], iconAnchor:[17,17],
  });

  let driverMarker = null;
  let animFrame = null;
  let lastServerUpdate = 0; // timestamp (ms) of the last driver_location we received
  const badge = document.getElementById('liveBadge');
  const eta   = document.getElementById('etaBadge');

  function haversine(a, b){
    const R=6371, r=d=>d*Math.PI/180;
    const dLat=r(b[0]-a[0]), dLng=r(b[1]-a[1]);
    const x=Math.sin(dLat/2)**2+Math.cos(r(a[0]))*Math.cos(r(b[0]))*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.min(1,Math.sqrt(x)));
  }

  // Smoothly slide the marker between two positions over `durationMs`.
  function animateMarker(marker, from, to, durationMs){
    if (!marker) return;
    if (animFrame) cancelAnimationFrame(animFrame);
    const start = performance.now();
    const ease = t => t<0.5 ? 2*t*t : 1 - Math.pow(-2*t+2,2)/2; // easeInOutQuad
    function step(now){
      const t = Math.min(1, (now - start) / durationMs);
      const k = ease(t);
      const lat = from[0] + (to[0]-from[0]) * k;
      const lng = from[1] + (to[1]-from[1]) * k;
      marker.setLatLng([lat,lng]);
      if (t < 1) animFrame = requestAnimationFrame(step);
      else animFrame = null;
    }
    animFrame = requestAnimationFrame(step);
  }

  function renderLoc(lat, lng, updatedAt){
    lastServerUpdate = Date.now();
    const serverAgeSec = Math.max(0, Math.round((Date.now() - new Date((updatedAt||'').replace(' ','T')).getTime())/1000));
    const stale = serverAgeSec > 120;
    const useIcon = stale ? staleIcon : driverIcon;

    if (!driverMarker) {
      driverMarker = L.marker([lat,lng],{icon:useIcon,zIndexOffset:500,title:'Driver'}).addTo(map).bindPopup('Driver');
    } else {
      const prev = driverMarker.getLatLng();
      driverMarker.setIcon(useIcon);
      // Skip animation for tiny jumps (< 5m) — avoids visual jitter.
      const deltaMeters = haversine([prev.lat,prev.lng],[lat,lng]) * 1000;
      if (deltaMeters > 5) animateMarker(driverMarker, [prev.lat,prev.lng], [lat,lng], 1200);
      else driverMarker.setLatLng([lat,lng]);
    }

    updateBadges(lat, lng, serverAgeSec, stale);
  }

  function updateBadges(lat, lng, serverAgeSec, stale){
    badge.textContent = stale
      ? `Driver offline · ${serverAgeSec > 60 ? Math.floor(serverAgeSec/60)+'m' : serverAgeSec+'s'} ago`
      : `● Driver live · ${serverAgeSec}s ago`;
    badge.className = 'chip ' + (stale ? 'chip-pending' : 'chip-delivered');
    const km = haversine([lat,lng], DROP);
    const mins = Math.max(1, Math.ceil(km / 22 * 60));
    eta.textContent = stale
      ? `Last known ${km.toFixed(2)} km from dropoff`
      : `⏱️ ETA ~${mins} min · ${km.toFixed(2)} km to dropoff`;
  }

  // Tick badge age every second so "3s ago" → "4s ago" without a fresh poll.
  setInterval(() => {
    if (!driverMarker || !lastServerUpdate) return;
    const p = driverMarker.getLatLng();
    const ageSec = Math.round((Date.now() - lastServerUpdate)/1000);
    updateBadges(p.lat, p.lng, ageSec, ageSec > 120);
  }, 1000);

  // Server-seeded first paint.
  if (initialLoc && initialLoc.lat && initialLoc.lng) {
    renderLoc(parseFloat(initialLoc.lat), parseFloat(initialLoc.lng), initialLoc.updated_at);
  }

  // Polling — pause when hidden, coalesce concurrent fetches, exponential back-off on errors.
  let inflight = null;
  let pollTimer = null;
  let pollInterval = 6000;
  let errorCount = 0;
  const POLL_URL = `<?= url('api/parcel/') ?>${encodeURIComponent(TRACKING)}/track`;

  async function pollOnce(){
    if (document.hidden) return; // don't burn battery / bandwidth
    if (inflight) return inflight;
    try {
      inflight = fetch(POLL_URL, {credentials:'same-origin', cache:'no-store'});
      const r = await inflight;
      const d = await r.json();
      errorCount = 0; pollInterval = 6000;
      if (d.ok && d.driver_location) {
        renderLoc(
          parseFloat(d.driver_location.lat),
          parseFloat(d.driver_location.lng),
          d.driver_location.updated_at
        );
      } else {
        badge.textContent = 'Waiting for driver…';
        badge.className = 'chip chip-pending';
        eta.textContent = '';
      }
    } catch(e){
      errorCount++;
      // Exponential back-off up to 30s on repeated errors.
      pollInterval = Math.min(30000, 6000 * Math.pow(1.8, Math.min(4, errorCount)));
    } finally {
      inflight = null;
    }
  }
  function schedulePoll(){
    if (pollTimer) clearTimeout(pollTimer);
    pollTimer = setTimeout(async () => { await pollOnce(); schedulePoll(); }, pollInterval);
  }

  pollOnce().then(schedulePoll);

  // Poll instantly when the tab comes back to foreground — users expect fresh data.
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) pollOnce();
  });

  // Make the map resize cleanly on orientation / viewport changes (mobile).
  const onResize = () => { try { map.invalidateSize({pan:false}); } catch(e){} };
  window.addEventListener('resize', onResize);
  window.addEventListener('orientationchange', onResize);

  // Cleanup when the page is unloaded — avoids leaking timers across SPA-like navigations.
  window.addEventListener('pagehide', () => {
    if (pollTimer) clearTimeout(pollTimer);
    if (animFrame) cancelAnimationFrame(animFrame);
  });

  // Light partial refresh for parcel status (timeline, payment state).
  DropIT.autoRefresh(['live-parcel'], 12000);
  DropIT.startNotifPoll();
})();
</script>
<?php else: ?>
<script>if (window.DropIT) DropIT.startNotifPoll();</script>
<?php endif; ?>
<?php include __DIR__ . '/../partials/mobile_nav.php'; ?>
</body></html>
