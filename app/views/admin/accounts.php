<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Accounts';
$active = 'accounts';

$filter = $_GET['role'] ?? 'all';
$q = db()->table('users')
    ->select('id, username, email, role, full_name, phone, is_active, created_at')
    ->where('role', '!=', 'admin');  // Exclude admin accounts
if (in_array($filter, ['customer','driver'])) {
    $q = $q->where('role', $filter);
}
$users = $q->order_by('created_at', 'DESC')->get_all();

include __DIR__ . '/../partials/head.php';
?>
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold">Accounts</h1>
        <p class="text-sm text-brand-mist">Manage drivers and customers.</p>
      </div>
      <a href="<?= url('admin/accounts/new') ?>" class="btn btn-primary">+ New Account</a>
    </div>

    <?php include __DIR__ . '/../partials/flash.php'; ?>

    <div class="flex gap-2 mb-4 text-sm">
      <?php foreach (['all'=>'All','customer'=>'Customers','driver'=>'Drivers'] as $k=>$v): ?>
        <a href="<?= url('admin/accounts?role='.$k) ?>" class="chip <?= $filter===$k?'chip-transit':'bg-white/5 text-brand-mist' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>

    <div class="glass rounded-3xl p-4 md:p-6 shadow-glass overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-brand-mist text-xs uppercase tracking-wider">
          <tr>
            <th class="py-2 pr-3">User</th>
            <th class="py-2 pr-3">Email</th>
            <th class="py-2 pr-3">Role</th>
            <th class="py-2 pr-3">Phone</th>
            <th class="py-2 pr-3">Status</th>
            <th class="py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr class="border-t border-white/10">
            <td class="py-3 pr-3">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-brand-azure to-brand-blue flex items-center justify-center text-xs font-bold">
                  <?= strtoupper(substr($u['full_name'] ?: $u['username'], 0, 1)) ?>
                </div>
                <div>
                  <div class="font-medium"><?= esc($u['full_name']) ?: esc($u['username']) ?></div>
                  <div class="text-xs text-brand-mist">@<?= esc($u['username']) ?></div>
                </div>
              </div>
            </td>
            <td class="py-3 pr-3 text-brand-mist"><?= esc($u['email']) ?></td>
            <td class="py-3 pr-3"><span class="chip chip-transit"><?= esc($u['role']) ?></span></td>
            <td class="py-3 pr-3 text-brand-mist"><?= esc($u['phone']) ?: '�' ?></td>
            <td class="py-3 pr-3">
              <?= $u['is_active'] ? '<span class="chip chip-delivered">Active</span>' : '<span class="chip chip-cancelled">Disabled</span>' ?>
            </td>
            <td class="py-3">
              <div class="flex items-center gap-2">
                <a href="<?= url('admin/accounts/edit/'.$u['id']) ?>" class="btn btn-ghost text-xs !py-1.5 !px-3">Edit</a>
                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                <form method="post" action="<?= url('admin/accounts/delete/'.$u['id']) ?>" onsubmit="return confirm('Delete this account?');">
                  <?php (new Router())->csrf_field(); ?>
                  <button class="btn btn-danger text-xs !py-1.5 !px-3">Delete</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
</body></html>
