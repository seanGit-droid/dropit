<?php defined('APP_ROOT') OR exit('No direct script access allowed');
/**
 * Floating chat bubble for customer ↔ driver ↔ admin, per-parcel (incl. "SUPPORT-…" threads).
 * Required in-scope vars:
 *   $chat_parcel_id   (int)
 *   $chat_peer_name   (string)
 *   $chat_self_role   ('customer' | 'driver' | 'admin')
 * Optional:
 *   $chat_peer_user_id (int)  — enables the 📞 call button in the chat header
 *   $chat_closed       (bool) — set true when parcel is Delivered/Cancelled. Hides bubble + disables composer.
 *   $chat_closed_reason(str)  — optional label shown in the composer area, e.g. "Parcel delivered"
 */
$_cp = (int)($chat_parcel_id ?? 0);
if ($_cp <= 0) return;
$_peer = $chat_peer_name ?? 'Support';
$_peer_uid = (int)($chat_peer_user_id ?? 0);
$_closed = !empty($chat_closed);
$_closed_reason = (string)($chat_closed_reason ?? 'Chat closed');

// Hide the separate floating call bubble since the chat has one built-in.
$GLOBALS['__dropit_hide_floating_call_btn'] = true;

// When chat is closed: don't render the bubble at all.
if ($_closed):
?>
<div class="fixed bottom-24 right-4 z-50">
  <div class="glass rounded-full px-3 py-2 text-xs text-brand-mist flex items-center gap-2 shadow-glass opacity-80">
    🔒 <?= esc($_closed_reason) ?>
  </div>
</div>
<?php return; endif; ?>

<div id="chatBubble" style="position:fixed;bottom:calc(5rem + env(safe-area-inset-bottom,0px));right:1rem;z-index:9999;">
  <button id="chatOpen" class="w-14 h-14 rounded-full shadow-glow flex items-center justify-center relative" style="background:linear-gradient(135deg,#3b82f6,#1e40af);color:#fff;">
    <?= icon('messages', 'w-7 h-7 dropit-icon-active') ?>
    <span id="chatBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1.5 py-0.5 hidden">0</span>
  </button>
</div>
<div id="chatPanel" class="fixed bottom-4 right-4 z-50 w-80 max-w-[94vw] h-[60vh] max-h-[520px] rounded-3xl shadow-glass hidden flex-col overflow-hidden" style="background:rgba(11,30,63,0.92); backdrop-filter: blur(28px) saturate(180%); -webkit-backdrop-filter: blur(28px) saturate(180%); color:#fff; border:1px solid rgba(255,255,255,0.18);">
  <div class="flex items-center justify-between px-4 py-3 gap-2" style="background:rgba(255,255,255,0.08); border-bottom:1px solid rgba(255,255,255,0.12);">
    <div class="min-w-0">
      <div class="text-[11px] opacity-75">Parcel chat</div>
      <div class="font-semibold text-sm truncate"><?= esc($_peer) ?></div>
    </div>
    <div class="flex items-center gap-1 shrink-0">
      <?php if ($_peer_uid > 0): ?>
        <button id="chatCallBtn" title="Voice call" class="w-8 h-8 rounded-full flex items-center justify-center hover:scale-110 transition" style="background:linear-gradient(135deg,#22c55e,#15803d);color:#fff;"><?= icon('call', 'w-4 h-4 dropit-icon-active') ?></button>
      <?php endif; ?>
      <button id="chatClose" class="opacity-75 hover:opacity-100 text-xl leading-none w-8 h-8">×</button>
    </div>
  </div>
  <div id="chatBody" class="flex-1 overflow-auto p-3 space-y-2 scrollbar-thin"></div>
  <form id="chatForm" class="p-3 flex gap-2" style="background:rgba(255,255,255,0.04); border-top:1px solid rgba(255,255,255,0.1);">
    <input id="chatInput" class="input !py-2 text-sm flex-1" placeholder="Type a message..." maxlength="500" required>
    <button class="btn btn-primary !py-2 !px-3 text-sm">→</button>
  </form>
