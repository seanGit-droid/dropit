<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$admin_mode = !empty($_POST['admin_mode']);

if ($email === '' || $password === '') {
    set_flash('error', 'Please enter email and password.');
    header('Location: ' . url('login'));
    exit;
}

$user = db()->table('users')
    ->select('id, username, email, password, role, full_name, is_active')
    ->where('email', $email)
    ->get();

if (!$user || !password_verify($password, $user['password'])) {
    set_flash('error', 'Invalid email or password.');
    header('Location: ' . url('login'));
    exit;
}

if ($user['role'] === 'admin' && !$admin_mode) {
    set_flash('error', 'Invalid email or password.');
    header('Location: ' . url('login'));
    exit;
}

if ((int)$user['is_active'] !== 1) {
    set_flash('error', 'Your account is disabled. Contact admin.');
    header('Location: ' . url('login'));
    exit;
}

$_SESSION['user_id']   = $user['id'];
$_SESSION['username']  = $user['username'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'];

db()->table('activity_logs')->insert([
    'user_id' => $user['id'],
    'action'  => 'login',
    'details' => "User {$user['username']} logged in.",
]);

$dest = match ($user['role']) {
    'admin'    => 'admin/dashboard',
    'driver'   => 'driver/dashboard',
    'customer' => 'customer/dashboard',
    default    => '/',
};
header('Location: ' . url($dest));
exit;
