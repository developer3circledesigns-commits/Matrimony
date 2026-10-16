<?php
declare(strict_types=1);

namespace Matrimony\Services;

use Matrimony\Database\Connection;
use Matrimony\Database\Schema;

/** Database-driven plan catalogue. Never hard-code prices/limits in PHP. */
final class PlanService
{
    /**
     * Transient pre-migration fallback values (used ONLY when migration 008
     * has not been applied yet). The migration seeds these same values into
     * the DB, which is then the single source of truth.
     */
    private const FALLBACK_CATALOGUE = [
        'SILVER' => ['price_paise' => 100000, 'duration_days' => 45, 'contact_limit' => 25, 'is_unlimited' => 0],
        'GOLD' => ['price_paise' => 300000, 'duration_days' => 90, 'contact_limit' => 45, 'is_unlimited' => 0],
        'PLATINUM' => ['price_paise' => 1000000, 'duration_days' => 365, 'contact_limit' => null, 'is_unlimited' => 1],
    ];

    public static function activePlans(): array
    {
        $pdo = Connection::pdo();
        try {
            if (Schema::has('membership_plans', 'slug') && Schema::has('membership_plans', 'price_paise')) {
                $stmt = $pdo->query("SELECT id, code, slug, name, description, duration_days, price_paise, price_cents, currency, contact_limit, is_unlimited, features, display_order FROM membership_plans WHERE is_active = 1 AND code IN ('SILVER','GOLD','PLATINUM') ORDER BY display_order ASC");
                $rows = $stmt->fetchAll();
                foreach ($rows as &$r) {
                    $r['price_paise'] = (int) ($r['price_paise'] ?? $r['price_cents']);
                    $r['price_inr'] = $r['price_paise'] / 100;
                }
                return $rows;
            }
            // Legacy schema fallback (migration pending): map known codes to catalogue.
            @error_log('PlanService: migration 008 not applied; using transient catalogue fallback.');
            $stmt = $pdo->query("SELECT id, code, name, duration_days, price_cents, features FROM membership_plans WHERE is_active = 1 AND code IN ('SILVER','GOLD','PLATINUM')");
            $rows = $stmt->fetchAll();
            $out = [];
            foreach ($rows as $r) {
                $fb = self::FALLBACK_CATALOGUE[$r['code']] ?? null;
                if (!$fb) {
                    continue;
                }
                $out[] = [
                    'id' => $r['id'],
                    'code' => $r['code'],
                    'slug' => strtolower($r['code']),
                    'name' => $r['name'],
                    'description' => $r['features'],
                    'duration_days' => (int) $fb['duration_days'],
                    'price_paise' => (int) $fb['price_paise'],
                    'price_cents' => (int) $r['price_cents'],
                    'price_inr' => $fb['price_paise'] / 100,
                    'currency' => 'INR',
                    'contact_limit' => $fb['contact_limit'],
                    'is_unlimited' => $fb['is_unlimited'],
                    'features' => $r['features'],
                    'display_order' => 0,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            @error_log('PlanService::activePlans failed: ' . $e->getMessage());
            return [];
        }
    }

    public static function findActive(int|string $idOrSlug): ?array
    {
        try {
            $pdo = Connection::pdo();
            if (Schema::has('membership_plans', 'slug') && Schema::has('membership_plans', 'price_paise')) {
                if (is_numeric($idOrSlug)) {
                    $stmt = $pdo->prepare("SELECT * FROM membership_plans WHERE id = :id AND is_active = 1 AND code IN ('SILVER','GOLD','PLATINUM') LIMIT 1");
                    $stmt->execute([':id' => (int) $idOrSlug]);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM membership_plans WHERE (slug = :s1 OR code = :s2) AND is_active = 1 AND code IN ('SILVER','GOLD','PLATINUM') LIMIT 1");
                    $stmt->execute([':s1' => $idOrSlug, ':s2' => $idOrSlug]);
                }
                $r = $stmt->fetch();
                if (!$r) {
                    return null;
                }
                $r['price_paise'] = (int) ($r['price_paise'] ?? $r['price_cents']);
                return $r;
            }
            // Legacy fallback
            if (is_numeric($idOrSlug)) {
                $stmt = $pdo->prepare('SELECT * FROM membership_plans WHERE id = :id AND is_active = 1 LIMIT 1');
                $stmt->execute([':id' => (int) $idOrSlug]);
            } else {
                $code = strtoupper(str_replace('-', '_', (string) $idOrSlug));
                $stmt = $pdo->prepare('SELECT * FROM membership_plans WHERE (code = :s OR LOWER(code) = :s2) AND is_active = 1 LIMIT 1');
                $stmt->execute([':s' => $code, ':s2' => strtolower((string) $idOrSlug)]);
            }
            $r = $stmt->fetch();
            if (!$r || !isset(self::FALLBACK_CATALOGUE[$r['code']])) {
                return null;
            }
            $fb = self::FALLBACK_CATALOGUE[$r['code']];
            $r['slug'] = strtolower($r['code']);
            $r['price_paise'] = $fb['price_paise'];
            $r['currency'] = 'INR';
            $r['duration_days'] = $fb['duration_days'];
            $r['contact_limit'] = $fb['contact_limit'];
            $r['is_unlimited'] = $fb['is_unlimited'];
            return $r;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function findAny(int $id): ?array
    {
        try {
            $pdo = Connection::pdo();
            $stmt = $pdo->prepare('SELECT * FROM membership_plans WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $r = $stmt->fetch();
            return $r ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Immutable snapshot taken at purchase time. */
    public static function snapshot(array $plan): array
    {
        return [
            'plan_name_snapshot' => $plan['name'],
            'amount_paise' => (int) ($plan['price_paise'] ?? $plan['price_cents']),
            'currency' => $plan['currency'] ?? 'INR',
            'duration_days_snapshot' => (int) $plan['duration_days'],
            'contact_limit_snapshot' => $plan['contact_limit'] !== null ? (int) $plan['contact_limit'] : null,
            'is_unlimited_snapshot' => (int) ($plan['is_unlimited'] ?? 0),
        ];
    }

    public static function formatINR(int $paise): string
    {
        return '₹' . number_format($paise / 100, 0, '.', ',');
    }
}
