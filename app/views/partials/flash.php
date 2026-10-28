<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$ok = get_flash('success');
$err = get_flash('error');
?>
<?php if ($ok): ?>
  <div class="glass rounded-xl p-3 mb-4 flex items-start gap-3 ring-1 ring-emerald-300/30">
    <span class="text-emerald-300">✓</span>
    <p class="text-sm"><?= esc($ok) ?></p>
  </div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="glass rounded-xl p-3 mb-4 flex items-start gap-3 ring-1 ring-red-300/30">
    <span class="text-red-300">!</span>
    <p class="text-sm"><?= esc($err) ?></p>
  </div>
<?php endif; ?>
