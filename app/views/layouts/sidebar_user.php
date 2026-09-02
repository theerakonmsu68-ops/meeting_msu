<?php
// 👤 ดึงข้อมูลผู้ใช้ล่าสุดจาก Session และฐานข้อมูล
$sidebar_user_id = (int)($_SESSION['user_id'] ?? 0);

$sidebar_profile = [
    'name' => (string)($_SESSION['name'] ?? $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'General User'),
    'picture' => (string)($_SESSION['picture'] ?? ''),
    'role_id' => (int)($_SESSION['role_id'] ?? 2),
    'role_name' => (string)($_SESSION['role_name'] ?? ''),
];

$sidebar_db = null;
if (isset($db) && $db instanceof PDO) {
    $sidebar_db = $db;
} elseif (isset($pdo) && $pdo instanceof PDO) {
    $sidebar_db = $pdo;
} else {
    try {
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        if (class_exists('Database')) {
            $sidebar_db = (new Database())->connect();
        }
    } catch (Throwable $e) {
        $sidebar_db = null;
    }
}

if ($sidebar_user_id > 0 && $sidebar_db instanceof PDO) {
    try {
        $sidebar_stmt = $sidebar_db->prepare(
            "SELECT u.name, u.picture, u.role_id, COALESCE(r.role_name, '') AS role_name
             FROM user u
             LEFT JOIN role r ON r.role_id = u.role_id
             WHERE u.user_id = ?
             LIMIT 1"
        );
        $sidebar_stmt->execute([$sidebar_user_id]);
        $sidebar_db_profile = $sidebar_stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($sidebar_db_profile)) {
            $sidebar_profile = array_merge($sidebar_profile, $sidebar_db_profile);
        }
    } catch (Throwable $e) {
        // ใช้ Session ต่อ
    }
}

$sidebar_name = trim((string)($sidebar_profile['name'] ?? '')) ?: 'General User';
$role_id = (int)($sidebar_profile['role_id'] ?? 2);

if (!empty($sidebar_profile['role_name'])) {
    $user_role_label = (string)$sidebar_profile['role_name'];
} elseif ($role_id === 1) {
    $user_role_label = 'ผู้ดูแลระบบ';
} elseif ($role_id === 3) {
    $user_role_label = 'ผู้บริหาร';
} elseif ($role_id === 4) {
    $user_role_label = 'เจ้าหน้าที่ภาควิชา';
} else {
    $user_role_label = 'สมาชิก / กรรมการ';
}

$sidebar_public_base = defined('BASE_URL')
    ? rtrim((string)BASE_URL, '/') . '/'
    : '/public/';

$sidebar_picture = trim((string)($sidebar_profile['picture'] ?? ''));
$sidebar_avatar = '';

if ($sidebar_picture !== '') {
    if (filter_var($sidebar_picture, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string)parse_url($sidebar_picture, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            $sidebar_avatar = $sidebar_picture;
        }
    } else {
        $picture_file = basename(str_replace('\\', '/', $sidebar_picture));
        if ($picture_file !== '' && $picture_file !== '.' && $picture_file !== '..') {
            $sidebar_avatar = $sidebar_public_base
                . 'uploads/avatars/'
                . rawurlencode($picture_file)
                . '?v=' . rawurlencode((string) @filemtime(__DIR__ . '/../../../public/uploads/avatars/' . $picture_file));
        }
    }
}

if ($sidebar_avatar === '') {
    $initial = function_exists('mb_substr')
        ? mb_substr($sidebar_name, 0, 1, 'UTF-8')
        : substr($sidebar_name, 0, 1);

    $initial_xml = htmlspecialchars($initial ?: 'U', ENT_QUOTES | ENT_XML1, 'UTF-8');
    $fallback_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="110" height="110">'
        . '<rect width="110" height="110" rx="55" fill="#2563eb"/>'
        . '<text x="55" y="63" text-anchor="middle" font-family="Tahoma,Arial,sans-serif" '
        . 'font-size="46" font-weight="700" fill="#ffffff">' . $initial_xml . '</text></svg>';

    $sidebar_avatar = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($fallback_svg);
}

// 📌 ล็อกค่าเพื่อแสดงแถบสีน้ำเงินในเมนูที่กำลังเปิดอยู่
$current_page = $current_page ?? '';
$sidebar_collapsed = isset($_COOKIE['meeting_sidebar_collapsed']) && $_COOKIE['meeting_sidebar_collapsed'] === '1';
?>

