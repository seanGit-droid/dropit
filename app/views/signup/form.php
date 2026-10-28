<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$role = ($role ?? '') === 'driver' ? 'driver' : 'customer';
$page_title = 'Sign up as ' . ucfirst($role);
include __DIR__ . '/../partials/head.php';
?>
<main class="min-h-screen flex flex-col items-center px-5 py-8">
  <a href="<?= url('signup') ?>" class="self-start text-sm text-brand-mist mb-2">← Back</a>

  <div class="w-full max-w-md glass-strong rounded-3xl p-7 shadow-glass">
    <div class="flex items-center gap-3">
      <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-azure to-brand-blue flex items-center justify-center text-2xl">
        <?= $role === 'driver' ? '🚚' : '📦' ?>
      </div>
      <div>
        <div class="text-xs text-brand-mist">Signing up as</div>
        <h1 class="text-xl font-bold"><?= ucfirst($role) ?></h1>
      </div>
    </div>

    <div class="mt-4"><?php include __DIR__ . '/../partials/flash.php'; ?></div>

    <form id="signupForm" method="post" action="<?= url('signup/'.$role) ?>" class="mt-5 space-y-4">
      <?php (new Router())->csrf_field(); ?>
      <input type="hidden" name="role" value="<?= $role ?>">

      <div>
        <label class="label">Full name</label>
        <input class="input" name="full_name" required>
      </div>
      <div>
        <label class="label">Username</label>
        <input class="input" name="username" required pattern="[a-zA-Z0-9_]{3,30}" title="3-30 letters/digits/underscores">
      </div>

      <div>
        <label class="label">Phone (+63)</label>
        <input class="input" id="phone" name="phone" placeholder="09XXXXXXXXX" pattern="[0-9+]{10,15}" required>
      </div>

      <!-- Email + OTP (only email is verified) -->
      <div>
        <label class="label">Email</label>
        <div class="flex gap-2">
          <input class="input flex-1" id="email" name="email" type="email" required>
          <button type="button" id="sendEmailOtp" class="btn btn-ghost whitespace-nowrap text-sm">Send OTP</button>
        </div>
        <div id="emailOtpBox" class="hidden mt-2 flex gap-2">
          <input class="input flex-1 font-mono" id="emailOtp" placeholder="6-digit code" maxlength="6">
          <button type="button" id="verifyEmailOtp" class="btn btn-primary text-sm">Verify</button>
        </div>
        <div id="emailStatus" class="text-xs mt-1 text-brand-mist"></div>
        <input type="hidden" name="email_verified" id="emailVerifiedFlag" value="0">
      </div>

      <div>
        <label class="label">Password</label>
        <input class="input" name="password" type="password" required minlength="6" placeholder="Min. 6 characters">
      </div>
      <div>
        <label class="label">Confirm password</label>
        <input class="input" name="password_confirm" type="password" required minlength="6">
      </div>

      <button id="submitBtn" type="submit" class="btn btn-primary w-full" disabled>Create account</button>
      <p class="text-xs text-brand-mist text-center">Verify email to enable this button.</p>
    </form>
  </div>
</main>

<script>
(function(){
  const csrf = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
  let emailOK = false;
  function updateSubmit(){ document.getElementById('submitBtn').disabled = !emailOK; }

  async function apiSend(identifier, statusEl) {
    statusEl.textContent = 'Sending OTP…';
    statusEl.className = 'text-xs mt-1 text-brand-mist';
    try {
      const r = await fetch('<?= url('signup/otp/send') ?>', {
        method:'POST',
        body: new URLSearchParams({ purpose:'email', identifier, csrf_token: csrf }),
      });
      const d = await r.json();
      if (!d.ok) { statusEl.textContent = '✗ ' + (d.error || 'failed'); statusEl.className='text-xs mt-1 text-red-300'; return; }
      statusEl.textContent = '✓ OTP sent to ' + identifier + ' — check your inbox.';
      statusEl.className = 'text-xs mt-1 text-emerald-300';
    } catch(e){ statusEl.textContent = '✗ network error'; statusEl.className='text-xs mt-1 text-red-300'; }
  }
  async function apiVerify(identifier, code, statusEl, onOK) {
    try {
      const r = await fetch('<?= url('signup/otp/verify') ?>', {
        method:'POST',
        body: new URLSearchParams({ purpose:'email', identifier, code, csrf_token: csrf }),
      });
      const d = await r.json();
      if (!d.ok) { statusEl.textContent = '✗ ' + (d.error || 'invalid'); statusEl.className='text-xs mt-1 text-red-300'; return; }
      statusEl.textContent = '✓ Verified';
      statusEl.className = 'text-xs mt-1 text-emerald-300 font-semibold';
      onOK();
    } catch(e){ statusEl.textContent = '✗ network error'; statusEl.className='text-xs mt-1 text-red-300'; }
  }

  document.getElementById('sendEmailOtp').onclick = () => {
    const v = document.getElementById('email').value.trim();
    if (!v) return alert('Enter email first');
    document.getElementById('emailOtpBox').classList.remove('hidden');
    apiSend(v, document.getElementById('emailStatus'));
  };
  document.getElementById('verifyEmailOtp').onclick = () => {
    const email = document.getElementById('email').value.trim();
    const code = document.getElementById('emailOtp').value.trim();
    if (!code) return;
    apiVerify(email, code, document.getElementById('emailStatus'), () => {
      emailOK = true;
      document.getElementById('emailVerifiedFlag').value = '1';
      document.getElementById('email').readOnly = true;
      updateSubmit();
    });
  };
})();
</script>
</body></html>
