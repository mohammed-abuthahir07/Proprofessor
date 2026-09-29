<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('hod', 'admin');
$user = Auth::user();
redirect(($user['role'] ?? '') === 'hod' ? '/hod/dashboard' : '/admin/dashboard');
