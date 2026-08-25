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
                $pack = self::demoMcqForTopic($subject, $topic, $nextTopic, $prevTopic, $altTopic, $klevel, $unit, $i);
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
                    'stem' => self::demoOpenStem($subject, $topic, $type, $klevel, $unit, $i),
                    'correct_answer' => self::demoModelAnswer($subject, $topic, $type, $klevel),
                    'explanation' => "Assesses {$klevel} outcomes for {$subject}, Unit {$unit}: {$topic}.",
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
     * @return list<array{number:int,title:string,bullets:list<string>,speaker_notes:string,unit_tag:string}>
     */
    public static function demoPresentation(
        string $title,
        string $subject,
        int $unit,
        string $context = '',
        array $unitTopics = [],
        int $slideCount = 12
    ): array {
        $subject = trim($subject) !== '' ? trim($subject) : 'Course';
        $unit = max(1, min(20, $unit));
        $slideCount = max(8, min(16, $slideCount));
        $topics = self::resolveQuestionTopics($subject, $unit, $context, $unitTopics);
        $unitTag = 'Unit ' . $unit;
        $deckTitle = trim($title) !== '' ? trim($title) : "{$subject} · {$unitTag}";

        $slides = [];
        $slides[] = [
            'number' => 1,
            'title' => $deckTitle,
            'bullets' => [
                "Subject: {$subject}",
                "Focus: {$unitTag}",
                'Academic lecture presentation',
                'Includes learning outcomes, concepts, examples, and summary',
            ],
            'speaker_notes' => "Welcome students. Introduce {$subject} {$unitTag} and outline the session goals.",
            'unit_tag' => $unitTag,
        ];
        $slides[] = [
            'number' => 2,
            'title' => 'Learning Outcomes',
            'bullets' => [
                "Recall key terminology used in {$subject} {$unitTag}",
                "Explain the core concepts covered in this unit",
                "Apply basic techniques from {$unitTag} to simple problems",
                'Identify common mistakes and how to avoid them',
            ],
            'speaker_notes' => 'State outcomes clearly so students know what success looks like by the end of the lecture.',
            'unit_tag' => $unitTag,
        ];
        $overviewBullets = [];
        foreach (array_slice($topics, 0, 6) as $i => $t) {
            $overviewBullets[] = ($i + 1) . '. ' . $t;
        }
        $slides[] = [
            'number' => 3,
            'title' => "{$unitTag} Overview",
            'bullets' => $overviewBullets,
            'speaker_notes' => "Walk through the {$unitTag} agenda. Emphasize connections between topics.",
            'unit_tag' => $unitTag,
        ];

        $topicSlidesNeeded = max(1, $slideCount - 5); // title, outcomes, overview, summary, quiz
        for ($i = 0; $i < $topicSlidesNeeded; $i++) {
            $topic = $topics[$i % count($topics)];
            $next = $topics[($i + 1) % count($topics)];
            $slides[] = [
                'number' => count($slides) + 1,
                'title' => $topic,
                'bullets' => self::demoSlideBullets($subject, $topic, $next, $unit, $i),
                'speaker_notes' => self::demoSlideNotes($subject, $topic, $unit, $i),
                'unit_tag' => $unitTag,
            ];
        }

        $slides[] = [
            'number' => count($slides) + 1,
            'title' => "{$unitTag} Summary",
            'bullets' => [
                "Reviewed the main ideas of {$subject} {$unitTag}",
                'Connected definitions, methods, and simple applications',
                'Highlighted common errors and exam-focused points',
                'Prepare practice problems before the next class',
            ],
            'speaker_notes' => 'Recap in 2–3 minutes. Ask students which concept needs more practice.',
            'unit_tag' => $unitTag,
        ];
        $slides[] = [
            'number' => count($slides) + 1,
            'title' => 'Check Your Understanding',
            'bullets' => [
                "What is the most important idea from {$topics[0]}?",
                "How is {$topics[min(1, count($topics)-1)]} used in a simple {$subject} task?",
                "Name one common mistake students make in {$unitTag}",
                'Write one short example or definition before leaving class',
            ],
            'speaker_notes' => 'Use as exit ticket or oral Q&A. Collect 2–3 responses to assess understanding.',
            'unit_tag' => $unitTag,
        ];

        // Normalize numbering if we overshot/undershot.
        foreach ($slides as $idx => &$slide) {
            $slide['number'] = $idx + 1;
        }
        unset($slide);

        return array_slice($slides, 0, $slideCount);
    }

    /**
     * @return list<string>
     */
    private static function demoSlideBullets(string $subject, string $topic, string $nextTopic, int $unit, int $index): array
    {
        $variant = $index % 4;
        return match ($variant) {
            0 => [
                "Definition: {$topic} in {$subject}",
                'Why this concept matters in Unit ' . $unit,
                'Key terms and related ideas students must remember',
                "Connection to the next idea: {$nextTopic}",
            ],
            1 => [
                "Core idea of {$topic}",
                'Step-by-step explanation suitable for classroom teaching',
                'Short example or illustration from Unit ' . $unit,
                'Tip: avoid confusing this with similar concepts',
            ],
            2 => [
                "Where {$topic} is used in {$subject}",
                'Important rules / properties to highlight',
                'Classroom demo or board work suggestion',
                'Quick practice prompt for students',
            ],
            default => [
                "Exam focus: common questions on {$topic}",
                'What markers usually expect in answers',
                'One worked-style talking point for lecture delivery',
                'Bridge to revision and upcoming topics',
            ],
        };
    }

    private static function demoSlideNotes(string $subject, string $topic, int $unit, int $index): string
    {
        return match ($index % 3) {
            0 => "Explain {$topic} slowly with a board example. Check prior knowledge before moving deeper into Unit {$unit} of {$subject}.",
            1 => "Ask one student to restate {$topic} in their own words. Clarify misconceptions immediately.",
            default => "Link {$topic} to a short class activity or quiz item. Keep examples aligned to Unit {$unit}.",
        };
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
            if ($t !== '') {
                $topics[] = $t;
            }
        }
        foreach (self::extractTopicsFromContext($context, $unit) as $t) {
            $topics[] = $t;
        }
        $topics = array_values(array_unique(array_filter($topics, static fn($t) => strlen($t) > 2)));
        if (count($topics) >= 4) {
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
                if (preg_match('/unit\s*\d+\s*[:\-–.]\s*(.+)$/i', $line, $tm)) {
                    $title = trim($tm[1]);
                    if ($inUnit && $title !== '') {
                        $topics[] = self::cleanTopicText($title);
                    }
                }
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
                        if (strlen($part) > 2) {
                            $topics[] = $part;
                        }
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
                1 => ['Database concepts', 'ER model', 'Relational model', 'Keys and constraints', 'SQL basics', 'Normalization overview'],
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
     */
    private static function demoMcqForTopic(
        string $subject,
        string $topic,
        string $nextTopic,
        string $prevTopic,
        string $altTopic,
        string $klevel,
        int $unit,
        int $index
    ): array {
        $stem = match ($klevel) {
            'K1' => match ($index % 4) {
                0 => "In {$subject} (Unit {$unit}), which of the following best defines \"{$topic}\"?",
                1 => "Which statement about \"{$topic}\" in {$subject} is correct?",
                2 => "\"{$topic}\" in {$subject} is primarily associated with which concept?",
                default => "Which of the following is a key term related to \"{$topic}\" in Unit {$unit} of {$subject}?",
            },
            'K2' => match ($index % 3) {
                0 => "Which option correctly explains the role of \"{$topic}\" in {$subject}?",
                1 => "How is \"{$topic}\" best distinguished from \"{$nextTopic}\" in {$subject}?",
                default => "Which interpretation of \"{$topic}\" in Unit {$unit} of {$subject} is most accurate?",
            },
            'K3' => match ($index % 3) {
                0 => "A student is implementing a task involving \"{$topic}\" in {$subject}. Which approach is most appropriate?",
                1 => "While solving a Unit {$unit} problem in {$subject}, when should \"{$topic}\" be applied?",
                default => "Which practical use-case correctly applies \"{$topic}\" in {$subject}?",
            },
            'K4' => "Which analysis best compares \"{$topic}\" with \"{$nextTopic}\" for a Unit {$unit} problem in {$subject}?",
            'K5' => "Which evaluation criterion is most suitable for judging the effectiveness of \"{$topic}\" in {$subject}?",
            default => "Which design/decision best synthesizes \"{$topic}\" with related Unit {$unit} concepts in {$subject}?",
        };

        $correct = match ($klevel) {
            'K1' => "It is a fundamental Unit {$unit} concept in {$subject} covering {$topic}.",
            'K2' => "It clarifies meaning and relationships of {$topic} within {$subject}.",
            'K3' => "Apply {$topic} step-by-step to obtain the required Unit {$unit} result in {$subject}.",
            'K4' => "Break the problem into parts and examine how {$topic} influences the outcome versus {$nextTopic}.",
            'K5' => "Judge {$topic} against correctness, efficiency, and learning outcomes for {$subject}.",
            default => "Combine {$topic} with complementary Unit {$unit} ideas to form a coherent {$subject} solution.",
        };

        $wrong1 = "It is unrelated to {$subject} and belongs only to {$nextTopic}.";
        $wrong2 = "It replaces all other Unit {$unit} topics such as {$prevTopic} and {$altTopic}.";
        $wrong3 = "It is used only for documentation and never for solving {$subject} problems.";

        $options = [
            'A' => $correct,
            'B' => $wrong1,
            'C' => $wrong2,
            'D' => $wrong3,
        ];
        // Rotate correct option so answers are not always A.
        $keys = ['A', 'B', 'C', 'D'];
        $rotate = $index % 4;
        $values = array_values($options);
        $rotated = [];
        foreach ($keys as $ki => $key) {
            $rotated[$key] = $values[($ki + $rotate) % 4];
        }
        $correctKey = $keys[(4 - $rotate) % 4];

        return [
            'stem' => $stem,
            'options' => $rotated,
            'correct_answer' => $correctKey,
            'explanation' => "Correct option {$correctKey} aligns with {$klevel} expectation for {$topic} in {$subject} Unit {$unit}.",
        ];
    }

    private static function demoOpenStem(
        string $subject,
        string $topic,
        string $type,
        string $klevel,
        int $unit,
        int $index
    ): string {
        if ($type === 'short') {
            return match ($klevel) {
                'K1' => "Define \"{$topic}\" as used in Unit {$unit} of {$subject}.",
                'K2' => "Explain \"{$topic}\" with one suitable example from {$subject}.",
                'K3' => "Write the steps to apply \"{$topic}\" for a basic Unit {$unit} task in {$subject}.",
                'K4' => "Differentiate \"{$topic}\" from a closely related Unit {$unit} concept in {$subject}.",
                'K5' => "Justify why \"{$topic}\" is important in Unit {$unit} of {$subject}.",
                default => "Propose a short improvement related to teaching or using \"{$topic}\" in {$subject}.",
            };
        }
        // long / essay / case
        return match ($klevel) {
            'K1' => "Describe in detail the concept of \"{$topic}\" in Unit {$unit} of {$subject}, including key terms and definitions.",
            'K2' => "Discuss \"{$topic}\" in {$subject} with examples, and explain how it connects to other Unit {$unit} ideas.",
            'K3' => "With a suitable problem statement, demonstrate how \"{$topic}\" is applied in Unit {$unit} of {$subject}. Show clear steps.",
            'K4' => "Analyze the strengths and limitations of using \"{$topic}\" for Unit {$unit} problems in {$subject}.",
            'K5' => "Critically evaluate the role of \"{$topic}\" in achieving Unit {$unit} learning outcomes for {$subject}.",
            default => "Design a comprehensive solution approach that integrates \"{$topic}\" with other Unit {$unit} topics in {$subject}.",
        };
    }

    private static function demoModelAnswer(string $subject, string $topic, string $type, string $klevel): string
    {
        $base = "A model answer should address {$topic} in {$subject} at Bloom level {$klevel}, using correct terminology and unit-relevant examples.";
        if ($type === 'short') {
            return $base . ' Keep the response concise (about 4–8 lines).';
        }
        return $base . ' Include introduction, explanation/working, and a short conclusion.';
    }
}
