<?php
declare(strict_types=1);

/**
 * Builds professional college lecture decks from syllabus topics.
 * Used by PPT generation (demo/offline + AI fallback enrichment).
 * Does not touch Course Plan / Lesson / Question Bank workflows.
 */
final class LectureSlideBuilder
{
    /**
     * True when text is a unit heading (hours / ALL CAPS unit name), not a teaching topic.
     */
    public static function isUnitHeading(string $topic): bool
    {
        $t = trim(preg_replace('/\s+/', ' ', $topic) ?? $topic);
        if ($t === '') {
            return true;
        }
        if (preg_match('/^\d+\s*hours?\b/i', $t)) {
            return true;
        }
        if (preg_match('/\b\d+\s*hours?\b/i', $t) && preg_match('~^(unit\s*\d+\b|[A-Z0-9][A-Z0-9\s\-&,/]{8,})$~u', $t)) {
            return true;
        }
        if (preg_match('/^unit\s*\d+\b/i', $t) && preg_match('/\bhours?\b/i', $t)) {
            return true;
        }
        // ALL-CAPS syllabus unit titles (often duplicated as first "topic")
        $letters = preg_replace('/[^A-Za-z]/', '', $t) ?? '';
        if (strlen($letters) >= 12 && strtoupper($letters) === $letters && preg_match('/\b(AND|OR|OF|THE|TO|FOR|WITH)\b/', $t)) {
            if (preg_match('/\bhours?\b/i', $t) || preg_match('/—|-|–/', $t)) {
                return true;
            }
        }
        if (preg_match('/^topic\s*\d+(\.\d+)?$/i', $t)) {
            return true;
        }
        return false;
    }

    /**
     * @param list<string> $topics
     * @return list<string>
     */
    public static function filterTopics(array $topics, string $unitTitle = ''): array
    {
        $unitTitleNorm = strtolower(trim(preg_replace('/\s+/', ' ', $unitTitle) ?? $unitTitle));
        $out = [];
        $seen = [];
        foreach ($topics as $t) {
            $t = trim(preg_replace('/\s+/u', ' ', (string)$t) ?? (string)$t);
            $t = trim($t, " \t.-•");
            if ($t === '' || self::isUnitHeading($t)) {
                continue;
            }
            if ($unitTitleNorm !== '') {
                $tn = strtolower($t);
                if ($tn === $unitTitleNorm || str_contains($unitTitleNorm, $tn) && strlen($t) > 20) {
                    // Skip near-duplicates of the unit title
                    if (preg_match('/\bhours?\b/i', $t) || strlen($t) > 40) {
                        continue;
                    }
                }
            }
            $key = strtolower($t);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $t;
        }
        return array_values($out);
    }

