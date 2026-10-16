<?php
declare(strict_types=1);

namespace Matrimony\Security;

use Matrimony\Database\Connection;

/**
 * Append-only audit log. Application roles can only INSERT.
 * Never log passwords, tokens, session IDs, or gateway secrets.
 */
final class AuditService
{
    private const SENSITIVE_KEYS = ['password', 'password_hash', 'token', 'secret', 'session', 'cvv', 'card', 'upi', 'webhook_secret', 'key_secret', 'email', 'contact', 'phone', 'mobile', 'address', 'vpa', 'account_number', 'ifsc'];

    public static function log(?int $actorId, string $action, ?string $entityType = null, $entityId = null, array $meta = [], string $outcome = 'success'): void
    {
        try {
            $pdo = Connection::pdo();
            $stmt = $pdo->prepare('INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, meta_json, outcome, ip_address, user_agent) VALUES (:a, :ac, :et, :eid, :m, :o, :ip, :ua)');
            $stmt->execute([
                ':a' => $actorId,
                ':ac' => substr($action, 0, 120),
                ':et' => $entityType ? substr($entityType, 0, 80) : null,
                ':eid' => $entityId !== null ? substr((string) $entityId, 0, 80) : null,
                ':m' => json_encode(self::redact($meta), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':o' => $outcome,
                ':ip' => substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 64),
                ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'cli', 0, 255),
            ]);
        } catch (\Throwable $e) {
            @error_log('AuditService failed: ' . $e->getMessage());
        }
    }

    public static function redact(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $lk = strtolower((string) $k);
            $sensitive = false;
            foreach (self::SENSITIVE_KEYS as $s) {
                if (strpos($lk, $s) !== false) {
                    $sensitive = true;
                    break;
                }
            }
            if ($sensitive) {
                $out[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $out[$k] = self::redact($v);
            } elseif (is_string($v) && strlen($v) > 2000) {
                $out[$k] = substr($v, 0, 2000) . '…[truncated]';
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
