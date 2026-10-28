<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$tracking = $tracking ?? '';
$uid = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

$p = db()->table('parcels')
    ->select('id, tracking_number, status, customer_id, driver_id, sender_name, pickup_address, recipient_name, recipient_address, recipient_phone, product_name, distance_km, amount, admin_fee, driver_earnings, payment_method, payment_status, pod_photo, pod_signature, pod_receiver, created_at, updated_at')
    ->where('tracking_number', $tracking)->get();
if (!$p) { http_response_code(404); echo "Not found"; exit; }

$allowed = $role === 'admin'
        || ($role === 'customer' && (int)$p['customer_id'] === $uid)
        || ($role === 'driver'   && (int)$p['driver_id']   === $uid);
if (!$allowed) { http_response_code(403); echo "Forbidden"; exit; }

$driver   = $p['driver_id']   ? db()->table('users')->select('full_name, phone')->where('id',$p['driver_id'])->get() : null;
$customer = $p['customer_id'] ? db()->table('users')->select('full_name, phone, email')->where('id',$p['customer_id'])->get() : null;

$page_title = 'Receipt ' . $p['tracking_number'];
$base_fare  = 30.00;
$distance_fee = max(0, (float)$p['amount'] - $base_fare);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($page_title) ?> — <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="<?= url('resources/weblogo.png') ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  html,body{background:#eef2f7;color:#0b1e3f;font-family:'Inter',system-ui,sans-serif;min-height:100vh;}
  .wrap{max-width:720px;margin:32px auto;padding:0 20px;}
  .toolbar{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:16px;}
  .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 16px;border-radius:12px;border:1px solid transparent;font-weight:600;font-size:13px;cursor:pointer;transition:all .2s ease;text-decoration:none;}
  .btn-ghost{background:#fff;color:#0b1e3f;border-color:#cbd5e1;}
  .btn-ghost:hover{background:#f8fafc;}
  .btn-primary{background:linear-gradient(135deg,#3b82f6,#1e40af);color:#fff;box-shadow:0 6px 14px -4px rgba(59,130,246,.45);}
  .btn-primary:hover{transform:translateY(-1px);}

  .receipt{background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 24px 60px -24px rgba(8,20,52,.25);border:1px solid #e2e8f0;}
  .bar{height:6px;background:linear-gradient(90deg,#3b82f6 0%,#1e40af 50%,#60a5fa 100%);}

  .header{display:flex;align-items:center;gap:16px;padding:28px 32px 20px;}
  .header img{width:52px;height:52px;border-radius:14px;background:#eff6ff;padding:6px;}
  .header h1{font-size:22px;font-weight:800;letter-spacing:-0.02em;line-height:1.1;}
  .header .sub{font-size:12px;color:#64748b;margin-top:2px;letter-spacing:.02em;text-transform:uppercase;}
  .header .status-right{margin-left:auto;text-align:right;}
  .header .tn-label{font-size:10px;color:#64748b;letter-spacing:.1em;text-transform:uppercase;}
  .header .tn{font-family:'JetBrains Mono',ui-monospace,monospace;font-weight:700;font-size:16px;background:#0b1e3f;color:#fff;padding:5px 10px;border-radius:8px;display:inline-block;margin-top:4px;letter-spacing:.05em;}

  .status-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 32px 20px;border-bottom:1px solid #e2e8f0;}
  .status-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:700;}
  .status-delivered{background:#dcfce7;color:#166534;}
  .status-transit{background:#dbeafe;color:#1e40af;}
  .status-pending{background:#fef3c7;color:#92400e;}
  .status-cancelled{background:#fee2e2;color:#991b1b;}
  .status-arrived{background:#f3e8ff;color:#6b21a8;}
  .meta-dates{display:flex;gap:24px;flex-wrap:wrap;font-size:12px;color:#475569;}
  .meta-dates b{color:#0b1e3f;font-weight:600;margin-left:4px;}

  .addr-grid{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:#e2e8f0;}
  .addr{padding:22px 32px;background:#fff;}
  .addr .tag{font-size:10px;font-weight:700;letter-spacing:.12em;color:#64748b;text-transform:uppercase;margin-bottom:6px;}
  .addr .name{font-weight:700;font-size:15px;color:#0b1e3f;}
  .addr .detail{font-size:12px;color:#475569;line-height:1.55;margin-top:4px;}

  .section-title{font-size:10px;font-weight:700;letter-spacing:.12em;color:#64748b;text-transform:uppercase;padding:18px 32px 8px;}

  .fare{padding:0 32px 20px;}
  .fare .row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px dashed #e2e8f0;font-size:13px;}
  .fare .row:last-child{border-bottom:none;}
  .fare .row .lbl{color:#475569;}
  .fare .row .val{font-variant-numeric:tabular-nums;color:#0b1e3f;}
  .fare .total{display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding:14px 0 0;border-top:2px solid #0b1e3f;}
  .fare .total .lbl{font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#64748b;font-weight:700;}
  .fare .total .val{font-size:22px;font-weight:800;color:#0b1e3f;font-variant-numeric:tabular-nums;}

  .footmeta{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;padding:20px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;}
  .footmeta .k{color:#64748b;}
  .footmeta .v{color:#0b1e3f;font-weight:600;}

  .pod{padding:20px 32px;border-top:1px solid #e2e8f0;}
  .pod .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:10px;}
  .pod img{display:block;width:100%;border-radius:12px;border:1px solid #e2e8f0;max-height:240px;object-fit:cover;}
  .pod .sig{background:#fff;padding:10px;}

  .footer{display:flex;justify-content:space-between;align-items:center;padding:16px 32px;border-top:1px solid #e2e8f0;font-size:11px;color:#64748b;}
  .footer .brand{font-weight:700;color:#0b1e3f;}

  @media (max-width:640px){
    .header{padding:22px 20px 16px;}
    .status-row{padding:8px 20px 16px;flex-direction:column;align-items:flex-start;}
    .addr{padding:16px 20px;}
    .addr-grid{grid-template-columns:1fr;}
    .fare,.footmeta,.pod,.footer{padding-left:20px;padding-right:20px;}
    .pod .grid{grid-template-columns:1fr;}
    .footmeta{grid-template-columns:1fr;}
  }

  @media print {
    html,body{background:#fff;}
    .wrap{margin:0;padding:0;max-width:none;}
    .toolbar{display:none;}
    .receipt{border-radius:0;border:none;box-shadow:none;}
    .bar{background:#000;height:3px;}
    .btn{display:none;}
    a{color:inherit;text-decoration:none;}
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <a href="<?= url($role==='admin' ? 'admin/shipments/assign' : 'customer/track') ?>" class="btn btn-ghost">← Back</a>
    <button onclick="window.print()" class="btn btn-primary">🖨️ Print / Save PDF</button>
  </div>

  <div class="receipt">
    <div class="bar"></div>

    <div class="header">
      <img src="<?= url('resources/weblogo.png') ?>" alt="Drop IT">
      <div>
        <h1>Drop IT</h1>
        <div class="sub">Official Delivery Receipt</div>
      </div>
      <div class="status-right">
        <div class="tn-label">Tracking No.</div>
        <div class="tn"><?= esc($p['tracking_number']) ?></div>
      </div>
    </div>

    <?php
      $s = strtolower((string)$p['status']);
      $scls = 'status-pending';
      if (str_contains($s,'deliver')) $scls = 'status-delivered';
      elseif (str_contains($s,'transit') || str_contains($s,'picked')) $scls = 'status-transit';
      elseif (str_contains($s,'arrived')) $scls = 'status-arrived';
      elseif (str_contains($s,'cancel')) $scls = 'status-cancelled';
    ?>
    <div class="status-row">
      <span class="status-pill <?= $scls ?>">● <?= esc($p['status']) ?></span>
      <div class="meta-dates">
        <div>Placed: <b><?= date('M j, Y · g:ia', strtotime($p['created_at'])) ?></b></div>
        <div>Updated: <b><?= date('M j, Y · g:ia', strtotime($p['updated_at'])) ?></b></div>
      </div>
    </div>

    <div class="addr-grid">
      <div class="addr">
        <div class="tag">From — Sender</div>
        <div class="name"><?= esc($p['sender_name'] ?: ($customer['full_name'] ?? '—')) ?></div>
        <div class="detail"><?= esc($p['pickup_address']) ?></div>
      </div>
      <div class="addr">
        <div class="tag">To — Recipient</div>
        <div class="name"><?= esc($p['recipient_name']) ?></div>
        <div class="detail"><?= esc($p['recipient_address']) ?><?php if (!empty($p['recipient_phone'])): ?><br><?= esc(Notifier::display_phone($p['recipient_phone'] ?: '')) ?><?php endif; ?></div>
      </div>
    </div>

    <div class="section-title">Fare Breakdown</div>
    <div class="fare">
      <div class="row"><span class="lbl">Product</span><span class="val"><?= esc($p['product_name'] ?: '—') ?></span></div>
      <div class="row"><span class="lbl">Distance</span><span class="val"><?= number_format((float)$p['distance_km'], 2) ?> km</span></div>
      <div class="row"><span class="lbl">Base fare</span><span class="val">₱<?= number_format($base_fare, 2) ?></span></div>
      <div class="row"><span class="lbl">Distance fee (₱3/km)</span><span class="val">₱<?= number_format($distance_fee, 2) ?></span></div>
      <div class="row"><span class="lbl">Platform fee (10%)</span><span class="val">₱<?= number_format((float)$p['admin_fee'], 2) ?></span></div>
      <div class="row"><span class="lbl">Driver earnings (90%)</span><span class="val">₱<?= number_format((float)$p['driver_earnings'], 2) ?></span></div>
      <div class="total"><span class="lbl">Total paid</span><span class="val">₱<?= number_format((float)$p['amount'], 2) ?></span></div>
    </div>

    <div class="footmeta">
      <div><span class="k">Payment:</span> <span class="v"><?= esc($p['payment_method']) ?> · <?= esc($p['payment_status']) ?></span></div>
      <div><span class="k">Driver:</span> <span class="v"><?= $driver ? esc($driver['full_name']) . ' · ' . esc(Notifier::display_phone($driver['phone'] ?: '')) : '—' ?></span></div>
      <?php if (!empty($p['pod_receiver'])): ?>
        <div><span class="k">Received by:</span> <span class="v"><?= esc($p['pod_receiver']) ?></span></div>
      <?php endif; ?>
      <?php if ($customer): ?>
        <div><span class="k">Customer:</span> <span class="v"><?= esc($customer['full_name']) ?></span></div>
      <?php endif; ?>
    </div>

    <?php if (!empty($p['pod_photo']) || !empty($p['pod_signature'])): ?>
    <div class="pod">
      <div class="section-title" style="padding:0;">Proof of Delivery</div>
      <div class="grid">
        <?php if (!empty($p['pod_photo'])): ?>
          <div><div class="tag" style="font-size:10px;font-weight:700;letter-spacing:.12em;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Photo</div><img src="<?= url($p['pod_photo']) ?>" alt="Delivery photo"></div>
        <?php endif; ?>
        <?php if (!empty($p['pod_signature'])): ?>
          <div><div class="tag" style="font-size:10px;font-weight:700;letter-spacing:.12em;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Signature</div><img src="<?= esc($p['pod_signature']) ?>" alt="Signature" class="sig"></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="footer">
      <div><span class="brand">Drop IT</span> · Thank you for shipping with us.</div>
      <div>Generated <?= date('M j, Y g:ia') ?></div>
    </div>
  </div>
</div>
</body>
</html>
