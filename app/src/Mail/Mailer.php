<?php
declare(strict_types=1);

namespace Matrimony\Mail;

/**
 * Mail driver: 'log' (dev only) or 'smtp' (production) using a dependency-free
 * SMTP client (shared-hosting safe, no Composer requirement).
 *
 * Hardening: header-injection guards, encoded subject/display name, CRLF-safe
 * bodies with dot-stuffing, connect/read timeouts, certificate verification,
 * and no plaintext credential exchange over an unencrypted connection.
 */
final class Mailer
{
    private const CONNECT_TIMEOUT = 15;
    private const READ_TIMEOUT = 20;

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
                'to' => $to, 'subject' => $subject, 'at' => gmdate('c'),
                'text' => $text ?? strip_tags($html),
            ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
            return true;
        }
        if ($driver !== 'smtp') {
            return 'Unsupported mail driver (use smtp)';
        }
        return self::sendSmtp($cfg, $to, $subject, $html, $text);
    }

    private static function sendSmtp(array $cfg, string $to, string $subject, string $html, ?string $text)
    {
        $host = trim((string) ($cfg['host'] ?? 'localhost'));
        $port = (int) ($cfg['port'] ?? 587);
        $user = (string) ($cfg['username'] ?? '');
        $pass = (string) ($cfg['password'] ?? '');
        $enc = strtolower((string) ($cfg['encryption'] ?? 'tls'));
        $from = trim((string) ($cfg['from_address'] ?? 'no-reply@localhost'));
        $fromName = (string) ($cfg['from_name'] ?? 'Matrimony');

        // Reject header injection and malformed addresses before touching SMTP.
        foreach ([$to, $from, $subject] as $field) {
            if (preg_match('/[\r\n\0]/', $field)) {
                return 'Invalid mail header value';
            }
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid from/to address';
        }
        if (!in_array($enc, ['tls', 'ssl', 'none'], true)) {
            return 'Unsupported SMTP encryption mode';
        }
        if ($host === '' || $port < 1 || $port > 65535) {
            return 'Invalid SMTP host/port';
        }
        $isLocal = in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
        if ($user !== '' && $enc === 'none' && !$isLocal) {
            return 'Refusing to send SMTP credentials over an unencrypted connection';
        }

        $fromName = trim(preg_replace('/[\r\n\0]+/', ' ', $fromName) ?? '');
        $encodedName = $fromName !== '' && function_exists('mb_encode_mimeheader')
            ? mb_encode_mimeheader($fromName, 'UTF-8', 'B', "\r\n")
            : $fromName;
        $encodedSubject = function_exists('mb_encode_mimeheader')
            ? mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n")
            : $subject;

        $boundary = 'b_' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . ($encodedName !== '' ? '"' . str_replace('"', '', $encodedName) . '" ' : '') . '<' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (parse_url((string) (getenv('APP_URL') ?: 'localhost'), PHP_URL_HOST) ?: 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: Matrimony',
        ];
        $plain = $text ?? trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . $plain . "\r\n"
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n--$boundary--";
        $raw = implode("\r\n", $headers) . "\r\n\r\n" . $body;

        try {
            $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
            $ctx = stream_context_create(['ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ]]);
            $fp = @stream_socket_client($remote, $errno, $errstr, self::CONNECT_TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
            if (!$fp) {
                @error_log('Mailer: SMTP connect failed to ' . $host . ':' . $port . ' (' . $errno . ')');
                return 'SMTP connect failed';
            }
            stream_set_timeout($fp, self::READ_TIMEOUT);

            $read = function () use ($fp): array {
                $out = '';
                while (($line = fgets($fp, 2048)) !== false) {
                    $out .= $line;
                    if (preg_match('/^\d{3} /', $line)) {
                        break;
                    }
                }
                $meta = stream_get_meta_data($fp);
                if (!empty($meta['timed_out'])) {
                    return [0, 'timeout'];
                }
                $code = preg_match('/^(\d{3})/m', $out, $m) ? (int) $m[1] : 0;
                return [$code, $out];
            };
            $cmd = function (string $c) use ($fp, $read): array {
                if (@fwrite($fp, $c . "\r\n") === false) {
                    return [0, 'write failed'];
                }
                return $read();
            };
            $fail = static function ($fp, string $message): string {
                @fclose($fp);
                return $message;
            };

            [$code, $greet] = $read();
            if ($code !== 220) {
                return $fail($fp, 'SMTP greeting failed');
            }
            $hostName = gethostname() ?: 'localhost';
            [$code] = $cmd('EHLO ' . $hostName);
            if ($code !== 250) {
                return $fail($fp, 'EHLO failed');
            }
            if ($enc === 'tls') {
                [$code] = $cmd('STARTTLS');
                if ($code !== 220) {
                    return $fail($fp, 'STARTTLS failed');
                }
                $methods = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $methods |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $methods |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                if (!@stream_socket_enable_crypto($fp, true, $methods)) {
                    return $fail($fp, 'TLS negotiation failed');
                }
                [$code] = $cmd('EHLO ' . $hostName);
                if ($code !== 250) {
                    return $fail($fp, 'EHLO (TLS) failed');
                }
            }
            if ($user !== '') {
                if ($pass === '') {
                    return $fail($fp, 'SMTP password is not configured');
                }
                [$code] = $cmd('AUTH LOGIN');
                if ($code !== 334) {
                    return $fail($fp, 'AUTH not accepted');
                }
                [$code] = $cmd(base64_encode($user));
                if ($code !== 334) {
                    return $fail($fp, 'SMTP username rejected');
                }
                [$code] = $cmd(base64_encode($pass));
                if ($code !== 235) {
                    return $fail($fp, 'SMTP authentication failed');
                }
            }
            [$code] = $cmd('MAIL FROM:<' . $from . '>');
            if ($code !== 250) {
                return $fail($fp, 'MAIL FROM rejected');
            }
            [$code] = $cmd('RCPT TO:<' . $to . '>');
            if ($code !== 250 && $code !== 251) {
                return $fail($fp, 'RCPT TO rejected');
            }
            [$code] = $cmd('DATA');
            if ($code !== 354) {
                return $fail($fp, 'DATA not accepted');
            }

            // Normalise line endings, dot-stuff leading periods (SMTP transparency).
            $data = str_replace(["\r\n", "\r"], "\n", $raw);
            $data = str_replace("\n", "\r\n", $data);
            $data = preg_replace('/^\./m', '..', $data) ?? $data;
            if (@fwrite($fp, $data . "\r\n.\r\n") === false) {
                return $fail($fp, 'SMTP body write failed');
            }
            [$code] = $read();
            $cmd('QUIT');
            @fclose($fp);
            if ($code !== 250) {
                @error_log('Mailer: SMTP rejected message with code ' . $code);
                return 'SMTP send rejected (code ' . $code . ')';
            }
            return true;
        } catch (\Throwable $e) {
            @error_log('Mailer: SMTP exception ' . get_class($e));
            return 'SMTP error';
        }
    }
}
