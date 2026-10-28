<?php defined('APP_ROOT') OR exit('No direct script access allowed');
header('Content-Type: application/json');

$purpose    = $_POST['purpose']    ?? '';
$identifier = trim($_POST['identifier'] ?? '');
$code       = trim($_POST['code']       ?? '');

if (!in_array($purpose, ['phone','email'])) json_response(['ok'=>false,'error'=>'bad purpose'], 400);
if ($code === '' || !preg_match('/^\d{6}$/', $code)) json_response(['ok'=>false,'error'=>'Invalid code format'], 400);

$sid = session_id();
$row = db()->table('otps')
    ->select('id, code, expires_at')
    ->where('session_id', $sid)
    ->where('identifier', $identifier)
    ->where('purpose', $purpose)
    ->where('verified', 0)
    ->order_by('id', 'DESC')
    ->limit(1)
    ->get();

if (!$row) json_response(['ok'=>false,'error'=>'No active OTP. Send one first.'], 404);
if (strtotime($row['expires_at']) < time()) json_response(['ok'=>false,'error'=>'OTP expired'], 410);
if (!hash_equals($row['code'], $code))      json_response(['ok'=>false,'error'=>'Wrong code'], 400);

db()->table('otps')->where('id', $row['id'])->update(['verified' => 1]);
json_response(['ok' => true]);
