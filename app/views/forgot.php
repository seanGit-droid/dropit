<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Forgot password';
include __DIR__ . '/partials/head.php';
?>
<main class="min-h-screen flex flex-col items-center justify-center px-5 py-10">
  <div class="flex items-center gap-3 mb-6 select-none">
    <img src="<?= url('resources/weblogo.png') ?>" alt="Drop IT" class="w-12 h-12 rounded-2xl ring-1 ring-white/30 bg-white/10 p-1.5">
    <span class="font-bold tracking-tight text-2xl">Drop IT</span>
  </div>

  <div class="w-full max-w-md glass-strong rounded-3xl p-7 shadow-glass">
    <h1 class="text-2xl font-bold tracking-tight">Reset your password</h1>
    <p class="text-sm text-brand-mist mt-1">We'll email you a 6-digit code, then let you set a new password.</p>

    <div class="mt-5"><?php include __DIR__ . '/partials/flash.php'; ?></div>

    <!-- Step 1: enter email -->
    <section id="step1" class="mt-4 space-y-4">
      <div>
        <label class="label" for="fpEmail">Email</label>
        <input class="input" id="fpEmail" type="email" placeholder="you@example.com" autocomplete="email" required>
      </div>
      <button id="fpSendBtn" type="button" class="btn btn-primary w-full">Send reset code</button>
    </section>

    <!-- Step 2: verify OTP -->
    <section id="step2" class="mt-4 space-y-4 hidden">
      <div class="text-xs text-brand-mist">Code sent to <span id="fpEmailEcho" class="font-semibold"></span>. Check spam if it doesn't arrive.</div>
      <div>
        <label class="label" for="fpCode">6-digit code</label>
        <input class="input tracking-[.4em] text-center text-xl" id="fpCode" inputmode="numeric" maxlength="6" pattern="\d{6}" placeholder="000000">
      </div>
      <div class="flex gap-2">
        <button id="fpVerifyBtn" type="button" class="btn btn-primary flex-1">Verify code</button>
        <button id="fpResendBtn" type="button" class="btn btn-ghost text-xs">Resend</button>
      </div>
    </section>

    <!-- Step 3: new password -->
    <section id="step3" class="mt-4 space-y-4 hidden">
      <div>
        <label class="label" for="fpNew">New password</label>
        <input class="input" id="fpNew" type="password" placeholder="At least 8 characters" minlength="8" autocomplete="new-password">
      </div>
      <div>
        <label class="label" for="fpConfirm">Confirm new password</label>
        <input class="input" id="fpConfirm" type="password" placeholder="Re-type to confirm" minlength="8" autocomplete="new-password">
      </div>
      <button id="fpResetBtn" type="button" class="btn btn-primary w-full">Reset password</button>
    </section>

    <div class="mt-6 pt-5 border-t border-white/10 text-center">
      <a href="<?= url('login') ?>" class="text-sm text-brand-mist hover:text-white">← Back to login</a>
    </div>
  </div>
</main>

<script>
(function(){
  const csrf = <?= json_encode(csrf_token()) ?>;
  const post = async (url, data) => {
    const body = new URLSearchParams({ ...data, csrf_token: csrf });
    const r = await fetch(url, { method: 'POST', body, credentials: 'same-origin' });
    return r.json().catch(() => ({ ok: false, error: 'Bad response' }));
  };
  const show = id => { document.getElementById(id).classList.remove('hidden'); };
  const hide = id => { document.getElementById(id).classList.add('hidden'); };
  const toast = (msg, type='info') => window.DropIT ? DropIT.toast(msg, type) : alert(msg);

  let storedEmail = '';

  document.getElementById('fpSendBtn').onclick = async () => {
    const email = document.getElementById('fpEmail').value.trim();
    if (!email) return toast('Enter your email', 'error');
    const btn = document.getElementById('fpSendBtn');
    btn.disabled = true; btn.textContent = 'Sending…';
    const r = await post('<?= url('forgot/send') ?>', { email });
    btn.disabled = false; btn.textContent = 'Send reset code';
    if (!r.ok) return toast(r.error || 'Could not send code', 'error');
    storedEmail = email;
    document.getElementById('fpEmailEcho').textContent = email;
    hide('step1'); show('step2');
    toast('Code sent — check your inbox.', 'info');
  };

  document.getElementById('fpResendBtn').onclick = async () => {
    if (!storedEmail) return;
    const r = await post('<?= url('forgot/send') ?>', { email: storedEmail });
    toast(r.ok ? 'Code re-sent.' : (r.error || 'Could not resend'), r.ok ? 'info' : 'error');
  };

  document.getElementById('fpVerifyBtn').onclick = async () => {
    const code = document.getElementById('fpCode').value.trim();
    if (!/^\d{6}$/.test(code)) return toast('Enter the 6-digit code', 'error');
    const r = await post('<?= url('forgot/verify') ?>', { email: storedEmail, code });
    if (!r.ok) return toast(r.error || 'Invalid code', 'error');
    hide('step2'); show('step3');
  };

  document.getElementById('fpResetBtn').onclick = async () => {
    const p1 = document.getElementById('fpNew').value;
    const p2 = document.getElementById('fpConfirm').value;
    if (p1.length < 8) return toast('Password must be at least 8 characters', 'error');
    if (p1 !== p2)     return toast('Passwords do not match', 'error');
    const r = await post('<?= url('forgot/reset') ?>', { email: storedEmail, password: p1 });
    if (!r.ok) return toast(r.error || 'Could not reset password', 'error');
    toast('Password reset! Redirecting to login…', 'info');
    setTimeout(() => location.href = '<?= url('login') ?>', 1200);
  };
})();
</script>
</body></html>
