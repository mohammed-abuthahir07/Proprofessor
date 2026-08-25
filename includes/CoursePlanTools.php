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
     * @return array{added_topics:list<string>,removed_topics:list<string>,changed_units:list<array<string,string>>,changed_outcomes:list<string>,changed_bloom:list<string>,changed_hours:list<string>}
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
        ];
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
}
