<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class FeatureFlag extends Model
{
    protected static string $table = 'feature_flags';
}
