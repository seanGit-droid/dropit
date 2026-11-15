<?php defined('APP_ROOT') OR exit('No direct script access allowed');
require_once APP_ROOT . '/scheme/ProofStorage.php';
$path = 'resources/' . ($kind ?? '') . '/' . ($filename ?? '');
if (!ProofStorage::validPath($path)) { http_response_code(404); exit; }
$parcel = db()->raw('SELECT p.customer_id,p.driver_id FROM parcels p WHERE p.pod_photo=? OR EXISTS (SELECT 1 FROM parcel_history h WHERE h.parcel_id=p.id AND LEFT(h.notes,5)=\'[IMG]\' AND SUBSTRING_INDEX(SUBSTRING(h.notes,6),\'|\',1)=?) LIMIT 1', [$path, $path])->fetch();
if (!$parcel) { http_response_code(404); exit; }
$uid = (int)($_SESSION['user_id'] ?? 0);
if (($_SESSION['role'] ?? '') !== 'admin' && (int)$parcel['customer_id'] !== $uid && (int)$parcel['driver_id'] !== $uid) {
    http_response_code(403); exit;
}
// Release the session while fetching so a slow image cannot block other requests.
session_write_close();
try {
    [$mime, $bytes] = (new ProofStorage())->read($path);
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, max-age=300');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
} catch (OutOfBoundsException $e) { http_response_code(404); }
catch (Throwable $e) {
    error_log('Proof photo retrieval failed: ' . get_class($e));
    http_response_code(503);
    header('Retry-After: 30');
}
