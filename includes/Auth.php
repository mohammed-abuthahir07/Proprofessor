<?php
declare(strict_types=1);

final class Auth
{
    public static function start(): void
    {
        $cfg = require __DIR__ . '/../config/config.php';
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name($cfg['session_name']);
            session_start();
        }
        date_default_timezone_set($cfg['timezone']);
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = Database::fetch(
            'SELECT * FROM users WHERE email = ? LIMIT 1',
            [$email]
        );
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        if (!(int)$user['is_active']) {
            $_SESSION['login_error'] = 'This account is inactive. Ask an admin to reactivate it.';
            return false;
        }
        Database::query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        unset($user['password_hash']);
        unset($_SESSION['login_error']);
        $_SESSION['user'] = $user;
        return true;
    }

    public static function lastLoginError(): string
    {
        $msg = (string)($_SESSION['login_error'] ?? '');
        unset($_SESSION['login_error']);
        return $msg;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/auth/login.php');
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        $role = self::user()['role'] ?? '';
        if (!in_array($role, $roles, true)) {
            http_response_code(403);
            flash('error', 'You do not have permission to access that page.');
            redirect(dashboard_path_for_role($role));
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function refresh(): void
    {
        if (!self::id()) return;
        $user = Database::fetch('SELECT * FROM users WHERE id = ?', [self::id()]);
        if ($user) {
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
        }
    }
}
