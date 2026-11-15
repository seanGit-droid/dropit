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

require_once APP_ROOT . '/scheme/ProofStorage.php';
require_once APP_ROOT . '/scheme/DeliveryCapture.php';
$photo_storage = null; $photo_rel = null; $photo_db = db();
$caption = trim($_POST['caption'] ?? '');
// The production PHP image does not require the optional mbstring extension.
preg_match('/\A.{0,160}/us', $caption, $caption_match);
$caption = $caption_match[0] ?? '';
$photo_db->transaction();
try {
    $locked = $photo_db->raw('SELECT status,driver_id FROM parcels WHERE id=? FOR UPDATE', [$pid])->fetch();
    if (!$locked || (int)$locked['driver_id'] !== $uid || !in_array($locked['status'], $allowed_statuses, true)) {
        throw new InvalidArgumentException('This parcel is not in transit.');
    }
    $ext = strtolower(pathinfo($_FILES['photo']['name'] ?? '', PATHINFO_EXTENSION) ?: 'jpg');
    if (!in_array($ext, ['jpg','jpeg','png','webp','heic'])) $ext = 'jpg';
    $path = 'resources/transit/transit_' . $pid . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    $photo_storage = new ProofStorage();
    $photo_rel = $photo_storage->saveUpload($_FILES['photo'] ?? [], $path);
    // Retain the existing [IMG]<path>|<caption> history format.
    DeliveryCapture::insert($photo_db, 'parcel_history', [
        'parcel_id'=>$pid, 'status'=>$locked['status'],
        'notes'=>"[IMG]{$photo_rel}" . ($caption !== '' ? "|{$caption}" : ''), 'updated_by'=>$uid,
    ]);
    $photo_db->commit();
} catch (Throwable $e) {
    $photo_db->roll_back();
    if ($photo_rel && $photo_storage) {
        try { $photo_storage->discard($photo_rel); }
        catch (Throwable $cleanup) { error_log('Transit rollback cleanup failed: '.get_class($cleanup)); }
    }
    if (!($e instanceof InvalidArgumentException)) error_log('Transit photo save failed: '.get_class($e));
    set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Photo could not be saved. Please retry.');
    header('Location: ' . url('driver/parcel/'.$pid)); exit;
}

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
