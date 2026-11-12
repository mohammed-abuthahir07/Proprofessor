<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $db = self::dbConfig();
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['name'],
            $db['charset']
        );
        self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return self::$pdo;
    }

    /** @return array<string,mixed> */
    private static function dbConfig(): array
    {
        if (function_exists('config')) {
            $db = config('db');
            if (is_array($db) && $db !== []) {
                return $db;
            }
        }
        $cfg = require __DIR__ . '/../config/config.php';
        $local = __DIR__ . '/../config/config.local.php';
        if (is_file($local)) {
            $override = require $local;
            if (is_array($override)) {
                $cfg = array_replace_recursive($cfg, $override);
            }
        }
        $db = $cfg['db'] ?? null;
        if (!is_array($db)) {
            throw new RuntimeException('Database configuration is missing.');
        }
        return $db;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::normalizeParams($params));
        return $stmt;
    }

    /**
     * PDO warns and stores the word "Array" when a bound value is an array.
     * JSON columns and text fields keep the structured value instead.
     *
     * @param array<int|string,mixed> $params
     * @return array<int|string,mixed>
     */
    private static function normalizeParams(array $params): array
    {
        foreach ($params as $key => $value) {
            $params[$key] = self::bindValue($value);
        }
        return $params;
    }

    private static function bindValue(mixed $value): mixed
    {
        if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return (int)$value;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return $json === false ? null : $json;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`,`', $cols),
            implode(',', $placeholders)
        );
        self::query($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach ($data as $k => $v) {
            $sets[] = "`$k` = :$k";
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);
        $stmt = self::query($sql, array_merge($data, $whereParams));
        return $stmt->rowCount();
    }
}
