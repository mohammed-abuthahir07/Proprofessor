<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Department;
use Database;

/**
 * Examination timetable. Admin schedules one subject exam per row against the
 * academic details students already carry (level, year, department, class
 * section, semester), so the student calendar can match without duplicating any
 * academic data. Nothing here touches the class/attendance timetable or the
 * subject catalog.
 */
final class ExamTimetableController extends Controller
{
    private const TYPES = [
        'end_semester' => 'End Semester Exam',
        'internal' => 'Internal Exam',
        'practical' => 'Practical',
        'lab' => 'Lab',
    ];

    private const LEVELS = ['UG' => 'UG', 'PG' => 'PG'];

    private const SEMESTERS = [
        'Odd Semester' => 'Odd',
        'Even Semester' => 'Even',
    ];

    private const PAGE_SIZE = 15;

    public function index(): void
    {
        $this->requireRole('admin', 'superadmin');
        ensure_exam_timetable_schema();
        $instId = (int)$this->user()['institution_id'];
        $filters = $this->filters();
        $rows = $this->rows($instId, $filters);

        $this->view('admin/exam-timetable', [
            'title' => 'Exam Timetable',
            'active' => 'exams',
            'subtitle' => 'Schedule and manage examinations for each year, section, and semester',
            'rows' => $rows,
            'summary' => $this->summary($rows),
            'departmentOptions' => Department::forInstitution($instId),
            'subjectOptions' => $this->subjectOptions($instId),
            'classOptions' => $this->classOptions($instId),
            'years' => $this->yearOptions(),
            'semesters' => self::SEMESTERS,
            'levels' => self::LEVELS,
            'types' => self::TYPES,
            'filters' => $filters,
            'pageSize' => self::PAGE_SIZE,
            'returnTo' => $this->listPath($filters),
        ]);
    }

    public function store(): void
    {
        $this->requireRole('admin', 'superadmin');
        ensure_exam_timetable_schema();
        $this->verifyCsrf();
        $action = (string)$this->post('action');
        match ($action) {
            'add_exam' => $this->addExam(),
            'update_exam' => $this->updateExam(),
            'delete_exam' => $this->deleteExam(),
            default => $this->flash('error', 'Unknown exam timetable action.'),
        };
        $back = (string)$this->post('return_to', '/admin/exam-timetable');
        if (!str_starts_with($back, '/admin/exam-timetable')) {
            $back = '/admin/exam-timetable';
        }
        $this->redirect($back);
    }

    private function addExam(): void
    {
        $actor = $this->user();
        $instId = (int)$actor['institution_id'];
        $input = $this->readInput($instId);
        if ($input === null) {
            return;
        }
        if ($this->duplicate($instId, $input, 0)) {
            $this->flash('error', 'That subject already has an exam on ' . $this->dateLabel($input['exam_date']) . ' for this department, year, and semester.');
            return;
        }
        Database::insert('exam_timetable', $input + [
            'institution_id' => $instId,
            'created_by' => (int)$actor['id'],
        ]);
        $this->flash('success', 'Exam scheduled: ' . $input['subject_name'] . ' on ' . $this->dateLabel($input['exam_date']) . '.');
    }

    private function updateExam(): void
    {
        $instId = (int)$this->user()['institution_id'];
        $row = $this->examInInstitution((int)$this->post('exam_id', 0), $instId);
        if (!$row) {
            $this->flash('error', 'That exam schedule was not found.');
            return;
        }
        $input = $this->readInput($instId);
        if ($input === null) {
            return;
        }
        if ($this->duplicate($instId, $input, (int)$row['id'])) {
            $this->flash('error', 'That subject already has an exam on ' . $this->dateLabel($input['exam_date']) . ' for this department, year, and semester.');
            return;
        }
        Database::update('exam_timetable', $input, 'id = :id AND institution_id = :institution_id', [
            'id' => (int)$row['id'],
            'institution_id' => $instId,
        ]);
        $this->flash('success', 'Exam schedule updated.');
    }

    private function deleteExam(): void
    {
        $instId = (int)$this->user()['institution_id'];
        $row = $this->examInInstitution((int)$this->post('exam_id', 0), $instId);
        if (!$row) {
            $this->flash('error', 'That exam schedule was not found.');
            return;
        }
        Database::query(
            'DELETE FROM exam_timetable WHERE id = :id AND institution_id = :institution_id',
            ['id' => (int)$row['id'], 'institution_id' => $instId]
        );
        $this->flash('success', 'Exam schedule deleted. The subject and department were not changed.');
    }

