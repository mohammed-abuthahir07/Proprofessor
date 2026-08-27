<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Gemini.php';
require_once __DIR__ . '/Features.php';
require_once __DIR__ . '/Icons.php';
require_once __DIR__ . '/HodFeedback.php';
require_once __DIR__ . '/CoursePlanTools.php';
require_once __DIR__ . '/SimplePdf.php';
require_once __DIR__ . '/LessonPlanTools.php';
require_once __DIR__ . '/QuestionBankTools.php';
require_once __DIR__ . '/PresentationTools.php';
require_once __DIR__ . '/LectureSlideBuilder.php';
require_once __DIR__ . '/AssignmentTools.php';
require_once __DIR__ . '/AttendanceTools.php';
require_once __DIR__ . '/ProfessorMessageTools.php';
require_once __DIR__ . '/StudentAcademicHistoryTools.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/Permissions.php';
require_once __DIR__ . '/mvc_compat.php';

// Load MVC autoloader when available (legacy pages + front controller)
$autoload = dirname(__DIR__) . '/app/Core/Autoloader.php';
if (is_file($autoload)) {
    require_once $autoload;
    \App\Core\Autoloader::register(dirname(__DIR__) . '/app');
}

Auth::start();

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return base_url('/' . ltrim($path, '/'));
    }
}
