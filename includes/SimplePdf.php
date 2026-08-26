<?php
declare(strict_types=1);

/**
 * Minimal PDF writer (no Composer deps). Built-in Helvetica + vector tables/headers.
 * Good enough for accreditation-style course plan exports on XAMPP.
 */
final class SimplePdf
{
    private float $w = 595.28; // A4
    private float $h = 841.89;
    private float $margin = 42.0;
    private float $y = 0.0;
    private int $page = 0;
    /** @var list<string> */
    private array $pages = [];
    private string $buf = '';
    private float $fontSize = 11.0;
    private string $font = 'Helvetica';
    private bool $bold = false;
    /** When true, output() skips its built-in page footer (stampPageNumbers already applied). */
    private bool $pageNumbersStamped = false;

    public function __construct()
    {
        $this->addPage();
    }

    public function addPage(): void
    {
        if ($this->page > 0) {
            $this->pages[$this->page - 1] = $this->buf;
        }
        $this->page++;
        $this->buf = '';
        $this->y = $this->margin;
        $this->setFont(11, false);
    }

    public function setFont(float $size, bool $bold = false): void
    {
        $this->fontSize = $size;
        $this->bold = $bold;
        $this->font = $bold ? 'Helvetica-Bold' : 'Helvetica';
    }

    public function y(): float
    {
        return $this->y;
    }

    public function pageWidth(): float
    {
        return $this->w;
    }

    public function contentWidth(): float
    {
        return $this->w - (2 * $this->margin);
    }

    public function ensureSpace(float $needed): void
    {
        if ($this->y + $needed > $this->h - $this->margin) {
            $this->addPage();
        }
    }

