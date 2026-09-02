<?php
// app/middleware/AuthMiddleware.php

class AuthMiddleware
{
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(): bool
    {
        $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
        return $token !== ''
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        self::csrfToken();

        if (empty($_SESSION['user_id']) || empty($_SESSION['role_id'])) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '';
            header('Location: /Meeting_msu/public/auth/login.php');
            exit;
        }
    }

    /** @param array|int $allowed_roles */
    public static function allow($allowed_roles): void
    {
        self::init();

        $currentRole = (int)($_SESSION['role_id'] ?? 0);
        $allowed = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
        $allowed = array_map('intval', $allowed);

        if (!in_array($currentRole, $allowed, true)) {
            self::redirectToCorrectDashboard($currentRole);
        }
    }

    private static function redirectToCorrectDashboard(int $roleId): void
    {
        switch ($roleId) {
            case 1:
                header('Location: /Meeting_msu/public/admin/index.php');
                break;
            case 3:
                header('Location: /Meeting_msu/public/executives/index.php');
                break;
            case 4:
                header('Location: /Meeting_msu/public/departments/index.php');
                break;
            case 2:
            default:
                header('Location: /Meeting_msu/public/users/index.php');
                break;
        }
        exit;
    }
}