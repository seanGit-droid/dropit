<?php defined('APP_ROOT') OR exit('No direct script access allowed');
if (!empty($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $reason = ($_POST['reason'] ?? '') === 'idle' ? 'idle_logout' : 'logout';
    db()->table('activity_logs')->insert([
        'user_id' => $uid,
        'action'  => $reason,
        'details' => "User {$_SESSION['username']} logged out" . ($reason === 'idle_logout' ? ' (idle timeout).' : '.'),
    ]);
    // If driver, set availability to offline so fleet view reflects actual state.
    if (($_SESSION['role'] ?? '') === 'driver') {
        try { db()->table('users')->where('id', $uid)->update(['availability' => 'offline']); } catch (Throwable $e) {}
    }
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: ' . url('/'));
exit;
