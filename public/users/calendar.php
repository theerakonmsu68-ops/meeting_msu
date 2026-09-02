<?php
/* ===============================
🔐 AUTH & SECURITY
=============================== */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(2);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/Meeting.php';

$db = (new Database())->connect();

$page_title = "ปฏิทินตารางการประชุม";

$page_css = "user-calendar.css";

$page_js = "user-calendar.js";

include_once __DIR__ . '/../../app/views/layouts/header.php';
$current_page = 'calendar';
include_once __DIR__ . '/../../app/views/layouts/sidebar_user.php';
?>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet" />



<div class="main-content" id="mainContent">
    <header class="header"
        style="display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; background: #fff; border-bottom: 1px solid #e2e8f0;">
        <div class="header-left" style="display: flex; align-items: center; gap: 12px;">
            <button class="toggle-btn" id="toggle-sidebar" style="background: none; border: none; cursor: pointer;">
                <i data-lucide="menu"></i>
            </button>
            <div>
                <h2
                    style="margin: 0; font-size: 20px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="calendar" style="color: #2563eb; width: 22px; height: 22px;"></i>
                    <span>ปฏิทินตารางการประชุม</span>
                </h2>
                <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b; padding-left: 30px;">
                    แสดงกำหนดการนัดหมายประชุมคณะทั้งหมดในรูปแบบปฏิทิน</p>
            </div>
        </div>
    </header>

    <div class="content-wrapper">
        <div class="calendar-card">
            <div id="calendar"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales/th.global.min.js"></script>



<?php include_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>