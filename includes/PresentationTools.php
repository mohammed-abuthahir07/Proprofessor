<?php
declare(strict_types=1);

/**
 * PPT Generator extensions: institution branding, slide regen, handout/PDF helpers.
 * Does not replace existing AI PPT generation.
 */
final class PresentationTools
{
    /**
     * Branding from authenticated user's institution only.
     *
     * @return array{institution_id:int,name:string,logo_url:?string,primary:string,secondary:string,accent:string}
     */
    public static function brandingForUser(array $user): array
    {
        $instId = (int)($user['institution_id'] ?? 0);
        $defaults = [
            'institution_id' => $instId,
            'name' => 'Institution',
            'logo_url' => null,
            'primary' => '1E3A8A',
            'secondary' => '0F172A',
            'accent' => 'D97706',
        ];
        if ($instId < 1) {
            return $defaults;
        }
        $inst = Database::fetch('SELECT id, name, logo_url, settings FROM institutions WHERE id = ?', [$instId]);
        if (!$inst) {
            return $defaults;
        }
        $settings = json_decode((string)($inst['settings'] ?? '{}'), true) ?: [];
        $primary = self::sanitizeHex((string)($settings['brand_primary'] ?? $settings['primary_color'] ?? '1E3A8A'));
        $secondary = self::sanitizeHex((string)($settings['brand_secondary'] ?? $settings['secondary_color'] ?? '0F172A'));
        $accent = self::sanitizeHex((string)($settings['brand_accent'] ?? $settings['accent_color'] ?? 'D97706'));
        $logo = trim((string)($inst['logo_url'] ?? ''));
        return [
            'institution_id' => (int)$inst['id'],
            'name' => trim((string)$inst['name']) !== '' ? (string)$inst['name'] : 'Institution',
            'logo_url' => $logo !== '' ? $logo : null,
            'primary' => $primary,
            'secondary' => $secondary,
            'accent' => $accent,
        ];
    }

    public static function sanitizeHex(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        if (!preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
            return '1E3A8A';
        }
        return strtoupper($hex);
    }

    public static function ownedPresentation(array $user, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $ppt = Database::fetch('SELECT * FROM presentations WHERE id = ?', [$id]);
        if (!$ppt || !presentation_accessible($user, $ppt)) {
            return null;
        }
        // Extra institution check for professors/admins
        $owner = Database::fetch('SELECT institution_id FROM users WHERE id = ?', [(int)$ppt['professor_id']]);
        if (!$owner || (int)$owner['institution_id'] !== (int)($user['institution_id'] ?? 0)) {
            return null;
        }
        return $ppt;
    }

