<?php
/* ============================================================
 * Department meeting history — เฉพาะการประชุมที่ได้รับเชิญ
 * ============================================================ */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(4);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/url_helper.php';
require_once __DIR__ . '/../../app/helpers/view_helper.php';
require_once __DIR__ . '/../../app/helpers/status_helper.php';
require_once __DIR__ . '/../../app/helpers/meeting_history_helper.php';

$db = (new Database())->connect();
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    exit('ไม่พบข้อมูลผู้ใช้งาน');
}

$allowedFilters = ['all', 'pending', 'attending', 'present', 'representative', 'declined', 'closed'];
$filter = (string) ($_GET['filter'] ?? 'all');
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$limit = 8;

$where = ['ma.user_id = :user_id'];
$params = [':user_id' => $userId];

switch ($filter) {
    case 'pending':
        $where[] = "ma.rsvp_status = 'pending' AND ma.attendance_status = 'pending'";
        break;
    case 'attending':
        $where[] = "ma.rsvp_status = 'attending' AND ma.attendance_status = 'pending'";
        break;
    case 'present':
        $where[] = "ma.attendance_status = 'present'";
        break;
    case 'representative':
        $where[] = "ma.attendance_status = 'representative'";
        break;
    case 'declined':
        $where[] = "(ma.attendance_status = 'absent' OR ma.rsvp_status = 'declined')";
        break;
    case 'closed':
        $where[] = "m.meeting_status = 'closed'";
        break;
}