    public function filledRect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        [$r, $g, $b] = $rgb;
        $this->buf .= sprintf(
            "%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f 0 g\n",
            $r / 255,
            $g / 255,
            $b / 255,
            $x,
            $this->h - $y - $h,
            $w,
            $h
        );
    }

    public function strokeRect(float $x, float $y, float $w, float $h, array $rgb = [180, 180, 180], float $lw = 0.6): void
    {
        [$r, $g, $b] = $rgb;
        $this->buf .= sprintf(
            "%.3F %.3F %.3F RG %.2F w %.2F %.2F %.2F %.2F re S 0 G\n",
            $r / 255,
            $g / 255,
            $b / 255,
            $lw,
            $x,
            $this->h - $y - $h,
            $w,
            $h
        );
    }

    public function textAt(float $x, float $y, string $text, ?array $rgb = null): void
    {
        $text = $this->sanitize($text);
        $color = '';
        if ($rgb) {
            $color = sprintf("%.3F %.3F %.3F rg ", $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
        }
        $this->buf .= sprintf(
            "BT /F%s %.2F Tf %s%.2F %.2F Td (%s) Tj ET\n",
            $this->bold ? '2' : '1',
            $this->fontSize,
            $color,
            $x,
            $this->h - $y - $this->fontSize,
            $this->escape($text)
        );
        if ($rgb) {
            $this->buf .= "0 g\n";
        }
    }

    public function writeLine(string $text, ?array $rgb = null, float $lineH = 0.0): void
    {
        if ($lineH <= 0) {
            $lineH = $this->fontSize + 5;
        }
        $this->ensureSpace($lineH);
        $this->textAt($this->margin, $this->y, $text, $rgb);
        $this->y += $lineH;
    }

    /** Center a single line within the content area. */
    public function writeCentered(string $text, ?array $rgb = null, float $lineH = 0.0): void
    {
        if ($lineH <= 0) {
            $lineH = $this->fontSize + 5;
        }
        $this->ensureSpace($lineH);
        $clean = $this->sanitize($text);
        $tw = $this->textWidth($clean);
        $x = $this->margin + max(0.0, ($this->contentWidth() - $tw) / 2);
        $this->textAt($x, $this->y, $clean, $rgb);
        $this->y += $lineH;
    }

    /** Center wrapped lines within the content area. */
    public function writeCenteredWrapped(string $text, float $maxW = 0.0, float $lineH = 0.0, ?array $rgb = null): void
    {
        if ($maxW <= 0) {
            $maxW = $this->contentWidth();
        }
        if ($lineH <= 0) {
            $lineH = $this->fontSize + 4;
        }
        foreach ($this->wrap($text, $maxW) as $line) {
            $this->writeCentered($line, $rgb, $lineH);
        }
    }

    /** Left + right meta pair on one row (college paper style). */
    public function writeTwoColumn(string $left, string $right, ?array $rgb = null, float $lineH = 0.0): void
    {
        if ($lineH <= 0) {
            $lineH = $this->fontSize + 5;
        }
        $this->ensureSpace($lineH);
        $half = $this->contentWidth() / 2;
        $this->textAt($this->margin, $this->y, $left, $rgb);
        if ($right !== '') {
            $rx = $this->margin + $half;
            $this->textAt($rx, $this->y, $right, $rgb);
        }
        $this->y += $lineH;
    }

    /** Indented wrapped text (e.g. MCQ options). */
    public function writeIndented(string $text, float $indent = 24.0, float $maxW = 0.0, float $lineH = 0.0, ?array $rgb = null): void
    {
        if ($maxW <= 0) {
            $maxW = $this->contentWidth() - $indent;
        }
        if ($lineH <= 0) {
            $lineH = $this->fontSize + 4;
        }
        $x = $this->margin + $indent;
        foreach ($this->wrap($text, $maxW) as $line) {
            $this->ensureSpace($lineH);
            $this->textAt($x, $this->y, $line, $rgb);
            $this->y += $lineH;
        }
    }

    public function writeWrapped(string $text, float $maxW = 0.0, float $lineH = 0.0, ?array $rgb = null): void
    {
        if ($maxW <= 0) {
            $maxW = $this->contentWidth();
        }
        if ($lineH <= 0) {
            $lineH = $this->fontSize + 4;
        }
        foreach ($this->wrap($text, $maxW) as $line) {
            $this->writeLine($line, $rgb, $lineH);
        }
    }

    /** Thin horizontal rule (college exam paper style). */
    public function thinRule(array $rgb = [40, 40, 40], float $thickness = 0.8): void
    {
        $this->ensureSpace(10);
        $this->filledRect($this->margin, $this->y + 2, $this->contentWidth(), $thickness, $rgb);
        $this->y += 10;
    }

    /** Double rule under college header. */
    public function doubleRule(array $rgb = [30, 30, 30]): void
    {
        $this->ensureSpace(14);
        $this->filledRect($this->margin, $this->y, $this->contentWidth(), 1.2, $rgb);
        $this->filledRect($this->margin, $this->y + 3.5, $this->contentWidth(), 0.6, $rgb);
        $this->y += 12;
    }

    public function space(float $dy): void
    {
        $this->y += $dy;
    }

    public function moveTo(float $y): void
    {
        $this->y = $y;
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     * @param list<float> $colWeights
     */
    public function table(array $headers, array $rows, array $colWeights, float $fontSize = 9.0): void
    {
        $total = array_sum($colWeights) ?: 1;
        $width = $this->contentWidth();
        $cols = [];
        foreach ($colWeights as $w) {
            $cols[] = ($w / $total) * $width;
        }

        $drawHeader = function () use ($headers, $cols, $fontSize): void {
            $rowH = $fontSize + 10;
            $this->ensureSpace($rowH + 2);
            $x = $this->margin;
            $this->filledRect($x, $this->y, $this->contentWidth(), $rowH, [30, 41, 79]);
            $this->setFont($fontSize, true);
            $cx = $x;
            foreach ($headers as $i => $h) {
                $this->textAt($cx + 4, $this->y + 5, $this->truncate($h, $cols[$i] - 8), [255, 255, 255]);
                $cx += $cols[$i];
            }
            $this->y += $rowH;
            $this->setFont($fontSize, false);
        };

        $drawHeader();

        foreach ($rows as $ri => $row) {
            // Measure wrapped cell heights
            $cellLines = [];
            $maxLines = 1;
            foreach ($row as $i => $cell) {
                $lines = $this->wrap((string)$cell, max(12.0, $cols[$i] - 8));
                $cellLines[$i] = $lines;
                $maxLines = max($maxLines, count($lines));
            }
            $rowH = max($fontSize + 8, ($fontSize + 3) * $maxLines + 6);
            if ($this->y + $rowH > $this->h - $this->margin) {
                $this->addPage();
                $drawHeader();
            }
            $bg = $ri % 2 === 0 ? [248, 250, 252] : [255, 255, 255];
            $this->filledRect($this->margin, $this->y, $this->contentWidth(), $rowH, $bg);
            $this->strokeRect($this->margin, $this->y, $this->contentWidth(), $rowH, [210, 214, 220], 0.4);
            $cx = $this->margin;
            $this->setFont($fontSize, false);
            foreach ($cellLines as $i => $lines) {
                $ly = $this->y + 4;
                foreach ($lines as $line) {
                    $this->textAt($cx + 4, $ly, $line, [30, 30, 30]);
                    $ly += $fontSize + 3;
                }
                // vertical grid
                $this->buf .= sprintf(
                    "0.75 0.75 0.78 RG 0.4 w %.2F %.2F m %.2F %.2F l S 0 G\n",
                    $cx,
                    $this->h - $this->y,
                    $cx,
                    $this->h - $this->y - $rowH
                );
                $cx += $cols[$i];
            }
            $this->y += $rowH;
        }
    }

    public function hRule(array $rgb = [99, 102, 241]): void
    {
        $this->ensureSpace(8);
        $this->filledRect($this->margin, $this->y, $this->contentWidth(), 2.2, $rgb);
        $this->y += 10;
    }

    /** Stamp centered "— N —" page numbers on every page (call once before output()). */
    public function stampPageNumbers(): void
    {
        $this->pages[$this->page - 1] = $this->buf;
        $total = count($this->pages);
        $fontSize = 9.0;
        $prevSize = $this->fontSize;
        $prevBold = $this->bold;
        $this->setFont($fontSize, false);
        for ($i = 0; $i < $total; $i++) {
            $label = '- ' . ($i + 1) . ' -';
            $tw = $this->textWidth($label);
            $x = ($this->w - $tw) / 2;
            // PDF user space: origin bottom-left.
            $this->pages[$i] .= sprintf(
                "BT /F1 %.2F Tf 0.35 0.35 0.38 rg %.2F %.2F Td (%s) Tj ET 0 g\n",
                $fontSize,
                $x,
                28.0,
                $this->escape($this->sanitize($label))
            );
        }
        $this->setFont($prevSize, $prevBold);
        $this->buf = $this->pages[$this->page - 1];
        $this->pageNumbersStamped = true;
    }

    public function output(): string
    {
        $this->pages[$this->page - 1] = $this->buf;
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $pageCount = count($this->pages);
        // object 3,4 = fonts; pages start at 5
        $font1 = 3;
        $font2 = 4;
        $pageObjStart = 5;
        for ($i = 0; $i < $pageCount; $i++) {
            $contentObj = $pageObjStart + ($i * 2) + 1;
            $kids[] = ($pageObjStart + ($i * 2)) . ' 0 R';
            // page object filled later after we know content ids
        }
        $objects[] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), $pageCount);
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $prevSize = $this->fontSize;
        $prevBold = $this->bold;
        $this->setFont(8, false);
        for ($i = 0; $i < $pageCount; $i++) {
            $pageObj = $pageObjStart + ($i * 2);
            $contentObj = $pageObj + 1;
            $content = $this->pages[$i];
            $footer = '';
            if (!$this->pageNumbersStamped) {
                $label = 'Page ' . ($i + 1) . ' / ' . $pageCount;
                $tw = $this->textWidth($label);
                $fx = ($this->w - $tw) / 2;
                $footer = sprintf(
                    "BT /F1 8 Tf 0.45 0.45 0.5 rg %.2F %.2F Td (%s) Tj ET 0 g\n",
                    $fx,
                    24,
                    $this->escape($this->sanitize($label))
                );
            }
            $stream = $content . $footer;
            $objects[] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>',
                $this->w,
                $this->h,
                $font1,
                $font2,
                $contentObj
            );
            $objects[] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($stream), $stream);
        }
        $this->setFont($prevSize, $prevBold);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $n = count($objects) + 1;
        $pdf .= "xref\n0 {$n}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $n; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$n} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        return $pdf;
    }

    /** @return list<string> */
    private function wrap(string $text, float $maxW): array
    {
        $text = trim($this->sanitize($text));
        if ($text === '') {
            return [''];
        }
        $words = preg_split('/\s+/', $text) ?: [$text];
        $lines = [];
        $cur = '';
        foreach ($words as $w) {
            $try = $cur === '' ? $w : ($cur . ' ' . $w);
            if ($this->textWidth($try) <= $maxW) {
                $cur = $try;
                continue;
            }
            if ($cur !== '') {
                $lines[] = $cur;
            }
            // hard-break very long tokens
            while ($this->textWidth($w) > $maxW && strlen($w) > 4) {
                $cut = max(1, (int)floor(($maxW / max(0.1, $this->textWidth('W'))) ));
                $lines[] = substr($w, 0, $cut);
                $w = substr($w, $cut);
            }
            $cur = $w;
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
        return $lines ?: [''];
    }

    private function truncate(string $text, float $maxW): string
    {
        $text = $this->sanitize($text);
        if ($this->textWidth($text) <= $maxW) {
            return $text;
        }
        while (strlen($text) > 1 && $this->textWidth($text . '…') > $maxW) {
            $text = substr($text, 0, -1);
        }
        return $text . '…';
    }

    private function textWidth(string $text): float
    {
        // Approx Helvetica width at current size
        return strlen($text) * $this->fontSize * 0.50;
    }

    private function sanitize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace("\n", ' ', $text);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if (is_string($converted)) {
                $text = $converted;
            }
        }
        // Keep printable Latin-1
        return preg_replace('/[^\x09\x20-\x7E\xA0-\xFF]/', '?', $text) ?? $text;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
