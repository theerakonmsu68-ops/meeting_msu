<?php
/* ========================================================
🔐 API: บันทึกเวลาเช็กชื่อเข้าประชุม + รองรับหมายเหตุ (Submit Attendance)
======================================================== */

// 1. เรียกใช้งาน Middleware ตรวจสอบสิทธิ์การเข้าถึงเซสชัน
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';

// อนุญาตให้กลุ่มผู้ใช้งานที่มีสิทธิ์เข้าถึงระบบตัวนี้ใช้งานได้ (รวมถึง role_id = 4 ระดับภาควิชา)
AuthMiddleware::allow([1, 2, 3, 4]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !AuthMiddleware::verifyCsrf()) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'โทเคนความปลอดภัยไม่ถูกต้อง']);
    exit;
}

// 2. เรียกใช้งานไฟล์เชื่อมต่อฐานข้อมูลส่วนกลาง
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/config/database.php';

// ตั้งค่าให้ผลลัพธ์พ่นกลับไปเป็นรูปแบบ JSON เสมอ
header('Content-Type: application/json');

// 3. แกะข้อมูล JSON Raw Data ที่ถูกส่งมาจาก fetch() ฝั่งหน้าบ้าน
$data = json_decode(file_get_contents('php://input'), true);
$meeting_id = isset($data['meeting_id']) ? (int)$data['meeting_id'] : 0;

// รับค่าหมายเหตุตอบรับเพิ่มเติมจากหน้าบ้าน
$rsvp_remark = isset($data['rsvp_remark']) ? trim($data['rsvp_remark']) : '';
if (empty($rsvp_remark)) { 
    $rsvp_remark = null; // ถ้าผู้ใช้ไม่ได้พิมพ์อะไรส่งมา ให้บันทึกเป็น NULL ลงในตารางฐานข้อมูลตามเดิม
}

// ดึงรหัสผู้ใช้ (user_id) ที่บันทึกไว้ใน Session ส่วนกลาง
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// ตรวจสอบความถูกต้องเบื้องต้นก่อนลงมือทำงาน
if (!$meeting_id || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน หรือเซสชันหมดอายุ']);
    exit;
}

try {
    $db = (new Database())->connect();

    // 4. เช็กว่าผู้ใช้รายนี้เคยมีรายการนัดหมายในตารางนี้อยู่ก่อนหน้าแล้วหรือไม่
    $check_stmt = $db->prepare("SELECT attendance_id FROM meeting_attendance WHERE meeting_id = :mid AND user_id = :uid");
    $check_stmt->execute([':mid' => $meeting_id, ':uid' => $user_id]);
    $attendance = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($attendance) {
        // กรณีที่ผู้บริหารนัดชื่อไว้รออยู่แล้ว -> ทำการอัปเดตสถานะเช็กชื่อ และผูกค่าหมายเหตุ
        $update_sql = "UPDATE meeting_attendance 
                      SET rsvp_status = 'attending', 
                          is_present = 1, 
                          checkin_time = NOW(),
                          rsvp_remark = :remark 
                      WHERE meeting_id = :mid AND user_id = :uid";
        $stmt = $db->prepare($update_sql);
    } else {
        // กรณีไม่มีแถวชื่อเดิม (เช็กชื่อแบบด่วน/Walk-in) -> ทำการสร้างแถวใหม่ลงฐานข้อมูลพร้อมหมายเหตุ
        $insert_sql = "INSERT INTO meeting_attendance (meeting_id, user_id, attendance_role, rsvp_status, is_present, checkin_time, rsvp_remark) 
                      VALUES (:mid, :uid, 'member', 'attending', 1, NOW(), :remark)";
        $stmt = $db->prepare($insert_sql);
    }

    // 5. ส่งพารามิเตอร์และประมวลผลคำสั่ง SQL บันทึกข้อมูลจริง
    $params = [
        ':mid'    => $meeting_id,
        ':uid'    => $user_id,
        ':remark' => $rsvp_remark // บันทึกข้อความหมายเหตุที่พิมพ์กรอกมา หรือค่า null ลงฟิลด์ rsvp_remark
    ];

    if ($stmt->execute($params)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ไม่สามารถบันทึกข้อมูลลงตารางได้']);
    }

} catch (PDOException $e) {
    error_log('Submit attendance error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดทางเทคนิค กรุณาลองใหม่อีกครั้ง']);
}