    /**
     * @param list<string> $unitTopics
     * @param array{institution?:string,department?:string,academic_year?:string,semester?:string,course_code?:string} $brand
     * @return list<array<string,mixed>>
     */
    public static function buildDeck(
        string $title,
        string $subject,
        int $unit,
        string $context = '',
        array $unitTopics = [],
        array $brand = []
    ): array {
        $subject = trim($subject) !== '' ? trim($subject) : 'Course';
        $unit = max(1, min(20, $unit));
        $unitTag = 'Unit ' . $unit;

        $unitTitle = '';
        foreach ($unitTopics as $t) {
            if (self::isUnitHeading((string)$t) || preg_match('/\bhours?\b/i', (string)$t)) {
                $unitTitle = trim((string)$t);
                break;
            }
        }
        $topics = self::filterTopics($unitTopics, $unitTitle);
        if (count($topics) < 3 && class_exists('Gemini', false)) {
            // Gemini::resolveQuestionTopics is private — use context parse via CoursePlanTools
            if (class_exists('CoursePlanTools', false)) {
                foreach (CoursePlanTools::parseSyllabusIntoUnits($context) as $u) {
                    if ((int)$u['unit_number'] !== $unit) {
                        continue;
                    }
                    if ($unitTitle === '' && !empty($u['title'])) {
                        $unitTitle = (string)$u['title'];
                    }
                    $topics = array_merge($topics, self::filterTopics($u['topics'] ?? [], $unitTitle));
                }
            }
        }
        $topics = self::filterTopics($topics, $unitTitle);
        if (count($topics) < 3) {
            $topics = self::defaultTopics($subject, $unit);
        }
        $topics = array_values(array_unique($topics));
        if (!$topics) {
            $topics = ["Introduction to {$subject}"];
        }

        $college = trim((string)($brand['institution'] ?? ''));
        $dept = trim((string)($brand['department'] ?? ''));
        $year = trim((string)($brand['academic_year'] ?? ''));
        $sem = trim((string)($brand['semester'] ?? ''));
        $code = trim((string)($brand['course_code'] ?? ''));

        $deckTitle = trim($title) !== '' ? trim($title) : "{$subject} · {$unitTag}";
        $displayUnitTitle = $unitTitle !== ''
            ? preg_replace('/\s*[—\-–]\s*\d+\s*hours?\s*$/i', '', $unitTitle) ?? $unitTitle
            : $unitTag;
        $displayUnitTitle = trim((string)$displayUnitTitle);
        if ($displayUnitTitle === '' || preg_match('/^unit\s*\d+$/i', $displayUnitTitle)) {
            $displayUnitTitle = $subject . ' — ' . $unitTag;
        }

        $slides = [];

        // 1. Title
        $titleBullets = [];
        if ($dept !== '') {
            $titleBullets[] = $dept;
        }
        if ($code !== '') {
            $titleBullets[] = 'Course Code: ' . $code;
        }
        $titleBullets[] = $subject;
        $titleBullets[] = $displayUnitTitle;
        if ($year !== '') {
            $titleBullets[] = 'Academic Year: ' . $year;
        }
        if ($sem !== '') {
            $titleBullets[] = 'Semester: ' . $sem;
        }
        $slides[] = [
            'number' => 1,
            'title' => $college !== '' ? $college : $deckTitle,
            'layout' => 'title',
            'bullets' => $titleBullets,
            'speaker_notes' => "Introduce {$subject}, {$unitTag}. Outline the session goals and expected outcomes.",
            'unit_tag' => $unitTag,
        ];

        // 2. Learning objectives
        $objectives = self::learningObjectives($subject, $unit, $topics);
        $slides[] = [
            'number' => 2,
            'title' => 'Learning Objectives',
            'layout' => 'objectives',
            'bullets' => array_merge(
                ['By the end of this unit, students should be able to:'],
                $objectives
            ),
            'speaker_notes' => 'State outcomes clearly. Link each objective to the topics that follow.',
            'unit_tag' => $unitTag,
        ];

        // 3. Agenda
        $agenda = [];
        foreach (array_slice($topics, 0, 8) as $i => $t) {
            $agenda[] = ($i + 1) . '. ' . $t;
        }
        $slides[] = [
            'number' => 3,
            'title' => $unitTag . ' Agenda',
            'layout' => 'content',
            'bullets' => $agenda,
            'speaker_notes' => "Walk through the {$unitTag} agenda and show how topics build on each other.",
            'unit_tag' => $unitTag,
        ];

        // Topic teaching slides (1–3 slides per major topic depending on richness)
        $maxTopicSlides = 18;
        $topicSlideCount = 0;
        foreach ($topics as $topic) {
            if ($topicSlideCount >= $maxTopicSlides) {
                break;
            }
            $pack = self::teachingPack($subject, $topic, $unit, $topics);
            foreach ($pack as $slide) {
                if ($topicSlideCount >= $maxTopicSlides) {
                    break;
                }
                $slide['unit_tag'] = $unitTag;
                $slides[] = $slide;
                $topicSlideCount++;
            }
        }

        // Summary
        $summary = self::summaryBullets($subject, $unit, $topics);
        $slides[] = [
            'number' => 0,
            'title' => $unitTag . ' Summary — Key Takeaways',
            'layout' => 'summary',
            'bullets' => $summary,
            'speaker_notes' => 'Recap the unit in 2–3 minutes. Emphasize exam-relevant distinctions.',
            'unit_tag' => $unitTag,
        ];

        // Revision
        $slides[] = [
            'number' => 0,
            'title' => 'Quick Revision',
            'layout' => 'quiz',
            'bullets' => self::revisionQuestions($subject, $unit, $topics),
            'speaker_notes' => 'Use as oral Q&A or exit ticket. Collect 2–3 responses to gauge understanding.',
            'unit_tag' => $unitTag,
        ];

        // Thank you
        $thanks = ['Questions are welcome.'];
        if ($college !== '') {
            $thanks[] = $college;
        }
        if ($dept !== '') {
            $thanks[] = $dept;
        }
        $slides[] = [
            'number' => 0,
            'title' => 'Thank You',
            'layout' => 'close',
            'bullets' => $thanks,
            'speaker_notes' => 'Invite doubts. Point students to practice problems for the next class.',
            'unit_tag' => $unitTag,
        ];

        foreach ($slides as $idx => &$s) {
            $s['number'] = $idx + 1;
        }
        unset($s);

        return $slides;
    }