    /**
     * Validated form payload shared by add and update. Column names match the
     * table so both writes can use it directly.
     *
     * @return array<string,mixed>|null
     */
    private function readInput(int $instId): ?array
    {
        $deptId = (int)$this->post('department_id', 0);
        $year = (int)$this->post('year_level', 0);
        $semester = (string)$this->post('semester', '');
        $level = strtoupper(trim((string)$this->post('academic_level', '')));
        $name = trim((string)$this->post('subject_name', ''));
        $classId = (int)$this->post('class_id', 0);
        $date = $this->cleanDate((string)$this->post('exam_date', ''));
        $start = $this->cleanTime((string)$this->post('start_time', ''));
        $end = $this->cleanTime((string)$this->post('end_time', ''));
        $type = (string)$this->post('exam_type', 'end_semester');
        $hall = trim((string)$this->post('exam_hall', ''));
        $isLab = $this->post('is_lab') ? 1 : 0;

        if (!$this->departmentInInstitution($deptId, $instId)) {
            $this->flash('error', 'Choose a department from your institution.');
            return null;
        }
        if ($year < 1 || $year > 4 || !isset(self::SEMESTERS[$semester])) {
            $this->flash('error', 'Choose the academic year and semester for this exam.');
            return null;
        }
        if (!isset(self::LEVELS[$level])) {
            $this->flash('error', 'Choose the academic level (UG or PG).');
            return null;
        }
        if ($name === '') {
            $this->flash('error', 'Enter the subject for this exam.');
            return null;
        }
        if ($date === null) {
            $this->flash('error', 'Enter a valid exam date.');
            return null;
        }
        if ($start === null || $end === null) {
            $this->flash('error', 'Enter the exam start and end time.');
            return null;
        }
        if ($end <= $start) {
            $this->flash('error', 'The end time must be later than the start time.');
            return null;
        }
        if (!isset(self::TYPES[$type])) {
            $this->flash('error', 'Choose the exam type.');
            return null;
        }

        // A class/section is optional; when given it must agree with the scope above,
        // otherwise students in that class would never match the row.
        $section = null;
        if ($classId > 0) {
            $class = Database::fetch(
                'SELECT * FROM classes WHERE id = :id AND institution_id = :institution_id',
                ['id' => $classId, 'institution_id' => $instId]
            );
            if (!$class) {
                $this->flash('error', 'That class was not found in your institution.');
                return null;
            }
            if ((int)$class['department_id'] !== $deptId) {
                $this->flash('error', 'That class belongs to a different department. Pick a class from the selected department.');
                return null;
            }
            $classYear = (int)($class['year'] ?? 0);
            if ($classYear > 0 && $classYear !== $year) {
                $this->flash('error', 'That class is ' . subject_year_label($classYear) . '. Choose the matching year or leave the class blank.');
                return null;
            }
            $classLevel = class_program_level($class);
            if ($classLevel !== '' && $classLevel !== $level) {
                $this->flash('error', 'That class is ' . $classLevel . '. Choose the matching academic level or leave the class blank.');
                return null;
            }
            $section = trim((string)($class['section'] ?? ''));
            if ($section === '') {
                $section = null;
            }
        }

        return [
            'department_id' => $deptId,
            'subject_id' => $this->matchSubject($instId, $deptId, $name),
            'subject_name' => mb_substr($name, 0, 200),
            'academic_level' => $level,
            'year_level' => $year,
            'class_id' => $classId > 0 ? $classId : null,
            'section' => $section,
            'semester' => $semester,
            'exam_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'exam_type' => $type,
            'is_lab' => $isLab,
            'exam_hall' => $hall !== '' ? mb_substr($hall, 0, 100) : null,
        ];
    }

    /** @return array{department_id:int,academic_level:string,year_level:int,semester:string,exam_type:string,date:string} */
    private function filters(): array
    {
        $type = (string)$this->get('exam_type', '');
        if (!isset(self::TYPES[$type])) {
            $type = '';
        }
        $semester = (string)$this->get('semester', '');
        if (!isset(self::SEMESTERS[$semester])) {
            $semester = '';
        }
        $level = strtoupper(trim((string)$this->get('academic_level', '')));
        if (!isset(self::LEVELS[$level])) {
            $level = '';
        }
        $year = (int)$this->get('year_level', 0);
        return [
            'department_id' => max(0, (int)$this->get('department_id', 0)),
            'academic_level' => $level,
            'year_level' => ($year >= 1 && $year <= 4) ? $year : 0,
            'semester' => $semester,
            'exam_type' => $type,
            'date' => $this->cleanDate((string)$this->get('date', '')) ?? '',
        ];
    }

