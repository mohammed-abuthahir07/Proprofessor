<?php
declare(strict_types=1);

/**
 * Shared Professor-side academic PDF styling on top of SimplePdf.
 * Presentation only — does not change academic data or ownership checks.
 */
final class ProfessorPdf
{
    /** Navy academic primary */
    public const INK = [15, 23, 42];
    public const MUTED = [71, 85, 105];
    public const HEADER_BG = [15, 23, 42];
    public const ACCENT = [180, 83, 9];
    public const BAND = [30, 41, 59];
    public const RULE = [203, 213, 225];

    /**
     * Resolve institution + department for the authenticated user (tenant-scoped).
     *
     * @return array{institution:array<string,mixed>,department_name:string,college:string,address:string,year:string,semester:string}
     */
    public static function contextForUser(array $user): array
    {
        $instId = (int)($user['institution_id'] ?? 0);
        $inst = $instId > 0
            ? Database::fetch(
                'SELECT id, name, address, city, state, pincode, academic_year, current_semester,
                        affiliation_university, logo_url, settings
                 FROM institutions WHERE id = ?',
                [$instId]
            )
            : null;
        if (!$inst) {
            $inst = [
                'id' => 0,
                'name' => 'Institution',
                'address' => '',
                'city' => '',
                'state' => '',
                'pincode' => '',
                'academic_year' => '',
                'current_semester' => '',
                'affiliation_university' => '',
                'logo_url' => '',
            ];
        }

        $deptName = '';
        $deptId = (int)($user['department_id'] ?? 0);
        if ($deptId > 0 && $instId > 0) {
            $dept = Database::fetch(
                'SELECT name FROM departments WHERE id = ? AND institution_id = ?',
                [$deptId, $instId]
            );
            $deptName = trim((string)($dept['name'] ?? ''));
        }

        $addrParts = array_filter([
            trim((string)($inst['address'] ?? '')),
            trim((string)($inst['city'] ?? '')),
            trim((string)($inst['state'] ?? '')),
            trim((string)($inst['pincode'] ?? '')),
        ], static fn($v) => $v !== '');

        return [
            'institution' => $inst,
            'department_name' => $deptName,
            'college' => trim((string)($inst['name'] ?? '')),
            'address' => implode(', ', $addrParts),
            'year' => trim((string)($inst['academic_year'] ?? '')),
            'semester' => trim((string)($inst['current_semester'] ?? '')),
        ];
    }

    public static function newDocument(): SimplePdf
    {
        if (!class_exists('SimplePdf', false)) {
            require_once __DIR__ . '/SimplePdf.php';
        }
        $pdf = new SimplePdf();
        $pdf->setBottomReserve(54.0);
        return $pdf;
    }

    /**
     * Full-bleed academic header for report-style PDFs.
     *
     * @param array<string,string> $meta optional key=>value lines under the title
     */
    public static function drawReportHeader(
        SimplePdf $pdf,
        string $docTitle,
        string $subtitle = '',
        array $meta = [],
        ?array $ctx = null,
        ?array $user = null
    ): void {
        $ctx = $ctx ?? ($user ? self::contextForUser($user) : [
            'college' => '',
            'department_name' => '',
            'address' => '',
            'year' => '',
            'semester' => '',
        ]);

        $headerH = 92.0;
        if ($subtitle !== '') {
            $headerH += 14;
        }
        $pdf->filledRect(0, 0, $pdf->pageWidth(), $headerH, self::HEADER_BG);
        $pdf->filledRect(0, $headerH - 4, $pdf->pageWidth(), 4, self::ACCENT);

        $pdf->setFont(9, false);
        $pdf->textAt(42, 16, 'ProProfessor AI', [253, 230, 138]);

        $pdf->setFont(18, true);
        $pdf->textAt(42, 34, $docTitle, [255, 255, 255]);

        $y = 56;
        if ($subtitle !== '') {
            $pdf->setFont(11, false);
            $pdf->textAt(42, $y, $subtitle, [226, 232, 240]);
            $y += 16;
        }

        $pdf->setFont(8.5, false);
        $bits = [];
        if (($ctx['college'] ?? '') !== '') {
            $bits[] = (string)$ctx['college'];
        }
        if (($ctx['department_name'] ?? '') !== '') {
            $bits[] = (string)$ctx['department_name'];
        }
        if ($bits) {
            $pdf->textAt(42, $y, implode('  ·  ', $bits), [148, 163, 184]);
        }

        $pdf->moveTo($headerH + 14);

        if ($meta) {
            self::drawMetaGrid($pdf, $meta);
        }
    }

    /**
     * Compact navy band used at the top of continuation pages.
     */
    public static function drawPageBand(SimplePdf $pdf, string $title, string $right = ''): void
    {
        $pdf->filledRect(0, 0, $pdf->pageWidth(), 48, self::BAND);
        $pdf->filledRect(0, 44, $pdf->pageWidth(), 4, self::ACCENT);
        $pdf->setFont(9, false);
        $pdf->textAt(42, 12, 'ProProfessor AI', [253, 230, 138]);
        $pdf->setFont(12, true);
        $pdf->textAt(42, 28, $title, [255, 255, 255]);
        if ($right !== '') {
            $pdf->setFont(8.5, false);
            $pdf->textAt(360, 28, $right, [203, 213, 225]);
        }
        $pdf->moveTo(62);
    }

