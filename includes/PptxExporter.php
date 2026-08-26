<?php
declare(strict_types=1);

/**
 * Builds a widescreen lecture PowerPoint from AI slide JSON.
 */
final class PptxExporter
{
    private const W = 12192000;
    private const H = 6858000;

    /** @var array{name:string,primary:string,secondary:string,accent:string}|null */
    private static ?array $brand = null;

    /**
     * @param list<array<string,mixed>> $slides
     * @param array{name?:string,primary?:string,secondary?:string,accent?:string}|null $branding
     */
    public static function saveToFile(string $path, string $title, array $slides, ?array $branding = null): void
    {
        self::$brand = [
            'name' => trim((string)($branding['name'] ?? '')) !== '' ? (string)$branding['name'] : 'ProProfessor AI',
            'primary' => self::hex((string)($branding['primary'] ?? '1E3A8A')),
            'secondary' => self::hex((string)($branding['secondary'] ?? '0F172A')),
            'accent' => self::hex((string)($branding['accent'] ?? 'D97706')),
        ];
        try {
            $slides = array_values($slides);
            if ($slides === []) {
                $slides = [[
                    'number' => 1,
                    'title' => $title !== '' ? $title : 'Presentation',
                    'bullets' => ['No slide content yet.'],
                    'speaker_notes' => '',
                    'unit_tag' => '',
                ]];
            }

            $count = count($slides);
            $files = [
                '[Content_Types].xml' => self::contentTypes($count),
                '_rels/.rels' => self::packageRels(),
                'docProps/core.xml' => self::core($title),
                'docProps/app.xml' => self::app($count),
                'ppt/theme/theme1.xml' => self::theme(),
                'ppt/slideMasters/slideMaster1.xml' => self::slideMaster(),
                'ppt/slideMasters/_rels/slideMaster1.xml.rels' => self::slideMasterRels(),
                'ppt/slideLayouts/slideLayout1.xml' => self::slideLayout(),
                'ppt/slideLayouts/_rels/slideLayout1.xml.rels' => self::slideLayoutRels(),
                'ppt/notesMasters/notesMaster1.xml' => self::notesMaster(),
                'ppt/notesMasters/_rels/notesMaster1.xml.rels' => self::notesMasterRels(),
                'ppt/presentation.xml' => self::presentation($count),
                'ppt/_rels/presentation.xml.rels' => self::presentationRels($count),
            ];
            foreach ($slides as $i => $slide) {
                $n = $i + 1;
                $files['ppt/slides/slide' . $n . '.xml'] = self::slideXml($slide, $n, $count, $title);
                $files['ppt/slides/_rels/slide' . $n . '.xml.rels'] = self::slideRels($n);
                $files['ppt/notesSlides/notesSlide' . $n . '.xml'] = self::notesSlideXml($slide, $n);
                $files['ppt/notesSlides/_rels/notesSlide' . $n . '.xml.rels'] = self::notesSlideRels($n);
            }

            if (file_put_contents($path, self::buildZip($files)) === false) {
                throw new RuntimeException('Could not write the PPTX file.');
            }
        } finally {
            self::$brand = null;
        }
    }

    private static function hex(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        if (!preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
            return '1E3A8A';
        }
        return strtoupper($hex);
    }

    private static function primary(): string
    {
        return self::$brand['primary'] ?? '1E3A8A';
    }

    private static function secondary(): string
    {
        return self::$brand['secondary'] ?? '0F172A';
    }

    private static function accent(): string
    {
        return self::$brand['accent'] ?? 'D97706';
    }

    private static function brandName(): string
    {
        return self::$brand['name'] ?? 'ProProfessor AI';
    }

