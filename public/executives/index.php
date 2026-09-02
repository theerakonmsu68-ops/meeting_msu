<?php
/* ============================================================
 * Department dashboard — invitation / RSVP / agenda documents
 * Requires migrations from meeting_upgrade_report_invitation_v3
 * ============================================================ */

require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(3);

require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/helpers/url_helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/helpers/view_helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/helpers/status_helper.php';

$db = (new Database())->connect();
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    exit('ไม่พบข้อมูลผู้ใช้งาน');
}

if (empty($_SESSION['department_meeting_csrf'])) {
    $_SESSION['department_meeting_csrf'] = bin2hex(random_bytes(32));
}
$departmentMeetingCsrfToken = $_SESSION['department_meeting_csrf'];

function meetingStatusText(string $status): string
{
    return match ($status) {
        'ongoing' => 'กำลังประชุม',
        'closed' => 'จบประชุมแล้ว',
        default => 'ยังไม่เริ่ม',
    };
}

function rsvpStatusText(string $rsvpStatus, string $attendanceStatus): string
{
    if ($attendanceStatus === 'present') {
        return 'เข้าร่วมแล้ว';
    }
    if ($attendanceStatus === 'representative') {
        return 'ส่งผู้แทนเข้าร่วม';
    }
    if ($attendanceStatus === 'absent' || $rsvpStatus === 'declined') {
        return 'ไม่เข้าร่วม';
    }
    if ($rsvpStatus === 'attending') {
        return 'ยืนยันเข้าร่วม';
    }
    return 'รอตอบรับ';
}

// ข้อมูลผู้ใช้งานและสังกัด
$stmtProfile = $db->prepare(
    "SELECT
        u.name,
        u.email,
        COALESCE(p.position_name, '-') AS position_name,
        COALESCE(d.department_name, '-') AS department_name
     FROM user u
     LEFT JOIN positions p ON p.position_id = u.position_id
     LEFT JOIN departments d ON d.department_id = u.department_id
     WHERE u.user_id = ?
     LIMIT 1"
);
$stmtProfile->execute([$userId]);
$profile = $stmtProfile->fetch(PDO::FETCH_ASSOC) ?: [];

// สถิติคำเชิญเฉพาะผู้ใช้ปัจจุบัน
$stmtStats = $db->prepare(
    "SELECT
        COUNT(*) AS total_invited,
        SUM(CASE WHEN ma.rsvp_status = 'pending' AND ma.attendance_status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN ma.rsvp_status = 'attending' AND ma.attendance_status IN ('pending','present') THEN 1 ELSE 0 END) AS attending_count,
        SUM(CASE WHEN ma.attendance_status = 'representative' THEN 1 ELSE 0 END) AS representative_count,
        SUM(
    CASE 
        WHEN m.meeting_date = CURDATE()
        AND ma.rsvp_status = 'pending'
        AND ma.attendance_status = 'pending'
        THEN 1 
        ELSE 0 
    END
) AS today_count
     FROM meeting_attendance ma
     INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
     WHERE ma.user_id = ?"
);
$stmtStats->execute([$userId]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];

$totalInvited = (int) ($stats['total_invited'] ?? 0);
$pendingCount = (int) ($stats['pending_count'] ?? 0);
$attendingCount = (int) ($stats['attending_count'] ?? 0);
$representativeCount = (int) ($stats['representative_count'] ?? 0);
$todayCount = (int) ($stats['today_count'] ?? 0);

// ตัวกรองรายการคำเชิญ
$allowedFilters = ['all', 'pending', 'attending', 'today', 'representative'];
$filter = (string) ($_GET['filter'] ?? 'all');
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$where = ['ma.user_id = :user_id'];
if ($filter === 'pending') {
    $where[] = "ma.rsvp_status = 'pending' AND ma.attendance_status = 'pending'";
} elseif ($filter === 'attending') {
    $where[] = "ma.rsvp_status = 'attending' AND ma.attendance_status IN ('pending','present')";
} elseif ($filter === 'today') {
    $where[] = 'm.meeting_date = CURDATE()';
} elseif ($filter === 'representative') {
    $where[] = "ma.attendance_status = 'representative'";
}
$whereSql = implode(' AND ', $where);

