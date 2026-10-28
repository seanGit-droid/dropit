<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$active = $active ?? '';
$pending_count = 0;
try { $pending_count = (int) db()->table('parcels')->where('status','Pending')->count(); } catch (Throwable $e) {}
// Ensure live.php is loaded exactly once (for notif polling, toasts, theme toggle).
if (empty($GLOBALS['__dropit_live_loaded'])) {
    $GLOBALS['__dropit_live_loaded'] = true;
    include __DIR__ . '/live.php';
}
$menu = [
    ['dashboard','Dashboard','admin/dashboard', 'dashboard'],
    ['accounts','Accounts','admin/accounts', 'accounts'],
    ['walkin','New Parcel','admin/shipments/walkin', 'parcel'],
    ['assign','Dispatch','admin/shipments/assign', 'transit', $pending_count],
    ['drivers_map','Drivers Map','admin/drivers-map', 'map'],
    ['messages','Messages','admin/messages', 'messages'],
    ['analytics','Analytics','admin/analytics', 'analytics'],
    ['history','History','admin/history', 'history'],
    ['reports','Reports','admin/reports', 'reports'],
    ['inbox','Notifications','admin/inbox', 'bell'],
    ['notifications','Email Log','admin/notifications', 'emails'],
    ['settings','Settings','admin/settings', 'settings'],
];
?>
<aside class="hidden md:flex md:flex-col w-64 shrink-0 p-4 gap-3 sticky top-0 h-screen overflow-hidden">
  <div class="glass-strong rounded-2xl p-4 shadow-glass shrink-0">
    <div class="flex items-center gap-3">
      <img src="<?= url('resources/weblogo.png') ?>" alt="Drop IT" class="w-10 h-10 object-contain">
      <div class="flex-1 min-w-0">
        <div class="font-bold tracking-tight">Drop IT</div>
        <div class="text-xs text-brand-mist">Admin Console</div>
      </div>
      <a href="<?= url('admin/inbox') ?>" class="relative glass rounded-xl p-2" title="Inbox">
        <?= icon('bell', 'w-5 h-5') ?>
        <span data-notif-badge class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1.5 py-0.5 hidden">0</span>
      </a>
    </div>
  </div>
  <nav class="glass rounded-2xl p-2 shadow-glass flex-1 overflow-y-auto scrollbar-thin min-h-0">
    <?php foreach ($menu as $item):
      $key=$item[0]; $label=$item[1]; $path=$item[2]; $icon=$item[3]; $badge=$item[4]??null;
      $is = ($active === $key); ?>
      <a href="<?= url($path) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 text-sm font-medium transition <?= $is ? 'bg-white/15 text-white ring-1 ring-white/25' : 'text-brand-mist hover:bg-white/10 hover:text-white' ?>">
        <?= icon($icon, 'w-5 h-5 ' . ($is ? 'dropit-icon-active' : '')) ?>
        <span class="flex-1"><?= $label ?></span>
        <?php if (!empty($badge)): ?><span class="chip chip-pending !px-2 !py-0.5 text-[10px]"><?= $badge ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="space-y-2 shrink-0">
    <button id="themeToggle" class="w-full btn btn-ghost text-xs"><?= icon('theme', 'w-4 h-4') ?> <?= ($_SESSION['theme'] ?? 'dark')==='light' ? 'Dark mode' : 'Light mode' ?></button>
    <form action="<?= url('logout') ?>" method="post">
      <?php (new Router())->csrf_field(); ?>
      <button class="w-full btn btn-ghost"><?= icon('logout', 'w-4 h-4') ?> Logout</button>
    </form>
  </div>
</aside>
<?php include __DIR__ . '/global_bubbles.php'; ?>
<script>
(function(){
  const btn = document.getElementById('themeToggle');
  if (btn) {
    const csrf = <?= json_encode(csrf_token()) ?>;
    btn.onclick = async () => {
      const next = document.body.classList.contains('theme-light') ? 'dark' : 'light';
      const body = new URLSearchParams({theme: next, csrf_token: csrf});
      await fetch('<?= url('api/theme') ?>', {method:'POST', body, credentials:'same-origin'}).catch(()=>{});
      location.reload();
    };
  }
  // Real-time notifications for every admin page that includes the sidebar.
  if (window.DropIT) DropIT.startNotifPoll();
})();
</script>
