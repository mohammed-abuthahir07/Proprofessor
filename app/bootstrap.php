<?php
declare(strict_types=1);

/**
 * MVC bootstrap — loads helpers, legacy services, and App autoloader.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Gemini.php';
require_once __DIR__ . '/../includes/Features.php';
require_once __DIR__ . '/../includes/Icons.php';
require_once __DIR__ . '/../includes/HodFeedback.php';
require_once __DIR__ . '/../includes/CoursePlanTools.php';
require_once __DIR__ . '/../includes/SimplePdf.php';
require_once __DIR__ . '/../includes/LessonPlanTools.php';
require_once __DIR__ . '/../includes/QuestionBankTools.php';
require_once __DIR__ . '/../includes/Permissions.php';

require_once __DIR__ . '/Core/Autoloader.php';
\App\Core\Autoloader::register(__DIR__);

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return base_url('/' . ltrim($path, '/'));
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url('/assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('route_is')) {
    function route_is(string $needle): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_contains($uri, $needle);
    }
}

if (!function_exists('icon')) {
    function icon(string $name, string $class = 'icon'): string
    {
        return Icons::svg($name, $class);
    }
}
