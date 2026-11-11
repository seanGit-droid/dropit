<?php
defined('APP_ROOT') OR exit('No direct script access allowed');

final class DeliveryCapture
{
    public static function validate(array $input, array $photo): array
    {
        $number = static function (string $key) use ($input): float {
            $v = $input[$key] ?? null;
            if (!is_scalar($v) || !is_numeric($v) || !is_finite((float)$v)) {
                throw new InvalidArgumentException('A fresh camera photo and device location are required.');
            }
            return (float)$v;
        };
        $lat = $number('latitude'); $lng = $number('longitude');
        $accuracy = $number('accuracy'); $captured = $number('captured_at'); $located = $number('location_at');
        $now = microtime(true) * 1000;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || $accuracy < 0 || $accuracy > 99999999) {
            throw new InvalidArgumentException('Invalid device location. Please retake the photo.');
        }
        if ($captured < $now - 1800000 || $captured > $now + 60000 || abs($captured - $located) > 10000) {
            throw new InvalidArgumentException('The photo or location is stale. Please retake the photo with location enabled.');
        }
        $receiver = trim(is_string($input['receiver_name'] ?? null) ? $input['receiver_name'] : '');
        $signature = $input['signature'] ?? '';
        if ($receiver === '' || strlen($receiver) > 150 || !is_string($signature) || strlen($signature) > 1000000 || !str_starts_with($signature, 'data:image/png;base64,')) {
            throw new InvalidArgumentException('Receiver name and signature are required.');
        }
        $bytes = base64_decode(substr($signature, 22), true);
        $sig = $bytes !== false ? @getimagesizefromstring($bytes) : false;
        if (!$sig || $sig['mime'] !== 'image/png') throw new InvalidArgumentException('Invalid receiver signature. Please sign again.');
        if (($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($photo['tmp_name']) || !is_uploaded_file($photo['tmp_name']) || filesize($photo['tmp_name']) > 2*1024*1024) {
            throw new InvalidArgumentException('A camera photo up to 2 MB is required.');
        }
        $info = @getimagesize($photo['tmp_name']);
        $extension = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'][$info['mime'] ?? ''] ?? null;
        if (!$extension || $info[0]*$info[1] > 40000000) throw new InvalidArgumentException('The photo must be a valid JPEG, PNG, or WebP image.');
        $utc = static fn(float $ms): string => gmdate('Y-m-d H:i:s', (int)floor($ms/1000)).'.'.sprintf('%03d', (int)$ms%1000);
        return compact('lat','lng','accuracy','receiver','signature','extension') + ['captured_at'=>$utc($captured), 'location_at'=>$utc($located)];
    }

    // Raw prepared writes propagate failures so the delivery transaction can roll back.
    public static function update(Database $db, string $table, array $where, array $fields): void
    {
        $set=implode(',',array_map(static fn($key)=>$key.'=?',array_keys($fields)));
        $filter=implode(' AND ',array_map(static fn($key)=>$key.'=?',array_keys($where)));
        $db->raw('UPDATE '.$table.' SET '.$set.' WHERE '.$filter,[...array_values($fields),...array_values($where)]);
    }
    public static function insert(Database $db, string $table, array $fields): void
    {
        $db->raw('INSERT INTO '.$table.' ('.implode(',',array_keys($fields)).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));
    }
}
