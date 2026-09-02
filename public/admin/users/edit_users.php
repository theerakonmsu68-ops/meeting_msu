<?php
/* ===============================
🔐 AUTH & SECURITY
=============================== */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/models/User.php';
require_once __DIR__ . '/../../../app/controllers/UserController.php';
require_once __DIR__ . '/../../../app/helpers/view_helper.php';

function getRoleName($role_id)
{
    return match ((int) $role_id) {
        1 => "ผู้ดูแลระบบ",
        2 => "ผู้ใช้งาน",
        3 => "ผู้บริหาร",
        4 => "ภาควิชา",
        default => "ไม่ระบุ"
    };
}



$db = (new Database())->connect();
$userModel = new User($db);
$controller = new UserController($userModel);

$limit = 9;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_role = $_GET['role'] ?? '';
$filter_status = $_GET['status'] ?? '';

function buildSearchQuery($search, $filter_role = '', $filter_status = '')
{

    $where = [];
    $params = [];


    if ($search !== '') {

        $where[] = "
        (
            u.username LIKE :search
            OR u.name LIKE :search
            OR u.email LIKE :search
            OR d.department_name LIKE :search
        )
        ";

        $params[':search'] = '%' . $search . '%';
    }



    if ($filter_role !== '') {

        $where[] = "u.role_id = :role";

        $params[':role'] = $filter_role;
    }



    if ($filter_status !== '') {

        $where[] = "u.status = :status";

        $params[':status'] = $filter_status;
    }



    if (count($where) > 0) {

        return [
            " WHERE " . implode(" AND ", $where),
            $params
        ];
    }


    return ["", $params];
}

list($where_clause, $params) = buildSearchQuery(
    $search,
    $filter_role,
    $filter_status
);

// --- [AJAX Live Search] ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $count_query = "SELECT COUNT(*) FROM user u LEFT JOIN departments d ON u.department_id = d.department_id" . $where_clause;
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    $query = "SELECT u.*, d.department_name FROM user u LEFT JOIN departments d ON u.department_id = d.department_id " . $where_clause . " ORDER BY u.user_id DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<table>
    <thead>
        <tr>
            <th style="width: 80px;">ลำดับ</th>
            <th>รูปโปรไฟล์</th>
            <th>Username</th>
            <th>ชื่อ-นามสกุล</th>
            <th>อีเมล</th>
            <th>สิทธิ์การใช้งาน</th>
            <th>ภาควิชา</th>
            <th>สถานะ</th>
            <th>จัดการ</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($users)): ?>
        <?php $i = $offset + 1; ?>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= $i++ ?></td>
            <td>
                <?php if (!empty($u['picture'])): ?>
                <?php
                                // เช็คเงื่อนไขลิงก์รูปภาพของ Google
                                $imgUrl = (str_starts_with($u['picture'], 'http://') || str_starts_with($u['picture'], 'https://'))
                                    ? h($u['picture'])
                                    : "../../uploads/avatars/" . h($u['picture']);
                                ?>
                <img src="<?= $imgUrl ?>"
                    style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #cbd5e1;"
                    referrerpolicy="no-referrer">
                <?php else: ?>
                <div
                    style="width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #64748b; font-weight: 600;">
                    NO PIC</div>
                <?php endif; ?>
            </td>
            <td><b><?= h($u['username']) ?></b></td>
            <td><?= h($u['name']) ?></td>
            <td><?= h($u['email']) ?></td>
            <td><span class="role-badge"><?= getRoleName($u['role_id'] ?? 2) ?></span></td>
            <td><?= h($u['department_name'] ?? 'ไม่มีสังกัด') ?></td>
            <td>
                <?php
                            $st = $u['status'] ?? 'pending';
                            if ($st === 'active') echo '<span class="status-badge status-active">ใช้งาน</span>';
                            elseif ($st === 'inactive') echo '<span class="status-badge status-inactive">ไม่ใช้งาน</span>';
                            else echo '<span class="status-badge status-pending">รออนุมัติ</span>';
                            ?>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn-edit" onclick="editUser(<?= (int)$u['user_id'] ?>)">แก้ไข</button>
                    <button class="btn-delete" onclick="deleteUser(<?= (int)$u['user_id'] ?>)">ลบ</button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <tr>
            <td colspan="9" style="text-align: center; padding: 30px; color: #94a3b8;">
                ไม่พบข้อมูลผู้ใช้งานที่ตรงตามเงื่อนไข</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php if ($total_pages > 1): ?>
