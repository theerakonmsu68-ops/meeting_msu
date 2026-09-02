<?php
/* ========================================================
🔐 API: ตรวจสอบสถานะการเข้าประชุมและบทบาทของผู้ใช้ (RSVP)
======================================================== */
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/config/database.php';

// กำหนดให้ไฟล์นี้ส่งออกข้อมูลเป็นรูปแบบ JSON
header('Content-Type: application/json');

// รับค่ารหัสการประชุมจากหน้าบ้าน และรหัสผู้ใช้จาก Session
$meeting_id = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : 0;
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// ถ้าข้อมูลไม่ครบถ้วน ให้ส่งค่าว่างกลับไปทันที
if (!$meeting_id || !$user_id) {
    echo json_encode(null);
    exit;
}

try {
    $db = (new Database())->connect();

    // ดึงข้อมูลสิทธิ์และสถานะเช็กชื่อจากตาราง meeting_attendance (อิงตามฐานข้อมูลจริงของคุณ)
    $stmt = $db->prepare("SELECT attendance_role, rsvp_status, is_present, 
                          DATE_FORMAT(checkin_time, '%H:%i น.') as checkin_time 
                          FROM meeting_attendance 
                          WHERE meeting_id = :mid AND user_id = :uid");
    
    $stmt->execute([
        ':mid' => $meeting_id,
        ':uid' => $user_id
    ]);
    
    $rsvp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rsvp) {
        // หากมีแถวข้อมูลอยู่แล้ว ให้ส่งข้อมูลก้อนนั้นกลับไป
        echo json_encode($rsvp);
    } else {
        // หากยังไม่มีการบันทึกนัดหมายในตารางนี้ ให้ส่งสถานะเริ่มต้นกลับไป (เพื่อให้ปุ่มเช็กชื่อเปิดทำงานได้)
        echo json_encode([
            "attendance_role" => "member",
            "rsvp_status" => "pending",
            "is_present" => 0,
            "checkin_time" => null
        ]);
    }

} catch (PDOException $e) {
    // กรณีระบบฐานข้อมูลขัดข้อง ให้ส่งค่า null
    echo json_encode(null);
}