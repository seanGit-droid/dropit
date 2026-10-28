<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Reports';
$active = 'reports';

$reports = db()->table('reports')
    ->select('id, user_id, subject, message, status, created_at')
    ->order_by('created_at','DESC')->get_all();

// pull reporter names
$ids = array_filter(array_unique(array_column($reports,'user_id')));
$names = [];
if ($ids) {
    $rows = db()->table('users')->select('id, username, full_name, role')->in('id', $ids)->get_all();
    foreach ($rows as $r) $names[$r['id']] = $r;
}

include __DIR__ . '/../partials/head.php';
?>
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-8">
    <h1 class="text-2xl md:text-3xl font-bold">User Reports</h1>
    <p class="text-sm text-brand-mist">Feedback and issues submitted by users.</p>

    <div class="mt-4"><?php include __DIR__ . '/../partials/flash.php'; ?></div>

    <div class="mt-4 space-y-3">
      <?php if (empty($reports)): ?>
        <div class="glass rounded-3xl p-10 text-center text-brand-mist">No reports submitted yet.</div>
      <?php else: foreach ($reports as $r):
        $u = $names[$r['user_id']] ?? null; ?>
        <div class="glass rounded-2xl p-5 shadow-glass">
          <div class="flex items-center justify-between flex-wrap gap-2">
            <div>
              <div class="font-semibold"><?= esc($r['subject']) ?></div>
              <div class="text-xs text-brand-mist">
                From: <?= $u ? esc($u['full_name'] ?: $u['username']).' ('.esc($u['role']).')' : 'Unknown' ?>
                - <?= date('M j, Y g:ia', strtotime($r['created_at'])) ?>
              </div>
            </div>
            <form method="post" action="<?= url('admin/reports/status/'.$r['id']) ?>" class="flex gap-2">
              <?php (new Router())->csrf_field(); ?>
              <select name="status" class="input !py-2 !text-xs">
                <?php foreach (['Open','In Review','Resolved','Closed'] as $s): ?>
                  <option <?= $r['status']===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-primary !py-2 !px-3 text-xs">Update</button>
            </form>
          </div>
          <p class="mt-3 text-sm text-brand-mist whitespace-pre-wrap"><?= esc($r['message']) ?></p>
          <div class="mt-2"><?= status_chip($r['status']) ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </main>
</div>
</body></html>
