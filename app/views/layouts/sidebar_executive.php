<?php
/* ============================================================
 * Executive Sidebar
 * Role ID: 3
 * ใช้ร่วมกับ profile_modal.php กลาง
 * ============================================================ */

$execSidebarUserId = (int)($_SESSION['user_id'] ?? 0);
$execSidebarPage = (string)($current_page ?? 'dashboard');

$execSidebarBaseUrl = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '/Meeting_msu/';

$execH = static fn($value): string =>
htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');

$execProfile = [
    'name' => (string)($_SESSION['name'] ?? $_SESSION['fullname'] ?? 'ผู้บริหาร'),
    'email' => (string)($_SESSION['email'] ?? ''),
    'picture' => (string)($_SESSION['picture'] ?? ''),
    'role_name' => (string)($_SESSION['role_name'] ?? 'ผู้บริหาร'),
    'position_name' => (string)($_SESSION['position_name'] ?? ''),
    'department_name' => (string)($_SESSION['department_name'] ?? ''),
];

$execStats = [
    'today' => 0,
    'ongoing' => 0,
    'upcoming' => 0,
    'calendar' => 0,
];

$execDb = null;
if (isset($db) && $db instanceof PDO) {
    $execDb = $db;
} elseif (isset($pdo) && $pdo instanceof PDO) {
    $execDb = $pdo;
} else {
    try {
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        if (class_exists('Database')) {
            $execDb = (new Database())->connect();
        }
    } catch (Throwable $e) {
        $execDb = null;
    }
}

