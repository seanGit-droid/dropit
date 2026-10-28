<?php defined('APP_ROOT') OR exit('No direct script access allowed');
header('Content-Type: application/json');

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) json_response(['ok'=>false,'error'=>'no session'], 401);

$items = db()->table('notifications')
    ->select('id, title, message, is_read, created_at, parcel_id')
    ->where('user_id', $uid)
    ->order_by('id', 'DESC')
    ->limit(10)->get_all();

$unread = 0;
$latest = 0;
foreach ($items as $i) {
    if (!$i['is_read']) $unread++;
    if ((int)$i['id'] > $latest) $latest = (int)$i['id'];
}
$items = array_reverse($items);

json_response(['ok'=>true, 'items'=>$items, 'latest_id'=>$latest, 'unread_count'=>$unread]);
