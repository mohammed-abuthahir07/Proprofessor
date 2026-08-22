<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Notification;
use App\Services\NavService;

final class NotificationController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $user = $this->user();
        $role = $user['role'];

        if ($this->get('read') === 'all') {
            Notification::markAllRead((int)$user['id']);
            $this->redirect('/' . $this->rolePrefix($role) . '/notifications');
        }
        if ($id = (int)$this->get('read_id')) {
            Notification::markRead($id, (int)$user['id']);
        }

        $type = $this->get('type');
        $this->view('shared/notifications', [
            'title' => 'Notifications',
            'active' => 'notifications',
            'subtitle' => 'Approvals, AI completions & system events',
            'rows' => Notification::forUser((int)$user['id'], $type ?: null),
            'rolePrefix' => $this->rolePrefix($role),
        ]);
    }

    private function rolePrefix(string $role): string
    {
        return match ($role) {
            'student' => 'student',
            'hod' => 'hod',
            'admin', 'superadmin' => 'admin',
            default => 'professor',
        };
    }
}
