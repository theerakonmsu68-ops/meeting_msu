<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow([1, 2, 3, 4]);

require_once $_SERVER['DOCUMENT_ROOT'].'/Meeting_msu/app/config/database.php';

header('Content-Type: application/json');

$meeting_id =
(int)($_GET['meeting_id'] ?? 0);

$db = (new Database())->connect();

$stmt = $db->prepare("
SELECT
u.name,
ma.attendance_role,
ma.rsvp_status,
ma.checkin_time

FROM meeting_attendance ma

JOIN user u
ON u.user_id = ma.user_id

WHERE ma.meeting_id = ?

ORDER BY u.name
");

$stmt->execute([
$meeting_id
]);

echo json_encode(
$stmt->fetchAll()
);