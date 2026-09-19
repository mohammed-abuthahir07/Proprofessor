<?php
declare(strict_types=1);

/**
 * OpenAI Chat Completions client. Returns the same shape as Gemini::generate().
 */
final class OpenAIClient
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    public function __construct(string $apiKey, string $model, ?string $endpoint = null)
    {
        $this->apiKey = trim($apiKey);
        $this->model = trim($model) !== '' ? trim($model) : 'gpt-5.6-terra';
        $this->endpoint = rtrim($endpoint ?: 'https://api.openai.com/v1', '/');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * @return array{ok:bool,text:?string,json:?array,raw:?array,error?:string,latency_ms:int,http_code?:int,model:string,provider:string}
     */
    public function generate(string $system, string $userPrompt, ?string $model = null): array
    {
        return $this->chat($system, $userPrompt, $model ?: $this->model, true, 90);
    }

    /**
     * Lightweight live connection check (real HTTP call).
     *
     * @return array{ok:bool,text:?string,json:?array,raw:?array,error?:string,latency_ms:int,http_code?:int,model:string,provider:string}
     */
    public function ping(): array
    {
        return $this->chat(
            'You are a connection test. Reply with JSON only.',
            'Return {"ok":true}',
            $this->model,
            true,
            30
        );
    }

    /**
     * @return array{ok:bool,text:?string,json:?array,raw:?array,error?:string,latency_ms:int,http_code?:int,model:string,provider:string}
     */
    private function chat(string $system, string $userPrompt, string $model, bool $jsonMode, int $timeout): array
    {
        $started = hrtime(true);
        if (!$this->isConfigured()) {
            return $this->fail('OpenAI API key is missing.', $started, 0, $model);
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.4,
        ];
        if (str_starts_with($model, 'gpt-5') || str_starts_with($model, 'o')) {
            $payload['max_completion_tokens'] = 8192;
        } else {
            $payload['max_tokens'] = 8192;
        }
        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $result = $this->post('/chat/completions', $payload, $timeout, $started, $model);
        if (empty($result['ok']) && $jsonMode && $this->shouldRetryWithoutJsonMode((string)($result['error'] ?? ''), (int)($result['http_code'] ?? 0))) {
            unset($payload['response_format']);
            $result = $this->post('/chat/completions', $payload, $timeout, $started, $model);
        }
        return $result;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,text:?string,json:?array,raw:?array,error?:string,latency_ms:int,http_code?:int,model:string,provider:string}
     */
    private function post(string $path, array $payload, int $timeout, int $started, string $model): array
    {
        $ch = curl_init($this->endpoint . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return $this->fail($err !== '' ? $err : 'Network error contacting OpenAI.', $started, $code, $model);
        }

        $raw = json_decode((string)$body, true);
        if ($code >= 400) {
            $msg = '';
            if (is_array($raw)) {
                $msg = (string)($raw['error']['message'] ?? $raw['error']['code'] ?? '');
            }
            if ($msg === '') {
                $msg = 'HTTP ' . $code;
            }
            return $this->fail($msg, $started, $code, $model, is_array($raw) ? $raw : null);
        }

        $text = (string)($raw['choices'][0]['message']['content'] ?? '');
        $json = ProfessorAi::parseJsonPayload($text);

        return [
            'ok' => true,
            'text' => $text,
            'json' => $json,
            'raw' => is_array($raw) ? $raw : null,
            'latency_ms' => (int)((hrtime(true) - $started) / 1e6),
            'http_code' => $code,
            'model' => $model,
            'provider' => 'openai',
        ];
    }

    private function shouldRetryWithoutJsonMode(string $error, int $http): bool
    {
        $l = strtolower($error);
        return $http === 400 && (
            str_contains($l, 'response_format')
            || str_contains($l, 'json_object')
            || str_contains($l, 'json schema')
        );
    }

    /**
     * @return array{ok:bool,text:?string,json:?array,raw:?array,error:string,latency_ms:int,http_code:int,model:string,provider:string}
     */
    private function fail(string $error, int $started, int $http, string $model, ?array $raw = null): array
    {
        return [
            'ok' => false,
            'text' => null,
            'json' => null,
            'raw' => $raw,
            'error' => $error,
            'latency_ms' => (int)((hrtime(true) - $started) / 1e6),
            'http_code' => $http,
            'model' => $model,
            'provider' => 'openai',
        ];
    }
}
