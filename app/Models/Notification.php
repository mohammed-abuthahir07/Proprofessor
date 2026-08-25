<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Database;

final class Notification extends Model
{
    protected static string $table = 'notifications';

    public static function forUser(int $userId, ?string $type = null, int $limit = 100, ?string $priority = null): array
    {
        if (class_exists('\\NotificationService', false)) {
            \NotificationService::ensureSchema();
        }
        $sql = 'SELECT * FROM notifications WHERE user_id = ?';
        $params = [$userId];
        if ($type) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }
        if ($priority && in_array($priority, ['high', 'medium', 'low'], true)) {
            $sql .= ' AND priority = ?';
            $params[] = $priority;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . (int)$limit;
        return Database::fetchAll($sql, $params);
    }

    public static function markAllRead(int $userId): void
    {
        Database::query('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);
    }

    public static function markRead(int $id, int $userId): void
    {
        Database::query('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$id, $userId]);
    }
}
