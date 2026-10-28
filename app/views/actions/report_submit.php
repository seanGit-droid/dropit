<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($subject === '' || $message === '') {
    set_flash('error','Subject and message required.');
    header('Location: ' . url('customer/report')); exit;
}

$rid = db()->table('reports')->insert([
    'user_id' => $_SESSION['user_id'],
    'subject' => $subject,
    'message' => $message,
]);

db()->table('activity_logs')->insert([
    'user_id' => $_SESSION['user_id'],
    'action'  => 'report_submit',
    'details' => "{$_SESSION['username']} submitted report: {$subject}",
]);

$reporter = db()->table('users')
    ->select('id, full_name, username, email, role')
    ->where('id', $_SESSION['user_id'])->get();
if ($reporter) {
    Notifier::notify_admin_new_report($reporter, [
        'id' => $rid, 'subject' => $subject, 'message' => $message,
    ]);
}

set_flash('success','Report sent. Thank you!');
header('Location: ' . url('customer/dashboard'));
exit;