$limit = 6;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$page = max(1, $page);

$stmtCount = $db->prepare(
    "SELECT COUNT(*)
     FROM meeting_attendance ma
     INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
     WHERE {$whereSql}"
);
$stmtCount->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmtCount->execute();
$totalRows = (int) $stmtCount->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

$stmtMeetings = $db->prepare(
    "SELECT
        m.meeting_id,
        m.meeting_title,
        m.report_header,
        m.meeting_number,
        m.meeting_date,
        m.meeting_time,
        m.meeting_location,
        m.meeting_link,
        m.meeting_status,
        ma.attendance_role,
        ma.rsvp_status,
        ma.attendance_status,
        ma.representative_name,
        ma.representative_position,
        ma.attendance_remark,
        ma.is_present,
        ma.checkin_time,
        (SELECT COUNT(*) FROM agenda a WHERE a.meeting_id = m.meeting_id) AS agenda_count,
        (SELECT COUNT(*) FROM documents d WHERE d.meeting_id = m.meeting_id) AS meeting_document_count,
        (SELECT COUNT(*)
           FROM agenda_documents ad
           INNER JOIN agenda a2 ON a2.agenda_id = ad.agenda_id
          WHERE a2.meeting_id = m.meeting_id) AS agenda_document_count
     FROM meeting_attendance ma
     INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
     WHERE {$whereSql}
     ORDER BY
        CASE m.meeting_status WHEN 'ongoing' THEN 1 WHEN 'upcoming' THEN 2 ELSE 3 END,
        m.meeting_date DESC,
        m.meeting_time DESC
     LIMIT :limit OFFSET :offset"
);
$stmtMeetings->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmtMeetings->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtMeetings->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtMeetings->execute();
$meetings = $stmtMeetings->fetchAll(PDO::FETCH_ASSOC);

// การแจ้งเตือนเฉพาะผู้ใช้
$stmtNotifications = $db->prepare(
    "SELECT notification_id, title, message, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY is_read ASC, created_at DESC
     LIMIT 5"
);
$stmtNotifications->execute([$userId]);
$notifications = $stmtNotifications->fetchAll(PDO::FETCH_ASSOC);

$stmtUnreadInvitations = $db->prepare(
    "SELECT COUNT(*)
     FROM notifications
     WHERE user_id = ?
       AND is_read = 0
       AND title = 'คำเชิญเข้าร่วมประชุม'"
);
$stmtUnreadInvitations->execute([$userId]);
$unreadInvitationCount = (int) $stmtUnreadInvitations->fetchColumn();
$pendingMeetingId = 0;

$stmtPendingMeeting = $db->prepare(
    "SELECT ma.meeting_id
     FROM meeting_attendance ma
     INNER JOIN meeting m 
        ON m.meeting_id = ma.meeting_id
     WHERE ma.user_id = ?
       AND ma.rsvp_status = 'pending'
       AND ma.attendance_status = 'pending'
     ORDER BY m.meeting_date ASC, m.meeting_time ASC
     LIMIT 1"
);

$stmtPendingMeeting->execute([$userId]);

$pendingMeetingId = (int)$stmtPendingMeeting->fetchColumn();
$openMeetingId = filter_input(INPUT_GET, 'open_meeting', FILTER_VALIDATE_INT) ?: 0;

// วาระล่าสุดจากการประชุมที่ผู้ใช้นี้ได้รับเชิญ
$stmtRecentAgendas = $db->prepare(
    "SELECT a.agenda_title, a.agenda_status, a.created_at, m.meeting_title
     FROM meeting_attendance ma
     INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
     INNER JOIN agenda a ON a.meeting_id = m.meeting_id
     WHERE ma.user_id = ?
     ORDER BY a.created_at DESC, a.order_index ASC
     LIMIT 4"
);
$stmtRecentAgendas->execute([$userId]);
$recentAgendas = $stmtRecentAgendas->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'ศูนย์งานประชุมผู้บริหาร';

$page_css = "executive-dashboard.css";

$page_js = ["sweetalert2.all.min.js", "dashboard-formatters.js", "meeting-dashboard.js"];

include_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/views/layouts/header.php';
$current_page = 'dashboard';
include_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/views/layouts/sidebar_executive.php';
?>



