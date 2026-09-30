<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use Auth;
use StudyMaterialTools;

/** GET /api/study-materials/file?id={id} */
final class StudyMaterialFileController extends Controller
{
    public function download(): void
    {
        if (!Auth::check()) {
            http_response_code(401);
            echo 'Unauthorized';
            return;
        }
        $id = (int)($_GET['id'] ?? 0);
        StudyMaterialTools::stream(Auth::user(), $id);
        exit;
    }
}
