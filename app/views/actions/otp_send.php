<?php defined('APP_ROOT') OR exit('No direct script access allowed');
header('Content-Type: application/json');

$purpose    = $_POST['purpose']    ?? '';
$identifier = trim($_POST['identifier'] ?? '');

if (!in_array($purpose, ['phone','email']))  json_response(['ok'=>false,'error'=>'bad purpose'], 400);
if ($identifier === '')                       json_response(['ok'=>false,'error'=>'missing identifier'], 400);

if ($purpose === 'email' && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok'=>false,'error'=>'invalid email'], 400);
}
if ($purpose === 'phone' && strlen(preg_replace('/\D/','',$identifier)) < 10) {
    json_response(['ok'=>false,'error'=>'invalid phone'], 400);
}

// uniqueness check vs existing users
$exists = db()->table('users')->select('id')
    ->where($purpose === 'email' ? 'email' : 'phone', $identifier)->get();
if ($exists) json_response(['ok'=>false,'error'=>ucfirst($purpose).' already registered'], 409);

// rate-limit: max 5 sends per session per identifier per 10 minutes
$sid = session_id();
$recent = db()->table('otps')
    ->where('session_id', $sid)
    ->where('identifier', $identifier)
    ->where('purpose', $purpose)
    ->where('created_at', '>', date('Y-m-d H:i:s', time() - 600))
    ->count();
if ($recent >= 5) json_response(['ok'=>false,'error'=>'Too many OTP requests, wait a bit'], 429);

$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// clear older unverified codes for this session+purpose
db()->table('otps')
    ->where('session_id', $sid)
    ->where('identifier', $identifier)
    ->where('purpose', $purpose)
    ->where('verified', 0)
    ->delete();

db()->table('otps')->insert([
    'purpose'    => $purpose,
    'identifier' => $identifier,
    'code'       => $code,
    'session_id' => $sid,
    'expires_at' => date('Y-m-d H:i:s', time() + 600), // 10 min
]);

if ($purpose === 'phone') {
    // SMS removed - phone no longer requires OTP and this branch should not be reached from the UI.
    json_response(['ok' => false, 'error' => 'Phone OTP is disabled. Verify email only.'], 400);
}

$send_status = Notifier::notify_email_verification($identifier, $code);
if ($send_status !== 'sent') {
    json_response(['ok' => false, 'error' => 'Failed to send verification email. Try again in a moment.'], 502);
}

json_response(['ok' => true]);
