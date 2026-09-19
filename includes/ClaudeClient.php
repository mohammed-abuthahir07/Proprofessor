<?php
declare(strict_types=1);

/**
 * Anthropic Claude Messages API client. Returns the same shape as Gemini::generate().
 */
final class ClaudeClient
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    public function __construct(string $apiKey, string $model, ?string $endpoint = null)
    {
        $this->apiKey = trim($apiKey);
        $this->model = trim($model) !== '' ? trim($model) : 'claude-sonnet-5';
        $this->endpoint = rtrim($endpoint ?: 'https://api.anthropic.com/v1', '/');
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
        $system = trim($system) . "\nReturn ONLY valid JSON. No markdown fences.";
        return $this->messages($system, $userPrompt, $model ?: $this->model, 90);
    }

    /**
     * @return array{ok:bool,text:?string,json:?array,raw:?array,error?:string,latency_ms:int,http_code?:int,model:string,provider:string}
     */
    public function ping(): array
    {
        return $this->messages(
            'You are a connection test. Reply with JSON only.',
            'Return {"ok":true}',
            $this->model,
            30
        );
    }

    /**
     * @return array{ok:bool,text:?string,json:?array,raw:?array,error?:string,latency_ms:int,http_code?:int,model:string,provider:string}
     */
    private function messages(string $system, string $userPrompt, string $model, int $timeout): array
    {
        $started = hrtime(true);
        if (!$this->isConfigured()) {
            return $this->fail('Claude API key is missing.', $started, 0, $model);
        }

        $payload = [
            'model' => $model,
            'max_tokens' => 8192,
            'system' => $system,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];
        // Do not send temperature/top_p/top_k.
        // Claude Sonnet 5 / Opus 5 reject non-default sampling params (HTTP 400).

        $ch = curl_init($this->endpoint . '/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
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
            return $this->fail($err !== '' ? $err : 'Network error contacting Claude.', $started, $code, $model);
        }

        $raw = json_decode((string)$body, true);
        if ($code >= 400) {
            $msg = '';
            if (is_array($raw)) {
                $msg = (string)($raw['error']['message'] ?? $raw['error']['type'] ?? '');
            }
            if ($msg === '') {
                $msg = 'HTTP ' . $code;
            }
            return $this->fail($msg, $started, $code, $model, is_array($raw) ? $raw : null);
        }

        $text = '';
        foreach (($raw['content'] ?? []) as $part) {
            if (is_array($part) && ($part['type'] ?? '') === 'text') {
                $text .= (string)($part['text'] ?? '');
            }
        }
        $json = ProfessorAi::parseJsonPayload($text);

        return [
            'ok' => true,
            'text' => $text,
            'json' => $json,
            'raw' => is_array($raw) ? $raw : null,
            'latency_ms' => (int)((hrtime(true) - $started) / 1e6),
            'http_code' => $code,
            'model' => $model,
            'provider' => 'claude',
        ];
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
            'provider' => 'claude',
        ];
    }
}
