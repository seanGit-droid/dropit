<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$role = ($role ?? '') === 'driver' ? 'driver' : 'customer';

$full_name = trim($_POST['full_name'] ?? '');
$username  = trim($_POST['username']  ?? '');
$phone     = trim($_POST['phone']     ?? '');
$email     = trim($_POST['email']     ?? '');
$pw        = $_POST['password']         ?? '';
$pw2       = $_POST['password_confirm'] ?? '';

if ($full_name === '' || $username === '' || $phone === '' || $email === '') {
    set_flash('error', 'All fields are required.'); header('Location: '.url('signup/'.$role)); exit;
}
if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
    set_flash('error', 'Invalid username.'); header('Location: '.url('signup/'.$role)); exit;
}
if (strlen($pw) < 6 || $pw !== $pw2) {
    set_flash('error', 'Passwords must match and be at least 6 characters.'); header('Location: '.url('signup/'.$role)); exit;
}

// uniqueness
if (db()->table('users')->select('id')->where('email', $email)->get()) {
    set_flash('error', 'Email already registered.'); header('Location: '.url('signup/'.$role)); exit;
}
if (db()->table('users')->select('id')->where('username', $username)->get()) {
    set_flash('error', 'Username taken.'); header('Location: '.url('signup/'.$role)); exit;
}
if (db()->table('users')->select('id')->where('phone', $phone)->get()) {
    set_flash('error', 'Phone already registered.'); header('Location: '.url('signup/'.$role)); exit;
}

// OTP verification - email only (SMS disabled globally; phone auto-accepted).
$sid = session_id();
$email_ok = db()->table('otps')->where('session_id',$sid)->where('identifier',$email)->where('purpose','email')->where('verified',1)->count();
if (!$email_ok) {
    set_flash('error', 'Please verify your email first.'); header('Location: '.url('signup/'.$role)); exit;
}

$new_id = db()->table('users')->insert([
    'username'       => $username,
    'email'          => $email,
    'password'       => password_hash($pw, PASSWORD_DEFAULT),
    'role'           => $role,
    'full_name'      => $full_name,
    'phone'          => $phone,
    'phone_verified' => 1,
    'email_verified' => 1,
    'wallet_balance' => $role === 'customer' ? 500.00 : 0.00, // starter balance for sim
]);

db()->table('activity_logs')->insert([
    'user_id' => $new_id,
    'action'  => 'signup',
    'details' => "New {$role} account: {$username} ({$full_name}).",
]);

// clean up used OTPs
db()->table('otps')->where('session_id', $sid)->delete();

// auto-login
$_SESSION['user_id']   = $new_id;
$_SESSION['username']  = $username;
$_SESSION['full_name'] = $full_name;
$_SESSION['role']      = $role;

set_flash('success', 'Account created. Welcome to Drop IT!');
header('Location: ' . url(($role === 'driver' ? 'driver' : 'customer') . '/dashboard'));
exit;
