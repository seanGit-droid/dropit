<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$active = 'accounts';
$user = null;
if (isset($id)) {
    $user = db()->table('users')
        ->select('id, username, email, role, full_name, phone, is_active')
        ->where('id', $id)->get();
    if (!$user) { set_flash('error','User not found.'); header('Location: '.url('admin/accounts')); exit; }
}
$page_title = $user ? 'Edit Account' : 'New Account';
include __DIR__ . '/../partials/head.php';
?>
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-8 max-w-3xl">
    <a href="<?= url('admin/accounts') ?>" class="text-sm text-brand-mist">← Back</a>
    <h1 class="text-2xl md:text-3xl font-bold mt-2"><?= $user ? 'Edit Account' : 'New Account' ?></h1>

    <div class="mt-4"><?php include __DIR__ . '/../partials/flash.php'; ?></div>

    <form method="post" action="<?= url($user ? 'admin/accounts/update/'.$user['id'] : 'admin/accounts/create') ?>" class="mt-4 glass rounded-3xl p-6 space-y-4 shadow-glass">
      <?php (new Router())->csrf_field(); ?>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="label">Username *</label>
          <input class="input" name="username" required value="<?= esc($user['username'] ?? '') ?>">
        </div>
        <div>
          <label class="label">Email *</label>
          <input class="input" type="email" name="email" required value="<?= esc($user['email'] ?? '') ?>">
        </div>
        <div>
          <label class="label">Full name</label>
          <input class="input" name="full_name" value="<?= esc($user['full_name'] ?? '') ?>">
        </div>
        <div>
          <label class="label">Phone</label>
          <input class="input" name="phone" value="<?= esc($user['phone'] ?? '') ?>">
        </div>
        <div>
          <label class="label">Role *</label>
          <select class="input" name="role" required>
            <?php foreach (['customer','driver','admin'] as $r): ?>
              <option value="<?= $r ?>" <?= (isset($user) && $user['role']===$r)?'selected':'' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label">Status</label>
          <select class="input" name="is_active">
            <option value="1" <?= (!$user || $user['is_active'])?'selected':'' ?>>Active</option>
            <option value="0" <?= ($user && !$user['is_active'])?'selected':'' ?>>Disabled</option>
          </select>
        </div>
      </div>
      <div>
        <label class="label"><?= $user ? 'New password (leave blank to keep current)' : 'Password *' ?></label>
        <input class="input" type="password" name="password" <?= $user? '' : 'required' ?> placeholder="••••••••">
      </div>
      <div class="flex gap-2 pt-2">
        <button class="btn btn-primary"><?= $user?'Save changes':'Create account' ?></button>
        <a href="<?= url('admin/accounts') ?>" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </main>
</div>
</body></html>
