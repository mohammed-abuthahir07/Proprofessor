<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod', 'admin');
$user = Auth::user();
$deptId = $user['department_id'];
$isAdmin = ($user['role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $planId = (int)post('plan_id');
    $action = (string)post('action');
    $postedFeedback = array_key_exists('overall', $_POST) || isset($_POST['points']);
    $status = match ($action) {
        'approve' => 'approved',
        'reject' => 'returned',
        'request_changes' => 'returned',
        default => 'under_review',
    };
    $reviewAction = match ($action) {
        'approve' => 'approve',
        'reject' => 'reject',
        'comment' => 'comment',
        default => 'request_changes',
    };
    $sql = 'SELECT * FROM course_plans WHERE id=? AND institution_id=?';
    $params = [$planId, (int)$user['institution_id']];
    if (!$isAdmin) {
        $sql .= ' AND department_id=?';
        $params[] = $deptId;
    }
    $plan = Database::fetch($sql, $params);
    if ($plan && in_array($action, ['approve', 'reject', 'request_changes'], true) && ($plan['status'] ?? '') === 'draft') {
        flash('error', 'The professor must submit this plan before you can approve or return it.');
        redirect('/hod/approvals.php?id=' . $planId);
    }
    if ($plan) {
        if ($postedFeedback) {
            $packed = HodFeedback::fromPost(
                is_array($_POST['points'] ?? null) ? $_POST['points'] : [],
                is_array($_POST['flags'] ?? null) ? $_POST['flags'] : [],
                is_array($_POST['labels'] ?? null) ? $_POST['labels'] : [],
                (string)post('overall', '')
            );
            $encoded = HodFeedback::encode($packed);
            $summary = HodFeedback::summary($packed);
        } else {
            $encoded = trim((string)($plan['hod_comments'] ?? ''));
            if ($encoded === '') {
                $encoded = HodFeedback::encode(['overall' => '', 'points' => []]);
            }
            $summary = HodFeedback::summary(HodFeedback::parse($encoded));
        }
        Database::update('course_plans', [
            'status' => $status,
            'hod_comments' => $encoded,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'reviewed_by' => $user['id'],
        ], 'id = :id', ['id' => $planId]);
        Database::insert('plan_reviews', [
            'plan_id' => $planId,
            'reviewer_id' => $user['id'],
            'action' => $reviewAction,
            'comments' => $summary,
            'checklist' => $encoded,
        ]);
        notify_user(
            (int)$plan['professor_id'],
            'approval',
            'Plan ' . str_replace('_', ' ', $status),
            $summary,
            '/professor/plan-view.php?id=' . $planId,
            [
                'priority' => 'high',
                'category' => 'approvals',
                'action' => ['type' => 'VIEW_PLAN', 'record_id' => $planId],
            ]
        );
        flash('success', match ($action) {
            'approve' => 'Plan approved.',
            'reject', 'request_changes' => 'Plan returned for revision.',
            default => 'Feedback saved.',
        });
    }
    if ((string)post('back') === 'queue') {
        $backQuery = [];
        $backStatus = (string)post('status_filter');
        if (in_array($backStatus, ['pending', 'approved', 'returned'], true)) {
            $backQuery['status'] = $backStatus;
        }
        $backQ = trim((string)post('q'));
        if ($backQ !== '') {
            $backQuery['q'] = $backQ;
        }
        redirect('/hod/approvals.php' . ($backQuery ? '?' . http_build_query($backQuery) : ''));
    }
    $tabBack = (string)post('tab');
    $tabExtra = in_array($tabBack, ['details', 'bloom', 'ai', 'feedback'], true) ? '&tab=' . $tabBack : '';
    redirect('/hod/approvals.php?id=' . $planId . $tabExtra);
}

$viewId = (int)get('id');
$statusFilter = (string)get('status', 'all');
if (!in_array($statusFilter, ['all', 'pending', 'approved', 'returned'], true)) {
    $statusFilter = 'all';
}
$search = trim((string)get('q', ''));
$queueSql = 'SELECT p.*, u.full_name AS professor_name,
        c.name AS class_name, c.section AS class_section, c.year AS class_year
     FROM course_plans p
     JOIN users u ON u.id = p.professor_id
     LEFT JOIN classes c ON c.id = p.class_id
     WHERE p.institution_id=? AND p.status IN ("submitted","under_review","returned","approved")';
$queueParams = [(int)$user['institution_id']];
if (!$isAdmin) {
    $queueSql .= ' AND p.department_id=?';
    $queueParams[] = $deptId;
}
$queueSql .= ' ORDER BY FIELD(p.status,"submitted","under_review","returned","draft","approved"), p.submitted_at DESC';
$queue = Database::fetchAll($queueSql, $queueParams);

$bucketOf = static function (string $status): string {
    return match ($status) {
        'approved' => 'approved',
        'returned' => 'returned',
        default => 'pending',
    };
};
$fmtNum = static function (?float $n): string {
    if ($n === null) {
        return '—';
    }
    $rounded = round($n, 2);
    $text = number_format($rounded, 2, '.', '');
    return rtrim(rtrim($text, '0'), '.');
};
$fmtWhen = static function (?string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date('d M Y, g:i A', $ts) : $value;
};
$initialsOf = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
    if (!$parts) {
        return 'F';
    }
    $first = strtoupper(mb_substr($parts[0], 0, 1));
    $last = count($parts) > 1 ? strtoupper(mb_substr($parts[count($parts) - 1], 0, 1)) : '';
    return $first . $last;
};
$k46Of = static function (array $row): ?float {
    $bloom = json_decode((string)($row['bloom_data'] ?? ''), true);
    if (!is_array($bloom) || $bloom === []) {
        return null;
    }
    return round((float)($bloom['K4'] ?? 0) + (float)($bloom['K5'] ?? 0) + (float)($bloom['K6'] ?? 0), 1);
};
$aiTone = static function ($score): string {
    if ($score === null || $score === '') {
        return 'none';
    }
    $n = (float)$score;
    if ($n >= 75) {
        return 'ok';
    }
    if ($n >= 50) {
        return 'warn';
    }
    return 'bad';
};
$statusMeta = static function (string $status): array {
    return match ($status) {
        'approved' => ['Approved', 'ok'],
        'returned' => ['Returned', 'bad'],
        'under_review' => ['In review', 'warn'],
        default => ['Pending', 'warn'],
    };
};
$asList = static function (mixed $value): array {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : (trim($value) === '' ? [] : [$value]);
    }
    if (!is_array($value)) {
        return [];
    }
    $out = [];
    foreach ($value as $item) {
        if (is_string($item) || is_numeric($item)) {
            $text = trim((string)$item);
        } elseif (is_array($item)) {
            $text = trim((string)($item['details'] ?? $item['title'] ?? $item['focus'] ?? ''));
            $type = trim((string)($item['type'] ?? ''));
            if ($type !== '' && $text !== '') {
                $text = $type . ': ' . $text;
            }
            if ($text === '') {
                $text = trim((string)json_encode($item, JSON_UNESCAPED_UNICODE));
            }
        } else {
            $text = '';
        }
        if ($text !== '') {
            $out[] = $text;
        }
    }
    return $out;
};
$queueLink = static function (string $status, string $q = '') : string {
    $params = [];
    if ($status !== 'all') {
        $params['status'] = $status;
    }
    if ($q !== '') {
        $params['q'] = $q;
    }
    $query = http_build_query($params);
    return '?' . $query;
};

