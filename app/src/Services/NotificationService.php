<?php
declare(strict_types=1);

namespace Matrimony\Services;

use Matrimony\Database\Connection;

final class NotificationService
{
    public static function notify(int $userId, string $type, string $title, string $message, ?string $relatedType = null, $relatedId = null): void
    {
        try {
            $pdo = Connection::pdo();
            $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_type, related_id) VALUES (:u,:t,:ti,:m,:rt,:ri)');
            $stmt->execute([
                ':u' => $userId, ':t' => substr($type, 0, 60), ':ti' => substr($title, 0, 160),
                ':m' => $message, ':rt' => $relatedType, ':ri' => $relatedId,
            ]);
        } catch (\Throwable $e) {
        }
    }

    /** Notify all admins (in-panel). */
    public static function notifyAdmins(string $type, string $title, string $message, ?string $relatedType = null, $relatedId = null): void
    {
        try {
            $pdo = Connection::pdo();
            $ids = $pdo->query("SELECT id FROM users WHERE role IN ('admin','super_admin') AND account_status = 'active'")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($ids as $id) {
                self::notify((int) $id, $type, $title, $message, $relatedType, $relatedId);
            }
        } catch (\Throwable $e) {
        }
    }

    public static function forUser(int $userId, int $limit = 30, bool $unreadOnly = false): array
    {
        try {
            $pdo = Connection::pdo();
            $sql = 'SELECT * FROM notifications WHERE user_id = :u' . ($unreadOnly ? ' AND read_at IS NULL' : '') . ' ORDER BY created_at DESC LIMIT ' . max(1, min(100, $limit));
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':u' => $userId]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return []; // table missing pre-migration
        }
    }

    public static function markRead(int $userId, ?int $id = null): void
    {
        $pdo = Connection::pdo();
        if ($id) {
            $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE id = :id AND user_id = :u')->execute([':id' => $id, ':u' => $userId]);
        } else {
            $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = :u AND read_at IS NULL')->execute([':u' => $userId]);
        }
    }
}
