<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to']   ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    // Fallback: last 14 days
    $days = max(1, min(365, (int)($_GET['days'] ?? 14)));
    $from = date('Y-m-d', strtotime("-{$days} days"));
    $to   = date('Y-m-d');
}

$rows = db()->raw("
    SELECT p.tracking_number, p.status, p.payment_method, p.payment_status, p.amount, p.admin_fee, p.driver_earnings, p.distance_km,
           p.created_at, p.updated_at, u.full_name AS customer, d.full_name AS driver
    FROM parcels p
    LEFT JOIN users u ON u.id = p.customer_id
    LEFT JOIN users d ON d.id = p.driver_id
    WHERE DATE(p.created_at) BETWEEN ? AND ?
    ORDER BY p.created_at DESC
", [$from, $to])->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="dropit_report_' . $from . '_to_' . $to . '.csv"');
$out = fopen('php://output', 'w');
// BOM for Excel UTF-8 compat
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Tracking','Status','Payment Method','Payment Status','Amount','Platform Fee','Driver Earnings','Distance km','Customer','Driver','Created','Updated']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['tracking_number'], $r['status'], $r['payment_method'], $r['payment_status'],
        $r['amount'], $r['admin_fee'], $r['driver_earnings'], $r['distance_km'],
        $r['customer'], $r['driver'], $r['created_at'], $r['updated_at'],
    ]);
}
fclose($out);
exit;
