<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Settings';
$active = 'settings';

$keys = ['admin_email','admin_phone','smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','paymongo_secret_key','paymongo_public_key','fare_base','fare_per_km'];
$vals = [];
foreach ($keys as $k) $vals[$k] = setting($k, '');
if ($vals['fare_base'] === '' || $vals['fare_base'] === null) $vals['fare_base'] = '30.00';
if ($vals['fare_per_km'] === '' || $vals['fare_per_km'] === null) $vals['fare_per_km'] = '3.00';

include __DIR__ . '/../partials/head.php';
?>
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-8 max-w-3xl">
    <h1 class="text-2xl md:text-3xl font-bold">Notification Settings</h1>
    <p class="text-sm text-brand-mist">Configure how Drop IT sends email alerts. Leave SMTP host blank to run in dry-run mode (notifications are logged but not transmitted).</p>

    <div class="mt-4"><?php include __DIR__ . '/../partials/flash.php'; ?></div>

    <form method="post" action="<?= url('admin/settings') ?>" class="mt-4 space-y-5">
      <?php (new Router())->csrf_field(); ?>

      <section class="glass-strong rounded-3xl p-6 shadow-glass">
        <h2 class="font-semibold">📧 Admin notifications</h2>
        <p class="text-xs text-brand-mist mt-1">Where new-order alerts are sent.</p>
        <div class="mt-4 grid md:grid-cols-2 gap-4">
          <div>
            <label class="label">Admin email</label>
            <input class="input" name="admin_email" type="email" value="<?= esc($vals['admin_email']) ?>">
          </div>
          <div>
            <label class="label">Admin phone (+63)</label>
            <input class="input" name="admin_phone" value="<?= esc($vals['admin_phone']) ?>" placeholder="09XXXXXXXXX">
          </div>
        </div>
      </section>

      <section class="glass rounded-3xl p-6 shadow-glass">
        <h2 class="font-semibold">✉️ Email — SMTP</h2>
        <p class="text-xs text-brand-mist mt-1">Uses SMTP AUTH LOGIN with STARTTLS on port 587. Tested with <span class="font-mono">smtp-relay.brevo.com</span>.</p>
        <div class="mt-4 grid md:grid-cols-2 gap-4">
          <div>
            <label class="label">SMTP host</label>
            <input class="input" name="smtp_host" value="<?= esc($vals['smtp_host']) ?>" placeholder="smtp-relay.brevo.com">
          </div>
          <div>
            <label class="label">SMTP port</label>
            <input class="input" name="smtp_port" value="<?= esc($vals['smtp_port']) ?>" placeholder="587">
          </div>
          <div>
            <label class="label">SMTP username</label>
            <input class="input" name="smtp_user" value="<?= esc($vals['smtp_user']) ?>" placeholder="xxxxx@smtp-brevo.com">
          </div>
          <div>
            <label class="label">SMTP password / API key</label>
            <input class="input" name="smtp_pass" type="password" value="<?= esc($vals['smtp_pass']) ?>" placeholder="xsmtpsib-…">
          </div>
          <div class="md:col-span-2">
            <label class="label">From address</label>
            <input class="input" name="smtp_from" value="<?= esc($vals['smtp_from']) ?>" placeholder="Drop IT &lt;no-reply@dropit.app&gt;">
          </div>
        </div>
      </section>

      <section class="glass rounded-3xl p-6 shadow-glass">
        <h2 class="font-semibold">🛺 Fare configuration</h2>
        <p class="text-xs text-brand-mist mt-1">Tricycle pricing used for every order. Total fare = base + (per-km × distance), minimum is the base. 10% platform / 90% driver split applies on top.</p>
        <div class="mt-4 grid md:grid-cols-2 gap-4">
          <div>
            <label class="label">Base fare (₱)</label>
            <input class="input" name="fare_base" type="number" step="0.01" min="0" value="<?= esc($vals['fare_base']) ?>" placeholder="30.00">
          </div>
          <div>
            <label class="label">Rate per km (₱/km)</label>
            <input class="input" name="fare_per_km" type="number" step="0.01" min="0" value="<?= esc($vals['fare_per_km']) ?>" placeholder="3.00">
          </div>
        </div>
        <p class="text-[11px] text-brand-mist mt-2">Example: at base ₱<?= esc($vals['fare_base']) ?> + ₱<?= esc($vals['fare_per_km']) ?>/km, a 5 km trip costs ₱<?= number_format(((float)$vals['fare_base']) + ((float)$vals['fare_per_km']) * 5, 2) ?>.</p>
      </section>

      <section class="glass rounded-3xl p-6 shadow-glass">
        <h2 class="font-semibold">💳 PayMongo — GCash</h2>
        <p class="text-xs text-brand-mist mt-1">Free sandbox keys at <span class="font-mono">dashboard.paymongo.com</span>. Leave blank to use simulated payments (auto-marks as paid for demos).</p>
        <div class="mt-4 grid md:grid-cols-2 gap-4">
          <div>
            <label class="label">Secret key (sk_test_…)</label>
            <input class="input" name="paymongo_secret_key" value="<?= esc($vals['paymongo_secret_key']) ?>" placeholder="sk_test_xxx">
          </div>
          <div>
            <label class="label">Public key (pk_test_…)</label>
            <input class="input" name="paymongo_public_key" value="<?= esc($vals['paymongo_public_key']) ?>" placeholder="pk_test_xxx">
          </div>
        </div>
      </section>

      <button class="btn btn-primary">Save settings</button>
    </form>
  </main>
</div>
</body></html>
