<?php defined('APP_ROOT') OR exit('No direct script access allowed');

$type = $_GET['type'] ?? 'summary';
if (!in_array($type, ['summary','drivers','revenue','parcels'])) $type = 'summary';
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to']   ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $from = date('Y-m-d', strtotime('-14 days'));
    $to   = date('Y-m-d');
}

$generated = date('F j, Y · g:ia');
$admin = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';

$type_names = [
    'summary' => 'Daily Summary Report',
    'drivers' => 'Driver Performance Report',
    'revenue' => 'Revenue Breakdown Report',
    'parcels' => 'Full Parcel Log',
];
$report_title = $type_names[$type];

// Pull totals
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Drop IT — <?= esc($report_title) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; }
  html, body { margin:0; padding:0; font-family: 'Inter', system-ui, sans-serif; color:#0b1e3f; background:#eef3fb; }
  .page { max-width: 900px; margin: 24px auto; background: #fff; padding: 48px 56px; box-shadow: 0 10px 30px rgba(8,20,52,.15); border-radius: 8px; }
  header.rpt { display:flex; align-items:center; justify-content:space-between; border-bottom: 2px solid #0b1e3f; padding-bottom: 16px; margin-bottom: 24px; }
  header.rpt .brand { display:flex; align-items:center; gap:12px; }
  header.rpt .brand img { width:44px; height:44px; border-radius:8px; }
  header.rpt .brand h1 { margin:0; font-size:20px; font-weight:800; letter-spacing:-.01em; }
  header.rpt .brand p  { margin:0; color:#3b5a8a; font-size:11px; letter-spacing:.12em; text-transform:uppercase; }
  .meta { text-align:right; font-size:12px; color:#3b5a8a; }
  .meta strong { color:#0b1e3f; }
  h2.rpt { font-size:18px; margin: 0 0 12px; letter-spacing:-.01em; }
  .sub { color:#3b5a8a; font-size:12px; margin-bottom:20px; }
  table { width:100%; border-collapse: collapse; font-size:13px; margin-bottom:24px; }
  th, td { padding:10px 12px; text-align:left; border-bottom: 1px solid #e0e7f2; }
  thead th { background:#f1f5ff; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#3b5a8a; font-weight:600; }
  td.num, th.num { text-align:right; font-variant-numeric: tabular-nums; }
  .kpi { display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom:24px; }
  .kpi .box { border: 1px solid #dbe5f5; border-radius:8px; padding: 14px 16px; background:#fafbff; }
  .kpi .lbl { font-size:11px; color:#3b5a8a; letter-spacing:.08em; text-transform:uppercase; }
  .kpi .val { font-size:24px; font-weight:700; margin-top:4px; }
  .signoff { margin-top:48px; display:flex; justify-content:space-between; gap:60px; }
  .sig { flex:1; }
  .sig .line { border-top: 1px solid #0b1e3f; margin-top:40px; padding-top:6px; font-size:12px; color:#3b5a8a; text-align:center; }
  .footer { margin-top:32px; text-align:center; font-size:11px; color:#7a8ea6; border-top:1px solid #e0e7f2; padding-top:12px; }
  .tools { max-width: 900px; margin: 16px auto; display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; gap:8px; }
  .tools-note { width:100%; text-align:right; font-size:11px; color:#7a8ea6; margin-top:2px; }
  .btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; border:1px solid #0b1e3f; color:#0b1e3f; background:#fff; font-weight:600; cursor:pointer; text-decoration:none; font-size:13px; }
  .btn.primary { background:#0b1e3f; color:#fff; }
  @media print {
    .tools, .no-print { display:none !important; }
    body { background:#fff; }
    .page { box-shadow:none; margin:0; padding:20mm 18mm; border-radius:0; }
    @page { margin:0; size: A4; }
  }
</style>
</head>
<body>

<div class="tools no-print">
  <a href="javascript:history.back()" class="btn">← Back</a>
  <a id="btnPdf" href="<?= url('admin/analytics/report/pdf?type='.urlencode($type).'&from='.urlencode($from).'&to='.urlencode($to)) ?>" class="btn primary">⬇ Download PDF</a>
  <button onclick="window.print()" class="btn">🖨 Print preview</button>
  <span class="tools-note">PDF is generated server-side directly from the database — not a screenshot of this page.</span>
</div>

<div class="page">
  <header class="rpt">
    <div class="brand">
      <img src="<?= url('resources/weblogo.png') ?>" alt="Drop IT">
      <div>
        <h1>Drop IT</h1>
        <p>Official Business Report</p>
      </div>
    </div>
    <div class="meta">
      <div><strong>Report</strong> — <?= esc($report_title) ?></div>
      <div><strong>Period:</strong> <?= esc(date('M j, Y', strtotime($from))) ?> – <?= esc(date('M j, Y', strtotime($to))) ?></div>
      <div><strong>Prepared:</strong> <?= esc($generated) ?></div>
      <div><strong>Prepared by:</strong> <?= esc($admin) ?></div>
    </div>
  </header>

  <!-- always show the KPI row -->
  <div class="kpi">
    <div class="box"><div class="lbl">Revenue</div><div class="val">₱<?= number_format((float)($tot['rev'] ?? 0), 2) ?></div></div>
    <div class="box"><div class="lbl">Parcels</div><div class="val"><?= (int)($tot['c'] ?? 0) ?></div></div>
    <div class="box"><div class="lbl">Delivered</div><div class="val"><?= (int)($tot['delivered'] ?? 0) ?></div></div>
    <div class="box"><div class="lbl">Cancelled</div><div class="val"><?= (int)($tot['cancelled'] ?? 0) ?></div></div>
  </div>

  <?php if ($type === 'summary'): ?>
    <h2 class="rpt">Daily Activity</h2>
    <div class="sub">Parcel volume and revenue per day during the selected period.</div>
    <table>
      <thead><tr><th>Date</th><th class="num">Parcels</th><th class="num">Delivered</th><th class="num">Revenue</th></tr></thead>
      <tbody>
        <?php if (empty($daily)): ?>
          <tr><td colspan="4" style="text-align:center; color:#7a8ea6; padding:24px">No activity in this period.</td></tr>
        <?php else: foreach ($daily as $d): ?>
          <tr>
            <td><?= esc(date('M j, Y (D)', strtotime($d['d']))) ?></td>
            <td class="num"><?= (int)$d['c'] ?></td>
            <td class="num"><?= (int)$d['delivered'] ?></td>
            <td class="num">₱<?= number_format((float)$d['rev'], 2) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($type === 'drivers'): ?>
    <h2 class="rpt">Driver Performance</h2>
    <div class="sub">Only Delivered parcels counted. Earnings = driver's 90% share.</div>
    <table>
      <thead><tr><th>Driver</th><th>Phone</th><th class="num">Trips</th><th class="num">Distance</th><th class="num">Earnings</th><th class="num">Rating</th></tr></thead>
      <tbody>
        <?php foreach ($drivers as $d): ?>
          <tr>
            <td><?= esc($d['full_name'] ?: $d['username']) ?></td>
            <td><?= esc($d['phone'] ?: '—') ?></td>
            <td class="num"><?= (int)$d['trips'] ?></td>
            <td class="num"><?= number_format((float)$d['km'], 2) ?> km</td>
            <td class="num">₱<?= number_format((float)($d['earn'] ?? 0), 2) ?></td>
            <td class="num"><?= (int)$d['rating_count'] > 0 ? '★'.number_format((float)$d['rating_avg'],2).' ('.$d['rating_count'].')' : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($type === 'revenue'): ?>
    <h2 class="rpt">Revenue Breakdown by Payment Method</h2>
    <div class="sub">Platform fee = 10% of gross; driver share = 90%.</div>
    <table>
      <thead><tr><th>Method</th><th class="num">Parcels</th><th class="num">Gross</th><th class="num">Platform Fee</th><th class="num">Driver Share</th></tr></thead>
      <tbody>
        <?php $sum = ['c'=>0,'rev'=>0,'fee'=>0,'drv'=>0]; foreach ($pay as $p): $sum['c']+=(int)$p['c']; $sum['rev']+=(float)$p['rev']; $sum['fee']+=(float)$p['fee']; $sum['drv']+=(float)$p['drv']; ?>
          <tr>
            <td style="text-transform:capitalize;"><?= esc($p['payment_method'] ?: '—') ?></td>
            <td class="num"><?= (int)$p['c'] ?></td>
            <td class="num">₱<?= number_format((float)$p['rev'], 2) ?></td>
            <td class="num">₱<?= number_format((float)$p['fee'], 2) ?></td>
            <td class="num">₱<?= number_format((float)$p['drv'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr style="font-weight:700; background:#f1f5ff;">
          <td>TOTAL</td>
          <td class="num"><?= (int)$sum['c'] ?></td>
          <td class="num">₱<?= number_format($sum['rev'], 2) ?></td>
          <td class="num">₱<?= number_format($sum['fee'], 2) ?></td>
          <td class="num">₱<?= number_format($sum['drv'], 2) ?></td>
        </tr>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($type === 'parcels'): ?>
    <h2 class="rpt">Parcel Log</h2>
    <div class="sub">All parcels created in the selected period (<?= count($parcels) ?> entries).</div>
    <table>
      <thead><tr><th>Tracking</th><th>Customer</th><th>Driver</th><th>Status</th><th>Pay</th><th class="num">Amount</th><th>Created</th></tr></thead>
      <tbody>
        <?php foreach ($parcels as $p): ?>
          <tr>
            <td style="font-family:monospace;font-size:11px"><?= esc($p['tracking_number']) ?></td>
            <td><?= esc($p['customer'] ?: '—') ?></td>
            <td><?= esc($p['driver'] ?: '—') ?></td>
            <td><?= esc($p['status']) ?></td>
            <td style="text-transform:capitalize"><?= esc($p['payment_method'] ?: '—') ?></td>
            <td class="num">₱<?= number_format((float)$p['amount'], 2) ?></td>
            <td style="font-size:11px"><?= esc(date('M j g:ia', strtotime($p['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="signoff">
    <div class="sig"><div class="line">Prepared by — <?= esc($admin) ?></div></div>
    <div class="sig"><div class="line">Reviewed by</div></div>
  </div>

  <div class="footer">
    This document is system-generated by Drop IT. For inquiries, contact the platform administrator.<br>
    Generated <?= esc($generated) ?> · Drop IT © <?= date('Y') ?>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script>
// Server-queried data, embedded for the PDF builder. Source = the same SQL queries that built this page.
const RPT = {
  type:    <?= json_encode($type) ?>,
  title:   <?= json_encode($report_title) ?>,
  from:    <?= json_encode(date('M j, Y', strtotime($from))) ?>,
  to:      <?= json_encode(date('M j, Y', strtotime($to))) ?>,
  fromIso: <?= json_encode($from) ?>,
  toIso:   <?= json_encode($to) ?>,
  generated: <?= json_encode($generated) ?>,
  admin:   <?= json_encode($admin) ?>,
  totals:  <?= json_encode($tot ?: []) ?>,
  daily:   <?= json_encode($daily ?: []) ?>,
  drivers: <?= json_encode($drivers ?: []) ?>,
  pay:     <?= json_encode($pay ?: []) ?>,
  parcels: <?= json_encode($parcels ?: []) ?>,
};

function peso(n){ return '₱' + (Number(n)||0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
function num(n){ return String(parseInt(n||0,10)); }

function buildPdf(){
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ unit:'pt', format:'a4' });
  const pageW = doc.internal.pageSize.getWidth();
  const margin = 40;
  let y = margin;

  // Header
  doc.setFont('helvetica', 'bold'); doc.setFontSize(18); doc.setTextColor(11,30,63);
  doc.text('Drop IT', margin, y);
  doc.setFont('helvetica','normal'); doc.setFontSize(9); doc.setTextColor(59,90,138);
  doc.text('OFFICIAL BUSINESS REPORT', margin, y+14);
  // Right-aligned meta
  doc.setFontSize(10); doc.setTextColor(11,30,63);
  const meta = [
    ['Report:',   RPT.title],
    ['Period:',   RPT.from + ' – ' + RPT.to],
    ['Prepared:', RPT.generated],
    ['By:',       RPT.admin],
  ];
  let my = y;
  meta.forEach(([k,v]) => {
    const line = k + ' ' + v;
    doc.text(line, pageW - margin, my, { align:'right' });
    my += 12;
  });
  y = Math.max(y + 24, my) + 8;
  doc.setDrawColor(11,30,63); doc.setLineWidth(1.5);
  doc.line(margin, y, pageW - margin, y);
  y += 18;

  // KPI row
  const t = RPT.totals || {};
  const kpis = [
    ['Revenue',   peso(t.rev)],
    ['Parcels',   num(t.c)],
    ['Delivered', num(t.delivered)],
    ['Cancelled', num(t.cancelled)],
  ];
  const boxW = (pageW - margin*2 - 12*3) / 4;
  kpis.forEach((kv, i) => {
    const x = margin + i*(boxW + 12);
    doc.setFillColor(250,251,255); doc.setDrawColor(219,229,245);
    doc.roundedRect(x, y, boxW, 56, 6, 6, 'FD');
    doc.setFontSize(8); doc.setTextColor(59,90,138);
    doc.text(kv[0].toUpperCase(), x+10, y+16);
    doc.setFontSize(16); doc.setFont('helvetica','bold'); doc.setTextColor(11,30,63);
    doc.text(kv[1], x+10, y+40);
    doc.setFont('helvetica','normal');
  });
  y += 72;

  // Table per report type
  const auto = (head, body, opts={}) => {
    doc.autoTable({
      startY: y,
      head: [head],
      body: body,
      margin: { left: margin, right: margin },
      styles: { font:'helvetica', fontSize: 9, cellPadding: 6, textColor: [11,30,63] },
      headStyles: { fillColor: [241,245,255], textColor: [59,90,138], fontStyle:'bold', fontSize: 8 },
      alternateRowStyles: { fillColor: [250,251,255] },
      ...opts,
    });
    y = doc.lastAutoTable.finalY + 16;
  };
  const sectionTitle = (txt, sub) => {
    doc.setFont('helvetica','bold'); doc.setFontSize(13); doc.setTextColor(11,30,63);
    doc.text(txt, margin, y); y += 14;
    if (sub) { doc.setFont('helvetica','normal'); doc.setFontSize(9); doc.setTextColor(122,142,166); doc.text(sub, margin, y); y += 12; }
  };

  if (RPT.type === 'summary') {
    sectionTitle('Daily Activity', 'Parcel volume and revenue per day during the selected period.');
    if (!RPT.daily.length) {
      doc.setFontSize(10); doc.setTextColor(122,142,166);
      doc.text('No activity in this period.', margin, y); y += 24;
    } else {
      auto(
        ['Date','Parcels','Delivered','Revenue'],
        RPT.daily.map(d => [
          new Date(d.d).toDateString(),
          num(d.c),
          num(d.delivered),
          peso(d.rev),
        ]),
        { columnStyles: { 1:{halign:'right'}, 2:{halign:'right'}, 3:{halign:'right'} } }
      );
    }
  }

  if (RPT.type === 'drivers') {
    sectionTitle('Driver Performance', "Only Delivered parcels counted. Earnings = driver's 90% share.");
    auto(
      ['Driver','Phone','Trips','Distance','Earnings','Rating'],
      RPT.drivers.map(d => [
        d.full_name || d.username || '—',
        d.phone || '—',
        num(d.trips),
        (Number(d.km)||0).toFixed(2) + ' km',
        peso(d.earn),
        (parseInt(d.rating_count||0,10) > 0 ? '★' + (Number(d.rating_avg)||0).toFixed(2) + ' (' + d.rating_count + ')' : '—'),
      ]),
      { columnStyles: { 2:{halign:'right'}, 3:{halign:'right'}, 4:{halign:'right'}, 5:{halign:'right'} } }
    );
  }

  if (RPT.type === 'revenue') {
    sectionTitle('Revenue Breakdown by Payment Method', 'Platform fee = 10% of gross; driver share = 90%.');
    const sum = { c:0, rev:0, fee:0, drv:0 };
    const rows = RPT.pay.map(p => {
      sum.c += parseInt(p.c||0,10);
      sum.rev += parseFloat(p.rev||0);
      sum.fee += parseFloat(p.fee||0);
      sum.drv += parseFloat(p.drv||0);
      return [
        (p.payment_method || '—'),
        num(p.c),
        peso(p.rev),
        peso(p.fee),
        peso(p.drv),
      ];
    });
    rows.push(['TOTAL', num(sum.c), peso(sum.rev), peso(sum.fee), peso(sum.drv)]);
    auto(
      ['Method','Parcels','Gross','Platform Fee','Driver Share'],
      rows,
      {
        columnStyles: { 1:{halign:'right'}, 2:{halign:'right'}, 3:{halign:'right'}, 4:{halign:'right'} },
        didParseCell: data => {
          if (data.row.index === rows.length - 1) {
            data.cell.styles.fillColor = [241,245,255];
            data.cell.styles.fontStyle = 'bold';
          }
        }
      }
    );
  }

  if (RPT.type === 'parcels') {
    sectionTitle('Parcel Log', 'All parcels created in the selected period (' + RPT.parcels.length + ' entries).');
    auto(
      ['Tracking','Customer','Driver','Status','Pay','Amount','Created'],
      RPT.parcels.map(p => [
        p.tracking_number || '',
        p.customer || '—',
        p.driver || '—',
        p.status || '',
        p.payment_method || '—',
        peso(p.amount),
        new Date(p.created_at).toLocaleString(),
      ]),
      { columnStyles: { 5:{halign:'right'} }, styles:{fontSize:8, cellPadding:4} }
    );
  }

  // Sign-off + footer on the last page
  if (y > doc.internal.pageSize.getHeight() - 120) { doc.addPage(); y = margin; }
  y += 24;
  const colW = (pageW - margin*2 - 40) / 2;
  doc.setDrawColor(11,30,63); doc.setLineWidth(0.6);
  doc.line(margin, y, margin + colW, y);
  doc.line(margin + colW + 40, y, pageW - margin, y);
  doc.setFontSize(9); doc.setTextColor(59,90,138);
  doc.text('Prepared by — ' + RPT.admin, margin + colW/2, y+12, { align:'center' });
  doc.text('Reviewed by',                  margin + colW + 40 + colW/2, y+12, { align:'center' });

  // Footer on every page
  const total = doc.getNumberOfPages();
  for (let i = 1; i <= total; i++) {
    doc.setPage(i);
    doc.setFontSize(8); doc.setTextColor(122,142,166);
    const ph = doc.internal.pageSize.getHeight();
    doc.text('Drop IT © ' + new Date().getFullYear() + ' · ' + RPT.generated, pageW/2, ph - 22, { align:'center' });
    doc.text('Page ' + i + ' of ' + total, pageW - margin, ph - 22, { align:'right' });
  }

  const safe = s => String(s).replace(/[^a-z0-9-]+/gi,'-').toLowerCase();
  doc.save('dropit-' + safe(RPT.type) + '-' + RPT.fromIso + '-to-' + RPT.toIso + '.pdf');
}

// btnPdf is now a server-side PDF link — jsPDF kept below for reference only.
// document.getElementById('btnPdf').addEventListener('click', buildPdf);

// Auto-open print dialog when page loads (so "Open report" directly triggers print/save-PDF).
window.addEventListener('load', () => setTimeout(() => window.print(), 500));
</script>

</body></html>
