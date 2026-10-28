<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Messages';
$active = 'messages';
$uid = (int)$_SESSION['user_id'];

// Customer contacts — distinct customers this driver has delivered for.
// Prefer the most recent *active* parcel so "Open chat" actually lands in a chattable thread.
$contacts = db()->raw("
    SELECT u.id           AS user_id,
           u.full_name    AS name,
           u.username     AS username,
           latest.id      AS parcel_id,
           latest.tracking_number,
           latest.status,
           (SELECT body        FROM messages WHERE parcel_id = latest.id ORDER BY id DESC LIMIT 1) AS last_body,
           (SELECT created_at  FROM messages WHERE parcel_id = latest.id ORDER BY id DESC LIMIT 1) AS last_at,
           (SELECT sender_role FROM messages WHERE parcel_id = latest.id ORDER BY id DESC LIMIT 1) AS last_role,
           (SELECT COUNT(*) FROM messages m
              JOIN parcels p ON p.id = m.parcel_id
             WHERE p.driver_id = ? AND p.customer_id = u.id
               AND m.is_read = 0 AND m.sender_id <> ?) AS unread
    FROM (
        SELECT p.customer_id,
               COALESCE(
                 (SELECT MAX(p2.id) FROM parcels p2
                   WHERE p2.driver_id = p.driver_id AND p2.customer_id = p.customer_id
                     AND p2.status NOT IN ('Delivered','Cancelled')),
                 MAX(p.id)
               ) AS parcel_id_any
        FROM parcels p
        WHERE p.driver_id = ? AND p.customer_id IS NOT NULL
        GROUP BY p.driver_id, p.customer_id
    ) agg
    JOIN users u ON u.id = agg.customer_id
    JOIN parcels latest ON latest.id = agg.parcel_id_any
    ORDER BY last_at DESC, latest.id DESC
", [$uid, $uid, $uid])->fetchAll();

// Walk-in parcels (admin-dispatched, no customer) that have messages — admin chats.
$admin_threads = db()->raw("
    SELECT p.id, p.tracking_number, p.status,
           (SELECT body       FROM messages WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_body,
           (SELECT created_at FROM messages WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_at,
           (SELECT sender_role FROM messages WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_role,
           (SELECT COUNT(*) FROM messages WHERE parcel_id = p.id AND is_read = 0 AND sender_id <> ?) AS unread
    FROM parcels p
    WHERE p.driver_id = ? AND (p.is_walkin = 1 OR p.customer_id IS NULL)
      AND EXISTS (SELECT 1 FROM messages WHERE parcel_id = p.id)
    ORDER BY last_at DESC
", [$uid, $uid])->fetchAll();

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<main class="mx-auto max-w-md px-4 pt-5 pb-28">
  <a href="<?= url('driver/dashboard') ?>" class="text-sm text-brand-mist">← Back</a>
  <h1 class="text-2xl font-bold mt-2 flex items-center gap-2"><?= icon('messages', 'w-6 h-6') ?> Messages</h1>
  <p class="text-sm text-brand-mist">Chat with customers and Drop IT admin.</p>

  <input id="contactFilter" class="input !py-2 text-sm mt-4" placeholder="Search by name or tracking…">

  <!-- Admin support contact -->
  <section class="mt-4">
    <h2 class="text-sm font-semibold text-brand-mist uppercase tracking-wider">Admin</h2>
    <?php
      $admin_unread = 0;
      foreach ($admin_threads as $t) $admin_unread += (int)$t['unread'];
      $admin_latest_pid = $admin_threads[0]['id'] ?? null;
    ?>
    <a href="<?= $admin_latest_pid ? url('driver/parcel/'.$admin_latest_pid) : url('driver/dashboard') ?>"
       class="mt-2 glass rounded-2xl p-3 flex items-center gap-3 hover:bg-white/15 transition block contact-row"
       data-q="admin dispatch support">
      <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 flex items-center justify-center text-xl shrink-0">🛟</div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between gap-2">
          <span class="font-semibold text-sm">Drop IT Admin</span>
          <?php if ($admin_unread > 0): ?><span class="chip chip-cancelled !text-[10px] !px-1.5 !py-0.5"><?= $admin_unread ?></span><?php endif; ?>
        </div>
        <div class="text-[11px] text-brand-mist truncate">
          <?php if ($admin_latest_pid): ?>
            Walk-in dispatches and admin messages
          <?php else: ?>
            No admin dispatches yet
          <?php endif; ?>
        </div>
      </div>
      <span class="text-brand-sky text-sm">→</span>
    </a>
  </section>

  <!-- Customer contacts -->
  <section class="mt-5">
    <h2 class="text-sm font-semibold text-brand-mist uppercase tracking-wider">Customers</h2>
    <div class="mt-2 space-y-2">
      <?php if (empty($contacts)): ?>
        <div class="glass rounded-2xl p-4 text-center text-sm text-brand-mist">
          No customer contacts yet. They appear after you accept a parcel.
        </div>
      <?php else: foreach ($contacts as $c):
        $initials = strtoupper(mb_substr($c['name'] ?: $c['username'] ?: 'C', 0, 1));
        $is_closed = in_array($c['status'], ['Delivered','Cancelled']);
      ?>
        <a href="<?= url('driver/parcel/'.$c['parcel_id']) ?>"
           class="glass rounded-2xl p-3 flex items-center gap-3 hover:bg-white/15 transition block contact-row"
           data-q="<?= esc(strtolower(($c['name'] ?? '').' '.($c['username'] ?? '').' '.$c['tracking_number'])) ?>">
          <div class="w-11 h-11 rounded-2xl bg-gradient-to-br <?= $is_closed ? 'from-slate-400 to-slate-600' : 'from-brand-azure to-brand-blue' ?> flex items-center justify-center text-lg font-bold shrink-0"><?= esc($initials) ?></div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
              <span class="font-semibold text-sm truncate"><?= esc($c['name'] ?: $c['username']) ?></span>
              <?php if ((int)$c['unread'] > 0 && !$is_closed): ?>
                <span class="chip chip-cancelled !text-[10px] !px-1.5 !py-0.5"><?= (int)$c['unread'] ?></span>
              <?php elseif ($is_closed): ?>
                <span class="chip chip-delivered !text-[10px] !px-1.5 !py-0.5">🔒 closed</span>
              <?php endif; ?>
            </div>
            <div class="flex items-center gap-1 mt-0.5">
              <span class="font-mono text-[10px] text-brand-mist truncate"><?= esc($c['tracking_number']) ?></span>
              <?= status_chip($c['status']) ?>
            </div>
            <div class="text-[11px] text-brand-mist mt-0.5 truncate">
              <?php if ($c['last_body']): ?>
                <?= esc($c['last_role']) ?>: <?= esc(mb_substr($c['last_body'], 0, 60)) ?>
              <?php else: ?>
                <span class="italic opacity-70">No messages yet</span>
              <?php endif; ?>
            </div>
            <?php if (!$is_closed): ?>
              <div class="mt-1 text-[11px] text-brand-sky font-medium">💬 Tap to open chat →</div>
            <?php else: ?>
              <div class="mt-1 text-[11px] text-brand-mist/70">Chat closed — tap to view history</div>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </section>
</main>
<script>
document.getElementById('contactFilter').addEventListener('input', (e) => {
  const q = e.target.value.toLowerCase().trim();
  document.querySelectorAll('.contact-row').forEach(r => {
    r.style.display = (!q || (r.dataset.q || '').includes(q)) ? '' : 'none';
  });
});
</script>
<?php include __DIR__ . '/../partials/mobile_nav.php'; ?>
</body></html>
