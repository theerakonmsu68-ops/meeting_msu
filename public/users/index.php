<?php
/* ===============================
🔐 AUTH & SECURITY
=============================== */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(2);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/Meeting.php';
require_once __DIR__ . '/../../app/controllers/MeetingController.php';

$db = (new Database())->connect();

$limit = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

$count_query = "SELECT COUNT(*) FROM meeting";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$query = "SELECT m.*, u.name AS creator_name,
          (SELECT d.file_path
             FROM documents d
            WHERE d.meeting_id = m.meeting_id
            ORDER BY d.document_id DESC
            LIMIT 1) AS file_path,
          (SELECT COUNT(*) FROM agenda a WHERE a.meeting_id = m.meeting_id) AS agenda_count
          FROM meeting m
          LEFT JOIN user u ON m.user_id = u.user_id
          ORDER BY m.meeting_date DESC, m.meeting_time DESC
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$all_stmt = $db->prepare("SELECT * FROM meeting");
$all_stmt->execute();
$all_meetings = $all_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_meetings = count($all_meetings);
$today_meetings = [];
$pending_meetings = [];
$finished_meetings = [];
$ongoing_meetings = [];
$current_date_check = date('Y-m-d');

foreach ($all_meetings as $m) {
    $status = $m['meeting_status'] ?? 'upcoming';
    if (($m['meeting_date'] ?? '') === $current_date_check) {
        $today_meetings[] = $m;
    }
    if ($status === 'ongoing') {
        $ongoing_meetings[] = $m;
    }
    if ($status === 'upcoming') {
        $pending_meetings[] = $m;
    }
    if ($status === 'closed') {
        $finished_meetings[] = $m;
    }
}

$noti_stmt = $db->prepare("SELECT meeting_title, created_at FROM meeting ORDER BY created_at DESC LIMIT 3");
$noti_stmt->execute();
$notifications = $noti_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "ศูนย์งานประชุมผู้ใช้ทั่วไป";

$page_css = "user-dashboard.css";

$page_js = "user-dashboard.js";

include_once __DIR__ . '/../../app/views/layouts/header.php';
$current_page = 'dashboard';
include_once __DIR__ . '/../../app/views/layouts/sidebar_user.php';
?>

<style>

</style>

