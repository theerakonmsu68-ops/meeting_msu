<?php
/* ============================================================
 * Department calendar — แสดงเฉพาะการประชุมที่ผู้ใช้ได้รับเชิญ
 * ============================================================ */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(4);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/view_helper.php';
require_once __DIR__ . '/../../app/helpers/status_helper.php';

$db = (new Database())->connect();
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    exit('ไม่พบข้อมูลผู้ใช้งาน');
}

function invitationStatusText(string $rsvpStatus, string $attendanceStatus): string
{
    if ($attendanceStatus === 'present') {
        return 'เข้าร่วมแล้ว';
    }
    if ($attendanceStatus === 'representative') {
        return 'ส่งผู้แทน';
    }
    if ($attendanceStatus === 'absent' || $rsvpStatus === 'declined') {
        return 'ไม่เข้าร่วม';
    }
    if ($rsvpStatus === 'attending') {
        return 'ตอบรับแล้ว';
    }
    return 'รอตอบรับ';
}

$stmt = $db->prepare(
    "SELECT
        m.meeting_id,
        m.meeting_title,
        m.meeting_date,
        m.meeting_time,
        m.meeting_location,
        m.meeting_status,
        ma.rsvp_status,
        ma.attendance_status,
        ma.attendance_role
     FROM meeting_attendance ma
     INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
     WHERE ma.user_id = ?
     ORDER BY m.meeting_date ASC, m.meeting_time ASC"
);
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$events = [];
$totalInvited = count($rows);
$pendingCount = 0;
$acceptedCount = 0;
$todayCount = 0;
$today = date('Y-m-d');

foreach ($rows as $row) {
    $rsvpStatus = (string) ($row['rsvp_status'] ?? 'pending');
    $attendanceStatus = (string) ($row['attendance_status'] ?? 'pending');
    $meetingStatus = (string) ($row['meeting_status'] ?? 'upcoming');
    $statusText = invitationStatusText($rsvpStatus, $attendanceStatus);

    if ($rsvpStatus === 'pending' && $attendanceStatus === 'pending') {
        $pendingCount++;
    }
    if ($rsvpStatus === 'attending' || in_array($attendanceStatus, ['present', 'representative'], true)) {
        $acceptedCount++;
    }
    if (($row['meeting_date'] ?? '') === $today) {
        $todayCount++;
    }

    $events[] = [
        'id' => (int) $row['meeting_id'],
        'title' => (string) $row['meeting_title'],
        'start' => (string) $row['meeting_date'] . 'T' . substr((string) $row['meeting_time'], 0, 8),
        'allDay' => false,
        'classNames' => [invitationClass($rsvpStatus, $attendanceStatus, $meetingStatus)],
        'extendedProps' => [
            'meetingId' => (int) $row['meeting_id'],
            'location' => (string) ($row['meeting_location'] ?? ''),
            'invitationStatus' => $statusText,
            'meetingStatus' => $meetingStatus,
        ],
    ];
}

$stmtUpcoming = $db->prepare(
    "SELECT
        m.meeting_id,
        m.meeting_title,
        m.meeting_date,
        m.meeting_time,
        m.meeting_location,
        m.meeting_status,
        ma.rsvp_status,
        ma.attendance_status
     FROM meeting_attendance ma
     INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
     WHERE ma.user_id = ?
       AND m.meeting_status <> 'closed'
       AND TIMESTAMP(m.meeting_date, m.meeting_time) >= NOW()
     ORDER BY m.meeting_date ASC, m.meeting_time ASC
     LIMIT 5"
);
$stmtUpcoming->execute([$userId]);
$upcomingMeetings = $stmtUpcoming->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'ปฏิทินการประชุม';
$page_css = "department-calendar.css";
$page_js = "calendar.js";
include_once __DIR__ . '/../../app/views/layouts/header.php';
$current_page = 'calendar';
include_once __DIR__ . '/../../app/views/layouts/sidebar_department.php';
?>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">



