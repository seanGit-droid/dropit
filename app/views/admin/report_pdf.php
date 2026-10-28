<?php defined('APP_ROOT') OR exit('No direct script access allowed');

// ── Input validation ──────────────────────────────────────────────────────────
$type = $_GET['type'] ?? 'summary';
if (!in_array($type, ['summary','drivers','revenue','parcels'])) $type = 'summary';

$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to']   ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $from = date('Y-m-d', strtotime('-14 days'));
    $to   = date('Y-m-d');
}

$generated = date('F j, Y · g:ia');
$admin     = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';

$type_names = [
    'summary' => 'Daily Summary Report',
    'drivers' => 'Driver Performance Report',
    'revenue' => 'Revenue Breakdown Report',
    'parcels' => 'Full Parcel Log',
];
$report_title = $type_names[$type];

// ── SQL queries ───────────────────────────────────────────────────────────────
$tot = db()->raw("
    SELECT COUNT(*) c, SUM(amount) rev, SUM(admin_fee) fee, SUM(driver_earnings) drv,
           SUM(CASE WHEN status='Delivered' THEN 1 ELSE 0 END) delivered,
           SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END) cancelled
    FROM parcels WHERE DATE(created_at) BETWEEN ? AND ?
", [$from, $to])->fetch();

$daily = db()->raw("
    SELECT DATE(created_at) d, COUNT(*) c, SUM(amount) rev,
           SUM(CASE WHEN status='Delivered' THEN 1 ELSE 0 END) delivered
    FROM parcels WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at) ORDER BY d ASC
", [$from, $to])->fetchAll();

