<?php
/* ============================================================
 * Department Sidebar V9
 * - แสดงข้อมูลผู้ใช้ ตำแหน่ง ภาควิชา และสิทธิ์จากฐานข้อมูล
 * - แสดงจำนวนคำเชิญที่รอตอบรับและการแจ้งเตือนที่ยังไม่อ่าน
 * - รองรับรูปโปรไฟล์ Google / รูปในระบบ / รูปสำรองแบบ SVG
 * - รองรับ Desktop collapsed และ Mobile drawer
 * - เข้ากันกับหน้า Department V7/V8 และระบบเชิญประชุม V6
 * ============================================================ */

$sidebarUserId = (int) ($_SESSION['user_id'] ?? 0);
$sidebarCurrentPage = (string) ($current_page ?? 'dashboard');

$sidebarProfile = [
    'name' => (string) ($_SESSION['name'] ?? $_SESSION['fullname'] ?? 'ผู้ใช้ภาควิชา'),
    'email' => (string) ($_SESSION['email'] ?? ''),
    'picture' => (string) ($_SESSION['picture'] ?? $_SESSION['avatar'] ?? ''),
    'position_name' => (string) ($_SESSION['position_name'] ?? ''),
    'department_name' => (string) ($_SESSION['department_name'] ?? ''),
    'role_name' => (string) ($_SESSION['role_name'] ?? 'ภาควิชา'),
];

$sidebarPendingInvitations = 0;
$sidebarUnreadNotifications = 0;
$sidebarTodayMeetings = 0;

/*
 * ใช้ $db ของหน้าหลักก่อน หากไม่มีให้ Sidebar เชื่อมฐานข้อมูลเอง
 * เพื่อให้ชื่อ/ตำแหน่ง/ภาควิชา/รูปโปรไฟล์เป็นข้อมูลล่าสุดเสมอ
 */
$sidebarDb = null;

if (isset($db) && $db instanceof PDO) {
    $sidebarDb = $db;
} elseif (isset($pdo) && $pdo instanceof PDO) {
    $sidebarDb = $pdo;
} else {
    try {
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        if (class_exists('Database')) {
            $sidebarDb = (new Database())->connect();
        }
    } catch (Throwable $e) {
        $sidebarDb = null;
    }
}

if ($sidebarUserId > 0 && $sidebarDb instanceof PDO) {
    try {
        $sidebarProfileStmt = $sidebarDb->prepare(
            "SELECT
                u.name,
                u.email,
                u.picture,
                COALESCE(p.position_name, '') AS position_name,
                COALESCE(d.department_name, '') AS department_name,
                COALESCE(r.role_name, 'ภาควิชา') AS role_name
             FROM user u
             LEFT JOIN positions p ON p.position_id = u.position_id
             LEFT JOIN departments d ON d.department_id = u.department_id
             LEFT JOIN role r ON r.role_id = u.role_id
             WHERE u.user_id = ?
             LIMIT 1"
        );
        $sidebarProfileStmt->execute([$sidebarUserId]);
        $sidebarDbProfile = $sidebarProfileStmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($sidebarDbProfile)) {
            $sidebarProfile = array_merge($sidebarProfile, $sidebarDbProfile);
        }
    } catch (Throwable $sidebarProfileException) {
        // ใช้ข้อมูลจาก Session ต่อ เพื่อไม่ให้ Sidebar ทำให้ทั้งหน้าหยุดทำงาน
    }

    try {
        $sidebarInvitationStmt = $sidebarDb->prepare(
            "SELECT
                SUM(
                    CASE
                        WHEN ma.rsvp_status = 'pending'
                         AND COALESCE(ma.attendance_status, 'pending') = 'pending'
                        THEN 1 ELSE 0
                    END
                ) AS pending_count,
                SUM(
                    CASE
                        WHEN m.meeting_date = CURDATE()
                         AND m.meeting_status <> 'closed'
                        THEN 1 ELSE 0
                    END
                ) AS today_count
             FROM meeting_attendance ma
             INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
             WHERE ma.user_id = ?"
        );
        $sidebarInvitationStmt->execute([$sidebarUserId]);
        $sidebarInvitationStats = $sidebarInvitationStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $sidebarPendingInvitations = (int) ($sidebarInvitationStats['pending_count'] ?? 0);
        $sidebarTodayMeetings = (int) ($sidebarInvitationStats['today_count'] ?? 0);
    } catch (Throwable $sidebarInvitationException) {
        // รองรับหน้าหรือฐานข้อมูลที่ยังไม่อัปเกรดครบ
    }

    try {
        $sidebarNotificationStmt = $sidebarDb->prepare(
            "SELECT COUNT(*)
             FROM notifications
             WHERE user_id = ?
               AND is_read = 0"
        );
        $sidebarNotificationStmt->execute([$sidebarUserId]);
        $sidebarUnreadNotifications = (int) $sidebarNotificationStmt->fetchColumn();
    } catch (Throwable $sidebarNotificationException) {
        // ไม่แสดง Badge หากตารางแจ้งเตือนยังไม่พร้อม
    }
}

