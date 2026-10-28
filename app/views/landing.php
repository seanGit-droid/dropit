<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Welcome';
include __DIR__ . '/partials/head.php';
?>
<style>
/* ── Landing Mobile-First Overrides ── */
.hero-glow::before{
  content:""; position:absolute; inset:-40px; z-index:-1;
  background: radial-gradient(400px 200px at 30% 40%, rgba(96,165,250,.30), transparent 70%),
              radial-gradient(300px 180px at 80% 60%, rgba(59,130,246,.22), transparent 70%);
  filter: blur(32px); animation: float 12s ease-in-out infinite alternate;
}
@keyframes float { from{transform:translateY(0);} to{transform:translateY(-10px);} }
.card-feat{transition:transform .25s ease, box-shadow .25s ease;}
.card-feat:hover{transform:translateY(-4px); box-shadow:0 24px 50px -20px rgba(59,130,246,.55);}
.sticky-nav{position:sticky;top:.25rem;z-index:30;}
.mobile-menu{transform:translateY(-8px);opacity:0;pointer-events:none;transition:transform .2s ease, opacity .2s ease;}
.mobile-menu.open{transform:translateY(0);opacity:1;pointer-events:auto;}
body{overflow-x:hidden;margin:0;padding:0;}
/* Typography scale — mobile first */
.hero-title{font-size:2.2rem;line-height:1.1;letter-spacing:-.02em;}
.hero-sub{font-size:1rem;line-height:1.5;margin-top:1rem;}
@media(min-width:480px){.hero-title{font-size:2.8rem;} .hero-sub{font-size:.95rem;}}
@media(min-width:640px){.hero-title{font-size:3.5rem;}}
@media(min-width:768px){.hero-title{font-size:4.5rem;}}
@media(min-width:1024px){.hero-title{font-size:5.5rem;}}
/* Feature grid — Mobile first */
.feat-grid{display:grid;grid-template-columns:1fr;gap:1rem;}
@media(min-width:480px){.feat-grid{grid-template-columns:1fr 1fr;gap:1rem;}}
@media(min-width:768px){.feat-grid{grid-template-columns:1fr 1fr 1fr;gap:1.5rem;}}
/* Track form mobile wrap */
.track-form{display:flex;gap:.5rem;flex-direction:column;}
@media(min-width:480px){.track-form{flex-direction:row;flex-wrap:nowrap;}}
/* Step cards */
.step-grid{display:grid;grid-template-columns:1fr;gap:.75rem;}
@media(min-width:640px){.step-grid{grid-template-columns:1fr 1fr 1fr;}}
/* Prevent CTA overflow */
.cta-btns{display:flex;flex-direction:column;gap:.75rem;align-items:stretch;}
@media(min-width:400px){.cta-btns{flex-direction:row;justify-content:center;align-items:center;}}
/* Mobile hamburger animation */
.hamburger-line { transition: transform 0.3s ease, opacity 0.3s ease; transform-origin: center; }
.open .hamburger-line-1 { transform: translateY(5px) rotate(45deg); }
.open .hamburger-line-2 { opacity: 0; }
.open .hamburger-line-3 { transform: translateY(-5px) rotate(-45deg); }
</style>

<header class="px-3 sm:px-6 md:px-10 pt-0 sm:pt-4 sticky-nav">
  <div class="max-w-6xl mx-auto glass rounded-2xl px-3 py-2 sm:px-4 sm:py-3 flex items-center justify-between shadow-glass">
    <div class="flex items-center gap-2 select-none cursor-default" data-admin-logo>
      <img src="<?= url('resources/weblogo.png') ?>" alt="Drop IT" class="w-8 h-8 sm:w-10 sm:h-10 object-contain" draggable="false">
      <span class="font-bold tracking-tight text-sm sm:text-base">Drop IT</span>
    </div>
    <nav class="hidden md:flex items-center gap-5 text-sm text-brand-mist">
      <a href="#features" class="hover:text-white">Features</a>
      <a href="#how" class="hover:text-white">How it works</a>
    </nav>
    <div class="hidden sm:flex items-center gap-2">
      <a href="<?= url('signup') ?>" class="btn btn-ghost text-sm !py-2 !px-3">Sign up</a>
      <a href="<?= url('login') ?>" class="btn btn-primary text-sm !py-2 !px-3">Login →</a>
    </div>
    <!-- Mobile hamburger -->
    <button id="navToggle" class="sm:hidden w-10 h-10 rounded-xl bg-white/10 border border-white/20 flex flex-col items-center justify-center gap-[4px] p-2 hover:bg-white/20 active:scale-95 transition-all" aria-label="Menu">
      <span class="hamburger-line hamburger-line-1 block w-5 h-[2px] bg-current rounded-full"></span>
      <span class="hamburger-line hamburger-line-2 block w-5 h-[2px] bg-current rounded-full"></span>
      <span class="hamburger-line hamburger-line-3 block w-5 h-[2px] bg-current rounded-full"></span>
    </button>
  </div>

  <!-- Mobile menu -->
  <div id="mobileMenu" class="mobile-menu sm:hidden absolute top-[4.5rem] left-4 right-4 z-40 glass-strong border border-white/20 rounded-2xl p-4 shadow-2xl shadow-brand-navy/50">
    <div class="grid gap-2">
      <a href="#features" class="block rounded-xl px-4 py-3 text-base font-medium hover:bg-white/10 active:bg-white/20 transition-colors">✨ Features</a>
      <a href="#how" class="block rounded-xl px-4 py-3 text-base font-medium hover:bg-white/10 active:bg-white/20 transition-colors">🗺️ How it works</a>
      <div class="h-px bg-white/10 my-2"></div>
      <a href="<?= url('signup') ?>" class="btn btn-ghost text-base w-full justify-center !py-3">Sign up</a>
      <a href="<?= url('login') ?>" class="btn btn-primary text-base w-full justify-center !py-3 shadow-lg shadow-brand-blue/30">Login →</a>
    </div>
  </div>
