<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Create Account';
include __DIR__ . '/../partials/head.php';
?>
<main class="min-h-screen flex flex-col items-center justify-center px-5 py-10">
  <a href="<?= url('/') ?>" class="flex items-center gap-3 mb-6">
    <img src="<?= url('resources/weblogo.png') ?>" alt="Drop IT" class="w-12 h-12 object-contain">
    <span class="font-bold tracking-tight text-2xl">Drop IT</span>
  </a>

  <div class="w-full max-w-md glass-strong rounded-3xl p-7 shadow-glass">
    <h1 class="text-2xl font-bold tracking-tight">Create account</h1>
    <p class="text-sm text-brand-mist mt-1">Sign up as a customer or driver.</p>

    <div class="mt-6 grid grid-cols-2 gap-3">
      <a href="<?= url('signup/customer') ?>" class="glass rounded-2xl p-6 text-center hover:bg-white/15 transition">
        <div class="text-4xl">📦</div>
        <div class="mt-3 font-semibold">Customer</div>
        <div class="text-xs text-brand-mist mt-1">Send parcels</div>
      </a>
      <a href="<?= url('signup/driver') ?>" class="glass rounded-2xl p-6 text-center hover:bg-white/15 transition">
        <div class="text-4xl">🚚</div>
        <div class="mt-3 font-semibold">Driver</div>
        <div class="text-xs text-brand-mist mt-1">Earn by delivering</div>
      </a>
    </div>

    <div class="mt-6 pt-5 border-t border-white/10 text-center">
      <p class="text-sm text-brand-mist">Already have an account?</p>
      <a href="<?= url('login') ?>" class="btn btn-ghost w-full mt-2">Login</a>
    </div>
  </div>
</main>
</body></html>
