<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$uid = (int)($id ?? 0);
if ($uid <= 0 || $uid === (int)$_SESSION['user_id']) {
    set_flash('error', 'Cannot delete this account.');
    header('Location: ' . url('admin/accounts'));
    exit;
}
$user = db()->table('users')->select('username')->where('id', $uid)->get();
if ($user) {
    db()->table('users')->where('id', $uid)->delete();
    db()->table('activity_logs')->insert([
        'user_id' => $_SESSION['user_id'],
        'action'  => 'account_delete',
        'details' => "Admin deleted account '{$user['username']}' (id {$uid}).",
    ]);
    set_flash('success', 'Account deleted.');
}
header('Location: ' . url('admin/accounts'));
exit;
