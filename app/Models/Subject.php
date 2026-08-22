<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Database;

final class Subject extends Model
{
    protected static string $table = 'subjects';

    public static function forProfessor(int $professorId): array
    {
        return Database::fetchAll(
            'SELECT s.* FROM subjects s
             JOIN subject_assignments sa ON sa.subject_id = s.id
             WHERE sa.professor_id = ?',
            [$professorId]
        );
    }

    public static function enrolledForStudent(int $studentId): array
    {
        $user = Database::fetch('SELECT * FROM users WHERE id = ?', [$studentId]);
        return $user ? courses_for_student($user) : [];
    }
}
