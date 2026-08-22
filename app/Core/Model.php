<?php
declare(strict_types=1);

namespace App\Core;

use Database;

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';

    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT * FROM `' . static::$table . '` WHERE `' . static::$primaryKey . '` = ? LIMIT 1',
            [$id]
        );
    }

    public static function all(string $orderBy = 'id DESC', int $limit = 500): array
    {
        return Database::fetchAll(
            'SELECT * FROM `' . static::$table . '` ORDER BY ' . $orderBy . ' LIMIT ' . (int)$limit
        );
    }

    public static function where(string $sql, array $params = []): array
    {
        return Database::fetchAll(
            'SELECT * FROM `' . static::$table . '` WHERE ' . $sql,
            $params
        );
    }

    public static function firstWhere(string $sql, array $params = []): ?array
    {
        return Database::fetch(
            'SELECT * FROM `' . static::$table . '` WHERE ' . $sql . ' LIMIT 1',
            $params
        );
    }

    public static function create(array $data): int
    {
        return Database::insert(static::$table, $data);
    }

    public static function updateById(int $id, array $data): int
    {
        return Database::update(
            static::$table,
            $data,
            '`' . static::$primaryKey . '` = :__id',
            ['__id' => $id]
        );
    }

    public static function deleteById(int $id): void
    {
        Database::query(
            'DELETE FROM `' . static::$table . '` WHERE `' . static::$primaryKey . '` = ?',
            [$id]
        );
    }

    public static function count(string $where = '1=1', array $params = []): int
    {
        $row = Database::fetch(
            'SELECT COUNT(*) AS c FROM `' . static::$table . '` WHERE ' . $where,
            $params
        );
        return (int)($row['c'] ?? 0);
    }
}
