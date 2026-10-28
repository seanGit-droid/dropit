<?php defined('APP_ROOT') OR exit('No direct script access allowed'); ?>
<script>
// Drop IT live helper — partial refresh, toast, sound, notif poll.
window.DropIT = (function(){
  const csrf = <?= json_encode(csrf_token()) ?>;

  // Simple Web Audio beeps — no external files needed.
  let ac = null;
  function ctx(){ ac = ac || new (window.AudioContext || window.webkitAudioContext)(); return ac; }
  function beep(freq, dur, vol){
    try {
      const c = ctx(), o = c.createOscillator(), g = c.createGain();
      o.type = 'sine'; o.frequency.value = freq;
      g.gain.value = vol ?? 0.15;
      o.connect(g); g.connect(c.destination);
      const t = c.currentTime;
      o.start(t); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
      o.stop(t + dur);
    } catch(e){}
  }
  const sound = {
    notif:    () => { beep(880, 0.12, 0.18); setTimeout(()=>beep(1175, 0.15, 0.18), 110); },
    dispatch: () => { beep(523, 0.1, 0.22); setTimeout(()=>beep(659, 0.1, 0.22), 100); setTimeout(()=>beep(880, 0.2, 0.22), 200); },
    message:  () => { beep(660, 0.08, 0.16); },
    error:    () => { beep(200, 0.25, 0.25); },
  };

  // Toast
  function toast(text, kind){
    let host = document.getElementById('di-toast-host');
    if (!host){
      host = document.createElement('div');
      host.id = 'di-toast-host';
      host.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:9999;display:flex;flex-direction:column;gap:8px;max-width:92vw;';
      document.body.appendChild(host);
    }
    const el = document.createElement('div');
    const color = kind==='error' ? 'linear-gradient(135deg,#ef4444,#b91c1c)' :
                  kind==='info'  ? 'linear-gradient(135deg,#3b82f6,#1e40af)' :
                                   'linear-gradient(135deg,#22c55e,#15803d)';
    el.style.cssText = `background:${color};color:#fff;padding:10px 16px;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.35);font-weight:500;font-size:14px;animation:dfi .2s ease-out;`;
    el.textContent = text;
    host.appendChild(el);
    setTimeout(()=>{ el.style.opacity='0'; el.style.transition='opacity .3s'; setTimeout(()=>el.remove(), 300); }, 4000);
  }

  // Partial refresh: fetch same URL, swap elements by id.
  async function refresh(ids){
    try {
      const r = await fetch(window.location.href, {headers:{'X-Partial':'1'}, credentials:'same-origin'});
      if (!r.ok) return;
      const html = await r.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      for (const id of ids){
        const next = doc.getElementById(id);
        const curr = document.getElementById(id);
        if (next && curr) curr.innerHTML = next.innerHTML;
      }
    } catch(e){}
  }
  function autoRefresh(ids, intervalMs){
    setInterval(() => refresh(ids), intervalMs || 10000);
  }

  // Notification polling: plays sound only for genuinely new in-app notifications.
  // Persists last-seen ID in localStorage so page refreshes don't replay old toasts.
  const NOTIF_STORAGE = 'dropit_last_notif_id_' + <?= json_encode((int)($_SESSION['user_id'] ?? 0)) ?>;
  let lastNotifId = parseInt(localStorage.getItem(NOTIF_STORAGE) || '0', 10);
  let notifPollStarted = false;
  function paintBadge(count){
    document.querySelectorAll('[data-notif-badge]').forEach(el => {
      el.textContent = count;
      if (count > 0) el.classList.remove('hidden'); else el.classList.add('hidden');
    });
  }
  async function pollNotifications(){
    try {
      const r = await fetch('<?= url('api/notifications/unread') ?>', {credentials:'same-origin'});
      const d = await r.json();
      if (!d.ok) return;
      paintBadge(d.unread_count);
      if (lastNotifId === 0) {
        // First time this browser ever polls for this user — baseline only, no toasts.
        lastNotifId = d.latest_id || 0;
        localStorage.setItem(NOTIF_STORAGE, String(lastNotifId));
        return;
      }
      if (d.latest_id > lastNotifId){
        const fresh = d.items.filter(x => x.id > lastNotifId);
        for (const n of fresh){
          toast(n.title + ' — ' + (n.message||'').slice(0,80), 'info');
        }
        if (fresh.length > 0) sound.notif();
        lastNotifId = d.latest_id;
        localStorage.setItem(NOTIF_STORAGE, String(lastNotifId));
      }
    } catch(e){}
  }
  function paintMsgBadge(count){
    document.querySelectorAll('[data-msg-badge]').forEach(el => {
      el.textContent = count;
      if (count > 0) el.classList.remove('hidden'); else el.classList.add('hidden');
    });
  }
  async function pollMessages(){
    try {
      const r = await fetch('<?= url('api/messages/unread') ?>', {credentials:'same-origin'});
      const d = await r.json();
      if (d.ok) paintMsgBadge(d.count);
    } catch(e){}
  }
  function startNotifPoll(){
    if (notifPollStarted) return;
    notifPollStarted = true;
    pollNotifications(); pollMessages();
    setInterval(() => { pollNotifications(); pollMessages(); }, 8000);
  }

  // POST JSON helper (keeps csrf token)
  async function post(path, body){
    const fd = new URLSearchParams(body || {});
    fd.set('csrf_token', csrf);
    const r = await fetch(path, {method:'POST', body: fd, credentials:'same-origin'});
    return r.json().catch(() => ({ok:false}));
  }

  // Confirm action prompt (promise).
  function confirm2(msg){ return Promise.resolve(window.confirm(msg)); }

  // Idle auto-logout — 3 minutes of inactivity.
  let idleStarted = false;
  function startIdleLogout(minutes){
    if (idleStarted) return;
    idleStarted = true;
    const LIMIT_MS = (minutes || 3) * 60 * 1000;
    const WARN_MS = 30 * 1000;
    let last = Date.now(), warned = false, warnEl = null;
    function bump(){ last = Date.now(); if (warned){ warned = false; if (warnEl) { warnEl.remove(); warnEl = null; } } }
    ['mousemove','mousedown','keydown','touchstart','scroll','click'].forEach(ev =>
      document.addEventListener(ev, bump, {passive:true}));
    async function logout(reason){
      const body = new URLSearchParams({csrf_token: csrf, reason: reason || 'idle'});
      try { await fetch('<?= url('logout') ?>', {method:'POST', body, credentials:'same-origin'}); } catch(e){}
      location.href = '<?= url('login') ?>?idle=1';
    }
    setInterval(() => {
      const idle = Date.now() - last;
      if (idle >= LIMIT_MS) { logout('idle'); return; }
      if (idle >= LIMIT_MS - WARN_MS && !warned){
        warned = true;
        warnEl = document.createElement('div');
        warnEl.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:10000;background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;padding:10px 18px;border-radius:12px;box-shadow:0 14px 30px rgba(0,0,0,.35);font-weight:600;font-size:14px;';
        warnEl.textContent = 'Idle — logging out in 30s. Move mouse to stay signed in.';
        document.body.appendChild(warnEl);
      }
    }, 5000);
  }

  return { sound, toast, refresh, autoRefresh, startNotifPoll, post, confirm2, csrf, startIdleLogout, refreshMsgBadge: pollMessages, startPriceReviewPoll };

  // ── Customer price-review modal (polls every 10s, shown for role=customer only) ──
  function startPriceReviewPoll(){
    const role = document.body.dataset.role || '';
    if (role !== 'customer') return;
    // Inject modal HTML once
    if (!document.getElementById('priceReviewModal')) _injectPriceModal();
    const seen = new Set(JSON.parse(sessionStorage.getItem('dropit_pr_seen') || '[]'));
    async function poll(){
      try {
        const r = await fetch('<?= url('api/customer/price-reviews') ?>', {credentials:'same-origin'});
        const d = await r.json();
        if (!d.ok || !d.reviews.length) return;
        const pr = d.reviews.find(x => !seen.has(String(x.id)));
        if (!pr) return;
        seen.add(String(pr.id));
        sessionStorage.setItem('dropit_pr_seen', JSON.stringify([...seen]));
        _showPriceModal(pr);
      } catch(e){}
    }
    poll(); setInterval(poll, 10000);
  }

  function _injectPriceModal(){
    const el = document.createElement('div');
    el.id = 'priceReviewModal';
    el.style.cssText = 'display:none;position:fixed;inset:0;z-index:10000;background:rgba(6,17,36,.88);align-items:flex-end;justify-content:center;';
    el.innerHTML = `
      <div id="priceReviewCard" style="width:100%;max-width:460px;margin:0 auto;background:linear-gradient(160deg,#0b1e3f,#12306b);border-radius:28px 28px 0 0;border:1px solid rgba(255,255,255,.18);padding:24px;box-shadow:0 -20px 60px rgba(59,130,246,.3);transform:translateY(100%);transition:transform .35s cubic-bezier(.4,0,.2,1);">
        <div style="width:40px;height:4px;background:rgba(255,255,255,.3);border-radius:4px;margin:0 auto 20px;"></div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
          <span style="font-size:22px;">💰</span>
          <h2 style="font-size:17px;font-weight:700;color:#fff;margin:0;">Fare Adjustment</h2>
        </div>
        <p id="prParcel" style="font-size:11px;color:#81aee8;font-family:monospace;margin-bottom:16px;"></p>
        <div style="background:rgba(255,255,255,.07);border-radius:16px;padding:16px;margin-bottom:16px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
            <span style="font-size:12px;color:#81aee8;">Original fare</span>
            <span id="prOldFare" style="font-size:14px;font-weight:600;color:#fff;text-decoration:line-through;opacity:.6;"></span>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:12px;">
            <span style="font-size:12px;color:#81aee8;">New fare</span>
            <span id="prNewFare" style="font-size:20px;font-weight:800;color:#86efac;"></span>
          </div>
          <div style="border-top:1px solid rgba(255,255,255,.1);padding-top:12px;">
            <p style="font-size:11px;color:#81aee8;margin-bottom:4px;">Reason from admin:</p>
            <p id="prReason" style="font-size:13px;color:#e0f2fe;line-height:1.45;margin:0;"></p>
          </div>
        </div>
        <p id="prAddr" style="font-size:11px;color:#81aee8;margin-bottom:20px;"></p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <button id="prDeclineBtn" style="padding:14px;border-radius:14px;background:rgba(239,68,68,.18);border:1px solid rgba(239,68,68,.35);color:#fca5a5;font-weight:700;font-size:14px;cursor:pointer;">✕ Decline</button>
          <button id="prAcceptBtn" style="padding:14px;border-radius:14px;background:linear-gradient(135deg,#22c55e,#15803d);border:none;color:#fff;font-weight:700;font-size:14px;cursor:pointer;">✓ Accept</button>
        </div>
        <p style="font-size:11px;color:#81aee8;text-align:center;margin-top:12px;">If declined, your parcel returns to queue.</p>
      </div>`;
    document.body.appendChild(el);

    document.getElementById('prAcceptBtn').onclick  = () => _respondPrice('accept');
    document.getElementById('prDeclineBtn').onclick = () => _respondPrice('decline');
  }

  let _prParcelId = null;
  function _showPriceModal(pr){
    _prParcelId = pr.id;
    document.getElementById('prParcel').textContent  = '📦 ' + pr.tracking_number;
    document.getElementById('prOldFare').textContent = '₱' + parseFloat(pr.amount).toFixed(2);
    document.getElementById('prNewFare').textContent = '₱' + parseFloat(pr.pending_amount).toFixed(2);
    document.getElementById('prReason').textContent  = pr.price_adjust_reason || '(no reason given)';
    document.getElementById('prAddr').textContent    = '📍 To: ' + (pr.recipient_address || '');
    const modal = document.getElementById('priceReviewModal');
    modal.style.display = 'flex';
    setTimeout(() => { document.getElementById('priceReviewCard').style.transform = 'translateY(0)'; }, 30);
  }
  function _hidePriceModal(){
    const card = document.getElementById('priceReviewCard');
    if (card) card.style.transform = 'translateY(100%)';
    setTimeout(() => { const m = document.getElementById('priceReviewModal'); if(m) m.style.display='none'; }, 350);
  }
  async function _respondPrice(decision){
    if (!_prParcelId) return;
    const btn = document.getElementById(decision==='accept'?'prAcceptBtn':'prDeclineBtn');
    if (btn) { btn.disabled=true; btn.textContent = decision==='accept'?'Accepting…':'Declining…'; }
    const d = await post('<?= url('api/parcel/') ?>' + _prParcelId + '/price-respond', {decision});
    _hidePriceModal();
    if (d.ok){
      if (decision==='accept'){
        toast('✅ Fare accepted! Driver will be dispatched shortly.', 'info');
        sound.dispatch();
      } else {
        toast('Fare declined. Parcel returned to queue.', 'info');
      }
    } else {
      toast(d.error || 'Could not respond. Please try again.', 'error');
    }
  }
})();
// Auto-start idle logout + customer price-review poll on every authenticated page.
document.addEventListener('DOMContentLoaded', () => {
  if (window.DropIT && parseInt(document.body.dataset.uid || '0') > 0) DropIT.startIdleLogout(3);
  if (window.DropIT && document.body.dataset.role === 'customer') DropIT.startPriceReviewPoll();
});
</script>
<style>
@keyframes dfi{from{transform:translateX(-50%) translateY(-10px);opacity:0;}to{transform:translateX(-50%) translateY(0);opacity:1;}}
@media print {
  .no-print, nav, aside, header, footer, .btn { display:none !important; }
  body { background:#fff !important; color:#000 !important; }
  .glass, .glass-strong, .glass-light { background:#fff !important; border:1px solid #ddd !important; color:#000 !important; backdrop-filter:none !important; }
}
</style>
