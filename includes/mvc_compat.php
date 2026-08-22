<?php
declare(strict_types=1);
/**
 * Patch legacy module scripts to use MVC API path when included.
 * Also ensure NavService is available for layout.
 */
if (!class_exists(\App\Services\NavService::class) && is_file(__DIR__ . '/../app/Services/NavService.php')) {
    require_once __DIR__ . '/../app/Services/NavService.php';
}
