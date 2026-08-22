<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Database;

final class MarksFormula extends Model
{
    protected static string $table = 'marks_formulas';

    public static function forInstitution(int $institutionId): array
    {
        return self::where('institution_id = ? ORDER BY is_default DESC, id DESC', [$institutionId]);
    }
}
