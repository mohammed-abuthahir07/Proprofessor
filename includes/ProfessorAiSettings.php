<?php
declare(strict_types=1);

/**
 * Per-professor AI provider connection (BYOK). Isolated by authenticated user id.
 */
final class ProfessorAiSettings
{
    public const PROVIDERS = ['openai', 'gemini', 'claude'];

    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        Database::query(
            "CREATE TABLE IF NOT EXISTS `professor_ai_settings` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `professor_id` INT UNSIGNED NOT NULL,
                `provider` VARCHAR(32) NOT NULL,
                `model` VARCHAR(120) NOT NULL,
                `encrypted_api_key` TEXT NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_prof_ai` (`professor_id`),
                KEY `idx_prof_ai_active` (`professor_id`, `is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function catalog(): array
    {
        static $catalog = null;
        if ($catalog === null) {
            $path = dirname(__DIR__) . '/config/ai_models.php';
            $catalog = is_file($path) ? require $path : [];
            if (!is_array($catalog)) {
                $catalog = [];
            }
        }
        return $catalog;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function modelsByProvider(): array
    {
        $out = [];
        foreach (self::PROVIDERS as $provider) {
            $models = self::catalog()[$provider]['models'] ?? [];
            $out[$provider] = array_values(array_filter($models, static fn($m) => is_string($m) && $m !== ''));
        }
        return $out;
    }

    public static function defaultModel(string $provider): string
    {
        $cat = self::catalog()[$provider] ?? [];
        if (!empty($cat['default']) && is_string($cat['default'])) {
            return $cat['default'];
        }
        $models = self::modelsByProvider();
        return $models[$provider][0] ?? '';
    }

    public static function docsUrl(string $provider): string
    {
        $url = (string)(self::catalog()[$provider]['docs_url'] ?? '');
        return str_starts_with($url, 'https://') ? $url : '';
    }

    public static function providerLabel(string $provider): string
    {
        return match ($provider) {
            'openai' => 'OpenAI / ChatGPT',
            'gemini' => 'Gemini',
            'claude' => 'Claude',
            default => $provider,
        };
    }

    public static function normalizeProvider(string $provider): ?string
    {
        $provider = strtolower(trim($provider));
        return in_array($provider, self::PROVIDERS, true) ? $provider : null;
    }

    /**
     * @return array{ok:bool,model?:string,error?:string}
     */
    public static function validateModel(string $provider, string $model): array
    {
        $provider = self::normalizeProvider($provider);
        if ($provider === null) {
            return ['ok' => false, 'error' => 'Choose OpenAI, Gemini, or Claude.'];
        }
        $model = trim($model);
        $model = preg_replace('#^models/#', '', $model) ?? $model;
        if ($model === '') {
            return ['ok' => true, 'model' => self::defaultModel($provider)];
        }
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:-]{1,79}$/', $model)) {
            return ['ok' => false, 'error' => 'The selected model is not available for this API account. Please select another supported model.'];
        }
        $allowed = self::modelsByProvider()[$provider] ?? [];
        if (in_array($model, $allowed, true)) {
            return ['ok' => true, 'model' => $model];
        }
        $prefix = (string)(self::catalog()[$provider]['prefix'] ?? '');
        if ($prefix !== '' && str_starts_with(strtolower($model), strtolower($prefix))) {
            return ['ok' => true, 'model' => $model];
        }
        return ['ok' => false, 'error' => 'The selected model is not available for this API account. Please select another supported model.'];
    }

    public static function normalizeModel(string $provider, string $model): string
    {
        $checked = self::validateModel($provider, $model);
        return !empty($checked['ok']) ? (string)$checked['model'] : '';
    }

