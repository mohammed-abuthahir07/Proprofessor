<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Notification;
use NotificationService;

final class NotificationController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $user = $this->user();
        $role = $user['role'];
        $prefix = $this->rolePrefix($role);

        NotificationService::ensureSchema();

        if ($goId = (int)$this->get('go')) {
            $resolved = NotificationService::resolveAction($user, $goId);
            if (!$resolved['ok']) {
                flash('error', $resolved['error'] ?? 'Unable to open notification action.');
                $this->redirect('/' . $prefix . '/notifications');
            }
            Notification::markRead($goId, (int)$user['id']);
            $this->redirect($resolved['path']);
        }

        if ($this->get('read') === 'all') {
            Notification::markAllRead((int)$user['id']);
            $this->redirect('/' . $prefix . '/notifications');
        }
        if ($id = (int)$this->get('read_id')) {
            Notification::markRead($id, (int)$user['id']);
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('action') === 'generate_digest') {
            verify_csrf();
            $mode = (string)post('digest_mode', 'daily');
            NotificationService::publishDigest($user, $mode);
            flash('success', 'Digest summary added to your feed.');
            $this->redirect('/' . $prefix . '/notifications');
        }

        $type = $this->get('type');
        $priority = strtolower((string)$this->get('priority'));
        if (!in_array($priority, ['high', 'medium', 'low'], true)) {
            $priority = null;
        }

        $rows = Notification::forUser((int)$user['id'], $type ?: null, 100, $priority);
        foreach ($rows as &$n) {
            if (empty($n['priority'])) {
                $n['priority'] = NotificationService::inferPriority((string)$n['type'], (string)$n['title'], (string)($n['body'] ?? ''));
            }
        }
        unset($n);

        $prefs = NotificationService::preferencesFromUser($user);
        $digestMode = (string)($prefs['digest_mode'] ?? 'immediate');
        $digestPreview = null;
        if ($digestMode === 'daily' || $digestMode === 'weekly') {
            $digestPreview = NotificationService::buildDigest($user, $digestMode);
        } elseif ($this->get('digest') === 'daily' || $this->get('digest') === 'weekly') {
            $digestPreview = NotificationService::buildDigest($user, (string)$this->get('digest'));
        }

        $this->view('shared/notifications', [
            'title' => 'Notifications',
            'active' => 'notifications',
            'subtitle' => 'Approvals, AI completions & system events',
            'rows' => $rows,
            'rolePrefix' => $prefix,
            'typeFilter' => $type,
            'priorityFilter' => $priority,
            'providers' => NotificationService::allProviderStatuses(),
            'digestMode' => $digestMode,
            'digestPreview' => $digestPreview,
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
