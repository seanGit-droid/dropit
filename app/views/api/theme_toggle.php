<?php defined('APP_ROOT') OR exit('No direct script access allowed');
header('Content-Type: application/json');
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) json_response(['ok'=>false], 401);
$theme = $_POST['theme'] ?? 'dark';
if (!in_array($theme, ['dark','light'])) $theme = 'dark';
$_SESSION['theme'] = $theme;
// Persist to DB only if the column exists (gracefully skip if v4 migration not yet run).
try {
    $cols = db()->raw("SHOW COLUMNS FROM users LIKE 'theme'")->fetch();
    if ($cols) db()->table('users')->where('id',$uid)->update(['theme'=>$theme]);
} catch (Throwable $e) {}
json_response(['ok'=>true, 'theme'=>$theme]);
