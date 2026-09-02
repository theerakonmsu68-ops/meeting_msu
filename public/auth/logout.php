<?php

require_once __DIR__ . '/../../app/bootstrap.php';

// ลบข้อมูล session ทั้งหมด
$_SESSION = [];

// ลบ cookie session (ถ้ามี)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ทำลาย session
session_destroy();

// กัน cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// กลับหน้า login
header("Location: " . BASE_URL . "auth/login.php");
exit;