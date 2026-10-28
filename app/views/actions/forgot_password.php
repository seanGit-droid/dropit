<?php defined('APP_ROOT') OR exit('No direct script access allowed');
header('Content-Type: application/json');

// Routes:
//   POST /forgot/send   - generate + email a reset code (only if email exists)
//   POST /forgot/verify - confirm code matches; mark verified
//   POST /forgot/reset  - set new password (requires a verified code in this session)

$action = '';
if (str_ends_with($_SERVER['REQUEST_URI'] ?? '', '/forgot/send'))   $action = 'send';
elseif (str_ends_with($_SERVER['REQUEST_URI'] ?? '', '/forgot/verify')) $action = 'verify';
elseif (str_ends_with($_SERVER['REQUEST_URI'] ?? '', '/forgot/reset'))  $action = 'reset';
else json_response(['ok'=>false,'error'=>'Unknown action'], 400);

$email = strtolower(trim($_POST['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok'=>false,'error'=>'Invalid email'], 400);
}

$sid = session_id();

if ($action === 'send') {
    $user = db()->table('users')->select('id, full_name, username')->where('email', $email)->get();
    // Generic message either way to avoid email enumeration. Only actually send when user exists.
    if (!$user) {
        json_response(['ok' => true, 'note' => 'If that email is registered, a code is on the way.']);
    }

    // Rate-limit: max 5 sends per session per email per 10 minutes.
    $recent = db()->table('otps')
        ->where('session_id', $sid)->where('identifier', $email)->where('purpose', 'email')
        ->where('created_at', '>', date('Y-m-d H:i:s', time() - 600))->count();
    if ($recent >= 5) json_response(['ok'=>false,'error'=>'Too many requests, wait a bit'], 429);

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    db()->table('otps')->where('session_id', $sid)->where('identifier', $email)
        ->where('purpose','email')->where('verified', 0)->delete();
    db()->table('otps')->insert([
        'purpose'    => 'email',
        'identifier' => $email,
        'code'       => $code,
        'session_id' => $sid,
        'expires_at' => date('Y-m-d H:i:s', time() + 600),
    ]);

    $name = $user['full_name'] ?: $user['username'] ?: 'there';
    $body = "Hi {$name},\n\nYour Drop IT password reset code is: {$code}\n\nThis code expires in 10 minutes. If you did not request this, ignore this email.\n\n� Drop IT";
    $status = Notifier::email($email, 'Drop IT - Password reset code', $body);
    if ($status !== 'sent') {
        json_response(['ok'=>false,'error'=>'Could not send email. Try again in a moment.'], 502);
    }
    json_response(['ok' => true]);
}

if ($action === 'verify') {
    $code = trim($_POST['code'] ?? '');
    if (!preg_match('/^\d{6}$/', $code)) json_response(['ok'=>false,'error'=>'Invalid code format'], 400);
    $row = db()->table('otps')->select('id, code, expires_at')
        ->where('session_id', $sid)->where('identifier', $email)
        ->where('purpose', 'email')->where('verified', 0)
        ->order_by('id','DESC')->limit(1)->get();
    if (!$row)                                  json_response(['ok'=>false,'error'=>'No active code. Request one first.'], 404);
    if (strtotime($row['expires_at']) < time()) json_response(['ok'=>false,'error'=>'Code expired'], 410);
    if (!hash_equals($row['code'], $code))      json_response(['ok'=>false,'error'=>'Wrong code'], 400);
    db()->table('otps')->where('id', $row['id'])->update(['verified' => 1]);
    json_response(['ok' => true]);
}

if ($action === 'reset') {
    $password = (string) ($_POST['password'] ?? '');
    if (strlen($password) < 8) json_response(['ok'=>false,'error'=>'Password too short'], 400);

    // Require a verified code in this session for this email, issued in the last 15 min.
    $verified = db()->table('otps')->select('id, expires_at')
        ->where('session_id', $sid)->where('identifier', $email)
        ->where('purpose', 'email')->where('verified', 1)
        ->where('created_at', '>', date('Y-m-d H:i:s', time() - 900))
        ->order_by('id','DESC')->limit(1)->get();
    if (!$verified) json_response(['ok'=>false,'error'=>'Verify your code first'], 403);

    $user = db()->table('users')->select('id')->where('email', $email)->get();
    if (!$user) json_response(['ok'=>false,'error'=>'Account not found'], 404);

    db()->table('users')->where('id', $user['id'])->update([
        'password' => password_hash($password, PASSWORD_BCRYPT),
    ]);
    // Burn the OTP so it can't be reused.
    db()->table('otps')->where('id', $verified['id'])->delete();

    db()->table('activity_logs')->insert([
        'user_id' => $user['id'],
        'action'  => 'password_reset',
        'details' => 'Password reset via forgot-password flow.',
    ]);

    json_response(['ok' => true]);
}