if ($search !== '') {
    $where[] = '(m.meeting_title LIKE :search OR m.meeting_location LIKE :search OR m.report_header LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$stmtCount = $db->prepare(
    "SELECT COUNT(*)
     FROM meeting_attendance ma
     INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
     WHERE {$whereSql}"
);
foreach ($params as $key => $value) {
    $stmtCount->bindValue($key, $value, $key === ':user_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtCount->execute();
$totalRows = (int) $stmtCount->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

$stmt = $db->prepare(
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
     ORDER BY m.meeting_date DESC, m.meeting_time DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, $key === ':user_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtStats = $db->prepare(
    "SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN ma.rsvp_status = 'pending' AND ma.attendance_status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN ma.rsvp_status = 'attending' AND ma.attendance_status = 'pending' THEN 1 ELSE 0 END) AS attending_count,
        SUM(CASE WHEN ma.attendance_status = 'present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN ma.attendance_status = 'representative' THEN 1 ELSE 0 END) AS representative_count,
        SUM(CASE WHEN ma.attendance_status = 'absent' OR ma.rsvp_status = 'declined' THEN 1 ELSE 0 END) AS declined_count
     FROM meeting_attendance ma
     INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
     WHERE ma.user_id = ?"
);
$stmtStats->execute([$userId]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];

$page_title = 'ประวัติการประชุม';
$page_css = "department-history.css";
$page_js = "departmen-history.js";
include_once __DIR__ . '/../../app/views/layouts/header.php';
$current_page = 'history';
include_once __DIR__ . '/../../app/views/layouts/sidebar_department.php';
?>



<div class="main-content" id="mainContent">
    <header class="department-header">
        <div style="display:flex;align-items:center;gap:11px;">
            <button id="toggle-sidebar" type="button" style="border:0;background:transparent;cursor:pointer;color:#475569;padding:4px;"><i data-lucide="menu"></i></button>
            <div>
                <h2 style="margin:0;display:flex;align-items:center;gap:8px;color:#1e293b;font-size:20px;"><i data-lucide="history" style="width:22px;color:#0284c7;"></i> ประวัติการประชุมของฉัน</h2>
                <p style="margin:4px 0 0;color:#64748b;font-size:12.5px;">ตรวจสอบคำเชิญ สถานะตอบรับ เอกสาร และผลการเข้าร่วมย้อนหลัง</p>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="calendar.php" class="btn btn-soft"><i data-lucide="calendar-days"></i> ปฏิทิน</a>
            <a href="index.php" class="btn btn-soft"><i data-lucide="inbox"></i> คำเชิญ</a>
        </div>
    </header>

    <div class="content-wrapper">
        <div class="summary-grid">
            <?php
            $summaryCards = [
                ['all', (int) ($stats['total_count'] ?? 0), 'ทั้งหมด'],
                ['pending', (int) ($stats['pending_count'] ?? 0), 'รอตอบรับ'],
                ['attending', (int) ($stats['attending_count'] ?? 0), 'ตอบรับแล้ว'],
                ['present', (int) ($stats['present_count'] ?? 0), 'เข้าร่วมแล้ว'],
                ['representative', (int) ($stats['representative_count'] ?? 0), 'ส่งผู้แทน'],
                ['declined', (int) ($stats['declined_count'] ?? 0), 'ไม่เข้าร่วม'],
            ];
            foreach ($summaryCards as [$key, $count, $label]):
            ?>
                <a class="summary-card <?= $filter === $key ? 'active' : '' ?>" href="?filter=<?= h($key) ?>">
                    <strong><?= $count ?></strong><span><?= h($label) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="toolbar">
            <div class="filter-tabs">
                <?php
                $filterLabels = [
                    'all' => 'ทั้งหมด', 'pending' => 'รอตอบรับ', 'attending' => 'ตอบรับแล้ว',
                    'present' => 'เข้าร่วมแล้ว', 'representative' => 'ส่งผู้แทน',
                    'declined' => 'ไม่เข้าร่วม', 'closed' => 'ปิดประชุมแล้ว',
                ];
                foreach ($filterLabels as $key => $label):
                ?>
                    <a class="filter-tab <?= $filter === $key ? 'active' : '' ?>" href="?filter=<?= h($key) ?>&q=<?= urlencode($search) ?>"><?= h($label) ?></a>
                <?php endforeach; ?>
            </div>

            <form class="search-form" method="get">
                <input type="hidden" name="filter" value="<?= h($filter) ?>">
                <input type="search" name="q" value="<?= h($search) ?>" placeholder="ค้นหาชื่อประชุมหรือสถานที่">
                <button class="btn btn-primary" type="submit"><i data-lucide="search"></i> ค้นหา</button>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>การประชุม</th>
                        <th>วัน เวลา และสถานที่</th>
                        <th>หน้าที่</th>
                        <th>สถานะคำเชิญ</th>
                        <th>สถานะประชุม</th>
                        <th>เอกสาร</th>
                        <th>ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($meetings): ?>
                        <?php foreach ($meetings as $meeting):
                            $rsvpStatus = (string) ($meeting['rsvp_status'] ?? 'pending');
                            $attendanceStatus = (string) ($meeting['attendance_status'] ?? 'pending');
                            $onlineLink = safeHttpUrl($meeting['meeting_link'] ?? '');
                            $canJoinOnline = $onlineLink !== ''
                                && $meeting['meeting_status'] !== 'closed'
                                && $rsvpStatus === 'attending'
                                && !in_array($attendanceStatus, ['absent', 'representative'], true);
                        ?>
                            <tr>
                                <td class="meeting-title">
                                    <strong><?= h($meeting['meeting_title']) ?></strong>
                                    <span><?= h($meeting['report_header'] ?: 'การประชุมคณะ') ?><?= $meeting['meeting_number'] ? ' · ครั้งที่ ' . h($meeting['meeting_number']) : '' ?></span>
                                    <?php if ($attendanceStatus === 'representative'): ?>
                                        <div class="representative-box">ผู้แทน: <?= h($meeting['representative_name'] ?: '-') ?><?= $meeting['representative_position'] ? ' (' . h($meeting['representative_position']) . ')' : '' ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="meta-stack">
                                    <span><i data-lucide="calendar" style="width:13px;vertical-align:-2px;"></i> <?= date('d/m/Y', strtotime($meeting['meeting_date'])) ?></span>
                                    <span><i data-lucide="clock" style="width:13px;vertical-align:-2px;"></i> <?= substr((string) $meeting['meeting_time'], 0, 5) ?> น.</span>
                                    <span><i data-lucide="map-pin" style="width:13px;vertical-align:-2px;"></i> <?= h($meeting['meeting_location']) ?></span>
                                </td>
                                <td><?= h(attendanceRoleText((string) $meeting['attendance_role'])) ?></td>
                                <td><span class="badge <?= h(invitationBadgeClass($rsvpStatus, $attendanceStatus)) ?>"><?= h(invitationStatusText($rsvpStatus, $attendanceStatus)) ?></span></td>
                                <td><span class="badge meeting-status"><?= h(meetingStatusText((string) $meeting['meeting_status'])) ?></span></td>
                                <td>
                                    <div class="doc-count">
                                        <span class="doc-chip"><i data-lucide="list-checks" style="width:12px;"></i><?= (int) $meeting['agenda_count'] ?> วาระ</span>
                                        <span class="doc-chip"><i data-lucide="files" style="width:12px;"></i><?= (int) $meeting['meeting_document_count'] + (int) $meeting['agenda_document_count'] ?> ไฟล์</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                        <a class="btn btn-primary" href="index.php?open_meeting=<?= (int) $meeting['meeting_id'] ?>"><i data-lucide="eye"></i> ดูรายละเอียด</a>
                                        <?php if ($canJoinOnline): ?>
                                            <a class="btn btn-soft" href="<?= h($onlineLink) ?>" target="_blank" rel="noopener noreferrer"><i data-lucide="video"></i> เข้าประชุม</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7"><div class="empty-state"><i data-lucide="calendar-x" style="width:34px;height:34px;"></i><br><strong>ไม่พบรายการประชุม</strong><br><span>ระบบจะแสดงเฉพาะการประชุมที่คุณได้รับเชิญ</span></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="หน้ารายการประชุม">
                <?php
                $baseParams = ['filter' => $filter, 'q' => $search];
                $prevParams = http_build_query(array_merge($baseParams, ['page' => max(1, $page - 1)]));
                $nextParams = http_build_query(array_merge($baseParams, ['page' => min($totalPages, $page + 1)]));
                ?>
                <a class="page-link" href="?<?= h($prevParams) ?>">ก่อนหน้า</a>
                <?php for ($i = 1; $i <= $totalPages; $i++):
                    $pageParams = http_build_query(array_merge($baseParams, ['page' => $i]));
                ?>
                    <a class="page-link <?= $i === $page ? 'active' : '' ?>" href="?<?= h($pageParams) ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a class="page-link" href="?<?= h($nextParams) ?>">ถัดไป</a>
            </nav>
        <?php endif; ?>
    </div>
</div>



<?php
include_once __DIR__ . '/../../app/views/layouts/footer.php';
?>