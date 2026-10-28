<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Notification Log';
$active = 'notifications';

$filter = $_GET['channel'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$q = db()->table('notification_log')
    ->select('id, channel, recipient, subject, body, status, response, created_at')
    ->where('status', '!=', 'disabled')
    ->where('status', '!=', 'dry_run');
if (in_array($filter, ['email','in_app'])) $q = $q->where('channel', $filter);
if ($search !== '') $q = $q->like('recipient', '%'.$search.'%');

$logs = $q->order_by('created_at', 'DESC')->limit(200)->get_all();

$counts = db()->raw("SELECT channel, COUNT(*) c FROM notification_log WHERE channel IN ('email','in_app') GROUP BY channel")->fetchAll();
$count_by = ['email'=>0, 'in_app'=>0];
foreach ($counts as $c) if (isset($count_by[$c['channel']])) $count_by[$c['channel']] = (int)$c['c'];
$count_by['all'] = array_sum($count_by);

include __DIR__ . '/../partials/head.php';
?>
<style>
.notif-card { transition: transform .18s ease, box-shadow .18s ease; }
.notif-card:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(8,20,52,.5); }
.tab-pill { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:99px; font-size:.8rem; font-weight:600; cursor:pointer; transition:all .15s; text-decoration:none; }
.tab-pill.active { background:linear-gradient(135deg,#3b82f6,#1e40af); color:#fff; box-shadow:0 4px 14px rgba(59,130,246,.4); }
.tab-pill:not(.active) { background:rgba(255,255,255,.07); color:#bfdbfe; border:1px solid rgba(255,255,255,.12); }
.tab-pill:not(.active):hover { background:rgba(255,255,255,.13); color:#fff; }
.count-badge { font-size:10px; font-weight:700; padding:1px 7px; border-radius:99px; background:rgba(255,255,255,.18); }
.tab-pill.active .count-badge { background:rgba(255,255,255,.25); }
.chan-badge-email  { background:rgba(99,102,241,.2); color:#a5b4fc; border:1px solid rgba(99,102,241,.3); }
.chan-badge-in_app { background:rgba(59,130,246,.2); color:#93c5fd; border:1px solid rgba(59,130,246,.3); }
.timeline-line { position:absolute; left:19px; top:44px; bottom:0; width:2px; background:rgba(255,255,255,.06); border-radius:1px; }
</style>
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-8 overflow-x-hidden">

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold flex items-center gap-3">
          <?= icon('bell', 'w-7 h-7') ?>
          Notification Log
        </h1>
        <p class="text-sm text-brand-mist mt-1">Every email and in-app attempt Drop IT has sent.</p>
      </div>
      <!-- Search -->
      <form method="get" class="flex gap-2">
        <input type="hidden" name="channel" value="<?= esc($filter) ?>">
        <input name="q" value="<?= esc($search) ?>" class="input !py-2 text-sm w-44" placeholder="Search recipient…">
        <button class="btn btn-primary !py-2 !px-4 text-sm"><?= icon('magnifying','w-4 h-4 dropit-icon-active') ?> Search</button>
      </form>
    </div>

    <!-- Stats bar -->
    <div class="grid grid-cols-3 gap-3 mb-6">
      <?php
        $statItems = [
          ['All',    $count_by['all'],            'bell',    'from-brand-azure to-brand-blue'],
          ['In-app', $count_by['in_app'],          'inbox',   'from-indigo-500 to-indigo-700'],
          ['Email',  $count_by['email'],            'profile', 'from-violet-500 to-violet-700'],
        ];
        foreach ($statItems as [$label,$val,$ico,$grad]):
      ?>
      <div class="glass-strong rounded-2xl p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-gradient-to-br <?= $grad ?>">
          <?= icon($ico, 'w-5 h-5 dropit-icon-active') ?>
        </div>
        <div>
          <div class="text-xl font-bold"><?= number_format($val) ?></div>
          <div class="text-xs text-brand-mist"><?= $label ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Channel tabs -->
    <div class="flex flex-wrap gap-2 mb-5">
      <?php foreach (['all'=>['All','🔔'], 'in_app'=>['In-app','📥'], 'email'=>['Email','✉️']] as $k=>$v): ?>
        <a href="?channel=<?= $k ?><?= $search ? '&q='.urlencode($search) : '' ?>"
           class="tab-pill <?= $filter===$k ? 'active' : '' ?>">
          <span><?= $v[1] ?></span>
          <span><?= $v[0] ?></span>
          <span class="count-badge"><?= number_format($count_by[$k] ?? 0) ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Log list -->
    <?php if (empty($logs)): ?>
      <div class="glass-strong rounded-3xl p-14 text-center">
        <div class="text-6xl mb-4">🔕</div>
        <div class="font-semibold text-lg">Nothing here yet</div>
        <div class="text-sm text-brand-mist mt-1">Notifications will appear here as they are sent.</div>
      </div>
    <?php else: ?>
    <div class="space-y-2">
      <?php foreach ($logs as $l):
        $statusClass = match ($l['status']) {
          'sent'    => 'chip-delivered',
          'failed'  => 'chip-cancelled',
          'dry_run' => 'chip-pending',
          'disabled'=> 'chip-cancelled',
          default   => 'chip-transit',
        };
        $isEmail = $l['channel'] === 'email';
        $chanLabel = $isEmail ? 'Email' : 'In-app';
        $chanClass = $isEmail ? 'chan-badge-email' : 'chan-badge-in_app';
        $chanIcon  = $isEmail ? '✉️' : '📥';
        $isFailed  = $l['status'] === 'failed';
      ?>
      <div class="notif-card glass rounded-2xl p-4 flex items-start gap-3">
        <!-- Icon -->
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0 <?= $isFailed ? 'bg-red-500/15' : 'bg-white/08' ?>" style="background:<?= $isFailed ? 'rgba(239,68,68,.15)' : 'rgba(255,255,255,.08)' ?>">
          <?= $chanIcon ?>
        </div>
        <!-- Content -->
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-2 flex-wrap">
            <div class="flex items-center flex-wrap gap-1.5 min-w-0">
              <span class="font-semibold text-sm truncate max-w-xs"><?= esc($l['subject']) ?: '(no subject)' ?></span>
              <span class="chip <?= $statusClass ?> !text-[10px] !px-2"><?= esc($l['status']) ?></span>
              <span class="chip !text-[10px] !px-2 <?= $chanClass ?>"><?= $chanLabel ?></span>
            </div>
            <span class="text-[11px] text-brand-mist whitespace-nowrap shrink-0"><?= date('M j, g:ia', strtotime($l['created_at'])) ?></span>
          </div>
          <div class="text-xs text-brand-mist mt-1 font-mono truncate"><?= esc($l['recipient']) ?></div>
          <?php if ($l['body']): ?>
          <div class="text-xs mt-1.5 text-white/70 line-clamp-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?= esc($l['body']) ?></div>
          <?php endif; ?>
          <?php if ($l['response']): ?>
          <div class="flex items-center gap-1 mt-1.5 text-[11px] text-red-300/80">
            <span>⚠</span><span><?= esc($l['response']) ?></span>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="text-xs text-brand-mist text-center mt-5">Showing <?= count($logs) ?> most recent entries.</p>
    <?php endif; ?>

  </main>
</div>
</body></html>