<div class="main-content" id="mainContent">
    <header class="department-header">
        <div style="display:flex; align-items:center; gap:12px; min-width:0;">
            <button id="toggle-sidebar" type="button"
                style="border:0;background:transparent;cursor:pointer;color:#475569;padding:4px;">
                <i data-lucide="menu"></i>
            </button>
            <div style="min-width:0;">
                <h2 style="margin:0;display:flex;align-items:center;gap:8px;color:#1e293b;font-size:20px;">
                    <i data-lucide="building-2" style="width:22px;color:#7c3aed;"></i>
                    ศูนย์งานประชุมผู้บริหาร
                </h2>
                <p style="margin:4px 0 0;color:#64748b;font-size:12.5px;">
                    ตอบรับคำเชิญ ตรวจสอบวาระ และดาวน์โหลดเอกสารประกอบการประชุม
                </p>
            </div>
        </div>

        <div class="quick-nav">
            <a href="calendar.php" class="btn btn-soft"><i data-lucide="calendar-days"></i> ปฏิทินของฉัน</a>
            <a href="meeting_history.php" class="btn btn-soft"><i data-lucide="history"></i> ประวัติการประชุม</a>
        </div>

        <div class="department-user-card">
            <div
                style="width:34px;height:34px;border-radius:10px;background:#f0f9ff;color:#7c3aed;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="user-round" style="width:17px;"></i>
            </div>
            <div>
                <strong><?= h($profile['name'] ?? ($_SESSION['name'] ?? 'ผู้ใช้งานภาควิชา')) ?></strong>
                <span><?= h($profile['position_name'] ?? '-') ?> · <?= h($profile['department_name'] ?? '-') ?></span>
            </div>
        </div>
    </header>

    <div class="content-wrapper">
        <?php if ($unreadInvitationCount > 0): ?>
        <div class="invitation-alert">
            <div class="invitation-alert-main">
                <div class="invitation-alert-icon"><i data-lucide="mail-check"></i></div>
                <div>
                    <strong>คุณมีคำเชิญเข้าร่วมประชุมใหม่ <?= $unreadInvitationCount ?> รายการ</strong>
                    <span>เปิดรายละเอียดการประชุมเพื่อตรวจสอบวาระ เอกสาร และตอบรับคำเชิญ</span>
                </div>
            </div>
            <button type="button" class="btn btn-primary" onclick="openMeetingDetail(<?= $pendingMeetingId ?>)">
                <i data-lucide="inbox"></i> ดูคำเชิญ
            </button>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <a class="stat-card <?= $filter === 'all' ? 'active-filter' : '' ?>" href="?filter=all">
                <div class="stat-icon" style="background:#eff6ff;color:#2563eb;"><i data-lucide="mail"></i></div>
                <div class="stat-info">
                    <h3><?= $totalInvited ?></h3>
                    <p>คำเชิญทั้งหมด</p>
                </div>
            </a>
            <a class="stat-card <?= $filter === 'pending' ? 'active-filter' : '' ?>" href="?filter=pending">
                <div class="stat-icon" style="background:#fffbeb;color:#d97706;"><i data-lucide="mail-question"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $pendingCount ?></h3>
                    <p>รอตอบรับ</p>
                </div>
            </a>
            <a class="stat-card <?= $filter === 'attending' ? 'active-filter' : '' ?>" href="?filter=attending">
                <div class="stat-icon" style="background:#f0fdf4;color:#16a34a;"><i data-lucide="circle-check-big"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $attendingCount ?></h3>
                    <p>ยืนยันเข้าร่วม</p>
                </div>
            </a>
            <a class="stat-card <?= $filter === 'representative' ? 'active-filter' : '' ?>"
                href="?filter=representative">
                <div class="stat-icon" style="background:#faf5ff;color:#9333ea;"><i data-lucide="users-round"></i></div>
                <div class="stat-info">
                    <h3><?= $representativeCount ?></h3>
                    <p>ส่งผู้แทนเข้าร่วม</p>
                </div>
            </a>
        </div>

        <?php if ($todayCount > 0): ?>
        <div
            style="margin:-10px 0 20px;padding:11px 14px;border:1px solid #bbf7d0;background:#f0fdf4;border-radius:12px;color:#166534;font-size:12.5px;display:flex;align-items:center;gap:7px;">
            <i data-lucide="calendar-check" style="width:16px;"></i>
            วันนี้คุณมีการประชุมที่ได้รับเชิญจำนวน <strong><?= $todayCount ?></strong> รายการ
            <a href="?filter=today"
                style="margin-left:auto;color:#15803d;font-weight:700;text-decoration:none;">ดูรายการวันนี้</a>
        </div>
        <?php endif; ?>

        <div class="dashboard-layout">
            <main>
                <section class="card">
                    <div class="card-title-row">
                        <div>
                            <h3>รายการประชุมที่ได้รับเชิญ</h3>
                            <div class="small-note" style="margin-top:4px;">
                                แสดงเฉพาะการประชุมที่บัญชีของคุณมีรายชื่ออยู่ในคำเชิญ</div>
                        </div>
                        <a href="calendar.php"
                            style="font-size:12px;color:#7c3aed;text-decoration:none;font-weight:700;">ดูปฏิทินทั้งหมด</a>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>การประชุม</th>
                                    <th>วันและเวลา</th>
                                    <th>สถานะประชุม</th>
                                    <th>การตอบรับ</th>
                                    <th>เอกสาร</th>
                                    <th style="text-align:center;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($meetings): ?>
                                <?php foreach ($meetings as $meeting):
                                        $meetingStatus = (string) ($meeting['meeting_status'] ?? 'upcoming');
                                        $rsvpStatus = (string) ($meeting['rsvp_status'] ?? 'pending');
                                        $attendanceStatus = (string) ($meeting['attendance_status'] ?? 'pending');
                                        $meetingDocCount = (int) ($meeting['meeting_document_count'] ?? 0);
                                        $agendaDocCount = (int) ($meeting['agenda_document_count'] ?? 0);
                                        $totalDocCount = $meetingDocCount + $agendaDocCount;
                                        $onlineLink = safeHttpUrl($meeting['meeting_link'] ?? '');
                                        $showOnlineLink = $onlineLink !== ''
                                            && $meetingStatus !== 'closed'
                                            && $rsvpStatus === 'attending'
                                            && $attendanceStatus !== 'absent'
                                            && $attendanceStatus !== 'representative';
                                        ?>
                                <tr>
                                    <td style="min-width:230px;">
                                        <div class="meeting-title"><?= h($meeting['meeting_title']) ?></div>
                                        <div class="meeting-meta">
                                            <?php if (!empty($meeting['meeting_number'])): ?>
                                            <span><i data-lucide="hash"></i><?= h($meeting['meeting_number']) ?></span>
                                            <?php endif; ?>
                                            <span><i
                                                    data-lucide="map-pin"></i><?= h($meeting['meeting_location']) ?></span>
                                            <span><i data-lucide="list-checks"></i><?= (int) $meeting['agenda_count'] ?>
                                                วาระ</span>
                                        </div>
                                        <?php if ($showOnlineLink): ?>
                                        <a class="online-link" href="<?= h($onlineLink) ?>" target="_blank"
                                            rel="noopener noreferrer">
                                            <i data-lucide="video"></i> เข้าร่วมประชุมออนไลน์
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <strong
                                            style="font-size:12.5px;color:#334155;"><?= date('d/m/Y', strtotime($meeting['meeting_date'])) ?></strong>
                                        <div style="font-size:11.5px;color:#64748b;margin-top:3px;">
                                            <?= date('H:i', strtotime($meeting['meeting_time'])) ?> น.
                                        </div>
                                    </td>
                                    <td>
                                        <span class="meeting-state <?= h($meetingStatus) ?>">
                                            <?php if ($meetingStatus === 'ongoing'): ?><span
                                                class="pulse-dot"></span><?php endif; ?>
                                            <?= h(meetingStatusText($meetingStatus)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            class="rsvp-badge <?= h(rsvpBadgeClass($rsvpStatus, $attendanceStatus)) ?>">
                                            <?= h(rsvpStatusText($rsvpStatus, $attendanceStatus)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="doc-count"><i data-lucide="paperclip"
                                                style="width:14px;"></i><?= $totalDocCount ?> ไฟล์</span>
                                    </td>
                                    <td style="text-align:center;">
                                        <button type="button" class="btn btn-primary"
                                            onclick="openMeetingDetail(<?= (int) $meeting['meeting_id'] ?>)">
                                            <i data-lucide="eye"></i> ดูรายละเอียด
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i data-lucide="inbox"></i>
                                            <strong>ไม่พบรายการประชุมในหมวดนี้</strong>
                                            <span>เมื่อผู้ดูแลระบบเชิญคุณ รายการประชุมจะแสดงที่หน้านี้</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="หน้ารายการประชุม">
                        <?php if ($page > 1): ?>
                        <a href="?filter=<?= h($filter) ?>&page=<?= $page - 1 ?>">&laquo;</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a class="<?= $page === $i ? 'active' : '' ?>"
                            href="?filter=<?= h($filter) ?>&page=<?= $i ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                        <a href="?filter=<?= h($filter) ?>&page=<?= $page + 1 ?>">&raquo;</a>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>
                </section>
            </main>

            <aside>
                <section class="card">
                    <div class="card-title-row">
                        <h3 style="display:flex;align-items:center;gap:7px;"><i data-lucide="bell"
                                style="width:17px;color:#d97706;"></i> การแจ้งเตือนของฉัน</h3>
                    </div>
                    <div style="margin-top:8px;">
                        <?php if ($notifications): ?>
                        <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?= (int) $notification['is_read'] === 0 ? 'unread' : '' ?>">
                            <div class="notification-icon"><i
                                    data-lucide="<?= (int) $notification['is_read'] === 0 ? 'mail' : 'mail-open' ?>"></i>
                            </div>
                            <div>
                                <p><strong><?= h($notification['title']) ?></strong><br><?= h($notification['message']) ?>
                                </p>
                                <time><?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?> น.</time>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="empty-state" style="padding:26px 10px;"><i
                                data-lucide="bell-off"></i><strong>ยังไม่มีการแจ้งเตือน</strong></div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="card">
                    <div class="card-title-row">
                        <h3 style="display:flex;align-items:center;gap:7px;"><i data-lucide="list-checks"
                                style="width:17px;color:#6366f1;"></i> วาระล่าสุด</h3>
                    </div>
                    <div style="margin-top:12px;">
                        <?php if ($recentAgendas): ?>
                        <?php foreach ($recentAgendas as $agenda):
                                $agendaStatus = (string) ($agenda['agenda_status'] ?? 'pending');
                                $agendaStatusText = $agendaStatus === 'closed' ? 'ปิดวาระแล้ว' : ($agendaStatus === 'discussing' ? 'กำลังอภิปราย' : 'รอดำเนินการ');
                                ?>
                        <div class="agenda-status-item">
                            <small><?= h($agenda['meeting_title']) ?></small>
                            <strong><?= h($agenda['agenda_title']) ?></strong>
                            <small><?= h($agendaStatusText) ?> ·
                                <?= date('d/m/Y', strtotime($agenda['created_at'])) ?></small>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="empty-state" style="padding:26px 10px;"><i
                                data-lucide="file-clock"></i><strong>ยังไม่มีวาระที่เกี่ยวข้อง</strong></div>
                        <?php endif; ?>
                    </div>
                </section>

               
            </aside>
        </div>
    </div>
</div>

<div class="modal" id="meetingModal" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="meetingModalTitle">
        <div class="modal-header">
            <h3 id="meetingModalTitle">รายละเอียดการประชุม</h3>
            <button type="button" class="modal-close" onclick="closeMeetingModal()" aria-label="ปิด"><i
                    data-lucide="x"></i></button>
        </div>
        <div class="modal-body" id="meetingModalBody">
            <div class="loading">กำลังโหลดข้อมูล...</div>
        </div>
        <div class="modal-footer">
            <div class="action-group" id="meetingActionGroup"></div>
            <button type="button" class="btn btn-soft" onclick="closeMeetingModal()">ปิดหน้าต่าง</button>
        </div>
    </div>
</div>

<script>
window.MeetingConfig = {
    csrfToken: <?= json_encode($departmentMeetingCsrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    initialOpenMeetingId: <?= (int) $openMeetingId ?>,
    apiUrl: '/Meeting_msu/app/controllers/department_meeting_api.php'
};
</script>

<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/views/layouts/footer.php';
?>