<?php
/* ===============================
🔐 AUTH & SECURITY
=============================== */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/date_helper.php';

$db = (new Database())->connect();

// กำหนดค่าเริ่มต้นของตัวแปร
$totalUsers = 0;
$pendingUsers = 0;
$meetingCount = 0;
$recentMeetings = [];
$sqlErrorMsg = null;

try {
    // 🚀 OPTIMIZATION: รวบ Query การนับจำนวนทั้งหมดเหลือเพียง 1 ครั้ง (ลดภาระฐานข้อมูล)
    $statsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM `user`) AS total_users,
            (SELECT COUNT(*) FROM `user` WHERE `status` = 'pending') AS pending_users,
            (SELECT COUNT(*) FROM `meeting`) AS total_meetings
    ";
    $statsStmt = $db->query($statsQuery);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        $totalUsers = (int)$stats['total_users'];
        $pendingUsers = (int)$stats['pending_users'];
        $meetingCount = (int)$stats['total_meetings'];
    }

    // ดึงรายการประชุมล่าสุด 5 รายการ
    $stmt = $db->query("SELECT * FROM `meeting` ORDER BY `meeting_id` DESC LIMIT 5");
    $recentMeetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // เก็บ Error Message อย่างปลอดภัย ไม่หลุดโครงสร้าง Query ออกไปภายนอก
    $sqlErrorMsg = "ระบบฐานข้อมูลขัดข้อง กรุณาลองใหม่อีกครั้งในภายหลัง";
    // หรือหากต้องการ Debug ในช่วงพัฒนา สามารถใช้คอมเมนต์เปิดด้านล่างได้ครับ:
    // $sqlErrorMsg = $e->getMessage();
}


$adminHeaderAvatar = '';
$adminHeaderFallback = strtoupper(function_exists('mb_substr')
    ? mb_substr((string)($_SESSION['name'] ?? 'U'), 0, 2, 'UTF-8')
    : substr((string)($_SESSION['name'] ?? 'U'), 0, 2));

try {
    $avatarStmt = $db->prepare("SELECT picture FROM user WHERE user_id = ? LIMIT 1");
    $avatarStmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $picture = trim((string)$avatarStmt->fetchColumn());

    if ($picture !== '') {
        if (filter_var($picture, FILTER_VALIDATE_URL)) {
            $adminHeaderAvatar = $picture;
        } else {
            $file = basename(str_replace('\\', '/', $picture));
            if ($file !== '') {
                $adminHeaderAvatar = rtrim((string)BASE_URL, '/') . '/uploads/avatars/' . rawurlencode($file);
            }
        }
    }
} catch (Throwable $e) {
}

$page_title = "Dashboard - Admin";
$page_css = "admin-dashboard.css";
$page_js ="chart.umd.min.js";
$page_js ="admin-dashboard.js";
$use_chart = true;
include_once __DIR__ . '/../../app/views/layouts/header.php';

// ส่งสถานะเพจเพื่อไปทำ Active Menu ใน Sidebar
$current_page = 'dashboard';
include_once __DIR__ . '/../../app/views/layouts/sidebar_admin.php';
?>