    /**
     * Regenerate a single slide index (0-based). Preserves other slides on failure.
     *
     * @return array{ok:bool,error?:string,slides?:list<array<string,mixed>>}
     */
    public static function regenerateSlide(array $user, array $ppt, int $index, string $instruction = ''): array
    {
        $slides = json_decode((string)($ppt['slides'] ?? '[]'), true);
        if (!is_array($slides) || !isset($slides[$index]) || !is_array($slides[$index])) {
            return ['ok' => false, 'error' => 'Slide not found.'];
        }
        $original = $slides[$index];
        $meta = json_decode((string)($ppt['meta'] ?? '{}'), true) ?: [];
        $subject = (string)($meta['subject'] ?? 'Course');
        $unit = (int)($meta['unit'] ?? 1);
        if ($unit < 1) {
            $unit = 1;
        }
        $title = (string)($original['title'] ?? ('Slide ' . ($index + 1)));
        $unitTag = (string)($original['unit_tag'] ?? ('Unit ' . $unit));
        $instruction = trim($instruction);
        if (strlen($instruction) > 500) {
            $instruction = substr($instruction, 0, 500);
        }

        $newSlide = null;
        $gemini = class_exists('Gemini') ? new Gemini() : null;
        $aiConfigured = $gemini && $gemini->isConfigured();
        if ($aiConfigured) {
            try {
                $system = 'You regenerate ONE academic lecture slide as JSON. Return ONLY valid JSON object.';
                $prompt = "Regenerate this lecture slide for {$subject}, {$unitTag}.\n"
                    . "Current title: {$title}\n"
                    . "Current bullets: " . json_encode($original['bullets'] ?? [], JSON_UNESCAPED_UNICODE) . "\n"
                    . ($instruction !== '' ? "Professor instruction: {$instruction}\n" : '')
                    . "Rules: keep academic quality; include speaker_notes; unit_tag must be \"{$unitTag}\"; 3–6 bullets; no placeholders.\n"
                    . "Return: {\"number\":" . ($index + 1) . ",\"title\":\"\",\"bullets\":[\"\"],\"speaker_notes\":\"\",\"unit_tag\":\"{$unitTag}\"}";
                $result = $gemini->generate($system, $prompt);
                $raw = is_array($result['json'] ?? null) ? $result['json'] : null;
                if (is_array($raw) && isset($raw['slides'][0]) && is_array($raw['slides'][0])) {
                    $raw = $raw['slides'][0];
                }
                if (is_array($raw) && trim((string)($raw['title'] ?? '')) !== '') {
                    $normalized = self::normalizeOneSlide($raw, $unit, $index + 1, $unitTag);
                    if ($normalized !== null) {
                        $newSlide = $normalized;
                    }
                }
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => 'Regeneration failed. Original slide preserved.'];
            }
            if (!$newSlide) {
                return ['ok' => false, 'error' => 'AI returned unusable slide data. Original slide preserved.'];
            }
        } else {
            $hint = $instruction !== '' ? $instruction : 'Clarify with a short classroom example.';
            $pack = LectureSlideBuilder::buildDeck(
                (string)($ppt['title'] ?? 'Lecture'),
                $subject,
                $unit,
                '',
                [$title],
                ['institution' => (string)(PresentationTools::brandingForUser($user)['name'] ?? '')]
            );
            $newSlide = null;
            foreach ($pack as $cand) {
                if (!is_array($cand)) {
                    continue;
                }
                $ct = strtolower((string)($cand['title'] ?? ''));
                if ($ct === strtolower($title) || str_contains($ct, strtolower($title)) || str_contains(strtolower($title), $ct)) {
                    $newSlide = $cand;
                    break;
                }
            }
            if (!$newSlide) {
                $newSlide = $pack[3] ?? $pack[2] ?? null;
            }
            if (!$newSlide) {
                $newSlide = [
                    'number' => $index + 1,
                    'title' => $title,
                    'layout' => 'content',
                    'bullets' => [
                        'Define "' . $title . '" using precise ' . $subject . ' terminology.',
                        'Explain how "' . $title . '" works or is applied in Unit ' . $unit . '.',
                        'Give one concrete classroom example for "' . $title . '".',
                        'State one common misconception and the correct interpretation.',
                    ],
                    'speaker_notes' => "Teaching focus: {$title}. {$hint}",
                    'unit_tag' => $unitTag,
                ];
            } else {
                $newSlide['title'] = $title;
                $newSlide['speaker_notes'] = trim((string)($newSlide['speaker_notes'] ?? '') . ' ' . $hint);
            }
        }

        $newSlide['number'] = $index + 1;
        $newSlide['unit_tag'] = $unitTag;
        if (trim((string)($newSlide['speaker_notes'] ?? '')) === '') {
            $newSlide['speaker_notes'] = 'Teaching notes for ' . (string)$newSlide['title'];
        }

        $slides[$index] = $newSlide;
        // Re-number
        foreach ($slides as $i => &$s) {
            if (is_array($s)) {
                $s['number'] = $i + 1;
            }
        }
        unset($s);

        $meta['last_regen'] = [
            'slide_index' => $index,
            'at' => date('c'),
            'instruction' => $instruction !== '' ? $instruction : null,
        ];

        try {
            Database::update('presentations', [
                'slides' => json_encode(array_values($slides), JSON_UNESCAPED_UNICODE),
                'slide_count' => count($slides),
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            ], 'id = :id', ['id' => (int)$ppt['id']]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Could not save regenerated slide. Original slide preserved.'];
        }

        return ['ok' => true, 'slides' => array_values($slides)];
    }

