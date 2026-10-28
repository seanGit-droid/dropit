<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$pid = (int)($id ?? 0);
$uid = (int)$_SESSION['user_id'];
$p = db()->table('parcels')->select('id, tracking_number, status, customer_id, driver_id')->where('id',$pid)->get();
if (!$p || (int)$p['customer_id'] !== $uid) { set_flash('error','Not found.'); header('Location: ' . url('customer/dashboard')); exit; }
if ($p['status'] !== 'Delivered') { set_flash('info','You can rate after delivery.'); header('Location: ' . url('customer/dashboard')); exit; }

$existing = db()->table('ratings')->select('id, stars, review')->where('parcel_id',$pid)->get();
$driver = $p['driver_id'] ? db()->table('users')->select('full_name, username, rating_avg, rating_count')->where('id',$p['driver_id'])->get() : null;

$page_title = 'Rate your driver';
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<main class="mx-auto max-w-md px-4 pt-8 pb-28">
  <a href="<?= url('customer/dashboard') ?>" class="text-sm text-brand-mist">← Back</a>
  <h1 class="text-2xl font-bold mt-2">How was your delivery?</h1>
  <p class="text-sm text-brand-mist">Parcel <span class="font-mono"><?= esc($p['tracking_number']) ?></span></p>

  <div class="mt-4 glass rounded-2xl p-4 flex items-center gap-3">
    <div class="w-12 h-12 rounded-full bg-white/15 flex items-center justify-center text-xl">🚚</div>
    <div>
      <div class="font-semibold"><?= esc($driver['full_name'] ?? '—') ?></div>
      <?php if ($driver && $driver['rating_count'] > 0): ?>
        <div class="text-xs text-brand-mist">★<?= number_format((float)$driver['rating_avg'],1) ?> (<?= $driver['rating_count'] ?> reviews)</div>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" action="<?= url('customer/rate/'.$pid) ?>" class="mt-5 glass-strong rounded-3xl p-5 space-y-4">
    <?php (new Router())->csrf_field(); ?>
    <input type="hidden" name="stars" id="stars" value="<?= (int)($existing['stars'] ?? 5) ?>">
    <div class="text-center">
      <div id="starRow" class="text-4xl tracking-widest">
        <?php for ($i=1; $i<=5; $i++): ?>
          <span class="star cursor-pointer" data-n="<?= $i ?>" style="user-select:none">☆</span>
        <?php endfor; ?>
      </div>
      <div class="text-xs text-brand-mist mt-1"><span id="starLabel">Tap to rate</span></div>
    </div>
    <div>
      <label class="label">Review (optional)</label>
      <textarea class="input" name="review" rows="3" maxlength="400" placeholder="Fast, polite, careful…"><?= esc($existing['review'] ?? '') ?></textarea>
    </div>
    <button class="btn btn-primary w-full">Submit rating</button>
  </form>
</main>
<script>
(function(){
  const stars = document.querySelectorAll('.star');
  const input = document.getElementById('stars');
  const label = document.getElementById('starLabel');
  const texts = ['Tap to rate','Bad 😞','Meh 😐','Okay 🙂','Great 😊','Perfect 🤩'];
  function paint(n){
    stars.forEach((s,i) => s.textContent = i < n ? '★' : '☆');
    input.value = n; label.textContent = texts[n];
  }
  stars.forEach(s => s.onclick = () => paint(parseInt(s.dataset.n)));
  paint(parseInt(input.value) || 5);
})();
</script>
</body></html>
