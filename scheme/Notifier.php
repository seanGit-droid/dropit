<?php
defined('APP_ROOT') OR exit('No direct script access allowed');
/**
 * Drop IT Notifier
 * Channels: in-app (always), email (SMTP via Brevo-compatible auth).
 * SMS was removed — phone still normalized for display only.
 */
class Notifier
{
    public static function normalize_phone_ph(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '') return '';
        if (str_starts_with($digits, '63') && strlen($digits) === 12) return $digits;
        if (str_starts_with($digits, '09') && strlen($digits) === 11) return '63' . substr($digits, 1);
        if (strlen($digits) === 10 && $digits[0] === '9')            return '63' . $digits;
        if (str_starts_with($digits, '0063'))                         return substr($digits, 2);
        return $digits;
    }

    public static function display_phone(string $raw): string
    {
        $n = self::normalize_phone_ph($raw);
        if ($n === '' || strlen($n) < 11) return $raw;
        return '+' . $n;
    }

    public static function in_app(int $user_id, string $title, string $message, ?int $parcel_id = null): void
    {
        db()->table('notifications')->insert([
            'user_id'   => $user_id,
            'parcel_id' => $parcel_id,
            'title'     => $title,
            'message'   => $message,
        ]);
        self::log('in_app', (string)$user_id, $title, $message, 'sent', null);
    }

    public static function email(string $to, string $subject, string $body): string
    {
        if ($to === '') return 'no_recipient';

        $apiKey=trim((string)getenv('BREVO_API_KEY'));
        if ($apiKey !== '') {
            require_once APP_ROOT.'/scheme/BrevoEmail.php';
            $from=trim((string)getenv('BREVO_FROM_EMAIL'));
            $html=str_replace('cid:dropit-logo',url('resources/weblogo.png'),self::render_email_html($subject,$body));
            [$ok,$note]=BrevoEmail::send($apiKey,$from,$to,$subject,$body,$html);
            self::log('email',$to,$subject,$body,$ok?'sent':'failed',$note);
            return $ok?'sent':'failed';
        }


        $host = trim((string) setting('smtp_host', ''));
        $port = (int)   setting('smtp_port', 587);
        $user = trim((string) setting('smtp_user', ''));
        $pass = trim((string) setting('smtp_pass', ''));
        $from = trim((string) setting('smtp_from', 'Drop IT <no-reply@dropit.app>'));

        if ($host === '' || $user === '' || $pass === '') {
            self::log('email', $to, $subject, $body, 'disabled', 'SMTP not configured');
            return 'disabled';
        }

        // Wrap plain-text body with a light HTML template (referencing the inline CID logo).
        $html = self::render_email_html($subject, $body);
        $logo = self::load_inline_logo();

        [$ok, $note] = self::smtp_send($host, $port, $user, $pass, $from, $to, $subject, $body, $html, $logo);
        self::log('email', $to, $subject, $body, $ok ? 'sent' : 'failed', $note);
        return $ok ? 'sent' : 'failed';
    }

    private static function load_inline_logo(): ?array
    {
        $path = APP_ROOT . '/resources/email_logo.png';
        if (!is_file($path)) return null;
        static $cached = null;
        if ($cached === null) {
            $cached = [
                'cid'    => 'dropit-logo',
                'name'   => 'weblogo.png',
                'mime'   => 'image/png',
                'base64' => chunk_split(base64_encode(file_get_contents($path)), 76, "\r\n"),
            ];
        }
        return $cached;
    }

    // Legacy no-op kept so older callers don't crash. SMS feature removed.
    public static function sms(string $phone, string $message): string
    {
        return 'disabled';
    }

    // --- high-level helpers ---

    public static function notify_admin_new_order(array $parcel, array $customer): void
    {
        $subject = "[Drop IT] New delivery order: {$parcel['tracking_number']}";
        $body = "A new delivery has been requested.\n\n"
              . "Tracking: {$parcel['tracking_number']}\n"
              . "Customer: " . ($customer['full_name'] ?: $customer['username']) . "\n"
              . "Phone: " . self::display_phone($customer['phone'] ?? '') . "\n"
              . "Pickup: {$parcel['pickup_address']}\n"
              . "Dropoff: {$parcel['recipient_address']}\n"
              . "Recipient: {$parcel['recipient_name']} ({$parcel['recipient_phone']})\n"
              . "Notes: {$parcel['notes']}\n\n"
              . "Dispatch at: " . url('admin/shipments/assign');

        $admin_email = setting('admin_email', '');
        if ($admin_email) self::email($admin_email, $subject, $body);

        $admins = db()->table('users')->select('id, email')->where('role', 'admin')->where('is_active', 1)->get_all();
        foreach ($admins as $a) {
            self::in_app($a['id'], 'New delivery order', "{$parcel['tracking_number']} from " . ($customer['full_name'] ?: $customer['username']), $parcel['id']);
            if (!empty($a['email']) && $a['email'] !== $admin_email) self::email($a['email'], $subject, $body);
        }
    }

    public static function notify_driver_assigned(array $driver, array $parcel): void
    {
        $accept_token = $parcel['accept_token'] ?? '';
        $accept_link  = url("driver/quick-accept/{$parcel['id']}?t={$accept_token}");
        $decline_link = url("driver/quick-decline/{$parcel['id']}?t={$accept_token}");

        $short = "You have been dispatched parcel {$parcel['tracking_number']}. "
               . "Pickup: " . mb_substr((string)$parcel['pickup_address'], 0, 60) . ". "
               . "Dropoff: " . mb_substr((string)$parcel['recipient_address'], 0, 60) . ".";

        self::in_app($driver['id'], 'New assignment', $short, $parcel['id']);

        if (!empty($driver['email']))  {
            $subject = '[Drop IT] New assignment: ' . $parcel['tracking_number'];
            $body = "Hi " . ($driver['full_name'] ?: $driver['username']) . ",\n\n"
                  . $short . "\n\n"
                  . "Accept in one click: {$accept_link}\n"
                  . "Decline: {$decline_link}\n\n"
                  . "Or open the app: " . url('driver/dashboard');
            self::email($driver['email'], $subject, $body);
        }
    }

    public static function notify_driver_message(array $driver, array $parcel, string $from, string $body): void
    {
        $title = "Message re: {$parcel['tracking_number']}";
        $msg = "{$from}: " . mb_substr($body, 0, 120);
        self::in_app($driver['id'], $title, $msg, $parcel['id']);
    }

    public static function notify_sos(int $driver_id, array $driver, ?array $parcel, ?float $lat, ?float $lng): void
    {
        $subject = "[Drop IT] SOS from driver " . ($driver['full_name'] ?: $driver['username']);
        $loc = ($lat && $lng) ? "https://www.openstreetmap.org/?mlat={$lat}&mlon={$lng}#map=18/{$lat}/{$lng}" : 'location unknown';
        $body = "DRIVER EMERGENCY — SOS triggered.\n\n"
              . "Driver: " . ($driver['full_name'] ?: $driver['username']) . "\n"
              . "Phone: " . self::display_phone($driver['phone'] ?? '') . "\n"
              . ($parcel ? "Parcel: {$parcel['tracking_number']}\n" : "")
              . "Location: {$loc}\n\n"
              . "Open admin: " . url('admin/drivers-map');

        $admin_email = setting('admin_email', '');
        if ($admin_email) self::email($admin_email, $subject, $body);

        $admins = db()->table('users')->select('id, email')->where('role','admin')->where('is_active',1)->get_all();
        foreach ($admins as $a) {
            self::in_app($a['id'], 'Driver SOS', $body, $parcel['id'] ?? null);
            if (!empty($a['email']) && $a['email'] !== $admin_email) self::email($a['email'], $subject, $body);
        }
    }

    public static function notify_customer_status(array $customer, array $parcel, string $status, string $message): void
    {
        self::in_app($customer['id'], "Parcel {$status}", $message, $parcel['id']);
        if (!empty($customer['email'])) {
            $subject = "[Drop IT] {$parcel['tracking_number']} — {$status}";
            $body = "Hi " . ($customer['full_name'] ?: $customer['username']) . ",\n\n"
                  . $message . "\n\n"
                  . "Track: " . url('customer/track?q=' . urlencode($parcel['tracking_number']));
            self::email($customer['email'], $subject, $body);
        }
    }

    public static function notify_admin_new_report(array $reporter, array $report): void
    {
        $subject = "[Drop IT] New report: " . mb_substr($report['subject'], 0, 80);
        $body = "A user submitted a report.\n\n"
              . "From: " . ($reporter['full_name'] ?: $reporter['username']) . " (" . $reporter['role'] . ")\n"
              . "Email: " . ($reporter['email'] ?? '-') . "\n"
              . "Subject: {$report['subject']}\n\n"
              . "Message:\n{$report['message']}\n\n"
              . "Open: " . url('admin/reports');

        $admin_email = setting('admin_email', '');
        if ($admin_email) self::email($admin_email, $subject, $body);

        $admins = db()->table('users')->select('id, email')->where('role','admin')->where('is_active',1)->get_all();
        foreach ($admins as $a) {
            self::in_app($a['id'], '🛟 Customer support report', "{$report['subject']} — " . ($reporter['full_name'] ?: $reporter['username']), null);
            if (!empty($a['email']) && $a['email'] !== $admin_email) self::email($a['email'], $subject, $body);
        }
    }

    public static function notify_email_verification(string $to, string $code): string
    {
        $subject = 'Your Drop IT verification code';
        $body = "Your Drop IT verification code is:\n\n"
              . "    {$code}\n\n"
              . "This code is valid for 10 minutes. If you didn't request this, you can ignore the email.";
        return self::email($to, $subject, $body);
    }

    // ----- SMTP implementation (STARTTLS + AUTH LOGIN, e.g. Brevo) -----

    private static function smtp_send(string $host, int $port, string $user, string $pass, string $from, string $to, string $subject, string $body, ?string $html = null, ?array $inlineImage = null): array
    {
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 25);
        if (!$fp) return [false, "connect: {$errstr}"];
        stream_set_timeout($fp, 25);

        $read = function() use ($fp) {
            $resp = '';
            while (!feof($fp)) {
                $line = fgets($fp, 1024);
                if ($line === false) break;
                $resp .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') break;
            }
            return $resp;
        };
        $write = function($cmd) use ($fp) { fwrite($fp, $cmd . "\r\n"); };
        $expect = function(string $resp, string $code) {
            return substr(trim($resp), 0, 3) === $code;
        };

        try {
            $greet = $read();
            if (!$expect($greet, '220')) return self::smtp_fail($fp, 'greet', $greet);

            $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'dropit.local'));
            $r = $read();
            if (!$expect($r, '250')) return self::smtp_fail($fp, 'ehlo', $r);

            $write('STARTTLS');
            $r = $read();
            if (!$expect($r, '220')) return self::smtp_fail($fp, 'starttls', $r);

            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return self::smtp_fail($fp, 'tls', 'enable_crypto failed');
            }

            $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'dropit.local'));
            $r = $read();
            if (!$expect($r, '250')) return self::smtp_fail($fp, 'ehlo2', $r);

            $write('AUTH LOGIN');
            $r = $read();
            if (!$expect($r, '334')) return self::smtp_fail($fp, 'auth', $r);

            $write(base64_encode($user));
            $r = $read();
            if (!$expect($r, '334')) return self::smtp_fail($fp, 'user', $r);

            $write(base64_encode($pass));
            $r = $read();
            if (!$expect($r, '235')) return self::smtp_fail($fp, 'pass', $r);

            $fromEmail = preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : $from;
            $fromEmail = trim($fromEmail);

            $write("MAIL FROM:<{$fromEmail}>");
            $r = $read();
            if (!$expect($r, '250')) return self::smtp_fail($fp, 'mail_from', $r);

            $write("RCPT TO:<{$to}>");
            $r = $read();
            if (!$expect($r, '250')) return self::smtp_fail($fp, 'rcpt', $r);

            $write('DATA');
            $r = $read();
            if (!$expect($r, '354')) return self::smtp_fail($fp, 'data', $r);

            $rand     = bin2hex(random_bytes(8));
            $altB     = "alt-{$rand}";
            $relB     = "rel-{$rand}";
            $headers  = "From: {$from}\r\n"
                      . "To: {$to}\r\n"
                      . "Subject: " . self::encode_subject($subject) . "\r\n"
                      . "Date: " . date('r') . "\r\n"
                      . "MIME-Version: 1.0\r\n";

            if ($html !== null) {
                $altPart = "--{$altB}\r\n"
                         . "Content-Type: text/plain; charset=utf-8\r\n"
                         . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                         . self::dot_stuff($body) . "\r\n"
                         . "--{$altB}\r\n"
                         . "Content-Type: text/html; charset=utf-8\r\n"
                         . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                         . self::dot_stuff($html) . "\r\n"
                         . "--{$altB}--\r\n";

                if ($inlineImage !== null) {
                    // multipart/related wrapping multipart/alternative + the inline image (CID).
                    $headers .= "Content-Type: multipart/related; type=\"multipart/alternative\"; boundary=\"{$relB}\"\r\n";
                    $data = $headers . "\r\n"
                          . "--{$relB}\r\n"
                          . "Content-Type: multipart/alternative; boundary=\"{$altB}\"\r\n\r\n"
                          . $altPart . "\r\n"
                          . "--{$relB}\r\n"
                          . "Content-Type: {$inlineImage['mime']}; name=\"{$inlineImage['name']}\"\r\n"
                          . "Content-Transfer-Encoding: base64\r\n"
                          . "Content-ID: <{$inlineImage['cid']}>\r\n"
                          . "Content-Disposition: inline; filename=\"{$inlineImage['name']}\"\r\n\r\n"
                          . $inlineImage['base64'] . "\r\n"
                          . "--{$relB}--\r\n";
                } else {
                    $headers .= "Content-Type: multipart/alternative; boundary=\"{$altB}\"\r\n";
                    $data = $headers . "\r\n" . $altPart;
                }
            } else {
                $headers .= "Content-Type: text/plain; charset=utf-8\r\n"
                          . "Content-Transfer-Encoding: 8bit\r\n";
                $data = $headers . "\r\n" . self::dot_stuff($body) . "\r\n";
            }

            fwrite($fp, $data . ".\r\n");
            $r = $read();
            if (!$expect($r, '250')) return self::smtp_fail($fp, 'body', $r);

            $write('QUIT');
            fclose($fp);
            return [true, 'sent'];
        } catch (Throwable $e) {
            if (is_resource($fp)) fclose($fp);
            return [false, $e->getMessage()];
        }
    }

    private static function smtp_fail($fp, string $stage, string $msg): array
    {
        if (is_resource($fp)) { @fwrite($fp, "QUIT\r\n"); fclose($fp); }
        return [false, $stage . ': ' . trim(substr($msg, 0, 200))];
    }

    private static function dot_stuff(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $lines = explode("\n", $text);
        foreach ($lines as &$line) {
            if (isset($line[0]) && $line[0] === '.') $line = '.' . $line;
        }
        return implode("\r\n", $lines);
    }

    private static function encode_subject(string $s): string
    {
        if (preg_match('/[^\x20-\x7E]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return $s;
    }

    private static function render_email_html(string $subject, string $body): string
    {
        $pre = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
        // Auto-link URLs
        $pre = preg_replace('#(https?://[^\s<]+)#i', '<a href="$1" style="color:#1e40af">$1</a>', $pre);
        $esc_subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        return '<!doctype html><html><body style="margin:0;padding:0;background:#f1f5f9;font-family:Inter,Arial,sans-serif;color:#0b1e3f">'
             . '<div style="max-width:560px;margin:24px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 6px 18px rgba(8,20,52,.08)">'
             . '<div style="background:linear-gradient(135deg,#3b82f6,#1e40af);color:#fff;padding:18px 24px;display:flex;align-items:center;gap:12px">'
             .   '<img src="cid:dropit-logo" alt="Drop IT" width="44" height="44" style="display:block;border-radius:10px;background:rgba(255,255,255,0.12);padding:4px">'
             .   '<div style="font-weight:700;font-size:20px;letter-spacing:-0.01em">Drop IT</div>'
             . '</div>'
             . '<div style="padding:22px 24px;font-size:14px;line-height:1.55">'
             . '<div style="font-weight:600;font-size:16px;margin-bottom:10px">' . $esc_subject . '</div>'
             . '<div style="white-space:pre-wrap">' . $pre . '</div>'
             . '</div>'
             . '<div style="padding:14px 24px;background:#f8fafc;color:#64748b;font-size:12px;border-top:1px solid #e2e8f0">Drop IT · Same-day Pinoy parcels</div>'
             . '</div></body></html>';
    }

    private static function log(string $channel, string $to, string $subject, string $body, string $status, ?string $response): void
    {
        try {
            db()->table('notification_log')->insert([
                'channel'   => $channel,
                'recipient' => $to,
                'subject'   => $subject,
                'body'      => $body,
                'status'    => $status,
                'response'  => $response,
            ]);
        } catch (Throwable $e) { /* logging must never break the app */ }
    }
}
