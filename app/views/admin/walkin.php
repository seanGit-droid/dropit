<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Walk-in Parcel';
$active = 'register';

$drivers = db()->table('users')
    ->select('id, username, full_name, availability')
    ->where('role','driver')->where('is_active',1)
    ->order_by('full_name','ASC')->get_all();

$fare_base = (float) setting('fare_base', 30.00);
$fare_per_km = (float) setting('fare_per_km', 3.00);

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>.mini-map{height:260px;width:100%;border-radius:12px;overflow:hidden;z-index:0;}</style>

<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-8">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold">Walk-in Parcel</h1>
        <p class="text-sm text-brand-mist">Customer dropped off in person. Create and dispatch right away.</p>
      </div>
      <a href="<?= url('admin/shipments/assign') ?>" class="btn btn-ghost">← Back to dispatch</a>
    </div>

    <div class="mt-4"><?php include __DIR__ . '/../partials/flash.php'; ?></div>

    <form method="post" action="<?= url('admin/shipments/walkin') ?>" class="mt-4 grid md:grid-cols-2 gap-4">
      <?php (new Router())->csrf_field(); ?>
      <input type="hidden" name="pickup_lat" id="pickup_lat"><input type="hidden" name="pickup_lng" id="pickup_lng">
      <input type="hidden" name="dropoff_lat" id="dropoff_lat"><input type="hidden" name="dropoff_lng" id="dropoff_lng">

      <section class="glass-strong rounded-3xl p-5 shadow-glass space-y-3">
        <h2 class="font-semibold flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-emerald-400"></span> Pickup</h2>
        <div><label class="label">Sender name</label><input class="input" name="sender_name" required></div>
        <div><label class="label">Sender phone</label><input class="input" name="sender_phone" placeholder="09XXXXXXXXX"></div>
        <div>
          <label class="label">Pickup address</label>
          <input class="input" id="pickup_address" name="pickup_address" placeholder="Tap pickup map to pin" required>
        </div>
        <div id="pickupMap" class="mini-map"></div>
      </section>

      <section class="glass-strong rounded-3xl p-5 shadow-glass space-y-3">
        <h2 class="font-semibold flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-red-400"></span> Dropoff</h2>
        <div><label class="label">Recipient name</label><input class="input" name="recipient_name" required></div>
        <div><label class="label">Recipient phone</label><input class="input" name="recipient_phone" placeholder="09XXXXXXXXX" required></div>
        <div>
          <label class="label">Dropoff address</label>
          <input class="input" id="recipient_address" name="recipient_address" placeholder="Tap dropoff map to pin" required>
        </div>
        <div id="dropoffMap" class="mini-map"></div>
      </section>

      <section class="md:col-span-2 glass rounded-3xl p-5 space-y-3">
        <h2 class="font-semibold">Package & payment</h2>
        <div class="grid md:grid-cols-3 gap-3">
          <div><label class="label">Product name</label><input class="input" name="product_name" required></div>
          <div><label class="label">Weight (kg)</label><input class="input" type="number" step="0.01" name="weight" value="1.00"></div>
          <div>
            <label class="label">Payment method</label>
            <select class="input" name="payment_method" required>
              <option value="cod">COD (cash on delivery)</option>
              <option value="gcash">GCash (paid at counter)</option>
              <option value="paid_cash">Paid cash at counter</option>
            </select>
          </div>
          <div class="md:col-span-3"><label class="label">Note for driver</label><textarea class="input" name="notes" rows="2"></textarea></div>
        </div>
        <div class="glass-light rounded-2xl p-4 text-brand-deep">
          <div class="text-xs">Estimated fare</div>
          <div class="text-3xl font-bold" id="costTotal">₱ —</div>
          <div class="text-xs">Distance <span id="costKm">—</span> km · 10% platform fee · 90% driver</div>
        </div>
      </section>

      <section class="md:col-span-2 glass rounded-3xl p-5 space-y-3">
        <h2 class="font-semibold">Dispatch driver (optional — can assign later)</h2>
        <select class="input" name="driver_id">
          <option value="">— Assign later —</option>
          <?php foreach ($drivers as $d): ?>
            <option value="<?= $d['id'] ?>"><?= esc($d['full_name'] ?: $d['username']) ?> <?= $d['availability']==='online' ? '🟢' : '⚪' ?></option>
          <?php endforeach; ?>
        </select>
        <div class="flex gap-2 pt-2">
          <button class="btn btn-primary" id="submitBtn" disabled>✓ Create & dispatch</button>
          <a href="<?= url('admin/shipments/assign') ?>" class="btn btn-ghost">Cancel</a>
        </div>
      </section>
    </form>
  </main>
