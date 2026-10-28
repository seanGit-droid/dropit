<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Admin Dashboard';
$active = 'dashboard';

// Counts exclude admin accounts from "users" totals.
$total_users    = db()->table('users')->where('role','!=','admin')->count();
$total_drivers  = db()->table('users')->where('role','driver')->where('is_active',1)->count();
$total_customers = db()->table('users')->where('role','customer')->where('is_active',1)->count();
$total_parcels  = db()->table('parcels')->count();
$pending        = db()->table('parcels')->where('status','Pending')->count();
$in_transit     = db()->table('parcels')->where('status','In Transit')->count();
$delivered      = db()->table('parcels')->where('status','Delivered')->count();

$uptime_start_ts = @filemtime(APP_ROOT . '/index.php') ?: time();
$uptime_days     = max(1, floor((time() - $uptime_start_ts) / 86400));

$recent = db()->table('activity_logs')
    ->select('user_id, action, details, created_at')
    ->order_by('created_at', 'DESC')
    ->limit(10)
    ->get_all();


include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>

  <main class="flex-1 p-4 md:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold">Hi Admin! 👋</h1>
        <p class="text-sm text-brand-mist">Live overview of the Drop IT fleet.</p>
      </div>
      <div class="flex items-center gap-2">
        <?php if ($pending > 0): ?>
          <a href="<?= url('admin/shipments/assign') ?>" class="btn btn-ghost ring-1 ring-amber-300/40">🆕 <?= $pending ?> pending</a>
        <?php endif; ?>
        <a href="<?= url('admin/analytics') ?>" class="btn btn-ghost">📊 Analytics</a>
        <a href="<?= url('admin/shipments/walkin') ?>" class="btn btn-primary">+ New Parcel</a>
      </div>
    </div>

    <?php include __DIR__ . '/../partials/flash.php'; ?>

    <!-- stat boxes (auto-refreshed) -->
    <div id="live-stats" data-total-parcels="<?= (int)$total_parcels ?>" data-pending="<?= (int)$pending ?>" class="grid md:grid-cols-3 gap-4">
      <div class="glass-strong rounded-3xl p-6 shadow-glass">
        <div class="flex items-center justify-between">
          <div class="text-sm text-brand-mist">Fleet Status</div>
          <span class="chip chip-delivered">● Online</span>
        </div>
        <div class="mt-3 text-3xl font-bold"><?= $total_drivers ?></div>
        <div class="text-sm text-brand-mist">Active drivers</div>
        <div class="mt-3 text-xs text-brand-mist">Uptime: <?= $uptime_days ?> day(s)</div>
      </div>

      <div class="glass-strong rounded-3xl p-6 shadow-glass">
        <div class="flex items-center justify-between">
          <div class="text-sm text-brand-mist">Parcel Stats</div>
          <div class="text-xs text-brand-mist">Total: <?= $total_parcels ?></div>
        </div>
        <div class="mt-4 space-y-2 text-sm">
          <div class="flex items-center justify-between">
            <span>Pending</span><span class="font-semibold"><?= $pending ?></span>
          </div>
          <div class="h-1.5 w-full bg-white/10 rounded-full overflow-hidden">
            <div class="h-full bg-yellow-400" style="width:<?= $total_parcels? round($pending/$total_parcels*100):0 ?>%"></div>
          </div>
          <div class="flex items-center justify-between">
            <span>In Transit</span><span class="font-semibold"><?= $in_transit ?></span>
          </div>
          <div class="h-1.5 w-full bg-white/10 rounded-full overflow-hidden">
            <div class="h-full bg-sky-400" style="width:<?= $total_parcels? round($in_transit/$total_parcels*100):0 ?>%"></div>
          </div>
          <div class="flex items-center justify-between">
            <span>Delivered</span><span class="font-semibold"><?= $delivered ?></span>
          </div>
          <div class="h-1.5 w-full bg-white/10 rounded-full overflow-hidden">
            <div class="h-full bg-emerald-400" style="width:<?= $total_parcels? round($delivered/$total_parcels*100):0 ?>%"></div>
          </div>
        </div>
      </div>

      <div class="glass-strong rounded-3xl p-6 shadow-glass">
        <div class="text-sm text-brand-mist">Users (excl. admin)</div>
        <div class="mt-3 text-3xl font-bold"><?= $total_users ?></div>
        <div class="text-sm text-brand-mist"><?= $total_customers ?> customers · <?= $total_drivers ?> drivers</div>
        <a href="<?= url('admin/accounts') ?>" class="mt-4 inline-flex text-sm text-brand-sky hover:text-white">Manage →</a>
      </div>
    </div>

    <!-- activity (auto-refreshed) -->
    <div id="live-activity" class="mt-6 glass rounded-3xl p-6 shadow-glass">
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-lg">Recent Activity</h2>
        <span class="chip chip-transit">Live</span>
      </div>
      <div class="mt-4 overflow-auto scrollbar-thin">
        <table class="w-full text-sm">
          <thead class="text-left text-brand-mist text-xs uppercase tracking-wider">
            <tr>
              <th class="py-2 pr-4">When</th>
              <th class="py-2 pr-4">Action</th>
              <th class="py-2">Details</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($recent)): ?>
            <tr><td colspan="3" class="py-6 text-center text-brand-mist">No activity yet.</td></tr>
          <?php else: foreach ($recent as $r): ?>
            <tr class="border-t border-white/10">
              <td class="py-2 pr-4 whitespace-nowrap text-brand-mist"><?= date('M j, g:ia', strtotime($r['created_at'])) ?></td>
              <td class="py-2 pr-4"><span class="chip chip-transit"><?= esc($r['action']) ?></span></td>
              <td class="py-2"><?= esc($r['details']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<script>
DropIT.startNotifPoll();
// Live stats + activity partial refresh
(function(){
  const stats = document.getElementById('live-stats');
  let prevParcels = parseInt(stats.dataset.totalParcels || '0', 10);
  let prevPending = parseInt(stats.dataset.pending || '0', 10);
  async function refreshAndDetect(){
    await DropIT.refresh(['live-stats','live-activity']);
    const s = document.getElementById('live-stats');
    if (!s) return;
    const pp = parseInt(s.dataset.totalParcels || '0', 10);
    const pn = parseInt(s.dataset.pending || '0', 10);
    if (pp > prevParcels || pn > prevPending){
      DropIT.sound.notif();
      DropIT.toast('New parcel activity', 'info');
    }
    prevParcels = pp; prevPending = pn;
  }
  setInterval(refreshAndDetect, 8000);
})();
</script>
</body></html>