</header>

<!-- HERO -->
<main class="relative px-4 sm:px-5 pt-0 sm:pt-12 pb-8 sm:pb-16 overflow-hidden">
  <div class="hero-glow relative max-w-5xl mx-auto text-center mt-3 sm:mt-0">
    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass text-[10px] sm:text-xs text-brand-mist mb-2 sm:mb-5">
      <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse flex-shrink-0"></span>
      Live in Oriental Mindoro
    </div>
    <h1 class="hero-title font-extrabold tracking-tight">
      Drop IT.<br>
      <span class="bg-gradient-to-r from-brand-sky via-white to-brand-mist bg-clip-text text-transparent">Delivered.</span>
    </h1>
    <p class="hero-sub mt-1.5 sm:mt-5 text-brand-mist max-w-sm sm:max-w-xl mx-auto px-2">
      Same-day parcels, Pinoy style. Real-time tracking, chat with your driver, pay COD or GCash.
    </p>
    <div class="cta-btns mt-3 sm:mt-8 px-6 sm:px-0">
      <a href="<?= url('signup/customer') ?>" class="btn btn-primary">Send a parcel</a>
      <a href="<?= url('signup/driver') ?>" class="btn btn-ghost">Become a driver</a>
    </div>

    <!-- Instant public tracking -->
    <form method="get" action="<?= url('track') ?>" class="track-form mt-6 sm:mt-8 max-w-lg mx-auto glass rounded-3xl p-2 sm:p-2 shadow-glass border border-white/20">
      <input name="q" class="input !py-3.5 !px-5 flex-1 text-base min-w-0 !bg-transparent !border-none !shadow-none focus:ring-0" placeholder="🔎 Paste tracking number" required>
      <button class="btn btn-primary !py-3.5 !px-6 text-base whitespace-nowrap flex-shrink-0 justify-center !rounded-2xl shadow-lg shadow-brand-blue/30">Track Parcel</button>
    </form>
    <div class="mt-1.5 text-[11px] text-brand-mist/70">No account needed.</div>
  </div>
</main>

