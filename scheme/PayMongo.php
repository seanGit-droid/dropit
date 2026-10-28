<?php
defined('APP_ROOT') OR exit('No direct script access allowed');
/**
 * PayMongo helper � GCash sandbox source + webhook.
 * In sandbox, if no key is set, we return a fake checkout URL that short-circuits to our "paid" return page.
 */
class PayMongo
{
    public static function is_configured(): bool {
        return trim((string) setting('paymongo_secret_key', '')) !== '';
    }

    public static function create_gcash_source(float $amount_php, string $success_url, string $failed_url, string $desc = 'Drop IT delivery'): array
    {
        if (!self::is_configured()) {
            // Sandbox fallback: auto-complete via our own "simulate paid" route.
            return [
                'ok' => true,
                'id' => 'sim_' . bin2hex(random_bytes(6)),
                'checkout_url' => $success_url . (str_contains($success_url,'?') ? '&' : '?') . 'sim=1',
                'simulated' => true,
            ];
        }

        $key = setting('paymongo_secret_key');
        $body = json_encode([
            'data' => [
                'attributes' => [
                    'amount'   => (int) round($amount_php * 100), // centavos
                    'currency' => 'PHP',
                    'type'     => 'gcash',
                    'redirect' => ['success' => $success_url, 'failed' => $failed_url],
                    'billing'  => ['name' => 'Drop IT Customer'],
                    'description' => $desc,
                ]
            ]
        ]);
        $ch = curl_init('https://api.paymongo.com/v1/sources');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($key . ':'),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $d = json_decode($res, true);
        if ($http >= 200 && $http < 300 && !empty($d['data'])) {
            return [
                'ok' => true,
                'id' => $d['data']['id'],
                'checkout_url' => $d['data']['attributes']['redirect']['checkout_url'] ?? $success_url,
                'simulated' => false,
            ];
        }
        return ['ok' => false, 'error' => $d['errors'][0]['detail'] ?? 'PayMongo error', 'raw' => $res];
    }

    public static function fetch_source(string $source_id): ?array {
        if (!self::is_configured()) return null;
        $key = setting('paymongo_secret_key');
        $ch = curl_init('https://api.paymongo.com/v1/sources/' . urlencode($source_id));
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($key . ':')],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $d = json_decode($res, true);
        return $d['data'] ?? null;
    }
}
