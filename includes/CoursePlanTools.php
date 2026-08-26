<?php
declare(strict_types=1);

/**
 * Helpers for New Course Plan enhancements (extract, Bloom balance, version diff, export).
 * Reuses existing course_plans / course_plan_versions / plan_reviews architecture.
 */
final class CoursePlanTools
{
    public const TEMPLATES = ['standard', 'naac', 'nba', 'aicte'];
    public const MAX_UPLOAD_BYTES = 8 * 1024 * 1024; // 8 MB

    public static function normalizeTemplate(mixed $raw): string
    {
        $t = strtolower(trim((string)$raw));
        return in_array($t, self::TEMPLATES, true) ? $t : 'standard';
    }

    public static function templateLabel(string $template): string
    {
        return match (self::normalizeTemplate($template)) {
            'naac' => 'NAAC',
            'nba' => 'NBA',
            'aicte' => 'AICTE',
            default => 'Standard',
        };
    }

    /** Extra prompt guidance for accreditation templates (no fake attainment). */
    public static function templatePromptHint(string $template): string
    {
        return match (self::normalizeTemplate($template)) {
            'nba' => "Accreditation template: NBA (GAPC). Emphasize Course Outcomes (COs), Bloom's K-levels per unit, "
                . 'explicit CO wording in outcomes, and map assessments to COs where possible. '
                . 'Do NOT invent attainment percentages or fabricated PO/PSO scores. '
                . 'If PO/PLO mapping is included, use qualitative mapping only (e.g. CO1 supports PO1) without numeric attainment.',
            'naac' => 'Accreditation template: NAAC. Emphasize clear learning outcomes, teaching-learning methods, '
                . 'assessment alignment, and resources suitable for NAAC evidence. Do NOT invent SSR metrics or attainment %.',
            'aicte' => 'Accreditation template: AICTE. Emphasize contact hours, practical/lab balance where relevant, '
                . 'industry relevance, and structured unit hours. Do NOT invent compliance percentages.',
            default => 'Accreditation template: Standard OBE course plan for Indian higher education.',
        };
    }

    /**
     * Extract plain text from uploaded PDF or DOCX. Does not store the file.
     *
     * @throws RuntimeException
     */
    public static function extractUploadedSyllabus(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $code = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            $msg = match ($code) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large for the server upload limit.',
                UPLOAD_ERR_PARTIAL => 'Upload was incomplete. Try again.',
                UPLOAD_ERR_NO_FILE => 'No file selected.',
                default => 'Upload failed. Please try again.',
            };
            throw new RuntimeException($msg);
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $name = (string)($file['name'] ?? 'file');
        $size = (int)($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid upload.');
        }
        if ($size < 1) {
            throw new RuntimeException('Empty file.');
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('File too large. Maximum size is 8 MB.');
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = '';
        if (class_exists('finfo')) {
            try {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string)$finfo->file($tmp);
            } catch (Throwable $e) {
                $mime = '';
            }
        }

