<?php
/* ===============================
🔐 AUTH & SECURITY
=============================== */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(1);

ini_set('display_errors', 0);
error_reporting(0);

ob_start();

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/models/User.php';
require_once __DIR__ . '/../../../app/controllers/UserController.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !AuthMiddleware::verifyCsrf()) {
    http_response_code(419);
    echo json_encode(['status' => 'error', 'message' => 'โทเคนความปลอดภัยไม่ถูกต้อง']);
    exit;
}

$db = (new Database())->connect();
$userModel = new User($db);
$userController = new UserController($userModel);

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$response = []; 

switch ($action) {

    case "get":
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $response = ["status" => "error", "message" => "ไม่พบ ID ที่ต้องการเรียกดู"];
        } else {
            $response = $userController->getUserById($id);
        }
        break;

    case "create":
    case "update":
        // ปรับให้รองรับภาควิชาในทุกระดับสิทธิ์อย่างอิสระตามหน้าจอเลือกจริง
        if (!isset($_POST['department_id']) || $_POST['department_id'] === '') {
            $_POST['department_id'] = null;
        } else {
            $_POST['department_id'] = (int)$_POST['department_id'];
        }

        $file = $_FILES['picture'] ?? null;

        if ($action === "create") {
            $response = $userController->createUser($_POST, $file);
        } else {
            $response = $userController->updateUser($_POST, $file);
        }
        break;

    case "delete":
        $id = $_POST['id'] ?? null;
        if (!$id) {
            $response = ["status" => "error", "message" => "ไม่พบ ID ที่ต้องการลบ"];
        } else {
            $response = $userController->deleteUser($id);
        }
        break;

    default:
        $response = ["status" => "error", "message" => "การทำงานไม่ถูกต้อง (Invalid action)"];
}

ob_clean();
echo json_encode($response);
exit;