$sidebarH = static fn ($value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$sidebarName = trim((string) ($sidebarProfile['name'] ?? '')) ?: 'ผู้ใช้ภาควิชา';
$sidebarEmail = trim((string) ($sidebarProfile['email'] ?? ''));
$sidebarPosition = trim((string) ($sidebarProfile['position_name'] ?? '')) ?: '';
$sidebarDepartment = trim((string) ($sidebarProfile['department_name'] ?? '')) ?: 'ยังไม่ระบุภาควิชา';
$sidebarRole = trim((string) ($sidebarProfile['role_name'] ?? '')) ?: 'ภาควิชา';

/* BASE_URL ของโปรเจกต์นี้ชี้เข้าฝั่ง public อยู่แล้ว เช่น /Meeting_msu/public/ */
$sidebarPublicBase = defined('BASE_URL')
    ? rtrim((string) BASE_URL, '/') . '/'
    : '/Meeting_msu/public/';

/* สร้างรูปสำรองในเครื่อง ไม่เรียกบริการ Avatar ภายนอก */
$sidebarInitial = function_exists('mb_substr')
    ? mb_substr($sidebarName, 0, 1, 'UTF-8')
    : substr($sidebarName, 0, 1);
$sidebarInitialEscaped = htmlspecialchars($sidebarInitial ?: 'U', ENT_QUOTES | ENT_XML1, 'UTF-8');
$sidebarFallbackSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">'
    . '<rect width="100%" height="100%" rx="60" fill="#0284c7"/>'
    . '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" '
    . 'font-family="Tahoma,Arial,sans-serif" font-size="52" font-weight="700" fill="#ffffff">'
    . $sidebarInitialEscaped
    . '</text></svg>';
$sidebarFallbackAvatar = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($sidebarFallbackSvg);

$sidebarPicture = trim((string) ($sidebarProfile['picture'] ?? ''));
$sidebarAvatar = '';

if ($sidebarPicture !== '') {
    if (filter_var($sidebarPicture, FILTER_VALIDATE_URL)) {
        $sidebarPictureScheme = strtolower((string) parse_url($sidebarPicture, PHP_URL_SCHEME));
        if (in_array($sidebarPictureScheme, ['http', 'https'], true)) {
            $sidebarAvatar = $sidebarPicture;
        }
    } else {
        $normalizedPicture = str_replace('\\', '/', $sidebarPicture);
        $pictureFile = basename($normalizedPicture);

        if ($pictureFile !== '' && $pictureFile !== '.' && $pictureFile !== '..') {
            $sidebarAvatar = $sidebarPublicBase
                . 'uploads/avatars/'
                . rawurlencode($pictureFile)
                . '?v=' . rawurlencode((string) @filemtime(__DIR__ . '/../../../public/uploads/avatars/' . $pictureFile));
        }
    }
}

if ($sidebarAvatar === '') {
    $sidebarAvatar = $sidebarFallbackAvatar;
}

$sidebarIsActive = static function (string $page) use ($sidebarCurrentPage): string {
    return $sidebarCurrentPage === $page ? ' active' : '';
};

$sidebarPendingBadge = $sidebarPendingInvitations > 99 ? '99+' : (string) $sidebarPendingInvitations;
$sidebarUnreadBadge = $sidebarUnreadNotifications > 99 ? '99+' : (string) $sidebarUnreadNotifications;
?>

<style>
    :root {
        --ds-primary: #0284c7;
        --ds-primary-dark: #0369a1;
        --ds-primary-soft: #f0f9ff;
        --ds-border: #e2e8f0;
        --ds-text: #1e293b;
        --ds-muted: #64748b;
        --ds-danger: #ef4444;
    }

    .sidebar-wrapper,
    .sidebar-wrapper * {
        box-sizing: border-box;
    }

    .sidebar-wrapper {
        width: 260px;
        height: 100vh;
        background: #ffffff;
        border-right: 1px solid var(--ds-border);
        display: flex;
        flex-direction: column;
        position: fixed;
        inset: 0 auto 0 0;
        z-index: 1000;
        overflow: hidden;
        transition: width .28s ease, transform .28s ease, box-shadow .28s ease;
    }

    .sidebar-wrapper.collapsed {
        width: 70px;
    }

    .ds-brand {
        min-height: 68px;
        padding: 13px 16px;
        display: flex;
        align-items: center;
        gap: 11px;
        color: #ffffff;
        background: linear-gradient(135deg, #0369a1, #0284c7 58%, #0ea5e9);
        overflow: hidden;
        flex: 0 0 auto;
    }

    .ds-brand-icon {
        width: 39px;
        height: 39px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        background: rgba(255, 255, 255, .16);
        border: 1px solid rgba(255, 255, 255, .17);
        flex: 0 0 auto;
    }

    .ds-brand-icon svg {
        width: 21px;
        height: 21px;
    }

    .ds-brand-copy {
        min-width: 0;
        line-height: 1.25;
        white-space: nowrap;
    }

    .ds-brand-copy strong {
        display: block;
        font-size: 14px;
        font-weight: 750;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ds-brand-copy span {
        display: block;
        margin-top: 3px;
        color: rgba(255,255,255,.78);
        font-size: 10.5px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ds-mobile-close {
        width: 32px;
        height: 32px;
        margin-left: auto;
        border: 0;
        border-radius: 9px;
        color: #ffffff;
        background: rgba(255,255,255,.14);
        display: none;
        place-items: center;
        cursor: pointer;
        flex: 0 0 auto;
    }

    .ds-profile {
        margin: 12px;
        padding: 13px;
        display: flex;
        align-items: center;
        gap: 11px;
        border: 1px solid var(--ds-border);
        border-radius: 15px;
        background: linear-gradient(180deg, #ffffff, #f8fafc);
        box-shadow: 0 4px 12px rgba(15, 23, 42, .035);
        overflow: hidden;
        flex: 0 0 auto;
    }

    .ds-avatar-wrap {
        position: relative;
        flex: 0 0 auto;
    }

    .ds-avatar {
        width: 43px;
        height: 43px;
        display: block;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid #ffffff;
        background: #e0f2fe;
        box-shadow: 0 0 0 2px #bae6fd;
    }

    .ds-alert-dot {
        position: absolute;
        right: -1px;
        top: -2px;
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        border: 2px solid #ffffff;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        background: #ef4444;
        font-size: 8.5px;
        font-weight: 800;
        line-height: 1;
    }

    .ds-profile-copy {
        min-width: 0;
        flex: 1;
    }

    .ds-profile-name {
        display: block;
        overflow: hidden;
        color: var(--ds-text);
        font-size: 13.5px;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ds-profile-meta {
        display: block;
        margin-top: 3px;
        overflow: hidden;
        color: var(--ds-muted);
        font-size: 10.5px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ds-role-chip {
        display: inline-flex;
        max-width: 100%;
        margin-top: 6px;
        padding: 3px 7px;
        border-radius: 999px;
        overflow: hidden;
        color: var(--ds-primary-dark);
        background: #e0f2fe;
        font-size: 9.5px;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sidebar-menu {
        min-height: 0;
        flex: 1;
        padding: 2px 10px 13px;
        overflow-x: hidden;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .sidebar-menu::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar-menu::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: #cbd5e1;
    }

    .menu-category {
        padding: 15px 10px 6px;
        color: #94a3b8;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .055em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .menu-item {
        position: relative;
        min-height: 43px;
        margin: 2px 0;
        padding: 10px 11px;
        display: flex;
        align-items: center;
        gap: 11px;
        border: 1px solid transparent;
        border-radius: 11px;
        color: #475569;
        background: transparent;
        text-decoration: none;
        font-size: 12.5px;
        font-weight: 600;
        line-height: 1.3;
        white-space: nowrap;
        transition: color .16s ease, background .16s ease, border-color .16s ease, transform .16s ease;
    }

    .menu-item:hover {
        color: var(--ds-text);
        background: #f8fafc;
        border-color: #edf2f7;
        transform: translateX(2px);
    }

    .menu-item.active {
        color: var(--ds-primary-dark);
        background: var(--ds-primary-soft);
        border-color: #bae6fd;
    }

    .menu-item.active::before {
        content: "";
        position: absolute;
        left: -10px;
        top: 8px;
        bottom: 8px;
        width: 3px;
        border-radius: 0 4px 4px 0;
        background: var(--ds-primary);
    }

    .menu-item > svg,
    .menu-item > i,
    .menu-item .lucide {
        width: 19px;
        height: 19px;
        flex: 0 0 auto;
    }

    .menu-text {
        min-width: 0;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ds-menu-badge {
        min-width: 21px;
        height: 21px;
        padding: 0 6px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        background: #f59e0b;
        font-size: 9.5px;
        font-weight: 800;
        line-height: 1;
        flex: 0 0 auto;
    }

    .ds-menu-badge.is-info {
        background: var(--ds-primary);
    }

    .ds-today-note {
        margin: 8px 3px 0;
        padding: 9px 10px;
        border: 1px solid #bbf7d0;
        border-radius: 11px;
        color: #166534;
        background: #f0fdf4;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 10.5px;
        font-weight: 650;
        line-height: 1.35;
    }

    .ds-today-note svg {
        width: 16px;
        height: 16px;
        flex: 0 0 auto;
    }

    .sidebar-footer {
        padding: 11px 10px 14px;
        border-top: 1px solid #f1f5f9;
        background: #ffffff;
        flex: 0 0 auto;
    }

    .btn-logout {
        color: #dc2626;
    }

    .btn-logout:hover {
        color: #b91c1c;
        background: #fef2f2;
        border-color: #fecaca;
    }

    .sidebar-wrapper.collapsed .ds-brand {
        padding-inline: 15px;
        justify-content: center;
    }

    .sidebar-wrapper.collapsed .ds-brand-copy,
    .sidebar-wrapper.collapsed .ds-profile-copy,
    .sidebar-wrapper.collapsed .menu-category,
    .sidebar-wrapper.collapsed .menu-text,
    .sidebar-wrapper.collapsed .ds-menu-badge,
    .sidebar-wrapper.collapsed .ds-today-note {
        display: none;
    }

    .sidebar-wrapper.collapsed .ds-profile {
        margin: 12px 8px;
        padding: 10px 5px;
        justify-content: center;
        border-color: transparent;
        background: transparent;
        box-shadow: none;
    }

    .sidebar-wrapper.collapsed .menu-item {
        padding-inline: 13px;
        justify-content: center;
    }

    .sidebar-wrapper.collapsed .menu-item:hover {
        transform: none;
    }

    .sidebar-wrapper.collapsed .menu-item.active::before {
        left: -10px;
    }

    .ds-mobile-overlay {
        position: fixed;
        inset: 0;
        z-index: 999;
        display: none;
        background: rgba(15, 23, 42, .48);
        backdrop-filter: blur(2px);
    }

    @media (max-width: 768px) {
        .sidebar-wrapper {
            width: min(86vw, 300px);
            transform: translateX(-105%);
            border-right: 0;
            box-shadow: 18px 0 42px rgba(15, 23, 42, .20);
        }

        /*
         * หน้า Department เดิมใช้ class collapsed ตอนกดปุ่มเมนู
         * บนมือถือจึงตีความ collapsed เป็น "เปิด Drawer"
         */
        .sidebar-wrapper.collapsed {
            width: min(86vw, 300px);
            transform: translateX(0);
        }

        .sidebar-wrapper.collapsed .ds-brand {
            padding: 13px 16px;
            justify-content: flex-start;
        }

        .sidebar-wrapper.collapsed .ds-brand-copy,
        .sidebar-wrapper.collapsed .ds-profile-copy,
        .sidebar-wrapper.collapsed .menu-category,
        .sidebar-wrapper.collapsed .menu-text,
        .sidebar-wrapper.collapsed .ds-menu-badge,
        .sidebar-wrapper.collapsed .ds-today-note {
            display: initial;
        }

        .sidebar-wrapper.collapsed .ds-profile {
            margin: 12px;
            padding: 13px;
            justify-content: flex-start;
            border-color: var(--ds-border);
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            box-shadow: 0 4px 12px rgba(15, 23, 42, .035);
        }

        .sidebar-wrapper.collapsed .menu-item {
            padding: 10px 11px;
            justify-content: flex-start;
        }

        .sidebar-wrapper.collapsed .ds-menu-badge {
            display: inline-flex;
        }

        .sidebar-wrapper.collapsed .ds-today-note {
            display: flex;
        }

        .ds-mobile-close {
            display: grid;
        }

        .ds-mobile-overlay.is-visible {
            display: block;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .sidebar-wrapper,
        .menu-item {
            transition: none;
        }
    }
</style>

<aside class="sidebar-wrapper" id="sidebar" aria-label="เมนูผู้ใช้งานภาควิชา">
    <div class="ds-brand">
        <span class="ds-brand-icon" aria-hidden="true">
            <i data-lucide="presentation"></i>
        </span>

        <div class="ds-brand-copy">
            <strong>ระบบงานประชุมคณะ</strong>
            <span><?= $sidebarH($sidebarDepartment) ?></span>
        </div>

        <button
            type="button"
            class="ds-mobile-close"
            id="departmentSidebarClose"
            aria-label="ปิดเมนู"
            title="ปิดเมนู"
        >
            <i data-lucide="x"></i>
        </button>
    </div>

    <button
        type="button"
        class="ds-profile"
        onclick="if (typeof openProfileModal === 'function') { openProfileModal(); }"
        aria-label="เปิดโปรไฟล์ของ <?= $sidebarH($sidebarName) ?>"
        title="เปิดข้อมูลโปรไฟล์"
        style="width:auto;text-align:left;cursor:pointer;"
    >
        <span class="ds-avatar-wrap">
            <img
                src="<?= $sidebarH($sidebarAvatar) ?>"
                alt="รูปโปรไฟล์ของ <?= $sidebarH($sidebarName) ?>"
                class="ds-avatar"
                onerror="this.onerror=null;this.src='<?= $sidebarH($sidebarFallbackAvatar) ?>';"
            >
            <?php if ($sidebarUnreadNotifications > 0): ?>
                <span class="ds-alert-dot" title="มีการแจ้งเตือนที่ยังไม่อ่าน">
                    <?= $sidebarH($sidebarUnreadBadge) ?>
                </span>
            <?php endif; ?>
        </span>

        <span class="ds-profile-copy">
            <span class="ds-profile-name" title="<?= $sidebarH($sidebarName) ?>">
                <?= $sidebarH($sidebarName) ?>
            </span>
            <span
                class="ds-profile-meta"
                title="<?= $sidebarH($sidebarPosition . ' • ' . $sidebarDepartment) ?>"
            >
                <?= $sidebarH($sidebarPosition) ?> · <?= $sidebarH($sidebarDepartment) ?>
            </span>
            <span class="ds-role-chip"><?= $sidebarH($sidebarRole) ?></span>
        </span>
    </button>

    <nav class="sidebar-menu" aria-label="เมนูหลัก">
        <div class="menu-category">การประชุมของฉัน</div>

        <a
            href="index.php"
            class="menu-item<?= $sidebarIsActive('dashboard') ?>"
            <?= $sidebarCurrentPage === 'dashboard' ? 'aria-current="page"' : '' ?>
            title="คำเชิญและการประชุมของฉัน"
        >
            <i data-lucide="mail-check"></i>
            <span class="menu-text">ศูนย์ควบคุมงานประชุมภาควิชา</span>
            <?php if ($sidebarPendingInvitations > 0): ?>
                <span class="ds-menu-badge" title="รอตอบรับ <?= $sidebarH($sidebarPendingInvitations) ?> รายการ">
                    <?= $sidebarH($sidebarPendingBadge) ?>
                </span>
            <?php endif; ?>
        </a>

        <a
            href="calendar.php"
            class="menu-item<?= $sidebarIsActive('calendar') ?>"
            <?= $sidebarCurrentPage === 'calendar' ? 'aria-current="page"' : '' ?>
            title="ปฏิทินการประชุมของฉัน"
        >
            <i data-lucide="calendar-days"></i>
            <span class="menu-text">ปฏิทินการประชุม</span>
            <?php if ($sidebarTodayMeetings > 0): ?>
                <span class="ds-menu-badge is-info" title="มีประชุมวันนี้">
                    <?= $sidebarH($sidebarTodayMeetings > 9 ? '9+' : $sidebarTodayMeetings) ?>
                </span>
            <?php endif; ?>
        </a>

        <a
            href="meeting_history.php"
            class="menu-item<?= $sidebarIsActive('history') ?>"
            <?= $sidebarCurrentPage === 'history' ? 'aria-current="page"' : '' ?>
            title="ประวัติการประชุมของฉัน"
        >
            <i data-lucide="history"></i>
            <span class="menu-text">ประวัติการเข้าประชุม</span>
        </a>

        <?php if ($sidebarTodayMeetings > 0): ?>
            <div class="ds-today-note">
                <i data-lucide="calendar-clock"></i>
                <span>วันนี้มีการประชุม <?= $sidebarH($sidebarTodayMeetings) ?> รายการ</span>
            </div>
        <?php endif; ?>

        <div class="menu-category">งานของภาควิชา</div>

        <a
            href="submit_agenda.php"
            class="menu-item<?= $sidebarIsActive('submit_agenda') ?>"
            <?= $sidebarCurrentPage === 'submit_agenda' ? 'aria-current="page"' : '' ?>
            title="เสนอวาระการประชุม"
        >
            <i data-lucide="file-plus-2"></i>
            <span class="menu-text">เสนอวาระการประชุม</span>
        </a>

        <a
            href="track_agendas.php"
            class="menu-item<?= $sidebarIsActive('track_agendas') ?>"
            <?= $sidebarCurrentPage === 'track_agendas' ? 'aria-current="page"' : '' ?>
            title="ติดตามสถานะวาระที่เสนอ"
        >
            <i data-lucide="clipboard-list"></i>
            <span class="menu-text">ติดตามวาระที่เสนอ</span>
        </a>

        <a
            href="documents.php"
            class="menu-item<?= $sidebarIsActive('documents') ?>"
            <?= $sidebarCurrentPage === 'documents' ? 'aria-current="page"' : '' ?>
            title="คลังเอกสารของภาควิชา"
        >
            <i data-lucide="folder-open"></i>
            <span class="menu-text">คลังเอกสารการประชุม</span>
        </a>

        <div class="menu-category">บัญชีผู้ใช้งาน</div>

        <a
            href="javascript:void(0)"
            onclick="if (typeof openProfileModal === 'function') { openProfileModal(); }"
            class="menu-item<?= $sidebarIsActive('profile') ?>"
            title="ตั้งค่าโปรไฟล์"
        >
            <i data-lucide="user-cog"></i>
            <span class="menu-text">ข้อมูลและโปรไฟล์</span>
            <?php if ($sidebarUnreadNotifications > 0): ?>
                <span class="ds-menu-badge is-info" title="มีการแจ้งเตือนที่ยังไม่อ่าน">
                    <?= $sidebarH($sidebarUnreadBadge) ?>
                </span>
            <?php endif; ?>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a
            href="../auth/logout.php"
            class="menu-item btn-logout"
            onclick="return handleLogout(event)"
            title="ออกจากระบบ"
        >
            <i data-lucide="log-out"></i>
            <span class="menu-text">ออกจากระบบ</span>
        </a>
    </div>
</aside>

<div
    class="ds-mobile-overlay"
    id="departmentSidebarOverlay"
    aria-hidden="true"
></div>

<script>
(function () {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('departmentSidebarOverlay');
    const closeButton = document.getElementById('departmentSidebarClose');

    if (!sidebar || !overlay) {
        return;
    }

    function isMobile() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function syncMobileOverlay() {
        const shouldShow = isMobile() && sidebar.classList.contains('collapsed');
        overlay.classList.toggle('is-visible', shouldShow);
        overlay.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
        document.body.style.overflow = shouldShow ? 'hidden' : '';
    }

    function closeMobileSidebar() {
        if (!isMobile()) {
            return;
        }

        sidebar.classList.remove('collapsed');
        document.getElementById('mainContent')?.classList.remove('expanded');
        syncMobileOverlay();
    }

    overlay.addEventListener('click', closeMobileSidebar);
    closeButton?.addEventListener('click', closeMobileSidebar);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMobileSidebar();
        }
    });

    /*
     * ปุ่ม #toggle-sidebar อยู่ในหน้า Content และผูก Event จากหน้าเดิมอยู่แล้ว
     * MutationObserver ใช้เพียงอัปเดต Overlay หลัง class ถูกเปลี่ยน
     */
    const observer = new MutationObserver(syncMobileOverlay);
    observer.observe(sidebar, {
        attributes: true,
        attributeFilter: ['class']
    });

    window.addEventListener('resize', syncMobileOverlay);

    document.addEventListener('DOMContentLoaded', function () {
        syncMobileOverlay();
        if (window.lucide) {
            window.lucide.createIcons();
        }
    });

    syncMobileOverlay();

    if (window.lucide) {
        window.lucide.createIcons();
    }
})();
</script>