<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Place Order';
$active = '';
$uid = $_SESSION['user_id'];
$me = db()->table('users')->select('id, full_name, phone, wallet_balance')->where('id', $uid)->get();
$favorites = db()->table('favorite_addresses')->select('id, label, address, lat, lng')->where('user_id', $uid)->order_by('id','DESC')->get_all();
$fare_base = (float) setting('fare_base', 30.00);
$fare_per_km = (float) setting('fare_per_km', 3.00);
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
.mini-map{height:220px;width:100%;border-radius:12px;overflow:hidden;z-index:0;}
</style>

<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('customer/dashboard') ?>" class="text-sm text-brand-mist">← Back</a>
  <h1 class="text-2xl font-bold mt-2">Place Order</h1>
  <p class="text-sm text-brand-mist">Like Lalamove — tell us where to pick up and drop off.</p>

  <div class="mt-2 glass rounded-xl px-3 py-2 text-xs">Wallet: <span class="font-semibold text-emerald-300">₱<?= number_format($me['wallet_balance'], 2) ?></span></div>

  <div class="mt-4"><?php include __DIR__ . '/../partials/flash.php'; ?></div>

  <form id="orderForm" method="post" action="<?= url('customer/order') ?>" class="mt-4 space-y-4">
    <?php (new Router())->csrf_field(); ?>

    <!-- Pickup -->
    <div class="glass rounded-2xl p-4 space-y-3">
      <div class="flex items-center gap-2">
        <span class="w-3 h-3 rounded-full bg-emerald-400 ring-2 ring-emerald-400/40"></span>
        <span class="font-semibold">Pickup</span>
        <span id="pickBadge" class="ml-auto chip chip-pending">Not set</span>
      </div>
      <div>
        <label class="label">Sender name</label>
        <input class="input" name="sender_name" value="<?= esc($me['full_name']) ?>" required>
      </div>
      <div>
        <label class="label">Pickup address</label>
        <div class="flex gap-2">
          <input class="input flex-1" id="pickup_address" name="pickup_address" placeholder="Type or use current location" required>
          <button type="button" id="useHere" class="btn btn-ghost whitespace-nowrap text-xs">📍 Current</button>
        </div>
      </div>
      <div id="pickupMap" class="mini-map"></div>
      <div class="grid grid-cols-2 gap-2">
        <input class="input !py-1.5 text-[11px] font-mono" id="pickup_lat" name="pickup_lat" placeholder="lat" readonly>
        <input class="input !py-1.5 text-[11px] font-mono" id="pickup_lng" name="pickup_lng" placeholder="lng" readonly>
      </div>
    </div>

    <!-- Dropoff -->
    <div class="glass rounded-2xl p-4 space-y-3">
      <div class="flex items-center gap-2">
        <span class="w-3 h-3 rounded-full bg-red-400 ring-2 ring-red-400/40"></span>
        <span class="font-semibold">Dropoff</span>
        <span id="dropBadge" class="ml-auto chip chip-pending">Not set</span>
      </div>
      <div>
        <label class="label">Recipient name</label>
        <input class="input" name="recipient_name" required>
      </div>
      <div>
        <label class="label">Recipient phone</label>
        <input class="input" name="recipient_phone" placeholder="09XXXXXXXXX" pattern="[0-9+\s]{10,15}" required>
      </div>
      <div>
        <label class="label">Dropoff address</label>
        <input class="input" id="recipient_address" name="recipient_address" placeholder="Type or tap map below to pin" required>
      </div>
      <div id="dropoffMap" class="mini-map"></div>
      <div class="grid grid-cols-2 gap-2">
        <input class="input !py-1.5 text-[11px] font-mono" id="dropoff_lat" name="dropoff_lat" placeholder="lat" readonly>
        <input class="input !py-1.5 text-[11px] font-mono" id="dropoff_lng" name="dropoff_lng" placeholder="lng" readonly>
      </div>
    </div>

    <!-- Product info -->
    <div class="glass rounded-2xl p-4 space-y-3">
      <div class="font-semibold">Package details</div>
      <div>
        <label class="label">Product name</label>
        <input class="input" name="product_name" required placeholder="e.g. Documents, Electronics">
      </div>
      <div>
        <label class="label">Description</label>
        <input class="input" name="product_description" placeholder="Short description">
      </div>
      <div>
        <label class="label">Message / note for driver</label>
        <textarea class="input" name="notes" rows="2" placeholder="Fragile, leave at gate, etc."></textarea>
      </div>
      <div>
        <label class="label">Weight (kg)</label>
        <input class="input" type="number" step="0.01" name="weight" value="1.00" min="0">
      </div>
    </div>

    <!-- Payment method -->
    <div class="glass rounded-2xl p-4 space-y-3">
      <div class="font-semibold">💳 Payment</div>
      <div class="grid grid-cols-3 gap-2 text-center text-xs">
        <label class="glass rounded-xl p-3 cursor-pointer hover:bg-white/15 transition">
          <input type="radio" name="payment_method" value="wallet" class="sr-only peer" checked>
          <div class="text-xl">💼</div>
          <div class="mt-1 font-semibold peer-checked:text-brand-sky">Wallet</div>
          <div class="text-[10px] text-brand-mist">₱<?= number_format($me['wallet_balance'],2) ?></div>
        </label>
        <label class="glass rounded-xl p-3 cursor-pointer hover:bg-white/15 transition">
          <input type="radio" name="payment_method" value="cod" class="sr-only peer">
          <div class="text-xl">💵</div>
          <div class="mt-1 font-semibold peer-checked:text-brand-sky">COD</div>
          <div class="text-[10px] text-brand-mist">Cash on delivery</div>
        </label>
        <label class="glass rounded-xl p-3 cursor-pointer hover:bg-white/15 transition">
          <input type="radio" name="payment_method" value="gcash" class="sr-only peer">
          <div class="text-xl">📱</div>
          <div class="mt-1 font-semibold peer-checked:text-brand-sky">GCash</div>
          <div class="text-[10px] text-brand-mist">Pay before pickup</div>
        </label>
      </div>
    </div>

    <!-- Cost summary -->
    <div class="glass-strong rounded-2xl p-5 shadow-glow">
      <div class="text-xs text-brand-mist">Estimated fare</div>
      <div class="mt-1 flex items-baseline gap-2">
        <div class="text-4xl font-extrabold" id="costTotal">₱ —</div>
        <div class="text-sm text-brand-mist"><span id="costKm">—</span> km</div>
      </div>
      <div class="mt-3 text-xs text-brand-mist space-y-0.5">
        <div class="flex justify-between"><span>Base fare</span><span>₱<?= number_format($fare_base, 2) ?></span></div>
        <div class="flex justify-between"><span>₱<?= number_format($fare_per_km, 2) ?> / km (Tricycle)</span><span id="costPerKm">—</span></div>
        <div class="flex justify-between border-t border-white/10 pt-2 mt-2"><span>Platform fee (10%)</span><span id="costAdmin">—</span></div>
        <div class="flex justify-between"><span>Driver earns (90%)</span><span id="costDriver">—</span></div>
      </div>
      <input type="hidden" name="client_distance_km" id="client_distance_km" value="0">
      <input type="hidden" name="client_amount" id="client_amount" value="0">
    </div>

    <button type="submit" id="submitBtn" class="btn btn-primary w-full" disabled>🚚 Confirm & request pickup</button>
  </form>

  <?php if (!empty($favorites)): ?>
  <div class="mt-6 glass rounded-2xl p-4">
    <div class="font-semibold text-sm mb-2">⭐ Favorite addresses</div>
    <div class="space-y-2">
    <?php foreach ($favorites as $f): ?>
      <button type="button" class="w-full text-left glass rounded-lg p-2 text-xs hover:bg-white/15 transition fav-btn" data-lat="<?= (float)$f['lat'] ?>" data-lng="<?= (float)$f['lng'] ?>" data-addr="<?= esc($f['address']) ?>">
        <span class="font-semibold"><?= esc($f['label']) ?></span> — <span class="text-brand-mist"><?= esc($f['address']) ?></span>
      </button>
    <?php endforeach; ?>
    </div>
    <div class="text-[11px] text-brand-mist mt-2">Tap to fill dropoff. <a href="<?= url('customer/favorites') ?>" class="text-brand-sky">Manage →</a></div>
  </div>
  <?php endif; ?>