        if ($ext === 'pdf' || str_contains($mime, 'pdf')) {
            if ($ext !== 'pdf') {
                throw new RuntimeException('PDF uploads must use a .pdf extension.');
            }
            return self::extractPdfText($tmp);
        }
        if (in_array($ext, ['docx'], true)
            || str_contains($mime, 'wordprocessingml')
            || str_contains($mime, 'officedocument.wordprocessingml')) {
            if ($ext !== 'docx') {
                throw new RuntimeException('Word uploads must use a .docx extension (not .doc).');
            }
            return self::extractDocxText($tmp);
        }
        throw new RuntimeException('Unsupported file type. Upload a PDF or DOCX only.');
    }

    public static function extractPdfText(string $path): string
    {
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            throw new RuntimeException('Could not read the PDF file.');
        }
        if (!str_starts_with($raw, '%PDF')) {
            throw new RuntimeException('File does not look like a valid PDF.');
        }

        // Cap work on huge files to avoid memory/time fatals.
        if (strlen($raw) > 12 * 1024 * 1024) {
            $raw = substr($raw, 0, 12 * 1024 * 1024);
        }

        $chunks = [];

        // Prefer text inside content streams (BT ... ET).
        if (preg_match_all('/BT(.*?)ET/s', $raw, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match_all('/\((?:\\\\.|[^\\\\)]){1,500}\)\s*Tj/i', $block, $m)) {
                    foreach ($m[0] as $frag) {
                        if (preg_match('/\(((?:\\\\.|[^\\\\)])*)\)/', $frag, $mm)) {
                            $chunks[] = self::pdfUnescape($mm[1]);
                        }
                    }
                }
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $arrs)) {
                    foreach ($arrs[1] as $arr) {
                        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/', $arr, $parts)) {
                            foreach ($parts[0] as $lit) {
                                $chunks[] = self::pdfUnescape(substr($lit, 1, -1));
                            }
                        }
                    }
                }
            }
        }

        // Fallback: any parenthesized literals that look like text.
        if (count($chunks) < 5 && preg_match_all('/\((?:\\\\.|[^\\\\)]){3,200}\)/', $raw, $m)) {
            foreach ($m[0] as $lit) {
                $s = self::pdfUnescape(substr($lit, 1, -1));
                if (strlen($s) >= 3 && preg_match('/[A-Za-z0-9]/', $s)) {
                    $chunks[] = $s;
                }
            }
        }

        $text = trim(preg_replace("/[ \t]+/", ' ', implode(' ', $chunks)) ?? '');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = self::sanitizeExtractedText($text);
        if (strlen($text) < 40) {
            throw new RuntimeException(
                'Could not extract enough text from this PDF. It may be scanned/image-only — paste the syllabus text manually.'
            );
        }
        return $text;
    }

    private static function pdfUnescape(string $s): string
    {
        $s = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", "\r", "\t", '(', ')', '\\'], $s);
        $s = preg_replace('/\\\\[0-7]{1,3}/', '', $s) ?? $s;
        return trim($s);
    }

    public static function extractDocxText(string $path): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('DOCX extraction requires ZipArchive on the server. Enable php_zip in XAMPP, or paste syllabus text.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not read the DOCX file (corrupt or invalid).');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false || $xml === '') {
            throw new RuntimeException('DOCX has no document.xml content.');
        }
        $xml = preg_replace('/<w:tab\b[^>]*\/>/', "\t", $xml) ?? $xml;
        $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<\/w:tr>/', "\n", $xml) ?? $xml;
        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = trim($text);
        if (strlen($text) < 20) {
            throw new RuntimeException('Could not extract text from this DOCX. Paste the syllabus manually.');
        }
        return self::sanitizeExtractedText($text);
    }

    public static function sanitizeExtractedText(string $text): string
    {
        $text = str_replace("\0", '', $text);
        // Force valid UTF-8 (invalid bytes from PDFs break json_encode → empty HTTP body).
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            }
        }
        if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
            $text = function_exists('mb_convert_encoding')
                ? (string)mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1')
                : $text;
        }
        // Strip control chars without unicode-property regex on dirty bytes.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? $text;
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        if (strlen($text) > 200000) {
            $text = substr($text, 0, 200000);
        }
        return trim($text);
    }

    /**
     * Bloom K1–K6 % from stored distribution or unit bloom_k_level counts.
     *
     * @param array<string,mixed> $planRow
     * @param list<array<string,mixed>> $units
     * @return array{distribution:array<string,float>,lower_order_pct:float,warning:?string}
     */
    public static function bloomBalance(array $planRow, array $units = []): array
    {
        $dist = json_decode((string)($planRow['bloom_data'] ?? '{}'), true);
        if (!is_array($dist) || !$dist) {
            $planData = json_decode((string)($planRow['plan_data'] ?? '{}'), true) ?: [];
            $dist = is_array($planData['bloom_distribution'] ?? null) ? $planData['bloom_distribution'] : [];
        }

        $keys = ['K1', 'K2', 'K3', 'K4', 'K5', 'K6'];
        $out = array_fill_keys($keys, 0.0);
        $hasNumeric = false;
        foreach ($keys as $k) {
            if (isset($dist[$k]) && is_numeric($dist[$k])) {
                $out[$k] = (float)$dist[$k];
                $hasNumeric = true;
            }
        }

        if (!$hasNumeric || array_sum($out) <= 0) {
            $counts = array_fill_keys($keys, 0);
            $total = 0;
            $sourceUnits = $units;
            if (!$sourceUnits) {
                $planData = json_decode((string)($planRow['plan_data'] ?? '{}'), true) ?: [];
                $sourceUnits = is_array($planData['units'] ?? null) ? $planData['units'] : [];
            }
            foreach ($sourceUnits as $u) {
                $level = strtoupper(trim((string)($u['bloom_k_level'] ?? '')));
                if (isset($counts[$level])) {
                    $counts[$level]++;
                    $total++;
                }
            }
            if ($total > 0) {
                foreach ($keys as $k) {
                    $out[$k] = round(($counts[$k] * 100) / $total, 1);
                }
            }
        } else {
            $sum = array_sum($out);
            if ($sum > 0 && abs($sum - 100) > 1.5) {
                foreach ($keys as $k) {
                    $out[$k] = round(($out[$k] / $sum) * 100, 1);
                }
            }
        }

        $lower = $out['K1'] + $out['K2'];
        $warning = null;
        if ($lower >= 55) {
            $warning = 'This course plan has a high concentration of lower-order Bloom levels (K1/K2). Consider adding more higher-order learning activities.';
        }

        return [
            'distribution' => $out,
            'lower_order_pct' => round($lower, 1),
            'warning' => $warning,
        ];
    }

    /**
     * Diff two plan snapshots (from course_plan_versions.snapshot or plan_data).
     *
     * @return array{added_topics:list<string>,removed_topics:list<string>,changed_units:list<array<string,string>>,changed_outcomes:list<string>,changed_bloom:list<string>,changed_hours:list<string>,changed_clo_plo:list<string>}
     */
    public static function diffSnapshots(array $old, array $new): array
    {
        $oldUnits = self::indexUnits($old['units'] ?? []);
        $newUnits = self::indexUnits($new['units'] ?? []);

        $oldTopics = self::flattenTopics($oldUnits);
        $newTopics = self::flattenTopics($newUnits);

        $added = array_values(array_diff($newTopics, $oldTopics));
        $removed = array_values(array_diff($oldTopics, $newTopics));

        $changedUnits = [];
        $changedOutcomes = [];
        $changedBloom = [];
        $changedHours = [];
        $changedCloPlo = [];

        $oldLo = self::normalizeList($old['learning_outcomes'] ?? []);
        $newLo = self::normalizeList($new['learning_outcomes'] ?? []);
        if ($oldLo !== $newLo) {
            $loAdded = array_values(array_diff($newLo, $oldLo));
            $loRemoved = array_values(array_diff($oldLo, $newLo));
            foreach (array_slice($loAdded, 0, 12) as $item) {
                $changedCloPlo[] = 'CLO/LO added: ' . $item;
            }
            foreach (array_slice($loRemoved, 0, 12) as $item) {
                $changedCloPlo[] = 'CLO/LO removed: ' . $item;
            }
            if (!$loAdded && !$loRemoved) {
                $changedCloPlo[] = 'Course learning outcomes reordered or updated';
            }
        }
        foreach (['clo_po_mapping', 'co_po_mapping', 'plo_mapping', 'co_plo_mapping'] as $mapKey) {
            $om = self::normalizeList($old[$mapKey] ?? []);
            $nm = self::normalizeList($new[$mapKey] ?? []);
            if ($om !== $nm) {
                $changedCloPlo[] = strtoupper(str_replace('_', ' ', $mapKey)) . ' changed';
            }
        }

        $allNums = array_unique(array_merge(array_keys($oldUnits), array_keys($newUnits)));
        sort($allNums);
        foreach ($allNums as $num) {
            $o = $oldUnits[$num] ?? null;
            $n = $newUnits[$num] ?? null;
            if (!$o && $n) {
                $changedUnits[] = [
                    'label' => 'Unit ' . $num,
                    'detail' => 'Added: ' . (string)($n['title'] ?? ''),
                ];
                continue;
            }
            if ($o && !$n) {
                $changedUnits[] = [
                    'label' => 'Unit ' . $num,
                    'detail' => 'Removed: ' . (string)($o['title'] ?? ''),
                ];
                continue;
            }
            if (!$o || !$n) {
                continue;
            }
            $ot = trim((string)($o['title'] ?? ''));
            $nt = trim((string)($n['title'] ?? ''));
            if ($ot !== $nt) {
                $changedUnits[] = [
                    'label' => 'Unit ' . $num,
                    'detail' => $ot . ' → ' . $nt,
                ];
            }
            $ob = strtoupper(trim((string)($o['bloom_k_level'] ?? '')));
            $nb = strtoupper(trim((string)($n['bloom_k_level'] ?? '')));
            if ($ob !== $nb) {
                $changedBloom[] = 'Unit ' . $num . ': ' . ($ob ?: '—') . ' → ' . ($nb ?: '—');
            }
            $oh = (string)($o['hours'] ?? '');
            $nh = (string)($n['hours'] ?? '');
            if ($oh !== $nh) {
                $changedHours[] = 'Unit ' . $num . ': ' . ($oh !== '' ? $oh : '—') . ' → ' . ($nh !== '' ? $nh : '—') . ' hours';
            }
            $oo = self::normalizeList($o['outcomes'] ?? []);
            $no = self::normalizeList($n['outcomes'] ?? []);
            if ($oo !== $no) {
                $changedOutcomes[] = 'Unit ' . $num . ' (' . ($nt ?: $ot) . '): outcomes updated';
            }
        }

        return [
            'added_topics' => array_slice($added, 0, 40),
            'removed_topics' => array_slice($removed, 0, 40),
            'changed_units' => array_slice($changedUnits, 0, 40),
            'changed_outcomes' => array_slice($changedOutcomes, 0, 40),
            'changed_bloom' => array_slice($changedBloom, 0, 40),
            'changed_hours' => array_slice($changedHours, 0, 40),
            'changed_clo_plo' => array_slice($changedCloPlo, 0, 40),
        ];
    }

    /** Ensure share_token columns exist (smallest compatible extension). */
    public static function ensureShareSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $cols = [];
        foreach (Database::fetchAll('SHOW COLUMNS FROM course_plans') as $c) {
            $cols[(string)$c['Field']] = true;
        }
        if (!isset($cols['share_token'])) {
            Database::query(
                "ALTER TABLE course_plans
                 ADD COLUMN share_token VARCHAR(64) NULL DEFAULT NULL
                 COMMENT 'Public read-only share token' AFTER meta"
            );
        }
        if (!isset($cols['share_enabled'])) {
            Database::query(
                "ALTER TABLE course_plans
                 ADD COLUMN share_enabled TINYINT(1) NOT NULL DEFAULT 0
                 COMMENT '1=public read-only link active' AFTER share_token"
            );
        }
        $idx = Database::fetchAll("SHOW INDEX FROM course_plans WHERE Key_name = 'uq_course_plans_share_token'");
        if (!$idx) {
            try {
                Database::query('ALTER TABLE course_plans ADD UNIQUE KEY uq_course_plans_share_token (share_token)');
            } catch (Throwable $e) {
                // Index may already exist under another name.
            }
        }
    }

    /**
     * Create/rotate a read-only share token for an approved plan owned by the user.
     *
     * @return array{token:string,url:string}
     */
    public static function enableShareLink(array $plan, array $user): array
    {
        self::ensureShareSchema();
        if ((int)($plan['institution_id'] ?? 0) !== (int)($user['institution_id'] ?? 0)) {
            throw new RuntimeException('Access denied.');
        }
        if ((int)($plan['professor_id'] ?? 0) !== (int)($user['id'] ?? 0)
            && !in_array((string)($user['role'] ?? ''), ['admin', 'superadmin'], true)) {
            throw new RuntimeException('Only the plan owner can share this plan.');
        }
        if ((string)($plan['status'] ?? '') !== 'approved') {
            throw new RuntimeException('Only approved plans can be shared as a read-only link.');
        }
        $token = bin2hex(random_bytes(32));
        Database::update('course_plans', [
            'share_token' => $token,
            'share_enabled' => 1,
        ], 'id = :id AND institution_id = :iid AND professor_id = :pid', [
            'id' => (int)$plan['id'],
            'iid' => (int)$plan['institution_id'],
            'pid' => (int)$plan['professor_id'],
        ]);
        return [
            'token' => $token,
            'url' => base_url('/share/plan.php?t=' . urlencode($token)),
        ];
    }

    public static function disableShareLink(array $plan, array $user): void
    {
        self::ensureShareSchema();
        if ((int)($plan['institution_id'] ?? 0) !== (int)($user['institution_id'] ?? 0)) {
            throw new RuntimeException('Access denied.');
        }
        if ((int)($plan['professor_id'] ?? 0) !== (int)($user['id'] ?? 0)
            && !in_array((string)($user['role'] ?? ''), ['admin', 'superadmin'], true)) {
            throw new RuntimeException('Only the plan owner can revoke sharing.');
        }
        Database::update('course_plans', [
            'share_enabled' => 0,
            'share_token' => null,
        ], 'id = :id AND institution_id = :iid AND professor_id = :pid', [
            'id' => (int)$plan['id'],
            'iid' => (int)$plan['institution_id'],
            'pid' => (int)$plan['professor_id'],
        ]);
    }

    /** Public read-only lookup — token + approved + enabled only. */
    public static function findPublicSharedPlan(string $token): ?array
    {
        self::ensureShareSchema();
        $token = trim($token);
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $plan = Database::fetch(
            'SELECT * FROM course_plans WHERE share_token = ? AND share_enabled = 1 AND status = "approved" LIMIT 1',
            [$token]
        );
        return $plan ?: null;
    }

    /**
     * Owned approved plans for bulk export (tenant-scoped).
     *
     * @param list<int> $planIds
     * @return list<array<string,mixed>>
     */
    public static function ownedApprovedPlans(array $user, array $planIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $planIds), static fn(int $id) => $id > 0)));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge(
            [(int)$user['id'], (int)$user['institution_id']],
            $ids
        );
        return Database::fetchAll(
            "SELECT * FROM course_plans
             WHERE professor_id = ? AND institution_id = ? AND status = 'approved' AND id IN ($in)
             ORDER BY subject_name ASC, id ASC",
            $params
        );
    }

    /**
     * Build accreditation package as a downloadable PDF (one file, all selected plans).
     *
     * @param list<array<string,mixed>> $plans
     * @return array{bytes:string,filename:string,content_type:string}
     */
    public static function buildAccreditationPackage(array $plans, string $format = 'naac'): array
    {
        $format = strtolower($format) === 'nba' ? 'nba' : 'naac';
        if (!class_exists('SimplePdf', false)) {
            require_once dirname(__DIR__) . '/includes/SimplePdf.php';
        }
        $bytes = self::buildAccreditationPdf($plans, $format);
        return [
            'bytes' => $bytes,
            'filename' => 'Accreditation_Package_' . strtoupper($format) . '_' . date('Ymd_His') . '.pdf',
            'content_type' => 'application/pdf',
        ];
    }

    /**
     * Styled multi-plan PDF using the same stored course-plan fields as exportHtml().
     *
     * @param list<array<string,mixed>> $plans
     */
    public static function buildAccreditationPdf(array $plans, string $format = 'naac'): string
    {
        if (!class_exists('SimplePdf', false)) {
            require_once dirname(__DIR__) . '/includes/SimplePdf.php';
        }
        $format = strtolower($format) === 'nba' ? 'nba' : 'naac';
        $label = strtoupper($format);
        $pdf = new SimplePdf();

        // Cover
        $pdf->filledRect(0, 0, $pdf->pageWidth(), 118, [15, 23, 56]);
        $pdf->setFont(11, false);
        $pdf->textAt(42, 28, 'ProProfessor AI', [196, 181, 253]);
        $pdf->setFont(22, true);
        $pdf->textAt(42, 52, $label . ' Accreditation Package', [255, 255, 255]);
        $pdf->setFont(11, false);
        $pdf->textAt(42, 82, 'Generated ' . date('d M Y, H:i') . '  ·  ' . count($plans) . ' approved course plan(s)', [203, 213, 225]);
        $pdf->moveTo(140);
        $pdf->setFont(13, true);
        $pdf->writeLine('Included course plans', [30, 41, 79]);
        $pdf->hRule([99, 102, 241]);
        $pdf->setFont(10, false);
        foreach ($plans as $i => $plan) {
            $pdf->writeLine(
                ($i + 1) . '. ' . (string)$plan['subject_name'] . '  —  ' . (string)$plan['title'] . '  (v' . (int)$plan['version'] . ')',
                [51, 65, 85]
            );
        }
        $pdf->space(10);
        $pdf->setFont(9, false);
        $pdf->writeWrapped(
            'This document is generated from approved course-plan records only. Attainment percentages and fabricated matrices are not included.',
            0,
            12,
            [100, 116, 139]
        );

        foreach ($plans as $idx => $plan) {
            $pdf->addPage();
            self::renderPlanPdfPage($pdf, $plan, $format, $idx + 1, count($plans));
        }

        $out = $pdf->output();
        if ($out === '' || !str_starts_with($out, '%PDF')) {
            throw new RuntimeException('PDF generation failed.');
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function renderPlanPdfPage(SimplePdf $pdf, array $plan, string $format, int $index, int $total): void
    {
        $units = Database::fetchAll(
            'SELECT * FROM plan_units WHERE plan_id = ? ORDER BY sort_order, unit_number',
            [(int)$plan['id']]
        );
        $meta = json_decode((string)($plan['meta'] ?? '{}'), true) ?: [];
        $template = self::templateLabel((string)($meta['accreditation_template'] ?? 'standard'));
        $bloom = self::bloomBalance($plan, $units);
        $planData = json_decode((string)($plan['plan_data'] ?? '{}'), true) ?: [];
        $lo = is_array($planData['learning_outcomes'] ?? null) ? $planData['learning_outcomes'] : [];
        $title = strtoupper($format) === 'NBA' ? 'NBA Course File' : 'NAAC Course Plan';

        $pdf->filledRect(0, 0, $pdf->pageWidth(), 64, [30, 41, 79]);
        $pdf->setFont(10, false);
        $pdf->textAt(42, 16, $title . '  ·  Plan ' . $index . ' of ' . $total, [165, 180, 252]);
        $pdf->setFont(16, true);
        $pdf->textAt(42, 34, (string)$plan['subject_name'], [255, 255, 255]);
        $pdf->moveTo(80);

        $pdf->setFont(12, true);
        $pdf->writeLine((string)$plan['title'], [15, 23, 42]);
        $pdf->setFont(9, false);
        $pdf->writeLine(
            'Status: ' . (string)$plan['status'] . '   ·   Version v' . (int)$plan['version'] . '   ·   Template: ' . $template,
            [100, 116, 139],
            14
        );
        $pdf->hRule([99, 102, 241]);

        $pdf->setFont(11, true);
        $pdf->writeLine('Course information', [30, 41, 79]);
        $infoRows = [
            ['Title', (string)$plan['title']],
            ['Subject', (string)$plan['subject_name']],
            ['Credits', (string)$plan['credits']],
            ['University', (string)($plan['university'] ?? '—')],
            ['Semester / Year', self::planSemesterLabel($plan) ?: '—'],
            ['Export format', strtoupper($format)],
        ];
        $pdf->table(['Field', 'Value'], $infoRows, [1.2, 3.2], 9);

        if ($lo) {
            $pdf->space(8);
            $pdf->setFont(11, true);
            $pdf->writeLine($format === 'nba' ? 'Learning outcomes / Course outcomes' : 'Learning outcomes', [30, 41, 79]);
            $pdf->setFont(9.5, false);
            $n = 1;
            foreach ($lo as $item) {
                $text = is_string($item) ? $item : (string)json_encode($item);
                $pdf->writeWrapped($n . '. ' . $text, 0, 13, [51, 65, 85]);
                $n++;
            }
        }

        $pdf->space(8);
        $pdf->setFont(11, true);
        $pdf->writeLine('Units', [30, 41, 79]);
        $unitRows = [];
        foreach ($units as $u) {
            $topics = json_decode((string)($u['topics'] ?? '[]'), true);
            $outcomes = json_decode((string)($u['outcomes'] ?? '[]'), true);
            $unitRows[] = [
                (string)(int)$u['unit_number'],
                (string)$u['title'],
                (string)$u['hours'],
                (string)($u['bloom_k_level'] ?? ''),
                is_array($topics) ? implode(', ', $topics) : '',
                is_array($outcomes) ? implode('; ', $outcomes) : '',
            ];
        }
        if (!$unitRows) {
            $pdf->setFont(9, false);
            $pdf->writeLine('No units stored for this plan.', [148, 163, 184]);
        } else {
            $pdf->table(
                ['#', 'Title', 'Hrs', 'Bloom', 'Topics', 'Outcomes'],
                $unitRows,
                [0.4, 1.4, 0.5, 0.6, 2.0, 2.0],
                8
            );
        }

        $pdf->space(10);
        $pdf->setFont(11, true);
        $pdf->writeLine("Bloom's distribution", [30, 41, 79]);
        $bloomHeaders = array_keys($bloom['distribution']);
        $bloomValues = [];
        foreach ($bloom['distribution'] as $v) {
            $bloomValues[] = rtrim(rtrim(number_format((float)$v, 1, '.', ''), '0'), '.') . '%';
        }
        $pdf->table($bloomHeaders, [$bloomValues], array_fill(0, count($bloomHeaders), 1), 9);

        $pdf->space(12);
        $pdf->filledRect(42, $pdf->y(), $pdf->contentWidth(), 36, [255, 247, 237]);
        $pdf->strokeRect(42, $pdf->y(), $pdf->contentWidth(), 36, [253, 186, 116], 0.8);
        $pdf->setFont(8.5, false);
        $pdf->textAt(50, $pdf->y() + 8, 'Note: Export reflects only stored course-plan data. No fabricated attainment or CLO-PO scores.', [146, 64, 14]);
        $pdf->textAt(50, $pdf->y() + 20, 'Institution-scoped · Approved plans only · ProProfessor AI', [146, 64, 14]);
        $pdf->moveTo($pdf->y() + 44);
    }

    /**
     * Build accreditation ZIP package using existing exportHtml().
     *
     * @param list<array<string,mixed>> $plans
     */
    public static function buildAccreditationZip(array $plans, string $format = 'naac'): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZIP export requires php_zip. Enable extension=zip in php.ini.');
        }
        $format = strtolower($format) === 'nba' ? 'nba' : 'naac';
        $tmp = tempnam(sys_get_temp_dir(), 'accpkg_');
        if ($tmp === false) {
            throw new RuntimeException('Could not create temporary file.');
        }
        $zipPath = $tmp . '.zip';
        @unlink($tmp);
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create ZIP package.');
        }
        $index = "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>Accreditation Package</title></head><body>";
        $index .= '<h1>Accreditation Package</h1><ul>';
        $used = [];
        foreach ($plans as $plan) {
            $units = Database::fetchAll(
                'SELECT * FROM plan_units WHERE plan_id = ? ORDER BY sort_order, unit_number',
                [(int)$plan['id']]
            );
            $folder = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string)$plan['subject_name']) ?: 'Plan';
            $folder = trim($folder, '_');
            if ($folder === '') {
                $folder = 'Plan_' . (int)$plan['id'];
            }
            $base = $folder;
            $n = 2;
            while (isset($used[$folder])) {
                $folder = $base . '_' . $n;
                $n++;
            }
            $used[$folder] = true;
            $html = self::exportHtml($plan, $units, $format);
            $file = $folder . '/' . $folder . '_' . strtoupper($format) . '_v' . (int)$plan['version'] . '.html';
            $zip->addFromString($file, $html);
            $index .= '<li><a href="' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars((string)$plan['subject_name'], ENT_QUOTES, 'UTF-8')
                . ' (v' . (int)$plan['version'] . ')</a></li>';
        }
        $index .= '</ul></body></html>';
        $zip->addFromString('index.html', $index);
        $zip->close();
        $bytes = (string)file_get_contents($zipPath);
        @unlink($zipPath);
        if ($bytes === '') {
            throw new RuntimeException('ZIP package was empty.');
        }
        return $bytes;
    }

    /** Semester/year label used by My Plans filters. */
    public static function planSemesterLabel(array $plan): string
    {
        $sem = trim((string)($plan['semester'] ?? ''));
        if ($sem !== '') {
            return $sem;
        }
        $classSem = trim((string)($plan['class_semester'] ?? ''));
        if ($classSem !== '') {
            return $classSem;
        }
        $ay = trim((string)($plan['academic_year'] ?? $plan['class_ay'] ?? ''));
        if ($ay !== '') {
            return $ay;
        }
        $year = (int)($plan['class_year'] ?? 0);
        if ($year > 0) {
            return 'Year ' . $year;
        }
        return '';
    }

    /**
     * Co-faculty: same institution + same department professors (or HOD/admin), not arbitrary users.
     */
    public static function canCommentOnPlan(array $user, array $plan): bool
    {
        if ((int)($plan['institution_id'] ?? 0) !== (int)($user['institution_id'] ?? 0)) {
            return false;
        }
        $role = (string)($user['role'] ?? '');
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return true;
        }
        if ($role === 'hod') {
            return (int)($plan['department_id'] ?? 0) === (int)($user['department_id'] ?? 0)
                && (int)($user['department_id'] ?? 0) > 0;
        }
        if ($role !== 'professor') {
            return false;
        }
        // Owner or same-department faculty.
        if ((int)($plan['professor_id'] ?? 0) === (int)($user['id'] ?? 0)) {
            return true;
        }
        $dept = (int)($user['department_id'] ?? 0);
        return $dept > 0 && $dept === (int)($plan['department_id'] ?? 0);
    }

    public static function canViewPlan(array $user, array $plan): bool
    {
        if ((int)($plan['institution_id'] ?? 0) !== (int)($user['institution_id'] ?? 0)) {
            return false;
        }
        $role = (string)($user['role'] ?? '');
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return true;
        }
        if ($role === 'hod') {
            return (int)($plan['department_id'] ?? 0) === (int)($user['department_id'] ?? 0);
        }
        if ($role === 'professor') {
            if ((int)($plan['professor_id'] ?? 0) === (int)($user['id'] ?? 0)) {
                return true;
            }
            // Co-faculty in same department may view drafts for review comments.
            $dept = (int)($user['department_id'] ?? 0);
            return $dept > 0 && $dept === (int)($plan['department_id'] ?? 0);
        }
        return false;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<array<string,mixed>> $units
     */
    public static function exportHtml(array $plan, array $units, string $format): string
    {
        $format = strtolower($format) === 'nba' ? 'nba' : 'naac';
        $meta = json_decode((string)($plan['meta'] ?? '{}'), true) ?: [];
        $template = self::templateLabel((string)($meta['accreditation_template'] ?? 'standard'));
        $bloom = self::bloomBalance($plan, $units);
        $lo = [];
        $planData = json_decode((string)($plan['plan_data'] ?? '{}'), true) ?: [];
        if (!empty($planData['learning_outcomes']) && is_array($planData['learning_outcomes'])) {
            $lo = $planData['learning_outcomes'];
        }
        $title = $format === 'nba' ? 'NBA Course File Export' : 'NAAC Course Plan Export';
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title . ' — ' . (string)$plan['subject_name'], ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    body{font-family:Segoe UI,Arial,sans-serif;margin:24px;color:#111;line-height:1.45}
    h1,h2,h3{margin:1.1rem 0 .4rem}
    table{border-collapse:collapse;width:100%;margin:.6rem 0 1rem}
    th,td{border:1px solid #bbb;padding:6px 8px;vertical-align:top;font-size:13px}
    th{background:#f3f3f3;text-align:left}
    .muted{color:#555;font-size:12px}
    .note{background:#fff8e6;border:1px solid #f0d58c;padding:8px 10px;margin:12px 0}
    @media print{body{margin:12px}}
  </style>
</head>
<body>
  <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
  <p class="muted">Generated from stored course-plan data only. No fabricated attainment percentages.</p>
  <h2>Course information</h2>
  <table>
    <tr><th>Title</th><td><?= htmlspecialchars((string)$plan['title'], ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><th>Subject</th><td><?= htmlspecialchars((string)$plan['subject_name'], ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><th>Credits</th><td><?= htmlspecialchars((string)$plan['credits'], ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><th>University</th><td><?= htmlspecialchars((string)($plan['university'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><th>Status</th><td><?= htmlspecialchars((string)$plan['status'], ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><th>Version</th><td>v<?= (int)$plan['version'] ?></td></tr>
    <tr><th>Curriculum template</th><td><?= htmlspecialchars($template, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><th>Export format</th><td><?= strtoupper($format) ?></td></tr>
  </table>

  <?php if ($lo): ?>
  <h2>Learning outcomes<?= $format === 'nba' ? ' / Course Outcomes' : '' ?></h2>
  <ol>
    <?php foreach ($lo as $item): ?>
      <li><?= htmlspecialchars(is_string($item) ? $item : json_encode($item), ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>

  <h2>Units</h2>
  <table>
    <thead><tr><th>#</th><th>Title</th><th>Hours</th><th>Bloom</th><th>Topics</th><th>Outcomes</th></tr></thead>
    <tbody>
    <?php foreach ($units as $u):
      $topics = json_decode((string)($u['topics'] ?? '[]'), true);
      $outcomes = json_decode((string)($u['outcomes'] ?? '[]'), true);
    ?>
      <tr>
        <td><?= (int)$u['unit_number'] ?></td>
        <td><?= htmlspecialchars((string)$u['title'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$u['hours'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($u['bloom_k_level'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(is_array($topics) ? implode(', ', $topics) : '', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(is_array($outcomes) ? implode('; ', $outcomes) : '', ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h2>Bloom's distribution (from plan)</h2>
  <table>
    <tr><?php foreach ($bloom['distribution'] as $k => $v): ?><th><?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?></tr>
    <tr><?php foreach ($bloom['distribution'] as $v): ?><td><?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>%</td><?php endforeach; ?></tr>
  </table>

  <div class="note">
    Attainment percentages, fabricated CLO–PO matrices, and unverified compliance scores are intentionally omitted.
    Export reflects only information stored on this course plan.
  </div>
</body>
</html>
        <?php
        return (string)ob_get_clean();
    }

    /** @param list<mixed> $units @return array<int,array<string,mixed>> */
    private static function indexUnits(array $units): array
    {
        $out = [];
        foreach ($units as $i => $u) {
            if (!is_array($u)) {
                continue;
            }
            $num = (int)($u['unit_number'] ?? ($i + 1));
            $out[$num] = $u;
        }
        return $out;
    }

    /** @param array<int,array<string,mixed>> $units @return list<string> */
    private static function flattenTopics(array $units): array
    {
        $topics = [];
        foreach ($units as $num => $u) {
            foreach (self::normalizeList($u['topics'] ?? []) as $t) {
                $topics[] = 'U' . $num . ': ' . $t;
            }
        }
        return $topics;
    }

    /** @return list<string> */
    private static function normalizeList(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $parts = preg_split('/\s*;\s*/', $raw);
                $raw = is_array($parts) ? $parts : [];
            }
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            $s = trim(is_string($item) ? $item : (string)json_encode($item));
            if ($s !== '') {
                $out[] = $s;
            }
        }
        sort($out);
        return $out;
    }

    /**
     * True when topics look like generic placeholders (Topic 1.1) rather than syllabus content.
     *
     * @param list<mixed>|mixed $topics
     */
    public static function topicsArePlaceholders(mixed $topics): bool
    {
        $list = self::normalizeListUnordered($topics);
        if ($list === []) {
            return true;
        }
        $placeholder = 0;
        foreach ($list as $t) {
            if (preg_match('/^topic\s*\d+(\.\d+)?$/i', $t) || preg_match('/^core\s+topic$/i', $t)) {
                $placeholder++;
            }
        }
        return $placeholder > 0 && $placeholder >= (int)ceil(count($list) * 0.5);
    }

    /**
     * Parse syllabus text into unit blocks with real topic lists (dynamic; no subject hard-coding).
     * Supports "Unit 1", "UNIT I", "UNIT-II", etc.
     *
     * @return list<array{unit_number:int,title:string,topics:list<string>}>
     */
    public static function parseSyllabusIntoUnits(string $syllabus): array
    {
        $syllabus = trim(self::sanitizeExtractedText($syllabus));
        if ($syllabus === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $syllabus) ?: [];
        $units = [];
        $current = null;

        $flush = static function () use (&$units, &$current): void {
            if ($current === null) {
                return;
            }
            $topics = [];
            $seen = [];
            foreach ($current['topics'] as $t) {
                if (!is_string($t)) {
                    continue;
                }
                $t = trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
                if (strlen($t) < 3 || strlen($t) > 220) {
                    continue;
                }
                $key = strtolower($t);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $topics[] = $t;
                if (count($topics) >= 40) {
                    break;
                }
            }
            $current['topics'] = $topics;
            $units[] = $current;
            $current = null;
        };

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $header = self::matchUnitHeader($line);
            if ($header !== null) {
                $flush();
                $current = [
                    'unit_number' => $header['number'],
                    'title' => $header['title'] !== '' ? $header['title'] : ('Unit ' . $header['number']),
                    'topics' => [],
                ];
                // Unit title itself can be a useful topic seed when it is descriptive.
                if ($header['title'] !== '' && !preg_match('/^unit\s*\d+$/i', $header['title'])) {
                    $current['topics'][] = $header['title'];
                }
                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^(outcomes?|hours?|credits?|assessment|resources?|text\s*book|references?)\b/i', $line)) {
                continue;
            }

            // Section header like "Matrices:" — keep label and any inline topics.
            if (preg_match('/^([A-Za-z][A-Za-z0-9 \/\-&]{1,80}):\s*(.*)$/', $line, $sm)) {
                $label = trim($sm[1]);
                $rest = trim($sm[2]);
                if ($label !== '') {
                    $current['topics'][] = $label;
                }
                if ($rest !== '') {
                    foreach (self::splitTopicFragments($rest) as $frag) {
                        $current['topics'][] = $frag;
                    }
                }
                continue;
            }

            $clean = preg_replace('/^[\-\*\x{2022}\d\.\)\s]+/u', '', $line) ?? $line;
            $clean = trim($clean);
            if ($clean === '') {
                continue;
            }
            foreach (self::splitTopicFragments($clean) as $frag) {
                $current['topics'][] = $frag;
            }
        }
        $flush();

        return $units;
    }

    /**
     * Merge syllabus-derived topics into plan units when stored topics are placeholders/empty.
     * Preserves hours, outcomes, bloom, methods, assessment from the existing unit rows.
     *
     * @param list<array<string,mixed>> $units
     * @return list<array<string,mixed>>
     */
    public static function enrichUnitsFromSyllabus(array $units, string $syllabus): array
    {
        $parsed = self::parseSyllabusIntoUnits($syllabus);
        if ($parsed === []) {
            return $units;
        }
        $byNum = [];
        foreach ($parsed as $p) {
            $num = (int)$p['unit_number'];
            $count = count($p['topics'] ?? []);
            if (!isset($byNum[$num])) {
                $byNum[$num] = $p;
                continue;
            }
            // Prefer a sane topic list over a later PDF dump of the whole document.
            $existing = count($byNum[$num]['topics'] ?? []);
            if (($existing > 60 || $existing < 2) && $count >= 2 && $count <= 60) {
                $byNum[$num] = $p;
            }
        }

        $out = [];
        foreach ($units as $i => $u) {
            if (!is_array($u)) {
                continue;
            }
            $num = (int)($u['unit_number'] ?? ($i + 1));
            $topicsRaw = $u['topics'] ?? [];
            if (is_string($topicsRaw)) {
                $decoded = json_decode($topicsRaw, true);
                $topicsRaw = is_array($decoded) ? $decoded : [];
            }
            $needs = self::topicsArePlaceholders($topicsRaw);
            if ($needs && isset($byNum[$num]) && !empty($byNum[$num]['topics'])) {
                $u['topics'] = $byNum[$num]['topics'];
                // Prefer descriptive syllabus unit title when current title is generic.
                $curTitle = trim((string)($u['title'] ?? ''));
                $parsedTitle = trim((string)$byNum[$num]['title']);
                if ($parsedTitle !== '' && (
                    $curTitle === ''
                    || preg_match('/^unit\s*\d+(\s*[·\-:]\s*core\s+concepts)?$/i', $curTitle)
                )) {
                    $u['title'] = 'Unit ' . $num . ' – ' . $parsedTitle;
                }
            } elseif ($needs && !isset($byNum[$num])) {
                // No matching unit header — leave placeholders (true fallback).
            }
            $u['unit_number'] = $num;
            $out[] = $u;
        }

        // If plan had no units yet, build minimal units from syllabus parse.
        if ($out === [] && $parsed !== []) {
            foreach ($parsed as $p) {
                $out[] = [
                    'unit_number' => $p['unit_number'],
                    'title' => 'Unit ' . $p['unit_number'] . ' – ' . $p['title'],
                    'hours' => 12,
                    'topics' => $p['topics'],
                    'outcomes' => [],
                    'bloom_k_level' => 'K' . min(6, max(1, $p['unit_number'])),
                    'teaching_methods' => ['Lecture', 'Activity'],
                    'assessment' => ['Formative quiz'],
                ];
            }
        }

        return $out;
    }

    /**
     * Soft-repair stored plan unit topics from syllabus_input when placeholders are present.
     * Does NOT change status, version, HOD comments, ownership, or create a new version.
     * Never writes invalid JSON into CHECK(json_valid(...)) columns.
     *
     * @return array Updated plan row (same row if nothing changed / on failure)
     */
    public static function syncPlanTopicsFromSyllabus(array $planRow): array
    {
        try {
            return self::syncPlanTopicsFromSyllabusInner($planRow);
        } catch (Throwable $e) {
            // Soft-repair must never break PPT / lesson / question flows.
            return $planRow;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function syncPlanTopicsFromSyllabusInner(array $planRow): array
    {
        $planId = (int)($planRow['id'] ?? 0);
        if ($planId < 1) {
            return $planRow;
        }
        $syllabus = trim((string)($planRow['syllabus_input'] ?? ''));
        if ($syllabus === '') {
            return $planRow;
        }

        $dbUnits = Database::fetchAll(
            'SELECT * FROM plan_units WHERE plan_id = ? ORDER BY sort_order, unit_number',
            [$planId]
        );
        $data = json_decode((string)($planRow['plan_data'] ?? ''), true) ?: [];
        $jsonUnits = is_array($data['units'] ?? null) ? $data['units'] : [];

        $sourceUnits = $dbUnits ?: $jsonUnits;
        if ($sourceUnits === []) {
            return $planRow;
        }

        $needsRepair = false;
        foreach ($sourceUnits as $u) {
            $topics = $u['topics'] ?? [];
            if (is_string($topics)) {
                $topics = json_decode($topics, true) ?: [];
            }
            if (self::topicsArePlaceholders($topics)) {
                $needsRepair = true;
                break;
            }
        }
        if (!$needsRepair) {
            return $planRow;
        }

        // Normalize DB rows to array shape for enrichment.
        $normalized = [];
        foreach ($sourceUnits as $i => $u) {
            if (!is_array($u)) {
                continue;
            }
            $topics = $u['topics'] ?? [];
            if (is_string($topics)) {
                $topics = json_decode($topics, true) ?: [];
            }
            $normalized[] = [
                'id' => $u['id'] ?? null,
                'unit_number' => (int)($u['unit_number'] ?? ($i + 1)),
                'title' => (string)($u['title'] ?? ('Unit ' . ($i + 1))),
                'hours' => $u['hours'] ?? 0,
                'topics' => is_array($topics) ? $topics : [],
                'outcomes' => $u['outcomes'] ?? [],
                'bloom_k_level' => $u['bloom_k_level'] ?? null,
                'teaching_methods' => $u['teaching_methods'] ?? [],
                'assessment' => $u['assessment'] ?? [],
                'sort_order' => $u['sort_order'] ?? $i,
            ];
        }

        $enriched = self::enrichUnitsFromSyllabus($normalized, $syllabus);
        $changed = false;
        foreach ($enriched as $i => $eu) {
            $before = $normalized[$i]['topics'] ?? [];
            $after = $eu['topics'] ?? [];
            if ($before !== $after || (string)($normalized[$i]['title'] ?? '') !== (string)($eu['title'] ?? '')) {
                $changed = true;
                break;
            }
        }
        if (!$changed) {
            return $planRow;
        }

        // Update plan_units.topics (+ title if improved) only — preserve all other columns/rows.
        foreach ($enriched as $eu) {
            $num = (int)$eu['unit_number'];
            $row = Database::fetch(
                'SELECT id FROM plan_units WHERE plan_id = ? AND unit_number = ? LIMIT 1',
                [$planId, $num]
            );
            if (!$row) {
                continue;
            }
            $topicsJson = self::encodeJsonColumn(self::normalizeTopicList($eu['topics'] ?? []));
            if ($topicsJson === null) {
                continue; // never write invalid JSON
            }
            $title = self::sanitizeExtractedText((string)($eu['title'] ?? ('Unit ' . $num)));
            $title = mb_substr($title !== '' ? $title : ('Unit ' . $num), 0, 255);
            Database::update('plan_units', [
                'topics' => $topicsJson,
                'title' => $title,
            ], 'id = :id AND plan_id = :pid', [
                'id' => (int)$row['id'],
                'pid' => $planId,
            ]);
        }

        // Keep plan_data.units topics in sync without bumping version/status.
        if ($jsonUnits !== []) {
            $data['units'] = self::enrichUnitsFromSyllabus($jsonUnits, $syllabus);
            foreach ($data['units'] as &$uu) {
                if (is_array($uu)) {
                    $uu['topics'] = self::normalizeTopicList($uu['topics'] ?? []);
                }
            }
            unset($uu);
            $planDataJson = self::encodeJsonColumn($data);
            if ($planDataJson !== null) {
                Database::update('course_plans', [
                    'plan_data' => $planDataJson,
                ], 'id = :id', ['id' => $planId]);
                $planRow['plan_data'] = $planDataJson;
            }
        }

        return $planRow;
    }

    /**
     * @param mixed $topics
     * @return list<string>
     */
    public static function normalizeTopicList(mixed $topics): array
    {
        if (is_string($topics)) {
            $decoded = json_decode($topics, true);
            $topics = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($topics)) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($topics as $t) {
            if (!is_string($t) && !is_numeric($t)) {
                continue;
            }
            $t = self::sanitizeExtractedText((string)$t);
            $t = trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
            if (strlen($t) < 3 || strlen($t) > 220) {
                continue;
            }
            if (preg_match('/^topic\s*\d+(\.\d+)?$/i', $t)) {
                continue;
            }
            $key = strtolower($t);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $t;
            if (count($out) >= 40) {
                break;
            }
        }
        return $out;
    }

    /** Encode value for MySQL/MariaDB JSON CHECK columns; null if unusable. */
    public static function encodeJsonColumn(mixed $value): ?string
    {
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $json = json_encode($value, $flags);
        if (!is_string($json) || $json === '' || $json === 'null') {
            return null;
        }
        // Empty array is valid JSON and allowed.
        return $json;
    }

    /**
     * @return array{number:int,title:string}|null
     */
    private static function matchUnitHeader(string $line): ?array
    {
        // UNIT I – Title | Unit 1: Title | UNIT-II Title
        if (preg_match(
            '/^\s*unit\s*(?:[-.]?\s*)?([ivxlcdm]+|\d+)\s*(?:[–—\-:.]|\s)+?\s*(.*)$/iu',
            $line,
            $m
        )) {
            $num = self::parseUnitNumberToken($m[1]);
            if ($num < 1) {
                return null;
            }
            return ['number' => $num, 'title' => trim($m[2])];
        }
        if (preg_match('/^\s*unit\s*(?:[-.]?\s*)?([ivxlcdm]+|\d+)\s*$/iu', $line, $m)) {
            $num = self::parseUnitNumberToken($m[1]);
            if ($num < 1) {
                return null;
            }
            return ['number' => $num, 'title' => ''];
        }
        return null;
    }

    private static function parseUnitNumberToken(string $token): int
    {
        $token = strtoupper(trim($token));
        if ($token !== '' && ctype_digit($token)) {
            return max(0, (int)$token);
        }
        $map = [
            'I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5,
            'VI' => 6, 'VII' => 7, 'VIII' => 8, 'IX' => 9, 'X' => 10,
            'XI' => 11, 'XII' => 12, 'XIII' => 13, 'XIV' => 14, 'XV' => 15,
        ];
        return $map[$token] ?? 0;
    }

    /** @return list<string> */
    private static function splitTopicFragments(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/\s*[,;\/|]\s*/u', $text) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string)$p, " \t.-•");
            $p = preg_replace('/\s+/u', ' ', $p) ?? $p;
            if (strlen($p) > 2 && strlen($p) < 160 && !preg_match('/^topic\s*\d+(\.\d+)?$/i', $p)) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** @return list<string> */
    private static function normalizeListUnordered(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = preg_split('/\s*;\s*/', $raw) ?: [];
            }
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            $s = trim(is_string($item) ? $item : (string)$item);
            if ($s !== '') {
                $out[] = $s;
            }
        }
        return $out;
    }
}
