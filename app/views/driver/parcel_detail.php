<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$uid = (int)$_SESSION['user_id'];
$pid = (int)($id ?? 0);

$p = db()->table('parcels')
    ->select('id, tracking_number, status, pickup_address, pickup_lat, pickup_lng, recipient_address, recipient_name, recipient_phone, dropoff_lat, dropoff_lng, distance_km, amount, driver_earnings, notes, product_name, product_description, driver_id, customer_id, payment_method, payment_status')
    ->where('id', $pid)->get();

if (!$p || (int)$p['driver_id'] !== $uid) {
    set_flash('error','Parcel not found or not yours.');
    header('Location: ' . url('driver/dashboard')); exit;
}

$customer = $p['customer_id'] ? db()->table('users')->select('id, full_name, phone')->where('id',$p['customer_id'])->get() : null;

$next_options = match ($p['status']) {
    'Accepted'    => ['Picked Up','Cancelled'],
    'Picked Up'   => ['In Transit','Cancelled'],
    'In Transit'  => ['Arrived','Cancelled'],
    'Arrived'     => ['Delivered','Cancelled'],
    default       => ['Picked Up','In Transit','Arrived','Delivered','Cancelled'],
};

// Compute ETA if we have driver's last known location.
$drv_loc = db()->table('driver_locations')->select('lat, lng, updated_at')->where('driver_id', $uid)->get();
$eta_min = $drv_loc && $p['dropoff_lat']
    ? compute_eta_minutes((float)$drv_loc['lat'], (float)$drv_loc['lng'], (float)$p['dropoff_lat'], (float)$p['dropoff_lng'])
    : null;

$page_title = $p['tracking_number'];
$chat_parcel_id = $pid;
$chat_peer_name = $customer['full_name'] ?? 'Customer';
$chat_self_role = 'driver';
$chat_closed = in_array($p['status'], ['Delivered','Cancelled']);
$chat_closed_reason = $p['status'] === 'Delivered' ? 'Delivered — chat closed' : 'Cancelled — chat closed';