    /**
     * Concise student handout PDF bytes from deck slides.
     *
     * @param list<array<string,mixed>> $slides
     */
    public static function buildHandoutPdf(string $title, array $slides, array $branding): string
    {
        if (!class_exists('SimplePdf', false)) {
            require_once __DIR__ . '/SimplePdf.php';
        }
        $pdf = new SimplePdf();
        $pdf->filledRect(0, 0, $pdf->pageWidth(), 90, self::rgb($branding['primary'] ?? '1E3A8A'));
        $pdf->setFont(11, false);
        $pdf->textAt(42, 22, (string)($branding['name'] ?? 'Institution'), [255, 255, 255]);
        $pdf->setFont(18, true);
        $pdf->textAt(42, 44, 'Student Handout', [255, 255, 255]);
        $pdf->moveTo(110);
        $pdf->setFont(14, true);
        $pdf->writeLine($title, [15, 23, 42]);
        $pdf->setFont(9, false);
        $pdf->writeLine('Concise companion notes — not a full transcript of the lecture.', [100, 116, 139], 14);
        $pdf->hRule(self::rgb($branding['accent'] ?? 'D97706'));

        $pdf->setFont(12, true);
        $pdf->writeLine('Key concepts', [30, 41, 79]);
        $pdf->setFont(10, false);
        $count = 0;
        foreach ($slides as $s) {
            if (!is_array($s)) {
                continue;
            }
            $st = trim((string)($s['title'] ?? ''));
            if ($st === '' || preg_match('/\b(title|welcome|thank|summary|recap|check|quiz)\b/i', $st)) {
                // Still include summary lightly
            }
            $pdf->setFont(10, true);
            $pdf->writeLine(($s['unit_tag'] ?? '') . ($st !== '' ? ' · ' . $st : ''), [30, 41, 79], 14);
            $pdf->setFont(9.5, false);
            $bullets = $s['bullets'] ?? [];
            if (is_array($bullets)) {
                foreach (array_slice($bullets, 0, 4) as $b) {
                    $pdf->writeWrapped('• ' . (is_string($b) ? $b : json_encode($b)), 0, 12, [51, 65, 85]);
                }
            }
            $count++;
            if ($count >= 12) {
                break;
            }
        }

        $pdf->space(10);
        $pdf->setFont(11, true);
        $pdf->writeLine('Study tips', [30, 41, 79]);
        $pdf->setFont(9.5, false);
        $pdf->writeWrapped('Review definitions before class examples. Attempt one practice problem per topic. Bring questions on unclear bullets to the next session.', 0, 13, [51, 65, 85]);
        $pdf->space(8);
        $pdf->setFont(8, false);
        $pdf->writeLine('Generated for students from the lecture deck. Branding: ' . (string)($branding['name'] ?? ''), [148, 163, 184]);

        return $pdf->output();
    }

    /**
     * Full deck PDF (readable export, not a renamed PPTX).
     *
     * @param list<array<string,mixed>> $slides
     */
    public static function buildDeckPdf(string $title, array $slides, array $branding, bool $includeInstructorNotes = true): string
    {
        if (!class_exists('SimplePdf', false)) {
            require_once __DIR__ . '/SimplePdf.php';
        }
        $pdf = new SimplePdf();
        $pdf->filledRect(0, 0, $pdf->pageWidth(), 100, self::rgb($branding['secondary'] ?? '0F172A'));
        $pdf->setFont(11, false);
        $pdf->textAt(42, 24, (string)($branding['name'] ?? 'Institution'), [253, 230, 138]);
        $pdf->setFont(20, true);
        $pdf->textAt(42, 48, $title, [255, 255, 255]);
        $pdf->setFont(10, false);
        $pdf->textAt(42, 78, count($slides) . ' slides · PDF export', [203, 213, 225]);
        $pdf->moveTo(120);

        foreach ($slides as $i => $s) {
            if (!is_array($s)) {
                continue;
            }
            if ($i > 0) {
                $pdf->addPage();
            }
            $pdf->setFont(11, true);
            $pdf->writeLine('Slide ' . ($i + 1) . ' · ' . (string)($s['title'] ?? ''), [15, 23, 42]);
            if (!empty($s['unit_tag'])) {
                $pdf->setFont(9, false);
                $pdf->writeLine((string)$s['unit_tag'], [100, 116, 139], 12);
            }
            $pdf->hRule(self::rgb($branding['primary'] ?? '1E3A8A'));
            $pdf->setFont(10, false);
            foreach ((array)($s['bullets'] ?? []) as $b) {
                $pdf->writeWrapped('• ' . (is_string($b) ? $b : (string)json_encode($b)), 0, 13, [30, 41, 59]);
            }
            // Speaker notes are for the instructor — omit from student-facing PDF body;
            // include a small notes section labeled as instructor-only.
            $notes = trim((string)($s['speaker_notes'] ?? ''));
            if ($includeInstructorNotes && $notes !== '') {
                $pdf->space(8);
                $pdf->setFont(9, true);
                $pdf->writeLine('Instructor notes (not slide content)', [146, 64, 14], 12);
                $pdf->setFont(9, false);
                $pdf->writeWrapped($notes, 0, 12, [120, 53, 15]);
            }
        }
        return $pdf->output();
    }

