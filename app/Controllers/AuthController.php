<?php
/**
 * Restaurant POS & Management SaaS
 * Authentication Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\CSRF;
use App\Core\Auth;
use App\Services\AuthService;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function showLogin(Request $request): void
    {
        // If already logged in, redirect directly to dashboard
        if (Auth::check()) {
            Response::redirect('/dashboard');
        }

        Response::render('auth/login', [
            'flashes' => Session::getFlashes(),
            'csrf_field' => CSRF::formField(),
            'title' => 'Sign In - ServeFlow POS'
        ], 'auth');
    }

    public function login(Request $request): void
    {
        // Validate CSRF
        if (!CSRF::validate($request->post('csrf_token'))) {
            Session::setFlash('danger', 'Security validation failed (CSRF). Please try again.');
            Response::redirect('/login');
        }

        $login = (string)$request->post('login', '');
        $password = (string)$request->post('password', '');
        $tenantSlug = (string)$request->post('tenant_slug', '');

        $result = $this->authService->authenticate($login, $password, $tenantSlug ?: null);

        if (!$result['success']) {
            Session::setFlash('danger', $result['error']);
            Response::redirect('/login');
        }

        // Bind user to session
        Auth::login($result['user'], $result['is_platform']);

        // Check if user must change default password
        if (!empty($result['user']['must_change_password'])) {
            Session::setFlash('warning', 'Please set a new secure password before continuing.');
            Response::redirect('/change-password');
        }

        Session::setFlash('success', 'Welcome back, ' . htmlspecialchars($result['user']['full_name']) . '!');
        Response::redirect('/dashboard');
    }

    public function showChangePassword(Request $request): void
    {
        Auth::requireLogin();

        Response::render('auth/change_password', [
            'flashes' => Session::getFlashes(),
            'csrf_field' => CSRF::formField(),
            'title' => 'Change Password - Restaurant POS'
        ], 'auth');
    }

    public function changePassword(Request $request): void
    {
        Auth::requireLogin();

        if (!CSRF::validate($request->post('csrf_token'))) {
            Session::setFlash('danger', 'Security token expired. Please try again.');
            Response::redirect('/change-password');
        }

        $newPassword = (string)$request->post('new_password', '');
        $confirmPassword = (string)$request->post('confirm_password', '');

        if ($newPassword !== $confirmPassword) {
            Session::setFlash('danger', 'New password and confirmation do not match.');
            Response::redirect('/change-password');
        }

        $userId = Auth::id();
        $isPlatform = Auth::isPlatformAdmin();

        $result = $this->authService->updatePassword($userId, $newPassword, $isPlatform);

        if (!$result['success']) {
            Session::setFlash('danger', $result['error']);
            Response::redirect('/change-password');
        }

        Session::setFlash('success', 'Your password has been successfully updated.');
        Response::redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Session::setFlash('info', 'You have been signed out successfully.');
        Response::redirect('/login');
    }
}
