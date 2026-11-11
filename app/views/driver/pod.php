<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$pid = (int)($id ?? 0);
$uid = (int)$_SESSION['user_id'];
$p = db()->table('parcels')
    ->select('id, tracking_number, status, driver_id, recipient_name, recipient_address, amount, payment_method, payment_status, pod_photo, pod_latitude, pod_longitude, pod_accuracy, pod_captured_at')
    ->where('id',$pid)->get();
if (!$p || (int)$p['driver_id'] !== $uid) { set_flash('error','Not your parcel.'); header('Location: ' . url('driver/dashboard')); exit; }

$page_title = 'Proof of Delivery';
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('driver/parcel/'.$pid) ?>" class="text-sm text-brand-mist">← Back to parcel</a>
  <h1 class="text-2xl font-bold mt-2">Proof of Delivery</h1>
  <p class="text-sm text-brand-mist">Capture a delivery photo with your current device latitude and longitude, then add the receiver signature.</p>

  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <div class="mt-4 glass rounded-2xl p-4">
    <div class="text-sm"><span class="text-brand-mist">Parcel:</span> <span class="font-mono"><?= esc($p['tracking_number']) ?></span></div>
    <div class="text-sm"><span class="text-brand-mist">To:</span> <?= esc($p['recipient_name']) ?></div>
    <?php if ($p['payment_method'] === 'cod' && $p['payment_status'] !== 'settled'): ?>
      <div class="mt-2 glass rounded-lg p-3 text-amber-300 text-sm border border-amber-400/30" style="background:rgba(245,158,11,0.1);">
        💵 Collect <b><?= peso((float)$p['amount']) ?></b> cash from recipient before marking delivered.
      </div>
    <?php endif; ?>
  </div>

  <?php if ($p['status'] === 'Delivered' && $p['pod_photo']): ?>
    <section class="mt-4 glass rounded-2xl p-4 space-y-2">
      <h2 class="font-semibold">Saved delivery proof</h2>
      <img src="<?= esc(url($p['pod_photo'])) ?>" alt="Saved delivery proof" class="w-full rounded-xl">
      <?php if (isset($p['pod_latitude'], $p['pod_longitude'])): ?>
        <div data-pod-location data-geocoder-url="https://photon.komoot.io/reverse" data-latitude="<?= esc($p['pod_latitude']) ?>" data-longitude="<?= esc($p['pod_longitude']) ?>" data-accuracy="<?= esc($p['pod_accuracy']) ?>" data-captured-at="<?= esc($p['pod_captured_at']) ?> UTC">
          <p data-location-address class="text-sm" aria-live="polite">Location: Looking up address…</p>
          <p data-location-coordinates class="text-sm font-mono overflow-x-auto whitespace-pre" aria-label="Photo GPS coordinates">Latitude: <?= esc($p['pod_latitude']) ?>   Longitude: <?= esc($p['pod_longitude']) ?></p>
          <p data-location-meta class="text-xs text-brand-mist"></p>
          <p class="text-xs text-brand-mist">Address: <a href="https://photon.komoot.io/" target="_blank" rel="noopener noreferrer">Photon</a> · © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a></p>
        </div>
      <?php else: ?><p class="text-xs text-brand-mist">No GPS data was recorded for this historical photo.</p><?php endif; ?>
    </section>
  <?php elseif (!in_array($p['status'], ['Cancelled','Return Pending','Returned'], true)): ?>
  <form id="podForm" method="post" action="<?= url('driver/pod/'.$pid) ?>" enctype="multipart/form-data" class="mt-4 space-y-4">
    <?php (new Router())->csrf_field(); ?>
    <input type="hidden" name="signature" id="sigData">
    <?php foreach (['latitude','longitude','accuracy','captured_at','location_at'] as $field): ?>
      <input type="hidden" name="<?= $field ?>" id="pod_<?= $field ?>">
    <?php endforeach; ?>

    <div class="glass rounded-2xl p-4 space-y-2">
      <div class="label">Receiver name</div>
      <input class="input" name="receiver_name" value="<?= esc($p['recipient_name']) ?>" required>
    </div>

    <div class="glass rounded-2xl p-4 space-y-2">
      <div class="label">📷 Delivery proof camera</div>
      <p class="text-xs text-brand-mist">Allow camera and location access. Frame the parcel at the destination.</p>
      <video id="podVideo" autoplay muted playsinline class="w-full rounded-xl" hidden></video>
      <img id="podPreview" alt="Captured delivery proof" class="w-full rounded-xl" hidden>
      <canvas id="podCanvas" hidden></canvas>
      <div class="flex flex-wrap gap-2">
        <button type="button" id="podStart" class="btn btn-primary">Open camera</button>
        <button type="button" id="podCapture" class="btn btn-success" disabled>Take photo &amp; record GPS</button>
        <button type="button" id="podRetake" class="btn btn-ghost" hidden>Retake photo</button>
      </div>
      <div id="podLocationDisplay" data-pod-location data-geocoder-url="https://photon.komoot.io/reverse" hidden>
        <p data-location-address class="text-sm" aria-live="polite"></p>
        <p id="podCoordinates" data-location-coordinates class="text-sm font-mono overflow-x-auto whitespace-pre" aria-label="Photo GPS coordinates" aria-live="polite"></p>
        <p data-location-meta class="text-xs text-brand-mist"></p>
        <p class="text-xs text-brand-mist">Address: <a href="https://photon.komoot.io/" target="_blank" rel="noopener noreferrer">Photon</a> · © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a></p>
      </div>
      <p id="podCaptureStatus" role="status" aria-live="polite" class="text-xs text-brand-mist">A camera photo and fresh device location are required before delivery can be completed.</p>
      <p class="text-xs text-brand-mist">Coordinates come from your device. The reported accuracy is shown with the photo.</p>
    </div>

    <div class="glass rounded-2xl p-4 space-y-2">
      <div class="label">✍️ Receiver signature</div>
      <canvas id="sig" class="w-full" height="160" style="background:#fff;border-radius:12px;touch-action:none;"></canvas>
      <div class="flex gap-2 text-xs">
        <button type="button" id="sigClear" class="btn btn-ghost !py-1 !px-3">Clear</button>
        <span class="text-brand-mist self-center">Sign with finger/mouse</span>
      </div>
    </div>

    <?php if ($p['payment_method'] === 'cod' && $p['payment_status'] !== 'settled'): ?>
    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" name="cod_collected" value="1" required>
      I have collected <?= peso((float)$p['amount']) ?> COD.
    </label>
    <?php endif; ?>

    <button class="btn btn-success w-full" id="submitBtn" disabled>✓ Mark Delivered</button>
  </form>
  <?php endif; ?>
</main>

<style>#podForm [hidden] {display:none!important;} #podForm button:disabled {opacity:.45;cursor:not-allowed;transform:none;box-shadow:none;}</style>
<script src="<?= url('resources/js/pod-location.js?v=20261104-address') ?>" defer></script>
<script src="<?= url('resources/js/pod-capture.js?v=20261104-address') ?>" defer></script>
</body></html>
