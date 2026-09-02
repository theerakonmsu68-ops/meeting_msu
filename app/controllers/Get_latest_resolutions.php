<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow([1, 2, 3, 4]);

require_once $_SERVER['DOCUMENT_ROOT'].'/app/config/database.php';

header('Content-Type: application/json');

$db = (new Database())->connect();

$stmt = $db->query("
SELECT

a.agenda_title,

r.resolution_detail,

r.status,

r.due_date,

u.name responsible_name

FROM resolution r

LEFT JOIN agenda a
ON a.agenda_id = r.agenda_id

LEFT JOIN user u
ON u.user_id = r.responsible_user

ORDER BY r.resolution_date DESC

LIMIT 10
");

echo json_encode(
$stmt->fetchAll()
);