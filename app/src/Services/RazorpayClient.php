<?php
declare(strict_types=1);

namespace Matrimony\Services;

/**
 * Minimal Razorpay REST client (cURL, no Composer SDK required).
 * API-compatible with Razorpay Orders API. Verify against official docs
 * at implementation time; event names/fields can change.
 */
final class RazorpayClient
{
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

    /** Create a Razorpay order. Amount in paise. Returns ['id'=>..., 'raw'=>...]. */
    public function createOrder(int $amountPaise, string $currency, string $receipt, array $notes = []): array
    {
        if (!$this->configured()) {
            throw new \RuntimeException('Razorpay keys are not configured');
        }
        if ($amountPaise <= 0) {
            throw new \RuntimeException('Invalid order amount');
        }
        $payload = json_encode([
            'amount' => $amountPaise,
            'currency' => $currency,
            'receipt' => substr($receipt, 0, 40),
            'notes' => $notes,
        ]);
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)],
            CURLOPT_USERPWD => $this->keyId . ':' . $this->keySecret,
            CURLOPT_TIMEOUT => 20,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new \RuntimeException('Razorpay order request failed: ' . $err);
        }
        $data = json_decode($resp, true);
        if ($code < 200 || $code >= 300 || empty($data['id'])) {
            throw new \RuntimeException('Razorpay order failed: ' . substr($resp, 0, 300));
        }
        return ['id' => $data['id'], 'raw' => $data];
    }

    /** Fetch an order (for reconciliation). */
    public function fetchOrder(string $orderId): array
    {
        $ch = curl_init('https://api.razorpay.com/v1/orders/' . urlencode($orderId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->keyId . ':' . $this->keySecret,
            CURLOPT_TIMEOUT => 20,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string) $resp, true);
        return is_array($data) ? $data : [];
    }

    /** Fetch a payment (for reconciliation). */
    public function fetchPayment(string $paymentId): array
    {
        $ch = curl_init('https://api.razorpay.com/v1/payments/' . urlencode($paymentId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->keyId . ':' . $this->keySecret,
            CURLOPT_TIMEOUT => 20,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string) $resp, true);
        return is_array($data) ? $data : [];
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
}
