<?php
declare(strict_types=1);

/**
 * Resolves the AI engine for a Professor: BYOK provider if connected, else global Gemini.
 */
final class ProfessorAi
{
    private object $client;
    private bool $byok;
    private string $provider;
    private string $model;

    private function __construct(object $client, bool $byok, string $provider, string $model)
    {
        $this->client = $client;
        $this->byok = $byok;
        $this->provider = $provider;
        $this->model = $model;
    }

    public static function forUser(array $user): self
    {
        $role = (string)($user['role'] ?? '');
        if ($role === 'professor') {
            $secret = ProfessorAiSettings::activeSecretForProfessor((int)($user['id'] ?? 0));
            if ($secret) {
                $client = self::clientFor($secret['provider'], $secret['model'], $secret['api_key'], true);
                return new self($client, true, $secret['provider'], $secret['model']);
            }
            return new self(new Gemini(['api_key' => '']), false, '', '');
        }
        $gemini = new Gemini();
        $model = class_exists('Gemini')
            ? Gemini::normalizeModel((string)config('gemini.model', 'gemini-2.5-flash'))
            : (string)config('gemini.model', 'gemini-2.5-flash');
        return new self($gemini, false, 'gemini', $model);
    }

    public function isByok(): bool
    {
        return $this->byok;
    }

    public function isConfigured(): bool
    {
        if (!$this->byok && $this->provider === '') {
            return false;
        }
        return method_exists($this->client, 'isConfigured') && (bool)$this->client->isConfigured();
    }

    public function generate(string $system, string $userPrompt, ?string $model = null): array
    {
        if (!$this->byok && $this->provider === '') {
            return [
                'ok' => false,
                'text' => null,
                'json' => null,
                'error' => 'No AI provider connected. Open Settings → AI Provider and connect OpenAI, Gemini, or Claude before generating.',
                'latency_ms' => 0,
                'provider' => '',
                'model' => '',
            ];
        }
        $result = $this->client->generate($system, $userPrompt, $model);
        return self::normalizeResult($result, $this->provider, $model ?: $this->model);
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * @param array<string,mixed> $result
     * @return array{ok:bool,text:?string,json:?array,raw:?array,error?:string,latency_ms:int,provider:string,model:string,http_code?:int}
     */
    public static function normalizeResult(array $result, string $provider, string $model, bool $forTest = false): array
    {
        $result['provider'] = $provider;
        $result['model'] = (string)($result['model'] ?? $model);
        if (empty($result['ok'])) {
            $result['error'] = self::friendlyError(
                $provider,
                (string)($result['error'] ?? 'The AI provider request failed.'),
                (int)($result['http_code'] ?? 0),
                $forTest
            );
        } elseif (($result['json'] ?? null) === null && is_string($result['text'] ?? null)) {
            $parsed = self::parseJsonPayload((string)$result['text']);
            if (is_array($parsed)) {
                $result['json'] = $parsed;
            }
        }
        unset($result['raw']);
        return $result;
    }

    public static function parseJsonPayload(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (preg_match('/^```(?:json)?\s*([\s\S]*?)```/i', $text, $m)) {
            $text = trim($m[1]);
        }
        $json = json_decode($text, true);
        if (is_array($json)) {
            return $json;
        }
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $json = json_decode($m[0], true);
            if (is_array($json)) {
                return $json;
            }
        }
        return null;
    }

