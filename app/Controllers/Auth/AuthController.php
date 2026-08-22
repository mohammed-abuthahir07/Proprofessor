<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Services\NavService;
use Auth;

final class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            $this->redirect(NavService::dashboardPath(Auth::user()['role']));
        }
        $this->view('auth/login', [
            'title' => 'Login',
            'error' => '',
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $email = trim((string)$this->post('email'));
        $password = (string)$this->post('password');
        if (Auth::attempt($email, $password)) {
            log_activity('login');
            $this->redirect(NavService::dashboardPath(Auth::user()['role']));
        }
        $this->view('auth/login', [
            'title' => 'Login',
            'error' => Auth::lastLoginError() ?: 'Invalid email or password.',
            'email' => $email,
        ], 'layouts/auth');
    }

    public function logout(): void
    {
        if (Auth::check()) {
            log_activity('logout');
        }
        Auth::logout();
        $this->redirect('/');
    }

    public function home(): void
    {
        if (Auth::check()) {
            $this->redirect(NavService::dashboardPath(Auth::user()['role']));
        }
        $this->view('landing/home', [
            'title' => 'Transform Academic Management',
        ], 'layouts/landing');
    }
}
