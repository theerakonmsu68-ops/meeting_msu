<?php
/* ===============================
🔐 AUTH & SECURITY
=============================== */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow([1, 3, 4]);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';

// ฟังก์ชันช่วยแสดงหน้าจอแจ้งเตือนดีไซน์โมเดิร์น
function showModernAlert($title, $message, $type = 'error') {
    $icon = $type === 'success' ? 'check-circle' : 'shield-alert';
    $primary_color = $type === 'success' ? '#10b981' : '#ef4444';
    $bg_icon_color = $type === 'success' ? 'rgba(16, 185, 129, 0.08)' : 'rgba(239, 68, 68, 0.08)';
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">s
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="icon" href="../assets/image/logo.svg">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
        <script src="https://unpkg.com/lucide@latest"></script>
        <style>
            * {
                box-sizing: border-box;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            body {
                background-color: #f8fafc;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .alert-card {
                background: #ffffff;
                width: 100%;
                max-width: 440px;
                padding: 32px 24px;
                border-radius: 24px;
                box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
                border: 1px solid #f1f5f9;
                text-align: center;
                animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            }
            @keyframes slideUp {
                from { opacity: 0; transform: translateY(16px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .icon-wrapper {
                width: 64px;
                height: 64px;
                background-color: <?= $bg_icon_color ?>;
                color: <?= $primary_color ?>;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px auto;
            }
            .alert-title {
                font-size: 18px;
                font-weight: 600;
                color: #0f172a;
                margin: 0 0 8px 0;
            }
            .alert-message {
                font-size: 14px;
                color: #64748b;
                line-height: 1.6;
                margin: 0 0 28px 0;
                font-weight: 400;
            }
            .btn-back {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                padding: 12px 20px;
                background-color: #0f172a;
                color: #ffffff;
                border: none;
                border-radius: 12px;
                font-size: 14px;
                font-weight: 500;
                text-decoration: none;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .btn-back:hover {
                background-color: #1e293b;
                transform: translateY(-1px);
            }
            .btn-back:active {
                transform: translateY(0);
            }
        </style>
    </head>
    <body>
        <div class="alert-card">
            <div class="icon-wrapper">
                <i data-lucide="<?= $icon ?>" style="width: 28px; height: 28px; stroke-width: 2px;"></i>
            </div>
            <h1 class="alert-title"><?= htmlspecialchars($title) ?></h1>
            <p class="alert-message"><?= htmlspecialchars($message) ?></p>
            <a href="meeting_history.php" class="btn-back">
                <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
                <span>กลับสู่หน้าประวัติการประชุม</span>
            </a>
        </div>
        <script>
            lucide.createIcons();
        </script>
    </body>
    </html>
    <?php
    exit;
}

// 1. ตรวจสอบล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 403 Forbidden");
    showModernAlert("Access Denied", "กรุณาเข้าสู่ระบบด้วยบัญชีผู้ใช้งานของคุณก่อน จึงจะสามารถดาวน์โหลดเอกสารการประชุมนี้ได้");
}

// 2. ตรวจสอบการส่งค่าไฟล์
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header("HTTP/1.1 400 Bad Request");
    showModernAlert("Bad Request", "ไม่พบข้อมูลพาร์ทไฟล์ที่ต้องการดาวน์โหลด กรุณาตรวจสอบลิงก์อีกครั้ง");
}

$base_dir = realpath(__DIR__ . '/../../'); 
$upload_dir = realpath($base_dir . '/public/uploads');
$requested_file = str_replace('\\', '/', (string)$_GET['file']);

if ($upload_dir === false || strpos($requested_file, "\0") !== false) {
    header("HTTP/1.1 404 Not Found");
    showModernAlert("File Not Found", "ไม่พบไฟล์เอกสารนี้ในระบบจัดเก็บข้อมูลของเซิร์ฟเวอร์ อาจถูกลบหรือย้ายโฟลเดอร์");
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$role_id = (int)($_SESSION['role_id'] ?? 0);
$db = (new Database())->connect();

if ($role_id === 1) {
    $access_stmt = $db->prepare(
        'SELECT 1 FROM documents WHERE file_path = :file
         UNION ALL
         SELECT 1 FROM agenda_documents WHERE file_path = :file
         LIMIT 1'
    );
    $access_stmt->execute([':file' => $requested_file]);
} else {
    $access_stmt = $db->prepare(
        'SELECT 1
         FROM documents d
         INNER JOIN meeting_attendance ma ON ma.meeting_id = d.meeting_id
         WHERE d.file_path = :file AND ma.user_id = :user_id
         UNION ALL
         SELECT 1
         FROM agenda_documents ad
         INNER JOIN agenda a ON a.agenda_id = ad.agenda_id
         INNER JOIN meeting_attendance ma ON ma.meeting_id = a.meeting_id
         WHERE ad.file_path = :file AND ma.user_id = :user_id
         LIMIT 1'
    );
    $access_stmt->execute([
        ':file' => $requested_file,
        ':user_id' => $user_id,
    ]);
}

if (!$access_stmt->fetchColumn()) {
    header("HTTP/1.1 403 Forbidden");
    showModernAlert("Access Denied", "คุณไม่มีสิทธิ์ดาวน์โหลดเอกสารนี้");
}

$file_path = realpath($base_dir . '/' . ltrim($requested_file, '/'));
$upload_prefix = rtrim($upload_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// 3. ส่งไฟล์ให้ดาวน์โหลดถ้าตรวจสอบผ่าน
if ($file_path !== false && strncmp($file_path, $upload_prefix, strlen($upload_prefix)) === 0 && is_file($file_path)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    
    readfile($file_path);
    exit;
} else {
    header("HTTP/1.1 404 Not Found");
    showModernAlert("File Not Found", "ไม่พบไฟล์เอกสารนี้ในระบบจัดเก็บข้อมูลของเซิร์ฟเวอร์ อาจถูกลบหรือย้ายโฟลเดอร์");
}