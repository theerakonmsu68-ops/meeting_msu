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
if ($page < 1) $page = 1;
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

$page_title = "ประวัติการประชุม";

$page_css = "user-meeting.css";

$page_js = "user-meeting.js";

include_once __DIR__ . '/../../app/views/layouts/header.php';
$current_page = 'history';
include_once __DIR__ . '/../../app/views/layouts/sidebar_user.php';
?>



<div class="main-content" id="mainContent">
    <header class="header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; background: #fff; border-bottom: 1px solid #e2e8f0;">
        <div class="header-left" style="display: flex; align-items: center; gap: 12px;">
            <button class="toggle-btn" id="toggle-sidebar" style="background: none; border: none; cursor: pointer;"><i data-lucide="menu"></i></button>
            <div>
                <h2 style="margin: 0; font-size: 20px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="history" style="width:22px;color:#2563eb;"></i> <span>ประวัติการประชุม</span>
                </h2>
                <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b; padding-left: 30px;">ระบบจัดการประชุมคณะ</p>
            </div>
        </div>
    </header>

    <div class="content-wrapper">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 500; color: #1e293b;">รายการประวัติและตารางประชุม</h3>
            <div style="display: flex; gap: 12px;">
                <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="ค้นหาการประชุม..." style="padding: 8px 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; width: 240px; font-weight: 400;">
            </div>
        </div>

        <div class="filter-tabs">
            <button class="tab-btn active" onclick="filterStatus('all', this)">ทั้งหมด</button>
            <button class="tab-btn" onclick="filterStatus('upcoming', this)">ยังไม่เริ่ม</button>
            <button class="tab-btn" onclick="filterStatus('ongoing', this)">กำลังประชุม</button>
            <button class="tab-btn" onclick="filterStatus('closed', this)">จบประชุมแล้ว</button>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ชื่อการประชุม</th>
                        <th>วันที่ประชุม</th>
                        <th>เวลา</th>
                        <th>ผู้สร้าง</th>
                        <th>วาระ</th>
                        <th>สถานะ</th>
                        <th style="text-align: center;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($meetings) > 0): ?>
                        <?php foreach ($meetings as $m): 
                            $status = $m['meeting_status'] ?? 'upcoming';
                            $status_text = 'ยังไม่เริ่ม';
                            if ($status === 'ongoing') $status_text = 'กำลังประชุม';
                            if ($status === 'closed') $status_text = 'จบประชุมแล้ว';
                            $can_download = (!empty($m['file_path']) && ($status === 'ongoing' || $status === 'closed'));
                        ?>
                            <tr data-status="<?= $status ?>">
                                <td><?= htmlspecialchars($m['meeting_title']) ?></td>
                                <td><?= date('d/m/Y', strtotime($m['meeting_date'])) ?></td>
                                <td><?= date('H:i', strtotime($m['meeting_time'])) ?> น.</td>
                                <td><?= htmlspecialchars($m['creator_name'] ?? 'ไม่ระบุ') ?></td>
                                <td>
                                    <span style="background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 400; color: #475569;">
                                        <?= $m['agenda_count'] ?> วาระ
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 13px; font-weight: 400; color: <?= $status === 'ongoing' ? '#22c55e' : ($status === 'closed' ? '#64748b' : '#0284c7') ?>;">
                                        <?php if($status === 'ongoing'): ?><span class="pulse-dot"></span><?php endif; ?>
                                        <?= $status_text ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <button class="btn-table-agenda" onclick="viewAgenda(<?= $m['meeting_id'] ?>)">
                                        <i data-lucide="eye" style="width: 14px; height: 14px;"></i> ดูวาระ
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #94a3b8; padding: 40px;">ไม่มีข้อมูลรายการประชุม</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <a href="?page=<?= max(1, $page - 1) ?>" class="pagination-link <?= ($page <= 1) ? 'disabled' : '' ?>">&laquo; ก่อนหน้า</a>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="pagination-link <?= ($page == $i) ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?page=<?= min($total_pages, $page + 1) ?>" class="pagination-link <?= ($page >= $total_pages) ? 'disabled' : '' ?>">ถัดไป &raquo;</a>
            </div>
        <?php endif; ?>

    </div>
</div>

<div class="modal" id="agendaModal">
    <div class="modal-box">
        <div class="modal-header-container">
            <h3 class="modal-title-with-icon">
                <i data-lucide="layers" style="color: #2563eb; width: 18px; height: 18px;"></i> 
                <span>รายละเอียดและวาระการประชุม</span>
            </h3>
            <button class="modal-close-icon-btn" onclick="closeAgenda()" title="ปิดหน้าต่าง">
                <i data-lucide="x" style="width: 16px; height: 16px;"></i>
            </button>
        </div>
        
        <div class="agenda-view-box">
            <div id="attendanceStatusZone" style="background: #f8fafc; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2e8f0; font-size: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 500; color: #334155;">บทบาทในการประชุมนี้:</span>
                    <span id="user_meeting_role" style="font-weight: 600; color: #2563eb; background: rgba(37, 99, 235, 0.08); padding: 2px 10px; border-radius: 20px; font-size: 12.5px;">กำลังโหลด...</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #e2e8f0;">
                    <span style="font-weight: 500; color: #334155;">สถานะการเข้าประชุมปัจจุบัน:</span>
                    <span id="user_checkin_status" style="font-weight: 500;">กำลังโหลด...</span>
                </div>
            </div>

            <div id="agendaList"></div>
        </div>
        
        <div class="modal-actions">
            <button onclick="closeAgenda()">ปิดหน้าต่าง</button>
        </div>
    </div>
</div>



<?php include_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>