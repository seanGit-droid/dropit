<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$is_update = isset($id);
$uid = $is_update ? (int)$id : null;

$data = [
    'username'  => trim($_POST['username'] ?? ''),
    'email'     => trim($_POST['email'] ?? ''),
    'full_name' => trim($_POST['full_name'] ?? ''),
    'phone'     => trim($_POST['phone'] ?? ''),
    'role'      => $_POST['role'] ?? 'customer',
    'is_active' => (int)($_POST['is_active'] ?? 1),
];

if (!in_array($data['role'], ['admin','customer','driver'])) {
    set_flash('error', 'Invalid role.');
    header('Location: ' . url('admin/accounts'));
    exit;
}
if ($data['username'] === '' || $data['email'] === '') {
    set_flash('error', 'Username and email are required.');
    header('Location: ' . url('admin/accounts'));
    exit;
}

// uniqueness
$q = db()->table('users')->select('id')->where('email', $data['email']);
if ($is_update) $q = $q->where('id', '!=', $uid);
if ($q->get()) {
    set_flash('error', 'Email already in use.');
    header('Location: ' . url('admin/accounts'));
    exit;
}

$pw = $_POST['password'] ?? '';
if (!$is_update) {
    if (strlen($pw) < 6) {
        set_flash('error', 'Password must be at least 6 characters.');
        header('Location: ' . url('admin/accounts/new'));
        exit;
    }
    $data['password'] = password_hash($pw, PASSWORD_DEFAULT);
    $new_id = db()->table('users')->insert($data);
    db()->table('activity_logs')->insert([
        'user_id' => $_SESSION['user_id'],
        'action'  => 'account_create',
        'details' => "Admin created {$data['role']} account '{$data['username']}'.",
    ]);
    set_flash('success', 'Account created.');
} else {
    if ($pw !== '') {
        if (strlen($pw) < 6) {
            set_flash('error', 'Password must be at least 6 characters.');
            header('Location: ' . url('admin/accounts/edit/'.$uid));
            exit;
        }
        $data['password'] = password_hash($pw, PASSWORD_DEFAULT);
    }
    db()->table('users')->where('id', $uid)->update($data);
    db()->table('activity_logs')->insert([
        'user_id' => $_SESSION['user_id'],
        'action'  => 'account_update',
        'details' => "Admin updated account '{$data['username']}' (id {$uid}).",
    ]);
    set_flash('success', 'Account updated.');
}
header('Location: ' . url('admin/accounts'));
exit;
