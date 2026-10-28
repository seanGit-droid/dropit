<?php
/**
 * One-time: apply Brevo SMTP credentials to the settings table.
 * Run once after schema import:
 *   C:\xampp\php\php.exe database\apply_brevo_smtp.php
 */
require_once __DIR__ . '/../config.php';
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/scheme/Database.php';

$rows = [
    'smtp_host' => 'smtp-relay.brevo.com',
    'smtp_port' => '587',
    'smtp_user' => getenv('SMTP_USER') ?: '',
    'smtp_pass' => getenv('SMTP_PASS') ?: '',
    'smtp_from' => getenv('SMTP_FROM') ?: 'Drop IT <no-reply@dropit.app>',
    'admin_email' => getenv('ADMIN_EMAIL') ?: '',
];

$db = new Database();
foreach ($rows as $k => $v) {
    $existing = $db->table('settings')->select('setting_key')->where('setting_key', $k)->get();
    if ($existing) {
        $db->table('settings')->where('setting_key', $k)->update(['setting_value' => $v]);
    } else {
        $db->table('settings')->insert(['setting_key' => $k, 'setting_value' => $v]);
    }
    echo "Set {$k}\n";
}
echo "Done. SMTP configured for Brevo.\n";
