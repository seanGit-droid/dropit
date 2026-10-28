<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Notifications';
$active = 'inbox';
$uid = (int)$_SESSION['user_id'];

// Mark all unread as read on view.
db()->table('notifications')->where('user_id', $uid)->where('is_read', 0)->update(['is_read' => 1]);

$filter = $_GET['f'] ?? 'all';
$notifs = db()->table('notifications')
    ->select('id, title, message, parcel_id, created_at')
    ->where('user_id', $uid)
    ->order_by('created_at', 'DESC')
    ->limit(300)->get_all();

// Categorize each notification so we can filter + style.
function inbox_category(array $n): string {
    $t = strtolower($n['title'] ?? '');
    if (str_contains($t, 'sos'))                                    return 'sos';
    if (str_contains($t, 'report') || str_contains($t, 'support'))  return 'support';
    if (str_contains($t, 'timeout') || str_contains($t, 'timed out')) return 'timeout';
    if (str_contains($t, 'accept') || str_contains($t, 'decline'))  return 'driver';
    if (str_contains($t, 'deliver') || str_contains($t, 'picked') || str_contains($t, 'transit') || str_contains($t, 'arrived') || str_contains($t, 'order') || str_contains($t, 'cancel')) return 'order';
    if (str_contains($t, 'message'))                                return 'message';
    return 'other';
}

// Route title-first so reports always land on /admin/reports, never /admin/parcel/<id>.
function inbox_link(array $n, string $cat): ?string {
    switch ($cat) {
        case 'support': return url('admin/reports');
        case 'sos':     return url('admin/drivers-map');
        case 'timeout': return url('admin/shipments/assign');
    }
    if (!empty($n['parcel_id'])) return url('admin/parcel/' . (int)$n['parcel_id']);
    return null;
}

$CATS = [
    'all'     => ['All',      '🔔', 'text-brand-sky'],
    'support' => ['Support',  '🛟', 'text-emerald-300'],
    'sos'     => ['SOS',      '🚨', 'text-red-300'],
    'timeout' => ['Timeouts', '⏰', 'text-amber-300'],
    'order'   => ['Orders',   '📦', 'text-brand-sky'],
    'driver'  => ['Drivers',  '🚚', 'text-purple-300'],
    'message' => ['Messages', '💬', 'text-blue-300'],
];

// Tag each notif with its category, plus tally counts for the filter chips.
$counts = array_fill_keys(array_keys($CATS), 0);
foreach ($notifs as &$n) {
    $n['__cat'] = inbox_category($n);
    $counts[$n['__cat']] = ($counts[$n['__cat']] ?? 0) + 1;
    $counts['all']++;
}
unset($n);

$filtered = $filter === 'all' ? $notifs : array_values(array_filter($notifs, fn($n) => $n['__cat'] === $filter));

$groups = [];
foreach ($filtered as $n) {
    $groups[date('Y-m-d', strtotime($n['created_at']))][] = $n;
}

// Meta for card visuals per category.
function cat_visual(string $cat): array {
    return match ($cat) {
        'sos'     => ['icon' => '🚨', 'grad' => 'from-red-500 to-rose-700',       'accent' => 'rgba(239,68,68,.18)'],
        'support' => ['icon' => '🛟', 'grad' => 'from-emerald-500 to-teal-700',   'accent' => 'rgba(16,185,129,.18)'],
        'timeout' => ['icon' => '⏰', 'grad' => 'from-amber-500 to-orange-700',   'accent' => 'rgba(245,158,11,.18)'],
        'order'   => ['icon' => '📦', 'grad' => 'from-brand-azure to-brand-blue', 'accent' => 'rgba(59,130,246,.18)'],
        'driver'  => ['icon' => '🚚', 'grad' => 'from-purple-500 to-indigo-700',  'accent' => 'rgba(168,85,247,.18)'],
        'message' => ['icon' => '💬', 'grad' => 'from-sky-500 to-blue-700',       'accent' => 'rgba(14,165,233,.18)'],
        default   => ['icon' => '🔔', 'grad' => 'from-slate-500 to-slate-700',    'accent' => 'rgba(148,163,184,.18)'],
    };
}

