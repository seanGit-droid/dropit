<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$pid = (int)($id ?? 0);
$uid = (int)$_SESSION['user_id'];
$p = db()->table('parcels')
    ->select('id, tracking_number, status, driver_id, recipient_name, recipient_address, amount, payment_method, payment_status, pod_photo')
    ->where('id',$pid)->get();
if (!$p || (int)$p['driver_id'] !== $uid) { set_flash('error','Not your parcel.'); header('Location: ' . url('driver/dashboard')); exit; }

$page_title = 'Proof of Delivery';
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('driver/parcel/'.$pid) ?>" class="text-sm text-brand-mist">← Back to parcel</a>
  <h1 class="text-2xl font-bold mt-2">Proof of Delivery</h1>
  <p class="text-sm text-brand-mist">Capture photo + signature. This marks the parcel as Delivered.</p>

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

  <form id="podForm" method="post" action="<?= url('driver/pod/'.$pid) ?>" enctype="multipart/form-data" class="mt-4 space-y-4">
    <?php (new Router())->csrf_field(); ?>
    <input type="hidden" name="signature" id="sigData">

    <div class="glass rounded-2xl p-4 space-y-2">
      <div class="label">Receiver name</div>
      <input class="input" name="receiver_name" value="<?= esc($p['recipient_name']) ?>" required>
    </div>

    <div class="glass rounded-2xl p-4 space-y-2">
      <div class="label">📷 Photo of parcel / receipt</div>
      <input class="input" type="file" name="photo" accept="image/*" capture="environment" required>
      <div class="text-[11px] text-brand-mist">Tip: snap the package at the recipient's door.</div>
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

    <button class="btn btn-success w-full" id="submitBtn">✓ Mark Delivered</button>
  </form>
</main>

<script>
(function(){
  const c = document.getElementById('sig');
  const ctx = c.getContext('2d');
  function resize(){ const r = c.getBoundingClientRect(); c.width = r.width; }
  resize(); window.addEventListener('resize', resize);
  ctx.strokeStyle = '#0b1e3f'; ctx.lineWidth = 2; ctx.lineCap='round'; ctx.lineJoin='round';
  let drawing = false, dirty = false;
  function pt(e){ const r=c.getBoundingClientRect(); const t = e.touches?e.touches[0]:e; return [t.clientX-r.left, t.clientY-r.top]; }
  function start(e){ drawing = true; dirty = true; const [x,y]=pt(e); ctx.beginPath(); ctx.moveTo(x,y); e.preventDefault(); }
  function move(e){ if(!drawing) return; const [x,y]=pt(e); ctx.lineTo(x,y); ctx.stroke(); e.preventDefault(); }
  function end(){ drawing = false; }
  c.addEventListener('mousedown', start); c.addEventListener('mousemove', move); c.addEventListener('mouseup', end); c.addEventListener('mouseleave', end);
  c.addEventListener('touchstart', start); c.addEventListener('touchmove', move); c.addEventListener('touchend', end);
  document.getElementById('sigClear').onclick = () => { ctx.clearRect(0,0,c.width,c.height); dirty=false; };
  document.getElementById('podForm').addEventListener('submit', (e) => {
    if (!dirty) { e.preventDefault(); DropIT.toast('Please sign first.', 'error'); return; }
    document.getElementById('sigData').value = c.toDataURL('image/png');
  });
})();
</script>
</body></html>
