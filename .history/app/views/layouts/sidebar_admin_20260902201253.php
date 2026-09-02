<?php
/* ============================================================
 * Admin Sidebar V11
 * เข้ากันกับระบบผู้ใช้งาน ตำแหน่ง คำเชิญ และรายงานประชุมล่าสุด
 * ============================================================ */

$adminSidebarUserId = (int) ($_SESSION['user_id'] ?? 0);
$adminSidebarPage = (string) ($current_page ?? 'dashboard');

$adminSidebarBaseUrl = defined('BASE_URL')
    ? rtrim((string) BASE_URL, '/') . '/'
    : '/';

$adminSidebarH = static fn($value): string =>
htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$adminSidebarProfile = [
    'name' => (string) ($_SESSION['name'] ?? $_SESSION['fullname'] ?? 'ผู้ดูแลระบบ'),
    'email' => (string) ($_SESSION['email'] ?? ''),
    'picture' => (string) ($_SESSION['picture'] ?? $_SESSION['avatar'] ?? ''),
    'role_name' => (string) ($_SESSION['role_name'] ?? 'แอดมิน'),
    'position_name' => (string) ($_SESSION['position_name'] ?? ''),
    'department_name' => (string) ($_SESSION['department_name'] ?? ''),
];

$adminSidebarStats = [
    'pending_users' => 0,
    'active_meetings' => 0,
    'pending_invitations' => 0,
    'unread_notifications' => 0,
    'pending_agendas' => 0
];

$adminSidebarDb = null;

if (isset($db) && $db instanceof PDO) {
    $adminSidebarDb = $db;
} elseif (isset($pdo) && $pdo instanceof PDO) {
    $adminSidebarDb = $pdo;
}

if ($adminSidebarDb instanceof PDO) {
    try {
        $profileStmt = $adminSidebarDb->prepare(
            "SELECT
                u.name,
                u.email,
                u.picture,
                COALESCE(r.role_name, 'แอดมิน') AS role_name,
                COALESCE(p.position_name, '') AS position_name,
                COALESCE(d.department_name, '') AS department_name
             FROM user u
             LEFT JOIN role r ON r.role_id = u.role_id
             LEFT JOIN positions p ON p.position_id = u.position_id
             LEFT JOIN departments d ON d.department_id = u.department_id
             WHERE u.user_id = ?
             LIMIT 1"
        );
        $profileStmt->execute([$adminSidebarUserId]);
        $profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($profileData)) {
            $adminSidebarProfile = array_merge($adminSidebarProfile, $profileData);
        }
    } catch (Throwable $e) {
        // ใช้ข้อมูลจาก Session ต่อ เพื่อไม่ให้ Sidebar ทำให้ทั้งหน้าหยุดทำงาน
    }

    try {
        $adminSidebarStats['pending_users'] = (int) $adminSidebarDb
            ->query("SELECT COUNT(*) FROM user WHERE status = 'pending'")
            ->fetchColumn();
    } catch (Throwable $e) {
        // ฐานข้อมูลบางชุดอาจยังไม่มีสถานะนี้
    }

    try {
        $adminSidebarStats['active_meetings'] = (int) $adminSidebarDb
            ->query(
                "SELECT COUNT(*)
                 FROM meeting
                 WHERE meeting_status IN ('upcoming', 'ongoing')"
            )
            ->fetchColumn();
    } catch (Throwable $e) {
        // ไม่แสดง Badge หากตารางยังไม่พร้อม
    }

    try {
        $adminSidebarStats['pending_invitations'] = (int) $adminSidebarDb
            ->query(
                "SELECT COUNT(*)
                 FROM meeting_attendance
                 WHERE rsvp_status = 'pending'"
            )
            ->fetchColumn();
    } catch (Throwable $e) {
        // ไม่แสดง Badge หากตารางยังไม่พร้อม
    }

    /* ===============================
   นับวาระรออนุมัติ
=============================== */
    try {
        $adminSidebarStats['pending_agendas'] = (int) $adminSidebarDb
            ->query(
                "SELECT COUNT(*)
             FROM agenda
             WHERE admin_status = 'pending'"
            )
            ->fetchColumn();
    } catch (Throwable $e) {
        // ไม่แสดง Badge หากตารางยังไม่พร้อม
    }

    try {
        $notificationStmt = $adminSidebarDb->prepare(
            "SELECT COUNT(*)
             FROM notifications
             WHERE user_id = ?
               AND is_read = 0"
        );
        $notificationStmt->execute([$adminSidebarUserId]);
        $adminSidebarStats['unread_notifications'] = (int) $notificationStmt->fetchColumn();
    } catch (Throwable $e) {
        // ไม่แสดง Badge หากตารางยังไม่พร้อม
    }
}

