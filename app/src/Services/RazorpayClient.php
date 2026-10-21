<?php
declare(strict_types=1);

namespace Matrimony\Services;

/**
 * Minimal Razorpay REST client (cURL, no Composer SDK required).
 *
 * Security notes:
 *  - The key secret is only ever used for HTTP Basic auth; it is never logged.
 *  - Gateway response bodies are never placed in exception messages because they
 *    can contain customer PII (email, contact, notes, card metadata).
 *  - TLS peer verification is explicit and connect/read timeouts are enforced.
 */
final class RazorpayClient
{
    private const BASE_URL = 'https://api.razorpay.com/v1';
    private const CONNECT_TIMEOUT = 10;
    private const REQUEST_TIMEOUT = 25;

    private string $keyId;
    private string $keySecret;

    public function __construct(?string $keyId = null, ?string $keySecret = null)
    {
        $cfg = require BASE_PATH . '/app/config/payments.php';
        $this->keyId = $keyId ?? (string) ($cfg['razorpay']['key_id'] ?? '');
        $this->keySecret = $keySecret ?? (string) ($cfg['razorpay']['key_secret'] ?? '');
    }

    public function configured(): bool
    {
        return $this->keyId !== '' && $this->keySecret !== '';
    }

    /** 'test' when the key id is a test key, 'live' for live keys, '' when unset. */
    public function keyMode(): string
    {
        if (str_starts_with($this->keyId, 'rzp_test_')) {
            return 'test';
        }
        if (str_starts_with($this->keyId, 'rzp_live_')) {
            return 'live';
        }
        return '';
    }

    /** Create a Razorpay order. Amount in paise. Returns ['id'=>..., 'raw'=>...]. */
    public function createOrder(int $amountPaise, string $currency, string $receipt, array $notes = []): array
    {
        if (!$this->configured()) {
            throw new \RuntimeException('Razorpay keys are not configured');
        }
        if ($amountPaise <= 0) {
            throw new \RuntimeException('Invalid order amount');
        }
        $currency = strtoupper(trim($currency));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \RuntimeException('Invalid order currency');
        }
        $data = $this->request('POST', '/orders', [
            'amount' => $amountPaise,
            'currency' => $currency,
            'receipt' => substr($receipt, 0, 40),
            'notes' => $notes,
        ]);
        if (empty($data['id'])) {
            throw new \RuntimeException('Razorpay order response did not contain an id');
        }
        return ['id' => (string) $data['id'], 'raw' => $data];
    }

    /** Fetch an order (for reconciliation). */
    public function fetchOrder(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return [];
        }
        return $this->request('GET', '/orders/' . rawurlencode($orderId));
    }

    /** Fetch a payment (for reconciliation / capture checks). */
    public function fetchPayment(string $paymentId): array
    {
        $paymentId = trim($paymentId);
        if ($paymentId === '') {
            return [];
        }
        return $this->request('GET', '/payments/' . rawurlencode($paymentId));
    }

    /** Fetch all payments recorded against an order. */
    public function fetchOrderPayments(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return [];
        }
        $data = $this->request('GET', '/orders/' . rawurlencode($orderId) . '/payments');
        return isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    }

    /** Fetch a refund. */
    public function fetchRefund(string $refundId): array
    {
        $refundId = trim($refundId);
        if ($refundId === '') {
            return [];
        }
        return $this->request('GET', '/refunds/' . rawurlencode($refundId));
    }

    /**
     * Create a refund for a captured payment. Amount in paise.
     * Passing null refunds the full remaining amount.
     */
    public function createRefund(string $paymentId, ?int $amountPaise = null, array $notes = []): array
    {
        $paymentId = trim($paymentId);
        if ($paymentId === '') {
            throw new \RuntimeException('Payment id is required for a refund');
        }
        if ($amountPaise !== null && $amountPaise <= 0) {
            throw new \RuntimeException('Invalid refund amount');
        }
        $payload = ['notes' => $notes];
        if ($amountPaise !== null) {
            $payload['amount'] = $amountPaise;
        }
        $data = $this->request('POST', '/payments/' . rawurlencode($paymentId) . '/refund', $payload);
        if (empty($data['id'])) {
            throw new \RuntimeException('Razorpay refund response did not contain an id');
        }
        return $data;
    }

    /** Fetch a dispute. */
    public function fetchDispute(string $disputeId): array
    {
        $disputeId = trim($disputeId);
        if ($disputeId === '') {
            return [];
        }
        return $this->request('GET', '/disputes/' . rawurlencode($disputeId));
    }

    /**
     * Verify Checkout payment signature (official algorithm):
     * HMAC-SHA256(order_id + '|' + payment_id) with key_secret.
     */
    public static function verifyPaymentSignature(string $orderId, string $paymentId, string $signature, string $secret): bool
    {
        // Empty secret must never verify — HMAC('', …) is deterministic and forgeable.
        if ($orderId === '' || $paymentId === '' || $signature === '' || $secret === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
        return hash_equals($expected, $signature);
    }

    /** Verify webhook signature against the RAW body + webhook secret. */
    public static function verifyWebhookSignature(string $rawBody, string $signature, string $secret): bool
    {
        if ($rawBody === '' || $signature === '' || $secret === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Execute an API request and decode the JSON response.
     * Throws a safe RuntimeException on transport/API failures.
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (!$this->configured()) {
            throw new \RuntimeException('Razorpay keys are not configured');
        }
        $url = self::BASE_URL . $path;
        $body = $payload !== null
            ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Could not initialise the payment gateway request');
        }
        $headers = ['Accept: application/json'];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($body);
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERPWD => $this->keyId . ':' . $this->keySecret,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'MatrimonyRazorpayClient/1.0',
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = (int) curl_errno($ch);
        $error = (string) curl_error($ch);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            @error_log('RazorpayClient: transport failure for ' . $method . ' ' . $path . ' (curl ' . $errno . ')');
            throw new \RuntimeException('Payment gateway is unreachable');
        }

        $decoded = json_decode((string) $raw, true);

        if ($code < 200 || $code >= 300) {
            // Only the provider error code/description key is safe to log; never the body.
            $error = is_array($decoded) && isset($decoded['error']) && is_array($decoded['error']) ? $decoded['error'] : [];
            $providerCode = (string) ($error['code'] ?? '');
            $providerReason = (string) ($error['reason'] ?? '');
            @error_log('RazorpayClient: HTTP ' . $code . ' for ' . $method . ' ' . $path
                . ($providerCode !== '' ? ' [' . $providerCode . ']' : '')
                . ($providerReason !== '' ? ' (' . $providerReason . ')' : ''));
            throw new \RuntimeException('Payment gateway returned HTTP ' . $code . ($providerCode !== '' ? ' (' . $providerCode . ')' : ''));
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('Payment gateway returned an invalid response');
        }

        return $decoded;
    }
}