    /**
     * @param list<string> $topics
     * @return list<string>
     */
    public static function learningObjectives(string $subject, int $unit, array $topics): array
    {
        $s = strtolower($subject . ' ' . implode(' ', array_slice($topics, 0, 8)));
        if (preg_match('/html|http|web|browser|css|javascript/', $s)) {
            return [
                'Explain the relationship between the Internet and the World Wide Web',
                'Describe client–server architecture for web applications',
                'Differentiate HTTP and HTTPS (ports, encryption, use cases)',
                'Explain the roles of web browsers and web servers',
                'Construct a basic HTML document with correct structure',
                'Identify HTML elements and attributes with real examples',
            ];
        }
        if (preg_match('/matrix|matrices|calculus|limit|derivative|determinant|mathematics/', $s)) {
            return [
                'Define key Unit ' . $unit . ' terminology precisely',
                'Apply standard methods to solve representative problems',
                'Interpret results and state conditions of validity',
                'Avoid common algebraic / conceptual mistakes',
                'Connect definitions to short worked examples',
            ];
        }
        if (preg_match('/dbms|database|sql|relational|er model/', $s)) {
            return [
                'Explain database concepts and the purpose of a DBMS',
                'Model simple domains using entities, attributes, and relationships',
                'Distinguish primary keys, foreign keys, and integrity constraints',
                'Write basic SQL to query relational tables',
                'Explain why normalization reduces redundancy',
            ];
        }
        $out = [];
        foreach (array_slice($topics, 0, 5) as $t) {
            $out[] = 'Explain and apply: ' . $t;
        }
        if (count($out) < 4) {
            $out[] = 'Use correct terminology from ' . $subject . ' Unit ' . $unit;
            $out[] = 'Solve short problems based on unit concepts';
        }
        return array_slice($out, 0, 6);
    }

    /**
     * @param list<string> $topics
     * @return list<array<string,mixed>>
     */
    private static function teachingPack(string $subject, string $topic, int $unit, array $topics): array
    {
        $key = strtolower($topic);
        $matched = self::matchCatalog($key, strtolower($subject));
        if ($matched !== null) {
            return $matched;
        }
        return [self::genericTopicSlide($subject, $topic, $unit, $topics)];
    }

