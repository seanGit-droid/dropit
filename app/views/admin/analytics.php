<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Analytics';
$active = 'analytics';

// Date range — supports ?days=N (quick), or ?from=YYYY-MM-DD&to=YYYY-MM-DD (range), or ?date=YYYY-MM-DD (single day).
$single = trim($_GET['date'] ?? '');
$from   = trim($_GET['from']  ?? '');
$to     = trim($_GET['to']    ?? '');
$days   = (int)($_GET['days'] ?? 14);

if ($single !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $single)) {
    $from = $to = $single;
    $mode = 'day';
} elseif ($from !== '' && $to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $mode = 'range';
} else {
    $days = max(1, min(365, $days ?: 14));
    $from = date('Y-m-d', strtotime("-{$days} days"));
    $to   = date('Y-m-d');
    $mode = 'days';
}

$range_label = ($from === $to) ? date('M j, Y', strtotime($from))
                                : date('M j, Y', strtotime($from)) . ' – ' . date('M j, Y', strtotime($to));

$rows = db()->raw("
    SELECT DATE(created_at) d, COUNT(*) c, SUM(amount) rev,
           SUM(CASE WHEN status='Delivered' THEN 1 ELSE 0 END) delivered,
           SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END) cancelled
    FROM parcels WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at) ORDER BY d ASC
", [$from, $to])->fetchAll();

