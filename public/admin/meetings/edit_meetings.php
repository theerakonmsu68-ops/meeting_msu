<?php
/* ===============================
🔐 AUTH & SECURITY
=============================== */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/models/Meeting.php';
require_once __DIR__ . '/../../../app/controllers/MeetingController.php';



/* ===============================
INITIALIZE CONTROLLER & MODEL
=============================== */
$db = (new Database())->connect();
$model = new Meeting($db);
$controller = new MeetingController($model);

// 📄 [เพิ่มส่วนนี้] ระบบคำนวณ Pagination (แบ่งหน้าเมื่อมีข้อมูลถึง 9 รายการ)
$limit = 9; // กำหนดให้แสดงหน้าละ 9 รายการ
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// ดักจับกรณีที่ Controller มีฟังก์ชันรับ limit/offset หรือตัดข้อมูลด้วย PHP array slice เพื่อความยืดหยุ่น
$all_meetings = $controller->getAllMeetings();

// ==========================================
// ➕ ส่วนที่เพิ่มใหม่: รับค่าและกรองข้อมูล (Filter)
// ==========================================
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_month = isset($_GET['month']) ? $_GET['month'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

if ($filter_search !== '' || $filter_status !== '' || $filter_month !== '' || $filter_date !== '') {
    $all_meetings = array_filter($all_meetings, function ($m) use ($filter_search, $filter_status, $filter_month, $filter_date) {
        $match = true;

        // 1. กรองคำค้นหา
        if ($filter_search !== '') {
            $s = mb_strtolower($filter_search, 'UTF-8');
            $title = mb_strtolower($m['meeting_title'] ?? '', 'UTF-8');
            $loc = mb_strtolower($m['meeting_location'] ?? '', 'UTF-8');
            $creator = mb_strtolower($m['creator_name'] ?? '', 'UTF-8');

            if (mb_strpos($title, $s) === false && mb_strpos($loc, $s) === false && mb_strpos($creator, $s) === false) {
                $match = false;
            }
        }

        // 2. กรองสถานะ
        if ($filter_status !== '') {
            $st = $m['meeting_status'] ?? 'upcoming';
            if ($st !== $filter_status) $match = false;
        }

        // 3. กรองเดือน
        if ($filter_month !== '') {
            $date = $m['meeting_date'] ?? '';
            if ($date && date('m', strtotime($date)) !== $filter_month) {
                $match = false;
            } elseif (!$date) {
                $match = false;
            }
        }

        // 4. กรองวันที่เฉพาะ
        if ($filter_date !== '') {
            $date = $m['meeting_date'] ?? '';
            if ($date !== $filter_date) {
                $match = false;
            }
        }

        return $match;
    });
}

// เตรียม Query String เพื่อให้ Pagination ไม่ลืมค่าตัวกรอง
$query_string_array = [
    'search' => $filter_search,
    'status' => $filter_status,
    'month' => $filter_month,
    'date' => $filter_date
];
$filter_query_string = http_build_query(array_filter($query_string_array));
// ==========================================

$total_rows = count($all_meetings);
$total_pages = ceil($total_rows / $limit);

// ตัดแบ่งชุดข้อมูลเพื่อแสดงผลเฉพาะหน้านั้นๆ
$meetings = array_slice($all_meetings, $offset, $limit);

// จำนวนสมาชิกที่ได้รับเชิญในแต่ละการประชุม เพื่อแสดงบนปุ่มเชิญสมาชิก
$inviteCounts = [];
$meetingIdsOnPage = array_values(array_filter(array_map(
    static fn($meeting) => (int) ($meeting['meeting_id'] ?? 0),
    $meetings
)));

if ($meetingIdsOnPage) {
    $invitePlaceholders = implode(',', array_fill(0, count($meetingIdsOnPage), '?'));
    $stmtInviteCount = $db->prepare(
        "SELECT meeting_id, COUNT(*) AS invite_count
         FROM meeting_attendance
         WHERE meeting_id IN ($invitePlaceholders)
         GROUP BY meeting_id"
    );
    $stmtInviteCount->execute($meetingIdsOnPage);
    foreach ($stmtInviteCount->fetchAll(PDO::FETCH_ASSOC) as $inviteCountRow) {
        $inviteCounts[(int) $inviteCountRow['meeting_id']] = (int) $inviteCountRow['invite_count'];
    }
}
$page_title = "Dashboard - Admin";
$page_css = "meetings-management.css";

$page_js = [
    "sweetalert2.all.min.js",
    "meetings-management.js"
];

include_once __DIR__ . '/../../../app/views/layouts/header.php';

$current_page = 'meetings';
include_once __DIR__ . '/../../../app/views/layouts/sidebar_admin.php';
?>




<div class="main-content" id="mainContent">
    <header class="header">
        <div class="header-left">
            <button class="toggle-btn" id="toggle-sidebar">
                <i data-lucide="menu"></i>
            </button>
            <h2>จัดการการประชุม</h2>
        </div>
        <div class="header-right">
            <!-- คง Logic เดิมของคุณไว้ทั้งหมด -->
            <button class="btn-add" onclick="openCreate()">
                <i data-lucide="plus"></i>
                เพิ่มการประชุม
            </button>
        </div>
    </header>

    <main class="content-wrapper">

        <!-- ➕ ส่วนของ Form ตัวกรองที่เพิ่มเข้ามา -->
        <div class="filter-card">
            <h3><i data-lucide="filter" style="width: 18px; height: 18px; color: #64748b;"></i> ค้นหาและตัวกรอง</h3>

            <form method="GET" action="edit_meetings.php" class="filter-form">

                <div class="form-group search-group">
                    <label for="search">คำค้นหา</label>
                    <input type="text" id="search" name="search" class="form-control"
                        value="<?= htmlspecialchars($filter_search) ?>" placeholder="หัวข้อ, สถานที่">
                </div>

                <div class="form-group">
                    <label for="status">สถานะ</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">-- ทุกสถานะ --</option>
                        <option value="upcoming" <?= $filter_status === 'upcoming' ? 'selected' : '' ?>>🔵 เร็วๆ นี้
                        </option>
                        <option value="ongoing" <?= $filter_status === 'ongoing' ? 'selected' : '' ?>>🔴 กำลังประชุม
                        </option>
                        <option value="closed" <?= $filter_status === 'closed' ? 'selected' : '' ?>>⚫ ปิดประชุมแล้ว
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="month">เดือนที่ประชุม</label>
                    <select id="month" name="month" class="form-control">
                        <option value="">-- ทุกเดือน --</option>
                        <?php
                        $months = [
                            '01' => 'มกราคม',
                            '02' => 'กุมภาพันธ์',
                            '03' => 'มีนาคม',
                            '04' => 'เมษายน',
                            '05' => 'พฤษภาคม',
                            '06' => 'มิถุนายน',
                            '07' => 'กรกฎาคม',
                            '08' => 'สิงหาคม',
                            '09' => 'กันยายน',
                            '10' => 'ตุลาคม',
                            '11' => 'พฤศจิกายน',
                            '12' => 'ธันวาคม'
                        ];
                        foreach ($months as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $filter_month === (string)$num ? 'selected' : '' ?>><?= $name ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date">วันที่เฉพาะเจาะจง</label>
                    <input type="date" id="date" name="date" class="form-control"
                        value="<?= htmlspecialchars($filter_date) ?>">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-search">
                        <i data-lucide="search" style="width: 16px; height: 16px;"></i> ค้นหา
                    </button>
                    <!-- เปลี่ยน action clear ให้กลับมาหน้าปกติ (ไม่ต้องแนบ parameter) -->
                    <a href="edit_meetings.php" class="btn-clear">
                        <i data-lucide="rotate-ccw" style="width: 16px; height: 16px;"></i> ล้างค่า
                    </a>
                </div>
            </form>
        </div>


        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>หัวข้อ</th>
                        <th>สถานะ</th>
                        <th>วันที่</th>
                        <th>เวลา</th>
                        <th>สถานที่</th>
                        <th>ลิงก์ประชุม</th>
                        <th>เอกสารแนบ</th>
                        <th>ผู้สร้าง</th>
                        <th>วาระ</th>
                        <th>คำเชิญ</th>
                        <th>รายงานผู้เข้าร่วม</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($meetings)): ?>
                    <?php foreach ($meetings as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['meeting_title'] ?? '') ?></td>
                        <td>
                            <?php
                                    $st = $m['meeting_status'] ?? 'upcoming';
                                    if ($st === 'ongoing') {
                                        echo '<span class="status-badge status-ongoing">กำลังประชุม</span>';
                                    } elseif ($st === 'closed') {
                                        echo '<span class="status-badge status-closed">ปิดประชุมแล้ว</span>';
                                    } else {
                                        echo '<span class="status-badge status-upcoming">เร็วๆ นี้</span>';
                                    }
                                    ?>
                        </td>
                        <td><?= htmlspecialchars($m['meeting_date'] ?? '') ?></td>
                        <td><?= htmlspecialchars($m['meeting_time'] ?? '') ?></td>
                        <td><?= htmlspecialchars($m['meeting_location'] ?? '') ?></td>
                        <td>
                            <?php
                                    // 1. ดึงสถานะมาเก็บในตัวแปรก่อนเพื่อลดความซับซ้อนของโครงสร้าง PHP
                                    $check_status = isset($m['meeting_status']) ? $m['meeting_status'] : 'upcoming';
                                    $has_link = !empty($m['meeting_link']);
                                    ?>

                            <?php if ($has_link): ?>
                            <?php if ($check_status === 'ongoing'): ?>
                            <a href="<?= htmlspecialchars($m['meeting_link']) ?>" target="_blank" class="btn-table-link"
                                style="display: inline-flex; align-items: center; gap: 6px; color: #16a34a; background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 6px 12px; border-radius: 20px; font-size: 13px; text-decoration: none; transition: all 0.2s;">
                                <i data-lucide="video" style="width: 14px; height: 14px;"></i>
                                <span>เข้าประชุม</span>
                            </a>

                            <?php elseif ($check_status === 'upcoming'): ?>
                            <span class="btn-table-link-upcoming"
                                style="display: inline-flex; align-items: center; gap: 6px; color: #0284c7; background-color: #f0f9ff; border: 1px solid #bae6fd; padding: 6px 12px; border-radius: 20px; font-size: 13px; cursor: not-allowed;">
                                <i data-lucide="video" style="width: 14px; height: 14px; color: #0284c7;"></i>
                                <span>ยังไม่เริ่มประชุม</span>
                            </span>

                            <?php else: ?>
                            <span class="btn-table-link-disabled"
                                style="display: inline-flex; align-items: center; gap: 6px; color: #94a3b8; background-color: #f1f5f9; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 20px; font-size: 13px; cursor: not-allowed;">
                                <i data-lucide="video" style="width: 14px; height: 14px; color: #94a3b8;"></i>
                                <span>จบประชุมแล้ว</span>
                            </span>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-muted-dash">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="doc-list-container">
                                <?php
                                        if (!empty($m['attached_files'])) {
                                            $files = explode('||', $m['attached_files']);
                                            foreach ($files as $f) {
                                                $parts = explode('::', $f);
                                                if (count($parts) === 2) {
                                                    $doc_name = $parts[0];
                                                    $file_path = $parts[1];
                                                    echo '<a href="../../../app/controllers/download.php?file=' . urlencode($file_path) . '" target="_blank" class="btn-table-doc-item" title="' . htmlspecialchars($doc_name) . '">';
                                                    echo '<i data-lucide="file-text"></i>';
                                                    echo '<span>' . htmlspecialchars($doc_name) . '</span>';
                                                    echo '</a>';
                                                }
                                            }
                                        } else {
                                            echo '<span class="text-muted-dash">-</span>';
                                        }
                                        ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($m['creator_name'] ?? '-') ?></td>
                        <td>
                            <button class="btn-table-agenda" onclick="viewAgenda(<?= (int) $m['meeting_id'] ?>)">
                                <i data-lucide="layers"></i>
                                <span><?= (int) ($m['agenda_count'] ?? 0) ?> วาระ</span>
                            </button>
                        </td>
                        <td>
                            <?php
                                    $rowMeetingId = (int) ($m['meeting_id'] ?? 0);
                                    $rowInviteCount = $inviteCounts[$rowMeetingId] ?? 0;
                                    $rowMeetingTitleJs = json_encode(
                                        (string) ($m['meeting_title'] ?? ''),
                                        JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT
                                    );
                                    ?>
                            <button class="btn-table-invite"
                                onclick='openInvitationManager(<?= $rowMeetingId ?>, <?= $rowMeetingTitleJs ?>)'>
                                <i data-lucide="user-round-plus"></i>
                                <span>เชิญสมาชิก (<?= $rowInviteCount ?>)</span>
                            </button>
                        </td>
                        <td>
                            <button class="btn-table-report"
                                onclick="openAttendanceReport(<?= (int) $m['meeting_id'] ?>)">
                                <i data-lucide="users"></i>
                                <span>บันทึกรายงาน</span>
                            </button>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-edit"
                                    onclick="editMeeting(<?= (int) $m['meeting_id'] ?>)">แก้ไข</button>
                                <button class="btn-delete"
                                    onclick="deleteMeeting(<?= (int) $m['meeting_id'] ?>)">ลบ</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 20px;">ไม่พบข้อมูลการประชุม</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                แสดงผลรายการที่ <?= $offset + 1 ?> ถึง <?= min($offset + $limit, $total_rows) ?> จากทั้งหมด
                <?= $total_rows ?> รายการ
            </div>
            <ul class="pagination-list">
                <!-- ➕ ส่วนที่ปรับปรุง: แนบ Query String เข้าไปกับเลขหน้า เพื่อกันตัวกรองหาย -->
                <li class="pagination-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a
                        href="?page=<?= $page - 1 ?><?= !empty($filter_query_string) ? '&' . $filter_query_string : '' ?>">&laquo;
                        ก่อนหน้า</a>
                </li>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="pagination-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a
                        href="?page=<?= $i ?><?= !empty($filter_query_string) ? '&' . $filter_query_string : '' ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>

                <li class="pagination-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a
                        href="?page=<?= $page + 1 ?><?= !empty($filter_query_string) ? '&' . $filter_query_string : '' ?>">ถัดไป
                        &raquo;</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

    </main>
</div>

<div id="modal" class="modal">
    <div class="modal-box">
        <h3 id="modalTitle">เพิ่มการประชุม</h3>
        <input type="hidden" id="meeting_id">

        <div class="modal-form-body">

            <div class="status-control-container" id="statusControlBox">
                <label style="color: #b45309; margin-bottom: 4px;">⚠️ สถานะกระบวนการประชุม</label>
                <select id="meeting_status">
                    <option value="upcoming">🔵 ยังไม่เริ่มการประชุม (เร็ว ๆ นี้)</option>
                    <option value="ongoing">🔴 กำลังดำเนินการประชุม (Live)</option>
                    <option value="closed">⚫ จบและปิดการประชุมเสสิ้น (Closed)</option>
                </select>
            </div>

            <label>หัวข้อการประชุม</label>
            <input type="text" id="meeting_title" placeholder="ระบุหัวข้อประชุม...">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <label>วันที่</label>
                    <input type="date" id="meeting_date">
                </div>
                <div>
                    <label>เวลา</label>
                    <input type="time" id="meeting_time">
                </div>
            </div>

            <label>สถานที่</label>
            <input type="text" id="meeting_location" placeholder="ห้องประชุม หรือ ตึก...">

            <label>ลิงก์ห้องประชุมออนไลน์ (ถ้ามี)</label>
            <input type="url" id="meeting_link" placeholder="https://example.zoom.us/j/...">

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-top:8px;">
                <div>
                    <label>ชื่อคณะกรรมการ/หน่วยงานบนรายงาน</label>
                    <input type="text" id="report_header" placeholder="เช่น คณะกรรมการประจำคณะวิทยาการสารสนเทศ">
                </div>
                <div>
                    <label>ครั้งที่</label>
                    <input type="text" id="meeting_number" placeholder="เช่น 9/2568">
                </div>
            </div>

            <div class="invitee-panel">
                <div class="invitee-panel-header">
                    <strong style="display:flex;align-items:center;gap:7px;color:#1e3a8a;">
                        <i data-lucide="user-round-plus" style="width:18px;"></i>
                        เชิญผู้เข้าร่วมประชุม
                    </strong>
                    <div class="invitee-toolbar">
                        <input type="search" id="inviteeSearch" placeholder="ค้นหาชื่อ อีเมล ตำแหน่ง หรือภาควิชา..."
                            oninput="renderInviteeList()">
                        <label class="invitee-select-all">
                            <input type="checkbox" id="inviteeSelectAll" onchange="toggleAllInvitees(this.checked)">
                            <span class="invitee-checkmark" aria-hidden="true"></span>
                            เลือกทั้งหมด
                        </label>
                    </div>
                </div>
                <div id="inviteeList" class="invitee-list">
                    <div class="invitee-empty">กำลังโหลดรายชื่อผู้ใช้งาน...</div>
                </div>
            </div>

            <label>เอกสารแนบการประชุม</label>
            <div class="upload-zone" id="dropZone" onclick="document.getElementById('meeting_documents').click()">
                <i data-lucide="cloud-upload"></i>
                <p><b>คลิกเพื่อเลือกไฟล์</b> หรือลากไฟล์มาวางที่นี่</p>
                <span>รองรับไฟล์ PDF, Word, Excel, PowerPoint</span>
                <input type="file" id="meeting_documents" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                    style="display: none;" onchange="updateFilePreview()">
            </div>

            <span class="file-helper-text" id="fileOldNotice"
                style="margin-top: 6px; color: #d97706; font-size: 12px; display: block; font-weight: 500;">
                * หมายเหตุ: การอัปโหลดไฟล์ใหม่ในโหมดแก้ไข จะเป็นการลบและแทนที่เอกสารเดิมของการประชุมนี้ทั้งหมด
            </span>
            <div id="filePreviewContainer" class="file-preview-list"></div>

            <label style="margin-top: 20px; display: block;">วาระการประชุม</label>
            <div id="agenda-container"></div>


        </div>

        <div class="modal-actions">
            <button onclick="saveMeeting()">บันทึกข้อมูล</button>
            <button onclick="closeModal()">ปิดหน้าต่าง</button>
        </div>
    </div>
</div>

<div id="invitationModal" class="modal">
    <div class="modal-box" style="max-width: 820px;">
        <h3 id="invitationModalTitle">เชิญสมาชิกเข้าร่วมประชุม</h3>
        <input type="hidden" id="invitationMeetingId">

        <div class="modal-form-body">
            <div class="invitation-help">
                เลือกสมาชิกที่ต้องการเชิญจากรายชื่อผู้ใช้งานในระบบ แล้วกด
                <strong>“บันทึกและส่งคำเชิญ”</strong> ระบบจะเพิ่มรายชื่อในผู้เข้าร่วมประชุม
                และสร้างการแจ้งเตือนภายในระบบให้สมาชิกแต่ละคน<br>
                <strong>หมายเหตุ:</strong> สมาชิกที่ตอบรับ ปฏิเสธ ส่งผู้แทน หรือเช็กชื่อแล้ว
                จะถูกล็อกและไม่สามารถนำออกจากคำเชิญได้
            </div>

            <div class="invitee-panel" style="margin-top:0;">
                <div class="invitee-panel-header">
                    <strong style="display:flex;align-items:center;gap:7px;color:#1e3a8a;">
                        <i data-lucide="users-round" style="width:18px;"></i>
                        รายชื่อสมาชิกในระบบ
                    </strong>
                    <div class="invitee-toolbar">
                        <input type="search" id="managerInviteeSearch"
                            placeholder="ค้นหาชื่อ อีเมล ตำแหน่ง หรือภาควิชา..." oninput="renderManagerInviteeList()">
                        <label class="invitee-select-all">
                            <input type="checkbox" id="managerInviteeSelectAll"
                                onchange="toggleAllManagerInvitees(this.checked)">
                            <span class="invitee-checkmark" aria-hidden="true"></span>
                            เลือกทั้งหมด
                        </label>
                    </div>
                </div>

                <div id="managerInviteeList" class="invitee-list">
                    <div class="invitee-empty">กำลังโหลดรายชื่อสมาชิก...</div>
                </div>
            </div>

            <div class="invitation-summary">
                <span>สมาชิกที่เลือกสำหรับการประชุมนี้</span>
                <strong><span id="managerInviteeSelectedCount">0</span> คน</strong>
            </div>
        </div>

        <div class="modal-actions">
            <button type="button" onclick="saveMeetingInvitations()">
                บันทึกและส่งคำเชิญ
            </button>
            <button type="button" onclick="closeInvitationManager()">ปิดหน้าต่าง</button>
        </div>
    </div>
</div>

<div id="agendaModal" class="modal">
    <div class="modal-box" style="max-width: 500px;">
        <h3 class="modal-title-with-icon">
            <i data-lucide="clipboard-list"></i>
            <span>วาระการประชุม</span>
        </h3>
        <div class="agenda-view-box" id="agendaList"></div>
        <div class="modal-actions" style="margin-top: 15px;">
            <button onclick="closeAgenda()"
                style="background-color: #2563eb; width: 100%; color: white; border: none;">ปิดหน้าต่าง</button>
        </div>
    </div>
</div>

<div id="attendanceModal" class="modal">
    <div class="modal-box attendance-modal-box">
        <h3>
            <span style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="users-round"></i>
                รายงานผู้เข้าร่วมประชุม
            </span>
        </h3>

        <input type="hidden" id="attendanceMeetingId">

        <div class="modal-form-body">
            <div id="attendanceMeetingInfo"
                style="padding:12px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:14px;color:#1e3a8a;">
            </div>

            <div class="attendance-summary" id="attendanceSummary"></div>

            <div class="attendance-table-wrapper">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>เลือก</th>
                            <th style="width:45px;">ลำดับ</th>
                            <th>ชื่อผู้เข้าร่วม</th>
                            <th>ตำแหน่ง / สังกัด</th>
                            <th>หน้าที่ประชุม</th>
                            <th>ผลการเข้าร่วม</th>
                            <th>ข้อมูลผู้เข้าร่วมแทน</th>
                            <th>หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceTableBody"></tbody>
                </table>
            </div>
        </div>

        <div class="modal-actions">
            <button type="button" onclick="saveAttendanceReport()">บันทึกรายงาน</button>
            <button type="button" onclick="printAttendanceReport()"
                style="background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;">พิมพ์รายงานแบบเอกสาร</button>
            <button type="button" onclick="closeAttendanceReport()">ปิดหน้าต่าง</button>
        </div>
    </div>
</div>



<?php include_once __DIR__ . '/../../../app/views/components/profile_modal.php'; ?>
<?php include_once __DIR__ . '/../../../app/views/layouts/footer.php'; ?>