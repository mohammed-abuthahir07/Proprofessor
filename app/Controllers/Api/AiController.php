<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;

final class AiController extends Controller
{
    public function handle(): void
    {
        $this->requireLogin();
        // Existing AI orchestration lives in api/ai.php (service-style script).
        require dirname(__DIR__, 3) . '/api/ai.php';
        exit;
    }
}
