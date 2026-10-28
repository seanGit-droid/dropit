<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Messages';
$active = 'messages';
$uid = (int)$_SESSION['user_id'];
$selected_pid = (int)($_GET['parcel'] ?? 0);
$peer_uid = (int)($_GET['peer'] ?? 0);  // filter by contact
$view = $_GET['view'] ?? 'contacts';    // 'contacts' | 'threads'

// Contacts = all drivers + all customers. Annotate with their most recent parcel + last msg.
$contacts = db()->raw("
    SELECT u.id AS user_id, u.role, u.full_name, u.username, u.phone, u.avatar, u.availability,
           lastp.id AS parcel_id,
           lastp.tracking_number,
           lastp.status,
           (SELECT body       FROM messages WHERE parcel_id = lastp.id ORDER BY id DESC LIMIT 1) AS last_body,
           (SELECT created_at FROM messages WHERE parcel_id = lastp.id ORDER BY id DESC LIMIT 1) AS last_at,
           (SELECT COUNT(*) FROM messages m
              JOIN parcels p ON p.id = m.parcel_id
             WHERE ((u.role='driver'   AND p.driver_id   = u.id)
                 OR (u.role='customer' AND p.customer_id = u.id))
               AND m.is_read = 0 AND m.sender_id <> ?) AS unread
    FROM users u
    LEFT JOIN parcels lastp ON lastp.id = (
        SELECT MAX(pp.id) FROM parcels pp
         WHERE (u.role = 'driver'   AND pp.driver_id   = u.id)
            OR (u.role = 'customer' AND pp.customer_id = u.id)
    )
    WHERE u.role IN ('driver','customer') AND u.is_active = 1
    ORDER BY (last_at IS NULL), last_at DESC, u.full_name ASC
    LIMIT 300
", [$uid])->fetchAll();

// If peer_uid is set, load that peer's threads (parcels). Otherwise top 40 recent threads overall.
if ($peer_uid > 0) {
    $threads = db()->raw("
        SELECT p.id, p.tracking_number, p.is_walkin, p.status, p.customer_id, p.driver_id,
               m.body AS last_body, m.created_at AS last_at, m.sender_role AS last_role,
               (SELECT COUNT(*) FROM messages mm WHERE mm.parcel_id = p.id AND mm.is_read = 0 AND mm.sender_id <> ?) AS unread,
               (SELECT u.full_name FROM users u WHERE u.id = p.driver_id) AS driver_name,
               (SELECT u.full_name FROM users u WHERE u.id = p.customer_id) AS customer_name
        FROM parcels p
        LEFT JOIN messages m ON m.id = (SELECT MAX(id) FROM messages WHERE parcel_id = p.id)
        WHERE (p.driver_id = ? OR p.customer_id = ?)
        ORDER BY (m.id IS NULL), m.id DESC, p.id DESC
        LIMIT 60
    ", [$uid, $peer_uid, $peer_uid])->fetchAll();
} else {
    $threads = db()->raw("
        SELECT p.id, p.tracking_number, p.is_walkin, p.status, p.customer_id, p.driver_id,
               m.body AS last_body, m.created_at AS last_at, m.sender_role AS last_role,
               (SELECT COUNT(*) FROM messages mm WHERE mm.parcel_id = p.id AND mm.is_read = 0 AND mm.sender_id <> ?) AS unread,
               (SELECT u.full_name FROM users u WHERE u.id = p.driver_id) AS driver_name,
               (SELECT u.full_name FROM users u WHERE u.id = p.customer_id) AS customer_name
        FROM parcels p
        INNER JOIN messages m ON m.id = (SELECT MAX(id) FROM messages WHERE parcel_id = p.id)
        ORDER BY m.id DESC
        LIMIT 40
    ", [$uid])->fetchAll();
}

$selected = null;
$sel_driver = null; $sel_customer = null;
if ($selected_pid > 0) {
    $selected = db()->table('parcels')
        ->select('id, tracking_number, status, customer_id, driver_id, is_walkin, pickup_address, recipient_address')
        ->where('id', $selected_pid)->get();
    if ($selected && $selected['driver_id']) {
        $sel_driver = db()->table('users')->select('id, full_name, phone, availability')->where('id',$selected['driver_id'])->get();
    }
    if ($selected && $selected['customer_id']) {
        $sel_customer = db()->table('users')->select('id, full_name, phone')->where('id',$selected['customer_id'])->get();
    }
}

$peer_user = null;
if ($peer_uid > 0) {
    $peer_user = db()->table('users')->select('id, full_name, username, role')->where('id',$peer_uid)->get();
}

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/live.php';
?>
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold">💬 Messages</h1>
        <p class="text-sm text-brand-mist">Contacts and parcel chats — drivers, customers, walk-in admin chats.</p>
      </div>
    </div>

    <div class="grid lg:grid-cols-[360px_1fr] gap-4" style="height: calc(100vh - 140px); min-height: 480px;">
      <!-- Left rail -->
      <aside class="glass-strong rounded-3xl p-3 shadow-glass overflow-hidden flex flex-col">
        <!-- Tabs -->
        <div class="flex gap-1 p-1 rounded-xl bg-white/5 mb-2">
          <a href="?view=contacts<?= $selected_pid ? '&parcel='.$selected_pid : '' ?>" class="flex-1 text-center text-xs py-2 rounded-lg <?= $view==='contacts' ? 'bg-white/20 font-semibold' : 'text-brand-mist hover:bg-white/10' ?>">👥 Contacts</a>
          <a href="?view=threads<?= $selected_pid ? '&parcel='.$selected_pid : '' ?>" class="flex-1 text-center text-xs py-2 rounded-lg <?= $view==='threads' ? 'bg-white/20 font-semibold' : 'text-brand-mist hover:bg-white/10' ?>">📨 Threads</a>
        </div>

        <input id="paneFilter" class="input !py-2 text-sm mb-2" placeholder="Search…">

        <div id="paneList" class="flex-1 overflow-auto scrollbar-thin space-y-1 pr-1">
          <?php if ($view === 'contacts'): ?>
            <?php if ($peer_user): ?>
              <a href="?view=contacts" class="block text-xs text-brand-mist hover:text-white px-2 py-1.5">← All contacts</a>
              <div class="text-xs font-semibold px-2 py-1.5 glass rounded-xl mb-2">
                <?= esc($peer_user['full_name'] ?: $peer_user['username']) ?>
                <span class="chip chip-transit !text-[9px] !ml-1"><?= esc($peer_user['role']) ?></span>
              </div>
              <?php foreach ($threads as $t):
                $who = $peer_user['role'] === 'driver' ? ($t['customer_name'] ?: 'Walk-in') : ($t['driver_name'] ?: '(unassigned)');
                $active_class = ($selected_pid == $t['id']) ? 'bg-white/15 ring-1 ring-brand-sky/40' : 'hover:bg-white/10';
              ?>
                <a href="?view=contacts&peer=<?= $peer_uid ?>&parcel=<?= (int)$t['id'] ?>" class="thread-row block rounded-xl p-3 transition <?= $active_class ?>" data-q="<?= esc(strtolower($t['tracking_number'].' '.$who)) ?>">
                  <div class="flex items-center justify-between gap-2">
                    <span class="font-mono text-xs"><?= esc($t['tracking_number']) ?></span>
                    <div class="flex items-center gap-1">
                      <?php if ((int)$t['unread'] > 0): ?><span class="chip chip-cancelled !text-[10px] !px-1.5 !py-0.5"><?= (int)$t['unread'] ?></span><?php endif; ?>
                      <?= status_chip($t['status']) ?>
                    </div>
                  </div>
                  <div class="text-sm mt-1 truncate">↔ <?= esc($who) ?></div>
                  <?php if ($t['last_body']): ?>
                    <div class="text-xs text-brand-mist mt-1 truncate"><?= esc($t['last_role']) ?>: <?= esc(mb_substr($t['last_body'],0,80)) ?></div>
                    <div class="text-[10px] text-brand-mist mt-0.5"><?= date('M j, g:ia', strtotime($t['last_at'])) ?></div>
                  <?php else: ?>
                    <div class="text-[11px] text-brand-mist/70 italic mt-1">No messages on this parcel yet.</div>
                  <?php endif; ?>
                </a>
              <?php endforeach; ?>
            <?php else: ?>
              <?php
                $drivers_ct = $customers_ct = 0;
                foreach ($contacts as $c) {
                  if ($c['role'] === 'driver') $drivers_ct++; else $customers_ct++;
                }
              ?>
              <?php if (empty($contacts)): ?>
                <div class="text-center py-8 text-brand-mist text-sm">Walang contacts pa.</div>
              <?php else: ?>
                <?php
                  $shown_header = null;
                  foreach ($contacts as $c):
                    $role = $c['role'];
                    if ($shown_header !== $role) {
                      $shown_header = $role;
                      $label = $role === 'driver' ? '🚚 Drivers' : '👤 Customers';
                      $count = $role === 'driver' ? $drivers_ct : $customers_ct;
                      echo '<div class="text-[11px] font-semibold text-brand-mist uppercase tracking-wider px-2 py-2 mt-1">'.$label.' · '.$count.'</div>';
                    }
                    $initials = strtoupper(mb_substr($c['full_name'] ?: $c['username'] ?: '?', 0, 1));
                    $is_on = ($c['availability'] ?? '') === 'online';
                ?>
                  <a href="?view=contacts&peer=<?= (int)$c['user_id'] ?><?= $c['parcel_id'] ? '&parcel='.(int)$c['parcel_id'] : '' ?>"
                     class="thread-row block rounded-xl p-2.5 transition hover:bg-white/10"
                     data-q="<?= esc(strtolower(($c['full_name'] ?? '').' '.($c['username'] ?? '').' '.($c['tracking_number'] ?? ''))) ?>">
                    <div class="flex items-center gap-2.5">
                      <div class="relative shrink-0">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-azure to-brand-blue flex items-center justify-center text-sm font-bold"><?= esc($initials) ?></div>
                        <?php if ($role==='driver' && $is_on): ?><span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-[#0b1e3f]"></span><?php endif; ?>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                          <span class="font-semibold text-sm truncate"><?= esc($c['full_name'] ?: $c['username']) ?></span>
                          <?php if ((int)$c['unread'] > 0): ?><span class="chip chip-cancelled !text-[10px] !px-1.5 !py-0.5"><?= (int)$c['unread'] ?></span><?php endif; ?>
                        </div>
                        <div class="text-[11px] text-brand-mist truncate">
                          <?php if ($c['last_body']): ?>
                            <?= esc(mb_substr($c['last_body'], 0, 50)) ?>
                          <?php elseif ($c['tracking_number']): ?>
                            <?= esc($c['tracking_number']) ?>
                          <?php else: ?>
                            <span class="italic opacity-70">No parcels yet</span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php endif; ?>
          <?php else: ?>
            <?php if (empty($threads)): ?>
              <div class="text-center py-8 text-brand-mist text-sm">Walang messages pa.</div>
            <?php else: foreach ($threads as $t):
              $who = ($t['is_walkin'] || !$t['customer_id']) ? ('Walk-in → '.($t['driver_name'] ?: '(unassigned)')) : (($t['customer_name'] ?: '?') . ' ↔ ' . ($t['driver_name'] ?: '(unassigned)'));
              $active_class = ($selected_pid == $t['id']) ? 'bg-white/15 ring-1 ring-brand-sky/40' : 'hover:bg-white/10';
            ?>
              <a href="?view=threads&parcel=<?= (int)$t['id'] ?>" class="thread-row block rounded-xl p-3 transition <?= $active_class ?>" data-q="<?= esc(strtolower($t['tracking_number'].' '.$who)) ?>">
                <div class="flex items-center justify-between gap-2">
                  <span class="font-mono text-xs"><?= esc($t['tracking_number']) ?></span>
                  <div class="flex items-center gap-1">
                    <?php if ((int)$t['unread'] > 0): ?><span class="chip chip-cancelled !text-[10px] !px-1.5 !py-0.5"><?= (int)$t['unread'] ?></span><?php endif; ?>
                    <?= status_chip($t['status']) ?>
                  </div>
                </div>
                <div class="text-sm mt-1 truncate"><?= esc($who) ?></div>
                <div class="text-xs text-brand-mist mt-1 truncate"><?= esc($t['last_role']) ?>: <?= esc(mb_substr($t['last_body'] ?? '',0,80)) ?></div>
                <div class="text-[10px] text-brand-mist mt-0.5"><?= $t['last_at'] ? date('M j, g:ia', strtotime($t['last_at'])) : '' ?></div>
              </a>
            <?php endforeach; endif; ?>
          <?php endif; ?>
        </div>
      </aside>

      <!-- Conversation pane -->
      <section class="glass-strong rounded-3xl shadow-glass flex flex-col overflow-hidden">
        <?php if (!$selected): ?>
          <div class="flex-1 flex items-center justify-center p-10 text-center">
            <div>
              <div class="text-6xl">💬</div>
              <div class="mt-3 text-lg font-semibold">Pick a contact or thread</div>
              <div class="text-sm text-brand-mist mt-1">Messages between drivers, customers, and admin appear here.</div>
            </div>
          </div>
        <?php else: ?>
          <!-- Conversation header -->
          <div class="flex items-center justify-between px-5 py-3 border-b border-white/10">
            <div>
              <div class="flex items-center gap-2">
                <span class="font-mono text-sm font-bold"><?= esc($selected['tracking_number']) ?></span>
                <?= status_chip($selected['status']) ?>
                <?php if (!empty($selected['is_walkin'])): ?><span class="chip chip-pending !text-[10px]">walk-in</span><?php endif; ?>
              </div>
              <div class="text-xs text-brand-mist mt-0.5">
                <?php if ($sel_driver): ?>🚚 <?= esc($sel_driver['full_name']) ?><?php endif; ?>
                <?php if ($sel_customer): ?> · 👤 <?= esc($sel_customer['full_name']) ?><?php endif; ?>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <?php if ($sel_driver): ?>
                <button type="button" onclick="window.dropItVoiceCall && window.dropItVoiceCall.callUser(<?= (int)$sel_driver['id'] ?>, <?= json_encode($sel_driver['full_name']) ?>)" class="btn btn-success !py-1.5 !px-3 text-xs">🎙️ Call driver</button>
              <?php endif; ?>
              <?php if ($sel_customer): ?>
                <button type="button" onclick="window.dropItVoiceCall && window.dropItVoiceCall.callUser(<?= (int)$sel_customer['id'] ?>, <?= json_encode($sel_customer['full_name']) ?>)" class="btn btn-ghost !py-1.5 !px-3 text-xs">🎙️ Call customer</button>
              <?php endif; ?>
              <a href="<?= url('admin/parcel/'.$selected['id']) ?>" class="btn btn-ghost !py-1.5 !px-3 text-xs">Open parcel →</a>
            </div>
          </div>

          <!-- Messages -->
          <div id="chatBody" class="flex-1 overflow-auto scrollbar-thin p-5 space-y-2"></div>

          <!-- Composer -->
          <form id="chatForm" class="p-3 border-t border-white/10 flex gap-2 items-end">
            <textarea id="chatInput" class="input !py-2 text-sm flex-1 resize-none" rows="2" placeholder="Type message…" maxlength="500" required></textarea>
            <button class="btn btn-primary !py-2 text-sm">Send →</button>
          </form>
        <?php endif; ?>
      </section>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../partials/voice_call.php'; ?>

<?php if ($selected): ?>
<script>
(function(){
  const PID = <?= (int)$selected['id'] ?>;
  const SELF_ROLE = 'admin';
  const body = document.getElementById('chatBody');
  const form = document.getElementById('chatForm');
  const input = document.getElementById('chatInput');
  let lastId = 0;

  function bubble(m){
    const mine = (SELF_ROLE === m.sender_role);
    const wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;' + (mine?'justify-content:flex-end;':'');
    const b = document.createElement('div');
    b.style.cssText = 'max-width:75%;padding:10px 14px;border-radius:16px;font-size:13px;line-height:1.4;' +
      (mine ? 'background:linear-gradient(135deg,#3b82f6,#1e40af);color:#fff;border-bottom-right-radius:4px;'
            : 'background:rgba(255,255,255,0.12);color:#fff;border-bottom-left-radius:4px;');
    const who = document.createElement('div');
    who.style.cssText = 'font-size:10px;opacity:0.7;margin-bottom:2px;';
    const when = m.created_at ? new Date(m.created_at.replace(' ','T')).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) : '';
    who.textContent = (mine ? 'You' : (m.sender_name || m.sender_role)) + ' · ' + when;
    b.appendChild(who);
    const t = document.createElement('div'); t.textContent = m.body; b.appendChild(t);
    wrap.appendChild(b);
    body.appendChild(wrap);
  }
  async function poll(){
    try {
      const r = await fetch(`<?= url('api/parcel/') ?>${PID}/messages?since=${lastId}`, {credentials:'same-origin'});
      const d = await r.json();
      if (!d.ok || !d.items) return;
      for (const m of d.items){ bubble(m); lastId = Math.max(lastId, m.id); }
      body.scrollTop = body.scrollHeight;
    } catch(e){}
  }
  form.onsubmit = async (e) => {
    e.preventDefault();
    const text = input.value.trim(); if (!text) return;
    input.value = '';
    const d = await DropIT.post(`<?= url('api/parcel/') ?>${PID}/messages`, {body: text});
    if (!d.ok){ DropIT.toast('Send failed', 'error'); return; }
    poll();
  };
  input.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey){ e.preventDefault(); form.requestSubmit(); } });
  poll(); setInterval(poll, 5000);
})();
</script>
<?php endif; ?>

<script>
document.getElementById('paneFilter').addEventListener('input', (e) => {
  const q = e.target.value.toLowerCase().trim();
  document.querySelectorAll('.thread-row').forEach(r => {
    r.style.display = (!q || (r.dataset.q || '').includes(q)) ? '' : 'none';
  });
});
</script>
</body></html>
