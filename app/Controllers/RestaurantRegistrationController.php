<?php
/**
 * Public self-service restaurant registration.
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\CSRF;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Throwable;

class RestaurantRegistrationController
{
    public function show(Request $request): void
    {
        if (\App\Core\Auth::check()) {
            Response::redirect('/dashboard');
        }

        Response::render('auth/register', [
            'csrf_field' => CSRF::formField(),
            'flashes' => Session::getFlashes(),
            'title' => 'Create Your Restaurant Account - ServeFlow POS'
        ], 'auth');
    }

    public function register(Request $request): void
    {
        if (!CSRF::validate($request->post('csrf_token'))) {
            Session::setFlash('danger', 'Your security token expired. Please try again.');
            Response::redirect('/register');
        }

        $restaurantName = trim((string)$request->post('restaurant_name', ''));
        $ownerName = trim((string)$request->post('owner_name', ''));
        $email = strtolower(trim((string)$request->post('email', '')));
        $phone = trim((string)$request->post('phone', ''));
        $username = strtolower(trim((string)$request->post('username', '')));
        $password = (string)$request->post('password', '');
        $confirm = (string)$request->post('password_confirmation', '');

        if ($restaurantName === '' || mb_strlen($restaurantName) > 150 ||
            $ownerName === '' || mb_strlen($ownerName) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100 ||
            $phone === '' || mb_strlen($phone) > 30 ||
            !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username) ||
            strlen($password) < 10 || $password !== $confirm) {
            Session::setFlash('danger', 'Please complete all fields correctly. Username must be 3–50 characters; password must be at least 10 characters and match confirmation.');
            Response::redirect('/register');
        }

        try {
            $existing = Database::fetchOne(
                "SELECT id FROM users WHERE username = ? OR email = ? UNION SELECT id FROM platform_users WHERE username = ? OR email = ? LIMIT 1",
                [$username, $email, $username, $email]
            );
            if ($existing) {
                Session::setFlash('danger', 'That username or email is already in use. Please sign in or choose another.');
                Response::redirect('/register');
            }

            $pdo = Database::getInstance();
            $pdo->beginTransaction();

            $baseSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $restaurantName), '-'));
            $baseSlug = substr($baseSlug !== '' ? $baseSlug : 'restaurant', 0, 80);
            $slug = $baseSlug;
            $suffix = 2;
            while (Database::fetchOne("SELECT id FROM restaurants WHERE slug = ?", [$slug])) {
                $slug = substr($baseSlug, 0, 88) . '-' . $suffix++;
            }

            $activationSetting = Database::fetchOne("SELECT setting_value FROM platform_settings WHERE setting_key = 'activation_fee_php'");
            $activationFee = (float)($activationSetting['setting_value'] ?? 399.00);
            Database::query(
                "INSERT INTO restaurants (name, slug, contact_email, contact_phone, currency_symbol, timezone, status, activation_status, activation_fee)
                 VALUES (?, ?, ?, ?, '₱', 'Asia/Manila', 'active', 'pending', ?)",
                [$restaurantName, $slug, $email, $phone, $activationFee]
            );
            $restaurantId = (int)$pdo->lastInsertId();

            Database::query(
                "INSERT INTO roles (restaurant_id, name, slug, description) VALUES (?, 'Owner', 'owner', 'Restaurant owner with management access')",
                [$restaurantId]
            );
            $roleId = (int)$pdo->lastInsertId();

            // Give the initial owner the application's registered permissions only.
            Database::query(
                "INSERT INTO role_permissions (restaurant_id, role_id, permission_id)
                 SELECT ?, ?, id FROM permissions",
                [$restaurantId, $roleId]
            );

            Database::query(
                "INSERT INTO users (restaurant_id, role_id, username, email, password_hash, full_name, status, must_change_password)
                 VALUES (?, ?, ?, ?, ?, ?, 'active', 0)",
                [$restaurantId, $roleId, $username, $email, password_hash($password, PASSWORD_DEFAULT), $ownerName]
            );

            // No automatic trial or subscription is created at registration.\n            // The owner signs in and submits the one-time activation payment first.

            $pdo->commit();
            Session::setFlash('success', 'Your ServeFlow POS account has been created. Sign in to submit your one-time activation payment. Restaurant slug: ' . $slug);
            Response::redirect('/login');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[ServeFlow registration] ' . $e->getMessage());
            Session::setFlash('danger', 'Registration could not be completed. Please try again later or contact support.');
            Response::redirect('/register');
        }
    }
}