<!-- FEATURES -->
<section id="features" class="px-4 sm:px-5 py-10 sm:py-16 relative">
  <div class="max-w-5xl mx-auto">
    <h2 class="text-center text-2xl sm:text-3xl md:text-4xl font-extrabold tracking-tight">Everything you need</h2>
    <p class="text-center text-base text-brand-mist mt-2 px-2 max-w-lg mx-auto">Real-time, reliable, and built specifically for the Pinoy hustle.</p>
    <div class="feat-grid mt-8 sm:mt-12">
      <div class="card-feat glass-strong rounded-3xl p-6 sm:p-8 shadow-glass flex flex-col items-center sm:items-start text-center sm:text-left">
        <div class="text-4xl sm:text-3xl mb-1">🗺️</div>
        <div class="mt-3 font-bold text-lg sm:text-xl text-white">Live map</div>
        <div class="text-sm sm:text-base text-brand-mist mt-2 leading-relaxed">See your driver's exact location and ETA in real-time.</div>
      </div>
      <div class="card-feat glass-strong rounded-3xl p-6 sm:p-8 shadow-glass flex flex-col items-center sm:items-start text-center sm:text-left">
        <div class="text-4xl sm:text-3xl mb-1">💬</div>
        <div class="mt-3 font-bold text-lg sm:text-xl text-white">Real-time chat</div>
        <div class="text-sm sm:text-base text-brand-mist mt-2 leading-relaxed">Floating bubble. Coordinate with driver easily anywhere.</div>
      </div>
      <div class="card-feat glass-strong rounded-3xl p-6 sm:p-8 shadow-glass flex flex-col items-center sm:items-start text-center sm:text-left">
        <div class="text-4xl sm:text-3xl mb-1">💳</div>
        <div class="mt-3 font-bold text-lg sm:text-xl text-white">COD or GCash</div>
        <div class="text-sm sm:text-base text-brand-mist mt-2 leading-relaxed">Pay upfront via GCash or cash on delivery securely.</div>
      </div>
      <div class="card-feat glass rounded-3xl p-6 sm:p-8 flex flex-col items-center sm:items-start text-center sm:text-left">
        <div class="text-4xl sm:text-3xl mb-1">📷</div>
        <div class="mt-3 font-bold text-lg sm:text-xl text-white">Proof of delivery</div>
        <div class="text-sm sm:text-base text-brand-mist mt-2 leading-relaxed">Photo + signature captured on successful delivery.</div>
      </div>
      <div class="card-feat glass rounded-3xl p-6 sm:p-8 flex flex-col items-center sm:items-start text-center sm:text-left">
        <div class="text-4xl sm:text-3xl mb-1">⭐</div>
        <div class="mt-3 font-bold text-lg sm:text-xl text-white">Rate & review</div>
        <div class="text-sm sm:text-base text-brand-mist mt-2 leading-relaxed">Pick the best drivers with our honest rating system.</div>
      </div>
      <div class="card-feat glass rounded-3xl p-6 sm:p-8 flex flex-col items-center sm:items-start text-center sm:text-left">
        <div class="text-4xl sm:text-3xl mb-1">📊</div>
        <div class="mt-3 font-bold text-lg sm:text-xl text-white">Analytics</div>
        <div class="text-sm sm:text-base text-brand-mist mt-2 leading-relaxed">Powerful earnings history & revenue dashboards.</div>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section id="how" class="px-4 sm:px-5 py-10 sm:py-16 relative">
  <div class="max-w-4xl mx-auto">
    <h2 class="text-center text-2xl sm:text-3xl md:text-4xl font-extrabold tracking-tight">Three steps. Tapos na.</h2>
    <div class="step-grid mt-8 sm:mt-12">
      <div class="glass rounded-3xl p-6 sm:p-8 text-center flex flex-col items-center">
        <div class="text-4xl sm:text-4xl mb-2">📍</div>
        <div class="mt-2 font-bold text-lg sm:text-xl text-white">1. Pin & book</div>
        <div class="text-sm sm:text-base text-brand-mist mt-2 leading-relaxed">Map-based pickup and dropoff. Fare auto-computed.</div>
      </div>
      <div class="glass rounded-3xl p-6 sm:p-8 text-center flex flex-col items-center">
        <div class="text-4xl sm:text-4xl mb-2">🚚</div>
        <div class="mt-2 font-bold text-lg sm:text-xl text-white">2. Driver accepts</div>
        <div class="text-sm sm:text-base text-brand-mist mt-2 leading-relaxed">Nearest rider grabs your parcel in seconds.</div>
      </div>
      <div class="glass rounded-3xl p-6 sm:p-8 text-center flex flex-col items-center">
        <div class="text-4xl sm:text-4xl mb-2">✅</div>
        <div class="mt-2 font-bold text-lg sm:text-xl text-white">3. Delivered</div>
        <div class="text-sm sm:text-base text-brand-mist mt-2 leading-relaxed">POD + receipt. Rate your driver after completion.</div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="px-4 sm:px-5 py-10 sm:py-16">
  <div class="max-w-4xl mx-auto glass-strong rounded-[2rem] p-8 sm:p-12 shadow-glow text-center flex flex-col items-center relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-brand-sky/10 to-transparent pointer-events-none"></div>
    <h3 class="text-2xl sm:text-3xl md:text-4xl font-extrabold tracking-tight relative z-10">Ready to Drop IT?</h3>
    <p class="mt-3 text-base sm:text-lg text-brand-mist px-2 max-w-md mx-auto relative z-10">Sign up in under a minute. Wallet + OTP verified.</p>
    <div class="cta-btns mt-8 sm:mt-10 w-full sm:w-auto relative z-10">
      <a href="<?= url('signup/customer') ?>" class="btn btn-primary !py-3.5 !px-8 text-base shadow-lg shadow-brand-blue/30 justify-center">Create account</a>
      <a href="<?= url('signup/driver') ?>" class="btn btn-ghost !py-3.5 !px-8 text-base justify-center">Drive with us</a>
    </div>
  </div>
</section>

<footer class="px-5 pb-10">
  <div class="max-w-5xl mx-auto flex flex-col items-center gap-2">
    <img src="<?= url('resources/textlogo.png') ?>" alt="Drop IT" class="h-7 sm:h-9 opacity-80">
    <div class="text-[11px] text-brand-mist text-center">© <?= date('Y') ?> Drop IT · Built for Pinoy parcels</div>
  </div>
</footer>

<script>
(function(){
  const tog = document.getElementById('navToggle');
  const menu = document.getElementById('mobileMenu');
  if (!tog || !menu) return;
  tog.addEventListener('click', () => {
    menu.classList.toggle('open');
    tog.classList.toggle('open');
  });
  // Close on link click or outside tap
  menu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
    menu.classList.remove('open');
    tog.classList.remove('open');
  }));
  document.addEventListener('click', e => {
    if (!menu.contains(e.target) && !tog.contains(e.target)) {
      menu.classList.remove('open');
      tog.classList.remove('open');
    }
  });
})();
</script>

<?php include __DIR__ . '/partials/admin_trigger.php'; ?>
</body></html>
