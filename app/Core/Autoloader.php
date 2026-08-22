<?php
declare(strict_types=1);

namespace App\Core;

final class Autoloader
{
    public static function register(string $baseDir): void
    {
        spl_autoload_register(static function (string $class) use ($baseDir): void {
            $prefix = 'App\\';
            if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                return;
            }
            $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
            $file = $baseDir . DIRECTORY_SEPARATOR . $relative . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
