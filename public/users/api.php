<?php
/* ===============================
🔐 AUTH & SECURITY
=============================== */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(2);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$db = (new Database())->connect();
$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

if ($action === 'get_rsvp') {
    $meeting_id = $_GET['meeting_id'] ?? '';
    
    if (empty($meeting_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing meeting_id']);
        exit;
    }

    try {
        $query = "SELECT attendance_role, is_present, checkin_time 
                  FROM meeting_attendance 
                  WHERE meeting_id = :meeting_id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':meeting_id', $meeting_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode($result);
        } else {
            // กรณีไม่มีแถวข้อมูลผูกอยู่ ให้ส่งสถานะทั่วไปเพื่อไปแปลค่าที่หน้าบ้าน
            echo json_encode([
                'attendance_role' => 'general_user', 
                'is_present' => 0, 
                'checkin_time' => null
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit;