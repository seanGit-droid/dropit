<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$uid  = (int) $_SESSION['user_id'];
$days = max(1, min(365, (int) ($_GET['days'] ?? 30)));
$since = date('Y-m-d', strtotime("-{$days} days"));

$me = db()->table('users')->select('full_name, username, rating_avg, rating_count')->where('id', $uid)->get();
$totals = db()->raw("
    SELECT COUNT(*) c, SUM(driver_earnings) earn, SUM(distance_km) km
    FROM parcels WHERE driver_id=? AND status='Delivered'
", [$uid])->fetch();

$daily = db()->raw("
    SELECT DATE(updated_at) d, COUNT(*) trips, SUM(driver_earnings) earnings, SUM(distance_km) km
    FROM parcels WHERE driver_id=? AND status='Delivered' AND DATE(updated_at) >= ?
    GROUP BY DATE(updated_at) ORDER BY d ASC
", [$uid, $since])->fetchAll();

$trips = db()->raw("
    SELECT tracking_number, recipient_name, recipient_address, distance_km,
           amount, driver_earnings, payment_method, updated_at
    FROM parcels
    WHERE driver_id=? AND status='Delivered' AND DATE(updated_at) >= ?
    ORDER BY updated_at DESC
", [$uid, $since])->fetchAll();

$safe = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($me['full_name'] ?: $me['username']));
$filename = 'dropit-driver-stats-' . $safe . '-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, max-age=0');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel reads ₱ correctly.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Drop IT — Driver Stats Export']);
fputcsv($out, ['Driver',     $me['full_name'] ?: $me['username']]);
fputcsv($out, ['Period',     'Last ' . $days . ' days (' . $since . ' to ' . date('Y-m-d') . ')']);
fputcsv($out, ['Generated',  date('Y-m-d H:i:s')]);
fputcsv($out, []);

fputcsv($out, ['Lifetime Totals']);
fputcsv($out, ['Delivered parcels', (int) ($totals['c'] ?? 0)]);
fputcsv($out, ['Total earnings (PHP)', number_format((float) ($totals['earn'] ?? 0), 2, '.', '')]);
fputcsv($out, ['Total distance (km)', number_format((float) ($totals['km'] ?? 0), 2, '.', '')]);
fputcsv($out, ['Rating', (int) $me['rating_count'] > 0 ? number_format((float) $me['rating_avg'], 2) . ' (' . $me['rating_count'] . ' reviews)' : 'No reviews yet']);
fputcsv($out, []);

fputcsv($out, ['Daily Breakdown (Last ' . $days . ' days)']);
fputcsv($out, ['Date', 'Trips', 'Earnings (PHP)', 'Distance (km)']);
$sumTrips = 0; $sumEarn = 0.0; $sumKm = 0.0;
foreach ($daily as $d) {
    $sumTrips += (int) $d['trips'];
    $sumEarn  += (float) $d['earnings'];
    $sumKm    += (float) $d['km'];
    fputcsv($out, [
        $d['d'],
        (int) $d['trips'],
        number_format((float) $d['earnings'], 2, '.', ''),
        number_format((float) $d['km'], 2, '.', ''),
    ]);
}
fputcsv($out, ['TOTAL', $sumTrips, number_format($sumEarn, 2, '.', ''), number_format($sumKm, 2, '.', '')]);
fputcsv($out, []);

fputcsv($out, ['Trip Log (Last ' . $days . ' days)']);
fputcsv($out, ['Tracking', 'Recipient', 'Address', 'Distance (km)', 'Fare (PHP)', 'Earnings (PHP)', 'Payment', 'Delivered At']);
foreach ($trips as $t) {
    fputcsv($out, [
        $t['tracking_number'],
        $t['recipient_name'] ?: '',
        $t['recipient_address'] ?: '',
        number_format((float) $t['distance_km'], 2, '.', ''),
        number_format((float) $t['amount'], 2, '.', ''),
        number_format((float) $t['driver_earnings'], 2, '.', ''),
        $t['payment_method'] ?: '',
        $t['updated_at'],
    ]);
}

fclose($out);
exit;
