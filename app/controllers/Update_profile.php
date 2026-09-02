<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow([1, 2, 3, 4]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !AuthMiddleware::verifyCsrf()) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'โทเคนความปลอดภัยไม่ถูกต้อง']);
    exit;
}

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';

header('Content-Type: application/json; charset=UTF-8');

function profileJson(bool $success, string $message, int $status = 200, array $extra = []): never
{
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profileJson(false, 'Method not allowed', 405);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    profileJson(false, 'เซสชันหมดอายุ กรุณาล็อกอินใหม่', 401);
}

$name = trim((string)($_POST['name'] ?? ''));
if (mb_strlen($name, 'UTF-8') < 2) {
    profileJson(false, 'กรุณาระบุชื่อ–นามสกุลอย่างน้อย 2 ตัวอักษร', 422);
}
if (mb_strlen($name, 'UTF-8') > 150) {
    profileJson(false, 'ชื่อ–นามสกุลยาวเกิน 150 ตัวอักษร', 422);
}

try {
    $db = (new Database())->connect();
    $db->beginTransaction();

    $oldStmt = $db->prepare('SELECT picture FROM user WHERE user_id = :id LIMIT 1');
    $oldStmt->execute([':id' => $userId]);
    $oldPicture = (string)($oldStmt->fetchColumn() ?: '');

    $newImageName = null;
    $newImagePath = null;
    $uploadDir = __DIR__ . '/../../public/uploads/avatars/';

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            profileJson(false, 'เกิดข้อผิดพลาดระหว่างอัปโหลดรูปภาพ', 422);
        }
        if ((int)$file['size'] > 2 * 1024 * 1024) {
            profileJson(false, 'ขนาดไฟล์ภาพห้ามเกิน 2 MB', 422);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            profileJson(false, 'ไม่พบไฟล์อัปโหลดที่ถูกต้อง', 422);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($file['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mime])) {
            profileJson(false, 'รองรับเฉพาะไฟล์ JPG, PNG และ WEBP เท่านั้น', 422);
        }

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            profileJson(false, 'ไม่สามารถสร้างโฟลเดอร์สำหรับรูปโปรไฟล์ได้', 500);
        }

        $newImageName = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        $newImagePath = $uploadDir . $newImageName;

        if (!move_uploaded_file($file['tmp_name'], $newImagePath)) {
            profileJson(false, 'ไม่สามารถบันทึกรูปภาพได้', 500);
        }
        @chmod($newImagePath, 0644);
    }

    if ($newImageName !== null) {
        $stmt = $db->prepare('UPDATE user SET name = :name, picture = :picture WHERE user_id = :id');
        $stmt->execute([':name' => $name, ':picture' => $newImageName, ':id' => $userId]);
    } else {
        $stmt = $db->prepare('UPDATE user SET name = :name WHERE user_id = :id');
        $stmt->execute([':name' => $name, ':id' => $userId]);
    }

    $db->commit();

    if ($newImageName !== null && $oldPicture !== '') {
        $isUrl = filter_var($oldPicture, FILTER_VALIDATE_URL) !== false;
        if (!$isUrl) {
            $oldBase = basename(str_replace('\\', '/', $oldPicture));
            $oldPath = $uploadDir . $oldBase;
            if ($oldBase !== $newImageName && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }
    }

    $_SESSION['name'] = $name;
    if ($newImageName !== null) {
        $_SESSION['picture'] = $newImageName;
    }

    profileJson(true, 'อัปเดตข้อมูลโปรไฟล์เรียบร้อยแล้ว', 200, [
        'name' => $name,
        'picture' => $newImageName ?? ($_SESSION['picture'] ?? null),
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    if (isset($newImagePath) && $newImagePath && is_file($newImagePath)) {
        @unlink($newImagePath);
    }
    profileJson(false, 'เกิดข้อผิดพลาดในการบันทึกโปรไฟล์', 500);
}