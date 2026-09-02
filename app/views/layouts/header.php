<?php
// 🌐 ดึงไฟล์ตั้งค่าหลักด้วยพาร์ทระบบราก (แก้ปัญหาลิงก์หลุดเมื่อถูก include ไปหน้าลึกๆ)
require_once __DIR__ . '/../components/ai.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
$csrfToken = AuthMiddleware::csrfToken();

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <title><?= $page_title ?? 'ระบบงานประชุม'; ?> | <?= APP_NAME ?></title>

    <?php
    $styleCssAssetPath = __DIR__ . '../../../public/assets/css/style.css';
    $styleCssAssetVersion = is_file($styleCssAssetPath) ? '?v=' . filemtime($styleCssAssetPath) : '';
    ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css<?= $styleCssAssetVersion ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modal-ed-profile.css">

    <?php
    $current_page = $page_title ?? '';

    if ($current_page === "Dashboard - Admin"): ?>

        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_ad.css">

    <?php elseif ($current_page === "Dashboard - User"): ?>

        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_user.css">

    <?php endif; ?>


    <?php if (isset($page_css) && $page_css !== ''): ?>

        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/<?= $page_css ?>">

    <?php endif; ?>


    <link rel="icon" href="<?= BASE_URL ?>assets/image/logo.svg">

    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <script src="<?= BASE_URL ?>assets/js/lucide.min.js"></script>

</head>
<?php
require_once __DIR__ . '/../components/profile_modal.php';
?>

<body>