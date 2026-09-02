<?php
// index.php (หน้าแรกสุดของ Root โปรเจกต์ Meeting_msu)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ตรวจสอบว่าผู้ใช้งานเข้าสู่ระบบอยู่แล้วหรือไม่
if (isset($_SESSION['user_id']) && isset($_SESSION['role_id'])) {

    $role_id = (int)$_SESSION['role_id'];


    // แยกหน้าเว็บตาม role
    switch ($role_id) {

        case 1: // Admin
            header("Location: /public/admin/index.php");
            break;


        case 3: // Executive
            header("Location: /public/executives/index.php");
            break;


        case 4: // Department
            header("Location: /public/departments/index.php");
            break;


        case 2: // User
        default:
            header("Location: /public/users/index.php");
            break;
    }

    exit;

}


// ยังไม่ได้ Login
header("Location: /public/auth/login.php");
exit;