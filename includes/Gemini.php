<?php
declare(strict_types=1);

/**
 * Google Gemini API client for ProProfessor AI modules.
 */
final class Gemini
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    public function __construct(?array $cfg = null)
    {
        if ($cfg === null && function_exists('config')) {
            $app = config('gemini') ?? [];
        } else {
            $app = $cfg ?? (require __DIR__ . '/../config/config.php')['gemini'];
        }
        $this->apiKey   = (string)($app['api_key'] ?? '');
        $this->model    = self::normalizeModel((string)($app['model'] ?? 'gemini-2.5-flash'));
        $this->endpoint = rtrim((string)($app['endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array{ok:bool,text:?string,json:?array,raw:?array,error?:string,latency_ms:int}
     */
    public function generate(string $system, string $userPrompt, ?string $model = null): array
    {
        $started = hrtime(true);
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'text' => null,
                'json' => null,
                'raw' => null,
                'error' => 'Gemini API key missing. Set GEMINI_API_KEY or config/config.php gemini.api_key.',
                'latency_ms' => 0,
            ];
        }

        $tried = [];
        $last = null;
        foreach (self::modelCandidates($model ?: $this->model) as $modelName) {
            if (isset($tried[$modelName])) {
                continue;
            }
            $tried[$modelName] = true;
            $last = $this->request($system, $userPrompt, $modelName, $started);
            if (!empty($last['ok'])) {
                return $last;
            }
            if (!self::isRetiredModelError((string)($last['error'] ?? ''))) {
                return $last;
            }
        }

        return $last ?? [
            'ok' => false,
            'text' => null,
            'json' => null,
            'raw' => null,
            'error' => 'Gemini model is unavailable.',
            'latency_ms' => (int)((hrtime(true) - $started) / 1e6),
        ];
    }

    public static function normalizeModel(string $model): string
    {
        $model = trim($model);
        $model = preg_replace('#^models/#', '', $model) ?? $model;
        $retired = [
            'gemini-1.0-pro' => 'gemini-2.5-flash',
            'gemini-1.0-pro-latest' => 'gemini-2.5-flash',
            'gemini-pro' => 'gemini-2.5-flash',
            'gemini-pro-latest' => 'gemini-2.5-flash',
            'gemini-1.5-pro' => 'gemini-2.5-flash',
            'gemini-1.5-pro-latest' => 'gemini-2.5-flash',
            'gemini-2.0-flash' => 'gemini-2.5-flash',
            'gemini-2.0-flash-001' => 'gemini-2.5-flash',
            'gemini-2.0-flash-exp' => 'gemini-2.5-flash',
        ];
        return $retired[$model] ?? $model;
    }

    /** @return list<string> */
    private static function modelCandidates(string $preferred): array
    {
        $preferred = self::normalizeModel($preferred);
        return array_values(array_unique(array_merge([$preferred], [
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-1.5-flash-latest',
            'gemini-flash-latest',
        ])));
    }

    private static function isRetiredModelError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, 'no longer available')
            || str_contains($message, 'is not found')
            || (str_contains($message, 'model') && str_contains($message, 'not found'));
    }

    /**
     * @return array{ok:bool,text:?string,json:?array,raw:?array,error?:string,latency_ms:int}
     */
    private function request(string $system, string $userPrompt, string $modelName, int $started): array
    {
        $url = $this->endpoint . '/models/' . rawurlencode($modelName) . ':generateContent?key=' . urlencode($this->apiKey);
        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $system]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $userPrompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'responseMimeType' => 'application/json',
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 90,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $err   = curl_error($ch);
        $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $latency = (int)((hrtime(true) - $started) / 1e6);
        if ($errno) {
            return ['ok' => false, 'text' => null, 'json' => null, 'raw' => null, 'error' => $err, 'latency_ms' => $latency];
        }

        $raw = json_decode((string)$body, true);
        if ($code >= 400) {
            $msg = $raw['error']['message'] ?? ('HTTP ' . $code);
            return ['ok' => false, 'text' => null, 'json' => null, 'raw' => $raw, 'error' => $msg, 'latency_ms' => $latency];
        }

        $text = '';
        foreach (($raw['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (!empty($part['text'])) {
                $text .= (string)$part['text'];
            }
        }
        $json = json_decode($text, true);
        if (!is_array($json) && preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $json = json_decode($m[0], true);
        }

        return [
            'ok' => true,
            'text' => $text,
            'json' => is_array($json) ? $json : null,
            'raw' => $raw,
            'latency_ms' => $latency,
        ];
    }

    /**
     * Demo/offline fallback content when API key is absent.
     */
    public static function demoCoursePlan(string $subject, string $syllabus): array
    {
        $units = [];
        $lines = preg_split('/\r\n|\r|\n/', $syllabus) ?: [];
        $i = 1;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('/unit\s*(\d+)\s*[:\-.]?\s*(.*)/i', $line, $m)) {
                $units[] = [
                    'unit_number' => (int)$m[1],
                    'title' => $m[2] ?: ('Unit ' . $m[1]),
                    'hours' => 12,
                    'topics' => [trim($m[2])],
                    'outcomes' => [
                        'Explain key concepts of ' . ($m[2] ?: $subject),
                        'Apply techniques related to ' . ($m[2] ?: $subject),
                    ],
                    'bloom_k_level' => 'K' . min(6, $i),
                    'teaching_methods' => ['Lecture', 'Discussion', 'Demo'],
                    'assessment' => ['Quiz', 'Assignment'],
                ];
                $i++;
            }
        }
        if (!$units) {
            for ($n = 1; $n <= 5; $n++) {
                $units[] = [
                    'unit_number' => $n,
                    'title' => "Unit $n · Core Concepts",
                    'hours' => 12,
                    'topics' => ["Topic $n.1", "Topic $n.2"],
                    'outcomes' => ["CLO$n: Demonstrate competence in unit $n"],
                    'bloom_k_level' => 'K' . min(6, $n),
                    'teaching_methods' => ['Lecture', 'Activity'],
                    'assessment' => ['Formative quiz'],
                ];
            }
        }

        return [
            'title' => $subject . ' · Course Plan',
            'subject' => $subject,
            'learning_outcomes' => [
                'Understand foundational concepts',
                'Apply skills to academic and industry contexts',
                'Analyze problems using higher-order thinking',
            ],
            'units' => $units,
            'weekly_plan' => [
                ['week' => 1, 'focus' => 'Orientation & Unit 1'],
                ['week' => 2, 'focus' => 'Unit 1 continued'],
                ['week' => 3, 'focus' => 'Unit 2'],
            ],
            'resources' => [
                'Textbook: Standard university recommended text',
                'NPTEL / SWAYAM modules',
                'Lab manuals / case packs',
            ],
            'expert_advice' => [
                'Balance K1-K3 with K4-K6 activities for NBA OBE evidence.',
                'Map each CLO to assessments explicitly.',
            ],
            'bloom_distribution' => [
                'K1' => 15, 'K2' => 20, 'K3' => 25, 'K4' => 20, 'K5' => 12, 'K6' => 8,
            ],
            'ai_score' => 78,
            'demo' => true,
        ];
    }
}