    public static function maskKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '••••';
        }
        $tail = substr($key, -4);
        return '••••••••' . $tail;
    }

    /**
     * Public view for the Settings page — never includes the full key.
     *
     * @return array{connected:bool,provider:?string,provider_label:?string,model:?string,masked_key:?string}|null
     */
    public static function publicForProfessor(int $professorId): ?array
    {
        $row = self::rowForProfessor($professorId);
        if (!$row || !(int)$row['is_active']) {
            return null;
        }
        $key = '';
        try {
            $key = Crypto::decrypt((string)$row['encrypted_api_key']);
        } catch (Throwable $e) {
            $key = '';
        }
        $provider = (string)$row['provider'];
        return [
            'connected' => true,
            'provider' => $provider,
            'provider_label' => self::providerLabel($provider),
            'model' => (string)$row['model'],
            'masked_key' => self::maskKey($key),
        ];
    }

    /**
     * Decrypted active connection for generation. Never send to the browser.
     *
     * @return array{provider:string,model:string,api_key:string}|null
     */
    public static function activeSecretForProfessor(int $professorId): ?array
    {
        $row = self::rowForProfessor($professorId);
        if (!$row || !(int)$row['is_active']) {
            return null;
        }
        try {
            $apiKey = Crypto::decrypt((string)$row['encrypted_api_key']);
        } catch (Throwable $e) {
            return null;
        }
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return null;
        }
        return [
            'provider' => (string)$row['provider'],
            'model' => (string)$row['model'],
            'api_key' => $apiKey,
        ];
    }

    /**
     * Live provider ping using the given or stored key.
     *
     * @return array{ok:bool,error?:string,message?:string}
     */
    public static function testConnection(string $provider, string $model, string $apiKey): array
    {
        $provider = self::normalizeProvider($provider);
        if ($provider === null) {
            return ['ok' => false, 'error' => 'Choose OpenAI, Gemini, or Claude.'];
        }
        $modelCheck = self::validateModel($provider, $model);
        if (empty($modelCheck['ok'])) {
            return ['ok' => false, 'error' => (string)($modelCheck['error'] ?? 'Unsupported model.')];
        }
        $model = (string)$modelCheck['model'];
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'API key is required.'];
        }

        $client = ProfessorAi::clientFor($provider, $model, $apiKey, true);
        if ($client instanceof OpenAIClient || $client instanceof ClaudeClient) {
            $result = $client->ping();
        } elseif ($client instanceof Gemini) {
            $result = $client->generate(
                'You are a connection test. Reply with JSON only.',
                'Return {"ok":true}'
            );
        } else {
            return ['ok' => false, 'error' => 'Unsupported provider.'];
        }

        $result = ProfessorAi::normalizeResult($result, $provider, $model, true);
        if (empty($result['ok'])) {
            return ['ok' => false, 'error' => (string)($result['error'] ?? 'Connection failed.')];
        }
        return ['ok' => true, 'message' => 'Connection successful.'];
    }

    /**
     * Validate then store. Replaces any previous provider for this professor.
     *
     * @return array{ok:bool,error?:string,message?:string}
     */
    public static function connect(int $professorId, string $provider, string $model, string $apiKey): array
    {
        $test = self::testConnection($provider, $model, $apiKey);
        if (empty($test['ok'])) {
            return $test;
        }
        $provider = self::normalizeProvider($provider);
        if ($provider === null) {
            return ['ok' => false, 'error' => 'Choose OpenAI, Gemini, or Claude.'];
        }
        $modelCheck = self::validateModel($provider, $model);
        if (empty($modelCheck['ok'])) {
            return ['ok' => false, 'error' => (string)$modelCheck['error']];
        }
        $model = (string)$modelCheck['model'];
        self::ensureSchema();
        $encrypted = Crypto::encrypt(trim($apiKey));
        $existing = self::rowForProfessor($professorId);
        if ($existing) {
            Database::update('professor_ai_settings', [
                'provider' => $provider,
                'model' => $model,
                'encrypted_api_key' => $encrypted,
                'is_active' => 1,
            ], 'professor_id = :pid', ['pid' => $professorId]);
        } else {
            Database::insert('professor_ai_settings', [
                'professor_id' => $professorId,
                'provider' => $provider,
                'model' => $model,
                'encrypted_api_key' => $encrypted,
                'is_active' => 1,
            ]);
        }
        return ['ok' => true, 'message' => self::providerLabel($provider) . ' connected.'];
    }

    public static function disconnect(int $professorId): void
    {
        self::ensureSchema();
        Database::query('DELETE FROM professor_ai_settings WHERE professor_id = ?', [$professorId]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function rowForProfessor(int $professorId): ?array
    {
        if ($professorId < 1) {
            return null;
        }
        self::ensureSchema();
        return Database::fetch(
            'SELECT * FROM professor_ai_settings WHERE professor_id = ? LIMIT 1',
            [$professorId]
        );
    }
}
