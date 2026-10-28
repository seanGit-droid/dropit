<?php defined('APP_ROOT') OR exit('No direct script access allowed');
header('Content-Type: application/json');
$uid = (int)($_SESSION['user_id'] ?? 0);
$state = $_POST['state'] ?? '';
if (!in_array($state, ['online','offline','break'])) json_response(['ok'=>false,'error'=>'bad state'], 400);
db()->table('users')->where('id',$uid)->update(['availability' => $state]);
json_response(['ok'=>true, 'state'=>$state]);
