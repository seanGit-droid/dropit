<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$pid = (int)($id ?? 0);
$uid = (int)$_SESSION['user_id'];

$p = db()->table('parcels')
    ->select('id, tracking_number, status, driver_id, customer_id')
    ->where('id',$pid)->get();
if (!$p || (int)$p['driver_id'] !== $uid) {
    set_flash('error','Not your parcel.'); header('Location: ' . url('driver/dashboard')); exit;
}

// Only allow transit photo updates while the parcel is in-flight.
$allowed_statuses = ['Accepted','Picked Up','In Transit','Arrived'];
if (!in_array($p['status'], $allowed_statuses)) {
    set_flash('error','This parcel is not in transit.');
    header('Location: ' . url('driver/parcel/'.$pid)); exit;
}

$dir = APP_ROOT . '/resources/transit';
if (!is_dir($dir)) @mkdir($dir, 0775, true);

$photo_rel = null;
if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg');
    if (!in_array($ext, ['jpg','jpeg','png','webp','heic'])) $ext = 'jpg';
    $fname = 'transit_' . $pid . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $fname)) {
        $photo_rel = 'resources/transit/' . $fname;
    }
}
if (!$photo_rel) {
    set_flash('error','Photo upload failed. Try a smaller image.');
    header('Location: ' . url('driver/parcel/'.$pid)); exit;
}

$caption = trim($_POST['caption'] ?? '');
$caption = mb_substr($caption, 0, 160);

// Encode as "[IMG]<path>|<caption>" so the customer + admin timeline can pick it up.
$note = "[IMG]{$photo_rel}" . ($caption !== '' ? "|{$caption}" : '');

db()->table('parcel_history')->insert([
    'parcel_id'  => $pid,
    'status'     => $p['status'], // keep the current status — this is just an image update
    'notes'      => $note,
    'updated_by' => $uid,
]);

// Notify the customer in-app so they know to refresh.
if ($p['customer_id']) {
    $cust = db()->table('users')->select('id')->where('id',$p['customer_id'])->get();
    if ($cust) {
        Notifier::in_app(
            (int)$cust['id'],
            "📷 Driver sent a photo",
            "New transit update on {$p['tracking_number']}" . ($caption !== '' ? ": {$caption}" : '.'),
            $pid
        );
    }
}

db()->table('activity_logs')->insert([
    'user_id' => $uid,
    'action'  => 'transit_photo',
    'details' => "Driver posted a transit photo on {$p['tracking_number']}.",
]);

set_flash('success', '📷 Photo posted. Customer can see it on their tracking page.');
header('Location: ' . url('driver/parcel/'.$pid)); exit;
