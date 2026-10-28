<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$pid = (int)($id ?? 0);
$p = db()->table('parcels')
    ->select('id, tracking_number, status, pickup_address, pickup_lat, pickup_lng, recipient_address, recipient_name, recipient_phone, dropoff_lat, dropoff_lng, distance_km, amount, driver_earnings, admin_fee, notes, product_name, driver_id, customer_id, payment_method, payment_status, is_walkin, created_at')
    ->where('id', $pid)->get();
if (!$p) { set_flash('error','Parcel not found.'); header('Location: ' . url('admin/shipments/assign')); exit; }

$driver = $p['driver_id'] ? db()->table('users')->select('id, full_name, username, phone, email')->where('id',$p['driver_id'])->get() : null;
$customer = $p['customer_id'] ? db()->table('users')->select('id, full_name, username, phone, email')->where('id',$p['customer_id'])->get() : null;
$history = db()->table('parcel_history')->select('status, notes, created_at')->where('parcel_id',$pid)->order_by('created_at','DESC')->get_all();

$page_title = $p['tracking_number']; $active = 'assign';
$chat_parcel_id = $pid;
$chat_peer_name = ($driver['full_name'] ?? '') ?: ($customer['full_name'] ?? 'Parcel chat');
$chat_self_role = 'admin';
// Prefer calling driver; fall back to customer.
if (!empty($driver)) $chat_peer_user_id = (int)$driver['id'];
elseif (!empty($customer)) $chat_peer_user_id = (int)$customer['id'];

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-8 max-w-5xl">
    <a href="<?= url('admin/shipments/assign') ?>" class="text-sm text-brand-mist">← Back to dispatch</a>
    <h1 class="text-2xl md:text-3xl font-bold mt-2"><?= esc($p['tracking_number']) ?></h1>

    <div class="mt-4 grid md:grid-cols-2 gap-4">
      <section class="glass-strong rounded-3xl p-5 shadow-glass">
        <div class="flex items-center justify-between">
          <div class="font-semibold text-lg">📦 <?= esc($p['product_name'] ?: 'Package') ?></div>
          <?= status_chip($p['status']) ?>
        </div>
        <div class="mt-3 text-sm space-y-1">
          <div><span class="text-brand-mist">Pickup:</span> <?= esc($p['pickup_address']) ?></div>
          <div><span class="text-brand-mist">Dropoff:</span> <?= esc($p['recipient_address']) ?></div>
          <div><span class="text-brand-mist">Recipient:</span> <?= esc($p['recipient_name']) ?> · <?= esc(Notifier::display_phone($p['recipient_phone'] ?: '')) ?></div>
          <div><span class="text-brand-mist">Distance:</span> <?= number_format((float)$p['distance_km'],2) ?> km · Fare <?= peso((float)$p['amount']) ?></div>
          <div><span class="text-brand-mist">Payment:</span> <?= esc($p['payment_method'] ?: 'wallet') ?> · <?= esc($p['payment_status']) ?></div>
        </div>
        <?php if ($p['notes']): ?><div class="mt-3 glass rounded-lg p-3 text-xs"><?= esc($p['notes']) ?></div><?php endif; ?>
      </section>

      <section class="glass rounded-3xl p-5 space-y-3">
        <div>
          <div class="font-semibold">🚗 Driver</div>
          <?php if ($driver): ?>
            <div class="text-sm"><?= esc($driver['full_name']) ?> · <?= esc(Notifier::display_phone($driver['phone'] ?: '')) ?></div>
            <div class="mt-2 flex gap-2">
              <button type="button" class="btn btn-primary !py-1.5 !px-3 text-xs" onclick="document.getElementById('msgBox').classList.toggle('hidden')">📨 Send message</button>
              <button type="button" class="btn btn-ghost !py-1.5 !px-3 text-xs" onclick="document.getElementById('chatOpen') && document.getElementById('chatOpen').click()">💬 Open chat</button>
            </div>

            <div id="msgBox" class="hidden mt-3 glass rounded-xl p-3 space-y-2">
              <div class="label !mb-1">Message to driver</div>
              <textarea id="msgBody" class="input !py-2 text-sm" rows="3" maxlength="480" placeholder="Type your message (tracking no. + accept link idagdag ako kung blank)…"></textarea>
              <div class="flex items-center gap-3 text-xs text-brand-mist">
                <label class="flex items-center gap-1"><input type="checkbox" class="ch-ch" value="in_app" checked> In-app</label>
                <label class="flex items-center gap-1"><input type="checkbox" class="ch-ch" value="email" checked> Email</label>
              </div>
              <div class="flex gap-2">
                <button type="button" id="msgPreset" class="btn btn-ghost !py-1.5 !px-3 text-xs">📋 Insert default text</button>
                <button type="button" id="msgSend"   class="btn btn-primary !py-1.5 !px-3 text-xs ml-auto">Send</button>
              </div>
              <div id="msgStatus" class="text-[11px] text-emerald-300"></div>
            </div>
          <?php else: ?>
            <div class="text-sm text-brand-mist">Not yet dispatched.</div>
          <?php endif; ?>
        </div>
        <hr class="border-white/10">
        <div>
          <div class="font-semibold">👤 Customer</div>
          <?php if ($customer): ?>
            <div class="text-sm"><?= esc($customer['full_name']) ?> · <?= esc(Notifier::display_phone($customer['phone'] ?: '')) ?></div>
          <?php else: ?>
            <div class="text-sm text-brand-mist">Walk-in / no account.</div>
          <?php endif; ?>
        </div>
      </section>

      <?php if ($p['pickup_lat'] && $p['dropoff_lat']): ?>
      <section class="md:col-span-2 glass rounded-3xl overflow-hidden">
        <div id="pmap" style="height:360px;"></div>
      </section>
      <?php endif; ?>

      <section class="md:col-span-2 glass rounded-3xl p-5">
        <div class="font-semibold mb-3">🕘 Timeline</div>
        <div class="space-y-2">
          <?php foreach ($history as $h):
            $is_img = is_string($h['notes'] ?? '') && str_starts_with($h['notes'], '[IMG]');
            $img_src = null; $img_caption = '';
            if ($is_img) {
              $raw = substr($h['notes'], 5);
              if (str_contains($raw, '|')) { [$img_src, $img_caption] = explode('|', $raw, 2); }
              else { $img_src = $raw; }
            }
          ?>
            <div class="flex items-start gap-3 text-sm">
              <div class="w-2 h-2 rounded-full <?= $is_img ? 'bg-emerald-400' : 'bg-brand-sky' ?> mt-2"></div>
              <div class="flex-1">
                <div class="flex justify-between">
                  <span class="font-medium"><?= $is_img ? '📷 Transit photo' : esc($h['status']) ?></span>
                  <span class="text-[11px] text-brand-mist"><?= date('M j, g:ia', strtotime($h['created_at'])) ?></span>
                </div>
                <?php if ($is_img && $img_src): ?>
                  <a href="<?= url($img_src) ?>" target="_blank" rel="noopener" class="block mt-2">
                    <img src="<?= url($img_src) ?>" alt="Transit photo" class="rounded-xl max-w-sm object-cover" style="max-height:260px">
                  </a>
                  <?php if (!empty($img_caption)): ?>
                    <div class="text-xs text-brand-mist mt-1 italic">"<?= esc($img_caption) ?>"</div>
                  <?php endif; ?>
                <?php elseif ($h['notes']): ?>
                  <div class="text-xs text-brand-mist"><?= esc($h['notes']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../partials/chat_bubble.php'; ?>

<script>
(function(){
  const send = document.getElementById('msgSend');
  if (!send) return;
  const body = document.getElementById('msgBody');
  const status = document.getElementById('msgStatus');
  const preset = document.getElementById('msgPreset');
  preset.onclick = () => {
    body.value = "Reminder: Parcel <?= esc($p['tracking_number']) ?> is waiting. Please accept or let me know the ETA. Thanks!";
    body.focus();
  };
  send.onclick = async () => {
    const channels = Array.from(document.querySelectorAll('.ch-ch')).filter(c=>c.checked).map(c=>c.value);
    if (channels.length === 0) { status.textContent = 'Pick at least one channel.'; status.style.color = '#fecaca'; return; }
    send.disabled = true;
    status.textContent = 'Sending…'; status.style.color = '#bfdbfe';
    const fd = new URLSearchParams();
    fd.set('message', body.value);
    channels.forEach(c => fd.append('channels[]', c));
    fd.set('csrf_token', <?= json_encode(csrf_token()) ?>);
    const r = await fetch('<?= url('admin/notify-driver/'.$pid) ?>', {method:'POST', body:fd, credentials:'same-origin'}).then(r=>r.json()).catch(()=>({ok:false}));
    send.disabled = false;
    if (r.ok) {
      const sent = Object.entries(r.sent).filter(([k,v])=>v).map(([k])=>k).join(', ');
      status.textContent = '✓ Sent to ' + r.driver + ' via ' + (sent || 'nothing');
      status.style.color = '#bbf7d0';
      if (window.DropIT) DropIT.toast('Message sent', 'info');
      body.value = '';
    } else {
      status.textContent = 'Failed: ' + (r.error || 'unknown');
      status.style.color = '#fecaca';
    }
  };
})();
</script>

<?php if ($p['pickup_lat'] && $p['dropoff_lat']): ?>
<script>
(function(){
  const PL=[<?= (float)$p['pickup_lat'] ?>,<?= (float)$p['pickup_lng'] ?>], DR=[<?= (float)$p['dropoff_lat'] ?>,<?= (float)$p['dropoff_lng'] ?>];
  const map=L.map('pmap').setView([(PL[0]+DR[0])/2,(PL[1]+DR[1])/2],13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
  const pin=c=>L.divIcon({className:'',html:`<div style="background:${c};width:22px;height:22px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff"></div>`,iconSize:[26,26],iconAnchor:[13,24]});
  L.marker(PL,{icon:pin('#22c55e')}).addTo(map).bindPopup('Pickup');
  L.marker(DR,{icon:pin('#ef4444')}).addTo(map).bindPopup('Dropoff');
  L.polyline([PL,DR],{color:'#60a5fa',dashArray:'6 8'}).addTo(map);
  map.fitBounds(L.latLngBounds(PL,DR).pad(0.3));
})();
</script>
<?php endif; ?>
</body></html>
