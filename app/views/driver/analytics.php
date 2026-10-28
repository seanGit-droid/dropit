<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'My Stats'; $active = 'analytics';
$uid = (int)$_SESSION['user_id'];
$days = 30;
$since = date('Y-m-d', strtotime("-{$days} days"));

$me = db()->table('users')->select('full_name, wallet_balance, rating_avg, rating_count')->where('id',$uid)->get();

$daily = db()->raw("
    SELECT DATE(updated_at) d, COUNT(*) c, SUM(driver_earnings) earn, SUM(distance_km) km
    FROM parcels WHERE driver_id=? AND status='Delivered' AND DATE(updated_at) >= ?
    GROUP BY DATE(updated_at) ORDER BY d ASC
", [$uid, $since])->fetchAll();

$total = db()->raw("SELECT COUNT(*) c, SUM(driver_earnings) e, SUM(distance_km) km FROM parcels WHERE driver_id=? AND status='Delivered'", [$uid])->fetch();
$recent_reviews = db()->raw("
    SELECT r.stars, r.review, r.created_at, u.full_name
    FROM ratings r LEFT JOIN users u ON u.id=r.customer_id
    WHERE r.driver_id=? ORDER BY r.id DESC LIMIT 5
", [$uid])->fetchAll();

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('driver/dashboard') ?>" class="text-sm text-brand-mist">← Back</a>
  <div class="flex items-start justify-between gap-2 mt-2">
    <div>
      <h1 class="text-2xl font-bold">My Stats</h1>
      <p class="text-sm text-brand-mist">Hi <?= esc($me['full_name']) ?>!</p>
    </div>
    <a href="<?= url('driver/analytics/export?days='.(int)$days) ?>" class="btn btn-ghost !py-2 !px-3 text-xs whitespace-nowrap"><?= icon('csv', 'w-4 h-4') ?> Export CSV</a>
  </div>

  <div class="grid grid-cols-3 gap-3 mt-5">
    <div class="glass rounded-2xl p-4 text-center"><div class="text-xs text-brand-mist">Delivered</div><div class="text-2xl font-bold mt-1"><?= (int)($total['c'] ?? 0) ?></div></div>
    <div class="glass rounded-2xl p-4 text-center"><div class="text-xs text-brand-mist">Earnings</div><div class="text-xl font-bold text-emerald-300 mt-1">₱<?= number_format((float)($total['e'] ?? 0),0) ?></div></div>
    <div class="glass rounded-2xl p-4 text-center"><div class="text-xs text-brand-mist">Rating</div><div class="text-xl font-bold mt-1"><?= (int)$me['rating_count'] > 0 ? '★'.number_format((float)$me['rating_avg'],1) : '—' ?></div></div>
  </div>

  <div class="mt-5 glass-strong rounded-2xl p-5">
    <h2 class="font-semibold text-sm mb-2">Last <?= $days ?> days</h2>
    <canvas id="cDaily" height="140"></canvas>
  </div>

  <div class="mt-5 glass rounded-2xl p-5">
    <h2 class="font-semibold text-sm mb-2">Recent reviews</h2>
    <?php if (empty($recent_reviews)): ?>
      <div class="text-xs text-brand-mist">No reviews yet.</div>
    <?php else: foreach ($recent_reviews as $r): ?>
      <div class="border-t border-white/10 py-2 text-sm">
        <div class="flex justify-between"><span><?= str_repeat('★', (int)$r['stars']) . str_repeat('☆', 5-(int)$r['stars']) ?></span><span class="text-[11px] text-brand-mist"><?= date('M j', strtotime($r['created_at'])) ?></span></div>
        <?php if ($r['review']): ?><div class="text-xs text-brand-mist mt-1">"<?= esc($r['review']) ?>" — <?= esc($r['full_name']) ?></div><?php endif; ?>
      </div>
    <?php endforeach; endif; ?>
  </div>
</main>

<script>
const daily = <?= json_encode($daily) ?>;
new Chart(cDaily, {
  type:'line',
  data:{ labels: daily.map(r=>r.d), datasets:[{label:'₱ earnings', data: daily.map(r=>parseFloat(r.earn||0)), borderColor:'#22c55e', backgroundColor:'rgba(34,197,94,.2)', fill:true, tension:.3}] },
  options:{ plugins:{legend:{labels:{color:'#cfe0ff'}}}, scales:{x:{ticks:{color:'#93b5e6'}},y:{ticks:{color:'#93b5e6'}}} }
});
</script>
</body></html>