<div class="main-content" id="mainContent">
    <header class="header"
        style="display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; background: #fff; border-bottom: 1px solid #e2e8f0;">
        <div class="header-left" style="display: flex; align-items: center; gap: 12px;">
            <button class="toggle-btn" id="toggle-sidebar" style="background: none; border: none; cursor: pointer;"><i
                    data-lucide="menu"></i></button>
            <div>
                <h2
                    style="margin: 0; font-size: 20px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="layout-dashboard" style="color: #2563eb; width: 22px; height: 22px;"></i>
                    <span>แผงควบคุมผู้ใช้งานทั่วไป</span>
                </h2>
                <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b; padding-left: 30px;">ยินดีต้อนรับคุณ,
                    <?= htmlspecialchars($_SESSION['name'] ?? 'ผู้ใช้งาน') ?></p>
            </div>
        </div>
    </header>

    <div class="content-wrapper">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(37, 99, 235, 0.08); color: #2563eb;"><i
                        data-lucide="folder"></i></div>
                <div class="stat-info">
                    <h3><?= $total_meetings ?></h3>
                    <p>การประชุมทั้งหมด</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(34, 197, 94, 0.08); color: #22c55e;"><i
                        data-lucide="calendar-days"></i></div>
                <div class="stat-info">
                    <h3><?= count($today_meetings) ?></h3>
                    <p>การประชุมวันนี้</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.08); color: #ef4444;"><i
                        data-lucide="activity"></i></div>
                <div class="stat-info">
                    <h3><?= count($ongoing_meetings) ?></h3>
                    <p>กำลังดำเนินการอยู่</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(100, 116, 139, 0.08); color: #64748b;"><i
                        data-lucide="check-circle"></i></div>
                <div class="stat-info">
                    <h3><?= count($finished_meetings) ?></h3>
                    <p>เสร็จสิ้นแล้ว</p>
                </div>
            </div>
        </div>

        <div class="dashboard-layout">
            <div class="card">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #1e293b;">รายการตารางการประชุมล่าสุด
                    </h3>
                    <a href="meeting_history.php"
                        style="font-size: 13px; color: #2563eb; text-decoration: none; font-weight: 500;">ดูทั้งหมด
                        &raquo;</a>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อการประชุม</th>
                                <th>วันที่ / เวลา</th>
                                <th>สถานะ</th>
                                <th style="text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($meetings) > 0): ?>
                                <?php foreach (array_slice($meetings, 0, 5) as $m):
                                    $status = $m['meeting_status'] ?? 'upcoming';
                                    $status_text = 'ยังไม่เริ่ม';
                                    if ($status === 'ongoing')
                                        $status_text = 'กำลังประชุม';
                                    if ($status === 'closed')
                                        $status_text = 'จบประชุมแล้ว';
                                    $can_download = (!empty($m['file_path']) && ($status === 'ongoing' || $status === 'closed'));
                                    ?>
                                    <tr>
                                        <td style="font-weight: 500; color: #1e293b; white-space: normal; min-width: 200px;">
                                            <?= htmlspecialchars($m['meeting_title']) ?>
                                        </td>
                                        <td>
                                            <div style="font-size: 13px; color: #334155;">
                                                <?= date('d/m/Y', strtotime($m['meeting_date'])) ?></div>
                                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                                <?= date('H:i', strtotime($m['meeting_time'])) ?> น.</div>
                                        </td>
                                        <td>
                                            <span
                                                style="font-size: 13px; font-weight: 400; color: <?= $status === 'ongoing' ? '#22c55e' : ($status === 'closed' ? '#64748b' : '#0284c7') ?>;">
                                                <?php if ($status === 'ongoing'): ?><span
                                                        class="pulse-dot"></span><?php endif; ?>
                                                <?= $status_text ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <button class="btn-action-view" onclick="viewAgenda(<?= $m['meeting_id'] ?>)">
                                                <i data-lucide="eye" style="width: 13px; height: 13px;"></i> ดูวาระ
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #94a3b8; padding: 30px;">
                                        ไม่มีรายการประชุมในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h3
                    style="margin: 0 0 16px 0; font-size: 15px; font-weight: 600; color: #1e293b; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i data-lucide="bell" style="color: #f59e0b; width: 18px; height: 18px;"></i> แจ้งเตือนประชุมใหม่
                </h3>
                <div style="display: flex; flex-direction: column;">
                    <?php if (count($notifications) > 0): ?>
                        <?php foreach ($notifications as $noti): ?>
                            <div class="notification-item">
                                <div
                                    style="background: rgba(245, 158, 11, 0.08); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #f59e0b;">
                                    <i data-lucide="megaphone" style="width: 14px; height: 14px;"></i>
                                </div>
                                <div style="flex: 1;">
                                    <p
                                        style="margin: 0; font-size: 13px; font-weight: 500; color: #334155; line-height: 1.4; white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        มีการเพิ่มหัวข้อประชุม: <?= htmlspecialchars($noti['meeting_title']) ?>
                                    </p>
                                    <span
                                        style="font-size: 11px; color: #94a3b8; display: block; margin-top: 4px;"><?= date('d/m/Y H:i', strtotime($noti['created_at'])) ?>
                                        น.</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; color: #94a3b8; padding: 20px 0; font-size: 13px;">
                            ไม่มีการแจ้งเตือนใหม่</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="agendaModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3
                style="margin: 0; font-size: 16px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="layers" style="color: #2563eb; width: 18px; height: 18px;"></i> รายละเอียดวาระการประชุม
            </h3>
            <button onclick="closeAgenda()"
                style="background: #f1f5f9; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center;">
                <i data-lucide="x" style="width: 15px; height: 15px;"></i>
            </button>
        </div>
        <div class="agenda-body">
            <div
                style="background: #f8fafc; padding: 12px 16px; border-radius: 12px; margin-bottom: 18px; border: 1px solid #e2e8f0; font-size: 13.5px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 500; color: #475569;">บทบาทในการประชุมนี้:</span>
                    <span id="user_meeting_role"
                        style="font-weight: 600; color: #2563eb; background: rgba(37, 99, 235, 0.08); padding: 2px 10px; border-radius: 20px; font-size: 12px;">กำลังโหลด...</span>
                </div>
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #e2e8f0;">
                    <span style="font-weight: 500; color: #475569;">สถานะการเข้าประชุม:</span>
                    <span id="user_checkin_status" style="font-weight: 500;">กำลังโหลด...</span>
                </div>
            </div>

            <div id="agendaList"></div>
        </div>
        <div class="modal-footer">
            <button onclick="closeAgenda()">ปิดหน้าต่าง</button>
        </div>
    </div>
</div>



<?php include_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>