<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'History';
$active = 'history';

$tab    = $_GET['tab'] ?? 'parcels';
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';

// --- Parcels (all-time, searchable) ---
$pq = db()->raw("
  SELECT p.id, p.tracking_number, p.status, p.amount, p.payment_method, p.payment_status,
         p.recipient_name, p.recipient_address, p.created_at, p.updated_at, p.is_walkin,
         c.full_name AS customer_name, d.full_name AS driver_name
  FROM parcels p
  LEFT JOIN users c ON c.id = p.customer_id
  LEFT JOIN users d ON d.id = p.driver_id
  " . ($status ? "WHERE p.status = ?" : "") . "
  ORDER BY p.created_at DESC
  LIMIT 500
", $status ? [$status] : []);
$parcels = $pq->fetchAll();
if ($search !== '') {
    $needle = mb_strtolower($search);
    $parcels = array_values(array_filter($parcels, function($p) use ($needle) {
        $hay = mb_strtolower(implode(' ', [$p['tracking_number'] ?? '', $p['recipient_name'] ?? '', $p['customer_name'] ?? '', $p['driver_name'] ?? '']));
        return str_contains($hay, $needle);
    }));
}

// --- Activity log (action-based timeline) ---
$logs = db()->raw("
  SELECT a.action, a.details, a.created_at, u.username, u.full_name
  FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id
  ORDER BY a.created_at DESC LIMIT 300
")->fetchAll();

// KPIs for header
$counts = db()->raw("SELECT status, COUNT(*) c FROM parcels GROUP BY status")->fetchAll();
$by_status = [];
foreach ($counts as $c) $by_status[$c['status']] = (int)$c['c'];
$total = array_sum($by_status);

function action_meta(string $a): array {
    return match (true) {
        str_starts_with($a, 'login')        => ['🔑','chip-transit'],
        str_starts_with($a, 'logout')       => ['⎋','chip-cancelled'],
        str_starts_with($a, 'idle')         => ['💤','chip-cancelled'],
        str_starts_with($a, 'signup')       => ['👋','chip-delivered'],
        str_starts_with($a, 'order_placed') => ['🛎️','chip-transit'],
        str_starts_with($a, 'walkin')       => ['🧍','chip-transit'],
        str_starts_with($a, 'parcel_dispatch') => ['🚚','chip-pending'],
        str_starts_with($a, 'driver_accept')   => ['✅','chip-delivered'],
        str_starts_with($a, 'driver_decline')  => ['❌','chip-cancelled'],
        str_starts_with($a, 'driver_update')   => ['📦','chip-transit'],
        str_starts_with($a, 'admin_notify')    => ['📨','chip-pending'],
        str_starts_with($a, 'settings')        => ['⚙️','chip-transit'],
        default                                => ['•','chip-transit'],
    };
}

include __DIR__ . '/../partials/head.php';
?>
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold">History</h1>
        <p class="text-sm text-brand-mist">All shipments and system activity — filter, search, drill in.</p>
      </div>
      <form method="get" action="<?= url('admin/history') ?>" class="flex gap-2">
        <input type="hidden" name="tab" value="<?= esc($tab) ?>">
        <?php if ($status): ?><input type="hidden" name="status" value="<?= esc($status) ?>"><?php endif; ?>
        <input name="q" value="<?= esc($search) ?>" class="input !py-2 text-sm w-56" placeholder="Search tracking, name…">
        <button class="btn btn-primary !py-2 text-sm">Search</button>
      </form>
    </div>

    <!-- KPI strip -->
    <div class="mt-5 grid grid-cols-2 md:grid-cols-5 gap-3">
      <a href="?tab=parcels" class="glass rounded-2xl p-3 text-center hover:bg-white/15 transition">
        <div class="text-[11px] text-brand-mist">Total</div><div class="text-2xl font-bold mt-0.5"><?= $total ?></div>
      </a>
      <?php foreach (['Pending'=>'🟡','In Transit'=>'🔵','Delivered'=>'🟢','Cancelled'=>'🔴'] as $s=>$icon): ?>
        <a href="?tab=parcels&status=<?= urlencode($s) ?>" class="glass rounded-2xl p-3 text-center hover:bg-white/15 transition <?= $status===$s?'ring-1 ring-brand-sky/50':'' ?>">
          <div class="text-[11px] text-brand-mist"><?= $icon ?> <?= $s ?></div>
          <div class="text-2xl font-bold mt-0.5"><?= $by_status[$s] ?? 0 ?></div>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Tabs -->
    <div class="mt-6 flex gap-2">
      <a href="?tab=parcels<?= $status ? '&status='.urlencode($status) : '' ?><?= $search ? '&q='.urlencode($search) : '' ?>"
         class="glass rounded-xl px-4 py-2 text-sm <?= $tab==='parcels'?'ring-1 ring-brand-sky bg-white/15':'hover:bg-white/10' ?>">📦 Shipments</a>
      <a href="?tab=activity"
         class="glass rounded-xl px-4 py-2 text-sm <?= $tab==='activity'?'ring-1 ring-brand-sky bg-white/15':'hover:bg-white/10' ?>">🕑 Activity</a>
    </div>

    <?php if ($tab === 'parcels'): ?>
      <!-- Parcels list -->
      <div class="mt-5 space-y-2">
        <?php if ($status): ?>
          <div class="flex items-center gap-2 mb-1">
            <span class="chip chip-transit">Filter: <?= esc($status) ?></span>
            <a href="?tab=parcels<?= $search ? '&q='.urlencode($search) : '' ?>" class="text-xs text-brand-sky">✕ Clear</a>
          </div>
        <?php endif; ?>
        <?php if (empty($parcels)): ?>
          <div class="glass-strong rounded-3xl p-10 text-center">
            <div class="text-5xl">📭</div>
            <div class="mt-3 font-semibold">No shipments found</div>
            <div class="text-sm text-brand-mist mt-1"><?= $search ? 'Try a different search term.' : 'Parcels will appear once created.' ?></div>
          </div>
        <?php else: foreach ($parcels as $p): ?>
          <a href="<?= url('admin/parcel/'.$p['id']) ?>" class="glass rounded-2xl p-4 flex items-start gap-3 hover:bg-white/15 transition block">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-brand-azure to-brand-blue flex items-center justify-center text-xl shrink-0">📦</div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2 flex-wrap">
                <div class="flex items-center gap-2">
                  <span class="font-mono text-xs font-bold"><?= esc($p['tracking_number']) ?></span>
                  <?= status_chip($p['status']) ?>
                  <?php if ($p['is_walkin']): ?><span class="chip chip-pending !text-[10px]">walk-in</span><?php endif; ?>
                  <span class="chip chip-transit !text-[10px] capitalize"><?= esc($p['payment_method'] ?: 'wallet') ?></span>
                </div>
                <span class="text-[11px] text-brand-mist"><?= date('M j, Y g:ia', strtotime($p['created_at'])) ?></span>
              </div>
              <div class="mt-1 text-sm"><?= esc($p['recipient_name']) ?></div>
              <div class="text-xs text-brand-mist truncate">📍 <?= esc($p['recipient_address']) ?></div>
              <div class="mt-1 flex items-center justify-between text-xs">
                <div class="text-brand-mist">
                  <?= $p['customer_name'] ? '👤 '.esc($p['customer_name']).' · ' : '' ?>
                  <?= $p['driver_name'] ? '🚚 '.esc($p['driver_name']) : '(no driver)' ?>
                </div>
                <div class="font-semibold text-emerald-300">₱<?= number_format((float)$p['amount'], 2) ?></div>
              </div>
            </div>
          </a>
        <?php endforeach; endif; ?>
      </div>
    <?php else: ?>
      <!-- Activity feed -->
      <div class="mt-5 space-y-2">
        <?php if (empty($logs)): ?>
          <div class="glass-strong rounded-3xl p-10 text-center">
            <div class="text-5xl">💤</div>
            <div class="mt-3 font-semibold">No activity yet</div>
          </div>
        <?php else:
          $last_day = null;
          foreach ($logs as $l):
            $day = date('Y-m-d', strtotime($l['created_at']));
            if ($day !== $last_day):
              $today = date('Y-m-d'); $yday = date('Y-m-d', strtotime('-1 day'));
              $dayLabel = ($day === $today) ? 'Today' : (($day === $yday) ? 'Yesterday' : date('l, M j, Y', strtotime($l['created_at'])));
        ?>
            <div class="text-[11px] uppercase tracking-wider text-brand-mist font-semibold px-1 pt-3"><?= $dayLabel ?></div>
        <?php     $last_day = $day; endif;
              [$icon, $chipClass] = action_meta($l['action']);
        ?>
          <div class="glass rounded-2xl p-3 flex items-start gap-3">
            <div class="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center text-lg shrink-0"><?= $icon ?></div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2 flex-wrap">
                <div class="flex items-center gap-2">
                  <span class="chip <?= $chipClass ?> !text-[10px]"><?= esc($l['action']) ?></span>
                  <?php if ($l['username']): ?><span class="text-xs text-brand-mist">@<?= esc($l['username']) ?></span><?php endif; ?>
                </div>
                <span class="text-[10px] text-brand-mist whitespace-nowrap"><?= date('g:ia', strtotime($l['created_at'])) ?></span>
              </div>
              <div class="text-sm mt-1 leading-snug"><?= esc($l['details']) ?></div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
</body></html>