$counts = ['all' => count($queue), 'pending' => 0, 'approved' => 0, 'returned' => 0];
foreach ($queue as $row) {
    $counts[$bucketOf((string)($row['status'] ?? ''))]++;
}
$visible = [];
$needle = mb_strtolower($search);
foreach ($queue as $row) {
    if ($statusFilter !== 'all' && $bucketOf((string)($row['status'] ?? '')) !== $statusFilter) {
        continue;
    }
    if ($needle !== '') {
        $hay = mb_strtolower(trim(
            (string)($row['professor_name'] ?? '') . ' '
            . (string)($row['subject_name'] ?? '') . ' '
            . (string)($row['title'] ?? '')
        ));
        if (!str_contains($hay, $needle)) {
            continue;
        }
    }
    $visible[] = $row;
}

$view = null;
if ($viewId) {
    $view = Database::fetch(
        'SELECT p.*, u.full_name AS professor_name, c.name AS class_name, c.section AS class_section, c.year AS class_year
         FROM course_plans p
         JOIN users u ON u.id = p.professor_id
         LEFT JOIN classes c ON c.id = p.class_id
         WHERE p.id=? AND p.institution_id=?',
        [$viewId, (int)$user['institution_id']]
    );
    if ($view && !$isAdmin && (int)$view['department_id'] !== (int)$deptId) {
        $view = null;
    }
}
$units = $view ? Database::fetchAll('SELECT * FROM plan_units WHERE plan_id=? ORDER BY unit_number', [$viewId]) : [];
$bloom = $view ? (json_decode($view['bloom_data'] ?: '{}', true) ?: []) : [];
$weekly = $view ? (json_decode($view['weekly_plan'] ?: '[]', true) ?: []) : [];
$resources = $view ? (json_decode($view['resources'] ?: '[]', true) ?: []) : [];
$advice = $view ? (json_decode($view['expert_advice'] ?: '[]', true) ?: []) : [];
$planData = $view ? (json_decode($view['plan_data'] ?: '{}', true) ?: []) : [];
$outcomes = $planData['learning_outcomes'] ?? [];
if (!$units && !empty($planData['units']) && is_array($planData['units'])) {
    $units = $planData['units'];
}
$fb = $view ? HodFeedback::parse($view['hod_comments'] ?? null) : ['overall' => '', 'points' => []];
require __DIR__ . '/../app/Views/hod/approvals_page.php';