include __DIR__ . '/../partials/head.php';
?>
<style>
.notif-card{transition:transform .18s ease, background .18s ease, box-shadow .18s ease;}
.notif-card:hover{transform:translateX(2px); background:rgba(255,255,255,.18);}
.notif-card .accent-bar{position:absolute;left:0;top:12px;bottom:12px;width:3px;border-radius:0 3px 3px 0;}
.cat-chip{transition:all .15s ease;}
.cat-chip.active{background:rgba(255,255,255,.22);box-shadow:inset 0 0 0 1px rgba(96,165,250,.55);}
.cat-chip:hover{background:rgba(255,255,255,.14);}
</style>
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-6 max-w-4xl">

    <!-- Header -->
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <div class="flex items-center gap-2">
          <span class="text-3xl">🔔</span>
          <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Notifications</h1>
        </div>
        <p class="text-sm text-brand-mist mt-1">Orders · reports · SOS · dispatch timeouts. Marked read the moment you land here.</p>
      </div>
      <div class="flex items-center gap-3 text-xs text-brand-mist">
        <span class="glass rounded-xl px-3 py-2">📚 <b class="text-white text-sm"><?= $counts['all'] ?></b> total</span>
      </div>
    </div>

    <!-- Quick stats strip -->
    <?php
      $highlight_cats = ['support','sos','timeout','order','driver'];
      $active_counts = array_filter($highlight_cats, fn($c) => ($counts[$c] ?? 0) > 0);
      if (!empty($active_counts)):
    ?>
    <div class="mt-4 grid grid-cols-2 sm:grid-cols-5 gap-2">
      <?php foreach ($highlight_cats as $k):
        $c = $counts[$k] ?? 0;
        $v = cat_visual($k);
        $meta = $CATS[$k];
        if ($c === 0): ?>
          <div class="glass rounded-2xl p-3 opacity-40">
            <div class="text-lg"><?= $v['icon'] ?></div>
            <div class="text-[10px] uppercase tracking-wider text-brand-mist mt-1"><?= $meta[0] ?></div>
            <div class="text-lg font-bold">0</div>
          </div>
        <?php else: ?>
          <a href="?f=<?= $k ?>" class="glass rounded-2xl p-3 hover:bg-white/15 transition" style="background-image:linear-gradient(135deg,<?= $v['accent'] ?>,transparent);">
            <div class="text-lg"><?= $v['icon'] ?></div>
            <div class="text-[10px] uppercase tracking-wider text-brand-mist mt-1"><?= $meta[0] ?></div>
            <div class="text-lg font-bold"><?= $c ?></div>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Category filter chips -->
    <div class="mt-4 flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
      <?php foreach ($CATS as $k => [$label, $emoji]):
        $c = $counts[$k] ?? 0;
        if ($k !== 'all' && $c === 0) continue;
      ?>
        <a href="?f=<?= $k ?>" class="cat-chip shrink-0 glass rounded-full px-3 py-1.5 text-xs flex items-center gap-2 <?= $filter === $k ? 'active' : '' ?>">
          <span><?= $emoji ?></span>
          <span class="font-medium"><?= $label ?></span>
          <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-white/10 min-w-[18px] text-center"><?= $c ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Notifications list -->
    <?php if (empty($filtered)): ?>
      <div class="mt-6 glass-strong rounded-3xl p-12 text-center shadow-glass">
        <div class="text-5xl">🔕</div>
        <div class="mt-3 font-semibold">Walang bago dito</div>
        <div class="text-sm text-brand-mist mt-1">
          <?= $filter === 'all' ? 'Reports, SOS pings, order updates, and dispatch timeouts will show here.' : 'Nothing in this category yet.' ?>
        </div>
      </div>
    <?php else: ?>
      <div class="mt-5 space-y-5">
        <?php foreach ($groups as $date => $items):
          $today = date('Y-m-d');
          $yday  = date('Y-m-d', strtotime('-1 day'));
          $label = ($date === $today) ? 'Today' : (($date === $yday) ? 'Yesterday' : date('l · M j', strtotime($date)));
        ?>
          <div>
            <div class="flex items-center gap-2 px-1 mb-2">
              <span class="text-[11px] uppercase tracking-wider text-brand-mist font-semibold"><?= $label ?></span>
              <span class="flex-1 h-px bg-white/10"></span>
              <span class="text-[10px] text-brand-mist"><?= count($items) ?></span>
            </div>
            <div class="space-y-2">
              <?php foreach ($items as $n):
                $cat = $n['__cat'];
                $link = inbox_link($n, $cat);
                $v = cat_visual($cat);
                $meta = $CATS[$cat] ?? $CATS['all'] ?? null;
                $is_link = (bool)$link;
                $tag = $is_link ? 'a' : 'div';
                $attrs = $is_link ? 'href="' . esc($link) . '"' : '';
              ?>
                <<?= $tag ?> <?= $attrs ?> class="notif-card relative block glass rounded-2xl p-4 pl-5 flex items-start gap-3 shadow-glass">
                  <span class="accent-bar" style="background:linear-gradient(180deg,<?= $v['accent'] ?>,transparent 90%);"></span>
                  <div class="w-11 h-11 rounded-2xl bg-gradient-to-br <?= $v['grad'] ?> flex items-center justify-center text-xl shrink-0 shadow-glow">
                    <?= $v['icon'] ?>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-sm leading-tight"><?= esc($n['title']) ?></span>
                        <?php if ($meta): ?>
                          <span class="chip !px-2 !py-0.5 text-[10px]" style="background:<?= $v['accent'] ?>;color:#fff;"><?= $meta[0] ?></span>
                        <?php endif; ?>
                      </div>
                      <div class="text-[10px] text-brand-mist whitespace-nowrap mt-0.5"><?= date('g:ia', strtotime($n['created_at'])) ?></div>
                    </div>
                    <div class="text-xs text-brand-mist mt-1 leading-snug"><?= nl2br(esc($n['message'])) ?></div>
                    <?php if ($is_link): ?>
                      <div class="mt-2 text-[11px] text-brand-sky font-medium">Open <?= $cat === 'support' ? 'report' : ($cat === 'sos' ? 'drivers map' : ($cat === 'timeout' ? 'dispatch queue' : 'details')) ?> →</div>
                    <?php endif; ?>
                  </div>
                </<?= $tag ?>>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
</body></html>
