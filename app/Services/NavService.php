<?php
declare(strict_types=1);

namespace App\Services;

final class NavService
{
    public static function grouped(string $role): array
    {
        return match ($role) {
            'student' => [
                ['label' => 'MAIN', 'items' => [
                    ['key' => 'dash', 'label' => 'Dashboard', 'href' => '/student/dashboard', 'icon' => 'home'],
                    ['key' => 'courses', 'label' => 'My Courses', 'href' => '/student/courses', 'icon' => 'book', 'feature' => 'student_portal'],
                    ['key' => 'notes', 'label' => 'Course PPT', 'href' => '/student/notes', 'icon' => 'folder'],
                ]],
                ['label' => 'LEARNING', 'items' => [
                    ['key' => 'assignments', 'label' => 'Assignments', 'href' => '/student/assignments', 'icon' => 'edit'],
                    ['key' => 'ask', 'label' => 'Ask AI', 'href' => '/student/ask-ai', 'icon' => 'ai', 'feature' => 'ask_ai'],
                    ['key' => 'calendar', 'label' => 'Calendar', 'href' => '/student/calendar', 'icon' => 'calendar'],
                ]],
                ['label' => 'ACADEMIC', 'items' => [
                    ['key' => 'attendance', 'label' => 'Attendance', 'href' => '/student/attendance', 'icon' => 'calendar'],
                    ['key' => 'marks', 'label' => 'Internal Marks', 'href' => '/student/marks', 'icon' => 'chart'],
                    ['key' => 'history', 'label' => 'Academic History', 'href' => '/student/academic-history', 'icon' => 'clock'],
                    ['key' => 'notifications', 'label' => 'Notifications', 'href' => '/student/notifications', 'icon' => 'bell'],
                ]],
            ],
            'hod' => [
                ['label' => 'MAIN', 'items' => [
                    ['key' => 'dash', 'label' => 'Dashboard', 'href' => '/hod/dashboard', 'icon' => 'home'],
                    ['key' => 'approvals', 'label' => 'Approvals', 'href' => '/hod/approvals', 'icon' => 'check', 'feature' => 'hod_approvals'],
                    ['key' => 'faculty', 'label' => 'Faculty', 'href' => '/hod/faculty', 'icon' => 'users'],
                    ['key' => 'students', 'label' => 'Students', 'href' => '/hod/students', 'icon' => 'book'],
                    ['key' => 'subjects', 'label' => 'Courses', 'href' => '/hod/subjects', 'icon' => 'folder'],
                ]],
                ['label' => 'INSIGHTS', 'items' => [
                    ['key' => 'analytics', 'label' => 'Analytics', 'href' => '/hod/analytics', 'icon' => 'trend', 'feature' => 'dept_analytics'],
                    ['key' => 'compliance', 'label' => 'Compliance', 'href' => '/hod/compliance', 'icon' => 'alert'],
                    ['key' => 'timeline', 'label' => 'Timeline', 'href' => '/hod/timeline', 'icon' => 'clock'],
                ]],
                ['label' => 'REPORTS', 'items' => [
                    ['key' => 'reports', 'label' => 'NAAC Reports', 'href' => '/hod/reports', 'icon' => 'file', 'feature' => 'naac_reports'],
                    ['key' => 'notifications', 'label' => 'Notifications', 'href' => '/hod/notifications', 'icon' => 'bell'],
                ]],
            ],
            'admin', 'superadmin' => [
                ['label' => 'MAIN', 'items' => [
                    ['key' => 'dash', 'label' => 'Dashboard', 'href' => '/admin/dashboard', 'icon' => 'home'],
                    ['key' => 'institution', 'label' => 'Institution', 'href' => '/admin/institution', 'icon' => 'building', 'perm' => 'manage_institution'],
                    ['key' => 'users', 'label' => 'Users & Roles', 'href' => '/admin/users', 'icon' => 'users', 'feature' => 'user_management', 'perm' => 'manage_users'],
                ]],
                ['label' => 'OPERATIONS', 'items' => [
                    ['key' => 'features', 'label' => 'Feature Flags', 'href' => '/admin/features', 'icon' => 'puzzle', 'perm' => 'manage_features'],
                    ['key' => 'formulas', 'label' => 'Marks Formulas', 'href' => '/admin/formulas', 'icon' => 'formula', 'perm' => 'manage_formulas'],
                    ['key' => 'finance', 'label' => 'Finance', 'href' => '/admin/finance', 'icon' => 'finance', 'feature' => 'finance', 'perm' => 'manage_finance'],
                ]],
                ['label' => 'GROWTH', 'items' => [
                    ['key' => 'naac', 'label' => 'NAAC Builder', 'href' => '/admin/naac', 'icon' => 'file', 'feature' => 'naac_reports', 'perm' => 'manage_naac'],
                    ['key' => 'analytics', 'label' => 'Analytics', 'href' => '/admin/analytics', 'icon' => 'trend', 'perm' => 'view_analytics'],
                    ['key' => 'billing', 'label' => 'Subscription', 'href' => '/admin/billing', 'icon' => 'card', 'perm' => 'manage_billing'],
                    ['key' => 'notifications', 'label' => 'Notifications', 'href' => '/admin/notifications', 'icon' => 'bell'],
                ]],
            ],
            default => [
                ['label' => 'MAIN', 'items' => [
                    ['key' => 'dash', 'label' => 'Dashboard', 'href' => '/professor/dashboard', 'icon' => 'home'],
                    ['key' => 'generate', 'label' => 'New Course Plan', 'href' => '/professor/generate-plan', 'icon' => 'spark', 'feature' => 'ai_course_plan'],
                    ['key' => 'plans', 'label' => 'My Plans', 'href' => '/professor/plans', 'icon' => 'file', 'feature' => 'version_control'],
                ]],
                ['label' => 'AI TOOLS', 'items' => [
                    ['key' => 'lessons', 'label' => 'Lesson Planner', 'href' => '/professor/lessons', 'icon' => 'grid', 'feature' => 'lesson_planner'],
                    ['key' => 'questions', 'label' => 'Question Bank', 'href' => '/professor/questions', 'icon' => 'help', 'feature' => 'question_bank'],
                    ['key' => 'ppt', 'label' => 'PPT Generator', 'href' => '/professor/ppt', 'icon' => 'monitor', 'feature' => 'ppt_generator'],
                ]],
                ['label' => 'ACADEMIC', 'items' => [
                    ['key' => 'assignments', 'label' => 'Assignments', 'href' => '/professor/assignments', 'icon' => 'edit', 'feature' => 'assignment_ai'],
                    ['key' => 'attendance', 'label' => 'Attendance', 'href' => '/professor/attendance', 'icon' => 'calendar', 'feature' => 'attendance'],
                    ['key' => 'marks', 'label' => 'Internal Marks', 'href' => '/professor/marks', 'icon' => 'chart', 'feature' => 'internal_marks'],
                    ['key' => 'messages', 'label' => 'Message Students', 'href' => '/professor/messages', 'icon' => 'mail'],
                    ['key' => 'message-hod', 'label' => 'Message HOD', 'href' => '/professor/message-hod', 'icon' => 'users'],
                    ['key' => 'settings', 'label' => 'Settings', 'href' => '/professor/settings', 'icon' => 'settings'],
                    ['key' => 'notifications', 'label' => 'Notifications', 'href' => '/professor/notifications', 'icon' => 'bell', 'feature' => 'notifications'],
                ]],
            ],
        };
    }

    public static function forRole(string $role): array
    {
        $flat = [];
        foreach (self::grouped($role) as $group) {
            foreach ($group['items'] as $item) {
                $flat[] = $item;
            }
        }
        return $flat;
    }

    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'student' => 'Student View',
            'hod' => 'HOD View',
            'admin', 'superadmin' => 'Admin View',
            default => 'Professor View',
        };
    }

    public static function dashboardPath(string $role): string
    {
        return match ($role) {
            'student' => '/student/dashboard',
            'hod' => '/hod/dashboard',
            'admin', 'superadmin' => '/admin/dashboard',
            default => '/professor/dashboard',
        };
    }
}
