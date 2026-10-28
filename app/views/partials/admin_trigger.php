<?php defined('APP_ROOT') OR exit('No direct script access allowed'); ?>
<!-- ============ Hidden admin trigger ============ -->
<!-- Konami code (↑ ↑ ↓ ↓ ← → ← →) AND a double-tap on the Drop IT logo, within 10s. Works on /login and /. -->
<div id="adminBackdrop" class="fixed inset-0 z-[70] hidden items-center justify-center px-4" style="background:rgba(5,10,25,.75);backdrop-filter:blur(6px);height:100dvh;">
  <div id="adminPopup" class="w-full max-w-md glass-strong rounded-3xl p-6 shadow-glass relative" style="transform:scale(.96);opacity:0;transition:transform .2s ease, opacity .2s ease;">
    <button type="button" id="adminClose" class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center" aria-label="Close">✕</button>
    <div class="flex items-center gap-3">
      <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-azure to-brand-blue flex items-center justify-center text-2xl">🛡️</div>
      <div>
        <h2 class="text-xl font-bold">Admin Access</h2>
        <p class="text-xs text-brand-mist">Authorized personnel only.</p>
      </div>
    </div>

    <form method="post" action="<?= url('login') ?>" class="mt-5 space-y-4" id="adminForm">
      <?php (new Router())->csrf_field(); ?>
      <input type="hidden" name="admin_mode" value="1">
      <div>
        <label class="label" for="adm_email">Admin email</label>
        <input class="input" id="adm_email" name="email" type="email" placeholder="admin@drop-it.dev" required autocomplete="off">
      </div>
      <div>
        <label class="label" for="adm_password">Admin password</label>
        <input class="input" id="adm_password" name="password" type="password" placeholder="••••••••" required autocomplete="off">
      </div>
      <button type="submit" class="btn btn-primary w-full">Enter admin console →</button>
      <p class="text-[11px] text-brand-mist text-center">
        <kbd class="px-1.5 py-0.5 rounded bg-white/15">Esc</kbd> to close · Invalid entry returns you to normal login.
      </p>
    </form>
  </div>
</div>

<!-- Admin combo toast -->
<div id="adminToast" class="fixed left-1/2 z-[80] hidden items-center gap-3 px-4 py-3 rounded-2xl shadow-glass"
     style="top:16px; transform:translateX(-50%); background:rgba(15,23,42,.95); border:1px solid rgba(96,165,250,.5); color:#fff; max-width:92vw;">
  <span id="adminToastIcon" style="font-size:20px">🛡️</span>
  <div class="text-sm">
    <div id="adminToastTitle" class="font-semibold">Admin mode armed</div>
    <div id="adminToastMsg" class="text-xs text-brand-mist mt-0.5">Double-tap the Drop IT logo to continue.</div>
  </div>
</div>

<script>
(function(){
  const KONAMI = ['ArrowUp','ArrowUp','ArrowDown','ArrowDown','ArrowLeft','ArrowRight','ArrowLeft','ArrowRight'];
  const backdrop = document.getElementById('adminBackdrop');
  const popup    = document.getElementById('adminPopup');
  const closeBtn = document.getElementById('adminClose');
  const logo     = document.querySelector('[data-admin-logo]');
  const toast    = document.getElementById('adminToast');
  const toastIcon= document.getElementById('adminToastIcon');
  const toastTtl = document.getElementById('adminToastTitle');
  const toastMsg = document.getElementById('adminToastMsg');

  if (!backdrop || !logo) return;

  const COMBO_WINDOW_MS = 10_000;
  let konamiArmed = false, logoArmed = false;
  let konamiTimer = null, logoTimer = null, toastTimer = null;

  function showToast(icon, title, msg, ok = false){
    toastIcon.textContent = icon;
    toastTtl.textContent = title;
    toastMsg.textContent = msg;
    toast.style.borderColor = ok ? 'rgba(34,197,94,.7)' : 'rgba(96,165,250,.55)';
    toast.style.display = 'flex';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.style.display = 'none'; }, ok ? 1500 : 9000);
  }
  function hideToast(){ toast.style.display = 'none'; clearTimeout(toastTimer); }

  function armKonami(){
    konamiArmed = true;
    clearTimeout(konamiTimer);
    konamiTimer = setTimeout(() => { konamiArmed = false; hideToast(); }, COMBO_WINDOW_MS);
    if (!tryOpen()) showToast('🛡️', 'Admin Mode', 'Double-tap the Drop IT logo to continue.');
  }
  function armLogo(){
    logoArmed = true;
    clearTimeout(logoTimer);
    logoTimer = setTimeout(() => { logoArmed = false; hideToast(); }, COMBO_WINDOW_MS);
    if (!tryOpen()) showToast('🛡️', 'Admin Mode', 'Enter the cheat code to continue.');
  }
  function tryOpen(){
    if (konamiArmed && logoArmed){
      konamiArmed = logoArmed = false;
      clearTimeout(konamiTimer); clearTimeout(logoTimer);
      showToast('✅', 'Access granted', 'Loading admin form…', true);
      setTimeout(open, 300);
      return true;
    }
    return false;
  }

  function open(){
    backdrop.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => {
      popup.style.transform = 'scale(1)';
      popup.style.opacity = '1';
    });
    setTimeout(() => document.getElementById('adm_email').focus(), 120);
  }
  function close(){
    popup.style.transform = 'scale(.96)';
    popup.style.opacity = '0';
    setTimeout(() => { backdrop.style.display = 'none'; document.body.style.overflow = ''; }, 180);
  }

  closeBtn.addEventListener('click', close);
  backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && backdrop.style.display === 'flex') close();
  });

  // --- Konami capture ---
  let ki = 0;
  document.addEventListener('keydown', (e) => {
    const tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
    if (!e.key.startsWith('Arrow')) { ki = 0; return; }
    if (e.key === KONAMI[ki]) {
      ki++;
      if (ki === KONAMI.length) { ki = 0; armKonami(); }
    } else {
      ki = (e.key === KONAMI[0]) ? 1 : 0;
    }
  });

  // --- Logo tap counter ---
  // Desktop: double-tap arms the logo half of the Konami combo.
  // Mobile (or anyone): 5 quick taps bypasses the combo entirely and opens the admin form.
  const MOBILE_TAP_COUNT = 5;
  const MOBILE_TAP_WINDOW_MS = 2500;
  let clicks = 0, mobileTaps = 0;
  let clickResetTimer = null, mobileResetTimer = null;

  function openDirect(){
    konamiArmed = logoArmed = false;
    clearTimeout(konamiTimer); clearTimeout(logoTimer);
    showToast('✅', 'Access granted', 'Loading admin form…', true);
    setTimeout(open, 300);
  }

  logo.addEventListener('click', (e) => {
    e.preventDefault();

    // 5-tap mobile bypass
    mobileTaps++;
    clearTimeout(mobileResetTimer);
    if (mobileTaps >= MOBILE_TAP_COUNT) {
      mobileTaps = 0;
      clicks = 0;
      clearTimeout(clickResetTimer);
      openDirect();
      return;
    }
    mobileResetTimer = setTimeout(() => { mobileTaps = 0; }, MOBILE_TAP_WINDOW_MS);

    // Desktop double-tap path (still arms half of the Konami combo)
    clicks++;
    clearTimeout(clickResetTimer);
    if (clicks >= 2) { clicks = 0; armLogo(); }
    else { clickResetTimer = setTimeout(() => { clicks = 0; }, 450); }
  });
})();
</script>
