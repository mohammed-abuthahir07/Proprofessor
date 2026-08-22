<?php
declare(strict_types=1);

final class Permissions
{
    /** @return array<string,string> */
    public static function catalog(): array
    {
        return [
            'manage_institution' => 'Institution setup',
            'manage_users' => 'Users & roles',
            'manage_features' => 'Feature flags',
            'manage_formulas' => 'Marks formulas',
            'manage_finance' => 'Finance',
            'manage_naac' => 'NAAC builder',
            'view_analytics' => 'Analytics',
            'manage_billing' => 'Subscription',
        ];
    }

    public static function extra(array $user): array
    {
        $extra = json_decode((string)($user['extra'] ?? ''), true);
        return is_array($extra) ? $extra : [];
    }

    /** @return list<string>|null null = full college-admin access */
    public static function listed(array $user): ?array
    {
        $extra = self::extra($user);
        if (!isset($extra['permissions']) || !is_array($extra['permissions'])) {
            return null;
        }
        $allowed = array_keys(self::catalog());
        return array_values(array_intersect($extra['permissions'], $allowed));
    }

    public static function isFullAdmin(array $user): bool
    {
        $role = (string)($user['role'] ?? '');
        if ($role === 'superadmin') {
            return true;
        }
        if ($role !== 'admin') {
            return false;
        }
        return self::listed($user) === null;
    }

    public static function can(?array $user, string $permission): bool
    {
        if (!$user) {
            return false;
        }
        $role = (string)($user['role'] ?? '');
        if ($role === 'superadmin') {
            return true;
        }
        if ($role !== 'admin') {
            return false;
        }
        $listed = self::listed($user);
        if ($listed === null) {
            return true;
        }
        return in_array($permission, $listed, true);
    }

    /** @param list<string> $selected */
    public static function encode(?array $selected): ?string
    {
        if ($selected === null || $selected === []) {
            return null;
        }
        $allowed = array_keys(self::catalog());
        $perms = array_values(array_intersect($selected, $allowed));
        if ($perms === []) {
            return null;
        }
        return json_encode(['permissions' => $perms], JSON_UNESCAPED_UNICODE);
    }
}

function admin_can(string $permission): bool
{
    return Permissions::can(Auth::user(), $permission);
}

function require_admin_perm(string $permission): void
{
    Auth::requireRole('admin', 'superadmin');
    if (!admin_can($permission)) {
        flash('error', 'You do not have permission for that module.');
        redirect('/admin/dashboard');
    }
}