</main>

<script>
(function(){
  const greenIcon = L.divIcon({className:'', html:'<div style="background:#22c55e;width:22px;height:22px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.35)"></div>', iconSize:[26,26], iconAnchor:[13,24]});
  const redIcon   = L.divIcon({className:'', html:'<div style="background:#ef4444;width:22px;height:22px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.35)"></div>', iconSize:[26,26], iconAnchor:[13,24]});
  const MNL = [14.5995, 120.9842];

  const pickMap = L.map('pickupMap').setView(MNL, 11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'© OSM'}).addTo(pickMap);
  const dropMap = L.map('dropoffMap').setView(MNL, 11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'© OSM'}).addTo(dropMap);

  let pickMarker = null, dropMarker = null;

  async function reverseGeocode(lat, lng){
    try {
      const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`, {headers:{'Accept-Language':'en'}});
      const d = await r.json();
      return d.display_name || '';
    } catch(e){ return ''; }
  }
  async function forwardGeocode(q){
    try {
      const r = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=1`, {headers:{'Accept-Language':'en'}});
      const d = await r.json();
      return d[0] ? {lat:parseFloat(d[0].lat), lng:parseFloat(d[0].lon), name:d[0].display_name} : null;
    } catch(e){ return null; }
  }

  function haversine(a, b){
    const R=6371, toRad=d=>d*Math.PI/180;
    const dLat=toRad(b[0]-a[0]), dLng=toRad(b[1]-a[1]);
    const x = Math.sin(dLat/2)**2 + Math.cos(toRad(a[0]))*Math.cos(toRad(b[0]))*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.min(1, Math.sqrt(x)));
  }

  function recomputeCost(){
    const pLat=parseFloat(document.getElementById('pickup_lat').value);
    const pLng=parseFloat(document.getElementById('pickup_lng').value);
    const dLat=parseFloat(document.getElementById('dropoff_lat').value);
    const dLng=parseFloat(document.getElementById('dropoff_lng').value);
    if (!pLat||!dLat) { document.getElementById('submitBtn').disabled = true; return; }
    const km = haversine([pLat,pLng],[dLat,dLng]);
    const base = <?= json_encode($fare_base) ?>, perKm = <?= json_encode($fare_per_km) ?>;
    const cost = Math.max(base, Math.round((base + perKm*km) * 100) / 100);
    const admin = Math.round(cost*0.10*100)/100;
    const driver = Math.round((cost-admin)*100)/100;
    document.getElementById('costTotal').textContent = '₱ ' + cost.toFixed(2);
    document.getElementById('costKm').textContent = km.toFixed(2);
    document.getElementById('costPerKm').textContent = '₱' + (perKm*km).toFixed(2);
    document.getElementById('costAdmin').textContent = '₱' + admin.toFixed(2);
    document.getElementById('costDriver').textContent = '₱' + driver.toFixed(2);
    document.getElementById('client_distance_km').value = km.toFixed(3);
    document.getElementById('client_amount').value = cost.toFixed(2);
    document.getElementById('submitBtn').disabled = false;
  }

  function setPickup(lat, lng, addr){
    if (pickMarker) pickMarker.setLatLng([lat,lng]);
    else {
      pickMarker = L.marker([lat,lng], {icon:greenIcon, draggable:true}).addTo(pickMap);
      pickMarker.on('dragend', async e => {
        const p = e.target.getLatLng();
        setPickup(p.lat, p.lng, await reverseGeocode(p.lat, p.lng));
      });
    }
    pickMap.setView([lat,lng], 15);
    document.getElementById('pickup_lat').value = lat.toFixed(7);
    document.getElementById('pickup_lng').value = lng.toFixed(7);
    if (addr) document.getElementById('pickup_address').value = addr;
    document.getElementById('pickBadge').textContent = '✓ Pinned';
    document.getElementById('pickBadge').className = 'chip chip-delivered ml-auto';
    recomputeCost();
  }
  function setDropoff(lat, lng, addr){
    if (dropMarker) dropMarker.setLatLng([lat,lng]);
    else {
      dropMarker = L.marker([lat,lng], {icon:redIcon, draggable:true}).addTo(dropMap);
      dropMarker.on('dragend', async e => {
        const p = e.target.getLatLng();
        setDropoff(p.lat, p.lng, await reverseGeocode(p.lat, p.lng));
      });
    }
    dropMap.setView([lat,lng], 15);
    document.getElementById('dropoff_lat').value = lat.toFixed(7);
    document.getElementById('dropoff_lng').value = lng.toFixed(7);
    if (addr) document.getElementById('recipient_address').value = addr;
    document.getElementById('dropBadge').textContent = '✓ Pinned';
    document.getElementById('dropBadge').className = 'chip chip-delivered ml-auto';
    recomputeCost();
  }

  pickMap.on('click', async e => setPickup(e.latlng.lat, e.latlng.lng, await reverseGeocode(e.latlng.lat, e.latlng.lng)));
  dropMap.on('click', async e => setDropoff(e.latlng.lat, e.latlng.lng, await reverseGeocode(e.latlng.lat, e.latlng.lng)));

  document.getElementById('useHere').onclick = () => {
    if (!navigator.geolocation) return alert('Geolocation not supported');
    navigator.geolocation.getCurrentPosition(async pos => {
      const addr = await reverseGeocode(pos.coords.latitude, pos.coords.longitude);
      setPickup(pos.coords.latitude, pos.coords.longitude, addr);
    }, err => alert('Could not get location: ' + err.message));
  };

  document.getElementById('pickup_address').addEventListener('blur', async e => {
    const q = e.target.value.trim();
    if (!q || document.getElementById('pickup_lat').value) return;
    const g = await forwardGeocode(q);
    if (g) setPickup(g.lat, g.lng, g.name);
  });
  document.getElementById('recipient_address').addEventListener('blur', async e => {
    const q = e.target.value.trim();
    if (!q || document.getElementById('dropoff_lat').value) return;
    const g = await forwardGeocode(q);
    if (g) setDropoff(g.lat, g.lng, g.name);
  });

  document.getElementById('orderForm').addEventListener('submit', e => {
    if (!document.getElementById('pickup_lat').value || !document.getElementById('dropoff_lat').value) {
      e.preventDefault();
      alert('Please pin both pickup and dropoff.');
      return;
    }
    if (!confirm('Go? Confirm this delivery request.')) e.preventDefault();
  });

  // refresh map tiles once laid out
  setTimeout(() => { pickMap.invalidateSize(); dropMap.invalidateSize(); }, 150);

  // Favorite address quick-fill
  document.querySelectorAll('.fav-btn').forEach(b => {
    b.onclick = () => {
      const lat = parseFloat(b.dataset.lat), lng = parseFloat(b.dataset.lng);
      if (lat && lng) setDropoff(lat, lng, b.dataset.addr);
    };
  });
})();
</script>
</body></html>
