<?php
declare(strict_types=1);

final class Features
{
    public static function forInstitution(int $institutionId): array
    {
        return Database::fetchAll(
            'SELECT f.code, f.name, f.description, f.module,
                    COALESCE(i.is_enabled, f.is_enabled) AS is_enabled,
                    COALESCE(i.config, f.default_config) AS config
             FROM feature_flags f
             LEFT JOIN institution_features i
               ON i.feature_code = f.code AND i.institution_id = ?
             ORDER BY f.module, f.name',
            [$institutionId]
        );
    }

    public static function toggle(int $institutionId, string $code, bool $enabled, ?array $config = null): void
    {
        $existing = Database::fetch(
            'SELECT id FROM institution_features WHERE institution_id = ? AND feature_code = ?',
            [$institutionId, $code]
        );
        $payload = [
            'is_enabled' => $enabled ? 1 : 0,
            'config' => $config ? json_encode($config) : null,
            'enabled_at' => $enabled ? date('Y-m-d H:i:s') : null,
            'disabled_at' => $enabled ? null : date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            Database::update('institution_features', $payload, 'id = :id', ['id' => $existing['id']]);
        } else {
            Database::insert('institution_features', array_merge([
                'institution_id' => $institutionId,
                'feature_code' => $code,
            ], $payload));
        }
    }
}
