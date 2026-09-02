<?php
// index.php (หน้าแรกสุดของ Root โปรเจกต์ Meeting_msu)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🚦 ตรวจสอบว่าผู้ใช้งานเข้าสู่ระบบอยู่แล้วหรือไม่
if (isset($_SESSION['user_id']) && isset($_SESSION['role_id'])) {
    
    $role_id = (int)$_SESSION['role_id'];

    // 🚀 ทางแยกอัจฉริยะ: ตรวจจับ role_id (1-4) แล้วส่งไปโฟลเดอร์ที่ถูกต้องทันที
    switch ($role_id) {
        case 1: // แอดมิน (ผู้ดูแลระบบ)
            header("Location: public/admin/index.php");
            break;
            
        case 3: // ผู้บริหาร (ผู้บริหาร สาขาวิชา)
            header("Location: public/executives/index.php");
            break;
            
        case 4: // ภาควิชา (ภาควิชาต่างๆ)
            header("Location: public/departments/index.php");
            break;
            
        case 2: // ผู้ใช้ (ผู้ใช้งานทั่วไป / กรรมการ)
        default:
            header("Location: public/users/index.php");
            break;
    }
    exit;
}

// 🔒 ถ้ายังไม่ได้เข้าสู่ระบบ (ไม่มี Session ค้างไว้) ให้ส่งไปหน้า Login ตามปกติ
header("Location: public/auth/login.php");
exit;