<div class="main-content" id="mainContent">
    <header class="header">
        <div class="header-left">
            <button class="toggle-btn" id="toggle-sidebar" type="button"><i data-lucide="menu"></i></button>
            <h2 style="font-size: 1.2rem;">ภาพรวมระบบ</h2>
        </div>

        <div class="header-right" style="display: flex; align-items: center;">
            <?php if ($adminHeaderAvatar !== ''): ?>
            <img src="<?= htmlspecialchars($adminHeaderAvatar, ENT_QUOTES, 'UTF-8') ?>" class="avatar-img" alt="profile"
                referrerpolicy="no-referrer"
                onerror="this.style.display='none'; const fb=document.getElementById('avatar-fallback'); if(fb) fb.style.display='flex';">
            <div class="avatar" id="avatar-fallback" style="display:none;">
                <?= htmlspecialchars($adminHeaderFallback, ENT_QUOTES, 'UTF-8') ?></div>
            <?php else: ?>
            <div class="avatar" id="avatar-fallback"><?= htmlspecialchars($adminHeaderFallback, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="content-wrapper">
        <div class="stats-grid">
            <div class="card card-users" onclick="location.href='../admin/users/edit_users.php'"
                style="cursor: pointer;">
                <div class="card-icon"><i data-lucide="users"></i></div>
                <div class="card-title">ผู้ใช้งานในระบบทั้งหมด</div>
                <div class="card-value"><?= number_format($totalUsers) ?></div>
                <div class="card-desc"><i data-lucide="shield-check"></i> บัญชีผู้ใช้ในระบบปัจจุบัน</div>
            </div>

            <div class="card card-pending" onclick="location.href='../admin/users/edit_users.php'"
                style="cursor: pointer;">
                <div class="card-icon"><i data-lucide="user-plus"></i></div>
                <div class="card-title">ผู้ใช้งานที่รอการอนุมัติ</div>
                <div class="card-value"><?= number_format($pendingUsers) ?></div>
                <div class="card-desc"><i data-lucide="bell"></i> มีข้อมูลใหม่รอการตรวจสอบ</div>
            </div>

            <div class="card card-meetings" style="cursor: default;">
                <div class="card-icon"><i data-lucide="calendar"></i></div>
                <div class="card-title">รายการประชุมทั้งหมด</div>
                <div class="card-value"><?= number_format($meetingCount) ?></div>
                <div class="card-desc"><i data-lucide="layers"></i> นัดหมายที่มีการบันทึกไว้</div>
            </div>
        </div>
        <div class="dashboard-chart-grid">


            <div class="chart-card">

                <h3>
                    <i data-lucide="bar-chart-3"></i>
                    จำนวนการประชุมรายเดือน
                </h3>

                <div class="chart-wrapper">

                    <canvas id="meetingMonthChart"></canvas>

                </div>

            </div>



            <div class="chart-card">

                <h3>
                    <i data-lucide="pie-chart"></i>
                    สถานะการประชุม
                </h3>

                <div class="chart-wrapper">

                    <canvas id="meetingStatusChart"></canvas>

                </div>

            </div>


        </div>

        <h3 class="section-title"><i data-lucide="clock"></i> รายการประชุมล่าสุดในระบบ</h3>

        <?php if ($sqlErrorMsg): ?>
        <div class="error-state">
            <i data-lucide="alert-triangle"></i>
            <div>
                <b>ข้อความระบบ:</b> <?= htmlspecialchars($sqlErrorMsg, ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>หัวข้อการประชุม</th>
                            <th>วันที่ประชุม</th>
                            <th>เวลา</th>
                            <th>ห้องประชุม/สถานที่</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentMeetings)): ?>
                        <?php foreach ($recentMeetings as $meeting): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($meeting['meeting_title'] ?? 'ไม่ระบุหัวข้อ', ENT_QUOTES, 'UTF-8') ?></b>
                            </td>
                            <td><?= htmlspecialchars(thai_date($meeting['meeting_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td><?= htmlspecialchars($meeting['meeting_time'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($meeting['meeting_location'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php
                                        $status = $meeting['meeting_status'] ?? 'upcoming';
                                        if ($status === 'ongoing' || $status === 'active') {
                                            echo '<span class="status-badge status-ongoing">กำลังประชุม</span>';
                                        } elseif ($status === 'upcoming' || $status === 'pending') {
                                            echo '<span class="status-badge status-upcoming">เร็ว ๆ นี้</span>';
                                        } elseif ($status === 'closed' || $status === 'completed') {
                                            echo '<span class="status-badge status-completed">สิ้นสุดแล้ว</span>';
                                        } elseif ($status === 'cancelled') {
                                            echo '<span class="status-badge status-cancelled">ยกเลิก</span>';
                                        } else {
                                            echo '<span class="status-badge status-upcoming">เร็ว ๆ นี้</span>';
                                        }
                                        ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <?php if (!$sqlErrorMsg): ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                <i data-lucide="folder-open"></i>
                                <div>ยังไม่มีรายการนัดหมายประชุมล่าสุดในระบบขณะนี้</div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>



<?php
include_once __DIR__ . '/../../app/views/components/profile_modal.php';
include_once __DIR__ . '/../../app/views/layouts/footer.php';
?>