$adminSidebarName = trim((string) $adminSidebarProfile['name']) ?: 'ผู้ดูแลระบบ';
$adminSidebarEmail = trim((string) $adminSidebarProfile['email']);
$adminSidebarRole = trim((string) $adminSidebarProfile['role_name']) ?: 'แอดมิน';
$adminSidebarPosition = trim((string) $adminSidebarProfile['position_name']);
$adminSidebarDepartment = trim((string) $adminSidebarProfile['department_name']);

$adminSidebarMeta = implode(
    ' · ',
    array_values(
        array_filter(
            [$adminSidebarPosition, $adminSidebarDepartment],
            static fn($value): bool => trim((string) $value) !== ''
        )
    )
);

if ($adminSidebarMeta === '') {
    $adminSidebarMeta = $adminSidebarEmail !== ''
        ? $adminSidebarEmail
        : 'ผู้ดูแลระบบงานประชุม';
}

$adminSidebarInitial = function_exists('mb_substr')
    ? mb_substr($adminSidebarName, 0, 1, 'UTF-8')
    : substr($adminSidebarName, 0, 1);

$adminSidebarInitialXml = htmlspecialchars(
    $adminSidebarInitial ?: 'A',
    ENT_QUOTES | ENT_XML1,
    'UTF-8'
);

$adminSidebarFallbackSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">'
    . '<rect width="120" height="120" rx="60" fill="#1d4ed8"/>'
    . '<text x="60" y="67" text-anchor="middle" '
    . 'font-family="Tahoma,Arial,sans-serif" font-size="52" font-weight="700" fill="#ffffff">'
    . $adminSidebarInitialXml
    . '</text></svg>';

$adminSidebarFallbackAvatar =
    'data:image/svg+xml;charset=UTF-8,' . rawurlencode($adminSidebarFallbackSvg);

$adminSidebarPicture = trim((string) ($adminSidebarProfile['picture'] ?? ''));
$adminSidebarAvatar = '';

/*
 * BASE_URL ของโปรเจกต์นี้ชี้มายัง public/ อยู่แล้ว
 * เช่น /public/
 * ดังนั้นรูป local ต้องต่อเพียง uploads/avatars/ เท่านั้น
 * ห้ามต่อ public/uploads/avatars/ ซ้ำ
 */