if ($customer) {
    $chat_peer_user_id = (int)$customer['id'];
    $call_target_user_id = (int)$customer['id'];
    $call_peer_name = $customer['full_name'] ?? 'Customer';
}

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('driver/dashboard') ?>" class="text-sm text-brand-mist">← Back to tasks</a>

  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <div class="mt-3 glass-strong rounded-3xl p-5 shadow-glass">
    <div class="flex items-center justify-between">
      <div class="font-mono text-sm font-semibold"><?= esc($p['tracking_number']) ?></div>
      <?= status_chip($p['status']) ?>
    </div>
    <div class="mt-2 text-xl font-semibold">📦 <?= esc($p['product_name'] ?: 'Package') ?></div>
    <?php if ($p['product_description']): ?>
      <div class="text-xs text-brand-mist"><?= esc($p['product_description']) ?></div>
    <?php endif; ?>
    <div class="mt-3 flex items-center justify-between text-sm">
      <div class="text-brand-mist"><?= number_format((float)$p['distance_km'],2) ?> km · <?= esc($p['payment_method'] ?: 'wallet') ?></div>
      <div class="text-emerald-300 font-semibold">+ ₱<?= number_format((float)$p['driver_earnings'],2) ?></div>
    </div>
    <?php if ($eta_min !== null && in_array($p['status'], ['Accepted','Picked Up','In Transit'])): ?>
      <div class="mt-2 text-xs glass-light rounded-lg p-2 text-brand-deep">⏱️ ETA to dropoff: ~<?= $eta_min ?> min</div>
    <?php endif; ?>
  </div>

  <div class="mt-4 glass rounded-2xl overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 text-sm">
      <span class="font-semibold">Route (tap map to fullscreen)</span>
      <button type="button" id="toggleBig" class="text-brand-sky text-xs">⤢ Expand</button>
    </div>
    <div id="driverMap" class="h-72 w-full z-0"></div>
  </div>

  <div class="mt-4 glass rounded-2xl p-4 space-y-2 text-sm">
    <div><span class="text-brand-mist">Pickup:</span><br><?= esc($p['pickup_address']) ?></div>
    <div><span class="text-brand-mist">Dropoff:</span><br><?= esc($p['recipient_address']) ?></div>
    <div><span class="text-brand-mist">Recipient:</span> <?= esc($p['recipient_name']) ?> · <?= esc(Notifier::display_phone($p['recipient_phone'] ?: '')) ?></div>
    <div class="flex gap-2">
      <a href="tel:<?= esc($p['recipient_phone']) ?>" class="btn btn-ghost !py-1.5 !px-3 text-xs flex-1 text-center" title="Recipient has no Drop IT account — phone dial only">☎ Call recipient</a>
      <?php if ($customer): ?>
        <button type="button" onclick="window.dropItVoiceCall && window.dropItVoiceCall.start()" class="btn btn-success !py-1.5 !px-3 text-xs flex-1 text-center">🎙️ Voice call sender</button>
      <?php endif; ?>
    </div>
    <?php if ($p['notes']): ?>
      <div class="glass rounded-lg p-2 text-xs"><span class="text-brand-mist">Note:</span> <?= esc($p['notes']) ?></div>
    <?php endif; ?>
  </div>

  <?php if ($p['payment_method'] === 'cod' && $p['payment_status'] !== 'settled'): ?>
    <div class="mt-4 glass-light rounded-xl p-3 text-brand-deep text-sm">
      💵 Collect <b>₱<?= number_format((float)$p['amount'],2) ?></b> cash on delivery.
    </div>
  <?php endif; ?>

  <?php if (in_array($p['status'], ['Accepted','Picked Up','In Transit','Arrived'])): ?>
    <!-- Transit photo — update customer with a picture (different from POD). -->
    <form method="post" action="<?= url('driver/transit-photo/'.$p['id']) ?>" enctype="multipart/form-data" class="mt-4 glass rounded-2xl p-4 space-y-3">
      <?php (new Router())->csrf_field(); ?>
      <div class="flex items-center gap-2">
        <div class="text-2xl">📷</div>
        <div class="flex-1">
          <div class="font-semibold text-sm">Send a transit photo</div>
          <div class="text-[11px] text-brand-mist">Picked up sa pickup? Stuck in traffic? Send the customer a photo.</div>
        </div>
      </div>
      <label class="block">
        <input type="file" name="photo" accept="image/*" capture="environment" required
               class="block w-full text-xs text-brand-mist file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-white/15 file:text-white file:font-semibold hover:file:bg-white/25 cursor-pointer">
      </label>
      <input class="input !py-2 text-sm" name="caption" maxlength="160" placeholder="Caption (optional) — e.g., 'Nasa EDSA, konting traffic lang'">
      <button class="btn btn-primary w-full !py-2 text-sm">Post photo to customer →</button>
    </form>
  <?php endif; ?>

  <?php if (in_array($p['status'], ['Arrived','In Transit'])): ?>
    <a href="<?= url('driver/pod/'.$p['id']) ?>" class="btn btn-success w-full mt-4">📷 Capture Proof of Delivery</a>
  <?php endif; ?>

  <form method="post" action="<?= url('driver/update/'.$p['id']) ?>" enctype="multipart/form-data" class="mt-4 glass rounded-2xl p-4" id="statusForm">
    <?php (new Router())->csrf_field(); ?>
    <div class="label">Update status</div>
    <div class="flex gap-2">
      <select name="status" id="statusSelect" class="input flex-1">
        <?php foreach ($next_options as $opt): ?>
          <option value="<?= $opt ?>"><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary">Update</button>
    </div>

    <!-- Pickup photo — shown only when transitioning to Picked Up, and required in that case. -->
    <div id="pickupPhotoBox" class="hidden mt-3 glass-light rounded-xl p-3 text-brand-deep">
      <div class="text-xs font-semibold">📷 Pickup photo (required)</div>
      <div class="text-[11px] text-brand-deep/80 mt-0.5">Take a photo of the parcel pala bago mag Picked Up — proof na nakuha mo na.</div>
      <input type="file" name="pickup_photo" id="pickupPhotoInput" accept="image/*" capture="environment"
             class="block w-full mt-2 text-xs text-brand-deep file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-brand-blue file:text-white file:font-semibold cursor-pointer">
    </div>
    <div class="text-[11px] text-brand-mist mt-2">Tip: prefer the Proof of Delivery button above when marking Delivered.</div>
  </form>

  <button id="sosBtn" class="btn btn-danger w-full mt-3">🚨 SOS (alert admin)</button>
