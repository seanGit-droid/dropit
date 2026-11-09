<?php defined('APP_ROOT') OR exit('No direct script access allowed');
/** HTTPS transactional email transport; credentials are supplied only through env. */
final class BrevoEmail
{
    public static function send(string $key,string $from,string $to,string $subject,string $text,string $html,?callable $transport=null): array
    {
        if (!filter_var($from,FILTER_VALIDATE_EMAIL) || !filter_var($to,FILTER_VALIDATE_EMAIL)) return [false,'Invalid email sender or recipient'];
        $payload=['sender'=>['name'=>'Drop IT','email'=>$from],'to'=>[['email'=>$to]],'subject'=>$subject,'textContent'=>$text,'htmlContent'=>$html];
        try {
            [$status,$body]=($transport ?? [self::class,'request'])($key,json_encode($payload,JSON_THROW_ON_ERROR));
            $response=json_decode($body,true);
            if ($status===201 && is_array($response) && !empty($response['messageId'])) return [true,'Brevo accepted message '.$response['messageId']];
            return [false,'Brevo email request failed (HTTP '.(int)$status.').'];
        } catch (Throwable $e) { return [false,'Brevo email connection failed.']; }
    }
    private static function request(string $key,string $payload): array
    {
        $context=stream_context_create(['http'=>['method'=>'POST','header'=>['Accept: application/json','Content-Type: application/json','api-key: '.$key],'content'=>$payload,'timeout'=>15,'ignore_errors'=>true,'follow_location'=>0], 'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
        $body=@file_get_contents('https://api.brevo.com/v3/smtp/email',false,$context);
        $headers=$http_response_header ?? [];
        preg_match('/^HTTP\/\S+\s+(\d{3})/',$headers[0] ?? '',$match);
        return [(int)($match[1] ?? 0),$body===false?'':$body];
    }
}
