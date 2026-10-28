<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$keys = ['admin_email','admin_phone','smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','paymongo_secret_key','paymongo_public_key'];
foreach ($keys as $k) {
    if (array_key_exists($k, $_POST)) {
        set_setting($k, trim((string)$_POST[$k]));
    }
}

foreach (['fare_base', 'fare_per_km'] as $fk) {
    if (array_key_exists($fk, $_POST)) {
        $v = max(0.0, (float) $_POST[$fk]);
        set_setting($fk, number_format($v, 2, '.', ''));
    }
}

db()->table('activity_logs')->insert([
    'user_id' => $_SESSION['user_id'],
    'action'  => 'settings_update',
    'details' => "Admin updated notification settings.",
]);

set_flash('success', 'Settings saved.');
header('Location: ' . url('admin/settings'));
exit;
