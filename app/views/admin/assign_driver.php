<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Dispatch';
$active = 'assign';

sweep_stale_dispatches(15);

$pending = db()->raw("
    SELECT p.id, p.tracking_number, p.status, p.driver_id,
           p.sender_name, p.recipient_name, p.recipient_address, p.pickup_address,
           p.pickup_lat, p.pickup_lng, p.dropoff_lat, p.dropoff_lng,
           p.is_customer_order, p.is_walkin, p.amount, p.payment_method, p.created_at,
           u.full_name AS customer_full_name, u.username AS customer_username
    FROM parcels p
    LEFT JOIN users u ON u.id = p.customer_id
    WHERE p.status = 'Pending'
    ORDER BY p.created_at DESC
")->fetchAll();

$active_parcels = db()->raw("
    SELECT p.id, p.tracking_number, p.status, p.driver_id,
           p.sender_name, p.recipient_name, p.recipient_address, p.pickup_address,
           p.pickup_lat, p.pickup_lng, p.dropoff_lat, p.dropoff_lng,
           p.is_customer_order, p.is_walkin, p.created_at,
           u.full_name AS customer_full_name, u.username AS customer_username
    FROM parcels p
    LEFT JOIN users u ON u.id = p.customer_id
    WHERE p.status NOT IN ('Pending','Delivered','Cancelled')
    ORDER BY p.created_at DESC
    LIMIT 100
")->fetchAll();

$customer_name = function(array $p): string {
    $name = trim((string)($p['customer_full_name'] ?? '')) ?: trim((string)($p['customer_username'] ?? ''));
    if ($name === '') $name = trim((string)($p['sender_name'] ?? ''));
    return $name !== '' ? $name : '—';
};

$drivers = db()->table('users')
    ->select('id, username, full_name, availability, rating_avg, rating_count')
    ->where('role','driver')->where('is_active',1)
    ->order_by('full_name','ASC')->get_all();
$driver_map = [];
foreach ($drivers as $d) $driver_map[$d['id']] = $d;

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
/* Dispatch-specific overrides */
.dispatch-card{transition:all .2s ease;cursor:default;}
.dispatch-card.ring-active{box-shadow:0 0 0 2px #60a5fa, 0 8px 32px rgba(59,130,246,.25);}
.driver-pill{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;}
.driver-pill.online{background:rgba(34,197,94,.18);color:#86efac;}
.driver-pill.offline{background:rgba(148,163,184,.12);color:#94a3b8;}
body.theme-light .driver-pill.online{background:rgba(21,128,61,.15);color:#15803d;}
body.theme-light .driver-pill.offline{background:rgba(71,85,105,.12);color:#475569;}
.nearest-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(245,158,11,.18);color:#fcd34d;}
body.theme-light .nearest-badge{background:rgba(180,83,9,.15);color:#92400e;}
.in-progress-row{transition:background .15s;}
.in-progress-row:hover{background:rgba(255,255,255,.06);}
body.theme-light .in-progress-row:hover{background:rgba(11,30,63,.04);}
/* Fix select/combobox: browsers ignore backdrop-filter on native selects */
select.input,
select.driver-select {
  background-color: #0f2347 !important;
  color: #e0f2fe !important;
  border: 1px solid rgba(255,255,255,0.2) !important;
}
select.input option,
select.driver-select option {
  background-color: #0f2347;
  color: #e0f2fe;
}
body.theme-light select.input,
body.theme-light select.driver-select {
  background-color: #f0f4ff !important;
  color: #0b1e3f !important;
  border-color: rgba(11,30,63,0.25) !important;
}
body.theme-light select.input option,
body.theme-light select.driver-select option {
  background-color: #fff;
  color: #0b1e3f;
}
</style>

<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-6 min-w-0">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold flex items-center gap-2">
          <?= icon('transit','w-7 h-7') ?>
          Dispatch Cockpit
        </h1>
        <p class="text-sm text-brand-mist mt-0.5">Assign drivers - Monitor fleet in real time</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <span class="chip chip-pending"><?= count($pending) ?> pending</span>
        <span class="chip chip-transit"><?= count($active_parcels) ?> in-flight</span>
        <a href="<?= url('admin/shipments/walkin') ?>" class="btn btn-primary text-sm">+ New Parcel</a>
      </div>
    </div>

    <div class="mt-1"><?php include __DIR__ . '/../partials/flash.php'; ?></div>

    <!-- Two-column cockpit -->
    <div class="grid lg:grid-cols-5 gap-4 mt-4">

      <!-- ── LEFT: Parcel queue ── -->
      <section class="lg:col-span-3 flex flex-col gap-4" id="pending-list">

        <!-- Pending cards -->
        <div class="glass-strong rounded-3xl p-4 shadow-glass">
          <div class="flex items-center justify-between mb-3" id="pending-count">
            <h2 class="font-bold flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse inline-block"></span>
              Pending dispatch
              <?php if (count($pending)): ?>
              <span class="chip chip-pending"><?= count($pending) ?> waiting</span>
              <?php endif; ?>
            </h2>
            <span class="text-xs text-brand-mist"><?= date('g:i a') ?></span>
          </div>

          <?php if (empty($pending)): ?>
            <div class="flex flex-col items-center justify-center py-10 text-brand-mist gap-2">
              <?= icon('delivered','w-10 h-10') ?>
              <p class="text-sm font-medium">All clear. no pending parcels </p>
            </div>
          <?php else: foreach ($pending as $p): ?>
            <div class="dispatch-card glass rounded-2xl p-4 mb-3 last:mb-0" data-parcel='<?= htmlspecialchars(json_encode([
              'id'  => (int)$p['id'],  'tn' => $p['tracking_number'],
              'pl'  => (float)$p['pickup_lat'],  'pg' => (float)$p['pickup_lng'],
              'dl'  => (float)$p['dropoff_lat'], 'dg' => (float)$p['dropoff_lng'],
              'pa'  => $p['pickup_address'],      'da' => $p['recipient_address'],
              'rn'  => $p['recipient_name'],      'am' => (float)$p['amount'],
            ])) ?>'>

              <!-- Card top row -->
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-1.5 mb-1">
                    <?= status_chip($p['status']) ?>
                    <?php if (!empty($p['is_walkin'])): ?><span class="chip chip-pending !text-[10px]">walk-in</span><?php endif; ?>
                    <?php if (!empty($p['is_customer_order'])): ?><span class="chip chip-delivered !text-[10px]">online order</span><?php endif; ?>
                  </div>
                  <div class="font-semibold text-sm"><?= esc($customer_name($p)) ?></div>
                  <div class="text-[11px] text-brand-mist font-mono mt-0.5"><?= esc($p['tracking_number']) ?></div>
                </div>
                <div class="text-right shrink-0">
                  <div class="font-bold text-emerald-300">₱<?= number_format((float)$p['amount'],2) ?></div>
                  <div class="text-[11px] text-brand-mist capitalize"><?= esc($p['payment_method'] ?: 'wallet') ?></div>
                </div>
              </div>

              <!-- Addresses -->
              <div class="mt-3 space-y-1.5 text-xs">
                <div class="flex items-start gap-2">
                  <span class="w-2 h-2 rounded-full bg-emerald-400 mt-0.5 shrink-0"></span>
                  <span class="text-brand-mist min-w-0 break-words"><?= esc($p['pickup_address'] ?: '—') ?></span>
                </div>
                <div class="ml-1 border-l border-dashed border-white/20 h-3 pl-0"></div>
                <div class="flex items-start gap-2">
                  <span class="w-2 h-2 rounded-full bg-red-400 mt-0.5 shrink-0"></span>
                  <span class="text-brand-mist min-w-0 break-words"><?= esc($p['recipient_address']) ?></span>
                </div>
              </div>

              <!-- Nearest hint -->
              <div class="nearest-hint hidden mt-2.5 flex items-center gap-2">
                <span class="nearest-badge">⭐ <span class="nearest-name"></span> · <span class="nearest-dist"></span></span>
              </div>

              <!-- Driver assign + actions -->
              <div class="mt-3 flex flex-col gap-2">
                <div class="flex gap-2">
                  <button class="focus-btn btn btn-ghost !py-1.5 !px-3 text-xs shrink-0">Preview</button>
                  <form method="post" action="<?= url('admin/shipments/assign/'.$p['id']) ?>" class="flex gap-2 flex-1">
                    <?php (new Router())->csrf_field(); ?>
                    <select name="driver_id" class="input !py-1.5 !text-xs flex-1 driver-select">
                      <option value="">Pick driver</option>
                      <?php foreach ($drivers as $d): ?>
                        <option value="<?= $d['id'] ?>">
                          <?= $d['availability']==='online' ? '🟢 ' : '⚪ ' ?>
                          <?= esc($d['full_name'] ?: $d['username']) ?>
                          <?= $d['rating_count']>0 ? ' ★'.number_format((float)$d['rating_avg'],1) : '' ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary !py-1.5 !px-3 text-xs shrink-0">Dispatch</button>
                  </form>
                </div>
                <form method="post" action="<?= url('admin/shipments/cancel/'.$p['id']) ?>"
                      onsubmit="return confirm('Cancel this parcel? Customer will be refunded if paid by wallet/GCash.');" class="flex gap-2">
                  <?php (new Router())->csrf_field(); ?>
                  <input type="hidden" name="from" value="admin/shipments/assign">
                  <input type="text" name="reason" class="input !py-1 text-[11px] flex-1" placeholder="Cancellation reason (optional)">
                  <button class="btn btn-danger !py-1 !px-3 text-[11px] shrink-0">Cancel</button>
                </form>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>

        <!-- In-progress table -->
        <div class="glass-strong rounded-3xl p-4 shadow-glass">
          <h2 class="font-bold flex items-center gap-2 mb-3">
            <span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span>
            In progress
            <?php if (count($active_parcels)): ?>
            <span class="chip chip-transit"><?= count($active_parcels) ?></span>
            <?php endif; ?>
          </h2>
          <?php if (empty($active_parcels)): ?>
            <p class="text-sm text-brand-mist text-center py-6">Nothing in flight right now.</p>
          <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead>
                <tr class="text-brand-mist uppercase tracking-wider text-[10px]">
                  <th class="pb-2 text-left font-semibold">Tracking</th>
                  <th class="pb-2 text-left font-semibold">Customer</th>
                  <th class="pb-2 text-left font-semibold">Driver</th>
                  <th class="pb-2 text-left font-semibold">Status</th>
                  <th class="pb-2 text-left font-semibold">Destination</th>
                  <th class="pb-2"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-white/5">
                <?php foreach ($active_parcels as $p): ?>
                <tr class="in-progress-row">
                  <td class="py-2 pr-3 font-mono text-[10px] text-brand-mist whitespace-nowrap"><?= esc($p['tracking_number']) ?></td>
                  <td class="py-2 pr-3 whitespace-nowrap"><?= esc($customer_name($p)) ?></td>
                  <td class="py-2 pr-3 whitespace-nowrap">
                    <?php if ($p['driver_id'] && isset($driver_map[$p['driver_id']])): ?>
                      <span class="driver-pill <?= ($driver_map[$p['driver_id']]['availability']==='online')?'online':'offline' ?>">
                        <?= esc($driver_map[$p['driver_id']]['full_name'] ?: $driver_map[$p['driver_id']]['username']) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-brand-mist">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2 pr-3"><?= status_chip($p['status']) ?></td>
                  <td class="py-2 pr-3 text-brand-mist max-w-[160px] truncate"><?= esc($p['recipient_address']) ?></td>
                  <td class="py-2 whitespace-nowrap flex gap-1">
                    <a href="<?= url('admin/parcel/'.$p['id']) ?>" class="btn btn-ghost !py-1 !px-2 text-[10px]">Open</a>
                    <form method="post" action="<?= url('admin/shipments/cancel/'.$p['id']) ?>"
                          onsubmit="return confirm('Cancel this parcel?');" style="display:inline">
                      <?php (new Router())->csrf_field(); ?>
                      <input type="hidden" name="from" value="admin/shipments/assign">
                      <button class="btn btn-danger !py-1 !px-2 text-[10px]">Cancel</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- ── RIGHT: Fleet map ── -->
      <aside class="lg:col-span-2 glass-strong rounded-3xl p-4 shadow-glass flex flex-col gap-3 sticky top-4 self-start">
        <div class="flex items-center justify-between">
          <h2 class="font-bold">Fleet map</h2>
          <div class="flex items-center gap-2">
            <span class="driver-pill online"><?= count(array_filter($drivers, fn($d)=>$d['availability']==='online')) ?> online</span>
            <span class="chip chip-transit"><?= count($drivers) ?> total</span>
          </div>
        </div>
        <div id="fleetMap" class="rounded-2xl overflow-hidden" style="height:480px;"></div>
        <div class="text-[11px] text-brand-mist space-y-1 border-t border-white/10 pt-2">
          <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-emerald-400 inline-block"></span> Driver (live GPS)</div>
          <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span> Nearest to selected pickup</div>
          <div class="text-brand-mist/70">Click <strong> Preview</strong> to plot a route and auto-select the closest available driver.</div>
        </div>
      </aside>
    </div>
  </main>
</div>

<!-- ── Dispatch Confirm Modal ── -->
<div id="dispatchModal" class="fixed inset-0 z-[80] hidden items-center justify-center p-4" style="background:rgba(6,17,36,.85);display:none;">
  <div class="glass-strong rounded-3xl p-6 max-w-sm w-full shadow-glass">
    <h2 class="text-lg font-bold mb-1">Confirm Dispatch</h2>
    <p id="dModalParcel" class="text-xs text-brand-mist mb-4"></p>

    <!-- Summary row -->
    <div class="glass rounded-2xl p-4 mb-4 space-y-1 text-sm">
      <div class="flex justify-between"><span class="text-brand-mist">Driver</span><span id="dModalDriver" class="font-semibold"></span></div>
      <div class="flex justify-between"><span class="text-brand-mist">Current fare</span><span id="dModalFare" class="font-bold text-emerald-300"></span></div>
    </div>

    <!-- Adjust price toggle -->
    <div id="dAdjustSection" class="hidden mb-4 space-y-3">
      <div>
        <label class="label !text-xs">New fare (&#8369;)</label>
        <input id="dNewFare" type="number" min="1" step="0.01" class="input !py-2 text-sm" placeholder="0.00">
      </div>
      <div>
        <label class="label !text-xs">Reason for adjustment</label>
        <textarea id="dReason" class="input !py-2 text-sm resize-none" rows="2" placeholder="e.g. Longer route, road construction…"></textarea>
      </div>
    </div>

    <div class="flex gap-2 mb-3">
      <button id="dToggleAdjust" class="btn btn-ghost text-xs flex-1">Adjust price</button>
    </div>

    <div class="flex gap-2">
      <button id="dCancel" class="btn btn-ghost flex-1">Cancel</button>
      <button id="dConfirm" class="btn btn-primary flex-1">Dispatch</button>
    </div>
    <p id="dModalError" class="text-xs text-red-300 mt-2 hidden"></p>
  </div>
</div>

<script>
(function(){
  /*  Fleet map  */
  const map = L.map('fleetMap').setView([14.5995, 120.9842], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19, attribution:'© OSM'}).addTo(map);

  const pin = c => L.divIcon({className:'', html:`<div style="background:${c};width:18px;height:18px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:2px solid #fff;box-shadow:0 4px 10px rgba(0,0,0,.35)"></div>`, iconSize:[22,22], iconAnchor:[11,20]});
  const drvIcon = L.divIcon({className:'', html:'<div style="background:#22c55e;width:26px;height:26px;border-radius:50%;border:3px solid #fff;box-shadow:0 0 0 2px rgba(34,197,94,.35),0 6px 14px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;font-size:12px">🚚</div>', iconSize:[30,30], iconAnchor:[15,15]});
  const drvIconNearest = L.divIcon({className:'', html:'<div style="background:#f59e0b;width:32px;height:32px;border-radius:50%;border:3px solid #fff;box-shadow:0 0 0 4px rgba(245,158,11,.45),0 6px 14px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;font-size:14px">⭐</div>', iconSize:[36,36], iconAnchor:[18,18]});

  const DRIVERS = <?= json_encode(array_values(array_map(fn($d) => ['id'=>(int)$d['id'], 'name'=>$d['full_name'] ?: $d['username']], $drivers))) ?>;
  const driverNames = new Map(DRIVERS.map(d => [d.id, d.name]));
  const driverLocs = new Map();
  const driverMarkers = new Map();

  function haversineKm(a, b){
    const R = 6371, toRad = d => d*Math.PI/180;
    const dLat = toRad(b[0]-a[0]), dLng = toRad(b[1]-a[1]);
    const x = Math.sin(dLat/2)**2 + Math.cos(toRad(a[0]))*Math.cos(toRad(b[0]))*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.min(1, Math.sqrt(x)));
  }
  function nearestDriverTo(lat, lng){
    let best = null;
    for (const [id, loc] of driverLocs){
      const km = haversineKm([lat, lng], [loc.lat, loc.lng]);
      if (!best || km < best.km) best = { id, km, lat: loc.lat, lng: loc.lng };
    }
    return best;
  }

  let nearestId = null, activeCard = null;
  function applyNearestStyle(){ for (const [id, m] of driverMarkers) m.setIcon(id === nearestId ? drvIconNearest : drvIcon); }

  async function pollDrivers(){
    try {
      const r = await fetch('<?= url('api/drivers/locations') ?>', {credentials:'same-origin'});
      const d = await r.json();
      if (!d.ok) return;
      const seen = new Set();
      for (const row of d.drivers){
        const id = parseInt(row.driver_id, 10);
        const lat = parseFloat(row.lat), lng = parseFloat(row.lng);
        seen.add(id); driverLocs.set(id, { lat, lng });
        const ll = [lat, lng];
        const label = driverNames.get(id) || ('Driver #' + id);
        if (driverMarkers.has(id)) { driverMarkers.get(id).setLatLng(ll).bindPopup(label); }
        else { driverMarkers.set(id, L.marker(ll, {icon: drvIcon}).addTo(map).bindPopup(label)); }
      }
      for (const [k,m] of driverMarkers) if (!seen.has(k)){ map.removeLayer(m); driverMarkers.delete(k); driverLocs.delete(k); }
      if (activeCard) refreshNearestForCard(activeCard);
    } catch(e){}
  }
  pollDrivers(); setInterval(pollDrivers, 8000);

  function refreshNearestForCard(card){
    const p = JSON.parse(card.getAttribute('data-parcel'));
    const hint = card.querySelector('.nearest-hint');
    const sel  = card.querySelector('.driver-select');
    if (!p.pl) return;
    const near = nearestDriverTo(p.pl, p.pg);
    if (near){
      nearestId = near.id;
      const name = driverNames.get(near.id) || ('Driver #' + near.id);
      if (hint){ hint.classList.remove('hidden'); hint.querySelector('.nearest-name').textContent = name; hint.querySelector('.nearest-dist').textContent = near.km.toFixed(2) + ' km'; }
      if (sel && !sel.value) sel.value = String(near.id);
    } else { nearestId = null; if (hint) hint.classList.add('hidden'); }
    applyNearestStyle();
  }

  let preview = [];
  function clearPreview(){ preview.forEach(x => map.removeLayer(x)); preview = []; }
  document.querySelectorAll('.focus-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const card = btn.closest('[data-parcel]');
      document.querySelectorAll('.dispatch-card.ring-active').forEach(c => c.classList.remove('ring-active'));
      card.classList.add('ring-active');
      const p = JSON.parse(card.getAttribute('data-parcel'));
      if (!p.pl || !p.dl) { DropIT.toast('No GPS coords for this parcel', 'error'); return; }
      clearPreview();
      const m1 = L.marker([p.pl,p.pg], {icon: pin('#22c55e')}).addTo(map).bindPopup('📦 Pickup: ' + p.pa).openPopup();
      const m2 = L.marker([p.dl,p.dg], {icon: pin('#ef4444')}).addTo(map).bindPopup('📍 Dropoff: ' + p.da);
      const ln = L.polyline([[p.pl,p.pg],[p.dl,p.dg]], {color:'#60a5fa', weight:3, dashArray:'6 8', opacity:.75}).addTo(map);
      preview = [m1, m2, ln]; activeCard = card;
      refreshNearestForCard(card);
      const points = [[p.pl,p.pg],[p.dl,p.dg]];
      const near = nearestDriverTo(p.pl, p.pg);
      if (near) points.push([near.lat, near.lng]);
      map.fitBounds(L.latLngBounds(points).pad(0.3));
    });
  });

  /* ── Dispatch Confirm Modal ── */
  const modal       = document.getElementById('dispatchModal');
  const dParcelTxt  = document.getElementById('dModalParcel');
  const dDriverTxt  = document.getElementById('dModalDriver');
  const dFareTxt    = document.getElementById('dModalFare');
  const dAdjSec     = document.getElementById('dAdjustSection');
  const dToggleBtn  = document.getElementById('dToggleAdjust');
  const dNewFare    = document.getElementById('dNewFare');
  const dReason     = document.getElementById('dReason');
  const dCancelBtn  = document.getElementById('dCancel');
  const dConfirmBtn = document.getElementById('dConfirm');
  const dError      = document.getElementById('dModalError');

  let _pendingForm = null;   // the form that was submitted
  let _parcelData  = null;   // data-parcel JSON of the card
  let _adjusting   = false;

  function showModal(form, parcelJson){
    _pendingForm = form;
    _parcelData  = parcelJson;
    _adjusting   = false;
    dAdjSec.classList.add('hidden');
    dToggleBtn.textContent = '✏️ Adjust price';
    dNewFare.value = ''; dReason.value = '';
    dError.classList.add('hidden');

    const sel = form.querySelector('.driver-select');
    const driverId = parseInt(sel?.value || '0', 10);
    const driverName = driverId ? (driverNames.get(driverId) || 'Driver #' + driverId) : '(none selected)';

    dParcelTxt.textContent = '📦 ' + (parcelJson.tn || '');
    dDriverTxt.textContent = driverName;
    dFareTxt.textContent   = '₱' + (parcelJson.am || '0').toLocaleString('en-PH', {minimumFractionDigits:2});

    modal.style.display = 'flex';
    modal.classList.remove('hidden');
  }

  function hideModal(){
    modal.style.display = 'none';
    modal.classList.add('hidden');
    _pendingForm = null; _parcelData = null;
  }

  dToggleBtn.onclick = () => {
    _adjusting = !_adjusting;
    dAdjSec.classList.toggle('hidden', !_adjusting);
    dToggleBtn.textContent = _adjusting ? '↩ Keep original price' : '✏️ Adjust price';
    if (_adjusting && !dNewFare.value) dNewFare.value = (_parcelData?.am || '').toString();
  };

  dCancelBtn.onclick = hideModal;
  modal.addEventListener('click', e => { if (e.target === modal) hideModal(); });

  dConfirmBtn.onclick = async () => {
    dError.classList.add('hidden');
    if (!_pendingForm) return;

    const sel      = _pendingForm.querySelector('.driver-select');
    const driverId = parseInt(sel?.value || '0', 10);
    if (!driverId) { dError.textContent = 'Please select a driver first.'; dError.classList.remove('hidden'); return; }

    if (!_adjusting) {
      // Normal dispatch — just submit the original form
      hideModal();
      _pendingForm.submit();
      return;
    }

    // Price adjustment flow
    const newAmt = parseFloat(dNewFare.value);
    const reason = dReason.value.trim();
    if (!newAmt || newAmt <= 0) { dError.textContent = 'Enter a valid new fare.'; dError.classList.remove('hidden'); return; }
    if (!reason) { dError.textContent = 'Please enter a reason.'; dError.classList.remove('hidden'); return; }

    const parcelId = _parcelData?.id;
    if (!parcelId) return;

    dConfirmBtn.disabled = true;
    dConfirmBtn.textContent = 'Sending…';

    const d = await DropIT.post('<?= url('api/parcel/') ?>' + parcelId + '/price-adjust', {
      driver_id:  driverId,
      new_amount: newAmt,
      reason:     reason,
    });

    dConfirmBtn.disabled = false;
    dConfirmBtn.textContent = 'Dispatch →';

    if (d.ok) {
      hideModal();
      DropIT.toast('Price adjustment sent to customer. Waiting for their approval.', 'info');
      DropIT.sound.dispatch();
      setTimeout(() => DropIT.refresh(['pending-list','pending-count']), 1200);
    } else {
      dError.textContent = d.error || 'Failed to send adjustment.';
      dError.classList.remove('hidden');
    }
  };

  // Intercept all dispatch forms (those that POST to assign endpoint)
  document.querySelectorAll('form[action*="assign"]').forEach(form => {
    form.addEventListener('submit', e => {
      e.preventDefault();
      const card = form.closest('[data-parcel]');
      const p = card ? JSON.parse(card.getAttribute('data-parcel')) : {};
      showModal(form, p);
    });
  });

  /* ── Ensure chat bubble is always above map ── */
  ['chatBubble','globalMsgBtn'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.zIndex = '9000';
  });

  DropIT.autoRefresh(['pending-list', 'pending-count'], 12000);
  DropIT.startNotifPoll();
})();
</script>
</body></html>
