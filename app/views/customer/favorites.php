<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Favorite Addresses';
$active = 'profile';
$uid = (int)$_SESSION['user_id'];
$list = db()->table('favorite_addresses')->select('id, label, address, lat, lng')->where('user_id',$uid)->order_by('id','DESC')->get_all();

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('customer/profile') ?>" class="text-sm text-brand-mist">← Back</a>
  <h1 class="text-2xl font-bold mt-2 flex items-center gap-2"><?= icon('favorites', 'w-6 h-6') ?> Favorite Addresses</h1>
  <p class="text-sm text-brand-mist">Save places you often ship to.</p>

  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <form method="post" action="<?= url('customer/favorites') ?>" class="mt-4 glass rounded-2xl p-4 space-y-3">
    <?php (new Router())->csrf_field(); ?>
    <div><label class="label">Label (Home, Office, Mom's place…)</label><input class="input" name="label" maxlength="60" required></div>
    <div><label class="label">Address</label><input class="input" name="address" required></div>
    <div class="grid grid-cols-2 gap-2">
      <input class="input !py-1.5 text-xs" name="lat" placeholder="lat (optional)">
      <input class="input !py-1.5 text-xs" name="lng" placeholder="lng (optional)">
    </div>
    <button class="btn btn-primary w-full">+ Save</button>
  </form>

  <div class="mt-5 space-y-2">
    <?php if (empty($list)): ?>
      <div class="glass rounded-2xl p-6 text-center text-brand-mist">No favorites yet.</div>
    <?php else: foreach ($list as $f): ?>
      <div class="glass rounded-2xl p-3 flex items-start justify-between gap-2">
        <div class="min-w-0">
          <div class="font-semibold text-sm"><?= esc($f['label']) ?></div>
          <div class="text-xs text-brand-mist"><?= esc($f['address']) ?></div>
        </div>
        <form method="post" action="<?= url('customer/favorites/delete/'.$f['id']) ?>">
          <?php (new Router())->csrf_field(); ?>
          <button class="btn btn-ghost !py-1 !px-2 text-xs">Delete</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>
</main>
</body></html>
