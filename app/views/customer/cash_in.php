<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Cash In';
$active = '';
$uid = (int)$_SESSION['user_id'];
$me = db()->table('users')->select('wallet_balance')->where('id',$uid)->get();

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('customer/dashboard') ?>" class="text-sm text-brand-mist">← Back</a>
  <h1 class="text-2xl font-bold mt-2">💳 Cash In</h1>
  <p class="text-sm text-brand-mist">Top-up your Drop IT wallet via GCash.</p>

  <div class="mt-4"><?php include __DIR__ . '/../partials/flash.php'; ?></div>

  <div class="mt-4 glass-strong rounded-3xl p-5 shadow-glass">
    <div class="text-xs text-brand-mist">Current balance</div>
    <div class="text-3xl font-bold text-emerald-300">₱<?= number_format((float)$me['wallet_balance'], 2) ?></div>
  </div>

  <form method="post" action="<?= url('customer/cash-in') ?>" class="mt-5 glass rounded-3xl p-5 space-y-4">
    <?php (new Router())->csrf_field(); ?>
    <div>
      <label class="label">Amount to top-up</label>
      <input class="input text-2xl font-semibold" type="number" step="0.01" min="20" max="50000" name="amount" placeholder="₱" required autofocus>
      <div class="text-[11px] text-brand-mist mt-1">Minimum ₱20 · Maximum ₱50,000 per top-up.</div>
    </div>
    <div class="grid grid-cols-4 gap-2">
      <?php foreach ([100, 300, 500, 1000] as $q): ?>
        <button type="button" class="btn btn-ghost !py-2 text-xs" onclick="document.querySelector('input[name=amount]').value=<?= $q ?>">₱<?= $q ?></button>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-primary w-full">Pay with GCash →</button>
    <?php if (!PayMongo::is_configured()): ?>
      <div class="text-[11px] text-amber-300 text-center">⚠️ PayMongo sandbox mode — payments auto-simulate as successful.</div>
    <?php endif; ?>
  </form>
</main>
<?php include __DIR__ . '/../partials/mobile_nav.php'; ?>
</body></html>
