<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$uid = $_SESSION['user_id'];
$role = $_SESSION['role'];

$fields = [
    'username'  => trim($_POST['username'] ?? ''),
    'email'     => trim($_POST['email'] ?? ''),
    'full_name' => trim($_POST['full_name'] ?? ''),
    'phone'     => trim($_POST['phone'] ?? ''),
];

if ($fields['username'] === '' || $fields['email'] === '') {
    set_flash('error', 'Username and email are required.');
    header('Location: ' . url($role . '/profile'));
    exit;
}

// uniqueness check (allow own record)
$dup = db()->table('users')
    ->select('id')
    ->where('email', $fields['email'])
    ->where('id', '!=', $uid)
    ->get();
if ($dup) {
    set_flash('error', 'Email already in use.');
    header('Location: ' . url($role . '/profile'));
    exit;
}

$pw = $_POST['password'] ?? '';
if ($pw !== '') {
    if (strlen($pw) < 6) {
        set_flash('error', 'Password must be at least 6 characters.');
        header('Location: ' . url($role . '/profile'));
        exit;
    }
    $fields['password'] = password_hash($pw, PASSWORD_DEFAULT);
}

db()->table('users')->where('id', $uid)->update($fields);

$_SESSION['username'] = $fields['username'];
$_SESSION['full_name'] = $fields['full_name'];

db()->table('activity_logs')->insert([
    'user_id' => $uid,
    'action'  => 'profile_update',
    'details' => "{$fields['username']} updated their profile.",
]);

set_flash('success', 'Profile updated successfully.');
header('Location: ' . url($role . '/profile'));
exit;
