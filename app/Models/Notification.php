<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Database;

final class Notification extends Model
{
    protected static string $table = 'notifications';

    public static function forUser(int $userId, ?string $type = null, int $limit = 100): array
    {
        if ($type) {
            return Database::fetchAll(
                'SELECT * FROM notifications WHERE user_id = ? AND type = ? ORDER BY created_at DESC LIMIT ' . (int)$limit,
                [$userId, $type]
            );
        }
        return Database::fetchAll(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . (int)$limit,
            [$userId]
        );
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