if ($adminSidebarPicture !== '') {
    if (filter_var($adminSidebarPicture, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string) parse_url($adminSidebarPicture, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            $adminSidebarAvatar = $adminSidebarPicture;
        }
    } else {
        $normalizedPicture = str_replace('\\', '/', $adminSidebarPicture);
        $pictureFile = basename($normalizedPicture);

        if ($pictureFile !== '' && $pictureFile !== '.' && $pictureFile !== '..') {
            $adminSidebarAvatar =
                $adminSidebarBaseUrl
                . 'uploads/avatars/'
                . rawurlencode($pictureFile)
                . '?v=' . rawurlencode((string) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/public/uploads/avatars/' . $pictureFile));
        }
    }
}

if ($adminSidebarAvatar === '') {
    $adminSidebarAvatar = $adminSidebarFallbackAvatar;
}

$adminSidebarSystemPages = [
    'users',
    'meetings',
    'meeting_reports',
    'agendas',
];

$adminSidebarSystemActive = in_array(
    $adminSidebarPage,
    $adminSidebarSystemPages,
    true
);

$adminSidebarIsActive = static function (string $page) use ($adminSidebarPage): string {
    return $adminSidebarPage === $page ? ' active' : '';
};

$adminSidebarBadge = static function (int $value): string {
    return $value > 99 ? '99+' : (string) $value;
};

$adminSidebarOwnProfileUrl =
    $adminSidebarBaseUrl
    . 'admin/users/edit_users.php?search='
    . rawurlencode($adminSidebarEmail !== '' ? $adminSidebarEmail : $adminSidebarName);
?>

<style>
:root {
    --as-primary: #2563eb;
    --as-primary-dark: #1d4ed8;
    --as-primary-soft: #eff6ff;
    --as-border: #e2e8f0;
    --as-text: #0f172a;
    --as-muted: #64748b;
    --as-danger: #dc2626;
}

.sidebar.admin-sidebar,
.sidebar.admin-sidebar * {
    box-sizing: border-box;
}

.sidebar.admin-sidebar {
    width: 268px;
    height: 100vh;
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-right: 1px solid var(--as-border);
    background: #ffffff;
    transition:
        width .26s ease,
        transform .26s ease,
        box-shadow .26s ease;
}

.sidebar.admin-sidebar.collapsed {
    width: 74px;
}

.admin-sidebar .sidebar-header {
    min-height: 70px;
    padding: 13px 15px;
    display: flex;
    align-items: center;
    gap: 11px;
    flex: 0 0 auto;
    overflow: hidden;
    color: #ffffff;
    background:
        linear-gradient(135deg, #172554 0%, #1d4ed8 58%, #3b82f6 100%);
}

.admin-sidebar-logo {
    width: 41px;
    height: 41px;
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, .18);
    border-radius: 13px;
    background: rgba(255, 255, 255, .14);
}

.admin-sidebar-logo svg {
    width: 22px;
    height: 22px;
}

.admin-sidebar-brand {
    min-width: 0;
    line-height: 1.25;
    white-space: nowrap;
}

.admin-sidebar-brand strong,
.admin-sidebar-brand span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-sidebar-brand strong {
    font-size: 14px;
    font-weight: 800;
}

.admin-sidebar-brand span {
    margin-top: 3px;
    color: rgba(255, 255, 255, .74);
    font-size: 10.5px;
}

.admin-sidebar-mobile-close {
    width: 32px;
    height: 32px;
    margin-left: auto;
    flex: 0 0 auto;
    border: 0;
    border-radius: 9px;
    display: none;
    place-items: center;
    color: #ffffff;
    background: rgba(255, 255, 255, .13);
    cursor: pointer;
}

.admin-profile-card {
    margin: 12px;
    padding: 12px;
    min-height: 70px;
    display: flex;
    align-items: center;
    gap: 11px;
    overflow: hidden;
    border: 1px solid var(--as-border);
    border-radius: 15px;
    color: inherit;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    box-shadow: 0 4px 14px rgba(15, 23, 42, .04);
    text-decoration: none;
    flex: 0 0 auto;
}

.admin-profile-card:hover {
    border-color: #bfdbfe;
    background: #f8fbff;
}

.admin-avatar-wrap {
    position: relative;
    flex: 0 0 auto;
}

.admin-avatar {
    width: 44px;
    height: 44px;
    display: block;
    object-fit: cover;
    border: 3px solid #ffffff;
    border-radius: 50%;
    background: #dbeafe;
    box-shadow: 0 0 0 2px #bfdbfe;
}

.admin-notification-dot {
    position: absolute;
    right: -2px;
    top: -3px;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    border: 2px solid #ffffff;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    background: #ef4444;
    font-size: 8px;
    font-weight: 800;
    line-height: 1;
}

.admin-profile-copy {
    min-width: 0;
    flex: 1;
}

.admin-profile-name,
.admin-profile-meta {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-profile-name {
    color: var(--as-text);
    font-size: 15px;
    font-weight: 750;
}

.admin-profile-meta {
    margin-top: 3px;
    color: var(--as-muted);
    font-size: 12px;
}

.admin-role-chip {
    max-width: 100%;
    margin-top: 6px;
    padding: 3px 7px;
    display: inline-flex;
    overflow: hidden;
    border-radius: 999px;
    color: #6d28d9;
    background: #f5f3ff;
    font-size: 12.5px;
    font-weight: 750;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-sidebar .sidebar-menu {
    min-height: 0;
    flex: 1;
    padding: 2px 10px 13px;
    overflow-x: hidden;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.admin-sidebar .sidebar-menu::-webkit-scrollbar {
    width: 5px;
}

.admin-sidebar .sidebar-menu::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: #cbd5e1;
}

.admin-menu-category {
    padding: 14px 10px 6px;
    color: #94a3b8;
    font-size: 9.5px;
    font-weight: 850;
    letter-spacing: .055em;
    text-transform: uppercase;
    white-space: nowrap;
}

.admin-sidebar .menu-item,
.admin-sidebar .submenu-item {
    position: relative;
    width: 100%;
    min-height: 43px;
    display: flex;
    align-items: center;
    gap: 11px;
    border: 1px solid transparent;
    color: #475569;
    background: transparent;
    text-decoration: none;
    font-weight: 650;
    line-height: 1.3;
    white-space: nowrap;
    cursor: pointer;
    transition:
        color .16s ease,
        background .16s ease,
        border-color .16s ease,
        transform .16s ease;
}

.admin-sidebar .menu-item {
    margin: 4px 0;
    padding: 12px 14px;
    border-radius: 11px;
    font-size: 15px;
    min-height: 48px;
}

.admin-sidebar .submenu-item {
    margin: 2px 0;
    padding: 9px 10px;
    border-radius: 9px;
    font-size: 14px;
    min-height: 44px;
}

.admin-sidebar .menu-item:hover,
.admin-sidebar .submenu-item:hover {
    color: var(--as-text);
    border-color: #edf2f7;
    background: #f8fafc;
    transform: translateX(2px);
}

.admin-sidebar .menu-item.active,
.admin-sidebar .submenu-item.active {
    color: var(--as-primary-dark);
    border-color: #bfdbfe;
    background: var(--as-primary-soft);
}

.admin-sidebar .menu-item.active::before {
    content: "";
    position: absolute;
    left: -10px;
    top: 8px;
    bottom: 8px;
    width: 3px;
    border-radius: 0 4px 4px 0;
    background: var(--as-primary);
}

.admin-sidebar .menu-item>svg,
.admin-sidebar .menu-item>i,
.admin-sidebar .submenu-item>svg,
.admin-sidebar .submenu-item>i {
    width: 22px;
    height: 22px;
    flex: 0 0 auto;
}

.admin-menu-text {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-menu-badge {
    min-width: 21px;
    height: 21px;
    padding: 0 6px;
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    color: #ffffff;
    background: #3657ff;
    font-size: 9px;
    font-weight: 850;
    line-height: 1;
}

.admin-menu-badge.info {
    background: var(--as-primary);
}

.admin-menu-badge.danger {
    background: #ef4444;
}

.admin-dropdown {
    width: 100%;
    display: flex;
    flex-direction: column;
}

.admin-dropdown-button {
    justify-content: space-between;
    text-align: left;
}

.admin-dropdown-button .admin-button-content {
    min-width: 0;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 11px;
}

.admin-dropdown-arrow {
    width: 16px !important;
    height: 16px !important;
    color: #94a3b8;
    transition: transform .22s ease, color .16s ease;
}

.admin-dropdown.open .admin-dropdown-arrow {
    color: var(--as-primary);
    transform: rotate(180deg);
}

.admin-submenu {
    max-height: 0;
    padding-left: 20px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translateY(-5px);
    transition:
        max-height .24s ease,
        opacity .18s ease,
        visibility .24s ease,
        transform .24s ease,
        margin .24s ease;
}

.admin-dropdown.open .admin-submenu {
    max-height: 320px;
    margin: 3px 0 7px;
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translateY(0);
}

/* ======================================
   ADMIN SYSTEM SUMMARY
   Senior Friendly Update
====================================== */


.admin-system-summary {

    margin: 12px 5px 4px;

    padding: 14px;

    border: 1px solid #bfdbfe;

    border-radius: 14px;

    background: #f8fbff;

}



.admin-system-summary-title {

    margin-bottom: 10px;

    display: flex;

    align-items: center;

    gap: 8px;

    color: #1e3a8a;

    font-size: 13px;

    font-weight: 800;

}



.admin-system-summary-title svg {

    width: 18px;

    height: 18px;

}



.admin-summary-row {

    padding: 6px 0;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    color: #475569;

    font-size: 13px;

}



.admin-summary-row strong {

    color: #1e293b;

    font-size: 14px;

    font-weight: 800;

}

.admin-sidebar-footer {
    padding: 10px 10px 14px;
    flex: 0 0 auto;
    border-top: 1px solid #f1f5f9;
    background: #ffffff;
}

.admin-sidebar .logout-item {
    color: #dc2626;
}

.admin-sidebar .logout-item:hover {
    color: #b91c1c;
    border-color: #fecaca;
    background: #fef2f2;
}

.admin-sidebar.collapsed .sidebar-header {
    padding-inline: 16px;
    justify-content: center;
}

.admin-sidebar.collapsed .admin-sidebar-brand,
.admin-sidebar.collapsed .admin-profile-copy,
.admin-sidebar.collapsed .admin-menu-category,
.admin-sidebar.collapsed .admin-menu-text,
.admin-sidebar.collapsed .admin-menu-badge,
.admin-sidebar.collapsed .admin-dropdown-arrow,
.admin-sidebar.collapsed .admin-submenu,
.admin-sidebar.collapsed .admin-system-summary {
    display: none;
}

.admin-sidebar.collapsed .admin-profile-card {
    margin: 12px 8px;
    padding: 10px 5px;
    justify-content: center;
    border-color: transparent;
    background: transparent;
    box-shadow: none;
}

.admin-sidebar.collapsed .menu-item {
    padding-inline: 13px;
    justify-content: center;
}

.admin-sidebar.collapsed .menu-item:hover {
    transform: none;
}

.admin-sidebar-overlay {
    position: fixed;
    inset: 0;
    z-index: 999;
    display: none;
    background: rgba(15, 23, 42, .50);
    backdrop-filter: blur(2px);
}

@media (max-width: 768px) {
    .sidebar.admin-sidebar {
        width: min(87vw, 310px);
        border-right: 0;
        transform: translateX(-105%);
        box-shadow: 18px 0 44px rgba(15, 23, 42, .22);
    }

    /* บนมือถือ: collapsed = เปิด Drawer */
    .sidebar.admin-sidebar.collapsed {
        width: min(87vw, 310px);
        transform: translateX(0);
    }

    /* ✅ Header กลับมาเป็นปกติ */
    .admin-sidebar.collapsed .sidebar-header {
        padding: 13px 15px;
        justify-content: flex-start;
    }

    /* ✅ คืน display ให้ถูกประเภท (แทน display:initial) */
    .admin-sidebar.collapsed .admin-sidebar-brand {
        display: block;
    }

    .admin-sidebar.collapsed .admin-menu-category {
        display: block;
    }

    .admin-sidebar.collapsed .admin-profile-copy {
        display: block;
    }

    .admin-sidebar.collapsed .admin-menu-text {
        display: block;
        /* อยู่ใน flex ใช้ block ได้ปกติ (fl:1 ทำงานต่อ) */
        flex: 1;
    }

    .admin-sidebar.collapsed .admin-dropdown-arrow {
        display: inline-flex;
    }

    .admin-sidebar.collapsed .admin-menu-badge {
        display: inline-flex;
    }

    /* ✅ สำคัญ! คืน submenu ที่เดิมโดน display:none ค้าง */
    .admin-sidebar.collapsed .admin-submenu {
        display: flex;
    }

    .admin-sidebar.collapsed .admin-system-summary {
        display: block;
    }

    /* Profile card กลับมาเต็มรูปแบบ */
    .admin-sidebar.collapsed .admin-profile-card {
        margin: 12px;
        padding: 12px;
        justify-content: flex-start;
        border-color: var(--as-border);
        background: linear-gradient(180deg, #ffffff, #f8fafc);
        box-shadow: 0 4px 14px rgba(15, 23, 42, .04);
    }

    .admin-sidebar.collapsed .menu-item {
        padding: 10px 11px;
        justify-content: flex-start;
    }

    .admin-sidebar-mobile-close {
        display: grid;
    }

    .admin-sidebar-overlay.visible {
        display: block;
    }
}

@media (prefers-reduced-motion: reduce) {

    .sidebar.admin-sidebar,
    .admin-sidebar .menu-item,
    .admin-sidebar .submenu-item,
    .admin-submenu {
        transition: none;
    }
}
</style>

<aside class="sidebar admin-sidebar" id="sidebar" aria-label="เมนูผู้ดูแลระบบ">
    <div class="sidebar-header">
        <span class="admin-sidebar-logo" aria-hidden="true">
            <i data-lucide="shield-check"></i>
        </span>

        <span class="admin-sidebar-brand">
            <strong>ระบบงานประชุมคณะ</strong>
            <span>ศูนย์ควบคุมผู้ดูแลระบบ</span>
        </span>

        <button type="button" class="admin-sidebar-mobile-close" id="adminSidebarClose" aria-label="ปิดเมนู"
            title="ปิดเมนู">
            <i data-lucide="x"></i>
        </button>
    </div>

    <button type="button" class="admin-profile-card"
        onclick="if (typeof openProfileModal === 'function') { openProfileModal(); }" title="เปิดข้อมูลโปรไฟล์"
        aria-label="เปิดโปรไฟล์ของ <?= $adminSidebarH($adminSidebarName) ?>"
        style="width:auto;text-align:left;cursor:pointer;">
        <span class="admin-avatar-wrap">
            <img src="<?= $adminSidebarH($adminSidebarAvatar) ?>"
                alt="รูปโปรไฟล์ของ <?= $adminSidebarH($adminSidebarName) ?>" class="admin-avatar"
                referrerpolicy="no-referrer"
                onerror="this.onerror=null;this.src='<?= $adminSidebarH($adminSidebarFallbackAvatar) ?>';">
        </span>

        <span class="admin-profile-copy">
            <span class="admin-profile-name" title="<?= $adminSidebarH($adminSidebarName) ?>">
                <?= $adminSidebarH($adminSidebarName) ?>
            </span>
            <span class="admin-profile-meta" title="<?= $adminSidebarH($adminSidebarMeta) ?>">
                <?= $adminSidebarH($adminSidebarMeta) ?>
            </span>
            <span class="admin-role-chip">
                <?= $adminSidebarH($adminSidebarRole) ?>
            </span>
        </span>
    </button>

    <nav class="sidebar-menu" aria-label="เมนูหลัก">
        <div class="admin-menu-category">ภาพรวม</div>

        <a href="<?= $adminSidebarH($adminSidebarBaseUrl . 'admin/index.php') ?>"
            class="menu-item<?= $adminSidebarIsActive('dashboard') ?>"
            <?= $adminSidebarPage === 'dashboard' ? 'aria-current="page"' : '' ?> title="หน้าแรกผู้ดูแลระบบ">
            <i data-lucide="layout-dashboard"></i>
            <span class="admin-menu-text">แดชบอร์ด</span>
        </a>

        <div class="admin-menu-category">จัดการระบบ</div>

        <div class="admin-dropdown<?= $adminSidebarSystemActive ? ' open' : '' ?>">
            <button type="button" class="menu-item admin-dropdown-button" data-admin-dropdown-button
                aria-expanded="<?= $adminSidebarSystemActive ? 'true' : 'false' ?>">
                <span class="admin-button-content">
                    <i data-lucide="settings-2"></i>
                    <span class="admin-menu-text">จัดการระบบ</span>
                </span>

                <i data-lucide="chevron-down" class="admin-dropdown-arrow"></i>
            </button>

            <div class="admin-submenu">
                <a href="<?= $adminSidebarH($adminSidebarBaseUrl . 'admin/users/edit_users.php') ?>"
                    class="submenu-item<?= $adminSidebarIsActive('users') ?>"
                    <?= $adminSidebarPage === 'users' ? 'aria-current="page"' : '' ?>
                    title="จัดการผู้ใช้งาน สิทธิ์ ตำแหน่ง และภาควิชา">
                    <i data-lucide="users-round"></i>
                    <span class="admin-menu-text">จัดการผู้ใช้งาน</span>

                    <?php if ($adminSidebarStats['pending_users'] > 0): ?>
                    <span class="admin-menu-badge"
                        title="มีผู้ใช้รออนุมัติ <?= (int) $adminSidebarStats['pending_users'] ?> รายการ">
                        <?= $adminSidebarH($adminSidebarBadge($adminSidebarStats['pending_users'])) ?>
                    </span>
                    <?php endif; ?>
                </a>

                <a href="<?= $adminSidebarH($adminSidebarBaseUrl . 'admin/meetings/.php') ?>"
                    class="submenu-item<?= $adminSidebarIsActive('meetings') ?>"
                    <?= $adminSidebarPage === 'meetings' ? 'aria-current="page"' : '' ?>
                    title="สร้าง แก้ไข เชิญสมาชิก และจัดทำรายงานประชุม">
                    <i data-lucide="calendar-cog"></i>
                    <span class="admin-menu-text">จัดการการประชุม</span>

                    <?php if ($adminSidebarStats['active_meetings'] > 0): ?>
                    <span class="admin-menu-badge info"
                        title="มีการประชุมที่ยังไม่ปิด <?= (int) $adminSidebarStats['active_meetings'] ?> รายการ">
                        <?= $adminSidebarH($adminSidebarBadge($adminSidebarStats['active_meetings'])) ?>
                    </span>
                    <?php endif; ?>
                </a>

                <a href="<?= $adminSidebarH($adminSidebarBaseUrl . 'admin/agenda/agendas.php') ?>"
                    class="submenu-item<?= $adminSidebarIsActive('agendas') ?>"
                    <?= $adminSidebarPage === 'agendas' ? 'aria-current="page"' : '' ?>
                    title="จัดการวาระการประชุมที่เสนอเข้ามา">
                    <i data-lucide="file-text"></i>
                    <span class="admin-menu-text">จัดการวาระ</span>

                    <?php if ($adminSidebarStats['pending_agendas'] > 0): ?>
                    <span class="admin-menu-badge"
                        title="มีวาระรอตรวจสอบ <?= (int) $adminSidebarStats['pending_agendas'] ?> รายการ">
                        <?= $adminSidebarH($adminSidebarBadge($adminSidebarStats['pending_agendas'])) ?>
                    </span>
                    <?php endif; ?>

                </a>
            </div>
        </div>

        <div class="admin-system-summary">
            <div class="admin-system-summary-title">
                <i data-lucide="activity"></i>
                <span>สถานะระบบประชุม</span>
            </div>

            <div class="admin-summary-row">
                <span>ผู้ใช้รออนุมัติ</span>
                <strong><?= (int) $adminSidebarStats['pending_users'] ?></strong>
            </div>

            <div class="admin-summary-row">
                <span>ประชุมที่ยังไม่ปิด</span>
                <strong><?= (int) $adminSidebarStats['active_meetings'] ?></strong>
            </div>

            <div class="admin-summary-row">
                <span>คำเชิญรอตอบรับ</span>
                <strong><?= (int) $adminSidebarStats['pending_invitations'] ?></strong>
            </div>
        </div>
    </nav>

    <div class="admin-sidebar-footer">
        <a href="<?= $adminSidebarH($adminSidebarBaseUrl . 'auth/logout.php') ?>" class="menu-item logout-item"
            onclick="return handleLogout(event)" title="ออกจากระบบ">
            <i data-lucide="log-out"></i>
            <span class="admin-menu-text">ออกจากระบบ</span>
        </a>
    </div>
</aside>

<div class="admin-sidebar-overlay" id="adminSidebarOverlay" aria-hidden="true"></div>

<script>
(function() {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('adminSidebarOverlay');
    const closeButton = document.getElementById('adminSidebarClose');
    const dropdownButtons = document.querySelectorAll('[data-admin-dropdown-button]');

    if (!sidebar || !overlay) {
        return;
    }

    function isMobile() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function syncOverlay() {
        const visible = isMobile() && sidebar.classList.contains('collapsed');

        overlay.classList.toggle('visible', visible);
        overlay.setAttribute('aria-hidden', visible ? 'false' : 'true');
        document.body.style.overflow = visible ? 'hidden' : '';
    }

    function closeMobileSidebar() {
        if (!isMobile()) {
            return;
        }

        sidebar.classList.remove('collapsed');

        document.getElementById('main-content')?.classList.remove('expanded');
        document.getElementById('mainContent')?.classList.remove('expanded');

        syncOverlay();
    }

    function toggleDropdown(button) {
        const dropdown = button.closest('.admin-dropdown');

        if (!dropdown) {
            return;
        }

        const isOpen = dropdown.classList.toggle('open');
        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    dropdownButtons.forEach((button) => {
        button.addEventListener('click', () => toggleDropdown(button));
    });

    /*
     * คงชื่อฟังก์ชันเดิมไว้ เผื่อหน้าเก่ายังเรียกใช้งานผ่าน onclick
     */
    window.toggleSidebarDropdown = function(button, event) {
        event?.preventDefault();

        const target = button?.matches?.('[data-admin-dropdown-button]') ?
            button :
            button?.closest?.('[data-admin-dropdown-button]');

        if (target) {
            toggleDropdown(target);
        }
    };

    overlay.addEventListener('click', closeMobileSidebar);
    closeButton?.addEventListener('click', closeMobileSidebar);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMobileSidebar();
        }
    });

    document.querySelectorAll('.admin-sidebar a').forEach((link) => {
        link.addEventListener('click', () => {
            if (isMobile()) {
                setTimeout(closeMobileSidebar, 50);
            }
        });
    });

    const observer = new MutationObserver(syncOverlay);
    observer.observe(sidebar, {
        attributes: true,
        attributeFilter: ['class']
    });

    window.addEventListener('resize', syncOverlay);

    syncOverlay();

    if (window.lucide) {
        window.lucide.createIcons();
    }

    document.addEventListener('DOMContentLoaded', () => {
        syncOverlay();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    });
})();
</script>