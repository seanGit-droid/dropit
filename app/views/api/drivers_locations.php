<?php defined('APP_ROOT') OR exit('No direct script access allowed');
header('Content-Type: application/json');

// expose active drivers' current locations (to drivers + admins only)
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['driver','admin'])) json_response(['ok'=>false,'error'=>'forbidden'], 403);

if ($role === 'admin') sweep_stale_dispatches(15);

$rows = db()->table('driver_locations')
    ->select('driver_id, lat, lng, updated_at')
    ->where('updated_at', '>', date('Y-m-d H:i:s', time() - 300))
    ->get_all();
json_response(['ok' => true, 'drivers' => $rows]);