</div>

<script>
(function(){
  const MNL=[14.5995, 120.9842];
  const pin=c=>L.divIcon({className:'', html:`<div style="background:${c};width:22px;height:22px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.35)"></div>`, iconSize:[26,26], iconAnchor:[13,24]});
  const pickMap=L.map('pickupMap').setView(MNL,11);
  const dropMap=L.map('dropoffMap').setView(MNL,11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(pickMap);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(dropMap);
  let pm=null,dm=null;

  async function rgc(lat,lng){try{const r=await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18`);return (await r.json()).display_name||'';}catch(e){return '';}}

  function haversine(a,b){const R=6371,r=d=>d*Math.PI/180;const dLat=r(b[0]-a[0]),dLng=r(b[1]-a[1]);const x=Math.sin(dLat/2)**2+Math.cos(r(a[0]))*Math.cos(r(b[0]))*Math.sin(dLng/2)**2;return 2*R*Math.asin(Math.min(1,Math.sqrt(x)));}
  function recompute(){
    const pL=parseFloat(pickup_lat.value),pG=parseFloat(pickup_lng.value);
    const dL=parseFloat(dropoff_lat.value),dG=parseFloat(dropoff_lng.value);
    if(!pL||!dL){submitBtn.disabled=true;return;}
    const km=haversine([pL,pG],[dL,dG]);
    const base=<?= json_encode($fare_base) ?>, perKm=<?= json_encode($fare_per_km) ?>;
    const cost=Math.max(base, Math.round((base+perKm*km)*100)/100);
    costTotal.textContent='₱ '+cost.toFixed(2);
    costKm.textContent=km.toFixed(2);
    submitBtn.disabled=false;
  }
  function setP(lat,lng,addr){if(pm)pm.setLatLng([lat,lng]);else pm=L.marker([lat,lng],{icon:pin('#22c55e'),draggable:true}).addTo(pickMap).on('dragend',async e=>{const p=e.target.getLatLng();setP(p.lat,p.lng,await rgc(p.lat,p.lng));});pickMap.setView([lat,lng],15);pickup_lat.value=lat.toFixed(7);pickup_lng.value=lng.toFixed(7);if(addr)pickup_address.value=addr;recompute();}
  function setD(lat,lng,addr){if(dm)dm.setLatLng([lat,lng]);else dm=L.marker([lat,lng],{icon:pin('#ef4444'),draggable:true}).addTo(dropMap).on('dragend',async e=>{const p=e.target.getLatLng();setD(p.lat,p.lng,await rgc(p.lat,p.lng));});dropMap.setView([lat,lng],15);dropoff_lat.value=lat.toFixed(7);dropoff_lng.value=lng.toFixed(7);if(addr)recipient_address.value=addr;recompute();}
  pickMap.on('click',async e=>setP(e.latlng.lat,e.latlng.lng,await rgc(e.latlng.lat,e.latlng.lng)));
  dropMap.on('click',async e=>setD(e.latlng.lat,e.latlng.lng,await rgc(e.latlng.lat,e.latlng.lng)));
  setTimeout(()=>{pickMap.invalidateSize();dropMap.invalidateSize();},150);
})();
</script>
</body></html>
