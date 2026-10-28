<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Login';
include __DIR__ . '/partials/head.php';
?>
<main class="min-h-screen flex flex-col items-center justify-center px-5 py-10">
  <div class="flex items-center gap-3 mb-6 select-none cursor-default" data-admin-logo>
    <img src="<?= url('resources/weblogo.png') ?>" alt="Drop IT" class="w-12 h-12 object-contain select-none" draggable="false">
    <span class="font-bold tracking-tight text-2xl">Drop IT</span>
  </div>

  <div class="w-full max-w-md glass-strong rounded-3xl p-7 shadow-glass">
    <div id="normalMode">
      <h1 class="text-2xl font-bold tracking-tight">Welcome back</h1>
      <p class="text-sm text-brand-mist mt-1">Sign in to continue.</p>
    </div>

    <div class="mt-5">
      <?php include __DIR__ . '/partials/flash.php'; ?>
    </div>

    <form method="post" action="<?= url('login') ?>" class="space-y-4" id="loginForm">
      <?php (new Router())->csrf_field(); ?>
      <div>
        <label class="label" for="email">Email</label>
        <input class="input" id="email" name="email" type="email" placeholder="you@dropit.app" required autocomplete="email">
      </div>
      <div>
        <div class="flex items-center justify-between">
          <label class="label !mb-0" for="password">Password</label>
          <a href="<?= url('forgot') ?>" class="text-xs text-brand-sky hover:underline">Forgot password?</a>
        </div>
        <input class="input mt-1" id="password" name="password" type="password" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary w-full">Login</button>
    </form>

    <div class="mt-6 pt-5 border-t border-white/10 text-center" id="signupFooter">
      <p class="text-sm text-brand-mist">New here?</p>
      <a href="<?= url('signup') ?>" class="btn btn-ghost w-full mt-2">Create account</a>
    </div>
  </div>

  <a href="<?= url('/') ?>" class="mt-6 text-sm text-brand-mist hover:text-white">← Back to home</a>
</main>

<?php include __DIR__ . '/partials/admin_trigger.php'; ?>
</body></html>