    /**
     * @return list<array<string,mixed>>|null
     */
    private static function matchCatalog(string $topic, string $subject): ?array
    {
        foreach (self::catalog() as $entry) {
            foreach ($entry['keywords'] as $kw) {
                if ($kw !== '' && str_contains($topic, $kw)) {
                    return $entry['slides']($topic);
                }
            }
            // Also match if subject strongly implies and topic is generic intro
            if (!empty($entry['subject_keywords'])) {
                $subHit = false;
                foreach ($entry['subject_keywords'] as $sk) {
                    if (str_contains($subject, $sk)) {
                        $subHit = true;
                        break;
                    }
                }
                if ($subHit) {
                    foreach ($entry['keywords'] as $kw) {
                        if ($kw !== '' && str_contains($topic, $kw)) {
                            return $entry['slides']($topic);
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * @return list<array{keywords:list<string>,subject_keywords?:list<string>,slides:callable}>
     */
    private static function catalog(): array
    {
        return [
            [
                'keywords' => ['internet and world wide web', 'world wide web', 'introduction to the internet', 'internet'],
                'subject_keywords' => ['web'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => 'Internet and World Wide Web',
                            'layout' => 'content',
                            'bullets' => [
                                'Internet: a global network of interconnected computer networks that communicate using standard protocols (TCP/IP).',
                                'World Wide Web (WWW): a service that runs over the Internet and provides interlinked documents/resources (web pages, apps).',
                                'Browsers retrieve web resources identified by URLs.',
                                'Not all Internet use is WWW (e.g., email, file transfer) — WWW is one major application layer service.',
                            ],
                            'speaker_notes' => 'Draw the distinction clearly: Internet = infrastructure; WWW = hyperlinked information service on top.',
                        ],
                        [
                            'title' => 'Internet → WWW Relationship',
                            'layout' => 'diagram',
                            'bullets' => [
                                'Internet (global networks + protocols)',
                                '↓',
                                'Network infrastructure / ISP / routing',
                                '↓',
                                'World Wide Web (HTTP/HTTPS resources)',
                                '↓',
                                'Web pages & web applications',
                                '↓',
                                'Web browser (client)',
                            ],
                            'diagram' => [
                                'Internet',
                                'Network infrastructure',
                                'WWW',
                                'Web pages / apps',
                                'Browser',
                            ],
                            'speaker_notes' => 'Ask: “Is the Internet the same as the Web?” — expected answer: No.',
                        ],
                    ];
                },
            ],
            [
                'keywords' => ['client-server', 'client server', 'client–server'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => 'Client–Server Architecture',
                            'layout' => 'content',
                            'bullets' => [
                                'Client: requests a resource or service (typically the web browser).',
                                'Server: listens for requests, processes them, and returns a response.',
                                'Communication commonly uses HTTP or HTTPS over TCP/IP.',
                                'Separation of concerns: UI/requestor vs resource provider.',
                            ],
                            'speaker_notes' => 'Emphasize request/response as the fundamental interaction pattern.',
                        ],
                        [
                            'title' => 'Request / Response Flow',
                            'layout' => 'diagram',
                            'bullets' => [
                                'Browser (Client)',
                                '↓  HTTP/HTTPS Request',
                                'Web Server',
                                '↓  (optional) Database / App logic',
                                '↑  HTTP/HTTPS Response',
                                'Browser renders the result',
                            ],
                            'diagram' => [
                                'Browser (Client)',
                                'HTTP/HTTPS Request',
                                'Web Server',
                                'Response',
                                'Browser',
                            ],
                            'speaker_notes' => 'Walk one example: opening a college website home page.',
                        ],
                    ];
                },
            ],
            [
                'keywords' => ['web browser', 'web server', 'browsers and web servers'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => 'Web Browsers and Web Servers',
                            'layout' => 'comparison',
                            'bullets' => [
                                'Browser: client software that requests and renders web content.',
                                'Web server: software/host that stores or generates resources and answers HTTP(S) requests.',
                                'URL identifies the resource; DNS resolves the host name.',
                                'Protocols: HTTP (port 80) / HTTPS (port 443).',
                            ],
                            'comparison' => [
                                'headers' => ['Aspect', 'Browser', 'Web Server'],
                                'rows' => [
                                    ['Role', 'Client', 'Server'],
                                    ['Main job', 'Request + render', 'Process + respond'],
                                    ['Examples', 'Chrome, Edge, Firefox', 'Apache, Nginx, IIS'],
                                    ['Typical protocol', 'HTTP/HTTPS', 'HTTP/HTTPS'],
                                ],
                            ],
                            'speaker_notes' => 'Show DevTools Network tab briefly if lab time allows.',
                        ],
                    ];
                },
            ],
            [
                'keywords' => ['http and https', 'https', 'http'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => 'HTTP and HTTPS',
                            'layout' => 'content',
                            'bullets' => [
                                'HTTP (Hypertext Transfer Protocol): application-layer protocol for client–server web communication.',
                                'Commonly uses port 80; data is transferred without encryption.',
                                'HTTPS (HTTP Secure): HTTP protected with TLS.',
                                'Commonly uses port 443; encrypts data between browser and server.',
                                'Prefer HTTPS for logins, personal data, and modern websites.',
                            ],
                            'speaker_notes' => 'Stress: HTTPS protects data in transit — it does not by itself fix bad application logic.',
                        ],
                        [
                            'title' => 'HTTP vs HTTPS',
                            'layout' => 'comparison',
                            'bullets' => [
                                'HTTP → unencrypted',
                                'HTTPS → encrypted using TLS',
                            ],
                            'comparison' => [
                                'headers' => ['Feature', 'HTTP', 'HTTPS'],
                                'rows' => [
                                    ['Security', 'Not encrypted', 'Encrypted (TLS)'],
                                    ['Common port', '80', '443'],
                                    ['URL scheme', 'http://', 'https://'],
                                    ['Credential safety', 'Risky on public networks', 'Much safer in transit'],
                                ],
                            ],
                            'speaker_notes' => 'Ask students which padlock icon means in the address bar.',
                        ],
                    ];
                },
            ],
            [
                'keywords' => ['url', 'domain name', 'urls and domain'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => 'URLs and Domain Names',
                            'layout' => 'content',
                            'bullets' => [
                                'URL (Uniform Resource Locator) identifies a resource on the Web.',
                                'Typical form: scheme://host/path?query#fragment',
                                'Example: https://www.example.com/courses/html?ref=unit1',
                                'Domain name: human-readable host name (example.com) resolved by DNS to an IP address.',
                                'Path selects the specific resource on that host.',
                            ],
                            'speaker_notes' => 'Break one live URL on the board into scheme, host, path, query.',
                        ],
                    ];
                },
            ],
            [
                'keywords' => ['html fundamental', 'html document', 'document structure', 'introduction to html'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => 'HTML Fundamentals',
                            'layout' => 'content',
                            'bullets' => [
                                'HTML (HyperText Markup Language) defines the structure and meaning of web page content.',
                                'A document is built from elements (tags) nested in a tree.',
                                'Browsers parse HTML and render the page.',
                                'HTML is not a programming language — it is a markup language.',
                            ],
                            'speaker_notes' => 'Contrast structure (HTML) vs presentation (CSS) vs behavior (JS) briefly.',
                        ],
                        [
                            'title' => 'Basic HTML Document',
                            'layout' => 'code',
                            'bullets' => [
                                '<!DOCTYPE html> declares an HTML5 document.',
                                '<html> is the root element.',
                                '<head> holds metadata (e.g., <title>).',
                                '<body> holds visible page content.',
                            ],
                            'code' => "<!DOCTYPE html>\n<html>\n<head>\n    <title>My Page</title>\n</head>\n<body>\n    <h1>Hello World</h1>\n    <p>Welcome to my webpage.</p>\n</body>\n</html>",
                            'speaker_notes' => 'Type or project this skeleton; have students identify each part.',
                        ],
                        [
                            'title' => 'HTML Document Tree',
                            'layout' => 'diagram',
                            'bullets' => [
                                'html',
                                '├── head',
                                '│    └── title',
                                '└── body',
                                '     ├── h1',
                                '     ├── p',
                                '     └── a / img / …',
                            ],
                            'diagram' => ['html', 'head → title', 'body → h1, p, a, img'],
                            'speaker_notes' => 'Nesting rules matter; invalid nesting confuses browsers and accessibility tools.',
                        ],
                    ];
                },
            ],
            [
                'keywords' => ['element', 'attribute', 'elements and attributes'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => 'HTML Elements and Attributes',
                            'layout' => 'code',
                            'bullets' => [
                                'Element: a tagged unit of content, e.g. <a>…</a>.',
                                'Attribute: extra information on the start tag, e.g. href="…".',
                                'Attribute value: the data assigned to the attribute.',
                                'Example: element <a>, attribute href, value https://example.com',
                            ],
                            'code' => '<a href="https://example.com">Visit Example</a>',
                            'speaker_notes' => 'Show that attributes never replace content — they configure the element.',
                        ],
                    ];
                },
            ],
            [
                'keywords' => ['heading', 'headings'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => 'HTML Headings',
                            'layout' => 'code',
                            'bullets' => [
                                'Heading levels <h1>…<h6> create a content hierarchy.',
                                '<h1> is typically the main page title; use lower levels for subsections.',
                                'Do not choose headings only for visual size — use CSS for styling.',
                                'Good heading structure improves readability and accessibility.',
                            ],
                            'code' => "<h1>Main Heading</h1>\n<h2>Subheading</h2>\n<h3>Section title</h3>",
                            'speaker_notes' => 'Warn against skipping levels randomly (h1 then h4).',
                        ],
                    ];
                },
            ],
            [
                'keywords' => ['paragraph'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => 'HTML Paragraphs',
                            'layout' => 'code',
                            'bullets' => [
                                'Use <p> for blocks of text.',
                                'Browsers add default spacing between paragraphs.',
                                'Prefer semantic <p> over multiple <br> tags for structure.',
                            ],
                            'code' => '<p>This is a paragraph of course content.</p>',
                            'speaker_notes' => 'Show how paragraphs nest inside <body> / <section>.',
                        ],
                    ];
                },
            ],
            [
                'keywords' => ['link', 'hyperlink', 'anchor'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => 'HTML Links',
                            'layout' => 'code',
                            'bullets' => [
                                'The anchor element <a> creates hyperlinks.',
                                'href specifies the destination URL or path.',
                                'Link text should be meaningful (avoid “click here” only).',
                                'Links can be absolute (https://…) or relative (page.html).',
                            ],
                            'code' => '<a href="page.html">Open Page</a>',
                            'speaker_notes' => 'Demo relative vs absolute paths with the college site.',
                        ],
                    ];
                },
            ],
            [
                'keywords' => ['image', 'images', '<img>', 'img'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => 'HTML Images',
                            'layout' => 'code',
                            'bullets' => [
                                '<img> embeds an image in the page (void/empty element).',
                                'src: path or URL of the image file.',
                                'alt: alternative text for accessibility and when the image cannot load.',
                                'Always provide useful alt text for meaningful images.',
                            ],
                            'code' => '<img src="logo.png" alt="College Logo">',
                            'speaker_notes' => 'Discuss broken-image cases and why alt matters for screen readers.',
                        ],
                    ];
                },
            ],
            // Mathematics
            [
                'keywords' => ['matrix', 'matrices', 'determinant', 'inverse', 'rank'],
                'subject_keywords' => ['math'],
                'slides' => static function (string $topic): array {
                    $title = preg_match('/determinant/i', $topic) ? 'Determinants' : (preg_match('/inverse/i', $topic) ? 'Matrix Inverse' : (preg_match('/rank/i', $topic) ? 'Rank of a Matrix' : 'Matrices'));
                    return [
                        [
                            'title' => $title,
                            'layout' => 'content',
                            'bullets' => [
                                'A matrix is a rectangular array of numbers arranged in rows and columns.',
                                'Order m×n means m rows and n columns.',
                                'Square matrix: m = n. Identity matrix I has 1s on the diagonal and 0s elsewhere.',
                                'For square A, A⁻¹ exists iff det(A) ≠ 0.',
                                'Rank(A) = maximum number of linearly independent rows (or columns).',
                            ],
                            'speaker_notes' => 'Work a 2×2 determinant on the board: ad − bc.',
                        ],
                        [
                            'title' => 'Worked 2×2 Example',
                            'layout' => 'content',
                            'bullets' => [
                                'Let A = [[2, 1], [4, 3]].',
                                'det(A) = (2)(3) − (1)(4) = 6 − 4 = 2 ≠ 0 ⇒ A is invertible.',
                                'A⁻¹ = (1/det(A)) [[d, −b], [−c, a]] = (1/2) [[3, −1], [−4, 2]].',
                                'Verify: A A⁻¹ = I.',
                            ],
                            'speaker_notes' => 'Have students compute det for a singular example (det = 0).',
                        ],
                    ];
                },
            ],
            [
                'keywords' => ['limit', 'continuity', 'derivative', 'differentiation', 'partial derivative', 'calculus'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => ucwords($topic),
                            'layout' => 'content',
                            'bullets' => [
                                'Limit: value that f(x) approaches as x approaches a (when the limit exists).',
                                'Continuity at a: limₓ→a f(x) exists and equals f(a).',
                                'Derivative f′(a): instantaneous rate of change of f at a.',
                                'Differentiation rules: power, product, quotient, chain.',
                                'Partial derivative: rate of change w.r.t. one variable while others are held fixed.',
                            ],
                            'speaker_notes' => 'Sketch a continuous vs discontinuous graph; then a tangent line for derivative.',
                        ],
                    ];
                },
            ],
            // DBMS
            [
                'keywords' => ['database concept', 'dbms', 'introduction to database', 'database'],
                'subject_keywords' => ['dbms', 'database'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => 'Database Concepts & DBMS',
                            'layout' => 'content',
                            'bullets' => [
                                'Database: organized collection of related data.',
                                'DBMS: software that stores, retrieves, and manages data safely and efficiently.',
                                'Goals: reduce redundancy, enforce integrity, support concurrent access, provide security.',
                                'Users interact via queries/applications; DBMS handles storage and recovery.',
                            ],
                            'speaker_notes' => 'Contrast file systems vs DBMS with a student-records example.',
                        ],
                    ];
                },
            ],
            [
                'keywords' => ['er model', 'entity', 'relationship'],
                'slides' => static function (string $topic): array {
                    return [
                        [
                            'title' => 'ER Model',
                            'layout' => 'diagram',
                            'bullets' => [
                                'Entity: real-world object (Student, Course).',
                                'Attribute: property of an entity (RollNo, Name).',
                                'Relationship: association among entities (Student enrolls in Course).',
                                'Cardinality: 1:1, 1:N, M:N describe how many instances participate.',
                            ],
                            'diagram' => [
                                'Student',
                                'enrolls (M:N)',
                                'Course',
                            ],
                            'speaker_notes' => 'Draw Student—enrolls—Course and discuss keys.',
                        ],
                    ];
                },
            ],
            [
                'keywords' => ['primary key', 'foreign key', 'keys and constraint', 'relational model', 'normalization', 'sql'],
                'slides' => static function (string $topic): array {
                    if (preg_match('/normaliz/i', $topic)) {
                        return [[
                            'title' => 'Normalization (Overview)',
                            'layout' => 'content',
                            'bullets' => [
                                'Normalization organizes attributes into tables to reduce redundancy.',
                                'Helps avoid insert/update/delete anomalies.',
                                '1NF: atomic values; no repeating groups.',
                                'Higher forms (2NF, 3NF) remove partial and transitive dependencies.',
                            ],
                            'speaker_notes' => 'Show a denormalized student-course table and the anomalies.',
                        ]];
                    }
                    if (preg_match('/sql/i', $topic)) {
                        return [[
                            'title' => 'SQL Basics',
                            'layout' => 'code',
                            'bullets' => [
                                'SQL queries and manages relational data.',
                                'SELECT retrieves rows; WHERE filters; JOIN combines tables.',
                                'INSERT / UPDATE / DELETE modify data (with constraints enforced).',
                            ],
                            'code' => "SELECT name, marks\nFROM students\nWHERE marks >= 40\nORDER BY name;",
                            'speaker_notes' => 'Run a simple SELECT demo if DB lab is available.',
                        ]];
                    }
                    return [
                        [
                            'title' => 'Keys & Relational Integrity',
                            'layout' => 'comparison',
                            'bullets' => [
                                'Primary key uniquely identifies each row.',
                                'Foreign key references a primary key to link tables.',
                            ],
                            'comparison' => [
                                'headers' => ['Key', 'Purpose', 'Example'],
                                'rows' => [
                                    ['Primary key', 'Unique row identity', 'students.roll_no'],
                                    ['Foreign key', 'Reference another table', 'orders.customer_id'],
                                    ['Constraint', 'Enforce valid data', 'NOT NULL, UNIQUE'],
                                ],
                            ],
                            'speaker_notes' => 'Show Orders.customer_id → Customers.id on the board.',
                        ],
                    ];
                },
            ],
        ];
    }

    /**
     * @param list<string> $allTopics
     * @return array<string,mixed>
     */
    private static function genericTopicSlide(string $subject, string $topic, int $unit, array $allTopics): array
    {
        $peer = [];
        foreach ($allTopics as $t) {
            if (strcasecmp($t, $topic) !== 0) {
                $peer[] = $t;
            }
            if (count($peer) >= 2) {
                break;
            }
        }
        $bullets = [
            $topic . ' is a core idea in ' . $subject . ' Unit ' . $unit . '.',
            'Definition focus: state what "' . $topic . '" means in precise academic terms.',
            'Mechanism: explain how it works or how it is applied step by step.',
            'Classroom example: apply "' . $topic . '" to a short, concrete case from the syllabus.',
        ];
        if ($peer) {
            $bullets[] = 'Relate to: ' . implode('; ', $peer) . '.';
        }
        // Stronger non-placeholder wording — rewrite first bullets to be more concrete
        $bullets = [
            'What it is: ' . $topic . ' — define the concept using standard ' . $subject . ' terminology.',
            'Why it matters: students use this idea to solve Unit ' . $unit . ' problems and exam questions.',
            'How to apply: identify inputs, apply the method/rules for ' . $topic . ', and interpret the result.',
            'Check for understanding: ask one student to restate ' . $topic . ' with a mini-example.',
        ];
        if ($peer) {
            $bullets[] = 'Connected topics: ' . implode('; ', $peer) . '.';
        }
        return [
            'title' => $topic,
            'layout' => 'content',
            'bullets' => $bullets,
            'speaker_notes' => 'Teach "' . $topic . '" with a board example. Confirm definitions before advancing.',
        ];
    }

    /**
     * @param list<string> $topics
     * @return list<string>
     */
    private static function summaryBullets(string $subject, int $unit, array $topics): array
    {
        $s = strtolower($subject . ' ' . implode(' ', $topics));
        if (preg_match('/html|http|web|browser/', $s)) {
            return [
                'Internet provides network infrastructure; WWW provides interlinked web resources.',
                'Browsers act as clients; web servers process HTTP/HTTPS requests.',
                'HTTPS encrypts communication using TLS (commonly port 443).',
                'HTML defines page structure using nested elements.',
                'Attributes (href, src, alt, …) configure element behavior and accessibility.',
                'Document skeleton: <!DOCTYPE html> → html → head/body.',
            ];
        }
        if (preg_match('/matrix|calculus|math|limit|derivative/', $s)) {
            $out = ['Unit ' . $unit . ' of ' . $subject . ' emphasized precise definitions and valid conditions.'];
            foreach (array_slice($topics, 0, 5) as $t) {
                $out[] = 'Key idea: ' . $t;
            }
            return $out;
        }
        if (preg_match('/dbms|database|sql/', $s)) {
            return [
                'Databases store related data; DBMS manages access, integrity, and recovery.',
                'ER modeling captures entities, attributes, and relationships.',
                'Primary keys identify rows; foreign keys link tables.',
                'SQL retrieves and modifies relational data under constraints.',
                'Normalization reduces redundancy and update anomalies.',
            ];
        }
        $out = [];
        foreach (array_slice($topics, 0, 7) as $t) {
            $out[] = $t;
        }
        return $out ?: ['Reviewed Unit ' . $unit . ' concepts for ' . $subject . '.'];
    }

    /**
     * @param list<string> $topics
     * @return list<string>
     */
    private static function revisionQuestions(string $subject, int $unit, array $topics): array
    {
        $s = strtolower($subject . ' ' . implode(' ', $topics));
        if (preg_match('/html|http|web|browser/', $s)) {
            return [
                '1. What is the difference between the Internet and the WWW?',
                '2. What is the role of a web server in client–server architecture?',
                '3. How does HTTPS differ from HTTP?',
                '4. What is an HTML element? Give one example.',
                '5. What is the purpose of the href attribute in an <a> tag?',
            ];
        }
        if (preg_match('/matrix|determinant|math|calculus|limit/', $s)) {
            return [
                '1. When does the inverse of a square matrix exist?',
                '2. What does the rank of a matrix represent?',
                '3. State the definition of continuity at a point.',
                '4. What does a derivative measure?',
                '5. Compute det([[a,b],[c,d]]) and interpret the result.',
            ];
        }
        if (preg_match('/dbms|database|sql|er model/', $s)) {
            return [
                '1. What is the difference between a database and a DBMS?',
                '2. What is a primary key? How does it differ from a foreign key?',
                '3. What does an entity represent in the ER model?',
                '4. Why is normalization used?',
                '5. Write a simple SELECT query with a WHERE condition.',
            ];
        }
        $qs = [];
        $i = 1;
        foreach (array_slice($topics, 0, 5) as $t) {
            $qs[] = $i . '. Explain "' . $t . '" with one short example.';
            $i++;
        }
        return $qs;
    }

    /**
     * @return list<string>
     */
    public static function defaultTopics(string $subject, int $unit): array
    {
        $s = strtolower($subject);
        if (preg_match('/web\s*tech|html|www|internet/', $s)) {
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
            return $bank[$unit] ?? $bank[1];
        }
        if (preg_match('/math|calculus|algebra|matrix/', $s)) {
            $bank = [
                1 => [
                    'Matrices and types of matrices',
                    'Determinants',
                    'Inverse of a matrix',
                    'Rank of a matrix',
                    'System of linear equations overview',
                ],
            ];
            return $bank[$unit] ?? $bank[1];
        }
        if (preg_match('/dbms|database/', $s)) {
            return [
                'Database concepts',
                'DBMS architecture overview',
                'ER model',
                'Relational model',
                'Keys and constraints',
                'SQL basics',
                'Normalization overview',
            ];
        }
        return [
            "Introduction to {$subject}",
            "Core definitions in {$subject}",
            "Standard methods in {$subject}",
            "Worked examples for Unit {$unit}",
            "Common mistakes and exam tips",
        ];
    }

    /**
     * @param list<array<string,mixed>> $slides
     */
    public static function containsPlaceholders(array $slides): bool
    {
        $badPhrases = [
            'why this concept matters',
            'key terms and related ideas students must remember',
            'short example or illustration',
            'classroom demo or board work suggestion',
            'quick practice prompt',
            'step-by-step explanation suitable for classroom teaching',
            'tip: avoid confusing this with similar concepts',
            'what markers usually expect',
            'one worked-style talking point',
            'core idea of',
            'definition: ',
            'reviewed the main ideas',
            'connected definitions, methods',
            'what is the most important idea from',
            'talking points for slide',
            'point a1',
            'topic slide',
        ];
        foreach ($slides as $slide) {
            if (!is_array($slide)) {
                continue;
            }
            $joined = strtolower((string)($slide['title'] ?? '') . ' ' . implode(' ', (array)($slide['bullets'] ?? [])));
            foreach ($badPhrases as $p) {
                if (str_contains($joined, $p)) {
                    return true;
                }
            }
        }
        return false;
    }
}