</main>

<?php include __DIR__ . '/../partials/chat_bubble.php'; ?>
<?php if ($customer) include __DIR__ . '/../partials/voice_call.php'; ?>

<script>
(function(){
  const PICK = [<?= (float)$p['pickup_lat'] ?>, <?= (float)$p['pickup_lng'] ?>];
  const DROP = [<?= (float)$p['dropoff_lat'] ?>, <?= (float)$p['dropoff_lng'] ?>];
  const map = L.map('driverMap', { zoomControl: true }).setView([(PICK[0]+DROP[0])/2, (PICK[1]+DROP[1])/2], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'© OSM'}).addTo(map);
  const pin=c=>L.divIcon({className:'',html:`<div style="background:${c};width:22px;height:22px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.4)"></div>`,iconSize:[26,26],iconAnchor:[13,24]});
  L.marker(PICK,{icon:pin('#22c55e')}).addTo(map).bindPopup('Pickup');
  L.marker(DROP,{icon:pin('#ef4444')}).addTo(map).bindPopup('Dropoff');
  L.polyline([PICK,DROP],{color:'#60a5fa',weight:3,opacity:.6,dashArray:'6 8'}).addTo(map);
  map.fitBounds(L.latLngBounds(PICK,DROP).pad(0.35));

  let meMarker=null;
  const meIcon=L.divIcon({className:'',html:'<div style="background:#3b82f6;width:28px;height:28px;border-radius:50%;border:4px solid #fff;box-shadow:0 0 0 3px rgba(59,130,246,.35),0 6px 16px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px">🚚</div>',iconSize:[32,32],iconAnchor:[16,16]});
  if ('geolocation' in navigator) {
    navigator.geolocation.watchPosition(pos=>{
      const ll=[pos.coords.latitude, pos.coords.longitude];
      if (!meMarker) meMarker=L.marker(ll,{icon:meIcon,zIndexOffset:1000}).addTo(map).bindPopup('You');
      else meMarker.setLatLng(ll);
      window._lastPos = pos;
    }, e=>console.warn(e.message), {enableHighAccuracy:true, maximumAge:5000, timeout:20000});
  }

  document.getElementById('toggleBig').onclick = () => {
    const d = document.getElementById('driverMap');
    d.classList.toggle('h-72'); d.classList.toggle('h-[70vh]');
    setTimeout(()=>map.invalidateSize(), 200);
  };

  document.getElementById('sosBtn').onclick = async () => {
    if (!await DropIT.confirm2('Alert admin with SOS?')) return;
    const pos = window._lastPos;
    const d = await DropIT.post('<?= url('api/driver/sos') ?>', {
      lat: pos?pos.coords.latitude:'', lng: pos?pos.coords.longitude:'',
      parcel_id: <?= (int)$pid ?>,
    });
    if (d.ok){ DropIT.sound.error(); DropIT.toast('SOS sent', 'info'); }
  };

  // Show/require pickup photo only when user selects "Picked Up".
  const sel = document.getElementById('statusSelect');
  const box = document.getElementById('pickupPhotoBox');
  const inp = document.getElementById('pickupPhotoInput');
  function syncPickupBox(){
    const isPick = sel && sel.value === 'Picked Up';
    if (!box) return;
    box.classList.toggle('hidden', !isPick);
    if (inp) inp.required = isPick;
  }
  if (sel) { sel.addEventListener('change', syncPickupBox); syncPickupBox(); }

  DropIT.startNotifPoll();
})();
</script>
</body></html>
