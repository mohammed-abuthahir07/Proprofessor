<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Database;

final class Assignment extends Model
{
    protected static string $table = 'assignments';

    public static function forProfessor(int $professorId): array
    {
        return self::where('professor_id = ? ORDER BY id DESC', [$professorId]);
    }

    public static function forStudent(int $studentId): array
    {
        $user = Database::fetch('SELECT id, institution_id, class_id FROM users WHERE id = ?', [$studentId]);
        return $user ? assignments_visible_to_student($user) : [];
    }
}
