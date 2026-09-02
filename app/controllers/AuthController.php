<?php

class AuthController
{
    private $userModel;

    public function __construct($userModel)
    {
        $this->userModel = $userModel;
    }

    public function login($username, $password): array
    {
        $username = trim((string)$username);
        $password = (string)$password;

        if ($username === '' || $password === '') {
            return ['status' => 'error', 'message' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน'];
        }

        $user = $this->userModel->login($username, $password);
        if (!$user) {
            return ['status' => 'error', 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }

        if (($user['status'] ?? '') !== 'active') {
            return ['status' => (string)($user['status'] ?? 'inactive'), 'message' => 'บัญชีไม่สามารถใช้งานได้'];
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['name'] = (string)$user['name'];
        $_SESSION['email'] = (string)$user['email'];
        $_SESSION['username'] = (string)$user['username'];
        $_SESSION['role_id'] = (int)$user['role_id'];
        $_SESSION['picture'] = $user['picture'] ?? null;
        $_SESSION['status'] = (string)$user['status'];
        $_SESSION['login_type'] = (string)($user['login_type'] ?? 'normal');
        $_SESSION['position_name'] = (string)($user['position_name'] ?? '');
        $_SESSION['department_name'] = (string)($user['department_name'] ?? '');
        $_SESSION['role_name'] = (string)($user['role_name'] ?? '');

        return ['status' => 'success', 'role_id' => (int)$user['role_id']];
    }
}