<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow([1, 2, 3, 4]);

require_once $_SERVER['DOCUMENT_ROOT'].'/app/config/database.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;

$db = (new Database())->connect();

$stmt = $db->prepare("
SELECT
notification_id,
title,
message,
is_read,
created_at
FROM notifications
WHERE user_id = :uid
ORDER BY created_at DESC
LIMIT 10
");

$stmt->execute([
':uid' => $user_id
]);

echo json_encode(
$stmt->fetchAll()
);