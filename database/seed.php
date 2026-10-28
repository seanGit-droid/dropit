<?php
/**
 * Seed admin + sample drivers/customers/parcels for Drop IT
 * Run: "C:\xampp\php\php.exe" database/seed.php
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config.php';
require_once APP_ROOT . '/scheme/Database.php';

$db = new Database();

$admin_pw = password_hash('DropITJACS', PASSWORD_DEFAULT);
$pw = password_hash('password123', PASSWORD_DEFAULT);

// Wipe (preserves schema)
$db->raw('SET FOREIGN_KEY_CHECKS=0');
$db->raw('TRUNCATE TABLE wallet_transactions');
$db->raw('TRUNCATE TABLE otps');
$db->raw('TRUNCATE TABLE notification_log');
$db->raw('TRUNCATE TABLE driver_locations');
$db->raw('TRUNCATE TABLE activity_logs');
$db->raw('TRUNCATE TABLE notifications');
$db->raw('TRUNCATE TABLE reports');
$db->raw('TRUNCATE TABLE parcel_history');
$db->raw('TRUNCATE TABLE parcels');
$db->raw('TRUNCATE TABLE users');
$db->raw('SET FOREIGN_KEY_CHECKS=1');

// Admin
$db->table('users')->insert([
    'username'       => 'admin_dropit',
    'email'          => 'admin@drop-it.dev',
    'password'       => $admin_pw,
    'role'           => 'admin',
    'full_name'      => 'Drop IT Admin',
    'phone'          => '09000000000',
    'phone_verified' => 1,
    'email_verified' => 1,
]);

// Drivers
$drivers = [
    ['driver_juan', 'juan@dropit.app', 'Juan dela Cruz', '09170000001'],
    ['driver_pedro', 'pedro@dropit.app', 'Pedro Santos', '09170000002'],
    ['driver_maria', 'maria@dropit.app', 'Maria Reyes', '09170000003'],
];
foreach ($drivers as $d) {
    $db->table('users')->insert([
        'username'       => $d[0],
        'email'          => $d[1],
        'password'       => $pw,
        'role'           => 'driver',
        'full_name'      => $d[2],
        'phone'          => $d[3],
        'phone_verified' => 1,
        'email_verified' => 1,
    ]);
}

// Customers (with starting wallet balance for simulation)
$customers = [
    ['customer_ana', 'ana@mail.com', 'Ana Lopez', '09180000001'],
    ['customer_ben', 'ben@mail.com', 'Ben Ramos', '09180000002'],
];
foreach ($customers as $c) {
    $db->table('users')->insert([
        'username'       => $c[0],
        'email'          => $c[1],
        'password'       => $pw,
        'role'           => 'customer',
        'full_name'      => $c[2],
        'phone'          => $c[3],
        'wallet_balance' => 500.00,
        'phone_verified' => 1,
        'email_verified' => 1,
    ]);
}

// Default notification settings (email-only; SMS feature removed).
$settings = [
    'admin_email' => 'admin@drop-it.dev',
    'admin_phone' => '09000000000',
    'smtp_host'   => '',
    'smtp_port'   => '587',
    'smtp_user'   => '',
    'smtp_pass'   => '',
    'smtp_from'   => 'no-reply@drop-it.dev',
];
$db->raw('TRUNCATE TABLE settings');
foreach ($settings as $k => $v) {
    $db->table('settings')->insert(['setting_key' => $k, 'setting_value' => $v]);
}

echo "Seed complete.\n";
echo "Admin login:    admin@drop-it.dev / DropITJACS\n";
echo "Driver login:   juan@dropit.app   / password123\n";
echo "Customer login: ana@mail.com      / password123\n";
