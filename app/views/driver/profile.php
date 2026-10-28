<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Profile';
$uid = $_SESSION['user_id'];
$me = db()->table('users')
    ->select('id, username, email, full_name, phone, created_at')
    ->where('id', $uid)->get();

include __DIR__ . '/../partials/head.php';
?>
<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('driver/dashboard') ?>" class="text-sm text-brand-mist">← Back</a>
  <h1 class="text-2xl font-bold mt-2">My Profile</h1>

  <div class="mt-4"><?php include __DIR__ . '/../partials/flash.php'; ?></div>

  <div class="glass-strong rounded-3xl p-5 shadow-glass flex items-center gap-4">
    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-brand-azure to-brand-blue flex items-center justify-center text-2xl font-bold">
      <?= strtoupper(substr($me['full_name'] ?: $me['username'], 0, 1)) ?>
    </div>
    <div>
      <div class="font-semibold text-lg"><?= esc($me['full_name']) ?: esc($me['username']) ?></div>
      <div class="text-xs text-brand-mist">@<?= esc($me['username']) ?> · Driver</div>
    </div>
  </div>

  <form method="post" action="<?= url('driver/profile/update') ?>" class="mt-5 glass rounded-2xl p-5 space-y-4">
    <?php (new Router())->csrf_field(); ?>
    <div><label class="label">Username</label><input class="input" name="username" value="<?= esc($me['username']) ?>" required></div>
    <div><label class="label">Full name</label><input class="input" name="full_name" value="<?= esc($me['full_name']) ?>"></div>
    <div><label class="label">Email</label><input class="input" type="email" name="email" value="<?= esc($me['email']) ?>" required></div>
    <div><label class="label">Phone</label><input class="input" name="phone" value="<?= esc($me['phone']) ?>"></div>
    <div class="border-t border-white/10 pt-4">
      <label class="label">New password (blank to keep)</label>
      <input class="input" type="password" name="password" placeholder="••••••••">
    </div>
    <button class="btn btn-primary w-full">Save changes</button>
  </form>

  <form method="post" action="<?= url('logout') ?>" class="mt-3">
    <?php (new Router())->csrf_field(); ?>
    <button class="btn btn-ghost w-full"><?= icon('logout', 'w-4 h-4') ?> Logout</button>
  </form>
</main>
</body></html>
