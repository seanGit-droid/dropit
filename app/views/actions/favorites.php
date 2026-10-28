<?php defined('APP_ROOT') OR exit('No direct script access allowed');
$uid = (int)$_SESSION['user_id'];
$act = strpos($_SERVER['REQUEST_URI'] ?? '', '/delete/') !== false ? 'delete' : 'create';

if ($act === 'delete') {
    $fid = (int)($id ?? 0);
    db()->table('favorite_addresses')->where('id',$fid)->where('user_id',$uid)->delete();
    set_flash('success','Deleted.');
    header('Location: ' . url('customer/favorites')); exit;
}

$label = trim($_POST['label'] ?? '');
$addr  = trim($_POST['address'] ?? '');
$lat = $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
$lng = $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;
if ($label === '' || $addr === '') { set_flash('error','Fill label + address.'); header('Location: ' . url('customer/favorites')); exit; }

db()->table('favorite_addresses')->insert(['user_id'=>$uid, 'label'=>$label, 'address'=>$addr, 'lat'=>$lat, 'lng'=>$lng]);
set_flash('success','Saved.');
header('Location: ' . url('customer/favorites')); exit;
