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
        $parsed = CoursePlanTools::parseSyllabusIntoUnits($syllabus);
        if ($parsed !== []) {
            foreach ($parsed as $i => $p) {
                $n = (int)$p['unit_number'];
                $title = trim((string)$p['title']);
                $topics = array_values(array_filter($p['topics'] ?? [], static fn($t) => is_string($t) && trim($t) !== ''));
                if ($topics === []) {
                    $topics = $title !== '' ? [$title] : ['Core concepts'];
                }
                $units[] = [
                    'unit_number' => $n,
                    'title' => $title !== '' ? ('Unit ' . $n . ' – ' . $title) : ('Unit ' . $n),
                    'hours' => 12,
                    'topics' => $topics,
                    'outcomes' => [
                        'Explain key concepts of ' . ($title !== '' ? $title : $subject),
                        'Apply techniques related to ' . ($title !== '' ? $title : $subject),
                    ],
                    'bloom_k_level' => 'K' . min(6, max(1, $n)),
                    'teaching_methods' => ['Lecture', 'Discussion', 'Demo'],
                    'assessment' => ['Quiz', 'Assignment'],
                ];
            }
        }
        if (!$units) {
            // True fallback only when syllabus has no detectable unit structure.
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

    /**
     * Build academically usable demo questions from subject + unit syllabus/context.
     * Used when Gemini is not configured, or when AI returns unusable placeholders.
     *
     * @param list<string> $unitTopics
     * @return list<array<string,mixed>>
     */
    public static function demoQuestionBank(
        string $subject,
        string $type,
        string $klevel,
        int $unit,
        int $count,
        string $context = '',
        array $unitTopics = []
    ): array {
        $subject = trim($subject) !== '' ? trim($subject) : 'the selected course';
        $type = strtolower(trim($type));
        if (!in_array($type, ['mcq', 'short', 'long', 'essay', 'case'], true)) {
            $type = 'mcq';
        }
        $klevel = strtoupper(trim($klevel));
        if (!preg_match('/^K[1-6]$/', $klevel)) {
            $klevel = 'K1';
        }
        $unit = max(1, min(20, $unit));
        $count = max(1, min(20, $count));

        $topics = self::resolveQuestionTopics($subject, $unit, $context, $unitTopics);
        // Prefer real syllabus concepts over the bare course title / unit heading as the "topic".
        $filtered = [];
        foreach ($topics as $t) {
            $t = self::cleanTopicText((string)$t);
            if ($t === '') {
                continue;
            }
            if (strcasecmp($t, $subject) === 0) {
                continue;
            }
            if (preg_match('/^unit\s*\d+(\s*[–\-:].*)?$/i', $t)) {
                continue;
            }
            $filtered[] = $t;
        }
        if (count($filtered) >= 2) {
            $topics = $filtered;
        }
        $questions = [];
        $marks = match ($type) {
            'mcq' => 1,
            'short' => 5,
            'long' => 10,
            'essay' => 15,
            'case' => 12,
            default => 1,
        };

        for ($i = 0; $i < $count; $i++) {
            $topic = $topics[$i % count($topics)];
            $nextTopic = $topics[($i + 1) % count($topics)];
            $prevTopic = $topics[($i + count($topics) - 1) % count($topics)];
            $altTopic = $topics[($i + 2) % count($topics)];

            if ($type === 'mcq') {
                $pack = self::demoMcqForTopic($subject, $topic, $nextTopic, $prevTopic, $altTopic, $klevel, $unit, $i, $context, $topics);
                $questions[] = [
                    'stem' => $pack['stem'],
                    'options' => $pack['options'],
                    'correct_answer' => $pack['correct_answer'],
                    'explanation' => $pack['explanation'],
                    'bloom_k_level' => $klevel,
                    'unit_number' => $unit,
                    'marks' => $marks,
                    'difficulty' => $i % 3 === 0 ? 'easy' : ($i % 3 === 1 ? 'medium' : 'hard'),
                    'question_type' => 'mcq',
                ];
            } else {
                $questions[] = [
                    'stem' => self::demoOpenStem($subject, $topic, $type, $klevel, $unit, $i, $context),
                    'correct_answer' => self::demoModelAnswer($subject, $topic, $type, $klevel, $context),
                    'explanation' => "Assesses {$klevel} understanding of {$topic} using Unit {$unit} syllabus concepts.",
                    'bloom_k_level' => $klevel,
                    'unit_number' => $unit,
                    'marks' => $marks,
                    'difficulty' => $type === 'short' ? 'medium' : 'hard',
                    'question_type' => $type,
                ];
            }
        }

        return $questions;
    }

    /**
     * Infer subject name and unit number from a presentation title + context.
     *
     * @return array{subject:string,unit:int}
     */
    public static function parsePresentationSubjectUnit(string $title, string $context = ''): array
    {
        $unit = 1;
        $hay = $title . "\n" . $context;
        if (preg_match('/unit\s*(\d+)\b/i', $hay, $m)) {
            $unit = max(1, min(20, (int)$m[1]));
        }

        $subject = '';
        if (preg_match('/(?:for|of|on)\s+unit\s*\d+\s+of\s+(.+?)(?:[.!]|$)/i', $title, $m)) {
            $subject = trim($m[1]);
        } elseif (preg_match('/^(.+?)\s*[·\-|–]\s*unit\s*\d+/i', $title, $m)) {
            $subject = trim($m[1]);
        } elseif (preg_match('/unit\s*\d+\s*[·\-|–:]\s*(.+)$/i', $title, $m)) {
            $candidate = trim($m[1]);
            if (!preg_match('/^(introduction|overview|agenda)/i', $candidate)) {
                $subject = $candidate;
            }
        }
        if ($subject === '' && preg_match('/\b(programming in c|digital fundamentals|data structures|dbms|database management|operating systems?|software engineering|computer networks|java programming)\b/i', $title, $m)) {
            $subject = trim($m[1]);
        }
        if ($subject === '' && $context !== '') {
            $first = trim((string)(preg_split('/\r\n|\r|\n/', $context)[0] ?? ''));
            if ($first !== '' && !preg_match('/^unit\s*\d+/i', $first) && strlen($first) < 120) {
                $subject = $first;
            }
        }
        if ($subject === '') {
            $subject = trim(preg_replace('/\bunit\s*\d+\b/i', '', $title) ?? $title);
            $subject = trim($subject, " ·-|–:.");
        }
        if ($subject === '') {
            $subject = 'Course';
        }
        return ['subject' => $subject, 'unit' => $unit];
    }

    /**
     * Build a professional academic lecture deck for demo / offline mode.
     *
     * @param list<string> $unitTopics
     * @param array{institution?:string,department?:string,academic_year?:string,semester?:string,course_code?:string} $brand
     * @return list<array<string,mixed>>
     */
    public static function demoPresentation(
        string $title,
        string $subject,
        int $unit,
        string $context = '',
        array $unitTopics = [],
        int $slideCount = 12,
        array $brand = []
    ): array {
        $subject = trim($subject) !== '' ? trim($subject) : 'Course';
        $unit = max(1, min(20, $unit));
        $topics = self::resolveQuestionTopics($subject, $unit, $context, $unitTopics);
        if (!class_exists('LectureSlideBuilder', false)) {
            require_once __DIR__ . '/LectureSlideBuilder.php';
        }
        $slides = LectureSlideBuilder::buildDeck($title, $subject, $unit, $context, $topics, $brand);
        // slideCount kept for API compatibility; real decks size to syllabus richness.
        unset($slideCount);
        return $slides;
    }

    /**
     * @param list<string> $unitTopics
     * @return list<string>
     */
    private static function resolveQuestionTopics(string $subject, int $unit, string $context, array $unitTopics): array
    {
        $topics = [];
        foreach ($unitTopics as $t) {
            $t = self::cleanTopicText((string)$t);
            if ($t === '' || preg_match('/^topic\s*\d+(\.\d+)?$/i', $t)) {
                continue;
            }
            if (class_exists('LectureSlideBuilder', false) && LectureSlideBuilder::isUnitHeading($t)) {
                continue;
            }
            $topics[] = $t;
        }
        // Prefer structured syllabus parse over thin/placeholder unit topics.
        if (class_exists('CoursePlanTools', false) || is_file(__DIR__ . '/CoursePlanTools.php')) {
            if (!class_exists('CoursePlanTools', false)) {
                require_once __DIR__ . '/CoursePlanTools.php';
            }
            foreach (CoursePlanTools::parseSyllabusIntoUnits($context) as $u) {
                if ((int)$u['unit_number'] !== $unit) {
                    continue;
                }
                foreach ($u['topics'] as $t) {
                    $t = self::cleanTopicText((string)$t);
                    if ($t === '' || preg_match('/^topic\s*\d+(\.\d+)?$/i', $t)) {
                        continue;
                    }
                    if (class_exists('LectureSlideBuilder', false) && LectureSlideBuilder::isUnitHeading($t)) {
                        continue;
                    }
                    $topics[] = $t;
                }
            }
        }
        foreach (self::extractTopicsFromContext($context, $unit) as $t) {
            $topics[] = $t;
        }
        $topics = array_values(array_unique(array_filter($topics, static fn($t) => strlen($t) > 2)));
        if (count($topics) >= 3) {
            return $topics;
        }
        foreach (self::defaultTopicsForSubject($subject, $unit) as $t) {
            $topics[] = self::cleanTopicText($t);
        }
        $topics = array_values(array_unique(array_filter($topics, static fn($t) => strlen($t) > 2)));
        if (!$topics) {
            $topics = [
                "Core concepts of {$subject}",
                "Fundamental terminology in {$subject}",
                "Basic operations related to {$subject}",
                "Standard applications of {$subject}",
                "Common errors and misconceptions in {$subject}",
            ];
        }
        return $topics;
    }

    private static function cleanTopicText(string $topic): string
    {
        $topic = str_replace(["\r", "\n", "\\n", "\\r"], ' ', $topic);
        $topic = preg_replace('/\s+/', ' ', $topic) ?? $topic;
        $topic = trim($topic, " \t.-•");
        return trim($topic);
    }

    /**
     * @return list<string>
     */
    private static function extractTopicsFromContext(string $context, int $unit): array
    {
        $context = trim($context);
        if ($context === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $context) ?: [];
        $topics = [];
        $inUnit = false;
        $capturedAnyUnitHeader = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/unit\s*(\d+)\b/i', $line, $m)) {
                $capturedAnyUnitHeader = true;
                $inUnit = ((int)$m[1] === $unit);
                // Unit header title is NOT a teaching topic (e.g. "WEB FUNDAMENTALS — 12 Hours").
                continue;
            }
            if ($capturedAnyUnitHeader && !$inUnit) {
                continue;
            }
            if ($capturedAnyUnitHeader && $inUnit) {
                $clean = preg_replace('/^[\-\*\d\.\)\s]+/', '', $line) ?? $line;
                $clean = self::cleanTopicText($clean);
                if ($clean !== '' && !preg_match('/^(outcomes?|hours?|assessment|resources?)\b/i', $clean)) {
                    foreach (preg_split('/[,;\/|]/', $clean) ?: [] as $part) {
                        $part = self::cleanTopicText((string)$part);
                        if (strlen($part) <= 2) {
                            continue;
                        }
                        if (class_exists('LectureSlideBuilder', false) && LectureSlideBuilder::isUnitHeading($part)) {
                            continue;
                        }
                        $topics[] = $part;
                    }
                }
            }
        }
        if ($topics) {
            return $topics;
        }
        // No unit headers: treat bullet/comma-separated fragments as topics.
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^unit\s*\d+/i', $line)) {
                continue;
            }
            $clean = preg_replace('/^[\-\*\d\.\)\s]+/', '', $line) ?? $line;
            foreach (preg_split('/[,;]/', $clean) ?: [] as $part) {
                $part = self::cleanTopicText((string)$part);
                if (strlen($part) > 3 && strlen($part) < 120) {
                    $topics[] = $part;
                }
            }
        }
        return $topics;
    }

    /**
     * Subject-aware curriculum seeds used only when syllabus/context is thin.
     *
     * @return list<string>
     */
    private static function defaultTopicsForSubject(string $subject, int $unit): array
    {
        $s = strtolower($subject);
        $bank = [];
        if (preg_match('/\bc\b|programming in c|c programming|ansi c/', $s)) {
            $bank = [
                1 => ['History and features of C', 'Structure of a C program', 'Tokens and character set', 'Keywords and identifiers', 'Data types in C', 'Variables and constants', 'Operators in C', 'Operator precedence', 'Input and output with printf/scanf', 'Type conversion and type casting'],
                2 => ['Decision making with if and else', 'Nested if statements', 'Switch-case statements', 'Looping with while and do-while', 'For loops', 'Break and continue', 'Nested loops', 'Simple pattern programs'],
                3 => ['One-dimensional arrays', 'Two-dimensional arrays', 'Strings in C', 'String handling functions', 'Pointers basics', 'Pointer arithmetic'],
                4 => ['User-defined functions', 'Call by value and call by reference', 'Recursion', 'Storage classes', 'Structures', 'Unions', 'File handling basics'],
            ];
        } elseif (preg_match('/digital\s*fundament|digital\s*logic|digital\s*electron/', $s)) {
            $bank = [
                1 => ['Number systems (binary, octal, decimal, hexadecimal)', 'Binary arithmetic', '1\'s and 2\'s complement', 'Boolean algebra laws', 'Logic gates (AND, OR, NOT)', 'NAND, NOR, XOR, XNOR gates', 'Truth tables', 'De Morgan\'s theorems', 'Canonical SOP and POS forms', 'Simplification using Boolean algebra'],
                2 => ['Karnaugh maps', 'Combinational circuits', 'Half adder and full adder', 'Multiplexers and demultiplexers', 'Encoders and decoders', 'Comparators'],
                3 => ['Sequential circuits', 'Latches and flip-flops', 'SR, JK, D and T flip-flops', 'Registers', 'Counters', 'Shift registers'],
                4 => ['A/D and D/A conversion basics', 'Memory organization overview', 'PLD concepts', 'Timing diagrams'],
            ];
        } elseif (preg_match('/data\s*struct/', $s)) {
            $bank = [
                1 => ['Introduction to data structures', 'Arrays', 'Linked lists', 'Stacks', 'Queues', 'Time complexity basics'],
                2 => ['Trees', 'Binary search trees', 'Heap', 'Graph representations', 'BFS and DFS'],
            ];
        } elseif (preg_match('/dbms|database/', $s)) {
            $bank = [
                1 => ['Database concepts', 'DBMS architecture overview', 'ER model', 'Relational model', 'Keys and constraints', 'SQL basics', 'Normalization overview'],
            ];
        } elseif (preg_match('/web\s*tech|html|www/', $s)) {
            $bank = [
                1 => [
                    'Introduction to the Internet and World Wide Web',
                    'Client-server architecture',
                    'Web browsers and web servers',
                    'HTTP and HTTPS protocols',
                    'URLs and domain names',
                    'HTML fundamentals',
                    'Document structure',
                    'Elements and attributes',
                    'Headings',
                    'Paragraphs',
                    'Links',
                    'Images',
                ],
            ];
        } elseif (preg_match('/math|calculus|algebra|matrix/', $s)) {
            $bank = [
                1 => ['Matrices and types of matrices', 'Determinants', 'Inverse of a matrix', 'Rank of a matrix', 'System of linear equations overview'],
            ];
        } elseif (preg_match('/operating\s*system|\bos\b/', $s)) {
            $bank = [
                1 => ['OS functions and types', 'Process concepts', 'CPU scheduling basics', 'Process states', 'System calls overview'],
            ];
        }

        if (isset($bank[$unit])) {
            return $bank[$unit];
        }
        if ($bank) {
            $first = $bank[array_key_first($bank)];
            return $first;
        }
        return [
            "Introduction to {$subject}",
            "Fundamental definitions in {$subject}",
            "Core principles of {$subject}",
            "Standard methods used in {$subject}",
            "Practical applications of {$subject}",
            "Common terminology in {$subject}",
        ];
    }

    /**
     * @return array{stem:string,options:array<string,string>,correct_answer:string,explanation:string}
     * @param list<string> $allTopics
     */
    private static function demoMcqForTopic(
        string $subject,
        string $topic,
        string $nextTopic,
        string $prevTopic,
        string $altTopic,
        string $klevel,
        int $unit,
        int $index,
        string $context = '',
        array $allTopics = []
    ): array {
        $matched = self::matchConceptMcq($topic, $subject, $context, $klevel, $index);
        if ($matched !== null) {
            return self::rotateMcqOptions($matched, $index);
        }

        // Syllabus-grounded fallback (still non-circular): use concrete peer concepts as options.
        $peers = [];
        foreach ($allTopics as $t) {
            $t = self::cleanTopicText((string)$t);
            if ($t === '' || strcasecmp($t, $topic) === 0) {
                continue;
            }
            if (!preg_match('/^topic\s*\d+(\.\d+)?$/i', $t) && strlen($t) > 3) {
                $peers[] = $t;
            }
        }
        $peers = array_values(array_unique($peers));
        while (count($peers) < 3) {
            $peers[] = 'A concept outside the current unit syllabus';
        }

        $bloom = strtoupper($klevel);
        if ($bloom === 'K1') {
            $stem = match ($index % 3) {
                0 => "Which of the following is a concrete concept studied under \"{$topic}\"?",
                1 => "In Unit {$unit}, which term is most directly associated with \"{$topic}\"?",
                default => "Which option correctly identifies content covered by \"{$topic}\"?",
            };
            $correct = $topic;
            // Prefer a shorter, more testable label when topic is a long phrase.
            if (strlen($topic) > 48 && preg_match('/\b([A-Za-z][A-Za-z0-9\-]{2,})\b/', $topic, $m)) {
                $correct = $topic; // keep full topic as the identifiable content label
            }
            $wrong = array_slice($peers, 0, 3);
            // Ensure distractors are not identical to correct.
            $wrong = array_values(array_filter($wrong, static fn($w) => strcasecmp((string)$w, $correct) !== 0));
            while (count($wrong) < 3) {
                $wrong[] = 'An unrelated lab instrument reading';
            }
            $pack = [
                'stem' => $stem,
                'options' => [
                    'A' => $correct,
                    'B' => $wrong[0],
                    'C' => $wrong[1],
                    'D' => $wrong[2],
                ],
                'correct_answer' => 'A',
                'explanation' => "\"{$correct}\" is the syllabus topic being assessed; the other options are different unit topics or unrelated items.",
            ];
            return self::rotateMcqOptions($pack, $index);
        }

        if ($bloom === 'K2') {
            $stem = match ($index % 3) {
                0 => "Which statement best shows understanding of \"{$topic}\" in Unit {$unit}?",
                1 => "Why is \"{$topic}\" introduced before related ideas such as \"{$nextTopic}\" in this unit?",
                default => "Which explanation of \"{$topic}\" is most consistent with the unit syllabus?",
            };
            $correct = "It provides essential background needed to understand later Unit {$unit} ideas such as {$nextTopic}.";
            $wrong1 = "It is used only to rename {$nextTopic} without adding meaning.";
            $wrong2 = "It removes the need to study {$prevTopic} and {$altTopic}.";
            $wrong3 = "It applies only after the full course is finished, not during Unit {$unit}.";
            $pack = [
                'stem' => $stem,
                'options' => [
                    'A' => $correct,
                    'B' => $wrong1,
                    'C' => $wrong2,
                    'D' => $wrong3,
                ],
                'correct_answer' => 'A',
                'explanation' => "Understanding \"{$topic}\" supports progression to related Unit {$unit} concepts.",
            ];
            return self::rotateMcqOptions($pack, $index);
        }

        // K3+ application-style without circular definition.
        $stem = match ($index % 3) {
            0 => "A classroom task requires using \"{$topic}\". Which action is most appropriate?",
            1 => "While solving a Unit {$unit} exercise involving \"{$topic}\", what should you do first?",
            default => "Which practice best applies \"{$topic}\" to a Unit {$unit} problem?",
        };
        $correct = "Identify the relevant facts about {$topic}, then apply them step-by-step to the given problem.";
        $pack = [
            'stem' => $stem,
            'options' => [
                'A' => $correct,
                'B' => "Ignore {$topic} and answer using only {$nextTopic}.",
                'C' => "Replace the problem with a definition of {$subject}.",
                'D' => "Skip working and select any option from {$prevTopic}.",
            ],
            'correct_answer' => 'A',
            'explanation' => "Application questions require using \"{$topic}\" deliberately on the problem, not avoiding it.",
        ];
        return self::rotateMcqOptions($pack, $index);
    }

    /**
     * Keyword-matched concept MCQs (content-driven, not course-name hardcoding).
     *
     * @return array{stem:string,options:array<string,string>,correct_answer:string,explanation:string}|null
     */
    private static function matchConceptMcq(
        string $topic,
        string $subject,
        string $context,
        string $klevel,
        int $index
    ): ?array {
        $hay = strtolower($topic . ' ' . $subject . ' ' . mb_substr($context, 0, 1200));
        $k = strtoupper($klevel);
        if (!in_array($k, ['K1', 'K2', 'K3', 'K4', 'K5', 'K6'], true)) {
            $k = 'K1';
        }
        $bankLevel = match ($k) {
            'K1' => 'K1',
            'K2' => 'K2',
            default => 'K3',
        };

        $pool = [];
        foreach (self::conceptMcqCatalog() as $pack) {
            $hit = false;
            foreach ($pack['keywords'] as $kw) {
                if ($kw !== '' && str_contains($hay, strtolower($kw))) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                continue;
            }
            $list = $pack[$bankLevel] ?? [];
            if ($list === [] && $bankLevel !== 'K1') {
                $list = $pack['K1'] ?? [];
            }
            foreach ($list as $q) {
                if (is_array($q) && !empty($q['stem'])) {
                    $pool[] = $q;
                }
            }
        }
        if ($pool === []) {
            return null;
        }
        $q = $pool[$index % count($pool)];
        return [
            'stem' => (string)$q['stem'],
            'options' => [
                'A' => (string)$q['A'],
                'B' => (string)$q['B'],
                'C' => (string)$q['C'],
                'D' => (string)$q['D'],
            ],
            'correct_answer' => strtoupper((string)$q['ans']),
            'explanation' => (string)($q['why'] ?? 'Correct option matches the syllabus concept.'),
        ];
    }

    /**
     * Concept packs matched by topic/context keywords (works across courses dynamically).
     *
     * @return list<array<string,mixed>>
     */
    private static function conceptMcqCatalog(): array
    {
        return [
            [
                'keywords' => ['html', 'hyperlink', 'heading', 'paragraph', 'element', 'attribute', 'document structure', '<a>', 'image', 'web page'],
                'K1' => [
                    ['stem' => 'Which HTML element is used to create a hyperlink?', 'A' => '<img>', 'B' => '<a>', 'C' => '<p>', 'D' => '<table>', 'ans' => 'B', 'why' => 'The anchor element <a> creates hyperlinks.'],
                    ['stem' => 'In HTML, what do attributes provide for an element?', 'A' => 'Extra information or configuration for the element', 'B' => 'A replacement for the web server', 'C' => 'Encrypted network traffic by default', 'D' => 'A database primary key', 'ans' => 'A', 'why' => 'Attributes configure elements (e.g., href, src, alt).'],
                    ['stem' => 'Which HTML tags are typically used for section headings?', 'A' => '<h1> to <h6>', 'B' => '<http> to <https>', 'C' => '<sql> to <db>', 'D' => '<css> to <js>', 'ans' => 'A', 'why' => 'Heading levels are marked with h1–h6.'],
                    ['stem' => 'Which tag is commonly used to display an image on a web page?', 'A' => '<a>', 'B' => '<p>', 'C' => '<img>', 'D' => '<title>', 'ans' => 'C', 'why' => '<img> embeds images.'],
                ],
                'K2' => [
                    ['stem' => 'Why must a well-formed HTML document follow a clear document structure?', 'A' => 'So browsers can correctly interpret and present content', 'B' => 'So the operating system can schedule CPU processes', 'C' => 'So matrices can be inverted faster', 'D' => 'So SQL joins become unnecessary', 'ans' => 'A', 'why' => 'Structure (html/head/body and elements) helps browsers render pages correctly.'],
                    ['stem' => 'How do HTML attributes help when inserting an image?', 'A' => 'They specify source and alternative text such as src and alt', 'B' => 'They encrypt the image using HTTPS automatically', 'C' => 'They convert the image into a primary key', 'D' => 'They replace the need for a domain name', 'ans' => 'A', 'why' => 'src points to the file; alt describes it for accessibility.'],
                ],
                'K3' => [
                    ['stem' => 'You need a clickable logo that opens the home page. Which approach is appropriate?', 'A' => 'Wrap an <img> inside an <a href="..."> element', 'B' => 'Use only a <p> tag with no link', 'C' => 'Store the logo as a foreign key', 'D' => 'Replace HTML with a determinant calculation', 'ans' => 'A', 'why' => 'Combine <a> and <img> for a linked logo.'],
                ],
            ],
            [
                'keywords' => ['http', 'https', 'protocol', 'url', 'domain', 'browser', 'web server', 'client-server', 'client server', 'www', 'internet', 'request', 'response'],
                'K1' => [
                    ['stem' => 'Which component typically receives an HTTP request from a browser and returns the requested resource?', 'A' => 'Web server', 'B' => 'HTML heading tag', 'C' => 'CSS color value', 'D' => 'Keyboard interrupt', 'ans' => 'A', 'why' => 'Web servers handle HTTP requests and responses.'],
                    ['stem' => 'What does URL stand for in web technologies?', 'A' => 'Uniform Resource Locator', 'B' => 'Universal Routing Logic', 'C' => 'User Record Lock', 'D' => 'Unary Relational Link', 'ans' => 'A', 'why' => 'URL means Uniform Resource Locator.'],
                    ['stem' => 'In a client-server web model, which role does the web browser usually play?', 'A' => 'Client', 'B' => 'Database engine', 'C' => 'DNS root authority only', 'D' => 'Compiler', 'ans' => 'A', 'why' => 'Browsers act as clients requesting resources.'],
                    ['stem' => 'Which protocol is the encrypted variant commonly used for secure web communication?', 'A' => 'FTP', 'B' => 'HTTP', 'C' => 'HTTPS', 'D' => 'SMTP only', 'ans' => 'C', 'why' => 'HTTPS adds TLS/SSL encryption over HTTP.'],
                ],
                'K2' => [
                    ['stem' => 'Why is HTTPS preferred over HTTP when transmitting login credentials?', 'A' => 'HTTPS provides encrypted communication between client and server', 'B' => 'HTTPS removes the need for a web server', 'C' => 'HTTPS converts HTML into CSS', 'D' => 'HTTPS prevents all browser errors', 'ans' => 'A', 'why' => 'Encryption protects credentials in transit.'],
                    ['stem' => 'How does client-server architecture help web applications?', 'A' => 'Clients request services; servers process requests and return responses', 'B' => 'Every browser becomes a relational database', 'C' => 'HTML tags replace network protocols', 'D' => 'Domain names are no longer required', 'ans' => 'A', 'why' => 'It separates requestors (clients) from providers (servers).'],
                ],
                'K3' => [
                    ['stem' => 'A user enters a domain name in the browser address bar. What happens next at a high level?', 'A' => 'The name is resolved and an HTTP/HTTPS request is sent to the appropriate server', 'B' => 'The browser invents an HTML primary key', 'C' => 'The OS deletes the CSS file', 'D' => 'The matrix inverse is computed first', 'ans' => 'A', 'why' => 'DNS resolution + HTTP(S) request is the normal flow.'],
                ],
            ],
            [
                'keywords' => ['matrix', 'matrices', 'determinant', 'inverse', 'rank', 'calculus', 'limit', 'continuity', 'derivative', 'differentiation', 'partial derivative'],
                'K1' => [
                    ['stem' => 'For a square matrix A, when does A⁻¹ exist?', 'A' => 'When det(A) ≠ 0', 'B' => 'When det(A) = 0', 'C' => 'Only when A has no elements', 'D' => 'Only when A is a row vector', 'ans' => 'A', 'why' => 'A square matrix is invertible iff its determinant is non-zero.'],
                    ['stem' => 'What does the rank of a matrix represent?', 'A' => 'The maximum number of linearly independent rows or columns', 'B' => 'The number of HTML attributes', 'C' => 'The HTTP status code', 'D' => 'The primary key count in SQL', 'ans' => 'A', 'why' => 'Rank measures linear independence.'],
                    ['stem' => 'The derivative of a function measures which idea?', 'A' => 'Instantaneous rate of change', 'B' => 'Database cardinality', 'C' => 'Browser cache size', 'D' => 'Process priority', 'ans' => 'A', 'why' => 'Differentiation gives instantaneous rate of change.'],
                    ['stem' => 'A limit of f(x) as x approaches a describes:', 'A' => 'The value f approaches near a (if it exists)', 'B' => 'The foreign key of a table', 'C' => 'The HTTPS certificate vendor', 'D' => 'The CSS selector list', 'ans' => 'A', 'why' => 'Limits describe approaching behavior.'],
                ],
                'K2' => [
                    ['stem' => 'Why is the determinant important when checking whether a square matrix has an inverse?', 'A' => 'A non-zero determinant indicates the matrix is invertible', 'B' => 'A determinant always encrypts the matrix', 'C' => 'A determinant converts the matrix into HTML', 'D' => 'A determinant is used only for sorting arrays', 'ans' => 'A', 'why' => 'Invertibility of a square matrix is equivalent to det ≠ 0.'],
                    ['stem' => 'How are continuity and limits related for a function at a point a?', 'A' => 'f is continuous at a if the limit exists and equals f(a)', 'B' => 'Continuity means the determinant is zero', 'C' => 'Limits replace partial derivatives permanently', 'D' => 'Continuity is defined only for databases', 'ans' => 'A', 'why' => 'Continuity requires limit = function value at the point.'],
                    ['stem' => 'What does a partial derivative represent for a multivariable function?', 'A' => 'The rate of change with respect to one variable while others are held fixed', 'B' => 'The HTTP status of a request', 'C' => 'The primary key of a relation', 'D' => 'The number of HTML headings', 'ans' => 'A', 'why' => 'Partial derivatives isolate change in one independent variable.'],
                    ['stem' => 'Why is matrix rank useful when studying linear systems?', 'A' => 'It indicates the number of independent equations/variables relationships', 'B' => 'It encrypts the coefficient matrix', 'C' => 'It renames the matrix as a web server', 'D' => 'It deletes dependent HTML tags', 'ans' => 'A', 'why' => 'Rank reflects independent row/column structure relevant to solvability.'],
                    ['stem' => 'Which statement correctly describes differentiation?', 'A' => 'It finds the instantaneous rate of change of a function', 'B' => 'It stores records in a relational table', 'C' => 'It styles hyperlinks in a browser', 'D' => 'It schedules CPU processes', 'ans' => 'A', 'why' => 'Differentiation measures instantaneous rate of change.'],
                ],
                'K3' => [
                    ['stem' => 'To decide if a 2×2 matrix [[a,b],[c,d]] is invertible, which computation should you perform first?', 'A' => 'Compute ad − bc and check whether it is non-zero', 'B' => 'Convert the matrix into an <img> tag', 'C' => 'Create a primary key for each entry', 'D' => 'Send an HTTP POST to the determinant', 'ans' => 'A', 'why' => 'For 2×2 matrices, det = ad − bc.'],
                ],
            ],
            [
                'keywords' => ['database', 'dbms', 'sql', 'primary key', 'foreign key', 'relational', 'normalization', 'er model', 'entity', 'table', 'record', 'tuple', 'attribute'],
                'K1' => [
                    ['stem' => 'Which key uniquely identifies a record in a relational table?', 'A' => 'Foreign key', 'B' => 'Primary key', 'C' => 'Composite attribute only', 'D' => 'View', 'ans' => 'B', 'why' => 'Primary keys uniquely identify rows.'],
                    ['stem' => 'A foreign key in a relational database is used to:', 'A' => 'Reference the primary key of another (or the same) table', 'B' => 'Encrypt HTTPS traffic', 'C' => 'Compute matrix rank', 'D' => 'Style HTML paragraphs', 'ans' => 'A', 'why' => 'Foreign keys enforce referential relationships.'],
                    ['stem' => 'In the ER model, an entity typically represents:', 'A' => 'A real-world object about which data is stored', 'B' => 'An HTTP status line', 'C' => 'A CSS class name', 'D' => 'A CPU scheduling quantum', 'ans' => 'A', 'why' => 'Entities model real-world objects/concepts.'],
                    ['stem' => 'SQL is primarily used to:', 'A' => 'Query and manage data in relational databases', 'B' => 'Render CSS animations', 'C' => 'Differentiate polynomials', 'D' => 'Schedule operating-system interrupts', 'ans' => 'A', 'why' => 'SQL is the standard language for relational data.'],
                    ['stem' => 'Which statement about the relational model is correct?', 'A' => 'Data is organized in tables (relations) with rows and columns', 'B' => 'Data must be stored only as HTML documents', 'C' => 'Every table must avoid keys', 'D' => 'Relations are the same as HTTP cookies', 'ans' => 'A', 'why' => 'Relational model uses tabular relations.'],
                ],
                'K2' => [
                    ['stem' => 'Why is normalization applied to relational schemas?', 'A' => 'To reduce redundancy and improve data integrity', 'B' => 'To convert SQL into HTML forms', 'C' => 'To increase duplicate storage intentionally', 'D' => 'To replace primary keys with images', 'ans' => 'A', 'why' => 'Normalization organizes data to reduce redundancy/anomalies.'],
                    ['stem' => 'How does a primary key differ from a foreign key?', 'A' => 'A primary key uniquely identifies rows; a foreign key references a key elsewhere', 'B' => 'A foreign key is always unique and never references another table', 'C' => 'They are identical in every database', 'D' => 'Primary keys exist only in NoSQL browsers', 'ans' => 'A', 'why' => 'PK identifies; FK references.'],
                ],
                'K3' => [
                    ['stem' => 'You must link Orders to Customers so each order belongs to one customer. Which design is appropriate?', 'A' => 'Add a customer_id foreign key in Orders referencing Customers', 'B' => 'Store the entire customer table inside every HTML <p> tag', 'C' => 'Delete all primary keys', 'D' => 'Use determinants instead of keys', 'ans' => 'A', 'why' => 'FK relationships connect related tables.'],
                ],
            ],
            [
                'keywords' => ['operating system', 'process', 'cpu scheduling', 'thread', 'memory management', 'deadlock', 'system call', 'kernel'],
                'K1' => [
                    ['stem' => 'Which of the following best describes a process in an operating system?', 'A' => 'A program in execution', 'B' => 'An HTML attribute', 'C' => 'A matrix determinant', 'D' => 'A CSS selector', 'ans' => 'A', 'why' => 'A process is a program in execution.'],
                    ['stem' => 'CPU scheduling is primarily concerned with:', 'A' => 'Deciding which ready process runs next on the CPU', 'B' => 'Encrypting HTTPS certificates', 'C' => 'Normalizing SQL tables', 'D' => 'Rendering <img> tags', 'ans' => 'A', 'why' => 'Schedulers allocate CPU time among ready processes.'],
                ],
                'K2' => [
                    ['stem' => 'Why do operating systems provide system calls?', 'A' => 'To let user programs request kernel services safely', 'B' => 'To replace relational databases', 'C' => 'To compute partial derivatives', 'D' => 'To style hyperlinks', 'ans' => 'A', 'why' => 'System calls are the controlled interface to kernel services.'],
                ],
                'K3' => [
                    ['stem' => 'If multiple processes are ready, which OS component chooses the next process to run?', 'A' => 'CPU scheduler / dispatcher logic', 'B' => 'HTML parser', 'C' => 'SQL foreign key', 'D' => 'Matrix inverter', 'ans' => 'A', 'why' => 'Scheduling selects the next runnable process.'],
                ],
            ],
            [
                'keywords' => ['java', 'class', 'object', 'inheritance', 'polymorphism', 'oop', 'method', 'constructor'],
                'K1' => [
                    ['stem' => 'In Java, a class is best described as:', 'A' => 'A blueprint for creating objects', 'B' => 'An HTTP response header', 'C' => 'A database view only', 'D' => 'A CSS media query', 'ans' => 'A', 'why' => 'Classes define structure/behavior for objects.'],
                    ['stem' => 'Inheritance in object-oriented programming allows a class to:', 'A' => 'Acquire properties/behaviors from another class', 'B' => 'Encrypt packets automatically', 'C' => 'Compute determinants', 'D' => 'Replace the web server', 'ans' => 'A', 'why' => 'Inheritance reuses and extends base-class features.'],
                ],
                'K2' => [
                    ['stem' => 'Why is encapsulation useful in Java?', 'A' => 'It hides internal details and exposes a controlled interface', 'B' => 'It forces every field to be public', 'C' => 'It removes the need for methods', 'D' => 'It converts objects into HTML tables', 'ans' => 'A', 'why' => 'Encapsulation protects state and clarifies APIs.'],
                ],
                'K3' => [
                    ['stem' => 'You need multiple classes to share common behavior with specific overrides. Which OOP feature fits best?', 'A' => 'Inheritance with method overriding (polymorphism)', 'B' => 'Primary keys only', 'C' => 'HTTP redirect codes', 'D' => 'Matrix transposition', 'ans' => 'A', 'why' => 'Inheritance + overriding enables polymorphic behavior.'],
                ],
            ],
        ];
    }

    /**
     * @param array{stem:string,options:array<string,string>,correct_answer:string,explanation:string} $pack
     * @return array{stem:string,options:array<string,string>,correct_answer:string,explanation:string}
     */
    private static function rotateMcqOptions(array $pack, int $index): array
    {
        $options = $pack['options'];
        $correctKey = strtoupper((string)$pack['correct_answer']);
        if (!isset($options[$correctKey])) {
            $correctKey = 'A';
        }
        $correctText = $options[$correctKey];
        $others = [];
        foreach (['A', 'B', 'C', 'D'] as $k) {
            if ($k === $correctKey) {
                continue;
            }
            $others[] = $options[$k] ?? '';
        }
        while (count($others) < 3) {
            $others[] = 'None of the syllabus-relevant options above';
        }
        $keys = ['A', 'B', 'C', 'D'];
        $target = $keys[$index % 4];
        $rotated = [];
        $oi = 0;
        foreach ($keys as $k) {
            if ($k === $target) {
                $rotated[$k] = $correctText;
            } else {
                $rotated[$k] = $others[$oi++];
            }
        }
        return [
            'stem' => (string)$pack['stem'],
            'options' => $rotated,
            'correct_answer' => $target,
            'explanation' => (string)$pack['explanation'],
        ];
    }

    private static function demoOpenStem(
        string $subject,
        string $topic,
        string $type,
        string $klevel,
        int $unit,
        int $index,
        string $context = ''
    ): string {
        if ($type === 'short') {
            return match ($klevel) {
                'K1' => "List two precise facts a student must recall about \"{$topic}\" from Unit {$unit}.",
                'K2' => "In your own words, explain \"{$topic}\" and give one syllabus-based example.",
                'K3' => "Describe the steps to apply \"{$topic}\" to a basic Unit {$unit} problem.",
                'K4' => "Compare \"{$topic}\" with a closely related Unit {$unit} concept and state one key difference.",
                'K5' => "Justify why mastering \"{$topic}\" matters for Unit {$unit} learning outcomes.",
                default => "Propose a short teaching activity that checks understanding of \"{$topic}\".",
            };
        }
        return match ($klevel) {
            'K1' => "Describe \"{$topic}\" using accurate terminology from the Unit {$unit} syllabus. Include definitions and key terms.",
            'K2' => "Explain \"{$topic}\" with examples drawn from the course context, showing how the idea is understood (not merely named).",
            'K3' => "Present a short problem that requires applying \"{$topic}\". Show clear working/steps.",
            'K4' => "Analyze strengths and limitations of using \"{$topic}\" for Unit {$unit} problems.",
            'K5' => "Evaluate the importance of \"{$topic}\" against Unit {$unit} outcomes, with reasoned criteria.",
            default => "Design a solution approach that integrates \"{$topic}\" with other Unit {$unit} ideas from the syllabus.",
        };
    }

    private static function demoModelAnswer(
        string $subject,
        string $topic,
        string $type,
        string $klevel,
        string $context = ''
    ): string {
        $hint = '';
        if (trim($context) !== '') {
            $hint = ' Use terminology consistent with the provided syllabus excerpt.';
        }
        if ($type === 'short') {
            return "State accurate points about {$topic} at Bloom {$klevel}; avoid restating the topic name as the whole answer.{$hint}";
        }
        return "Introduction → explanation/working on {$topic} → short conclusion. Demonstrate {$klevel} thinking with syllabus-relevant detail.{$hint}";
    }
}
