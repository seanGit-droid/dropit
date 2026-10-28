<?php defined('APP_ROOT') OR exit('No direct script access allowed');
/**
 * Browser-to-browser voice call via PeerJS cloud. User-based peer IDs.
 * Safe to include at most once per page; subsequent includes are ignored.
 */
if (!empty($GLOBALS['__dropit_voicecall_included'])) return;
$GLOBALS['__dropit_voicecall_included'] = true;

$_my_uid = (int)($_SESSION['user_id'] ?? 0);
if ($_my_uid <= 0) return;
$_target  = (int)($call_target_user_id ?? 0);
$_peer    = $call_peer_name ?? 'Other party';
?>
<script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>

<?php if ($_target > 0 && empty($GLOBALS['__dropit_hide_floating_call_btn'])): ?>
<div id="callWidget" class="fixed bottom-44 right-4 z-50">
  <button id="callBtn" title="Voice call <?= esc($_peer) ?>" class="w-14 h-14 rounded-full shadow-glow flex items-center justify-center relative" style="background:linear-gradient(135deg,#22c55e,#15803d);color:#fff;">
    <?= icon('call', 'w-7 h-7 dropit-icon-active') ?>
    <span id="callDot" class="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full border-2 border-white" style="background:#94a3b8" title="connecting"></span>
  </button>
</div>
<?php endif; ?>

<!-- Compact call popup (top-right) -->
<div id="callPopup" class="fixed top-4 right-4 z-[60] hidden w-80 max-w-[92vw] rounded-2xl shadow-glass overflow-hidden" style="background:rgba(11,30,63,0.92); backdrop-filter: blur(28px) saturate(180%); -webkit-backdrop-filter: blur(28px) saturate(180%); color:#fff; border:1px solid rgba(255,255,255,0.18);">
  <div id="callHeader" class="flex items-center justify-between px-3 py-2 cursor-move select-none" style="background:rgba(255,255,255,0.08); border-bottom:1px solid rgba(255,255,255,0.12);">
    <div class="flex items-center gap-2 min-w-0">
      <div id="callEmoji" class="text-lg">📞</div>
      <div class="min-w-0">
        <div id="callPeerName" class="text-sm font-semibold truncate"><?= esc($_peer) ?></div>
        <div id="callStatus" class="text-[11px] opacity-80">Ready</div>
      </div>
    </div>
    <button id="callMin" title="Minimize" class="w-6 h-6 rounded hover:bg-white/10 text-sm leading-none">_</button>
  </div>
  <div class="px-4 py-3">
    <div class="flex items-center justify-between">
      <div id="callTimer" class="text-lg font-mono text-emerald-300">00:00</div>
    </div>
    <div class="mt-3 flex gap-2">
      <button id="callAccept" class="btn btn-success hidden !py-1.5 text-xs flex-1">✓ Accept</button>
      <button id="callMute"   class="btn btn-ghost   !py-1.5 text-xs flex-1">🎙️ Mute</button>
      <button id="callHangup" class="btn btn-danger  !py-1.5 text-xs flex-1">✕ End</button>
    </div>
    <div id="callHint" class="mt-2 text-[10px] opacity-75 hidden">Tip: both users need the app open with mic access.</div>
  </div>
  <audio id="remoteAudio" autoplay playsinline></audio>
</div>

<div id="callMini" class="fixed top-4 right-4 z-[60] hidden rounded-full px-3 py-2 flex items-center gap-2 cursor-pointer shadow-glass" style="background:linear-gradient(135deg,#22c55e,#15803d);color:#fff;">
  <span id="callMiniEmoji" class="text-sm">🎙️</span>
  <span id="callMiniTimer" class="text-xs font-mono">00:00</span>
  <span class="text-[10px] opacity-80">click to expand</span>
</div>