    /**
     * Centered college letterhead (question bank / attendance style).
     */
    public static function drawLetterhead(
        SimplePdf $pdf,
        array $ctx,
        string $docTitle,
        string $subjectLine = ''
    ): void {
        $ink = self::INK;
        $pdf->setFont(15, true);
        if (($ctx['college'] ?? '') !== '') {
            $pdf->writeCenteredWrapped((string)$ctx['college'], 0, 18, $ink);
        } else {
            $pdf->writeCentered('ProProfessor AI', $ink, 18);
        }
        $pdf->setFont(8.5, false);
        if (($ctx['address'] ?? '') !== '') {
            $pdf->writeCenteredWrapped((string)$ctx['address'], 0, 11, self::MUTED);
        }
        $inst = $ctx['institution'] ?? [];
        if (!empty($inst['affiliation_university'])) {
            $pdf->writeCentered(
                'Affiliated to ' . trim((string)$inst['affiliation_university']),
                self::MUTED,
                11
            );
        }
        if (($ctx['department_name'] ?? '') !== '') {
            $pdf->space(3);
            $pdf->setFont(10.5, true);
            $pdf->writeCentered(strtoupper((string)$ctx['department_name']), $ink, 13);
        }
        $pdf->space(4);
        $pdf->doubleRule($ink);
        $pdf->setFont(13, true);
        $pdf->writeCentered(strtoupper($docTitle), $ink, 16);
        if ($subjectLine !== '') {
            $pdf->setFont(11, true);
            $pdf->writeCenteredWrapped($subjectLine, 0, 14, $ink);
        }
        $pdf->thinRule(self::RULE, 0.7);
    }

    /**
     * @param array<string,string> $pairs
     */
    public static function drawMetaGrid(SimplePdf $pdf, array $pairs): void
    {
        $items = [];
        foreach ($pairs as $k => $v) {
            $v = trim((string)$v);
            if ($v === '') {
                continue;
            }
            $items[] = trim((string)$k) . ': ' . $v;
        }
        if ($items === []) {
            return;
        }
        $pdf->setFont(9, false);
        for ($i = 0; $i < count($items); $i += 2) {
            $pdf->writeTwoColumn($items[$i], $items[$i + 1] ?? '', self::MUTED, 13);
        }
        $pdf->thinRule(self::RULE, 0.6);
        $pdf->space(2);
    }

    public static function sectionTitle(SimplePdf $pdf, string $title): void
    {
        $pdf->ensureSpace(28);
        $pdf->space(4);
        $pdf->setFont(11, true);
        $pdf->writeLine($title, self::BAND, 15);
        $pdf->filledRect(42, $pdf->y() - 2, 48, 2.2, self::ACCENT);
        $pdf->space(6);
        $pdf->setFont(9.5, false);
    }

    public static function noteBox(SimplePdf $pdf, string $line1, string $line2 = ''): void
    {
        $pdf->ensureSpace(48);
        $h = $line2 !== '' ? 40.0 : 28.0;
        $y = $pdf->y();
        $pdf->filledRect(42, $y, $pdf->contentWidth(), $h, [255, 247, 237]);
        $pdf->strokeRect(42, $y, $pdf->contentWidth(), $h, [253, 186, 116], 0.7);
        $pdf->setFont(8, false);
        $pdf->textAt(50, $y + 10, $line1, [146, 64, 14]);
        if ($line2 !== '') {
            $pdf->textAt(50, $y + 22, $line2, [146, 64, 14]);
        }
        $pdf->moveTo($y + $h + 8);
    }

    /**
     * Finish PDF with branded footer + page numbers.
     */
    public static function finalize(
        SimplePdf $pdf,
        ?array $user = null,
        string $extra = ''
    ): string {
        $prof = trim((string)($user['full_name'] ?? ''));
        $parts = ['ProProfessor AI'];
        if ($prof !== '') {
            $parts[] = 'Generated by: ' . $prof;
        }
        $parts[] = date('d M Y, H:i');
        if ($extra !== '') {
            $parts[] = $extra;
        }
        $pdf->stampDocumentFooter(implode('  ·  ', $parts));
        $out = $pdf->output();
        if ($out === '' || !str_starts_with($out, '%PDF')) {
            throw new RuntimeException('PDF generation failed.');
        }
        return $out;
    }

    public static function safeFilename(string $base, string $suffix = '.pdf'): string
    {
        $safe = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $base) ?: 'document';
        $safe = trim($safe, '._-');
        if ($suffix !== '' && !str_ends_with(strtolower($suffix), '.pdf')) {
            $suffix .= '.pdf';
        }
        if ($suffix === '') {
            $suffix = '.pdf';
        }
        if (!str_ends_with(strtolower($safe), '.pdf')) {
            $safe .= $suffix;
        }
        return $safe;
    }
}
