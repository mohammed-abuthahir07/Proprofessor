<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $viewFile = self::path($view);
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);
        $content = static function () use ($viewFile, $data): void {
            extract($data, EXTR_SKIP);
            require $viewFile;
        };

        if ($layout === null) {
            $content();
            return;
        }

        $layoutFile = self::path($layout);
        if (!is_file($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }
        require $layoutFile;
    }

    public static function partial(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require self::path($view);
    }

    private static function path(string $view): string
    {
        $view = str_replace(['.', '\\'], ['/', '/'], $view);
        return dirname(__DIR__) . '/Views/' . $view . '.php';
    }
}