<div class="pagination-container">
    <?php
            $startPage = max(1, $page - 2);
            $endPage = min($total_pages, $page + 2);
            ?>
    <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>"
        class="pagination-link <?= $page <= 1 ? 'disabled' : '' ?>">ก่อนหน้า</a>

    <?php if ($startPage > 1): ?>
    <a href="?page=1&search=<?= urlencode($search) ?>" class="pagination-link">1</a>
    <?php if ($startPage > 2): ?><span class="pagination-link" style="pointer-events:none;">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"
        class="pagination-link <?= ($page == $i) ? 'active' : '' ?>">
        <?= $i ?>
    </a>
    <?php endfor; ?>

    <?php if ($endPage < $total_pages): ?>
    <?php if ($endPage < $total_pages - 1): ?><span class="pagination-link"
        style="pointer-events:none;">…</span><?php endif; ?>
    <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>" class="pagination-link"><?= $total_pages ?></a>
    <?php endif; ?>

    <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search) ?>"
        class="pagination-link <?= $page >= $total_pages ? 'disabled' : '' ?>">ถัดไป</a>
</div>
<?php endif; ?>
<?php
    exit;
}

// โหลดหน้าปกติ
$count_query = "SELECT COUNT(*) FROM user u LEFT JOIN departments d ON u.department_id = d.department_id" . $where_clause;
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

$query = "SELECT u.*, d.department_name FROM user u LEFT JOIN departments d ON u.department_id = d.department_id " . $where_clause . " ORDER BY u.user_id DESC LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dept_stmt = $db->prepare("SELECT * FROM departments ORDER BY department_name ASC");
$dept_stmt->execute();
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Dashboard - Admin";
$page_css = "user-management.css";
$page_js = [
    "sweetalert2.all.min.js",
    "user-management.js"
];
include_once __DIR__ . '/../../../app/views/layouts/header.php';

$current_page = 'users';
include_once __DIR__ . '/../../../app/views/layouts/sidebar_admin.php';
?>



