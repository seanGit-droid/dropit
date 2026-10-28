<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$role = $_SESSION['role'] ?? '';
$active = $active ?? '';
// Ensure live.php + global bubbles are loaded for authenticated customer/driver pages.
if (empty($GLOBALS['__dropit_live_loaded'])) {
    $GLOBALS['__dropit_live_loaded'] = true;
    include __DIR__ . '/live.php';
}
include __DIR__ . '/global_bubbles.php';
$items = [];
if ($role === 'customer') {
    $items = [
        ['dashboard','Dashboard','customer/dashboard', 'dashboard'],
        ['track','Track','customer/track', 'magnifying'],
        ['notifications','Alerts','customer/notifications', 'alert'],
        ['history','History','customer/history', 'history'],
        ['profile','Profile','customer/profile', 'profile'],
    ];
} elseif ($role === 'driver') {
    $items = [
        ['dashboard','Tasks','driver/dashboard', 'tasks'],
        ['profile','Profile','driver/profile', 'profile'],
    ];
}
?>
<nav class="fixed bottom-0 inset-x-0 z-40 pb-[env(safe-area-inset-bottom)]">
  <div class="mx-auto max-w-md m-3 glass-strong rounded-2xl shadow-glass">
    <ul class="grid grid-cols-<?= count($items) ?> text-xs">
      <?php foreach ($items as $it): [$key,$label,$path,$ic] = $it; $is = ($active === $key); ?>
      <li>
        <a href="<?= url($path) ?>" class="flex flex-col items-center justify-center py-2.5 <?= $is ? 'text-white' : 'text-brand-mist/80 hover:text-white' ?>">
          <?= icon($ic, 'w-5 h-5 ' . ($is ? 'dropit-icon-active' : '')) ?>
          <span class="mt-0.5 font-medium"><?= $label ?></span>
          <?php if ($is): ?><span class="mt-1 h-1 w-6 rounded-full bg-brand-sky"></span><?php endif; ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</nav>
