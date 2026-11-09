<?php defined('APP_ROOT') OR exit('No direct script access allowed');
require_once APP_ROOT.'/scheme/GCashSimulation.php';
$pid=(int)($id ?? 0); $uid=(int)$_SESSION['user_id'];
try {
    if (!(new Router())->is_csrf_valid()) throw new InvalidArgumentException('Your payment form expired. Please retry.');
    $parcel=GCashSimulation::pay_existing(db(),$pid,$uid);
    set_flash('success','PAID (Via GCash Simulation). Your pickup request is confirmed.');
    header('Location: '.url('customer/track?q='.urlencode($parcel['tracking_number']))); exit;
} catch (Throwable $e) {
    if (!($e instanceof InvalidArgumentException)) error_log('GCash simulation retry failed: '.get_class($e));
    set_flash('error',$e instanceof InvalidArgumentException?$e->getMessage():'GCash simulated payment could not be saved. Please retry.');
    header('Location: '.url('customer/order')); exit;
}
