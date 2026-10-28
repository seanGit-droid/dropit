<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$rid = (int)($id ?? 0);
$status = $_POST['status'] ?? 'Open';
if (!in_array($status, ['Open','In Review','Resolved','Closed'])) {
    set_flash('error','Invalid status.');
    header('Location: ' . url('admin/reports')); exit;
}

db()->table('reports')->where('id', $rid)->update(['status' => $status]);

db()->table('activity_logs')->insert([
    'user_id' => $_SESSION['user_id'],
    'action'  => 'report_status',
    'details' => "Report #{$rid} marked {$status}.",
]);

set_flash('success','Report status updated.');
header('Location: ' . url('admin/reports'));
exit;
