<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Drivers Map';
$active = 'drivers_map';

// All drivers with their last known location.
$drivers = db()->raw("
    SELECT u.id, u.username, u.full_name, u.phone, u.availability, u.rating_avg, u.rating_count,
           dl.lat, dl.lng, dl.accuracy, dl.updated_at AS loc_updated,
           TIMESTAMPDIFF(MINUTE, dl.updated_at, NOW()) AS mins_ago
    FROM users u
    LEFT JOIN driver_locations dl ON dl.driver_id = u.id
    WHERE u.role='driver' AND u.is_active=1
    ORDER BY dl.updated_at DESC
")->fetchAll();

// Count by freshness
$online = 0; $recent = 0; $stale = 0; $nolocation = 0;
foreach ($drivers as $d) {
    if (!$d['lat']) { $nolocation++; continue; }
    $m = (int)$d['mins_ago'];
    if ($m <= 5) $online++;
    elseif ($m <= 30) $recent++;
    else $stale++;
}

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold">Drivers Map</h1>
        <p class="text-sm text-brand-mist">All drivers and their last known GPS position.</p>
      </div>
      <div class="flex gap-2 text-xs">
        <span class="chip chip-delivered">🟢 <?= $online ?> live</span>
        <span class="chip chip-pending">🟡 <?= $recent ?> recent</span>
        <span class="chip chip-cancelled">⚪ <?= $stale ?> stale</span>
        <span class="chip chip-transit">📵 <?= $nolocation ?> no GPS</span>
      </div>
    </div>

    <div class="mt-5 grid lg:grid-cols-5 gap-4">
      <!-- Driver list -->
      <section class="lg:col-span-2 glass-strong rounded-3xl p-4 shadow-glass">
        <div class="flex items-center justify-between mb-2">
          <h2 class="font-semibold">Fleet (<?= count($drivers) ?>)</h2>
          <input id="filter" class="input !py-1.5 text-xs w-36" placeholder="Search name…">
        </div>
        <div id="driverList" class="space-y-2 max-h-[600px] overflow-auto scrollbar-thin pr-1">
          <?php foreach ($drivers as $d):
            $m = $d['lat'] ? (int)$d['mins_ago'] : null;
            $freshClass = !$d['lat'] ? 'chip-transit' : ($m<=5 ? 'chip-delivered' : ($m<=30 ? 'chip-pending' : 'chip-cancelled'));
            $freshLabel = !$d['lat'] ? 'No GPS' : ($m<=5 ? 'Live' : ($m<=30 ? $m.'m ago' : round($m/60,1).'h ago'));
          ?>
            <div class="glass rounded-xl p-3 text-sm driver-card" data-name="<?= esc(strtolower($d['full_name'] ?: $d['username'])) ?>">
              <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                  <div class="font-semibold truncate"><?= esc($d['full_name'] ?: $d['username']) ?></div>
                  <div class="text-xs text-brand-mist"><?= esc(Notifier::display_phone($d['phone'] ?: '')) ?></div>
                </div>
                <span class="chip <?= $freshClass ?> !text-[10px]"><?= $freshLabel ?></span>
              </div>
              <div class="mt-1 flex items-center justify-between text-xs text-brand-mist">
                <span><?= $d['availability']==='online' ? '🟢 online' : ($d['availability']==='break' ? '☕ break' : '⚪ offline') ?></span>
                <?php if ((int)$d['rating_count'] > 0): ?>
                  <span class="text-yellow-300">★<?= number_format((float)$d['rating_avg'],1) ?> (<?= $d['rating_count'] ?>)</span>
                <?php endif; ?>
              </div>
              <div class="mt-2 flex gap-2">
                <?php if ($d['lat']): ?>
                  <button type="button" class="btn btn-ghost !py-1 !px-2 text-[11px] flex-1 focus-btn" data-lat="<?= $d['lat'] ?>" data-lng="<?= $d['lng'] ?>" data-id="<?= $d['id'] ?>">📍 Focus</button>
                <?php else: ?>
                  <span class="btn btn-ghost !py-1 !px-2 text-[11px] flex-1 opacity-50">📵 No GPS yet</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- Map -->
      <aside class="lg:col-span-3 glass-strong rounded-3xl p-4 shadow-glass">
        <div id="fleetMap" class="rounded-2xl overflow-hidden" style="height:640px;"></div>
        <div class="mt-2 text-[11px] text-brand-mist">Map auto-refreshes every 8s. Pins colored by freshness.</div>
      </aside>
    </div>
  </main>
</div>

<script>
(function(){
  const DRIVERS = <?= json_encode($drivers) ?>;
  const map = L.map('fleetMap').setView([14.5995, 120.9842], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'© OSM'}).addTo(map);

  function colorFor(m){ if (m === null) return '#94a3b8'; if (m <= 5) return '#22c55e'; if (m <= 30) return '#eab308'; return '#94a3b8'; }
  function icon(color, label){
    return L.divIcon({
      className:'',
      html:`<div style="position:relative;">
        <div style="background:${color};width:30px;height:30px;border-radius:50%;border:3px solid #fff;box-shadow:0 0 0 2px ${color}40,0 6px 14px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px">🚚</div>
        <div style="position:absolute;top:34px;left:50%;transform:translateX(-50%);background:rgba(8,20,52,.85);color:#fff;font-size:10px;padding:2px 6px;border-radius:6px;white-space:nowrap;max-width:140px;overflow:hidden;text-overflow:ellipsis;">${label}</div>
      </div>`,
      iconSize:[30,30], iconAnchor:[15,15]
    });
  }

  const markers = new Map();
  const bounds = [];
  for (const d of DRIVERS) {
    if (!d.lat) continue;
    const ll = [parseFloat(d.lat), parseFloat(d.lng)];
    const col = colorFor(parseInt(d.mins_ago));
    const m = L.marker(ll, {icon: icon(col, (d.full_name || d.username))}).addTo(map);
    const fresh = d.mins_ago === null ? '—' : (d.mins_ago <= 5 ? 'live' : d.mins_ago + ' min ago');
    m.bindPopup(`<b>${d.full_name || d.username}</b><br>${d.phone || ''}<br>Last GPS: ${fresh}`);
    markers.set(parseInt(d.id), m);
    bounds.push(ll);
  }
  if (bounds.length) map.fitBounds(L.latLngBounds(bounds).pad(0.25));

  // Focus button
  document.querySelectorAll('.focus-btn').forEach(b => {
    b.onclick = () => {
      const lat = parseFloat(b.dataset.lat), lng = parseFloat(b.dataset.lng);
      const id = parseInt(b.dataset.id);
      map.setView([lat,lng], 16);
      const m = markers.get(id); if (m) m.openPopup();
    };
  });

  // Client-side filter
  document.getElementById('filter').addEventListener('input', (e) => {
    const q = e.target.value.toLowerCase().trim();
    document.querySelectorAll('.driver-card').forEach(c => {
      c.style.display = (!q || c.dataset.name.includes(q)) ? '' : 'none';
    });
  });

  // Poll for fresh GPS every 8s.
  async function pollLive(){
    try {
      const r = await fetch('<?= url('api/drivers/locations') ?>', {credentials:'same-origin'});
      const d = await r.json();
      if (!d.ok) return;
      for (const row of d.drivers) {
        const id = parseInt(row.driver_id);
        const ll = [parseFloat(row.lat), parseFloat(row.lng)];
        if (markers.has(id)) markers.get(id).setLatLng(ll);
      }
    } catch(e){}
  }
  setInterval(pollLive, 8000);
})();
</script>
</body></html>
