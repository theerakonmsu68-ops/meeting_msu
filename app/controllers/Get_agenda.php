<?php
/* ===============================
🔐 AUTH & SECURITY
=============================== */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow([2,4]);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';

// เชื่อมต่อฐานข้อมูล
$db = (new Database())->connect();

// รับค่า ID การประชุมที่ส่งมาจาก JavaScript (ดักจับให้เป็นตัวเลขเสมอเพื่อความปลอดภัย)
$meeting_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($meeting_id > 0) {
    // ดึงข้อมูลวาระที่ผูกอยู่กับการประชุมนี้ เรียงตาม agenda_id
$query = "
    SELECT 
        a.*
    FROM agenda a
    WHERE 
        a.meeting_id = :mid
        AND a.admin_status = 'approved'
    ORDER BY 
        a.order_index ASC,
        a.agenda_id ASC
";
    $stmt = $db->prepare($query);
    $stmt->execute([':mid' => $meeting_id]);
    $agendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $agendas = [];
}

// กำหนดให้ไฟล์นี้ส่งออกข้อมูลเป็นรูปแบบ JSON เพื่อให้ JavaScript หน้าบ้านเอาไปลูปต่อได้
header('Content-Type: application/json; charset=utf-8');
echo json_encode($agendas, JSON_UNESCAPED_UNICODE);