<div class="main-content" id="mainContent">
    <header class="header">
        <div class="header-left">
            <button class="toggle-btn" id="toggle-sidebar"><i data-lucide="menu"></i></button>
            <h2>จัดการผู้ใช้งาน</h2>
        </div>
        <div class="header-right">
            <button class="btn-add" onclick="openCreate()"> <i data-lucide="plus"></i>เพิ่มผู้ใช้งาน</button>
        </div>
    </header>

    <main class="content-wrapper">
        <div class="filter-card">
            <h3>
                <i data-lucide="filter"></i>
                ค้นหาและตัวกรองผู้ใช้งาน
            </h3>
            <form method="GET" class="filter-form">
                <div class="form-group search-group">

                    <label>
                        คำค้นหา
                    </label>

                    <input type="text" name="search" class="form-control" value="<?= h($search) ?>"
                        placeholder="ชื่อ, ชื่อผู้ใช้งาน, อีเมล, ภาควิชา">

                </div>



                <div class="form-group">

                    <label>
                        สิทธิ์
                    </label>


                    <select name="role" class="form-control">

                        <option value="">
                            -- ทุกสิทธิ์ --
                        </option>


                        <option value="1" <?= $filter_role == '1' ? 'selected' : '' ?>>
                            Admin
                        </option>


                        <option value="2" <?= $filter_role == '2' ? 'selected' : '' ?>>
                            ผู้ใช้งานทั่วไป
                        </option>


                        <option value="3" <?= $filter_role == '3' ? 'selected' : '' ?>>
                            ผู้บริหาร
                        </option>


                        <option value="4" <?= $filter_role == '4' ? 'selected' : '' ?>>
                            ภาควิชา
                        </option>


                    </select>

                </div>
                <div class="form-group">

                    <label>
                        สถานะ
                    </label>


                    <select name="status" class="form-control">
                        <option value="">
                            -- ทุกสถานะ --
                        </option>
                        <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>
                            🟢 ใช้งาน
                        </option>
                        <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>
                            🟡 รออนุมัติ
                        </option>
                        <option value="inactive" <?= $filter_status == 'inactive' ? 'selected' : '' ?>>
                            ⚫ ระงับ
                        </option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-search">
                        <i data-lucide="search" style="width: 16px; height: 16px;"></i> ค้นหา
                    </button>
                    <!-- เปลี่ยน action clear ให้กลับมาหน้าปกติ (ไม่ต้องแนบ parameter) -->
                    <a href="edit_users.php" class="btn-clear">
                        <i data-lucide="rotate-ccw" style="width: 16px; height: 16px;"></i> ล้างค่า
                    </a>
                </div>
            </form>
        </div>
        <div class="table-card" id="tableContainer"></div>
        <div class="table-card" id="tableContainer">
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">ลำดับ</th>
                        <th>รูปโปรไฟล์</th>
                        <th>Username</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>อีเมล</th>
                        <th>สิทธิ์การใช้งาน</th>
                        <th>ภาควิชา</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                    <?php $i = $offset + 1; ?>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td>
                            <?php if (!empty($u['picture'])): ?>
                            <?php
                                        $imgUrl = (str_starts_with($u['picture'], 'http://') || str_starts_with($u['picture'], 'https://'))
                                            ? h($u['picture'])
                                            : "../../uploads/avatars/" . h($u['picture']);
                                        ?>
                            <img src="<?= $imgUrl ?>"
                                style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #cbd5e1;"
                                referrerpolicy="no-referrer">
                            <?php else: ?>
                            <div
                                style="width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #64748b; font-weight: 600;">
                                NO PIC</div>
                            <?php endif; ?>
                        </td>
                        <td><b><?= h($u['username']) ?></b></td>
                        <td><?= h($u['name']) ?></td>
                        <td><?= h($u['email']) ?></td>
                        <td>
                            <?php
                                    $roleId = (int)($u['role_id'] ?? 2);
                                    $roleClass = match ($roleId) {
                                        1 => 'role-admin',
                                        2 => 'role-member',
                                        3 => 'role-executive',
                                        4 => 'role-department',
                                        default => 'role-member'
                                    };
                                    ?>
                            <span class="role-badge <?= $roleClass ?>"><?= getRoleName($roleId) ?></span>
                        </td>
                        <td><?= h($u['department_name'] ?? 'ไม่มีสังกัด') ?></td>
                        <td>
                            <?php
                                    $st = $u['status'] ?? 'pending';
                                    if ($st === 'active') echo '<span class="status-badge status-active">ใช้งาน</span>';
                                    elseif ($st === 'inactive') echo '<span class="status-badge status-inactive">ไม่ใช้งาน</span>';
                                    else echo '<span class="status-badge status-pending">รออนุมัติ</span>';
                                    ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-edit" onclick="editUser(<?= (int)$u['user_id'] ?>)">แก้ไข</button>
                                <button class="btn-delete" onclick="deleteUser(<?= (int)$u['user_id'] ?>)">ลบ</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 30px; color: #94a3b8;">
                            ไม่พบข้อมูลผู้ใช้งานที่ตรงตามเงื่อนไข</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"
                    class="pagination-link <?= ($page == $i) ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<div id="modal" class="modal">
    <div class="modal-box">
        <h3 id="modalTitle">เพิ่มผู้ใช้งาน</h3>
        <input type="hidden" id="user_id">
        <div class="modal-form-body">

            <div class="avatar-upload-wrapper" id="dropZone" onclick="document.getElementById('picture').click()">
                <div class="avatar-preview-container" id="previewContainer" style="display: none;">
                    <img id="imgPreview" src="" referrerpolicy="no-referrer">
                </div>
                <div class="avatar-icon-placeholder" id="avatarPlaceholder">
                    <i data-lucide="user" style="width: 28px; height: 28px;"></i>
                </div>
                <div class="upload-text" id="uploadText">ลากไฟล์รูปภาพมาวางที่นี่ หรือ <span>คลิกเพื่อเลือกไฟล์</span>
                </div>
                <input type="file" id="picture" accept="image/jpeg, image/png, image/webp" style="display: none;"
                    onchange="previewImage(this)">
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" id="username" placeholder="ระบุชื่อผู้ใช้สำหรับ Login...">
            </div>

            <div class="form-group">
                <label>ชื่อ-นามสกุล</label>
                <input type="text" id="name" placeholder="ระบุชื่อจริง-นามสกุล...">
            </div>

            <div class="form-group">
                <label>อีเมล</label>
                <input type="email" id="email" placeholder="example@domain.com">
            </div>

            <div class="form-group">
                <label id="pwdLabel">รหัสผ่าน</label>
                <input type="password" id="password" placeholder="ระบุรหัสผ่าน...">
                <small id="pwdHelp" style="color: #94a3b8; font-size: 12px; margin-top: -2px; display: none;">*
                    ปล่อยว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่านเดิม</small>
            </div>

            <div class="form-group">
                <label>สิทธิ์การใช้งาน (Role)</label>
                <select id="role_id">
                    <option value="2">ผู้ใช้งานทั่วไป</option>
                    <option value="1">ผู้ดูแลระบบ (Admin)</option>
                    <option value="3">ผู้บริหาร</option>
                    <option value="4">ภาควิชา</option>
                </select>
            </div>

            <div class="form-group">
                <label>สังกัด/ภาควิชา</label>
                <select id="department_id">
                    <option value="">-- ไม่ระบุสังกัด --</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['department_id'] ?>"><?= h($d['department_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>สถานะบัญชี</label>
                <select id="status">
                    <option value="active">ใช้งานปกติ</option>
                    <option value="pending">รออนุมัติการเข้าใช้งาน</option>
                    <option value="inactive">ระงับการใช้งาน</option>
                </select>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal()">
                <i data-lucide="x" style="width: 16px; height: 16px;"></i> ยกเลิก
            </button>
            <button class="btn-save" onclick="saveUser()">
                <i data-lucide="save" style="width: 16px; height: 16px;"></i> บันทึกข้อมูล
            </button>
        </div>
    </div>
</div>







<?php
include_once __DIR__ . '/../../../app/views/components/profile_modal.php';
include_once __DIR__ . '/../../../app/views/layouts/footer.php';
?>