<style>
#callPopup.ringing { animation: ringshake .6s ease-in-out infinite; }
@keyframes ringshake { 0%,100%{transform:translateX(0);} 25%{transform:translateX(-3px);} 75%{transform:translateX(3px);} }
</style>

<script>
(function(){
  const MY_UID = <?= $_my_uid ?>;
  const selfId = `dropit-user-${MY_UID}`;

  const btn   = document.getElementById('callBtn'); // may be null if no target
  const dot   = document.getElementById('callDot');
  const pop   = document.getElementById('callPopup');
  const mini  = document.getElementById('callMini');
  const head  = document.getElementById('callHeader');
  const status = document.getElementById('callStatus');
  const peerNameEl = document.getElementById('callPeerName');
  const emoji = document.getElementById('callEmoji');
  const timer = document.getElementById('callTimer');
  const miniTimer = document.getElementById('callMiniTimer');
  const miniEmoji = document.getElementById('callMiniEmoji');
  const accept = document.getElementById('callAccept');
  const muteBtn = document.getElementById('callMute');
  const hangup = document.getElementById('callHangup');
  const minBtn = document.getElementById('callMin');
  const remote = document.getElementById('remoteAudio');
  const hint   = document.getElementById('callHint');

  let peer = null, currentCall = null, localStream = null;
  let timerInt = null, startTs = 0;
  let incoming = null, ringInt = null, ringAc = null;
  let muted = false;
  let peerReady = false;
  let noAnswerTO = null;
  let endedByMe = false;

  // Ringtone audio
  const _ringAudio = new Audio('<?= url('resources/sounds/ringtone2.mp3') ?>');
  _ringAudio.loop = true;
  _ringAudio.volume = 0.85;

  function setDot(color, title){ if (dot) { dot.style.background = color; dot.title = title; } }
  function showPopup(name){ peerNameEl.textContent = name || peerNameEl.textContent; pop.classList.remove('hidden'); mini.classList.add('hidden'); }
  function hidePopup(){
    pop.classList.add('hidden'); mini.classList.add('hidden');
    pop.classList.remove('ringing');
    stopTimer(); stopRing();
    timer.textContent = '00:00'; miniTimer.textContent = '00:00';
    accept.classList.add('hidden'); hint.classList.add('hidden');
    muted = false; muteBtn.textContent = '🎙️ Mute';
    muteBtn.classList.remove('btn-danger'); muteBtn.classList.add('btn-ghost');
  }
  function minimize(){ pop.classList.add('hidden'); mini.classList.remove('hidden'); }

  function startTimer(){
    startTs = Date.now();
    timerInt = setInterval(()=>{
      const s = Math.floor((Date.now()-startTs)/1000);
      const t = String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0');
      timer.textContent = t; miniTimer.textContent = t;
    }, 1000);
  }
  function stopTimer(){ if (timerInt) clearInterval(timerInt); timerInt = null; }

  function startRing(){
    pop.classList.add('ringing');
    _ringAudio.currentTime = 0;
    _ringAudio.play().catch(() => {
      // Fallback: oscillator beeps if autoplay blocked
      try {
        stopRing();
        ringAc = new (window.AudioContext || window.webkitAudioContext)();
        ringInt = setInterval(() => {
          const t = ringAc.currentTime;
          [[520, 0], [660, 0.42]].forEach(([f, off]) => {
            const o = ringAc.createOscillator(), g = ringAc.createGain();
            o.frequency.value = f; g.gain.value = 0.18;
            o.connect(g); g.connect(ringAc.destination);
            o.start(t + off); o.stop(t + off + 0.35);
          });
        }, 1600);
      } catch(e){}
    });
  }
  function stopRing(){
    pop.classList.remove('ringing');
    try { _ringAudio.pause(); _ringAudio.currentTime = 0; } catch(e){}
    if (ringInt) clearInterval(ringInt); ringInt = null;
    try { if (ringAc) { ringAc.close(); ringAc = null; } } catch(e){}
  }

  async function getMic(){
    if (localStream && localStream.active) return localStream;
    try { localStream = await navigator.mediaDevices.getUserMedia({audio:true}); return localStream; }
    catch(e){ alert('Please allow microphone access. ' + (e.message||'')); return null; }
  }

  function destroyPeer(){ try { if (peer && !peer.destroyed) peer.destroy(); } catch(e){} peer = null; peerReady = false; setDot('#94a3b8','disconnected'); }

  function initPeer(){
    return new Promise(resolve => {
      destroyPeer();
      const p = new Peer(selfId, { debug: 0 });
      let done = false;
      p.on('open', () => { peer = p; peerReady = true; setDot('#22c55e','ready'); if (!done){ done=true; resolve({ok:true}); } });
      p.on('error', (err) => {
        if (err.type === 'unavailable-id') {
          try { p.destroy(); } catch(e){}
          const alt = selfId + '-' + Math.random().toString(36).slice(2,6);
          const p2 = new Peer(alt, { debug: 0 });
          p2.on('open', () => { peer = p2; peerReady = true; attach(); setDot('#eab308','alt id — can call, may not receive'); if (!done){ done=true; resolve({ok:true,alt:true}); } });
          p2.on('error', (e2) => { if (!done){ done=true; resolve({ok:false,err:e2}); } });
          p2.on('call', onIncoming);
          return;
        }
        if (err.type === 'peer-unavailable') {
          status.textContent = peerNameEl.textContent + ' is offline';
          emoji.textContent = '📵'; miniEmoji.textContent = '📵';
          hint.classList.remove('hidden');
          setTimeout(hidePopup, 3500);
          return;
        }
        if (err.type === 'network' || err.type === 'server-error' || err.type === 'socket-error') setDot('#ef4444','network');
        if (!done){ done=true; resolve({ok:false,err}); }
      });
      p.on('disconnected', () => { setDot('#eab308','reconnecting'); try { p.reconnect(); } catch(e){} });
      p.on('call', onIncoming);
      setTimeout(() => { if (!done){ done=true; resolve({ok:false,err:{type:'timeout'}}); } }, 6000);
    });
  }
  function attach(){ if (peer) peer.on('call', onIncoming); }
  function onIncoming(call){
    incoming = call;
    const name = (call.metadata && call.metadata.from) ? call.metadata.from : 'Someone';
    status.textContent = '📞 Incoming from ' + name;
    emoji.textContent = '📞'; miniEmoji.textContent = '📞';
    showPopup(name);
    accept.classList.remove('hidden');
    startRing();
    if (window.DropIT) DropIT.toast(name + ' is calling you', 'info');
  }

  initPeer();

  async function callUser(targetUid, targetName){
    if (!targetUid) return;
    try {
      targetName = targetName || 'Driver';
      endedByMe = false;
      showPopup(targetName);
      status.textContent = 'Connecting…'; emoji.textContent = '📞'; miniEmoji.textContent = '📞';
      accept.classList.add('hidden');
      if (!peerReady) {
        const r = await initPeer();
        if (!r.ok) { status.textContent = 'Voice unavailable'; emoji.textContent='⚠️'; setTimeout(hidePopup, 2500); return; }
      }
      const stream = await getMic();
      if (!stream) { hidePopup(); return; }
      status.textContent = '📞 Ringing ' + targetName + '…';
      emoji.textContent = '📞';
      const meName = <?= json_encode($_SESSION['full_name'] ?: $_SESSION['username'] ?? 'User') ?>;
      const call = peer.call('dropit-user-' + targetUid, stream, {metadata:{from: meName}});
      if (!call) { status.textContent = 'Peer not registered'; hint.classList.remove('hidden'); setTimeout(hidePopup, 3000); return; }
      currentCall = call;
      let answered = false;

      call.on('stream', (r) => {
        if (endedByMe) return;
        remote.srcObject = r;
        remote.muted = false;
        remote.volume = 1;
        const p = remote.play();
        if (p) p.catch(() => {
          document.addEventListener('click', () => remote.play().catch(()=>{}), {once:true});
        });
        emoji.textContent = '🎙️'; miniEmoji.textContent='🎙️';
        status.textContent = 'Connected';
        answered = true;
        startTimer();
        if (noAnswerTO) { clearTimeout(noAnswerTO); noAnswerTO = null; }
      });
      call.on('close', () => {
        if (noAnswerTO) { clearTimeout(noAnswerTO); noAnswerTO = null; }
        if (endedByMe) { hidePopup(); return; }
        status.textContent = answered ? 'Call ended by other party' : 'No answer';
        emoji.textContent = answered ? '✓' : '📵';
        // Force local cleanup so mic is released and UI truly ends.
        setTimeout(() => endCall(), 1200);
      });
      call.on('error', () => { status.textContent = 'Call error'; setTimeout(() => endCall(), 1500); });
      // Fallback: detect underlying peer connection close/fail to force local end.
      watchPeerConnection(call);

      // 30s no-answer timeout
      noAnswerTO = setTimeout(() => {
        if (!answered && currentCall === call){
          try { call.close(); } catch(e){}
          status.textContent = targetName + ' did not answer';
          emoji.textContent = '📵';
          hint.classList.remove('hidden');
          setTimeout(hidePopup, 2500);
        }
      }, 30000);
    } catch(e){ console.warn(e); }
  }

  function endCall(){
    endedByMe = true;
    if (noAnswerTO) { clearTimeout(noAnswerTO); noAnswerTO = null; }
    try {
      if (currentCall) {
        // Also stop our own senders to signal peer connection tear-down.
        if (currentCall.peerConnection) {
          try { currentCall.peerConnection.getSenders().forEach(s => s.track && s.track.stop()); } catch(e){}
          try { currentCall.peerConnection.close(); } catch(e){}
        }
        currentCall.close();
      }
    } catch(e){}
    try { if (incoming) incoming.close(); } catch(e){}
    try { if (localStream) { localStream.getTracks().forEach(t => t.stop()); localStream = null; } } catch(e){}
    try { if (remote) { remote.pause(); remote.srcObject = null; } } catch(e){}
    currentCall = null; incoming = null;
    stopTimer(); stopRing();
    hidePopup();
  }

  // Watch the underlying RTCPeerConnection for disconnect — PeerJS's 'close' event sometimes
  // doesn't propagate to the remote end, so we also listen to connectionstatechange.
  function watchPeerConnection(call){
    const pc = call && call.peerConnection;
    if (!pc) return;
    const onState = () => {
      const s = pc.connectionState || pc.iceConnectionState;
      if (['disconnected','failed','closed'].includes(s) && currentCall === call && !endedByMe){
        status.textContent = 'Call ended';
        setTimeout(() => endCall(), 800);
      }
    };
    pc.addEventListener('connectionstatechange', onState);
    pc.addEventListener('iceconnectionstatechange', onState);
  }

  // Unlock audio autoplay on first user click anywhere (browsers block autoplay otherwise).
  document.addEventListener('click', () => {
    try { if (remote && remote.srcObject) remote.play().catch(()=>{}); } catch(e){}
  }, {once:false, capture:true});

  const _SILENT_WAV = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAESsAACJWAAACABAAZGF0YQAAAAA=';
  function _unlockAudio(){
    // Play a throwaway silent clip within the user gesture to unlock audio autoplay
    try { const a = new Audio(_SILENT_WAV); a.volume = 0; a.play().catch(()=>{}); } catch(e){}
  }

  if (btn) btn.onclick = () => {
    _unlockAudio();
    callUser(<?= $_target ?>, <?= json_encode($_peer) ?>);
  };
  accept.onclick = async () => {
    if (!incoming) return;
    stopRing();
    endedByMe = false;
    _unlockAudio(); // unlock within user gesture
    const stream = await getMic();
    if (!stream) { incoming.close(); incoming = null; hidePopup(); return; }
    // Ensure mic tracks are enabled (not muted from previous call)
    stream.getAudioTracks().forEach(t => { t.enabled = true; });
    muted = false;
    incoming.answer(stream);
    currentCall = incoming; incoming = null;
    accept.classList.add('hidden');
    emoji.textContent = '🎙️'; miniEmoji.textContent='🎙️'; status.textContent = 'Connected'; startTimer();
    currentCall.on('stream', (r) => {
      remote.srcObject = r;
      remote.muted = false;
      remote.volume = 1;
      // Force play — autoplay is unlocked because we called _unlockAudio() in user gesture
      const p = remote.play();
      if (p) p.catch(() => {
        // Last resort: wait for any click and retry
        document.addEventListener('click', () => remote.play().catch(()=>{}), {once:true});
      });
    });
    currentCall.on('close', () => {
      if (endedByMe) { hidePopup(); return; }
      status.textContent = 'Call ended by other party';
      setTimeout(() => endCall(), 1200);
    });
    currentCall.on('error', () => { status.textContent = 'Call error'; setTimeout(() => endCall(), 1500); });
    watchPeerConnection(currentCall);
  };
  muteBtn.onclick = () => {
    if (!localStream) return;
    muted = !muted;
    localStream.getAudioTracks().forEach(t => t.enabled = !muted);
    muteBtn.textContent = muted ? '🔇 Unmute' : '🎙️ Mute';
    muteBtn.classList.toggle('btn-danger', muted);
    muteBtn.classList.toggle('btn-ghost', !muted);
    emoji.textContent = muted ? '🔇' : '🎙️'; miniEmoji.textContent = emoji.textContent;
  };
  hangup.onclick = endCall;
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !pop.classList.contains('hidden')) endCall(); });
  minBtn.onclick = () => minimize();
  mini.onclick = () => { mini.classList.add('hidden'); pop.classList.remove('hidden'); };

  // Drag
  (function(){
    let dragging = false, dx = 0, dy = 0, startX = 0, startY = 0;
    function start(e){ if (e.target.closest('button')) return; dragging = true; const t = e.touches?e.touches[0]:e; const r = pop.getBoundingClientRect(); startX=t.clientX; startY=t.clientY; dx=r.left; dy=r.top; pop.style.left=dx+'px'; pop.style.top=dy+'px'; pop.style.right='auto'; e.preventDefault(); }
    function move(e){ if (!dragging) return; const t = e.touches?e.touches[0]:e; const nx = Math.max(4, Math.min(window.innerWidth-pop.offsetWidth-4, dx + (t.clientX-startX))); const ny = Math.max(4, Math.min(window.innerHeight-pop.offsetHeight-4, dy + (t.clientY-startY))); pop.style.left=nx+'px'; pop.style.top=ny+'px'; e.preventDefault(); }
    function end(){ dragging = false; }
    head.addEventListener('mousedown', start);
    document.addEventListener('mousemove', move);
    document.addEventListener('mouseup', end);
    head.addEventListener('touchstart', start, {passive:false});
    document.addEventListener('touchmove', move, {passive:false});
    document.addEventListener('touchend', end);
  })();

  // start() falls back to the configured target when the floating button is hidden
  // (it's hidden on any page that includes chat_bubble.php so the chat 📞 wins).
  const FALLBACK_UID = <?= $_target ?>;
  const FALLBACK_NAME = <?= json_encode($_peer) ?>;
  window.dropItVoiceCall = {
    start: () => { if (btn) btn.click(); else if (FALLBACK_UID) callUser(FALLBACK_UID, FALLBACK_NAME); },
    callUser
  };

  window.addEventListener('pagehide', () => {
    try { if (localStream) localStream.getTracks().forEach(t => t.stop()); } catch(e){}
    destroyPeer();
  });
})();
</script>
