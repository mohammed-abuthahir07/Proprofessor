<?php
declare(strict_types=1);

use App\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Controllers\Admin\BillingController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\FeatureController;
use App\Controllers\Admin\FinanceController;
use App\Controllers\Admin\FormulaController;
use App\Controllers\Admin\InstitutionController;
use App\Controllers\Admin\NaacController;
use App\Controllers\Admin\UserController;
use App\Controllers\Api\AiController;
use App\Controllers\Auth\AuthController;
use App\Controllers\Hod\DashboardController as HodDashboardController;
use App\Controllers\Hod\StudentsController as HodStudentsController;
use App\Controllers\LegacyController;
use App\Controllers\NotificationController;
use App\Controllers\Professor\DashboardController as ProfessorDashboardController;
use App\Controllers\Student\DashboardController as StudentDashboardController;
use App\Core\Router;

/** @var Router $router */

$router->get('/', [AuthController::class, 'home']);
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// Admin (full MVC)
$router->get('/admin/dashboard', [AdminDashboardController::class, 'index']);
$router->get('/admin/institution', [InstitutionController::class, 'index']);
$router->post('/admin/institution', [InstitutionController::class, 'save']);
$router->get('/admin/users', [UserController::class, 'index']);
$router->post('/admin/users', [UserController::class, 'store']);
$router->get('/admin/features', [FeatureController::class, 'index']);
$router->post('/admin/features', [FeatureController::class, 'toggle']);
$router->get('/admin/finance', [FinanceController::class, 'index']);
$router->post('/admin/finance', [FinanceController::class, 'store']);
$router->get('/admin/formulas', [FormulaController::class, 'index']);
$router->post('/admin/formulas', [FormulaController::class, 'store']);
$router->get('/admin/naac', [NaacController::class, 'index']);
$router->get('/admin/analytics', [AdminAnalyticsController::class, 'index']);
$router->get('/admin/billing', [BillingController::class, 'index']);
$router->get('/admin/notifications', [NotificationController::class, 'index']);

// Role dashboards (MVC)
$router->get('/professor/dashboard', [ProfessorDashboardController::class, 'index']);
$router->get('/student/dashboard', [StudentDashboardController::class, 'index']);
$router->get('/hod/dashboard', [HodDashboardController::class, 'index']);
$router->get('/api/hod/students', [HodStudentsController::class, 'list']);

$router->get('/professor/notifications', [NotificationController::class, 'index']);
$router->get('/student/notifications', [NotificationController::class, 'index']);
$router->get('/hod/notifications', [NotificationController::class, 'index']);

// AI API
$router->post('/api/ai', [AiController::class, 'handle']);

// Remaining modules via MVC front controller bridge
$router->get('/professor/{page}', [LegacyController::class, 'professor']);
$router->post('/professor/{page}', [LegacyController::class, 'professor']);
$router->get('/student/{page}', [LegacyController::class, 'student']);
$router->post('/student/{page}', [LegacyController::class, 'student']);
$router->get('/hod/{page}', [LegacyController::class, 'hod']);
$router->post('/hod/{page}', [LegacyController::class, 'hod']);