    /**
     * @param array<string, string> $files
     */
    private static function buildZip(array $files): string
    {
        $body = '';
        $central = '';
        $count = 0;
        foreach ($files as $name => $data) {
            $name = str_replace('\\', '/', (string)$name);
            $crc = crc32($data);
            $size = strlen($data);
            $nameLen = strlen($name);
            $offset = strlen($body);
            $flags = 0x0800;
            $body .= pack('VvvvvvVVVvv', 0x04034b50, 20, $flags, 0, 0, 0, $crc, $size, $size, $nameLen, 0)
                . $name
                . $data;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, $flags, 0, 0, 0, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 0, $offset)
                . $name;
            $count++;
        }
        $cdOffset = strlen($body);
        $cdSize = strlen($central);
        return $body . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $cdSize, $cdOffset, 0);
    }

    public static function filename(string $title): string
    {
        $base = trim($title) !== '' ? $title : 'presentation';
        $base = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $base) ?? 'presentation';
        $base = trim($base, '._-') ?: 'presentation';
        return $base . '.pptx';
    }

    private static function xml(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** @param mixed $slide */
    private static function slideTitle(mixed $slide, int $number): string
    {
        if (!is_array($slide)) {
            return 'Slide ' . $number;
        }
        $title = trim((string)($slide['title'] ?? ''));
        return $title !== '' ? $title : ('Slide ' . $number);
    }

    /** @param mixed $slide @return list<string> */
    private static function bullets(mixed $slide): array
    {
        if (!is_array($slide)) {
            return [];
        }
        $raw = $slide['bullets'] ?? [];
        if (is_string($raw) && trim($raw) !== '') {
            $raw = preg_split('/\r\n|\r|\n/', $raw) ?: [$raw];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $item = (string)($item['text'] ?? $item['title'] ?? reset($item) ?: '');
            }
            $item = trim((string)$item);
            if ($item !== '') {
                $out[] = $item;
            }
        }
        return $out;
    }

    /** @param mixed $slide */
    private static function notes(mixed $slide): string
    {
        if (!is_array($slide)) {
            return '';
        }
        return trim((string)($slide['speaker_notes'] ?? $slide['notes'] ?? ''));
    }

    /** @param mixed $slide */
    private static function unitTag(mixed $slide): string
    {
        if (!is_array($slide)) {
            return '';
        }
        return trim((string)($slide['unit_tag'] ?? $slide['unit'] ?? ''));
    }

    /** @param list<string> $bullets */
    private static function layoutKind(int $number, int $total, string $title, array $bullets): string
    {
        $t = strtolower($title);
        if ($number === 1) {
            return 'title';
        }
        if (
            str_contains($t, 'thank')
            || str_contains($t, 'question')
            || preg_match('/\b(summary|recap|conclusion|wrap.?up|takeaway|references)\b/', $t)
        ) {
            return 'close';
        }
        $n = count($bullets);
        $short = 0;
        foreach ($bullets as $b) {
            if (mb_strlen($b) <= 72) {
                $short++;
            }
        }
        if ($n >= 7) {
            return 'columns';
        }
        return 'content';
    }

    /** @param mixed $slide */
    private static function slideXml(mixed $slide, int $number, int $total, string $deckTitle): string
    {
        $title = self::slideTitle($slide, $number);
        $tag = self::unitTag($slide);
        $bullets = self::bullets($slide);
        $id = 2;
        $layoutHint = is_array($slide) ? strtolower(trim((string)($slide['layout'] ?? ''))) : '';
        $kind = match ($layoutHint) {
            'title' => 'title',
            'close', 'thank' => 'close',
            'comparison' => 'comparison',
            'code' => 'code',
            'diagram' => 'diagram',
            'objectives', 'summary', 'quiz', 'content' => 'content',
            default => self::layoutKind($number, $total, $title, $bullets),
        };
        // Prefer structured layouts when data is present
        if (is_array($slide) && !empty($slide['comparison']) && is_array($slide['comparison'])) {
            $kind = 'comparison';
        } elseif (is_array($slide) && !empty($slide['code']) && is_string($slide['code'])) {
            $kind = 'code';
        } elseif ($layoutHint === 'diagram') {
            $kind = 'diagram';
        }

        $shapes = match ($kind) {
            'title' => self::layoutTitle($id, $title, $tag, $bullets, $deckTitle, $number, $total),
            'cards' => self::layoutCards($id, $title, $tag, $bullets, $deckTitle, $number, $total),
            'columns' => self::layoutColumns($id, $title, $tag, $bullets, $deckTitle, $number, $total),
            'close' => self::layoutClose($id, $title, $tag, $bullets, $deckTitle, $number, $total),
            'comparison' => self::layoutComparison($id, $title, $tag, $slide, $deckTitle, $number, $total),
            'code' => self::layoutCode($id, $title, $tag, $slide, $deckTitle, $number, $total),
            'diagram' => self::layoutDiagram($id, $title, $tag, $bullets, $deckTitle, $number, $total),
            default => self::layoutContent($id, $title, $tag, $bullets, $deckTitle, $number, $total),
        };

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld><p:spTree>'
            . '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
            . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
            . $shapes
            . '</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>';
    }

    /** @param list<string> $bullets */
    private static function layoutTitle(int &$id, string $title, string $tag, array $bullets, string $deck, int $number, int $total): string
    {
        $xml = self::rect($id, 0, 0, self::W, self::H, self::secondary());
        $xml .= self::rect($id, 0, 0, 5600000, self::H, self::primary());
        $xml .= self::rect($id, 5600000, 0, 80000, self::H, self::accent());
        $xml .= self::ellipse($id, 9800000, -900000, 3400000, 3400000, 'FFFFFF', 8000);
        $xml .= self::ellipse($id, 10800000, 4800000, 2200000, 2200000, self::primary(), 40000);
        $brandLabel = mb_strtoupper(mb_substr(self::brandName(), 0, 40));
        $xml .= self::text($id, 480000, 1600000, 4700000, 400000, self::p($brandLabel, 1400, 'FBBF24', true, ['track' => 400, 'font' => 'Calibri']), 't');
        $xml .= self::rect($id, 480000, 2100000, 900000, 50000, 'FBBF24');
        $titleSz = mb_strlen($title) > 48 ? 2800 : (mb_strlen($title) > 28 ? 3400 : 4000);
        $xml .= self::text($id, 480000, 2300000, 4800000, 2200000, self::p($title, $titleSz, 'FFFFFF', true, ['line' => 110000, 'font' => 'Calibri Light']), 't');
        if ($tag !== '') {
            $xml .= self::text($id, 480000, 4700000, 4800000, 400000, self::p(mb_strtoupper($tag), 1400, 'FDE68A', true, ['track' => 200]), 't');
        }
        $xml .= self::text($id, 480000, 6200000, 4800000, 300000, self::p('Lecture presentation', 1200, '93C5FD'), 't');

        $lines = array_slice($bullets, 0, 5);
        $paras = self::p('TODAY', 1200, 'FBBF24', true, ['track' => 300, 'after' => 800]);
        if ($lines === []) {
            $paras .= self::p($deck !== '' ? $deck : 'Key ideas for this lecture', 1800, 'E2E8F0', false, ['line' => 130000]);
        } else {
            foreach ($lines as $line) {
                $paras .= self::p($line, 1600, 'E2E8F0', false, ['after' => 700, 'line' => 120000, 'bullet' => true, 'buColor' => 'FBBF24']);
            }
        }
        $xml .= self::text($id, 6200000, 1800000, 5400000, 4200000, $paras, 't');
        $xml .= self::text($id, 6200000, 6200000, 5400000, 300000, self::p(self::pageLabel($number, $total), 1100, '94A3B8', false, ['align' => 'r']), 't');
        return $xml;
    }

    /** @param list<string> $bullets */
    private static function layoutContent(int &$id, string $title, string $tag, array $bullets, string $deck, int $number, int $total): string
    {
        $xml = self::chrome($id, $title, $tag, $deck, $number, $total);
        $paras = '';
        if ($bullets === []) {
            $paras = self::p('Key points for this topic.', 1800, '334155');
        } else {
            $sz = count($bullets) > 6 ? 1500 : 1700;
            foreach ($bullets as $b) {
                $paras .= self::p($b, $sz, '1E293B', false, [
                    'after' => 800,
                    'line' => 122000,
                    'bullet' => true,
                    'buColor' => self::primary(),
                ]);
            }
        }
        $xml .= self::text($id, 560000, 1680000, 11080000, 4600000, $paras, 't');
        return $xml;
    }

    /** @param mixed $slide */
    private static function layoutComparison(int &$id, string $title, string $tag, mixed $slide, string $deck, int $number, int $total): string
    {
        $xml = self::chrome($id, $title, $tag, $deck, $number, $total);
        $cmp = is_array($slide) ? ($slide['comparison'] ?? null) : null;
        $headers = is_array($cmp) ? (array)($cmp['headers'] ?? []) : [];
        $rows = is_array($cmp) ? (array)($cmp['rows'] ?? []) : [];
        if ($headers === [] || $rows === []) {
            return self::layoutContent($id, $title, $tag, self::bullets($slide), $deck, $number, $total);
        }
        $cols = count($headers);
        $cols = max(2, min(4, $cols));
        $tableW = 11080000;
        $colW = (int)($tableW / $cols);
        $x0 = 560000;
        $y0 = 1720000;
        $rowH = 720000;
        for ($c = 0; $c < $cols; $c++) {
            $xml .= self::rect($id, $x0 + $c * $colW, $y0, $colW - 40000, $rowH - 60000, self::primary());
            $xml .= self::text(
                $id,
                $x0 + $c * $colW + 120000,
                $y0 + 160000,
                $colW - 280000,
                $rowH - 280000,
                self::p((string)($headers[$c] ?? ''), 1400, 'FFFFFF', true),
                't'
            );
        }
        $y = $y0 + $rowH;
        foreach (array_slice($rows, 0, 5) as $ri => $row) {
            $bg = $ri % 2 === 0 ? 'FFFFFF' : 'F8FAFC';
            $cells = is_array($row) ? array_values($row) : [$row];
            for ($c = 0; $c < $cols; $c++) {
                $xml .= self::rect($id, $x0 + $c * $colW, $y, $colW - 40000, $rowH - 60000, $bg);
                $xml .= self::text(
                    $id,
                    $x0 + $c * $colW + 120000,
                    $y + 160000,
                    $colW - 280000,
                    $rowH - 280000,
                    self::p((string)($cells[$c] ?? ''), 1300, '1E293B', $c === 0),
                    't'
                );
            }
            $y += $rowH;
        }
        $notes = self::bullets($slide);
        if ($notes) {
            $paras = '';
            foreach (array_slice($notes, 0, 3) as $b) {
                $paras .= self::p($b, 1300, '334155', false, ['after' => 400, 'bullet' => true, 'buColor' => self::accent()]);
            }
            $xml .= self::text($id, 560000, min($y + 100000, 5600000), 11080000, 900000, $paras, 't');
        }
        return $xml;
    }

    /** @param mixed $slide */
    private static function layoutCode(int &$id, string $title, string $tag, mixed $slide, string $deck, int $number, int $total): string
    {
        $xml = self::chrome($id, $title, $tag, $deck, $number, $total);
        $bullets = self::bullets($slide);
        $code = is_array($slide) ? trim((string)($slide['code'] ?? '')) : '';
        $paras = '';
        foreach (array_slice($bullets, 0, 4) as $b) {
            $paras .= self::p($b, 1400, '1E293B', false, [
                'after' => 500,
                'line' => 118000,
                'bullet' => true,
                'buColor' => self::primary(),
            ]);
        }
        $xml .= self::text($id, 560000, 1680000, 5200000, 4200000, $paras, 't');
        $xml .= self::roundRect($id, 6000000, 1680000, 5600000, 4300000, '0F172A', 8000, false);
        $codeParas = '';
        $lines = $code !== '' ? (preg_split('/\r\n|\r|\n/', $code) ?: []) : ['// example'];
        foreach (array_slice($lines, 0, 14) as $line) {
            $codeParas .= self::p($line === '' ? ' ' : $line, 1200, 'E2E8F0', false, [
                'after' => 200,
                'line' => 110000,
                'font' => 'Consolas',
            ]);
        }
        $xml .= self::text($id, 6200000, 1860000, 5200000, 4000000, $codeParas, 't');
        return $xml;
    }

    /** @param list<string> $bullets */
    private static function layoutDiagram(int &$id, string $title, string $tag, array $bullets, string $deck, int $number, int $total): string
    {
        $xml = self::chrome($id, $title, $tag, $deck, $number, $total);
        $steps = [];
        foreach ($bullets as $b) {
            $b = trim($b);
            if ($b === '' || $b === '↓' || $b === '↑' || $b === '→') {
                continue;
            }
            $b = preg_replace('/^[│├└─\s↓↑→]+/u', '', $b) ?? $b;
            $b = trim($b);
            if ($b !== '') {
                $steps[] = $b;
            }
        }
        $steps = array_slice($steps, 0, 6);
        if (count($steps) < 2) {
            return self::layoutContent($id, $title, $tag, $bullets, $deck, $number, $total);
        }
        $boxH = 700000;
        $gap = 180000;
        $totalH = count($steps) * $boxH + (count($steps) - 1) * $gap;
        $y = (int)(1680000 + max(0, (4500000 - $totalH) / 2));
        $x = 2800000;
        $w = 6600000;
        foreach ($steps as $i => $step) {
            $xml .= self::roundRect($id, $x, $y, $w, $boxH, 'FFFFFF', 8000, true);
            $xml .= self::rect($id, $x, $y, 90000, $boxH, self::accent());
            $xml .= self::text(
                $id,
                $x + 280000,
                $y + 180000,
                $w - 480000,
                $boxH - 280000,
                self::p($step, 1600, '1E293B', true, ['align' => 'ctr']),
                't'
            );
            $y += $boxH;
            if ($i < count($steps) - 1) {
                $xml .= self::text(
                    $id,
                    $x,
                    $y,
                    $w,
                    $gap,
                    self::p('v', 1600, self::primary(), true, ['align' => 'ctr']),
                    't'
                );
                $y += $gap;
            }
        }
        return $xml;
    }

    /** @param list<string> $bullets */
    private static function layoutColumns(int &$id, string $title, string $tag, array $bullets, string $deck, int $number, int $total): string
    {
        $xml = self::chrome($id, $title, $tag, $deck, $number, $total);
        $mid = (int)ceil(count($bullets) / 2);
        $cols = [array_slice($bullets, 0, $mid), array_slice($bullets, $mid)];
        $x = [500000, 6350000];
        foreach ($cols as $i => $items) {
            $paras = '';
            foreach ($items as $b) {
                $paras .= self::p($b, 1500, '1E293B', false, [
                    'after' => 700,
                    'line' => 120000,
                    'bullet' => true,
                    'buColor' => self::primary(),
                ]);
            }
            $xml .= self::roundRect($id, $x[$i], 1680000, 5340000, 4500000, 'FFFFFF', 8000, true);
            $xml .= self::text($id, $x[$i] + 220000, 1860000, 4900000, 4140000, $paras, 't');
        }
        return $xml;
    }

    /** @param list<string> $bullets */
    private static function layoutCards(int &$id, string $title, string $tag, array $bullets, string $deck, int $number, int $total): string
    {
        $xml = self::chrome($id, $title, $tag, $deck, $number, $total);
        $n = count($bullets);
        $cols = $n <= 3 ? $n : ($n === 4 ? 2 : 3);
        $rows = (int)ceil($n / $cols);
        $gap = 220000;
        $areaX = 480000;
        $areaY = 1680000;
        $areaW = 11230000;
        $areaH = 4520000;
        $cw = (int)(($areaW - ($cols - 1) * $gap) / $cols);
        $ch = (int)(($areaH - ($rows - 1) * $gap) / $rows);
        foreach ($bullets as $i => $b) {
            $r = intdiv($i, $cols);
            $c = $i % $cols;
            $x = $areaX + $c * ($cw + $gap);
            $y = $areaY + $r * ($ch + $gap);
            $xml .= self::roundRect($id, $x, $y, $cw, $ch, 'FFFFFF', 9000, true);
            $xml .= self::rect($id, $x, $y, 70000, $ch, self::accent());
            $num = str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
            $xml .= self::text($id, $x + 220000, $y + 180000, $cw - 400000, 400000, self::p($num, 1400, self::accent(), true, ['track' => 200]), 't');
            $xml .= self::text(
                $id,
                $x + 220000,
                $y + 620000,
                $cw - 400000,
                $ch - 820000,
                self::p($b, mb_strlen($b) > 80 ? 1400 : 1600, '1E293B', false, ['line' => 120000]),
                't'
            );
        }
        return $xml;
    }

    /** @param list<string> $bullets */
    private static function layoutClose(int &$id, string $title, string $tag, array $bullets, string $deck, int $number, int $total): string
    {
        $xml = self::rect($id, 0, 0, self::W, self::H, self::secondary());
        $xml .= self::rect($id, 0, 0, self::W, 90000, self::accent());
        $xml .= self::ellipse($id, -800000, 4200000, 2800000, 2800000, self::primary(), 50000);
        $xml .= self::text($id, 900000, 1600000, 10400000, 400000, self::p($tag !== '' ? mb_strtoupper($tag) : 'WRAP-UP', 1400, 'FBBF24', true, ['align' => 'ctr', 'track' => 400]), 't');
        $xml .= self::text($id, 900000, 2100000, 10400000, 1400000, self::p($title, mb_strlen($title) > 40 ? 3200 : 4000, 'FFFFFF', true, ['align' => 'ctr', 'line' => 110000, 'font' => 'Calibri Light']), 'ctr');
        $paras = '';
        foreach (array_slice($bullets, 0, 5) as $b) {
            $paras .= self::p($b, 1600, 'CBD5E1', false, ['align' => 'ctr', 'after' => 500]);
        }
        if ($paras !== '') {
            $xml .= self::text($id, 1800000, 3700000, 8600000, 2000000, $paras, 't');
        }
        $xml .= self::text($id, 900000, 6000000, 10400000, 400000, self::p(($deck !== '' ? $deck . '  ·  ' : '') . self::brandName(), 1300, '94A3B8', false, ['align' => 'ctr']), 't');
        return $xml;
    }

    private static function chrome(int &$id, string $title, string $tag, string $deck, int $number, int $total): string
    {
        $xml = self::rect($id, 0, 0, self::W, self::H, 'F1F5F9');
        $xml .= self::rect($id, 0, 0, 120000, self::H, self::primary());
        $xml .= self::rect($id, 0, 0, self::W, 1480000, self::primary());
        $xml .= self::rect($id, 0, 1480000, self::W, 50000, self::accent());
        $titleSz = mb_strlen($title) > 62 ? 2200 : (mb_strlen($title) > 42 ? 2600 : 2800);
        $xml .= self::text($id, 420000, 280000, 9000000, 900000, self::p($title, $titleSz, 'FFFFFF', true, ['line' => 108000, 'font' => 'Calibri Light']), 'ctr');
        if ($tag !== '') {
            $xml .= self::roundRect($id, 9600000, 480000, 2100000, 460000, '172554', 20000);
            $xml .= self::text($id, 9600000, 500000, 2100000, 420000, self::p($tag, 1200, 'FDE68A', true, ['align' => 'ctr']), 'ctr');
        }
        $xml .= self::rect($id, 0, 6520000, self::W, 338000, 'E2E8F0');
        $left = $deck !== '' ? $deck : self::brandName();
        $xml .= self::text($id, 400000, 6560000, 7000000, 280000, self::p($left, 1100, '64748B'), 'ctr');
        $xml .= self::text($id, 7400000, 6560000, 4300000, 280000, self::p(self::brandName() . '   ·   ' . self::pageLabel($number, $total), 1100, '64748B', false, ['align' => 'r']), 'ctr');
        return $xml;
    }

    private static function pageLabel(int $number, int $total): string
    {
        return str_pad((string)$number, 2, '0', STR_PAD_LEFT) . '  /  ' . str_pad((string)$total, 2, '0', STR_PAD_LEFT);
    }

    private static function rect(int &$id, int $x, int $y, int $w, int $h, string $fill): string
    {
        $n = $id++;
        return '<p:sp><p:nvSpPr><p:cNvPr id="' . $n . '" name="Shape' . $n . '"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $w . '" cy="' . $h . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>'
            . '<a:solidFill><a:srgbClr val="' . $fill . '"/></a:solidFill><a:ln><a:noFill/></a:ln></p:spPr></p:sp>';
    }

    private static function roundRect(int &$id, int $x, int $y, int $w, int $h, string $fill, int $adj = 8000, bool $shadow = false): string
    {
        $n = $id++;
        $fx = $shadow
            ? '<a:effectLst><a:outerShdw blurRad="76000" dist="25400" dir="2700000" algn="tl" rotWithShape="0">'
              . '<a:prstClr val="black"><a:alpha val="16000"/></a:prstClr></a:outerShdw></a:effectLst>'
            : '';
        return '<p:sp><p:nvSpPr><p:cNvPr id="' . $n . '" name="Card' . $n . '"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $w . '" cy="' . $h . '"/></a:xfrm>'
            . '<a:prstGeom prst="roundRect"><a:avLst><a:gd name="adj" fmla="val ' . $adj . '"/></a:avLst></a:prstGeom>'
            . '<a:solidFill><a:srgbClr val="' . $fill . '"/></a:solidFill><a:ln><a:noFill/></a:ln>'
            . $fx . '</p:spPr></p:sp>';
    }

    private static function ellipse(int &$id, int $x, int $y, int $w, int $h, string $fill, int $alpha = 100000): string
    {
        $n = $id++;
        return '<p:sp><p:nvSpPr><p:cNvPr id="' . $n . '" name="Orb' . $n . '"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $w . '" cy="' . $h . '"/></a:xfrm>'
            . '<a:prstGeom prst="ellipse"><a:avLst/></a:prstGeom>'
            . '<a:solidFill><a:srgbClr val="' . $fill . '"><a:alpha val="' . $alpha . '"/></a:srgbClr></a:solidFill>'
            . '<a:ln><a:noFill/></a:ln></p:spPr></p:sp>';
    }

    private static function text(int &$id, int $x, int $y, int $w, int $h, string $paras, string $anchor = 't'): string
    {
        $n = $id++;
        return '<p:sp><p:nvSpPr><p:cNvPr id="' . $n . '" name="Text' . $n . '"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $w . '" cy="' . $h . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/><a:ln><a:noFill/></a:ln></p:spPr>'
            . '<p:txBody><a:bodyPr wrap="square" lIns="0" tIns="0" rIns="0" bIns="0" rtlCol="0" anchor="' . $anchor . '"/>'
            . '<a:lstStyle/>' . $paras . '</p:txBody></p:sp>';
    }

    /** @param array<string,mixed> $opt */
    private static function p(string $text, int $sz, string $color, bool $bold = false, array $opt = []): string
    {
        $align = (string)($opt['align'] ?? 'l');
        $font = (string)($opt['font'] ?? 'Calibri');
        $after = (int)($opt['after'] ?? 0);
        $before = (int)($opt['before'] ?? 0);
        $line = (int)($opt['line'] ?? 0);
        $track = (int)($opt['track'] ?? 0);
        $bullet = !empty($opt['bullet']);
        $buColor = (string)($opt['buColor'] ?? self::primary());
        $spc = '';
        if ($line) {
            $spc .= '<a:lnSpc><a:spcPct val="' . $line . '"/></a:lnSpc>';
        }
        if ($before) {
            $spc .= '<a:spcBef><a:spcPts val="' . $before . '"/></a:spcBef>';
        }
        if ($after) {
            $spc .= '<a:spcAft><a:spcPts val="' . $after . '"/></a:spcAft>';
        }
        $bu = $bullet
            ? '<a:buFont typeface="Arial"/><a:buClr><a:srgbClr val="' . $buColor . '"/></a:buClr><a:buChar char="•"/>'
            : '<a:buNone/>';
        $kern = $track ? ' spc="' . $track . '"' : '';
        return '<a:p><a:pPr algn="' . $align . '" marL="' . ($bullet ? '285750' : '0') . '" indent="' . ($bullet ? '-285750' : '0') . '">'
            . $spc . $bu . '</a:pPr>'
            . '<a:r><a:rPr lang="en-US" sz="' . $sz . '" b="' . ($bold ? '1' : '0') . '"' . $kern . ' dirty="0">'
            . '<a:solidFill><a:srgbClr val="' . $color . '"/></a:solidFill>'
            . '<a:latin typeface="' . self::xml($font) . '"/><a:ea typeface="Calibri"/><a:cs typeface="Calibri"/>'
            . '</a:rPr><a:t>' . self::xml($text) . '</a:t></a:r>'
            . '<a:endParaRPr lang="en-US" sz="' . $sz . '" dirty="0"/></a:p>';
    }

    private static function contentTypes(int $count): string
    {
        $overrides = [
            '<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>',
            '<Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>',
            '<Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>',
            '<Override PartName="/ppt/notesMasters/notesMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.notesMaster+xml"/>',
            '<Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>',
            '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>',
        ];
        for ($i = 1; $i <= $count; $i++) {
            $overrides[] = '<Override PartName="/ppt/slides/slide' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
            $overrides[] = '<Override PartName="/ppt/notesSlides/notesSlide' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.notesSlide+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . implode('', $overrides)
            . '</Types>';
    }

    private static function packageRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private static function core(string $title): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $t = self::xml($title !== '' ? $title : 'Presentation');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
            . ' xmlns:dcterms="http://purl.org/dc/terms/"'
            . ' xmlns:dcmitype="http://purl.org/dc/dcmitype/"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . $t . '</dc:title>'
            . '<dc:creator>' . self::xml(self::brandName()) . '</dc:creator>'
            . '<cp:lastModifiedBy>' . self::xml(self::brandName()) . '</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private static function app(int $count): string
    {
        $titles = [];
        for ($i = 1; $i <= $count; $i++) {
            $titles[] = '<vt:lpstr>Slide ' . $i . '</vt:lpstr>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"'
            . ' xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>' . self::xml(self::brandName()) . '</Application>'
            . '<PresentationFormat>Widescreen</PresentationFormat>'
            . '<Slides>' . $count . '</Slides>'
            . '<Notes>' . $count . '</Notes>'
            . '<HiddenSlides>0</HiddenSlides>'
            . '<TitlesOfParts><vt:vector size="' . $count . '" baseType="lpstr">' . implode('', $titles) . '</vt:vector></TitlesOfParts>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Slides</vt:lpstr></vt:variant><vt:variant><vt:i4>' . $count . '</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '</Properties>';
    }

    private static function presentation(int $count): string
    {
        $ids = '';
        for ($i = 1; $i <= $count; $i++) {
            $ids .= '<p:sldId id="' . (255 + $i) . '" r:id="rId' . (2 + $i) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst>'
            . '<p:notesMasterIdLst><p:notesMasterId r:id="rId2"/></p:notesMasterIdLst>'
            . '<p:sldIdLst>' . $ids . '</p:sldIdLst>'
            . '<p:sldSz cx="' . self::W . '" cy="' . self::H . '" type="screen16x9"/>'
            . '<p:notesSz cx="6858000" cy="9144000"/>'
            . '</p:presentation>';
    }

    private static function presentationRels(int $count): string
    {
        $rels = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesMaster" Target="notesMasters/notesMaster1.xml"/>';
        for ($i = 1; $i <= $count; $i++) {
            $rels .= '<Relationship Id="rId' . (2 + $i) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide' . $i . '.xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    private static function slideMasterRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>'
            . '</Relationships>';
    }

    private static function slideLayoutRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>'
            . '</Relationships>';
    }

    private static function notesMasterRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>'
            . '</Relationships>';
    }

    private static function slideRels(int $n): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesSlide" Target="../notesSlides/notesSlide' . $n . '.xml"/>'
            . '</Relationships>';
    }

    private static function notesSlideRels(int $n): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="../slides/slide' . $n . '.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesMaster" Target="../notesMasters/notesMaster1.xml"/>'
            . '</Relationships>';
    }

    /** @param mixed $slide */
    private static function notesSlideXml(mixed $slide, int $number): string
    {
        $notes = self::notes($slide);
        $paras = '';
        if ($notes === '') {
            $paras = self::p('', 1200, '000000');
        } else {
            foreach (preg_split('/\r\n|\r|\n/', $notes) ?: [$notes] as $line) {
                $paras .= self::p($line, 1400, '1E293B', false, ['after' => 400, 'line' => 120000]);
            }
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:notes xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld><p:spTree>'
            . '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
            . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
            . '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Slide Image Placeholder 1"/><p:cNvSpPr><a:spLocks noGrp="1" noRot="1" noChangeAspect="1"/></p:cNvSpPr>'
            . '<p:nvPr><p:ph type="sldImg"/></p:nvPr></p:nvSpPr><p:spPr/></p:sp>'
            . '<p:sp><p:nvSpPr><p:cNvPr id="3" name="Notes Placeholder 2"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr>'
            . '<p:nvPr><p:ph type="body" idx="1"/></p:nvPr></p:nvSpPr><p:spPr/>'
            . '<p:txBody><a:bodyPr/><a:lstStyle/>' . $paras . '</p:txBody></p:sp>'
            . '</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:notes>';
    }

    private static function slideMaster(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld><p:bg><p:bgPr><a:solidFill><a:srgbClr val="F1F5F9"/></a:solidFill><a:effectLst/></p:bgPr></p:bg>'
            . '<p:spTree>'
            . '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
            . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
            . '</p:spTree></p:cSld>'
            . '<p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>'
            . '<p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst>'
            . '</p:sldMaster>';
    }

    private static function slideLayout(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1">'
            . '<p:cSld name="Blank"><p:spTree>'
            . '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
            . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
            . '</p:spTree></p:cSld>'
            . '<p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>'
            . '</p:sldLayout>';
    }

    private static function notesMaster(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:notesMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld><p:spTree>'
            . '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
            . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
            . '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Header Placeholder 1"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr>'
            . '<p:nvPr><p:ph type="hdr" sz="quarter"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr/></a:p></p:txBody></p:sp>'
            . '<p:sp><p:nvSpPr><p:cNvPr id="3" name="Slide Image Placeholder 2"/><p:cNvSpPr><a:spLocks noGrp="1" noRot="1" noChangeAspect="1"/></p:cNvSpPr>'
            . '<p:nvPr><p:ph type="sldImg" idx="1"/></p:nvPr></p:nvSpPr><p:spPr/></p:sp>'
            . '<p:sp><p:nvSpPr><p:cNvPr id="4" name="Notes Placeholder 3"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr>'
            . '<p:nvPr><p:ph type="body" sz="quarter" idx="2"/></p:nvPr></p:nvSpPr><p:spPr/>'
            . '<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr/></a:p></p:txBody></p:sp>'
            . '<p:sp><p:nvSpPr><p:cNvPr id="5" name="Footer Placeholder 4"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr>'
            . '<p:nvPr><p:ph type="ftr" sz="quarter" idx="3"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr/></a:p></p:txBody></p:sp>'
            . '<p:sp><p:nvSpPr><p:cNvPr id="6" name="Slide Number Placeholder 5"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr>'
            . '<p:nvPr><p:ph type="sldNum" sz="quarter" idx="4"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:fld type="slidenum"><a:rPr lang="en-US"/><a:t>1</a:t></a:fld><a:endParaRPr/></a:p></p:txBody></p:sp>'
            . '<p:sp><p:nvSpPr><p:cNvPr id="7" name="Date Placeholder 6"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr>'
            . '<p:nvPr><p:ph type="dt" sz="quarter" idx="5"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr/></a:p></p:txBody></p:sp>'
            . '</p:spTree></p:cSld>'
            . '<p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>'
            . '<p:notesStyle><a:lvl1pPr marL="0" algn="l" defTabSz="914400" rtl="0" eaLnBrk="1" latinLnBrk="0" hangingPunct="1">'
            . '<a:defRPr sz="1200" kern="1200"><a:solidFill><a:schemeClr val="tx1"/></a:solidFill>'
            . '<a:latin typeface="+mn-lt"/><a:ea typeface="+mn-ea"/><a:cs typeface="+mn-cs"/></a:defRPr>'
            . '</a:lvl1pPr></p:notesStyle>'
            . '</p:notesMaster>';
    }

    private static function theme(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="ProProfessor Lecture">'
            . '<a:themeElements>'
            . '<a:clrScheme name="Institution">'
            . '<a:dk1><a:srgbClr val="' . self::secondary() . '"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1>'
            . '<a:dk2><a:srgbClr val="' . self::primary() . '"/></a:dk2><a:lt2><a:srgbClr val="F1F5F9"/></a:lt2>'
            . '<a:accent1><a:srgbClr val="' . self::primary() . '"/></a:accent1><a:accent2><a:srgbClr val="' . self::accent() . '"/></a:accent2>'
            . '<a:accent3><a:srgbClr val="0EA5E9"/></a:accent3><a:accent4><a:srgbClr val="059669"/></a:accent4>'
            . '<a:accent5><a:srgbClr val="7C3AED"/></a:accent5><a:accent6><a:srgbClr val="E11D48"/></a:accent6>'
            . '<a:hlink><a:srgbClr val="1D4ED8"/></a:hlink><a:folHlink><a:srgbClr val="' . self::primary() . '"/></a:folHlink>'
            . '</a:clrScheme>'
            . '<a:fontScheme name="ProProfessor">'
            . '<a:majorFont><a:latin typeface="Calibri Light"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont>'
            . '<a:minorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont>'
            . '</a:fontScheme>'
            . '<a:fmtScheme name="ProProfessor">'
            . '<a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill>'
            . '<a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"><a:tint val="50000"/><a:satMod val="300000"/></a:schemeClr></a:gs>'
            . '<a:gs pos="35000"><a:schemeClr val="phClr"><a:tint val="37000"/><a:satMod val="300000"/></a:schemeClr></a:gs>'
            . '<a:gs pos="100000"><a:schemeClr val="phClr"><a:tint val="15000"/><a:satMod val="350000"/></a:schemeClr></a:gs></a:gsLst>'
            . '<a:lin ang="16200000" scaled="1"/></a:gradFill>'
            . '<a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"><a:tint val="100000"/><a:satMod val="130000"/></a:schemeClr></a:gs>'
            . '<a:gs pos="100000"><a:schemeClr val="phClr"><a:shade val="80000"/><a:satMod val="130000"/></a:schemeClr></a:gs></a:gsLst>'
            . '<a:lin ang="16200000" scaled="0"/></a:gradFill></a:fillStyleLst>'
            . '<a:lnStyleLst>'
            . '<a:ln w="9525" cap="flat" cmpd="sng" algn="ctr"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln>'
            . '<a:ln w="25400" cap="flat" cmpd="sng" algn="ctr"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln>'
            . '<a:ln w="38100" cap="flat" cmpd="sng" algn="ctr"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln>'
            . '</a:lnStyleLst>'
            . '<a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle>'
            . '<a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst>'
            . '<a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill>'
            . '<a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"><a:tint val="40000"/><a:satMod val="350000"/></a:schemeClr></a:gs>'
            . '<a:gs pos="100000"><a:schemeClr val="phClr"><a:shade val="20000"/><a:satMod val="255000"/></a:schemeClr></a:gs></a:gsLst>'
            . '<a:path path="circle"><a:fillToRect l="50000" t="-80000" r="50000" b="180000"/></a:path></a:gradFill>'
            . '<a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"><a:tint val="80000"/><a:satMod val="300000"/></a:schemeClr></a:gs>'
            . '<a:gs pos="100000"><a:schemeClr val="phClr"><a:shade val="30000"/><a:satMod val="200000"/></a:schemeClr></a:gs></a:gsLst>'
            . '<a:path path="circle"><a:fillToRect l="50000" t="50000" r="50000" b="50000"/></a:path></a:gradFill></a:bgFillStyleLst>'
            . '</a:fmtScheme></a:themeElements><a:objectDefaults/><a:extraClrSchemeLst/>'
            . '</a:theme>';
    }
}
