<?php
/* ===============================
🔐 AUTH & SECURITY
=============================== */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow([2,4]);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';

header('Content-Type: application/json; charset=utf-8');


try {
    $db = (new Database())->connect();

    // ดึงข้อมูลการประชุมทั้งหมดมาทำ Event ปฏิทิน
    $stmt = $db->prepare("SELECT meeting_id, meeting_title, meeting_date, meeting_time, meeting_status FROM meeting");
    $stmt->execute();
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];

    foreach ($meetings as $m) {
        // กำหนดสีของแถบกิจกรรมตามสถานะการประชุมให้สอดคล้องกับหน้าแรก
        $color = '#0284c7'; // 🔵 สีฟ้าเริ่มต้น (upcoming)
        if (($m['meeting_status'] ?? '') === 'ongoing') {
            $color = '#22c55e'; // 🟢 สีเขียว (ongoing)
        } elseif (($m['meeting_status'] ?? '') === 'closed') {
            $color = '#94a3b8'; // ⚪ สีเทา (closed)
        }

        // รวมวันและเวลาเริ่มต้นกิจกรรมในรูปแบบ ISO 8601 (YYYY-MM-DDTHH:MM)
        $start_datetime = $m['meeting_date'] . 'T' . $m['meeting_time'];

        $events[] = [
            'id'    => $m['meeting_id'],
            'title' => $m['meeting_title'],
            'start' => $start_datetime,
            'color' => $color,
            'textColor' => '#ffffff'
        ];
    }

    echo json_encode($events, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // ป้องกันการแสดง PHP Error บนหน้าจอจนทำให้โครงสร้าง JSON พัง
    echo json_encode([]);
    exit;
}