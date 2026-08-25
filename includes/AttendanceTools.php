<?php
declare(strict_types=1);

/**
 * Attendance extensions: QR check-in, optional geofence, shortage alerts,
 * risk score, bulk import/export, regularization. Reuses existing
 * attendance_sessions / attendance_records and the same % formula
 * (present+late count as present).
 */
final class AttendanceTools
{
    private static bool $schemaReady = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS attendance_qr_tokens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                token VARCHAR(64) NOT NULL,
                institution_id INT UNSIGNED NOT NULL,
                professor_id INT UNSIGNED NOT NULL,
                class_id INT UNSIGNED NOT NULL,
                subject_id INT UNSIGNED NOT NULL,
                session_id INT UNSIGNED NULL,
                session_date DATE NOT NULL,
                period VARCHAR(20) NOT NULL DEFAULT '1',
                topic VARCHAR(255) NULL,
                expires_at DATETIME NOT NULL,
                geofence_required TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                meta LONGTEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_att_qr_token (token),
                INDEX idx_att_qr_session (session_id),
                INDEX idx_att_qr_class (class_id, subject_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS attendance_regularization_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                institution_id INT UNSIGNED NOT NULL,
                session_id INT UNSIGNED NOT NULL,
                record_id INT UNSIGNED NULL,
                student_id INT UNSIGNED NOT NULL,
                register_no VARCHAR(40) NOT NULL,
                original_status VARCHAR(20) NOT NULL,
                requested_status VARCHAR(20) NOT NULL,
                reason TEXT NOT NULL,
                proof_url VARCHAR(255) NULL,
                status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                professor_note TEXT NULL,
                decided_by INT UNSIGNED NULL,
                decided_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_att_reg_sess (session_id),
                INDEX idx_att_reg_stu (student_id),
                INDEX idx_att_reg_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaReady = true;
    }

    /** Same formula as existing UI: present + late count toward %. */
    public static function isPresentStatus(string $status): bool
    {
        return in_array($status, ['present', 'late'], true);
    }

