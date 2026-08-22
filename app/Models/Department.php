<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Database;

final class Department extends Model
{
    protected static string $table = 'departments';

    public static function forInstitution(int $institutionId): array
    {
        return self::where('institution_id = ?', [$institutionId]);
    }
}
