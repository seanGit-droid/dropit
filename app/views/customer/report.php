<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$page_title = 'Report an Issue';
$active = '';
include __DIR__ . '/../partials/head.php';
?>
<main class="mx-auto max-w-md px-4 pt-6 pb-28">
  <a href="<?= url('customer/dashboard') ?>" class="text-sm text-brand-mist">← Back</a>
  <h1 class="text-2xl font-bold mt-2">Report an Issue</h1>
  <p class="text-sm text-brand-mist">We'll review it and get back to you.</p>

  <div class="mt-4"><?php include __DIR__ . '/../partials/flash.php'; ?></div>

  <form method="post" action="<?= url('customer/report') ?>" class="mt-4 glass rounded-2xl p-5 space-y-4">
    <?php (new Router())->csrf_field(); ?>
    <div>
      <label class="label">Subject</label>
      <input class="input" name="subject" required placeholder="e.g. Parcel damaged on arrival">
    </div>
    <div>
      <label class="label">Message</label>
      <textarea class="input" name="message" rows="5" required placeholder="Describe the issue..."></textarea>
    </div>
    <button class="btn btn-primary w-full">Send report</button>
  </form>
</main>
<?php include __DIR__ . '/../partials/mobile_nav.php'; ?>
</body></html>