    /** @param array{department_id:int,academic_level:string,year_level:int,semester:string,exam_type:string,date:string} $filters */
    private function listPath(array $filters): string
    {
        $query = array_filter([
            'department_id' => $filters['department_id'] > 0 ? (string)$filters['department_id'] : '',
            'academic_level' => $filters['academic_level'],
            'year_level' => $filters['year_level'] > 0 ? (string)$filters['year_level'] : '',
            'semester' => $filters['semester'],
            'exam_type' => $filters['exam_type'],
            'date' => $filters['date'],
        ], static fn(string $value): bool => $value !== '');
        return '/admin/exam-timetable' . ($query ? '?' . http_build_query($query) : '');
    }

    /**
     * @param array{department_id:int,academic_level:string,year_level:int,semester:string,exam_type:string,date:string} $filters
     * @return list<array<string,mixed>>
     */
    private function rows(int $instId, array $filters): array
    {
        $sql = 'SELECT t.*, d.name AS dept_name, d.code AS dept_code,
                       c.name AS class_name, c.section AS class_section
                FROM exam_timetable t
                LEFT JOIN departments d ON d.id = t.department_id
                LEFT JOIN classes c ON c.id = t.class_id
                WHERE t.institution_id = :institution_id';
        $params = ['institution_id' => $instId];
        if ($filters['department_id'] > 0) {
            $sql .= ' AND t.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }
        if ($filters['academic_level'] !== '') {
            $sql .= ' AND t.academic_level = :academic_level';
            $params['academic_level'] = $filters['academic_level'];
        }
        if ($filters['year_level'] > 0) {
            $sql .= ' AND t.year_level = :year_level';
            $params['year_level'] = $filters['year_level'];
        }
        if ($filters['semester'] !== '') {
            $sql .= ' AND t.semester = :semester';
            $params['semester'] = $filters['semester'];
        }
        if ($filters['exam_type'] !== '') {
            $sql .= ' AND t.exam_type = :exam_type';
            $params['exam_type'] = $filters['exam_type'];
        }
        if ($filters['date'] !== '') {
            $sql .= ' AND t.exam_date = :exam_date';
            $params['exam_date'] = $filters['date'];
        }
        $sql .= ' ORDER BY t.exam_date, t.start_time, t.subject_name';
        return array_map(
            fn(array $row): array => $this->present($row),
            Database::fetchAll($sql, $params)
        );
    }

    /** @param array<string,mixed> $row */
    private function present(array $row): array
    {
        $date = (string)$row['exam_date'];
        $start = substr((string)$row['start_time'], 0, 5);
        $end = substr((string)$row['end_time'], 0, 5);
        $year = (int)$row['year_level'];
        $semester = (string)$row['semester'];
        $classId = $row['class_id'] !== null ? (int)$row['class_id'] : 0;
        $section = trim((string)($row['section'] ?? ''));
        $className = trim((string)($row['class_name'] ?? ''));
        $scope = 'All sections';
        if ($classId > 0) {
            $scope = ($className !== '' ? $className : 'Class ' . $classId)
                . ($section !== '' ? ' · Sec ' . $section : '');
        }
        return [
            'id' => (int)$row['id'],
            'subject_id' => $row['subject_id'] !== null ? (int)$row['subject_id'] : null,
            'subject_name' => (string)$row['subject_name'],
            'dept_id' => (int)$row['department_id'],
            'dept_name' => (string)($row['dept_name'] ?? ''),
            'dept_code' => (string)($row['dept_code'] ?? ''),
            'academic_level' => (string)$row['academic_level'],
            'year_level' => $year,
            'year_label' => subject_year_label($year),
            'class_id' => $classId,
            'section' => $section,
            'scope_label' => $scope,
            'semester' => $semester,
            'semester_label' => self::SEMESTERS[$semester] ?? $semester,
            'exam_date' => $date,
            'date_label' => $this->dateLabel($date),
            'start_time' => $start,
            'end_time' => $end,
            'start_label' => $this->timeLabel($start),
            'end_label' => $this->timeLabel($end),
            'exam_type' => (string)$row['exam_type'],
            'type_label' => self::TYPES[(string)$row['exam_type']] ?? '',
            'is_lab' => (int)($row['is_lab'] ?? 0) === 1,
            'exam_hall' => trim((string)($row['exam_hall'] ?? '')),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{total:int,theory:int,lab:int,window:string}
     */
    private function summary(array $rows): array
    {
        $theory = 0;
        $lab = 0;
        $dates = [];
        foreach ($rows as $row) {
            if ($row['exam_type'] === 'lab' || $row['exam_type'] === 'practical' || $row['is_lab']) {
                $lab++;
            } else {
                $theory++;
            }
            $dates[] = (string)$row['exam_date'];
        }
        $window = '—';
        if ($dates !== []) {
            $first = min($dates);
            $last = max($dates);
            $window = $first === $last
                ? $this->dateLabel($first)
                : $this->dateLabel($first) . ' – ' . $this->dateLabel($last);
        }
        return [
            'total' => count($rows),
            'theory' => $theory,
            'lab' => $lab,
            'window' => $window,
        ];
    }

    /**
     * Existing subject catalog, used as suggestions only — no subject rows are created here.
     *
     * @return list<array{id:int,name:string,code:string,department_id:int,year:int,type:string}>
     */
    private function subjectOptions(int $instId): array
    {
        $rows = Database::fetchAll(
            'SELECT id, department_id, code, name, meta
             FROM subjects
             WHERE institution_id = :institution_id AND is_active = 1
             ORDER BY name',
            ['institution_id' => $instId]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'code' => (string)$row['code'],
                'department_id' => (int)$row['department_id'],
                'year' => subject_academic_year_level($row),
                'type' => subject_course_type($row),
            ];
        }
        return $out;
    }

    /** @return array<int,string> */
    private function yearOptions(): array
    {
        $out = [];
        for ($year = 1; $year <= 4; $year++) {
            $out[$year] = subject_year_label($year);
        }
        return $out;
    }

    /**
     * Existing classes, reused for the optional class/section scope.
     *
     * @return list<array{id:int,label:string,department_id:int,year:int,section:string,level:string}>
     */
    private function classOptions(int $instId): array
    {
        $out = [];
        foreach (academic_classes($instId) as $class) {
            $section = trim((string)($class['section'] ?? ''));
            $year = (int)($class['year'] ?? 0);
            $label = trim((string)$class['name']);
            if ($section !== '') {
                $label .= ' · Sec ' . $section;
            }
            if ($year > 0) {
                $label .= ' · ' . subject_year_label($year);
            }
            $out[] = [
                'id' => (int)$class['id'],
                'label' => $label,
                'department_id' => (int)$class['department_id'],
                'year' => $year,
                'section' => $section,
                'level' => class_program_level($class),
            ];
        }
        return $out;
    }

    /** Links the exam to an existing subject when the name matches that department's catalog. */
    private function matchSubject(int $instId, int $deptId, string $name): ?int
    {
        $row = Database::fetch(
            'SELECT id FROM subjects
             WHERE institution_id = :institution_id AND department_id = :department_id AND name = :name
             LIMIT 1',
            ['institution_id' => $instId, 'department_id' => $deptId, 'name' => $name]
        );
        return $row ? (int)$row['id'] : null;
    }

    /**
     * Same subject, same day, same audience. Two sections sitting the same paper
     * on the same date are separate rows and stay allowed.
     *
     * @param array<string,mixed> $input
     */
    private function duplicate(int $instId, array $input, int $ignoreId): bool
    {
        $row = Database::fetch(
            'SELECT id FROM exam_timetable
             WHERE institution_id = :institution_id
               AND department_id = :department_id
               AND academic_level = :academic_level
               AND year_level = :year_level
               AND semester = :semester
               AND subject_name = :subject_name
               AND exam_date = :exam_date
               AND ((class_id IS NULL AND :class_is_null = 1) OR class_id = :class_id)
               AND id <> :ignore_id
             LIMIT 1',
            [
                'institution_id' => $instId,
                'department_id' => $input['department_id'],
                'academic_level' => $input['academic_level'],
                'year_level' => $input['year_level'],
                'semester' => $input['semester'],
                'subject_name' => $input['subject_name'],
                'exam_date' => $input['exam_date'],
                'class_is_null' => $input['class_id'] === null ? 1 : 0,
                'class_id' => $input['class_id'] ?? 0,
                'ignore_id' => $ignoreId,
            ]
        );
        return $row !== null;
    }

    private function departmentInInstitution(int $deptId, int $instId): bool
    {
        if ($deptId < 1) {
            return false;
        }
        return Database::fetch(
            'SELECT id FROM departments WHERE id = :id AND institution_id = :institution_id',
            ['id' => $deptId, 'institution_id' => $instId]
        ) !== null;
    }

    /** @return array<string,mixed>|null */
    private function examInInstitution(int $id, int $instId): ?array
    {
        if ($id < 1) {
            return null;
        }
        return Database::fetch(
            'SELECT * FROM exam_timetable WHERE id = :id AND institution_id = :institution_id',
            ['id' => $id, 'institution_id' => $instId]
        );
    }

    private function cleanDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : null;
    }

    private function cleanTime(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $match)) {
            return null;
        }
        $hour = (int)$match[1];
        $minute = (int)$match[2];
        if ($hour > 23 || $minute > 59) {
            return null;
        }
        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function dateLabel(string $ymd): string
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);
        return $dt ? $dt->format('d M Y') : $ymd;
    }

    private function timeLabel(string $hm): string
    {
        $dt = \DateTime::createFromFormat('H:i', $hm);
        return $dt ? ltrim($dt->format('h:i A'), '0') : $hm;
    }
}
