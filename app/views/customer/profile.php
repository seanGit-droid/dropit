<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Profile';
$active = 'profile';
$uid = $_SESSION['user_id'];

// Select v4 columns only if they exist (graceful if migration not yet run).
$extra_cols = '';
try {
    $has = db()->raw("SHOW COLUMNS FROM users LIKE 'referral_code'")->fetch();
    if ($has) $extra_cols = ', referral_code, theme';
} catch (Throwable $e) {}
$me = db()->table('users')
    ->select('id, username, email, full_name, phone, avatar, created_at' . $extra_cols)
    ->where('id', $uid)->get();
if (!isset($me['referral_code'])) $me['referral_code'] = null;

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('customer/dashboard') ?>" class="text-sm text-brand-mist">← Back</a>
  <h1 class="text-2xl font-bold mt-2">My Profile</h1>

  <div class="mt-4"><?php include __DIR__ . '/../partials/flash.php'; ?></div>

  <div class="glass-strong rounded-3xl p-5 shadow-glass flex items-center gap-4">
    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-brand-azure to-brand-blue flex items-center justify-center text-2xl font-bold">
      <?= strtoupper(substr($me['full_name'] ?: $me['username'], 0, 1)) ?>
    </div>
    <div>
      <div class="font-semibold text-lg"><?= esc($me['full_name']) ?: esc($me['username']) ?></div>
      <div class="text-xs text-brand-mist">@<?= esc($me['username']) ?></div>
      <div class="text-xs text-brand-mist">Member since <?= date('M Y', strtotime($me['created_at'])) ?></div>
    </div>
  </div>

  <form method="post" action="<?= url('customer/profile/update') ?>" class="mt-5 glass rounded-2xl p-5 space-y-4">
    <?php (new Router())->csrf_field(); ?>
    <div>
      <label class="label">Username</label>
      <input class="input" name="username" value="<?= esc($me['username']) ?>" required>
    </div>
    <div>
      <label class="label">Full name</label>
      <input class="input" name="full_name" value="<?= esc($me['full_name']) ?>">
    </div>
    <div>
      <label class="label">Email</label>
      <input class="input" type="email" name="email" value="<?= esc($me['email']) ?>" required>
    </div>
    <div>
      <label class="label">Phone</label>
      <input class="input" name="phone" value="<?= esc($me['phone']) ?>">
    </div>
    <div class="border-t border-white/10 pt-4">
      <label class="label">New password (leave blank to keep current)</label>
      <input class="input" type="password" name="password" placeholder="••••••••">
    </div>
    <button class="btn btn-primary w-full">Save changes</button>
  </form>

  <div class="mt-4 grid grid-cols-2 gap-2">
    <a href="<?= url('customer/favorites') ?>" class="btn btn-ghost text-sm"><?= icon('favorites', 'w-4 h-4') ?> Favorites</a>
    <a href="<?= url('customer/history') ?>" class="btn btn-ghost text-sm"><?= icon('history', 'w-4 h-4') ?> History</a>
    <a href="<?= url('customer/support') ?>" class="btn btn-primary text-sm"><?= icon('report', 'w-4 h-4 dropit-icon-active') ?> Customer Service</a>
    <a href="<?= url('customer/report') ?>" class="btn btn-ghost text-sm"><?= icon('reports', 'w-4 h-4') ?> Report issue</a>
    <button type="button" id="themeToggle" class="btn btn-ghost text-sm col-span-2"><?= icon('theme', 'w-4 h-4') ?> <?= ($_SESSION['theme'] ?? 'dark')==='light' ? 'Dark' : 'Light' ?> mode</button>
  </div>

  <?php if (!empty($me['referral_code'])): ?>
  <div class="mt-4 glass-strong rounded-2xl p-4 shadow-glow">
    <div class="text-xs text-brand-mist">Your referral code</div>
    <div class="flex items-center justify-between mt-1">
      <span class="font-mono text-xl font-bold"><?= esc($me['referral_code']) ?></span>
      <button onclick="navigator.clipboard.writeText('<?= esc($me['referral_code']) ?>').then(()=>DropIT.toast('Copied!','info'))" class="btn btn-ghost !py-1 !px-3 text-xs">Copy</button>
    </div>
    <div class="text-[11px] text-brand-mist mt-1">Share mo para makakuha ng bonus sa Drop IT wallet.</div>
  </div>
  <?php endif; ?>

  <form method="post" action="<?= url('logout') ?>" class="mt-3">
    <?php (new Router())->csrf_field(); ?>
    <button class="btn btn-ghost w-full"><?= icon('logout', 'w-4 h-4') ?> Logout</button>
  </form>
</main>
<script>
document.getElementById('themeToggle').onclick = async () => {
  const next = document.body.classList.contains('theme-light') ? 'dark' : 'light';
  await DropIT.post('<?= url('api/theme') ?>', {theme:next});
  location.reload();
};
</script>
<?php include __DIR__ . '/../partials/mobile_nav.php'; ?>
</body></html>