if ($execDb instanceof PDO) {
    try {
        $stmt = $execDb->prepare(
            "SELECT
                u.name,
                u.email,
                u.picture,
                COALESCE(r.role_name, 'ผู้บริหาร') AS role_name,
                COALESCE(p.position_name, '') AS position_name,
                COALESCE(d.department_name, '') AS department_name
             FROM user u
             LEFT JOIN role r ON r.role_id = u.role_id
             LEFT JOIN positions p ON p.position_id = u.position_id
             LEFT JOIN departments d ON d.department_id = u.department_id
             WHERE u.user_id = ?
             LIMIT 1"
        );
        $stmt->execute([$execSidebarUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $execProfile = array_merge($execProfile, $row);
        }
    } catch (Throwable $e) {
        // fallback to session
    }

    try {
        $stmtToday = $execDb->prepare(
            "SELECT COUNT(*)
     FROM meeting_attendance ma
     INNER JOIN meeting m 
        ON m.meeting_id = ma.meeting_id
     WHERE ma.user_id = ?
       AND m.meeting_date = CURDATE()
       AND ma.rsvp_status = 'pending'
       AND ma.attendance_status = 'pending'"
        );

        $stmtToday->execute([$execSidebarUserId]);

        $execStats['today'] = (int)$stmtToday->fetchColumn();
        $execStats['ongoing'] = (int)$execDb
            ->query("SELECT COUNT(*) FROM meeting WHERE meeting_status = 'ongoing'")
            ->fetchColumn();
        $stmtCalendar = $execDb->prepare(
            "SELECT COUNT(*)
     FROM meeting_attendance ma
     INNER JOIN meeting m 
        ON m.meeting_id = ma.meeting_id
     WHERE ma.user_id = ?
       AND ma.rsvp_status = 'attending'
       AND m.meeting_date >= CURDATE()"
        );

        $stmtCalendar->execute([$execSidebarUserId]);

        $execStats['calendar'] = (int)$stmtCalendar->fetchColumn();
    } catch (Throwable $e) {
        // no badges if unavailable
    }
}

$execName = trim((string)$execProfile['name']) ?: 'ผู้บริหาร';
$execRole = trim((string)$execProfile['role_name']) ?: 'ผู้บริหาร';
$execPosition = trim((string)$execProfile['position_name']);
$execDepartment = trim((string)$execProfile['department_name']);

$execMeta = implode(' · ', array_values(array_filter(
    [$execPosition, $execDepartment],
    static fn($v): bool => trim((string)$v) !== ''
)));
if ($execMeta === '') {
    $execMeta = trim((string)$execProfile['email']) ?: 'ผู้บริหารระบบงานประชุม';
}

$execInitial = function_exists('mb_substr')
    ? mb_substr($execName, 0, 1, 'UTF-8')
    : substr($execName, 0, 1);
$execInitialXml = htmlspecialchars($execInitial ?: 'E', ENT_QUOTES | ENT_XML1, 'UTF-8');

$execFallbackSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">'
    . '<rect width="120" height="120" rx="60" fill="#7c3aed"/>'
    . '<text x="60" y="67" text-anchor="middle" '
    . 'font-family="Tahoma,Arial,sans-serif" font-size="52" font-weight="700" fill="#ffffff">'
    . $execInitialXml
    . '</text></svg>';

$execFallbackAvatar = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($execFallbackSvg);

$execPicture = trim((string)($execProfile['picture'] ?? ''));
$execAvatar = '';

/* BASE_URL ชี้ไป public อยู่แล้ว */
$execPublicBase = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '/Meeting_msu/public/';

if ($execPicture !== '') {
    if (filter_var($execPicture, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string)parse_url($execPicture, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            $execAvatar = $execPicture;
        }
    } else {
        $normalized = str_replace('\\', '/', $execPicture);
        $pictureFile = basename($normalized);

        if ($pictureFile !== '' && $pictureFile !== '.' && $pictureFile !== '..') {
            $execAvatar = $execPublicBase
                . 'uploads/avatars/'
                . rawurlencode($pictureFile)
                . '?v=' . rawurlencode((string) @filemtime(__DIR__ . '/../../../public/uploads/avatars/' . $pictureFile));
        }
    }
}

if ($execAvatar === '') {
    $execAvatar = $execFallbackAvatar;
}

$execActive = static function (string $page) use ($execSidebarPage): string {
    return $execSidebarPage === $page ? ' active' : '';
};

$execBadge = static function (int $value): string {
    return $value > 99 ? '99+' : (string)$value;
};
?>

<style>
    :root {
        --es-primary: #7c3aed;
        --es-primary-dark: #6d28d9;
        --es-primary-soft: #f5f3ff;
        --es-border: #e2e8f0;
        --es-text: #0f172a;
        --es-muted: #64748b;
        --es-danger: #dc2626;
    }

    .sidebar.executive-sidebar,
    .sidebar.executive-sidebar * {
        box-sizing: border-box;
    }

    .sidebar.executive-sidebar {
        width: 268px;
        height: 100vh;
        position: fixed;
        inset: 0 auto 0 0;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border-right: 1px solid var(--es-border);
        background: #fff;
        transition: width .26s ease, transform .26s ease, box-shadow .26s ease;
    }

    .sidebar.executive-sidebar.collapsed {
        width: 74px;
    }

    .exec-sidebar-header {
        min-height: 70px;
        padding: 13px 15px;
        display: flex;
        align-items: center;
        gap: 11px;
        flex: 0 0 auto;
        overflow: hidden;
        color: #fff;
        background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 60%, #a78bfa 100%);
    }

    .exec-brand-icon {
        width: 41px;
        height: 41px;
        flex: 0 0 auto;
        display: grid;
        place-items: center;
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: 13px;
        background: rgba(255, 255, 255, .14);
    }

    .exec-brand-icon svg {
        width: 22px;
        height: 22px;
    }

    .exec-brand-copy {
        min-width: 0;
        line-height: 1.25;
        white-space: nowrap;
    }

    .exec-brand-copy strong,
    .exec-brand-copy span {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .exec-brand-copy strong {
        font-size: 14px;
        font-weight: 800;
    }

    .exec-brand-copy span {
        margin-top: 3px;
        color: rgba(255, 255, 255, .74);
        font-size: 10.5px;
    }

    .exec-mobile-close {
        width: 32px;
        height: 32px;
        margin-left: auto;
        flex: 0 0 auto;
        border: 0;
        border-radius: 9px;
        display: none;
        place-items: center;
        color: #fff;
        background: rgba(255, 255, 255, .13);
        cursor: pointer;
    }

    .exec-profile-card {
        width: auto;
        margin: 12px;
        padding: 12px;
        min-height: 70px;
        display: flex;
        align-items: center;
        gap: 11px;
        overflow: hidden;
        border: 1px solid var(--es-border);
        border-radius: 15px;
        color: inherit;
        background: linear-gradient(180deg, #fff, #f8fafc);
        box-shadow: 0 4px 14px rgba(15, 23, 42, .04);
        text-align: left;
        cursor: pointer;
    }

    .exec-profile-card:hover {
        border-color: #ddd6fe;
        background: #fbfaff;
    }

    .exec-avatar {
        width: 44px;
        height: 44px;
        display: block;
        object-fit: cover;
        border: 3px solid #fff;
        border-radius: 50%;
        background: #ede9fe;
        box-shadow: 0 0 0 2px #ddd6fe;
        flex: 0 0 auto;
    }

    .exec-profile-copy {
        min-width: 0;
        flex: 1;
    }

    .exec-profile-name,
    .exec-profile-meta {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .exec-profile-name {
        color: var(--es-text);
        font-size: 13.5px;
        font-weight: 750;
    }

    .exec-profile-meta {
        margin-top: 3px;
        color: var(--es-muted);
        font-size: 10.5px;
    }

    .exec-role-chip {
        max-width: 100%;
        margin-top: 6px;
        padding: 3px 7px;
        display: inline-flex;
        overflow: hidden;
        border-radius: 999px;
        color: var(--es-primary-dark);
        background: var(--es-primary-soft);
        font-size: 9.5px;
        font-weight: 750;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .executive-sidebar .sidebar-menu {
        min-height: 0;
        flex: 1;
        padding: 2px 10px 13px;
        overflow-x: hidden;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .exec-menu-category {
        padding: 14px 10px 6px;
        color: #94a3b8;
        font-size: 9.5px;
        font-weight: 850;
        letter-spacing: .055em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .executive-sidebar .menu-item {
        position: relative;
        width: 100%;
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
        font-weight: 650;
        white-space: nowrap;
        cursor: pointer;
        transition: color .16s ease, background .16s ease, border-color .16s ease, transform .16s ease;
    }

    .executive-sidebar .menu-item:hover {
        color: var(--es-text);
        border-color: #f1f5f9;
        background: #fafafa;
        transform: translateX(2px);
    }

    .executive-sidebar .menu-item.active {
        color: var(--es-primary-dark);
        border-color: #ddd6fe;
        background: var(--es-primary-soft);
    }

    .executive-sidebar .menu-item.active::before {
        content: "";
        position: absolute;
        left: -10px;
        top: 8px;
        bottom: 8px;
        width: 3px;
        border-radius: 0 4px 4px 0;
        background: var(--es-primary);
    }

    .executive-sidebar .menu-item>svg,
    .executive-sidebar .menu-item>i {
        width: 19px;
        height: 19px;
        flex: 0 0 auto;
    }

    .exec-menu-text {
        min-width: 0;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .exec-menu-badge {
        min-width: 21px;
        height: 21px;
        padding: 0 6px;
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        color: #fff;
        background: var(--es-primary);
        font-size: 9px;
        font-weight: 850;
        line-height: 1;
    }

    .exec-menu-badge.warning {
        background: #f59e0b;
    }

    .exec-menu-badge.success {
        background: #16a34a;
    }

    .exec-sidebar-footer {
        padding: 10px 10px 14px;
        flex: 0 0 auto;
        border-top: 1px solid #f1f5f9;
        background: #fff;
    }

    .executive-sidebar .logout-item {
        color: var(--es-danger);
    }

    .executive-sidebar .logout-item:hover {
        color: #b91c1c;
        border-color: #fecaca;
        background: #fef2f2;
    }

    .executive-sidebar.collapsed .exec-sidebar-header {
        padding-inline: 16px;
        justify-content: center;
    }

    .executive-sidebar.collapsed .exec-brand-copy,
    .executive-sidebar.collapsed .exec-profile-copy,
    .executive-sidebar.collapsed .exec-menu-category,
    .executive-sidebar.collapsed .exec-menu-text,
    .executive-sidebar.collapsed .exec-menu-badge {
        display: none;
    }

    .executive-sidebar.collapsed .exec-profile-card {
        margin: 12px 8px;
        padding: 10px 5px;
        justify-content: center;
        border-color: transparent;
        background: transparent;
        box-shadow: none;
    }

    .executive-sidebar.collapsed .menu-item {
        padding-inline: 13px;
        justify-content: center;
    }

    .executive-sidebar.collapsed .menu-item:hover {
        transform: none;
    }

    .exec-sidebar-overlay {
        position: fixed;
        inset: 0;
        z-index: 999;
        display: none;
        background: rgba(15, 23, 42, .50);
        backdrop-filter: blur(2px);
    }

    @media (max-width: 768px) {
        .sidebar.executive-sidebar {
            width: min(87vw, 310px);
            border-right: 0;
            transform: translateX(-105%);
            box-shadow: 18px 0 44px rgba(15, 23, 42, .22);
        }

        .sidebar.executive-sidebar.collapsed {
            width: min(87vw, 310px);
            transform: translateX(0);
        }

        .executive-sidebar.collapsed .exec-sidebar-header {
            padding: 13px 15px;
            justify-content: flex-start;
        }

        .executive-sidebar.collapsed .exec-brand-copy,
        .executive-sidebar.collapsed .exec-menu-category,
        .executive-sidebar.collapsed .exec-profile-copy,
        .executive-sidebar.collapsed .exec-menu-text {
            display: block;
        }

        .executive-sidebar.collapsed .exec-menu-badge {
            display: inline-flex;
        }

        .executive-sidebar.collapsed .exec-profile-card {
            margin: 12px;
            padding: 12px;
            justify-content: flex-start;
            border-color: var(--es-border);
            background: linear-gradient(180deg, #fff, #f8fafc);
            box-shadow: 0 4px 14px rgba(15, 23, 42, .04);
        }

        .executive-sidebar.collapsed .menu-item {
            padding: 10px 11px;
            justify-content: flex-start;
        }

        .exec-mobile-close {
            display: grid;
        }

        .exec-sidebar-overlay.visible {
            display: block;
        }
    }
</style>

<aside class="sidebar executive-sidebar" id="sidebar" aria-label="เมนูผู้บริหาร">
    <div class="exec-sidebar-header">
        <span class="exec-brand-icon" aria-hidden="true">
            <i data-lucide="landmark"></i>
        </span>

        <span class="exec-brand-copy">
            <strong>ระบบงานประชุมคณะ</strong>
            <span>ศูนย์ข้อมูลสำหรับผู้บริหาร</span>
        </span>

        <button type="button" class="exec-mobile-close" id="execSidebarClose" aria-label="ปิดเมนู" title="ปิดเมนู">
            <i data-lucide="x"></i>
        </button>
    </div>

    <button type="button" class="exec-profile-card"
        onclick="if (typeof openProfileModal === 'function') { openProfileModal(); }" title="เปิดข้อมูลโปรไฟล์"
        aria-label="เปิดโปรไฟล์ของ <?= $execH($execName) ?>">
        <img src="<?= $execH($execAvatar) ?>" alt="รูปโปรไฟล์ของ <?= $execH($execName) ?>" class="exec-avatar"
            referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='<?= $execH($execFallbackAvatar) ?>';">

        <span class="exec-profile-copy">
            <span class="exec-profile-name"><?= $execH($execName) ?></span>
            <span class="exec-profile-meta"><?= $execH($execMeta) ?></span>
            <span class="exec-role-chip"><?= $execH($execRole) ?></span>
        </span>
    </button>

    <nav class="sidebar-menu" aria-label="เมนูหลัก">
        <div class="exec-menu-category">ภาพรวม</div>

        <a href="<?= $execH($execSidebarBaseUrl . 'executives/index.php') ?>"
            class="menu-item<?= $execActive('dashboard') ?>">
            <i data-lucide="layout-dashboard"></i>
            <span class="exec-menu-text">ศูนย์ควบคุมงานประชุมผู้บริหาร</span>
            <?php if ($execStats['today'] > 0): ?>
                <span class="exec-menu-badge success"
                    title="ประชุมวันนี้"><?= $execH($execBadge($execStats['today'])) ?></span>
            <?php endif; ?>
        </a>

        <a href="<?= $execH($execSidebarBaseUrl . 'executives/calendar.php') ?>"
            class="menu-item<?= $execActive('calendar') ?>">
            <i data-lucide="calendar-days"></i>

            <span class="exec-menu-text">ปฏิทินการประชุม</span>

            <?php if ($execStats['calendar'] > 0): ?>
                <span class="exec-menu-badge success">
                    <?= $execH($execBadge($execStats['calendar'])) ?>
                </span>
            <?php endif; ?>

        </a>

        <a href="<?= $execH($execSidebarBaseUrl . 'executives/meeting_history.php') ?>"
            class="menu-item<?= $execActive('history') ?>">
            <i data-lucide="history"></i>
            <span class="exec-menu-text">ประวัติการประชุม</span>
        </a>

        <div class="exec-menu-category">ข้อมูลเพื่อการบริหาร</div>

        <!-- <a href="<?= $execH($execSidebarBaseUrl . 'executives/resolutions.php') ?>"
           class="menu-item<?= $execActive('resolutions') ?>">
            <i data-lucide="gavel"></i>
            <span class="exec-menu-text">มติและผลการประชุม</span>
        </a> -->

        <a href="<?= $execH($execSidebarBaseUrl . 'executives/documents.php') ?>"
            class="menu-item<?= $execActive('documents') ?>">
            <i data-lucide="folder-open"></i>
            <span class="exec-menu-text">เอกสารการประชุม</span>
        </a>

        <div class="exec-menu-category">บัญชีผู้ใช้งาน</div>

        <a href="javascript:void(0)" onclick="if (typeof openProfileModal === 'function') { openProfileModal(); }"
            class="menu-item<?= $execActive('profile') ?>">
            <i data-lucide="user-cog"></i>
            <span class="exec-menu-text">ข้อมูลและโปรไฟล์</span>
        </a>
    </nav>

    <div class="exec-sidebar-footer">
        <a href="<?= $execH($execSidebarBaseUrl . 'auth/logout.php') ?>" class="menu-item logout-item"
            onclick="return handleLogout(event)">
            <i data-lucide="log-out"></i>
            <span class="exec-menu-text">ออกจากระบบ</span>
        </a>
    </div>
</aside>

<div class="exec-sidebar-overlay" id="execSidebarOverlay" aria-hidden="true"></div>

<script>
    (function() {
        'use strict';

        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('execSidebarOverlay');
        const closeButton = document.getElementById('execSidebarClose');

        if (!sidebar || !overlay) return;

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
            if (!isMobile()) return;
            sidebar.classList.remove('collapsed');
            document.getElementById('main-content')?.classList.remove('expanded');
            document.getElementById('mainContent')?.classList.remove('expanded');
            syncOverlay();
        }

        overlay.addEventListener('click', closeMobileSidebar);
        closeButton?.addEventListener('click', closeMobileSidebar);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeMobileSidebar();
        });

        const observer = new MutationObserver(syncOverlay);
        observer.observe(sidebar, {
            attributes: true,
            attributeFilter: ['class']
        });

        window.addEventListener('resize', syncOverlay);
        syncOverlay();

        if (window.lucide) window.lucide.createIcons();
    })();
</script>