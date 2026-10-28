<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Notifications';
$active = 'notifications';
$uid = $_SESSION['user_id'];

// mark all as read (on view)
db()->table('notifications')->where('user_id', $uid)->where('is_read', 0)->update(['is_read' => 1]);

$filter = $_GET['f'] ?? 'all';
$q = db()->table('notifications')
    ->select('id, title, message, parcel_id, created_at')
    ->where('user_id', $uid);
$notifs = $q->order_by('created_at', 'DESC')->limit(100)->get_all();

function icon_for(string $title): string {
    $t = strtolower($title);
    if (str_contains($t, 'accept') || str_contains($t, 'accepted')) return '✅';
    if (str_contains($t, 'deliver'))   return '📦';
    if (str_contains($t, 'pickup') || str_contains($t, 'picked')) return '🚚';
    if (str_contains($t, 'transit') || str_contains($t, 'way')) return '🏃';
    if (str_contains($t, 'cancel'))    return '⚠️';
    if (str_contains($t, 'sos'))       return '🚨';
    if (str_contains($t, 'message') || str_contains($t, 'chat')) return '💬';
    if (str_contains($t, 'payment') || str_contains($t, 'paid')) return '💳';
    if (str_contains($t, 'order'))     return '🛎️';
    return '🔔';
}

// Group by day.
$groups = [];
foreach ($notifs as $n) {
    $d = date('Y-m-d', strtotime($n['created_at']));
    $groups[$d][] = $n;
}

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('customer/dashboard') ?>" class="text-sm text-brand-mist">← Back</a>
  <div class="mt-2 flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold">Notifications</h1>
      <p class="text-sm text-brand-mist">Updates from your driver and Drop IT.</p>
    </div>
    <span class="chip chip-transit"><?= count($notifs) ?> total</span>
  </div>

  <?php if (empty($notifs)): ?>
    <div class="mt-6 glass-strong rounded-3xl p-10 text-center shadow-glass">
      <div class="text-5xl">🔕</div>
      <div class="mt-3 font-semibold">All caught up</div>
      <div class="text-sm text-brand-mist mt-1">Driver updates and chat pings will show here.</div>
    </div>
  <?php else: ?>
    <div class="mt-5 space-y-5">
      <?php foreach ($groups as $date => $items):
        $today   = date('Y-m-d');
        $yday    = date('Y-m-d', strtotime('-1 day'));
        $label   = ($date === $today) ? 'Today' : (($date === $yday) ? 'Yesterday' : date('l, M j', strtotime($date)));
      ?>
        <div>
          <div class="text-[11px] uppercase tracking-wider text-brand-mist font-semibold px-1 mb-2"><?= $label ?></div>
          <div class="space-y-2">
          <?php foreach ($items as $n):
            $link = $n['parcel_id'] ? url('customer/track?q=') : null;
          ?>
            <div class="glass rounded-2xl p-3 flex items-start gap-3 hover:bg-white/15 transition">
              <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-azure to-brand-blue flex items-center justify-center text-lg shrink-0">
                <?= icon_for($n['title']) ?>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                  <div class="font-semibold text-sm"><?= esc($n['title']) ?></div>
                  <div class="text-[10px] text-brand-mist whitespace-nowrap mt-0.5"><?= date('g:ia', strtotime($n['created_at'])) ?></div>
                </div>
                <div class="text-xs text-brand-mist mt-0.5 leading-snug"><?= esc($n['message']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/../partials/mobile_nav.php'; ?>
</body></html>