$drivers = db()->raw("
    SELECT u.full_name, u.username, u.phone,
           COUNT(p.id) trips, SUM(p.driver_earnings) earn, SUM(p.distance_km) km,
           u.rating_avg, u.rating_count
    FROM users u LEFT JOIN parcels p ON p.driver_id = u.id AND p.status='Delivered' AND DATE(p.created_at) BETWEEN ? AND ?
    WHERE u.role='driver' AND u.is_active=1
    GROUP BY u.id, u.full_name, u.username, u.phone, u.rating_avg, u.rating_count
    ORDER BY trips DESC
", [$from, $to])->fetchAll();

$pay = db()->raw("
    SELECT payment_method, COUNT(*) c, SUM(amount) rev, SUM(admin_fee) fee, SUM(driver_earnings) drv
    FROM parcels WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY payment_method
", [$from, $to])->fetchAll();

$parcels = [];
if ($type === 'parcels') {
    $parcels = db()->raw("
        SELECT p.tracking_number, p.status, p.payment_method, p.amount, p.driver_earnings, p.created_at,
               u.full_name AS customer, d.full_name AS driver
        FROM parcels p
        LEFT JOIN users u ON u.id = p.customer_id
        LEFT JOIN users d ON d.id = p.driver_id
        WHERE DATE(p.created_at) BETWEEN ? AND ?
        ORDER BY p.created_at DESC
    ", [$from, $to])->fetchAll();
}

// ── FPDF setup ────────────────────────────────────────────────────────────────
if (!defined('FPDF_FONTPATH')) define('FPDF_FONTPATH', APP_ROOT . '/scheme/font/');
require_once APP_ROOT . '/scheme/fpdf.php';

class DropITPdf extends FPDF {
    public string $generatedAt = '';
    public int    $reportYear  = 2024;

    function Header() {} // Custom header drawn per-page below
    function Footer() {
        $this->SetY(-14);
        $this->SetFont('Helvetica','',7);
        $this->SetTextColor(120,142,166);
        $this->Cell(0, 5, 'Drop IT (c) ' . $this->reportYear . '  |  Generated: ' . $this->generatedAt, 0, 0, 'C');
        $this->SetFont('Helvetica','',7);
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }
}

$pdf = new DropITPdf('P','mm','A4');
$pdf->AliasNbPages();
$pdf->generatedAt = $generated;
$pdf->reportYear  = (int)date('Y');
$pdf->SetAuthor('Drop IT');
$pdf->SetCreator('Drop IT – Server-side PDF');
$pdf->SetTitle('Drop IT – ' . $report_title);
$pdf->SetMargins(18, 18, 18);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// ── Colour palette ────────────────────────────────────────────────────────────
// Navy   : 11,30,63
// Steel  : 59,90,138
// Sky    : 96,165,250
// Mist   : 120,142,166
// White  : 255,255,255
// Stripe : 241,245,255
// Alt    : 250,251,255

// ── Helper closures ───────────────────────────────────────────────────────────
$peso = fn($n) => '₱' . number_format((float)$n, 2);
$safe = fn($s) => iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string)$s);

// ── Letterhead ────────────────────────────────────────────────────────────────
$pdf->SetFont('Helvetica','B',20);
$pdf->SetTextColor(11,30,63);
$pdf->Cell(0, 8, 'Drop IT', 0, 1, 'L');

$pdf->SetFont('Helvetica','',8);
$pdf->SetTextColor(59,90,138);
$pdf->Cell(0, 5, 'OFFICIAL BUSINESS REPORT', 0, 1, 'L');

// Right-side meta block (overlapped using absolute X/Y)
$metaY = 18;
$metaLines = [
    ['Report:',    $report_title],
    ['Period:',    date('M j, Y', strtotime($from)) . ' – ' . date('M j, Y', strtotime($to))],
    ['Prepared:',  $generated],
    ['By:',        $admin],
];
$pdf->SetFont('Helvetica','',8);
foreach ($metaLines as $i => [$k, $v]) {
    $pdf->SetXY(18, $metaY + $i * 5);
    $pdf->SetFont('Helvetica','B',8); $pdf->SetTextColor(11,30,63);
    $pdf->Cell(192, 5, $safe($k . ' ' . $v), 0, 0, 'R');
}

$pdf->SetY(max($pdf->GetY(), $metaY + count($metaLines) * 5) + 3);

// Divider line
$pdf->SetDrawColor(11,30,63);
$pdf->SetLineWidth(0.6);
$pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
$pdf->Ln(5);

// ── KPI row ───────────────────────────────────────────────────────────────────
$kpis = [
    ['Revenue',   $peso($tot['rev'] ?? 0)],
    ['Parcels',   (string)(int)($tot['c'] ?? 0)],
    ['Delivered', (string)(int)($tot['delivered'] ?? 0)],
    ['Cancelled', (string)(int)($tot['cancelled'] ?? 0)],
];
$kpiW = (192 - 18 - 9) / 4; // 4 boxes with 3 gaps of 3mm
$kpiX = 18;
$kpiY = $pdf->GetY();
foreach ($kpis as $kpi) {
    $pdf->SetFillColor(250,251,255);
    $pdf->SetDrawColor(219,229,245);
    $pdf->SetLineWidth(0.3);
    $pdf->Rect($kpiX, $kpiY, $kpiW, 18, 'FD');
    $pdf->SetFont('Helvetica','',7); $pdf->SetTextColor(59,90,138);
    $pdf->SetXY($kpiX + 2, $kpiY + 2);
    $pdf->Cell($kpiW - 4, 4, strtoupper($kpi[0]), 0, 1, 'L');
    $pdf->SetFont('Helvetica','B',13); $pdf->SetTextColor(11,30,63);
    $pdf->SetXY($kpiX + 2, $kpiY + 7);
    $pdf->Cell($kpiW - 4, 8, $safe($kpi[1]), 0, 0, 'L');
    $kpiX += $kpiW + 3;
}
$pdf->SetY($kpiY + 22);

// ── Section heading helper ────────────────────────────────────────────────────
$sectionHead = function(string $title, string $sub = '') use ($pdf, $safe) {
    $pdf->SetFont('Helvetica','B',12);
    $pdf->SetTextColor(11,30,63);
    $pdf->Cell(0, 7, $safe($title), 0, 1, 'L');
    if ($sub) {
        $pdf->SetFont('Helvetica','I',8);
        $pdf->SetTextColor(120,142,166);
        $pdf->Cell(0, 5, $safe($sub), 0, 1, 'L');
    }
    $pdf->Ln(1);
};

// ── Table renderer ────────────────────────────────────────────────────────────
// $cols = [['label','width','align'], ...]   align: L/C/R
$drawTable = function(array $cols, array $rows) use ($pdf, $safe) {
    $pageW = 174; // 210 - 18 - 18

    // Header row
    $pdf->SetFillColor(241,245,255);
    $pdf->SetDrawColor(224,231,242);
    $pdf->SetLineWidth(0.2);
    $pdf->SetFont('Helvetica','B',8);
    $pdf->SetTextColor(59,90,138);
    foreach ($cols as $col) {
        $pdf->Cell($col[1], 6, strtoupper($col[0]), 'B', 0, $col[2] ?? 'L', true);
    }
    $pdf->Ln();

    // Data rows
    $pdf->SetFont('Helvetica','',8);
    $stripe = false;
    foreach ($rows as $row) {
        // Page-break check (leave 24mm for sign-off)
        if ($pdf->GetY() > 262) {
            $pdf->AddPage();
            // Repeat header
            $pdf->SetFillColor(241,245,255);
            $pdf->SetDrawColor(224,231,242);
            $pdf->SetLineWidth(0.2);
            $pdf->SetFont('Helvetica','B',8);
            $pdf->SetTextColor(59,90,138);
            foreach ($cols as $col) {
                $pdf->Cell($col[1], 6, strtoupper($col[0]), 'B', 0, $col[2] ?? 'L', true);
            }
            $pdf->Ln();
            $pdf->SetFont('Helvetica','',8);
            $stripe = false;
        }

        $fill = $stripe;
        if ($fill) $pdf->SetFillColor(250,251,255); else $pdf->SetFillColor(255,255,255);
        $pdf->SetTextColor(11,30,63);
        foreach ($row as $i => $cell) {
            $align = $cols[$i][2] ?? 'L';
            $pdf->Cell($cols[$i][1], 6, $safe((string)$cell), 'B', 0, $align, true);
        }
        $pdf->Ln();
        $stripe = !$stripe;
    }
    $pdf->Ln(3);
};

// ── Report body ───────────────────────────────────────────────────────────────
if ($type === 'summary') {
    $sectionHead('Daily Activity', 'Parcel volume and revenue per day during the selected period.');
    if (empty($daily)) {
        $pdf->SetFont('Helvetica','I',9); $pdf->SetTextColor(120,142,166);
        $pdf->Cell(0, 8, 'No activity in this period.', 0, 1, 'C');
        $pdf->Ln(2);
    } else {
        $cols = [
            ['Date',      65, 'L'],
            ['Parcels',   35, 'R'],
            ['Delivered', 37, 'R'],
            ['Revenue',   37, 'R'],
        ];
        $tableRows = array_map(fn($d) => [
            date('M j, Y (D)', strtotime($d['d'])),
            (int)$d['c'],
            (int)$d['delivered'],
            $peso($d['rev']),
        ], $daily);
        $drawTable($cols, $tableRows);
    }
}

if ($type === 'drivers') {
    $sectionHead('Driver Performance', "Only Delivered parcels counted. Earnings = driver's 90% share.");
    $cols = [
        ['Driver',   45, 'L'],
        ['Phone',    35, 'L'],
        ['Trips',    20, 'R'],
        ['Distance', 30, 'R'],
        ['Earnings', 30, 'R'],
        ['Rating',   14, 'R'],
    ];
    $tableRows = array_map(fn($d) => [
        $d['full_name'] ?: $d['username'],
        $d['phone'] ?: '—',
        (int)$d['trips'],
        number_format((float)$d['km'], 2) . ' km',
        $peso($d['earn'] ?? 0),
        (int)$d['rating_count'] > 0 ? number_format((float)$d['rating_avg'],2) . ' (' . $d['rating_count'] . ')' : '—',
    ], $drivers);
    $drawTable($cols, $tableRows);
}

if ($type === 'revenue') {
    $sectionHead('Revenue Breakdown by Payment Method', 'Platform fee = 10% of gross; driver share = 90%.');
    $cols = [
        ['Method',       40, 'L'],
        ['Parcels',      28, 'R'],
        ['Gross',        36, 'R'],
        ['Platform Fee', 36, 'R'],
        ['Driver Share', 34, 'R'],
    ];
    $sum = ['c' => 0, 'rev' => 0, 'fee' => 0, 'drv' => 0];
    $tableRows = [];
    foreach ($pay as $p) {
        $sum['c']   += (int)$p['c'];
        $sum['rev'] += (float)$p['rev'];
        $sum['fee'] += (float)$p['fee'];
        $sum['drv'] += (float)$p['drv'];
        $tableRows[] = [ucfirst($p['payment_method'] ?: '—'), (int)$p['c'], $peso($p['rev']), $peso($p['fee']), $peso($p['drv'])];
    }
    // Totals row
    $tableRows[] = ['TOTAL', (int)$sum['c'], $peso($sum['rev']), $peso($sum['fee']), $peso($sum['drv'])];
    $drawTable($cols, $tableRows);
}

if ($type === 'parcels') {
    $sectionHead('Parcel Log', 'All parcels created in the selected period (' . count($parcels) . ' entries).');
    $cols = [
        ['Tracking',  38, 'L'],
        ['Customer',  32, 'L'],
        ['Driver',    30, 'L'],
        ['Status',    22, 'L'],
        ['Pay',       18, 'L'],
        ['Amount',    22, 'R'],
        ['Created',   12, 'L'],
    ];
    $tableRows = array_map(fn($p) => [
        $p['tracking_number'],
        $p['customer'] ?: '—',
        $p['driver']   ?: '—',
        $p['status'],
        ucfirst($p['payment_method'] ?: '—'),
        $peso($p['amount']),
        date('M j g:ia', strtotime($p['created_at'])),
    ], $parcels);
    $drawTable($cols, $tableRows);
}

// ── Sign-off block ────────────────────────────────────────────────────────────
// Ensure it fits on the current page
if ($pdf->GetY() > 250) { $pdf->AddPage(); }
$pdf->Ln(8);
$sigY  = $pdf->GetY() + 12;
$sigW  = (174 - 20) / 2;
$pdf->SetDrawColor(11,30,63); $pdf->SetLineWidth(0.5);
$pdf->Line(18,          $sigY, 18 + $sigW,          $sigY);
$pdf->Line(18 + $sigW + 20, $sigY, 18 + $sigW * 2 + 20, $sigY);
$pdf->SetFont('Helvetica','',8); $pdf->SetTextColor(59,90,138);
$pdf->SetXY(18, $sigY + 2);
$pdf->Cell($sigW, 5, $safe('Prepared by — ' . $admin), 0, 0, 'C');
$pdf->SetX(18 + $sigW + 20);
$pdf->Cell($sigW, 5, 'Reviewed by', 0, 1, 'C');

// ── Stream PDF ────────────────────────────────────────────────────────────────
$filename = 'dropit-' . $type . '-' . $from . '-to-' . $to . '.pdf';
$pdf->Output('D', $filename);   // 'D' = force download
exit;
