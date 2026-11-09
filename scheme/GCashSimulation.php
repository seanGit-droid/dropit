<?php defined('APP_ROOT') OR exit('No direct script access allowed');
/** Local checkout simulation. No external gateway calls or wallet credits. */
final class GCashSimulation
{
    private static function cents($value): int { return (int)round((float)$value*100); }
    private static function insert(Database $db,string $table,array $fields): int
    {
        $db->raw('INSERT INTO '.$table.' ('.implode(',',array_keys($fields)).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));
        return (int)$db->raw('SELECT LAST_INSERT_ID()')->fetchColumn();
    }
    private static function debit(Database $db,array $customer,int $amount,int $pid,string $tracking): string
    {
        if ($amount<=0) throw new InvalidArgumentException('Invalid GCash simulated fare.');
        $available=self::cents($customer['wallet_balance']);
        if ($available<$amount) throw new InvalidArgumentException('Insufficient GCash simulated balance.');
        $balance=number_format(($available-$amount)/100,2,'.','');
        $charge=number_format($amount/100,2,'.','');
        $db->raw('UPDATE users SET wallet_balance=? WHERE id=?',[$balance,$customer['id']]);
        self::insert($db,'wallet_transactions',[
            'user_id'=>$customer['id'],'parcel_id'=>$pid,'amount'=>'-'.$charge,'type'=>'delivery_charge',
            'description'=>"Order {$tracking} — PAID (Via GCash Simulation), held in escrow",'balance_after'=>$balance,
        ]);
        return 'gcash_sim_'.bin2hex(random_bytes(12));
    }
    public static function book(Database $db,int $uid,array $data,array $fare): array
    {
        $db->transaction();
        try {
            $customer=$db->raw("SELECT id,username,full_name,phone,email,wallet_balance FROM users WHERE id=? AND role='customer' FOR UPDATE",[$uid])->fetch();
            if (!$customer) throw new InvalidArgumentException('Customer account not found.');
            $amount=self::cents($fare['amount']);
            // Reject before creating any booking or payment records.
            if (self::cents($customer['wallet_balance'])<$amount) throw new InvalidArgumentException('Insufficient GCash simulated balance.');
            $row=array_merge($data,[
                'tracking_number'=>'TMP-'.bin2hex(random_bytes(6)),'customer_id'=>$uid,'sender_address'=>$data['pickup_address'],
                'status'=>'Pending','is_customer_order'=>1,'vehicle_type'=>'Tricycle','distance_km'=>$fare['distance_km'],
                'amount'=>$fare['amount'],'admin_fee'=>$fare['admin_fee'],'driver_earnings'=>$fare['driver_earnings'],
                // Existing delivery settlement/refunds expect prepaid funds to remain held until delivery.
                'payment_status'=>'held','payment_method'=>'gcash','accept_token'=>bin2hex(random_bytes(16)),
            ]);
            $pid=self::insert($db,'parcels',$row);
            $tracking='DROPIT'.str_pad((string)$pid,5,'0',STR_PAD_LEFT);
            $reference=self::debit($db,$customer,$amount,$pid,$tracking);
            $db->raw('UPDATE parcels SET tracking_number=?,payment_ref=? WHERE id=?',[$tracking,$reference,$pid]);
            self::insert($db,'payments',[
                'parcel_id'=>$pid,'method'=>'gcash','amount'=>$fare['amount'],'status'=>'paid','provider_ref'=>$reference,'checkout_url'=>null,
            ]);
            self::insert($db,'parcel_history',[
                'parcel_id'=>$pid,'status'=>'Pending','updated_by'=>$uid,
                'notes'=>'Customer placed order. PAID (Via GCash Simulation). Fare ₱'.number_format($fare['amount'],2).'.',
            ]);
            self::insert($db,'activity_logs',[
                'user_id'=>$uid,'action'=>'order_placed','details'=>"Customer placed order {$tracking} — ₱{$fare['amount']} (GCash Simulation).",
            ]);
            $db->commit();
            return ['id'=>$pid,'tracking'=>$tracking,'customer'=>$customer,'parcel'=>array_merge($row,['id'=>$pid,'tracking_number'=>$tracking,'payment_ref'=>$reference])];
        } catch (Throwable $e) { $db->roll_back(); throw $e; }
    }
    // Pay an existing unpaid GCash booking. A repeated callback never deducts twice.
    public static function pay_existing(Database $db,int $pid,int $uid): array
    {
        $db->transaction();
        try {
            $parcel=$db->raw('SELECT * FROM parcels WHERE id=? FOR UPDATE',[$pid])->fetch();
            if (!$parcel || (int)$parcel['customer_id']!==$uid || $parcel['payment_method']!=='gcash') throw new InvalidArgumentException('GCash booking not found.');
            if (in_array($parcel['payment_status'],['held','settled'],true)) { $db->roll_back(); return $parcel; }
            if ($parcel['payment_status']!=='unpaid' || in_array($parcel['status'],['Cancelled','Delivered','Return Pending','Returned'],true)) throw new InvalidArgumentException('This booking cannot be paid.');
            $customer=$db->raw("SELECT id,wallet_balance FROM users WHERE id=? AND role='customer' FOR UPDATE",[$uid])->fetch();
            if (!$customer) throw new InvalidArgumentException('Customer account not found.');
            $reference=self::debit($db,$customer,self::cents($parcel['amount']),$pid,$parcel['tracking_number']);
            $db->raw("UPDATE parcels SET payment_status='held',payment_ref=? WHERE id=?",[$reference,$pid]);
            $payment=$db->raw("SELECT id FROM payments WHERE parcel_id=? AND method='gcash' ORDER BY id LIMIT 1 FOR UPDATE",[$pid])->fetch();
            if ($payment) $db->raw("UPDATE payments SET status='paid',amount=?,provider_ref=?,checkout_url=NULL WHERE id=?",[$parcel['amount'],$reference,$payment['id']]);
            else self::insert($db,'payments',['parcel_id'=>$pid,'method'=>'gcash','amount'=>$parcel['amount'],'status'=>'paid','provider_ref'=>$reference,'checkout_url'=>null]);
            self::insert($db,'parcel_history',['parcel_id'=>$pid,'status'=>$parcel['status'],'updated_by'=>$uid,'notes'=>'PAID (Via GCash Simulation). Simulated wallet deduction saved.']);
            $db->commit();
            return $parcel;
        } catch (Throwable $e) { $db->roll_back(); throw $e; }
    }
}