    public static function friendlyError(string $provider, string $raw, int $http = 0, bool $forTest = false): string
    {
        $l = strtolower($raw);
        $invalid = $http === 401 || $http === 403
            || str_contains($l, 'invalid api key')
            || str_contains($l, 'incorrect api key')
            || str_contains($l, 'api key not valid')
            || str_contains($l, 'invalid x-api-key')
            || str_contains($l, 'authentication')
            || str_contains($l, 'unauthorized')
            || str_contains($l, 'permission_denied')
            || str_contains($l, 'api_key_invalid');
        if ($invalid) {
            return $forTest
                ? 'API key is invalid. Please check the key and try again.'
                : 'Invalid API key. Please check your API key and try again.';
        }

        $modelMissing = $http === 404
            || str_contains($l, 'model not found')
            || str_contains($l, 'is not found')
            || str_contains($l, 'no longer available')
            || str_contains($l, 'does not exist')
            || (str_contains($l, 'model') && str_contains($l, 'not found'));
        if ($modelMissing) {
            return 'The selected model is not available for this API account. Please select another supported model.';
        }

        $rate = $http === 429 && (str_contains($l, 'rate') || str_contains($l, 'too many'));
        $quota = $http === 429
            || str_contains($l, 'insufficient_quota')
            || str_contains($l, 'quota')
            || str_contains($l, 'resource_exhausted')
            || str_contains($l, 'credit balance')
            || str_contains($l, 'billing');
        if ($rate && !$quota) {
            return 'The provider is temporarily rate limiting requests. Please try again shortly.';
        }
        if ($quota) {
            if ($forTest) {
                return 'Your provider account has reached its usage or billing limit. Please check your provider account.';
            }
            return match ($provider) {
                'openai' => 'Your OpenAI model quota/usage limit has been reached. Please check your OpenAI billing/usage or update your API key/model.',
                'claude' => 'Your Claude model quota/usage limit has been reached. Please check your Claude usage/billing or update your API key/model.',
                default => 'Your Gemini model quota/usage limit has been reached. Please check your Gemini quota or update your API key/model.',
            };
        }

        if ($http >= 500 || str_contains($l, 'temporarily unavailable') || str_contains($l, 'overloaded')) {
            return 'The AI provider is temporarily unavailable. Please try again.';
        }
        if (str_contains($l, 'timed out') || str_contains($l, 'timeout') || str_contains($l, 'operation timed out')) {
            return 'The AI provider request timed out. Please try again.';
        }

        $clean = trim((string)preg_replace('/https?:\/\/\S+/i', '', $raw));
        $clean = trim((string)preg_replace('/\s+/', ' ', $clean));
        if ($clean === '' || strlen($clean) > 240) {
            $label = $provider !== '' ? ProfessorAiSettings::providerLabel($provider) : 'AI provider';
            return 'The ' . $label . ' request failed. Please try again or update your API key/model.';
        }
        return $clean;
    }

    public static function requireConnected(object $engine): void
    {
        if ($engine instanceof self && !$engine->isByok()) {
            json_response([
                'ok' => false,
                'error' => 'No AI provider connected. Open Settings → AI Provider and connect OpenAI, Gemini, or Claude before generating.',
            ], 422);
        }
    }

    public static function clientFor(string $provider, string $model, string $apiKey, bool $strictModel = false): object
    {
        $apiKey = trim($apiKey);
        $model = ProfessorAiSettings::normalizeModel($provider, $model);
        return match ($provider) {
            'openai' => new OpenAIClient($apiKey, $model),
            'claude' => new ClaudeClient($apiKey, $model),
            default => new Gemini([
                'api_key' => $apiKey,
                'model' => $model,
                'endpoint' => (string)config('gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta'),
                'strict_model' => $strictModel,
            ]),
        };
    }

    public static function abortIfByokFailed(object $engine, array $result): void
    {
        if (!empty($result['ok'])) {
            return;
        }
        if ($engine instanceof self && $engine->isByok()) {
            json_response([
                'ok' => false,
                'error' => (string)($result['error'] ?? 'The AI provider request failed.'),
            ], 502);
        }
    }

    public static function abortIfByokUnusable(object $engine, string $message = 'The AI provider returned unusable content. Please try again.'): void
    {
        if ($engine instanceof self && $engine->isByok()) {
            json_response(['ok' => false, 'error' => $message], 502);
        }
    }
}

/**
 * AI client for the current request: Professor BYOK when connected.
 * Non-professor roles keep the existing global Gemini client.
 */
function professor_ai_engine(?array $user = null): object
{
    $user = $user ?? (class_exists('Auth') ? Auth::user() : null);
    if (is_array($user) && ($user['role'] ?? '') === 'professor') {
        return ProfessorAi::forUser($user);
    }
    return new Gemini();
}

function professor_ai_is_byok(object $engine): bool
{
    return $engine instanceof ProfessorAi && $engine->isByok();
}