$pay_split = db()->raw("
    SELECT payment_method, COUNT(*) c, SUM(amount) rev
    FROM parcels WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY payment_method
", [$from, $to])->fetchAll();

$top_drivers = db()->raw("
    SELECT u.id, u.full_name, u.username, COUNT(p.id) trips, SUM(p.driver_earnings) earnings,
           u.rating_avg, u.rating_count
    FROM users u LEFT JOIN parcels p ON p.driver_id = u.id AND p.status='Delivered' AND DATE(p.created_at) BETWEEN ? AND ?
    WHERE u.role='driver' AND u.is_active=1
    GROUP BY u.id, u.full_name, u.username, u.rating_avg, u.rating_count
    ORDER BY trips DESC LIMIT 10
", [$from, $to])->fetchAll();

$status_counts = db()->raw("SELECT status, COUNT(*) c FROM parcels GROUP BY status")->fetchAll();

$total_rev   = array_sum(array_map(fn($r)=>(float)$r['rev'], $rows));
$total_par   = array_sum(array_map(fn($r)=>(int)$r['c'], $rows));
$total_del   = array_sum(array_map(fn($r)=>(int)$r['delivered'], $rows));
$total_can   = array_sum(array_map(fn($r)=>(int)$r['cancelled'], $rows));

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold">Analytics</h1>
        <p class="text-sm text-brand-mist"><?= esc($range_label) ?> · <span class="chip chip-transit"><?= strtoupper($mode) ?></span></p>
      </div>
      <div class="flex gap-2">
        <button type="button" onclick="document.getElementById('reportModal').classList.remove('hidden')" class="btn btn-primary">📄 Generate Report</button>
        <a href="<?= url('admin/analytics/export?from='.urlencode($from).'&to='.urlencode($to)) ?>" class="btn btn-ghost">⬇ Export CSV</a>
      </div>
    </div>

    <!-- Date range controls -->
    <form method="get" action="<?= url('admin/analytics') ?>" class="mt-5 glass rounded-2xl p-4 grid md:grid-cols-[1fr_auto_1fr_auto_auto] gap-3 items-end">
      <div>
        <label class="label !text-xs">From</label>
        <input type="date" name="from" value="<?= esc($from) ?>" class="input !py-2 text-sm" max="<?= date('Y-m-d') ?>">
      </div>
      <div class="text-brand-mist text-center md:pt-6">→</div>
      <div>
        <label class="label !text-xs">To</label>
        <input type="date" name="to" value="<?= esc($to) ?>" class="input !py-2 text-sm" max="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label class="label !text-xs">Or a single day</label>
        <input type="date" name="date" value="<?= $mode==='day' ? esc($single) : '' ?>" class="input !py-2 text-sm" max="<?= date('Y-m-d') ?>">
      </div>
      <button class="btn btn-primary">Apply</button>
      <div class="md:col-span-5 flex flex-wrap gap-2 pt-1">
        <span class="text-xs text-brand-mist mr-1">Quick:</span>
        <?php foreach ([1=>'Today',7=>'7d',14=>'14d',30=>'30d',60=>'60d',90=>'90d'] as $d=>$lbl): ?>
          <a href="?days=<?= $d ?>" class="btn btn-ghost !py-1 !px-2 text-xs <?= ($mode==='days' && $days==$d)?'ring-1 ring-white/30':'' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
      </div>
    </form>

    <!-- KPI row -->
    <div class="grid md:grid-cols-4 gap-4 mt-6">
      <div class="glass-strong rounded-2xl p-5"><div class="text-xs text-brand-mist">Revenue</div><div class="text-3xl font-bold mt-1">₱<?= number_format($total_rev,2) ?></div></div>
      <div class="glass-strong rounded-2xl p-5"><div class="text-xs text-brand-mist">Parcels</div><div class="text-3xl font-bold mt-1"><?= $total_par ?></div></div>
      <div class="glass-strong rounded-2xl p-5"><div class="text-xs text-brand-mist">Delivered</div><div class="text-3xl font-bold mt-1 text-emerald-300"><?= $total_del ?></div></div>
      <div class="glass-strong rounded-2xl p-5"><div class="text-xs text-brand-mist">Cancelled</div><div class="text-3xl font-bold mt-1 text-red-300"><?= $total_can ?></div></div>
    </div>

    <!-- Charts -->
    <div class="grid md:grid-cols-2 gap-4 mt-6">
      <div class="glass rounded-3xl p-5"><h2 class="font-semibold mb-3">Daily revenue</h2><canvas id="cRev" height="140"></canvas></div>
      <div class="glass rounded-3xl p-5"><h2 class="font-semibold mb-3">Daily parcels</h2><canvas id="cCnt" height="140"></canvas></div>
      <div class="glass rounded-3xl p-5"><h2 class="font-semibold mb-3">Payment methods</h2><canvas id="cPay" height="140"></canvas></div>
      <div class="glass rounded-3xl p-5"><h2 class="font-semibold mb-3">Parcel status</h2><canvas id="cStat" height="140"></canvas></div>
    </div>

    <!-- Top drivers -->
    <div class="glass rounded-3xl p-5 mt-6">
      <h2 class="font-semibold mb-3">Top drivers (<?= esc($range_label) ?>)</h2>
      <table class="w-full text-sm">
        <thead class="text-left text-brand-mist text-xs uppercase tracking-wider">
          <tr><th class="py-2">Driver</th><th class="py-2 text-right">Trips</th><th class="py-2 text-right">Earnings</th><th class="py-2 text-right">Rating</th></tr>
        </thead>
        <tbody>
          <?php foreach ($top_drivers as $d): ?>
          <tr class="border-t border-white/10">
            <td class="py-2"><?= esc($d['full_name'] ?: $d['username']) ?></td>
            <td class="py-2 text-right"><?= (int)$d['trips'] ?></td>
            <td class="py-2 text-right">₱<?= number_format((float)($d['earnings'] ?? 0),2) ?></td>
            <td class="py-2 text-right"><?= (int)$d['rating_count'] > 0 ? '★'.number_format((float)$d['rating_avg'],2).' ('.$d['rating_count'].')' : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<!-- Report picker modal -->
<div id="reportModal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4" style="background:rgba(8,20,52,.85); display:none;">
  <div class="glass-strong rounded-3xl p-6 md:p-8 max-w-lg w-full shadow-glass">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">📄 Generate Report</h2>
      <button onclick="document.getElementById('reportModal').classList.add('hidden');document.getElementById('reportModal').style.display='none'" class="text-brand-mist text-2xl leading-none">×</button>
    </div>
    <p class="text-sm text-brand-mist mt-1">Choose a report type and date range. The <strong>Download PDF</strong> button generates a formal PDF directly from the database — not a screenshot of the page.</p>

    <form method="get" action="<?= url('admin/analytics/report') ?>" target="_blank" class="mt-5 space-y-4">
      <div>
        <label class="label">Report type</label>
        <div class="grid grid-cols-2 gap-2">
          <?php
          $types = [
            'summary' => ['📊', 'Daily Summary', 'Revenue, counts, status mix for the period'],
            'drivers' => ['🚗', 'Driver Performance', 'Per-driver trips, earnings, ratings'],
            'revenue' => ['💰', 'Revenue Breakdown', 'Payment methods + platform vs driver share'],
            'parcels' => ['📦', 'Full Parcel Log', 'Line-by-line transaction ledger'],
          ];
          foreach ($types as $k => [$icon, $name, $desc]): ?>
            <label class="glass rounded-xl p-3 cursor-pointer hover:bg-white/15 transition block">
              <input type="radio" name="type" value="<?= $k ?>" class="sr-only peer" <?= $k==='summary'?'checked':'' ?>>
              <div class="flex items-start gap-2">
                <div class="text-2xl"><?= $icon ?></div>
                <div>
                  <div class="font-semibold text-sm peer-checked:text-brand-sky"><?= esc($name) ?></div>
                  <div class="text-[11px] text-brand-mist"><?= esc($desc) ?></div>
                </div>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <label class="label">Date range preset</label>
        <select id="rangePreset" class="input !py-2 text-sm">
          <option value="custom">Custom (pick below)</option>
          <option value="today">Today</option>
          <option value="yesterday">Yesterday</option>
          <option value="last7">Last 7 days</option>
          <option value="last14" selected>Last 14 days</option>
          <option value="last30">Last 30 days</option>
          <option value="last90">Last 90 days</option>
          <option value="thisMonth">This month</option>
          <option value="lastMonth">Last month</option>
          <option value="thisYear">This year</option>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="label !text-xs">From</label><input id="rptFrom" type="date" name="from" value="<?= esc($from) ?>" class="input !py-2 text-sm" required></div>
        <div><label class="label !text-xs">To</label><input id="rptTo" type="date" name="to" value="<?= esc($to) ?>" class="input !py-2 text-sm" required></div>
      </div>
      <div class="flex gap-2 pt-2">
        <button type="button" onclick="document.getElementById('reportModal').classList.add('hidden');document.getElementById('reportModal').style.display='none'" class="btn btn-ghost flex-1">Cancel</button>
        <button class="btn btn-primary flex-1">Open report ↗</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('reportModal').addEventListener('click', e => {
  if (e.target.id === 'reportModal') { e.target.classList.add('hidden'); e.target.style.display='none'; }
});
// When "hidden" class is used, display:flex; setting matters — override with js toggle.
(function(){
  const m = document.getElementById('reportModal');
  const obs = new MutationObserver(() => { m.style.display = m.classList.contains('hidden') ? 'none' : 'flex'; });
  obs.observe(m, {attributes:true, attributeFilter:['class']});
})();

const rows = <?= json_encode($rows) ?>;
const labels = rows.map(r => r.d);
const rev = rows.map(r => parseFloat(r.rev||0));
const cnt = rows.map(r => parseInt(r.c||0));
const opts = {plugins:{legend:{labels:{color:'#cfe0ff'}}}, scales:{x:{ticks:{color:'#93b5e6'}}, y:{ticks:{color:'#93b5e6'}}}};
new Chart(cRev, {type:'line', data:{labels, datasets:[{label:'₱ revenue', data:rev, borderColor:'#60a5fa', backgroundColor:'rgba(96,165,250,.2)', fill:true, tension:.3}]}, options:opts});
new Chart(cCnt, {type:'bar',  data:{labels, datasets:[{label:'parcels', data:cnt, backgroundColor:'#3b82f6'}]}, options:opts});

const paySplit = <?= json_encode($pay_split) ?>;
new Chart(cPay, {type:'doughnut', data:{labels: paySplit.map(p=>p.payment_method||'?'), datasets:[{data: paySplit.map(p=>p.c), backgroundColor:['#3b82f6','#22c55e','#f59e0b','#ef4444','#a855f7']}]}, options:{plugins:{legend:{labels:{color:'#cfe0ff'}}}}});

const stat = <?= json_encode($status_counts) ?>;
new Chart(cStat, {type:'bar', data:{labels: stat.map(s=>s.status), datasets:[{label:'count', data: stat.map(s=>s.c), backgroundColor:'#60a5fa'}]}, options:opts});

// Date range preset → auto-fills From/To in the report modal.
(function(){
  const sel = document.getElementById('rangePreset');
  const from = document.getElementById('rptFrom');
  const to = document.getElementById('rptTo');
  if (!sel || !from || !to) return;
  const fmt = d => d.toISOString().slice(0,10);
  function apply(v){
    const today = new Date(); today.setHours(0,0,0,0);
    let f = new Date(today), t = new Date(today);
    switch(v){
      case 'today':       break;
      case 'yesterday':   f.setDate(f.getDate()-1); t.setDate(t.getDate()-1); break;
      case 'last7':       f.setDate(f.getDate()-6); break;
      case 'last14':      f.setDate(f.getDate()-13); break;
      case 'last30':      f.setDate(f.getDate()-29); break;
      case 'last90':      f.setDate(f.getDate()-89); break;
      case 'thisMonth':   f = new Date(today.getFullYear(), today.getMonth(), 1); break;
      case 'lastMonth':   f = new Date(today.getFullYear(), today.getMonth()-1, 1);
                          t = new Date(today.getFullYear(), today.getMonth(), 0); break;
      case 'thisYear':    f = new Date(today.getFullYear(), 0, 1); break;
      default: return; // 'custom' — leave inputs as-is
    }
    from.value = fmt(f);
    to.value = fmt(t);
  }
  sel.addEventListener('change', e => apply(e.target.value));
  apply(sel.value); // sync on load
  // If user manually edits From/To, flip back to "Custom"
  [from, to].forEach(el => el.addEventListener('input', () => sel.value = 'custom'));
})();
</script>
</body></html>
