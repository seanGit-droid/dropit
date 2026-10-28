<?php defined('APP_ROOT') OR exit('No direct script access allowed');
/**
 * GET /api/customer/price-reviews
 * Returns any parcels in PriceReview status for the logged-in customer.
 */
if (($_SESSION['role'] ?? '') !== 'customer') { json_response(['ok'=>false,'error'=>'Forbidden'], 403); }

$uid = (int)$_SESSION['user_id'];
$rows = db()->raw("
    SELECT id, tracking_number, amount, pending_amount, price_adjust_reason,
           price_review_status, pickup_address, recipient_address
    FROM parcels
    WHERE customer_id = ? AND status = 'PriceReview' AND price_review_status = 'waiting'
    ORDER BY updated_at DESC
    LIMIT 5
", [$uid])->fetchAll();

json_response(['ok'=>true, 'reviews'=>$rows]);