    /** @return array{0:int,1:int,2:int} */
    public static function rgb(string $hex): array
    {
        $hex = self::sanitizeHex($hex);
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    public static function googleSlidesConfigured(): bool
    {
        return (bool)config('google_slides.enabled', false) && trim((string)config('google_slides.client_id', '')) !== '';
    }

    public static function narrationConfigured(): bool
    {
        return (bool)config('narration.enabled', false) && trim((string)config('narration.provider', '')) !== '';
    }

    /**
     * @param array<string,mixed> $slide
     * @return array{number:int,title:string,bullets:list<string>,speaker_notes:string,unit_tag:string}|null
     */
    private static function normalizeOneSlide(array $slide, int $unit, int $number, string $unitTag): ?array
    {
        $title = trim((string)($slide['title'] ?? ''));
        $bulletsRaw = $slide['bullets'] ?? ($slide['points'] ?? []);
        $bullets = [];
        if (is_array($bulletsRaw)) {
            foreach ($bulletsRaw as $b) {
                $text = trim(is_string($b) ? $b : (string)json_encode($b));
                if ($text !== '') {
                    $bullets[] = $text;
                }
            }
        } elseif (is_string($bulletsRaw) && trim($bulletsRaw) !== '') {
            $bullets[] = trim($bulletsRaw);
        }
        if ($title === '' && $bullets === []) {
            return null;
        }
        if ($title === '') {
            $title = 'Slide ' . $number;
        }
        // Reject obvious placeholder junk — keep original slide instead.
        $joined = strtolower(implode(' ', $bullets));
        if (
            preg_match('/^topic slide\s*\d*$/i', $title)
            || str_contains($joined, 'point a')
            || str_contains($joined, 'talking points for slide')
            || count($bullets) < 2
        ) {
            return null;
        }
        $notes = trim((string)($slide['speaker_notes'] ?? $slide['notes'] ?? ''));
        return [
            'number' => $number,
            'title' => $title,
            'bullets' => $bullets,
            'speaker_notes' => $notes !== '' ? $notes : ('Teaching notes for ' . $title),
            'unit_tag' => $unitTag !== '' ? $unitTag : ('Unit ' . max(1, $unit)),
        ];
    }

    /**
     * Resolve branding for a stored deck: snapshot in meta, else owner's institution.
     *
     * @return array{institution_id:int,name:string,logo_url:?string,primary:string,secondary:string,accent:string}
     */
    public static function brandingForPresentation(array $user, array $ppt): array
    {
        $meta = json_decode((string)($ppt['meta'] ?? '{}'), true) ?: [];
        $snap = is_array($meta['branding'] ?? null) ? $meta['branding'] : null;
        $live = self::brandingForUser($user);
        if ($snap) {
            // Never apply another institution's snapshot.
            $snapInst = (int)($snap['institution_id'] ?? 0);
            if ($snapInst > 0 && $snapInst !== (int)$live['institution_id']) {
                return $live;
            }
            return [
                'institution_id' => (int)$live['institution_id'],
                'name' => trim((string)($snap['name'] ?? '')) !== '' ? (string)$snap['name'] : $live['name'],
                'logo_url' => $snap['logo_url'] ?? $live['logo_url'],
                'primary' => self::sanitizeHex((string)($snap['primary'] ?? $live['primary'])),
                'secondary' => self::sanitizeHex((string)($snap['secondary'] ?? $live['secondary'])),
                'accent' => self::sanitizeHex((string)($snap['accent'] ?? $live['accent'])),
            ];
        }
        return $live;
    }
}