    public static function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $map = [
            'p' => 'present', 'present' => 'present',
            'a' => 'absent', 'absent' => 'absent',
            'l' => 'late', 'late' => 'late',
            'e' => 'excused', 'excused' => 'excused',
        ];
        return $map[$status] ?? '';
    }

    /**
     * @return array{total:int,present:int,percent:float}
     */
    public static function percentForRegister(int $classId, int $subjectId, string $registerNo): array
    {
        $rows = Database::fetchAll(
            'SELECT r.status FROM attendance_records r
             JOIN attendance_sessions s ON s.id = r.session_id
             WHERE s.class_id = ? AND s.subject_id = ? AND r.register_no = ?',
            [$classId, $subjectId, $registerNo]
        );
        $total = count($rows);
        $present = 0;
        foreach ($rows as $r) {
            if (self::isPresentStatus((string)$r['status'])) {
                $present++;
            }
        }
        return [
            'total' => $total,
            'present' => $present,
            'percent' => $total ? round($present * 100 / $total, 1) : 0.0,
        ];
    }

    /**
     * Shortage band using existing % formula + institution min.
     * @return array{band:string,label:string,percent:float,min:float}
     */
    public static function shortageBand(float $percent, float $minPct): array
    {
        if ($percent < $minPct) {
            return ['band' => 'below', 'label' => 'Below AICTE ' . (int)$minPct . '%', 'percent' => $percent, 'min' => $minPct];
        }
        if ($percent < $minPct + 2) {
            return ['band' => 'at_risk', 'label' => 'At risk', 'percent' => $percent, 'min' => $minPct];
        }
        if ($percent < $minPct + 8) {
            return ['band' => 'approaching', 'label' => 'Approaching shortage', 'percent' => $percent, 'min' => $minPct];
        }
        return ['band' => 'ok', 'label' => 'On track', 'percent' => $percent, 'min' => $minPct];
    }

    /**
     * Deterministic risk from recent trend (not ML). Needs ≥3 sessions.
     * @return array{level:string,label:string,detail:string}
     */
    public static function riskScore(int $classId, int $subjectId, string $registerNo, float $minPct): array
    {
        $rows = Database::fetchAll(
            'SELECT r.status, s.session_date FROM attendance_records r
             JOIN attendance_sessions s ON s.id = r.session_id
             WHERE s.class_id = ? AND s.subject_id = ? AND r.register_no = ?
             ORDER BY s.session_date DESC, s.id DESC
             LIMIT 12',
            [$classId, $subjectId, $registerNo]
        );
        if (count($rows) < 3) {
            return ['level' => 'insufficient', 'label' => 'Insufficient data', 'detail' => 'Need at least 3 sessions'];
        }
        $overall = self::percentForRegister($classId, $subjectId, $registerNo);
        $recent = array_slice($rows, 0, 5);
        $recentPresent = 0;
        foreach ($recent as $r) {
            if (self::isPresentStatus((string)$r['status'])) {
                $recentPresent++;
            }
        }
        $recentPct = round($recentPresent * 100 / count($recent), 1);
        $absences = 0;
        foreach ($recent as $r) {
            if ((string)$r['status'] === 'absent') {
                $absences++;
            }
        }
        $score = 0;
        if ($overall['percent'] < $minPct) {
            $score += 3;
        } elseif ($overall['percent'] < $minPct + 5) {
            $score += 2;
        } elseif ($overall['percent'] < $minPct + 10) {
            $score += 1;
        }
        if ($recentPct < $overall['percent'] - 8) {
            $score += 2; // declining
        } elseif ($recentPct < $overall['percent']) {
            $score += 1;
        }
        if ($absences >= 3) {
            $score += 2;
        } elseif ($absences >= 2) {
            $score += 1;
        }
        if ($score >= 5) {
            return ['level' => 'high', 'label' => 'High Risk', 'detail' => "Overall {$overall['percent']}% · recent {$recentPct}%"];
        }
        if ($score >= 3) {
            return ['level' => 'medium', 'label' => 'Medium Risk', 'detail' => "Overall {$overall['percent']}% · recent {$recentPct}%"];
        }
        return ['level' => 'low', 'label' => 'Low Risk', 'detail' => "Overall {$overall['percent']}% · recent {$recentPct}%"];
    }

    /** @return array{lat:?float,lng:?float,radius_m:float,required:bool} */
    public static function geofenceConfig(int $institutionId): array
    {
        $inst = Database::fetch('SELECT settings FROM institutions WHERE id = ?', [$institutionId]);
        $settings = json_decode((string)($inst['settings'] ?? '{}'), true) ?: [];
        $lat = isset($settings['geofence_lat']) ? (float)$settings['geofence_lat'] : null;
        $lng = isset($settings['geofence_lng']) ? (float)$settings['geofence_lng'] : null;
        $radius = isset($settings['geofence_radius_m']) ? (float)$settings['geofence_radius_m'] : 150.0;
        $required = !empty($settings['geofence_required_for_qr']);
        if ($lat === null || $lng === null || ($lat == 0.0 && $lng == 0.0)) {
            return ['lat' => null, 'lng' => null, 'radius_m' => $radius, 'required' => false];
        }
        return ['lat' => $lat, 'lng' => $lng, 'radius_m' => max(20.0, $radius), 'required' => $required];
    }

    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lng2 - $lng1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
        return 2 * $earth * asin(min(1, sqrt($a)));
    }

    /**
     * Ensure session exists (upsert). Does not wipe existing records when only opening QR.
     * @return array{session_id:int,created:bool}
     */
    public static function ensureSession(array $user, int $classId, int $subjectId, string $date, string $period, string $topic = ''): array
    {
        $instId = (int)$user['institution_id'];
        $period = trim($period) !== '' ? trim($period) : '1';
        $existing = Database::fetch(
            'SELECT * FROM attendance_sessions WHERE class_id=? AND subject_id=? AND session_date=? AND period=?',
            [$classId, $subjectId, $date, $period]
        );
        if ($existing) {
            if ((int)$existing['institution_id'] !== $instId) {
                throw new RuntimeException('Session institution mismatch.');
            }
            return ['session_id' => (int)$existing['id'], 'created' => false];
        }
        $id = (int)Database::insert('attendance_sessions', [
            'institution_id' => $instId,
            'professor_id' => (int)$user['id'],
            'subject_id' => $subjectId,
            'class_id' => $classId,
            'session_date' => $date,
            'period' => $period,
            'topic' => $topic !== '' ? $topic : null,
            'records' => json_encode([], JSON_UNESCAPED_UNICODE),
            'present_count' => 0,
            'absent_count' => 0,
            'meta' => json_encode(['opened_for_qr' => true], JSON_UNESCAPED_UNICODE),
        ]);
        return ['session_id' => $id, 'created' => true];
    }

    /**
     * @return array{ok:bool,error?:string,token?:string,expires_at?:string,session_id?:int,url?:string}
     */
    public static function startQr(array $user, int $classId, int $subjectId, string $date, string $period, string $topic = '', int $ttlMinutes = 15): array
    {
        self::ensureSchema();
        if (!professor_can_manage_class($user, $classId) || !professor_can_manage_subject($user, $subjectId, $classId)) {
            return ['ok' => false, 'error' => 'Not authorized for this class/subject.'];
        }
        $ttlMinutes = max(5, min(60, $ttlMinutes));
        $ensured = self::ensureSession($user, $classId, $subjectId, $date, $period, $topic);
        $geo = self::geofenceConfig((int)$user['institution_id']);
        $token = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', time() + $ttlMinutes * 60);
        // Deactivate prior active tokens for same session
        Database::query(
            'UPDATE attendance_qr_tokens SET is_active = 0 WHERE session_id = ? AND is_active = 1',
            [$ensured['session_id']]
        );
        Database::insert('attendance_qr_tokens', [
            'token' => $token,
            'institution_id' => (int)$user['institution_id'],
            'professor_id' => (int)$user['id'],
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'session_id' => $ensured['session_id'],
            'session_date' => $date,
            'period' => $period,
            'topic' => $topic !== '' ? $topic : null,
            'expires_at' => $expires,
            'geofence_required' => $geo['required'] ? 1 : 0,
            'is_active' => 1,
            'meta' => json_encode(['ttl_minutes' => $ttlMinutes], JSON_UNESCAPED_UNICODE),
        ]);
        return [
            'ok' => true,
            'token' => $token,
            'expires_at' => $expires,
            'session_id' => $ensured['session_id'],
            'url' => base_url('/student/attendance-qr.php?token=' . urlencode($token)),
        ];
    }

    public static function findActiveQr(string $token): ?array
    {
        self::ensureSchema();
        $token = trim($token);
        if ($token === '' || strlen($token) > 64) {
            return null;
        }
        return Database::fetch(
            'SELECT * FROM attendance_qr_tokens WHERE token = ? AND is_active = 1 LIMIT 1',
            [$token]
        ) ?: null;
    }

    /**
     * Student QR check-in. Marks Present via existing record tables.
     * @return array{ok:bool,error?:string}
     */
    public static function checkInWithQr(array $student, string $token, ?float $lat = null, ?float $lng = null): array
    {
        self::ensureSchema();
        $qr = self::findActiveQr($token);
        if (!$qr) {
            return ['ok' => false, 'error' => 'Invalid or inactive QR code.'];
        }
        if ((int)$qr['institution_id'] !== (int)($student['institution_id'] ?? 0)) {
            return ['ok' => false, 'error' => 'This QR does not belong to your institution.'];
        }
        if (strtotime((string)$qr['expires_at']) < time()) {
            Database::update('attendance_qr_tokens', ['is_active' => 0], 'id = :id', ['id' => (int)$qr['id']]);
            return ['ok' => false, 'error' => 'This QR has expired.'];
        }
        $classId = student_class_id($student);
        if ($classId < 1 || $classId !== (int)$qr['class_id']) {
            return ['ok' => false, 'error' => 'You are not in the class for this attendance session.'];
        }
        $geo = self::geofenceConfig((int)$qr['institution_id']);
        $requireGeo = !empty($qr['geofence_required']) && $geo['lat'] !== null && $geo['lng'] !== null;
        if ($requireGeo) {
            if ($lat === null || $lng === null) {
                return ['ok' => false, 'error' => 'Location is required for this QR check-in.'];
            }
            $dist = self::distanceMeters($lat, $lng, (float)$geo['lat'], (float)$geo['lng']);
            if ($dist > (float)$geo['radius_m']) {
                return ['ok' => false, 'error' => 'Outside allowed attendance location.'];
            }
        }

        $reg = self::studentRegisterNo($student, (int)$qr['class_id']);
        if ($reg === '') {
            return ['ok' => false, 'error' => 'Register number not found on roster.'];
        }
        $sessionId = (int)$qr['session_id'];
        $existing = Database::fetch(
            'SELECT id, status FROM attendance_records WHERE session_id = ? AND register_no = ?',
            [$sessionId, $reg]
        );
        if ($existing && self::isPresentStatus((string)$existing['status'])) {
            return ['ok' => false, 'error' => 'Already checked in for this session.'];
        }

        self::setRecordStatus($sessionId, $reg, (int)$student['id'], 'present', [
            'via' => 'qr',
            'at' => date('c'),
            'lat' => $lat,
            'lng' => $lng,
        ]);
        return ['ok' => true];
    }

    public static function studentRegisterNo(array $user, int $classId): string
    {
        $reg = trim((string)($user['register_no'] ?? ''));
        if ($reg !== '') {
            return $reg;
        }
        $row = Database::fetch(
            'SELECT register_no FROM students_roster WHERE user_id = ? AND class_id = ? LIMIT 1',
            [(int)$user['id'], $classId]
        );
        return trim((string)($row['register_no'] ?? ''));
    }

    /**
     * Update one student's status in a session (records + session JSON + counts).
     * @param array<string,mixed> $metaExtra
     */
    public static function setRecordStatus(int $sessionId, string $registerNo, ?int $studentId, string $status, array $metaExtra = []): void
    {
        $status = self::normalizeStatus($status);
        if ($status === '') {
            throw new InvalidArgumentException('Invalid status');
        }
        $session = Database::fetch('SELECT * FROM attendance_sessions WHERE id = ?', [$sessionId]);
        if (!$session) {
            throw new RuntimeException('Session not found');
        }
        $existing = Database::fetch(
            'SELECT id, meta FROM attendance_records WHERE session_id = ? AND register_no = ?',
            [$sessionId, $registerNo]
        );
        $meta = [];
        if ($existing) {
            $meta = json_decode((string)($existing['meta'] ?? '{}'), true) ?: [];
        }
        $meta = array_merge($meta, $metaExtra);
        if ($existing) {
            Database::update('attendance_records', [
                'status' => $status,
                'student_id' => $studentId ?: null,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            ], 'id = :id', ['id' => (int)$existing['id']]);
        } else {
            Database::insert('attendance_records', [
                'session_id' => $sessionId,
                'student_id' => $studentId,
                'register_no' => $registerNo,
                'status' => $status,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            ]);
        }
        self::rebuildSessionAggregate($sessionId);
    }

    public static function rebuildSessionAggregate(int $sessionId): void
    {
        $recs = Database::fetchAll(
            'SELECT register_no, status FROM attendance_records WHERE session_id = ?',
            [$sessionId]
        );
        $json = [];
        $present = 0;
        $absent = 0;
        foreach ($recs as $r) {
            $st = (string)$r['status'];
            $json[] = ['register_no' => $r['register_no'], 'status' => $st];
            if (self::isPresentStatus($st)) {
                $present++;
            } else {
                $absent++;
            }
        }
        Database::update('attendance_sessions', [
            'records' => json_encode($json, JSON_UNESCAPED_UNICODE),
            'present_count' => $present,
            'absent_count' => $absent,
        ], 'id = :id', ['id' => $sessionId]);
    }

    /**
     * Proactive shortage notifications (idempotent per band).
     */
    public static function dispatchShortageAlerts(array $user, int $classId, int $subjectId, array $summary, array $roster, float $minPct): int
    {
        if (!professor_can_manage_subject($user, $subjectId, $classId)) {
            return 0;
        }
        $sent = 0;
        $names = [];
        foreach ($roster as $st) {
            $names[(string)$st['register_no']] = $st;
        }
        foreach ($summary as $reg => $pct) {
            $band = self::shortageBand((float)$pct, $minPct);
            if ($band['band'] === 'ok') {
                continue;
            }
            $stu = $names[(string)$reg] ?? null;
            $uid = $stu ? (int)($stu['user_id'] ?? 0) : 0;
            if ($uid < 1) {
                continue;
            }
            $marker = 'att-shortage-' . $classId . '-' . $subjectId . '-' . $reg . '-' . $band['band'];
            $exists = Database::fetch(
                'SELECT id FROM notifications WHERE user_id = ? AND type = ? AND body LIKE ? LIMIT 1',
                [$uid, 'attendance_shortage', '%[' . $marker . ']%']
            );
            if ($exists) {
                continue;
            }
            notify_user(
                $uid,
                'attendance_shortage',
                'Attendance: ' . $band['label'],
                'Your attendance is ' . $pct . '%. ' . $band['label'] . ' [' . $marker . ']',
                '/student/attendance.php',
                [
                    'priority' => $band['band'] === 'below' ? 'high' : 'medium',
                    'category' => 'attendance',
                    'action' => ['type' => 'STUDENT_ATTENDANCE'],
                    'meta' => ['marker' => $marker, 'percent' => $pct, 'band' => $band['band']],
                ]
            );
            $sent++;
        }
        return $sent;
    }

    /**
     * Bulk import attendance rows into existing sessions (upsert).
     * Expected columns: register_no, session_date, period, status [, subject_code]
     *
     * @return array{ok:bool,valid:int,invalid:list<string>}
     */
    public static function importAttendanceCsv(array $user, int $classId, int $subjectId, string $csvText): array
    {
        if (!professor_can_manage_subject($user, $subjectId, $classId)) {
            return ['ok' => false, 'valid' => 0, 'invalid' => ['Not authorized.']];
        }
        $lines = preg_split('/\r\n|\r|\n/', trim($csvText)) ?: [];
        $invalid = [];
        $valid = 0;
        $header = null;
        $rowNum = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $rowNum++;
            $cols = str_getcsv($line);
            if ($header === null) {
                $maybe = array_map(static fn($h) => strtolower(trim((string)$h)), $cols);
                if (in_array('register_no', $maybe, true) || in_array('reg_no', $maybe, true) || in_array('status', $maybe, true)) {
                    $header = $maybe;
                    continue;
                }
                $header = ['register_no', 'session_date', 'period', 'status'];
            }
            $map = [];
            foreach ($header as $i => $h) {
                $map[$h] = $cols[$i] ?? '';
            }
            $reg = trim((string)($map['register_no'] ?? $map['reg_no'] ?? $cols[0] ?? ''));
            $date = trim((string)($map['session_date'] ?? $map['date'] ?? $cols[1] ?? ''));
            $period = trim((string)($map['period'] ?? $cols[2] ?? '1')) ?: '1';
            $status = self::normalizeStatus((string)($map['status'] ?? $cols[3] ?? ''));
            if ($reg === '') {
                $invalid[] = "Row {$rowNum}: Student not found (empty register_no)";
                continue;
            }
            $roster = Database::fetch(
                'SELECT user_id FROM students_roster WHERE class_id=? AND register_no=? AND institution_id=?',
                [$classId, $reg, (int)$user['institution_id']]
            );
            if (!$roster) {
                $invalid[] = "Row {$rowNum}: Student not found ({$reg})";
                continue;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $ts = strtotime($date);
                if ($ts === false) {
                    $invalid[] = "Row {$rowNum}: Invalid date";
                    continue;
                }
                $date = date('Y-m-d', $ts);
            }
            if ($status === '') {
                $invalid[] = "Row {$rowNum}: Invalid attendance status";
                continue;
            }
            try {
                $ensured = self::ensureSession($user, $classId, $subjectId, $date, $period, 'Imported');
                self::setRecordStatus(
                    $ensured['session_id'],
                    $reg,
                    isset($roster['user_id']) ? (int)$roster['user_id'] : null,
                    $status,
                    ['via' => 'bulk_import']
                );
                $valid++;
            } catch (Throwable $e) {
                $invalid[] = "Row {$rowNum}: " . $e->getMessage();
            }
        }
        return ['ok' => true, 'valid' => $valid, 'invalid' => $invalid];
    }

    /**
     * @return string CSV
     */
    public static function exportCsv(array $user, int $classId, int $subjectId, ?string $dateFrom = null, ?string $dateTo = null): string
    {
        if (!professor_can_manage_subject($user, $subjectId, $classId)) {
            throw new RuntimeException('Not authorized.');
        }
        $sql = 'SELECT r.register_no, u.full_name, sub.name AS subject_name, c.section, c.year,
                       sess.session_date, sess.period, r.status
                FROM attendance_records r
                JOIN attendance_sessions sess ON sess.id = r.session_id
                LEFT JOIN subjects sub ON sub.id = sess.subject_id
                LEFT JOIN classes c ON c.id = sess.class_id
                LEFT JOIN users u ON u.id = r.student_id
                WHERE sess.class_id = ? AND sess.subject_id = ? AND sess.institution_id = ?';
        $params = [$classId, $subjectId, (int)$user['institution_id']];
        if ($dateFrom) {
            $sql .= ' AND sess.session_date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= ' AND sess.session_date <= ?';
            $params[] = $dateTo;
        }
        $sql .= ' ORDER BY sess.session_date, sess.period, r.register_no';
        $rows = Database::fetchAll($sql, $params);
        $out = "register_no,student_name,subject,section,year,date,period,status,attendance_pct\n";
        foreach ($rows as $r) {
            $pct = self::percentForRegister($classId, $subjectId, (string)$r['register_no']);
            $out .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                self::csvCell((string)$r['register_no']),
                self::csvCell((string)($r['full_name'] ?? '')),
                self::csvCell((string)($r['subject_name'] ?? '')),
                self::csvCell((string)($r['section'] ?? '')),
                self::csvCell((string)($r['year'] ?? '')),
                self::csvCell((string)$r['session_date']),
                self::csvCell((string)$r['period']),
                self::csvCell((string)$r['status']),
                self::csvCell((string)$pct['percent'])
            );
        }
        return $out;
    }

    private static function csvCell(string $v): string
    {
        if (str_contains($v, ',') || str_contains($v, '"') || str_contains($v, "\n")) {
            return '"' . str_replace('"', '""', $v) . '"';
        }
        return $v;
    }

    /**
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function requestRegularization(array $student, int $sessionId, string $requestedStatus, string $reason, ?string $proofUrl): array
    {
        self::ensureSchema();
        $requestedStatus = self::normalizeStatus($requestedStatus);
        if ($requestedStatus === '') {
            return ['ok' => false, 'error' => 'Invalid requested status.'];
        }
        $reason = trim($reason);
        if ($reason === '') {
            return ['ok' => false, 'error' => 'Reason is required.'];
        }
        $session = Database::fetch('SELECT * FROM attendance_sessions WHERE id = ?', [$sessionId]);
        if (!$session || (int)$session['institution_id'] !== (int)$student['institution_id']) {
            return ['ok' => false, 'error' => 'Session not found.'];
        }
        if ((int)$session['class_id'] !== student_class_id($student)) {
            return ['ok' => false, 'error' => 'Not your class session.'];
        }
        $reg = self::studentRegisterNo($student, (int)$session['class_id']);
        $rec = Database::fetch(
            'SELECT * FROM attendance_records WHERE session_id = ? AND (student_id = ? OR register_no = ?)',
            [$sessionId, (int)$student['id'], $reg]
        );
        if (!$rec) {
            return ['ok' => false, 'error' => 'No attendance record found for this session.'];
        }
        $pending = Database::fetch(
            'SELECT id FROM attendance_regularization_requests
             WHERE session_id = ? AND student_id = ? AND status = "pending"',
            [$sessionId, (int)$student['id']]
        );
        if ($pending) {
            return ['ok' => false, 'error' => 'You already have a pending request for this session.'];
        }
        $id = (int)Database::insert('attendance_regularization_requests', [
            'institution_id' => (int)$student['institution_id'],
            'session_id' => $sessionId,
            'record_id' => (int)$rec['id'],
            'student_id' => (int)$student['id'],
            'register_no' => (string)$rec['register_no'],
            'original_status' => (string)$rec['status'],
            'requested_status' => $requestedStatus,
            'reason' => $reason,
            'proof_url' => $proofUrl,
            'status' => 'pending',
        ]);
        notify_user(
            (int)$session['professor_id'],
            'attendance_regularization',
            'Regularization request',
            (string)($student['full_name'] ?? 'Student') . ' requested ' . $requestedStatus . ' for ' . $session['session_date'],
            '/professor/attendance.php?class_id=' . (int)$session['class_id'] . '&subject_id=' . (int)$session['subject_id'] . '&tab=tools',
            [
                'priority' => 'medium',
                'category' => 'attendance',
                'action' => [
                    'type' => 'VIEW_ATTENDANCE',
                    'class_id' => (int)$session['class_id'],
                    'subject_id' => (int)$session['subject_id'],
                ],
            ]
        );
        return ['ok' => true, 'id' => $id];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public static function decideRegularization(array $professor, int $requestId, string $decision, string $note = ''): array
    {
        self::ensureSchema();
        $req = Database::fetch('SELECT * FROM attendance_regularization_requests WHERE id = ?', [$requestId]);
        if (!$req || (string)$req['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Request not found or already decided.'];
        }
        if ((int)$req['institution_id'] !== (int)$professor['institution_id']) {
            return ['ok' => false, 'error' => 'Access denied.'];
        }
        $session = Database::fetch('SELECT * FROM attendance_sessions WHERE id = ?', [(int)$req['session_id']]);
        if (!$session) {
            return ['ok' => false, 'error' => 'Session missing.'];
        }
        if (!professor_can_manage_subject($professor, (int)$session['subject_id'], (int)$session['class_id'])) {
            return ['ok' => false, 'error' => 'Not authorized for this class/subject.'];
        }
        $decision = strtolower($decision);
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'Invalid decision.'];
        }
        Database::update('attendance_regularization_requests', [
            'status' => $decision,
            'professor_note' => $note !== '' ? $note : null,
            'decided_by' => (int)$professor['id'],
            'decided_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $requestId]);

        if ($decision === 'approved') {
            self::setRecordStatus(
                (int)$req['session_id'],
                (string)$req['register_no'],
                (int)$req['student_id'],
                (string)$req['requested_status'],
                ['via' => 'regularization', 'request_id' => $requestId]
            );
        }
        notify_user(
            (int)$req['student_id'],
            'attendance_regularization',
            'Regularization ' . $decision,
            $decision === 'approved'
                ? 'Your attendance was updated to ' . $req['requested_status'] . '.'
                : 'Your regularization request was rejected.' . ($note !== '' ? ' ' . $note : ''),
            '/student/attendance.php',
            [
                'priority' => 'medium',
                'category' => 'attendance',
                'action' => ['type' => 'STUDENT_ATTENDANCE'],
            ]
        );
        return ['ok' => true];
    }

    /** @return list<array<string,mixed>> */
    public static function pendingRegularizations(array $user, int $classId, int $subjectId): array
    {
        self::ensureSchema();
        if (!professor_can_manage_subject($user, $subjectId, $classId)) {
            return [];
        }
        return Database::fetchAll(
            'SELECT r.*, u.full_name, sess.session_date, sess.period, sub.name AS subject_name
             FROM attendance_regularization_requests r
             JOIN attendance_sessions sess ON sess.id = r.session_id
             LEFT JOIN users u ON u.id = r.student_id
             LEFT JOIN subjects sub ON sub.id = sess.subject_id
             WHERE sess.class_id = ? AND sess.subject_id = ? AND r.institution_id = ?
             ORDER BY FIELD(r.status,"pending","approved","rejected"), r.id DESC',
            [$classId, $subjectId, (int)$user['institution_id']]
        );
    }

    /**
     * Week buckets for heatmap extension (actual data only).
     * @return list<array{label:string,start:string,end:string}>
     */
    public static function weekBuckets(string $monthStart, string $monthEnd): array
    {
        $buckets = [];
        $t = strtotime($monthStart) ?: time();
        $end = strtotime($monthEnd) ?: time();
        $n = 1;
        while ($t <= $end && $n <= 6) {
            $wStart = date('Y-m-d', $t);
            $wEnd = date('Y-m-d', min($end, strtotime('+6 days', $t) ?: $t));
            $buckets[] = ['label' => 'Week ' . $n, 'start' => $wStart, 'end' => $wEnd];
            $t = strtotime('+7 days', $t) ?: ($end + 1);
            $n++;
        }
        return $buckets;
    }

    /**
     * @param list<array<string,mixed>> $heatSessions
     * @param array<string,array<int,string>> $heat
     * @return array<string,array<string,string>> reg => weekLabel => High|Medium|Low|—
     */
    public static function weeklyHeatLevels(array $roster, array $heatSessions, array $heat, array $weekBuckets): array
    {
        $out = [];
        foreach ($roster as $st) {
            $reg = (string)$st['register_no'];
            $out[$reg] = [];
            foreach ($weekBuckets as $wb) {
                $present = 0;
                $total = 0;
                foreach ($heatSessions as $s) {
                    $d = (string)$s['session_date'];
                    if ($d < $wb['start'] || $d > $wb['end']) {
                        continue;
                    }
                    $stt = $heat[$reg][(int)$s['id']] ?? '';
                    if ($stt === '') {
                        continue;
                    }
                    $total++;
                    if (self::isPresentStatus($stt)) {
                        $present++;
                    }
                }
                if ($total === 0) {
                    $out[$reg][$wb['label']] = '—';
                } else {
                    $pct = $present * 100 / $total;
                    $out[$reg][$wb['label']] = $pct >= 80 ? 'High' : ($pct >= 60 ? 'Medium' : 'Low');
                }
            }
        }
        return $out;
    }
}
