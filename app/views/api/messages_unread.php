<?php defined('APP_ROOT') OR exit('No direct script access allowed');
header('Content-Type: application/json');

$uid = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';
if ($uid <= 0) json_response(['ok'=>false], 401);

// Count unread chat messages where the current user is a valid peer on the parcel.
if ($role === 'admin') {
    $row = db()->raw("
        SELECT COUNT(*) c FROM messages m
        JOIN parcels p ON p.id = m.parcel_id
        WHERE m.is_read = 0
          AND m.sender_id <> ?
          AND (p.is_walkin = 1 OR p.customer_id IS NULL OR m.sender_role = 'driver')
    ", [$uid])->fetch();
} else {
    $row = db()->raw("
        SELECT COUNT(*) c FROM messages m
        JOIN parcels p ON p.id = m.parcel_id
        WHERE m.is_read = 0
          AND m.sender_id <> ?
          AND ( (p.customer_id = ? AND m.sender_role IN ('driver','admin'))
             OR (p.driver_id   = ? AND m.sender_role IN ('customer','admin')) )
    ", [$uid, $uid, $uid])->fetch();
}

json_response(['ok'=>true, 'count' => (int)($row['c'] ?? 0)]);