<div class="main-content" id="mainContent">
    <header class="department-header">
        <div style="display:flex;align-items:center;gap:11px;min-width:0;">
            <button id="toggle-sidebar" type="button"
                style="border:0;background:transparent;cursor:pointer;color:#475569;padding:4px;"><i
                    data-lucide="menu"></i></button>
            <div>
                <h2 style="margin:0;display:flex;align-items:center;gap:8px;color:#1e293b;font-size:20px;"><i
                        data-lucide="calendar-days" style="width:22px;color:#0284c7;"></i> ปฏิทินการประชุมของฉัน</h2>
                <p style="margin:4px 0 0;color:#64748b;font-size:12.5px;">
                    แสดงเฉพาะการประชุมที่คุณได้รับเชิญจากผู้ดูแลระบบ</p>
            </div>
        </div>
        <a class="btn btn-soft" href="index.php"><i data-lucide="inbox"></i> กลับไปคำเชิญ</a>
    </header>

    <div class="content-wrapper">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i data-lucide="calendar-check"></i></div>
                <div>
                    <h3><?= $totalInvited ?></h3>
                    <p>คำเชิญทั้งหมด</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fffbeb;color:#d97706;"><i data-lucide="mail-question"></i>
                </div>
                <div>
                    <h3><?= $pendingCount ?></h3>
                    <p>รอตอบรับ</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#ecfdf5;color:#16a34a;"><i data-lucide="circle-check-big"></i>
                </div>
                <div>
                    <h3><?= $acceptedCount ?></h3>
                    <p>ตอบรับ/ส่งผู้แทน</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f5f3ff;color:#7c3aed;"><i data-lucide="calendar-clock"></i>
                </div>
                <div>
                    <h3><?= $todayCount ?></h3>
                    <p>การประชุมวันนี้</p>
                </div>
            </div>
        </div>

        <div class="legend">
            <span><i style="background:#f59e0b;"></i> รอตอบรับ</span>
            <span><i style="background:#0284c7;"></i> ตอบรับแล้ว</span>
            <span><i style="background:#16a34a;"></i> เข้าร่วมแล้ว</span>
            <span><i style="background:#9333ea;"></i> ส่งผู้แทน</span>
            <span><i style="background:#ef4444;"></i> ไม่เข้าร่วม</span>
            <span><i style="background:#64748b;"></i> ปิดประชุมแล้ว</span>
        </div>

        <div class="page-grid">
            <section class="calendar-card">
                <div id="calendar"></div>
            </section>
            <aside class="side-card">
                <h3><i data-lucide="clock-3" style="width:17px;color:#0284c7;"></i> การประชุมที่กำลังจะถึง</h3>
                <?php if ($upcomingMeetings): ?>
                    <?php foreach ($upcomingMeetings as $meeting):
                        $statusText = invitationStatusText((string) $meeting['rsvp_status'], (string) $meeting['attendance_status']);
                        $pillClass = 'pill-pending';
                        if ($meeting['attendance_status'] === 'present')
                            $pillClass = 'pill-present';
                        elseif ($meeting['attendance_status'] === 'representative')
                            $pillClass = 'pill-representative';
                        elseif ($meeting['attendance_status'] === 'absent' || $meeting['rsvp_status'] === 'declined')
                            $pillClass = 'pill-declined';
                        elseif ($meeting['rsvp_status'] === 'attending')
                            $pillClass = 'pill-attending';
                        ?>
                        <a class="meeting-item" href="index.php?open_meeting=<?= (int) $meeting['meeting_id'] ?>">
                            <strong><?= h($meeting['meeting_title']) ?></strong>
                            <span><?= date('d/m/Y', strtotime($meeting['meeting_date'])) ?> ·
                                <?= substr((string) $meeting['meeting_time'], 0, 5) ?> น.</span>
                            <span><?= h($meeting['meeting_location']) ?></span>
                            <span class="status-pill <?= h($pillClass) ?>"><?= h($statusText) ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state"><i data-lucide="calendar-x"></i><br>ยังไม่มีการประชุมที่กำลังจะถึง</div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales/th.global.min.js"></script>

<script>
    window.CalendarConfig = {
        events: <?= json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
</script>


<?php
include_once __DIR__ . '/../../app/views/layouts/footer.php';
?>