<style>
    /* 📁 สไตล์และระเบียบมิติของ Sidebar ยูสเซอร์ */
    .sidebar-wrapper {
        width: 260px;
        height: 100vh;
        background-color: #ffffff;
        border-right: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 99;
        transition: all 0.3s ease;
    }

    /* คลาสคำสั่งควบคุมสเกลเมื่อกดหดเมนู */
    .sidebar-wrapper.collapsed {
        width: 70px;
    }

    /* ส่วนหัวโชว์โปรไฟล์จริง */
    .sidebar-profile {
        padding: 24px 16px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 12px;
        overflow: hidden;
    }

    .sidebar-wrapper.collapsed .sidebar-profile {
        padding: 24px 12px;
        justify-content: center;
    }

    .profile-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #eff6ff;
        flex-shrink: 0;
    }

    .profile-info {
        display: flex;
        flex-direction: column;
        white-space: nowrap;
    }

    .sidebar-wrapper.collapsed .profile-info {
        display: none;
    }

    .profile-name {
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
        max-width: 160px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .profile-role {
        font-size: 12px;
        color: #64748b;
    }

    /* จัดสัดส่วนลิงก์เมนูภายในแถบข้าง */
    .sidebar-menu {
        flex: 1;
        padding: 16px 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .menu-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        color: #475569;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .menu-item:hover {
        background-color: #f1f5f9;
        color: #1e293b;
    }

    .menu-item.active {
        background-color: #eff6ff;
        color: #2563eb;
    }

    .menu-item svg {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
    }

    .sidebar-wrapper.collapsed .menu-text {
        display: none;
    }

    /* โซนล่างสุดสำหรับออกจากระบบ */
    .sidebar-footer {
        padding: 16px 12px;
        border-top: 1px solid #f1f5f9;
    }

    .btn-logout {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        font-size: 14px;
        font-weight: 500;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.2s ease;
        white-space: nowrap;
        color: #ef4444;
    }

    .btn-logout:hover {
        background-color: #fef2f2;
        color: #b91c1c;
    }

    @media (max-width: 768px) {
        .sidebar-wrapper {
            transform: translateX(-100%);
            width: 260px;
            box-shadow: 10px 0 30px rgba(15, 23, 42, 0.08);
        }
        .sidebar-wrapper.collapsed {
            width: 260px;
            transform: translateX(0);
        }
        .sidebar-wrapper.collapsed .profile-info,
        .sidebar-wrapper.collapsed .menu-text {
            display: flex;
        }
        .sidebar-wrapper.collapsed .sidebar-profile {
            justify-content: flex-start;
            padding: 24px 16px;
        }
    }
</style>

<div class="sidebar-wrapper<?= $sidebar_collapsed ? ' collapsed' : '' ?>" id="sidebar">
    
    <div class="sidebar-profile">
        <img src="<?= htmlspecialchars($sidebar_avatar) ?>" alt="User Avatar" class="profile-avatar">
        <div class="profile-info">
            <span class="profile-name" title="<?= htmlspecialchars($sidebar_name) ?>">
                <?= htmlspecialchars($sidebar_name) ?>
            </span>
            <span class="profile-role"><?= htmlspecialchars($user_role_label) ?></span>
        </div>
    </div>

    <nav class="sidebar-menu">
        <a href="<?= BASE_URL ?>users/index.php" class="menu-item <?= $current_page == 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i>
            <span class="menu-text">หน้าแรก / การประชุม</span>
        </a>

        <a href="<?= BASE_URL ?>users/calendar.php" class="menu-item <?= $current_page == 'calendar' ? 'active' : '' ?>">
            <i data-lucide="calendar-days"></i>
            <span class="menu-text">ปฏิทินการประชุม</span>
        </a>

        <a href="<?= BASE_URL ?>users/meeting_history.php" class="menu-item <?= $current_page == 'history' ? 'active' : '' ?>">
            <i data-lucide="history"></i>
            <span class="menu-text">ประวัติการเข้าประชุม</span>
        </a>

        <a href="javascript:void(0);" onclick="if (typeof openProfileModal === 'function') { openProfileModal(); }"
            class="menu-item <?= $current_page == 'profile' ? 'active' : '' ?>">
            <i data-lucide="user-cog"></i>
            <span class="menu-text">ตั้งค่าโปรไฟล์</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>auth/logout.php" class="btn-logout"
            onclick="return handleLogout(event)">
            <i data-lucide="log-out"></i>
            <span class="menu-text">ออกจากระบบ</span>
        </a>
    </div>
</div>

<script>
    // จัดการสถานะย่อ/ขยาย Sidebar กลาง เพื่อให้ทุกหน้าทำงานเหมือนกัน
    window.toggleUserSidebar = function () {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        if (!sidebar) return;

        sidebar.classList.toggle('collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');

        if (mainContent) {
            mainContent.classList.toggle('expanded', isCollapsed);
        }

        document.cookie = 'meeting_sidebar_collapsed=' + (isCollapsed ? '1' : '0') + '; path=/; max-age=31536000; SameSite=Lax';
        window.dispatchEvent(new CustomEvent('meetingSidebarToggled', { detail: { collapsed: isCollapsed } }));
    };

    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        if (sidebar && mainContent) {
            mainContent.classList.toggle('expanded', sidebar.classList.contains('collapsed'));
        }

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>