</div>
<script>
(function(){
  const PID = <?= $_cp ?>;
  const SELF_ROLE = <?= json_encode($chat_self_role ?? '') ?>;
  const PEER_UID = <?= $_peer_uid ?>;
  const PEER_NAME = <?= json_encode($_peer) ?>;
  const panel = document.getElementById('chatPanel');
  const body  = document.getElementById('chatBody');
  const badge = document.getElementById('chatBadge');
  const open  = document.getElementById('chatOpen');
  const close = document.getElementById('chatClose');
  const form  = document.getElementById('chatForm');
  const input = document.getElementById('chatInput');
  const callBtn = document.getElementById('chatCallBtn');

  let lastId = 0;
  let unread = 0;
  let firstLoad = true;
  const bubbleEl = document.getElementById('chatBubble');

  function showBubble(){ bubbleEl.classList.remove('hidden'); }
  function hideBubble(){ bubbleEl.classList.add('hidden'); }

  // Bubble is visible by default on active chats, so users can always initiate.
  showBubble();

  function bubble(m){
    const mine = (SELF_ROLE === m.sender_role);
    const wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;' + (mine?'justify-content:flex-end;':'');
    const b = document.createElement('div');
    b.style.cssText = 'max-width:80%;padding:8px 12px;border-radius:16px;font-size:13px;line-height:1.35;' +
      (mine ? 'background:linear-gradient(135deg,#3b82f6,#1e40af);color:#fff;border-bottom-right-radius:4px;' :
              'background:rgba(255,255,255,0.12);color:#fff;border-bottom-left-radius:4px;');
    const who = document.createElement('div');
    who.style.cssText = 'font-size:10px;opacity:0.7;margin-bottom:2px;';
    who.textContent = (mine ? 'You' : (m.sender_name || m.sender_role)) + ' · ' + (m.created_at ? new Date(m.created_at.replace(' ','T')).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) : '');
    b.appendChild(who);
    const t = document.createElement('div'); t.textContent = m.body; b.appendChild(t);
    wrap.appendChild(b);
    body.appendChild(wrap);
  }

  async function poll(){
    try {
      const r = await fetch(`<?= url('api/parcel/') ?>${PID}/messages?since=${lastId}`, {credentials:'same-origin'});
      const d = await r.json();
      if (!d.ok || !d.items || d.items.length === 0) return;
      const wasOpen = !panel.classList.contains('hidden');
      for (const m of d.items){
        bubble(m);
        lastId = Math.max(lastId, m.id);
        if (!firstLoad && !wasOpen && m.sender_role !== SELF_ROLE){
          unread++;
          if (window.DropIT) { DropIT.sound.message(); }
        }
      }
      body.scrollTop = body.scrollHeight;
      firstLoad = false;
      if (unread > 0){
        badge.textContent = unread;
        badge.classList.remove('hidden');
      }
    } catch(e){}
  }

  function clearBadge(){
    unread = 0;
    badge.classList.add('hidden');
    if (window.DropIT && DropIT.refreshMsgBadge) DropIT.refreshMsgBadge();
  }

  open.onclick = () => {
    panel.classList.remove('hidden');
    panel.style.display = 'flex';
    hideBubble();
    clearBadge();
    body.scrollTop = body.scrollHeight;
    input.focus();
  };
  close.onclick = () => {
    panel.classList.add('hidden');
    panel.style.display = '';
    showBubble();
  };

  if (callBtn) callBtn.onclick = () => {
    if (window.dropItVoiceCall) window.dropItVoiceCall.callUser(PEER_UID, PEER_NAME);
  };

  form.onsubmit = async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    const d = await DropIT.post(`<?= url('api/parcel/') ?>${PID}/messages`, {body: text});
    if (!d.ok){ DropIT.toast('Send failed', 'error'); return; }
    poll();
  };

  poll();
  setInterval(poll, 6000);
})();
</script>
