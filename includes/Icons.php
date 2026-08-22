<?php
declare(strict_types=1);

/**
 * Inline SVG icon set — avoids broken emoji/encoding glyphs.
 */
final class Icons
{
    /** @var array<string, string> path-only SVGs (viewBox 0 0 24 24) */
    private static array $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1v-10.5z"/>',
        'spark' => '<path d="M12 2l1.2 6.3L19 10l-5.8 1.7L12 18l-1.2-6.3L5 10l5.8-1.7L12 2zm7 11 .7 3.3L23 17l-3.3.7L19 21l-.7-3.3L15 17l3.3-.7L19 13zM4 14l.6 2.6L7 17.5 4.6 18 4 21l-.6-2.5L1 17.5l2.4-.9L4 14z"/>',
        'file' => '<path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-6zm0 2.5L17.5 8H14V4.5zM8 12h8v1.5H8V12zm0 4h8v1.5H8V16z"/>',
        'book' => '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v18H6.5A2.5 2.5 0 0 0 4 22.5V4.5zm2 0V19a1 1 0 0 1 1-1H18V3.5H6.5c-.28 0-.5.22-.5.5z"/>',
        'help' => '<path d="M12 2a10 10 0 1 0 .01 20.01A10 10 0 0 0 12 2zm0 15.2a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4zm1.4-4.55c-.62.36-1 .9-1 1.85h-1.6c0-1.45.66-2.35 1.55-2.86.7-.4 1.15-.75 1.15-1.44 0-.8-.65-1.3-1.55-1.3-.95 0-1.6.55-1.7 1.45H8.6C8.8 7.9 10.15 6.7 12 6.7c1.95 0 3.3 1.15 3.3 2.85 0 1.2-.65 1.9-1.9 2.6z"/>',
        'monitor' => '<path d="M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-5l1 2h2v2H7v-2h2l1-2H5a2 2 0 0 1-2-2V5zm2 0v10h14V5H5z"/>',
        'edit' => '<path d="M4 17.25V20h2.75L17.8 8.95l-2.75-2.75L4 17.25zM20.7 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 2.75 2.75 1.83-1.83z"/>',
        'calendar' => '<path d="M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm12 7H5v10h14V9z"/>',
        'chart' => '<path d="M4 19h16v2H4v-2zm2-2V9h2v8H6zm5 0V5h2v12h-2zm5 0v-5h2v5h-2z"/>',
        'settings' => '<path d="M19.4 13a7.7 7.7 0 0 0 .1-1 7.7 7.7 0 0 0-.1-1l2-1.55-2-3.45-2.4.95a7.3 7.3 0 0 0-1.7-1L13 2h-2l-.4 2.95a7.3 7.3 0 0 0-1.7 1L6.5 5l-2 3.45L6.5 10a7.7 7.7 0 0 0-.1 1 7.7 7.7 0 0 0 .1 1L4.5 14.55 6.5 18l2.4-.95a7.3 7.3 0 0 0 1.7 1L11 22h2l.4-2.95a7.3 7.3 0 0 0 1.7-1l2.4.95 2-3.45L19.4 13zM12 15.5A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 0 7z"/>',
        'bell' => '<path d="M12 22a2.2 2.2 0 0 0 2.2-2.2h-4.4A2.2 2.2 0 0 0 12 22zm7-5.5V11a7 7 0 0 0-5-6.7V3.8a2 2 0 1 0-4 0v.5A7 7 0 0 0 5 11v5.5L3 18.5V20h18v-1.5l-2-2z"/>',
        'users' => '<path d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zM8 12a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 8 12zm8 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4zM8 14.2c-.33-.03-.66-.05-1-.05-2.5 0-7.5 1.2-7.5 3.6V20h6.5v-1.8c0-.9.35-2.55 2-3.55A10.4 10.4 0 0 0 8 14.2z"/>',
        'building' => '<path d="M4 21V5l8-3 8 3v16h-5v-6H9v6H4zm5-8h6V8H9v5z"/>',
        'check' => '<path d="M9.2 16.6 4.8 12.2l1.7-1.7 2.7 2.7 8.3-8.3 1.7 1.7-10 10z"/>',
        'alert' => '<path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>',
        'clock' => '<path d="M12 2a10 10 0 1 0 .01 20.01A10 10 0 0 0 12 2zm1 11H7.5v-1.5H11V6.8h2V13z"/>',
        'puzzle' => '<path d="M20.5 11H19V7a2 2 0 0 0-2-2h-4V3.5a2.5 2.5 0 0 0-5 0V5H4a2 2 0 0 0-2 2v3.5h1.5a2.5 2.5 0 0 1 0 5H2V19a2 2 0 0 0 2 2h3.5v-1.5a2.5 2.5 0 0 1 5 0V21H17a2 2 0 0 0 2-2v-4h1.5a2.5 2.5 0 0 0 0-5z"/>',
        'card' => '<path d="M3 5h18a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zm0 2v2h18V7H3zm0 5v5h18v-5H3z"/>',
        'folder' => '<path d="M10 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-8l-2-2z"/>',
        'grid' => '<path d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/>',
        'download' => '<path d="M12 3v10.2l3.4-3.4 1.4 1.4L12 17.4 7.2 11.2l1.4-1.4L11 13.2V3h2zM5 19h14v2H5v-2z"/>',
        'logout' => '<path d="M10 17v2H4V5h6v2H6v10h4zm3.5-1.5 1.4-1.4L12.8 12H20v-2h-7.2l2.1-2.1-1.4-1.4L9 11l4.5 4.5z"/>',
        'menu' => '<path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z"/>',
        'close' => '<path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>',
        'finance' => '<path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm1 15h-2v-1.1c-1.7-.4-2.8-1.6-2.8-3.2h1.8c0 .9.7 1.5 2 1.5 1.1 0 1.8-.5 1.8-1.2 0-.8-.5-1.1-2.1-1.5-2-.5-3.3-1.3-3.3-3.1 0-1.5 1.1-2.6 2.6-3V6h2v1.1c1.5.3 2.6 1.4 2.7 2.9h-1.8c-.1-.8-.7-1.4-1.8-1.4-1 0-1.6.5-1.6 1.2 0 .7.5 1.1 2.2 1.5 2 .5 3.2 1.4 3.2 3.2 0 1.6-1.1 2.7-2.9 3.1V17z"/>',
        'formula' => '<path d="M5 4h6v2H8.4l3.1 4.2L15.6 6H13V4h6v2h-1.2l-4.5 6 4.7 6.2H19v2h-6v-2h2.7l-3.3-4.4L8.9 18H11v2H5v-2h1.3l4.5-6.2L6.3 6H5V4z"/>',
        'trend' => '<path d="M3 17.5 9.5 11l3.5 3.5L21 6.5 19.5 5l-6.5 6.5L9.5 8 1.5 16l1.5 1.5zM21 18v2H3v-2h18z"/>',
        'ai' => '<path d="M12 2a4 4 0 0 1 4 4v1.1A5 5 0 0 1 19 12v2a3 3 0 0 1-2 2.8V19a3 3 0 0 1-3 3h-4a3 3 0 0 1-3-3v-2.2A3 3 0 0 1 5 14v-2a5 5 0 0 1 3-4.6V6a4 4 0 0 1 4-4zm-1 15v2h2v-2h-2zm-3-7a3 3 0 0 0-3 3v2h2v-1a2 2 0 0 1 2-2h1V10H8zm8 0h-1v2h1a2 2 0 0 1 2 2v1h2v-2a3 3 0 0 0-3-3z"/>',
        'mail' => '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm0 2v.5l8 5 8-5V6H4zm16 3.2-7.4 4.6a1 1 0 0 1-1.2 0L4 9.2V18h16V9.2z"/>',
        'lock' => '<path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2zm-7-2a2 2 0 1 1 4 0v2h-4V7zm7 13H7v-9h10v9zm-5-3.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/>',
    ];

    public static function svg(string $name, string $class = 'icon'): string
    {
        $path = self::$paths[$name] ?? self::$paths['spark'];
        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="currentColor">' . $path . '</svg>';
    }

    public static function img(string $src, string $alt = '', string $class = ''): string
    {
        $url = function_exists('asset') ? asset($src) : (rtrim((string)(function_exists('app_base_path') ? app_base_path() : ''), '/') . '/assets/' . ltrim($src, '/'));
        return '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="' . htmlspecialchars($alt, ENT_QUOTES) . '" class="' . htmlspecialchars($class, ENT_QUOTES) . '" loading="lazy" decoding="async">';
    }
}
