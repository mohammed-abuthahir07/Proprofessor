<?php
declare(strict_types=1);

/**
 * Structured HOD review comments, stored as JSON in course_plans.hod_comments.
 */
final class HodFeedback
{
    /**
     * @return array{overall:string,points:list<array{key:string,label:string,comment:string,flag:string}>}
     */
    public static function parse(?string $raw): array
    {
        $empty = ['overall' => '', 'points' => []];
        $raw = trim((string)$raw);
        if ($raw === '') {
            return $empty;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['overall' => $raw, 'points' => []];
        }
        $points = [];
        foreach (($decoded['points'] ?? []) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $comment = trim((string)($p['comment'] ?? ''));
            $flag = self::flag((string)($p['flag'] ?? ''));
            if ($comment === '' && $flag === '') {
                continue;
            }
            $points[] = [
                'key' => (string)($p['key'] ?? ''),
                'label' => (string)($p['label'] ?? $p['key'] ?? 'Comment'),
                'comment' => $comment,
                'flag' => $flag !== '' ? $flag : 'suggest',
            ];
        }
        return [
            'overall' => trim((string)($decoded['overall'] ?? '')),
            'points' => $points,
        ];
    }

    /**
     * @param array<string,string> $comments
     * @param array<string,string> $flags
     * @param array<string,string> $labels
     * @return array{overall:string,points:list<array{key:string,label:string,comment:string,flag:string}>}
     */
    public static function fromPost(array $comments, array $flags, array $labels, string $overall): array
    {
        $points = [];
        foreach ($comments as $key => $text) {
            $key = (string)$key;
            $text = trim((string)$text);
            $flag = self::flag((string)($flags[$key] ?? ''));
            if ($text === '' && $flag === '') {
                continue;
            }
            $points[] = [
                'key' => $key,
                'label' => trim((string)($labels[$key] ?? $key)) ?: $key,
                'comment' => $text,
                'flag' => $flag !== '' ? $flag : 'suggest',
            ];
        }
        return [
            'overall' => trim($overall),
            'points' => $points,
        ];
    }

    /**
     * @param array{overall?:string,points?:list<array<string,string>>} $data
     */
    public static function encode(array $data): string
    {
        return (string)json_encode([
            'overall' => trim((string)($data['overall'] ?? '')),
            'points' => array_values($data['points'] ?? []),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array{overall?:string,points?:list<array<string,string>>} $data
     */
    public static function point(array $data, string $key): ?array
    {
        foreach (($data['points'] ?? []) as $p) {
            if (($p['key'] ?? '') === $key) {
                return $p;
            }
        }
        return null;
    }

    /**
     * @param array{overall?:string,points?:list<array<string,string>>} $data
     */
    public static function summary(array $data): string
    {
        $bits = [];
        $overall = trim((string)($data['overall'] ?? ''));
        if ($overall !== '') {
            $bits[] = $overall;
        }
        $must = 0;
        $suggest = 0;
        foreach (($data['points'] ?? []) as $p) {
            $flag = self::flag((string)($p['flag'] ?? ''));
            if ($flag === 'must_fix') {
                $must++;
            } elseif ($flag === 'suggest') {
                $suggest++;
            }
        }
        if ($must) {
            $bits[] = $must . ' item' . ($must === 1 ? '' : 's') . ' must be fixed';
        }
        if ($suggest) {
            $bits[] = $suggest . ' suggestion' . ($suggest === 1 ? '' : 's');
        }
        return $bits ? implode('. ', $bits) : 'HOD reviewed this course plan.';
    }

    /**
     * @param array{overall?:string,points?:list<array<string,string>>} $data
     * @return array{must_fix:int,suggest:int,ok:int}
     */
    public static function counts(array $data): array
    {
        $c = ['must_fix' => 0, 'suggest' => 0, 'ok' => 0];
        foreach (($data['points'] ?? []) as $p) {
            $flag = self::flag((string)($p['flag'] ?? 'suggest')) ?: 'suggest';
            if (isset($c[$flag])) {
                $c[$flag]++;
            }
        }
        return $c;
    }

    public static function flagLabel(string $flag): string
    {
        return match (self::flag($flag)) {
            'must_fix' => 'Must fix',
            'ok' => 'Looks good',
            'suggest' => 'Suggestion',
            default => 'Comment',
        };
    }

    /** @param array{key?:string,label?:string,comment?:string,flag?:string}|null $point */
    public static function renderInline(?array $point): string
    {
        if (!$point) {
            return '';
        }
        $comment = trim((string)($point['comment'] ?? ''));
        $flag = self::flag((string)($point['flag'] ?? 'suggest')) ?: 'suggest';
        if ($comment === '' && $flag === 'ok') {
            $comment = 'Looks good.';
        }
        if ($comment === '') {
            return '';
        }
        return '<div class="inline-fb inline-fb-' . e($flag) . '">'
            . '<span class="inline-fb-flag">HOD · ' . e(self::flagLabel($flag)) . '</span>'
            . '<p>' . e($comment) . '</p>'
            . '</div>';
    }

    /**
     * @param array{overall?:string,points?:list<array<string,string>>} $fb
     */
    public static function renderEditor(array $fb, string $key, string $label): void
    {
        $point = self::point($fb, $key);
        $flag = (string)($point['flag'] ?? '');
        $comment = (string)($point['comment'] ?? '');
        ?>
    <input type="hidden" name="labels[<?= e($key) ?>]" value="<?= e($label) ?>">
    <div class="review-comment">
      <label>HOD comment · <?= e($label) ?></label>
      <textarea name="points[<?= e($key) ?>]" rows="2" placeholder="Comment on this point…"><?= e($comment) ?></textarea>
      <select name="flags[<?= e($key) ?>]">
        <option value="" <?= $flag === '' ? 'selected' : '' ?>>No flag</option>
        <option value="ok" <?= $flag === 'ok' ? 'selected' : '' ?>>Looks good</option>
        <option value="suggest" <?= $flag === 'suggest' ? 'selected' : '' ?>>Suggestion</option>
        <option value="must_fix" <?= $flag === 'must_fix' ? 'selected' : '' ?>>Must fix</option>
      </select>
    </div>
        <?php
    }

    private static function flag(string $flag): string
    {
        $flag = strtolower(trim($flag));
        return in_array($flag, ['ok', 'suggest', 'must_fix'], true) ? $flag : '';
    }
}
