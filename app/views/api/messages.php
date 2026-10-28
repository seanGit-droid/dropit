<?php defined('APP_ROOT') OR exit('No direct script access allowed');
header('Content-Type: application/json');

$pid = (int)($id ?? 0);
if ($pid <= 0) json_response(['ok'=>false,'error'=>'bad id'], 400);

$parcel = db()->table('parcels')->select('id, customer_id, driver_id, tracking_number, is_walkin, status')->where('id', $pid)->get();
if (!$parcel) json_response(['ok'=>false,'error'=>'not found'], 404);

$uid  = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';
$allowed = $role === 'admin'
        || ($role === 'customer' && (int)$parcel['customer_id'] === $uid)
        || ($role === 'driver'   && (int)$parcel['driver_id']   === $uid);
if (!$allowed) json_response(['ok'=>false,'error'=>'forbidden'], 403);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lock chat once parcel is Delivered or Cancelled. Admins can still post. Support threads are never locked.
    $is_support = str_starts_with($parcel['tracking_number'] ?? '', 'SUPPORT-');
    if ($role !== 'admin' && !$is_support && in_array($parcel['status'], ['Delivered','Cancelled'])) {
        json_response(['ok'=>false,'error'=>'Chat is closed — parcel ' . strtolower($parcel['status']) . '.'], 423);
    }
    $body = trim((string)($_POST['body'] ?? ''));
    if ($body === '' || mb_strlen($body) > 500) json_response(['ok'=>false,'error'=>'bad body'], 400);

    $mid = db()->table('messages')->insert([
        'parcel_id' => $pid,
        'sender_id' => $uid,
        'sender_role' => $role,
        'body' => $body,
    ]);

    // Admin chat commands for return requests: /confirm and /reject
    if ($role === 'admin') {
        $cmd = strtolower(trim(explode(' ', $body)[0] ?? ''));
        if (in_array($cmd, ['/confirm', '/reject'])) {
            $ret = db()->table('returns')->select('id, status')->where('parcel_id',$pid)->get();
            if ($ret && $ret['status'] === 'Requested') {
                $new_status = $cmd === '/confirm' ? 'Approved' : 'Rejected';
                db()->table('returns')->where('id',$ret['id'])->update(['status' => $new_status, 'admin_note' => $body]);

                // Post a system reply
                $sys_reply = $cmd === '/confirm'
                    ? "✅ Return APPROVED by admin. Waiting for pickup dispatch. Status: Approved."
                    : "❌ Return REJECTED by admin. No further action.";
                db()->table('messages')->insert([
                    'parcel_id' => $pid, 'sender_id' => $uid, 'sender_role' => 'admin',
                    'body' => $sys_reply,
                ]);

                // If confirmed, flip parcel status so it can be re-dispatched as a return pickup
                if ($cmd === '/confirm') {
                    db()->table('parcels')->where('id',$pid)->update(['status' => 'Return Pending']);
                    db()->table('parcel_history')->insert([
                        'parcel_id'=>$pid, 'status'=>'Return Pending',
                        'notes'=>'Admin confirmed return. Ready for dispatch (reverse direction).',
                        'updated_by'=>$uid,
                    ]);
                }

                // Notify customer
                if ($parcel['customer_id']) {
                    $cust = db()->table('users')->select('id')->where('id',$parcel['customer_id'])->get();
                    if ($cust) Notifier::in_app((int)$cust['id'],
                        $cmd === '/confirm' ? '✅ Return approved' : '❌ Return rejected',
                        "Admin replied to your return on {$parcel['tracking_number']}.",
                        $pid);
                }

                db()->table('activity_logs')->insert([
                    'user_id' => $uid, 'action' => 'return_' . substr($cmd, 1),
                    'details' => "Admin {$cmd} return on {$parcel['tracking_number']}.",
                ]);
            }
        }
    }

    // Notify the other party (in-app only).
    $from = $_SESSION['full_name'] ?: $_SESSION['username'];
    $targets = [];
    if ($role === 'customer') {
        if ($parcel['driver_id']) {
            // Normal parcel: notify driver
            $t = db()->table('users')->select('id, full_name, username')->where('id', $parcel['driver_id'])->get();
            if ($t) $targets[] = $t;
        } else {
            // Support thread or no driver yet: notify all admins
            $targets = db()->table('users')->select('id, full_name, username')->where('role','admin')->where('is_active',1)->get_all();
        }
    } elseif ($role === 'driver') {
        // If the parcel has a customer, notify them. Otherwise (walk-in / admin-dispatched) notify all admins.
        if ($parcel['customer_id']) {
            $t = db()->table('users')->select('id, full_name, username')->where('id', $parcel['customer_id'])->get();
            if ($t) $targets[] = $t;
        } else {
            $targets = db()->table('users')->select('id, full_name, username')->where('role','admin')->where('is_active',1)->get_all();
        }
    } elseif ($role === 'admin') {
        if ($parcel['driver_id']) {
            $t = db()->table('users')->select('id, full_name, username')->where('id', $parcel['driver_id'])->get();
            if ($t) $targets[] = $t;
        }
        if ($parcel['customer_id']) {
            $t = db()->table('users')->select('id, full_name, username')->where('id', $parcel['customer_id'])->get();
            if ($t) $targets[] = $t;
        }
    }
    foreach ($targets as $t) Notifier::notify_driver_message($t, $parcel, $from, $body);

    json_response(['ok'=>true, 'id'=>$mid]);
}

// GET: return messages since given id
$since = (int)($_GET['since'] ?? 0);
$rows = db()->table('messages')
    ->select('messages.id, messages.sender_id, messages.sender_role, messages.body, messages.created_at, users.full_name, users.username')
    ->left_join('users', 'users.id = messages.sender_id')
    ->where('messages.parcel_id', $pid)
    ->where('messages.id', '>', $since)
    ->order_by('messages.id', 'ASC')
    ->limit(100)->get_all();

$items = [];
foreach ($rows as $r) {
    $items[] = [
        'id' => (int)$r['id'],
        'sender_id'   => (int)$r['sender_id'],
        'sender_role' => $r['sender_role'],
        'sender_name' => $r['full_name'] ?: $r['username'],
        'body' => $r['body'],
        'created_at' => $r['created_at'],
    ];
}

// Mark messages from other users as read
db()->raw("UPDATE messages SET is_read=1 WHERE parcel_id=? AND sender_id<>? AND is_read=0", [$pid, $uid]);

json_response(['ok'=>true, 'items'=>$items]);
