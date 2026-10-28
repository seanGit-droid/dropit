<?php defined('APP_ROOT') OR exit('No direct script access allowed');
// Theme preference is stored in session; set by /api/theme.
if (!isset($_SESSION['theme'])) $_SESSION['theme'] = 'dark';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no">
<title><?= isset($page_title) ? esc($page_title).' &mdash; '.APP_NAME : APP_NAME ?></title>
<link rel="icon" type="image/png" href="<?= url('resources/weblogo.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        brand: {
          ink:    '#061124',
          navy:   '#0b1e3f',
          deep:   '#12306b',
          blue:   '#1e40af',
          azure:  '#3b82f6',
          sky:    '#60a5fa',
          mist:   '#bfdbfe',
          frost:  '#e0f2fe',
        }
      },
      fontFamily: { sans: ['Inter','system-ui','sans-serif'] },
      boxShadow: {
        'glass': '0 8px 32px 0 rgba(8, 20, 52, 0.37)',
        'glow':  '0 0 24px 4px rgba(59,130,246,0.35)',
      }
    }
  }
};
</script>
<style>
/* ── Base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Inter', system-ui, sans-serif;
  background: linear-gradient(135deg, #061124 0%, #0b1e3f 60%, #12306b 100%);
  min-height: 100vh; color: #e0f2fe;
}
/* ── Glass ── */
.glass        { background: rgba(255,255,255,0.07); backdrop-filter: blur(16px) saturate(160%); -webkit-backdrop-filter: blur(16px) saturate(160%); border: 1px solid rgba(255,255,255,0.14); }
.glass-strong { background: rgba(255,255,255,0.10); backdrop-filter: blur(24px) saturate(180%); -webkit-backdrop-filter: blur(24px) saturate(180%); border: 1px solid rgba(255,255,255,0.18); }
.glass-light  { background: rgba(255,255,255,0.04); backdrop-filter: blur(10px);                -webkit-backdrop-filter: blur(10px);                border: 1px solid rgba(255,255,255,0.08); }
.shadow-glass { box-shadow: 0 8px 32px 0 rgba(8,20,52,0.37); }
.shadow-glow  { box-shadow: 0 0 24px 4px rgba(59,130,246,0.35); }
/* ── Buttons ── */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 14px; font-weight: 600; font-size: 0.875rem; cursor: pointer; transition: all 0.2s ease; border: none; text-decoration: none; }
.btn-primary { background: linear-gradient(135deg, #3b82f6, #1e40af); color: #fff; }
.btn-primary:hover { background: linear-gradient(135deg, #60a5fa, #3b82f6); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(59,130,246,0.4); }
.btn-ghost   { background: rgba(255,255,255,0.08); color: #bfdbfe; border: 1px solid rgba(255,255,255,0.18); }
.btn-ghost:hover { background: rgba(255,255,255,0.14); color: #fff; }
.btn-danger  { background: rgba(239,68,68,0.18); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
.btn-danger:hover { background: rgba(239,68,68,0.30); }
.btn-success { background: linear-gradient(135deg, #22c55e, #15803d); color: #fff; }
/* ── Inputs ── */
.input { width: 100%; padding: 12px 16px; border-radius: 12px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); color: #fff; font-size: 0.875rem; outline: none; transition: border-color 0.2s; font-family: inherit; }
.input:focus { border-color: #3b82f6; background: rgba(255,255,255,0.12); }
.input::placeholder { color: rgba(191,219,254,0.5); }
.label { display: block; font-size: 0.75rem; font-weight: 600; color: #bfdbfe; margin-bottom: 6px; letter-spacing: 0.04em; text-transform: uppercase; }
/* ── Chips ── */
.chip { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: 0.03em; }
.chip-pending   { background: rgba(245,158,11,0.18); color: #fcd34d; }
.chip-transit   { background: rgba(59,130,246,0.18);  color: #93c5fd; }
.chip-arrived   { background: rgba(168,85,247,0.18);  color: #d8b4fe; }
.chip-delivered { background: rgba(34,197,94,0.18);   color: #86efac; }
.chip-cancelled { background: rgba(239,68,68,0.15);   color: #fca5a5; }
/* ── SVG icons ── */
.dropit-icon         { fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.dropit-icon-active  { fill: none; stroke: #fff; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.dropit-icon-muted   { fill: none; stroke: #bfdbfe; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
/* ── SVG icons ── */
.dropit-icon        { fill: none; stroke: #bfdbfe; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.dropit-icon-active { fill: none; stroke: #ffffff; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.dropit-icon-muted  { fill: none; stroke: #7097c4; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
/* ── Sidebar ── */
.sidebar-link { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 12px; font-size: 0.875rem; color: #bfdbfe; transition: all 0.15s; text-decoration: none; }
.sidebar-link:hover, .sidebar-link.active { background: rgba(59,130,246,0.18); color: #fff; }
/* ── Scrollbar ── */
.scrollbar-thin::-webkit-scrollbar { width: 4px; height: 4px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 99px; }
/* ── Select/combobox: browsers ignore backdrop-filter on native selects ── */
select, select.input {
  background-color: #0f2347 !important;
  color: #e0f2fe !important;
  border: 1px solid rgba(255,255,255,0.18) !important;
}
select option { background-color: #0f2347; color: #e0f2fe; }
body.theme-light select, body.theme-light select.input {
  background-color: #f0f4ff !important;
  color: #0b1e3f !important;
  border-color: rgba(11,30,63,0.22) !important;
}
body.theme-light select option { background-color: #fff; color: #0b1e3f; }
/* ── Flash / toast ── */
.flash-success { background: rgba(34,197,94,0.18); border: 1px solid rgba(34,197,94,0.35); color: #86efac; padding: 12px 16px; border-radius: 12px; font-size: 0.875rem; margin-bottom: 12px; }
.flash-error   { background: rgba(239,68,68,0.15);  border: 1px solid rgba(239,68,68,0.3);  color: #fca5a5; padding: 12px 16px; border-radius: 12px; font-size: 0.875rem; margin-bottom: 12px; }
.flash-info    { background: rgba(59,130,246,0.15);  border: 1px solid rgba(59,130,246,0.3);  color: #93c5fd; padding: 12px 16px; border-radius: 12px; font-size: 0.875rem; margin-bottom: 12px; }
/* ── Light mode overrides ── */
body.theme-light {
  background: linear-gradient(135deg,#e8f0fe 0%,#f0f4ff 60%,#dbeafe 100%) !important;
  color: #0b1e3f !important;
}
body.theme-light .glass        { background: rgba(255,255,255,0.75) !important; border-color: rgba(11,30,63,0.12) !important; }
body.theme-light .glass-strong { background: rgba(255,255,255,0.88) !important; border-color: rgba(11,30,63,0.16) !important; }
body.theme-light .glass-light  { background: rgba(255,255,255,0.55) !important; border-color: rgba(11,30,63,0.08) !important; }
body.theme-light .input        { background: rgba(255,255,255,0.9) !important; border-color: rgba(11,30,63,0.2) !important; color: #0b1e3f !important; }
body.theme-light .input::placeholder { color: rgba(11,30,63,0.4) !important; }
body.theme-light .input:focus  { border-color: #3b82f6 !important; }
body.theme-light .btn-ghost    { background: rgba(11,30,63,0.08) !important; color: #0b1e3f !important; border-color: rgba(11,30,63,0.18) !important; }
body.theme-light .btn-ghost:hover { background: rgba(11,30,63,0.14) !important; }
body.theme-light .label        { color: #1e40af !important; }
body.theme-light .chip-pending { background: rgba(180,83,9,0.12); color: #92400e; }
body.theme-light .chip-transit { background: rgba(29,78,216,0.12); color: #1e40af; }
body.theme-light .chip-delivered { background: rgba(21,128,61,0.12); color: #15803d; }
body.theme-light .chip-cancelled { background: rgba(185,28,28,0.12); color: #b91c1c; }
body.theme-light .text-brand-mist { color: #1e3a5f !important; }
body.theme-light .text-emerald-300 { color: #15803d !important; }
body.theme-light .text-red-300 { color: #b91c1c !important; }
body.theme-light .text-amber-300 { color: #b45309 !important; }
body.theme-light .font-bold,.theme-light .font-semibold { color: #0b1e3f; }
body.theme-light h1,body.theme-light h2,body.theme-light h3 { color: #061124; }
body.theme-light .scrollbar-thin::-webkit-scrollbar-thumb { background: rgba(11,30,63,.2); }
body.theme-light select option,body.theme-light .input option {
  background-color: #fff;
  color: #0b1e3f;
}
body.theme-light [data-notif-badge],[data-msg-badge] { color: #fff !important; }
</style>
</head>
<body class="antialiased <?= (($_SESSION['theme'] ?? 'dark')==='light') ? 'theme-light' : '' ?>" data-uid="<?= (int)($_SESSION['user_id'] ?? 0) ?>" data-role="<?= esc($_SESSION['role'] ?? '') ?>">
<?php
// helper: status chip
function status_chip($status){
    $s = strtolower($status);
    $cls = 'chip-pending';
    if (str_contains($s,'transit') || str_contains($s,'picked')) $cls='chip-transit';
    elseif (str_contains($s,'arrived')) $cls='chip-arrived';
    elseif (str_contains($s,'delivered')) $cls='chip-delivered';
    elseif (str_contains($s,'cancel')) $cls='chip-cancelled';
    return '<span class="chip '.$cls.'">'.esc($status).'</span>';
}
?>
