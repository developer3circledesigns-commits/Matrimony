<?php
declare(strict_types=1);

namespace Matrimony\Mail;

/**
 * Mail driver: 'log' (dev) or 'smtp' (production) using a dependency-free
 * SMTP client (shared-hosting safe, no Composer requirement).
 */
final class Mailer
{
    /** @return true|string true on success, error string on failure */
    public static function send(string $to, string $subject, string $html, ?string $text = null)
    {
        $cfg = require BASE_PATH . '/app/config/mail.php';
        $driver = strtolower((string) ($cfg['driver'] ?? 'log'));
        $env = (string) (getenv('APP_ENV') ?: 'production');
        if ($driver === 'log') {
            if ($env === 'production') {
                @error_log('Mailer: MAIL_DRIVER=log is not allowed in production. Configure SMTP.');
                return 'Mail is not configured for production (set MAIL_DRIVER=smtp)';
            }
            $dir = BASE_PATH . '/app/storage/emails';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($dir . '/' . date('Y-m-d') . '.log', json_encode([
                'to' => $to, 'subject' => $subject, 'at' => date('c'),
                'text' => $text ?? strip_tags($html),
            ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
            return true;
        }
        return self::sendSmtp($cfg, $to, $subject, $html, $text);
    }

    private static function sendSmtp(array $cfg, string $to, string $subject, string $html, ?string $text)
    {
        $host = (string) ($cfg['host'] ?? 'localhost');
        $port = (int) ($cfg['port'] ?? 587);
        $user = (string) ($cfg['username'] ?? '');
        $pass = (string) ($cfg['password'] ?? '');
        $enc = strtolower((string) ($cfg['encryption'] ?? 'tls'));
        $from = (string) ($cfg['from_address'] ?? 'no-reply@localhost');
        $fromName = (string) ($cfg['from_name'] ?? 'Matrimony');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid from/to address';
        }
        $boundary = 'b_' . bin2hex(random_bytes(12));
        $headers = [
            'From: "' . addslashes($fromName) . '" <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . ($text ?? strip_tags($html)) . "\r\n"
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $html . "\r\n--$boundary--\r\n";
        $raw = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        try {
            $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
            $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
            $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
            if (!$fp) {
                return "SMTP connect failed: $errstr ($errno)";
            }
            $read = function () use ($fp) {
                $out = '';
                while ($line = fgets($fp, 1024)) {
                    $out .= $line;
                    if (preg_match('/^\d{3} /', $line)) {
                        break;
                    }
                }
                return $out;
            };
            $cmd = function (string $c) use ($fp, $read) {
                fwrite($fp, $c . "\r\n");
                return $read();
            };
            $greet = $read();
            if (strpos($greet, '220') === false) {
                fclose($fp);
                return 'SMTP greeting failed';
            }
            $host_name = gethostname() ?: 'localhost';
            $r = $cmd('EHLO ' . $host_name);
            if (strpos($r, '250') === false) {
                fclose($fp);
                return 'EHLO failed';
            }
            if ($enc === 'tls') {
                $r = $cmd('STARTTLS');
                if (strpos($r, '220') === false) {
                    fclose($fp);
                    return 'STARTTLS failed';
                }
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($fp);
                    return 'TLS negotiation failed';
                }
                $r = $cmd('EHLO ' . $host_name);
                if (strpos($r, '250') === false) {
                    fclose($fp);
                    return 'EHLO (TLS) failed';
                }
            }
            if ($user !== '') {
                $r = $cmd('AUTH LOGIN');
                if (strpos($r, '334') === false) {
                    fclose($fp);
                    return 'AUTH not accepted';
                }
                $r = $cmd(base64_encode($user));
                if (strpos($r, '334') === false) {
                    fclose($fp);
                    return 'SMTP username rejected';
                }
                $r = $cmd(base64_encode($pass));
                if (strpos($r, '235') === false) {
                    fclose($fp);
                    return 'SMTP authentication failed';
                }
            }
            $r = $cmd('MAIL FROM:<' . $from . '>');
            if (strpos($r, '250') === false) {
                fclose($fp);
                return 'MAIL FROM rejected';
            }
            $r = $cmd('RCPT TO:<' . $to . '>');
            if (strpos($r, '250') !== false && strpos($r, '251') !== false) {
                // accepted variants
            }
            if (strpos($r, '250') === false && strpos($r, '251') === false) {
                fclose($fp);
                return 'RCPT TO rejected';
            }
            $r = $cmd('DATA');
            if (strpos($r, '354') === false) {
                fclose($fp);
                return 'DATA not accepted';
            }
            fwrite($fp, $raw . "\r\n.\r\n");
            $r = $read();
            $cmd('QUIT');
            fclose($fp);
            return (strpos($r, '250') !== false) ? true : ('SMTP send rejected: ' . substr($r, 0, 200));
        } catch (\Throwable $e) {
            return 'SMTP error: ' . substr($e->getMessage(), 0, 200);
        }
    }
}
