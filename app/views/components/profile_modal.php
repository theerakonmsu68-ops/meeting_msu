<?php
/* ============================================================
 * Department profile modal — compatible with positions,
 * departments, roles and Google/local avatars.
 *
 * Editable fields remain: name and profile image.
 * Position, department, role and email are read-only because
 * they are controlled by the administrator/account data.
 * ============================================================ */


$pmUserId = (int) ($_SESSION['user_id'] ?? 0);
$pmProfile = [
    'username' => (string) ($_SESSION['username'] ?? $_SESSION['user_name'] ?? $_SESSION['Username'] ?? ''),
    'name' => (string) ($_SESSION['name'] ?? ''),
    'email' => (string) ($_SESSION['email'] ?? ''),
    'picture' => (string) ($_SESSION['picture'] ?? ''),
    'position_name' => (string) ($_SESSION['position_name'] ?? ''),
    'department_name' => (string) ($_SESSION['department_name'] ?? ''),
    'role_name' => (string) ($_SESSION['role_name'] ?? ''),
    'status' => (string) ($_SESSION['status'] ?? 'active'),
    'login_type' => (string) ($_SESSION['login_type'] ?? 'normal'),
];

// Pull the latest profile data from the database when the including page already has $db.
if ($pmUserId > 0 && isset($db) && $db instanceof PDO) {
    try {
        $pmStmt = $db->prepare(
            "SELECT
                u.username,
                u.name,
                u.email,
                u.picture,
                u.status,
                u.login_type,
                COALESCE(p.position_name, '') AS position_name,
                COALESCE(d.department_name, '') AS department_name,
                COALESCE(r.role_name, '') AS role_name
             FROM user u
             LEFT JOIN positions p ON p.position_id = u.position_id
             LEFT JOIN departments d ON d.department_id = u.department_id
             LEFT JOIN role r ON r.role_id = u.role_id
             WHERE u.user_id = ?
             LIMIT 1"
        );
        $pmStmt->execute([$pmUserId]);
        $pmDbProfile = $pmStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($pmDbProfile)) {
            $pmProfile = array_merge($pmProfile, $pmDbProfile);
        }
    } catch (Throwable $pmException) {
        // Keep the session fallback so the modal does not break the whole page.
    }
}

if ($pmProfile['username'] === '') {
    $pmProfile['username'] = $pmProfile['email'] !== '' ? $pmProfile['email'] : 'ไม่พบชื่อผู้ใช้งาน';
}

$pmScriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$pmAppPosition = strpos($pmScriptPath, '/app/');
$pmProjectBase = $pmAppPosition !== false ? substr($pmScriptPath, 0, $pmAppPosition) : '/Meeting_msu';
$pmProjectBase = rtrim($pmProjectBase, '/');
$pmUpdateUrl = $pmProjectBase . '/app/controllers/update_profile.php';

$pmPicture = trim((string) $pmProfile['picture']);
$pmAvatarUrl = '';
if ($pmPicture !== '') {
    if (filter_var($pmPicture, FILTER_VALIDATE_URL)) {
        $pmScheme = strtolower((string) parse_url($pmPicture, PHP_URL_SCHEME));
        if (in_array($pmScheme, ['http', 'https'], true)) {
            $pmAvatarUrl = $pmPicture;
        }
    } elseif (str_starts_with($pmPicture, '/')) {
        $pmAvatarUrl = $pmPicture;
    } elseif (str_contains(str_replace('\\', '/', $pmPicture), 'public/uploads/avatars/')) {
        $pmAvatarUrl = $pmProjectBase . '/' . ltrim(str_replace('\\', '/', $pmPicture), '/');
    } else {
        $pmAvatarUrl = $pmProjectBase . '/public/uploads/avatars/' . rawurlencode(basename($pmPicture));
    }
}

if ($pmAvatarUrl === '') {
    $pmDisplayName = trim((string) $pmProfile['name']);
    if ($pmDisplayName === '') {
        $pmDisplayName = 'U';
    }
    $pmInitial = function_exists('mb_substr')
        ? mb_substr($pmDisplayName, 0, 1, 'UTF-8')
        : substr($pmDisplayName, 0, 1);
    $pmInitialEscaped = htmlspecialchars($pmInitial, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $pmSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160">'
        . '<rect width="100%" height="100%" rx="80" fill="#0284c7"/>'
        . '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" '
        . 'font-family="Tahoma,Arial,sans-serif" font-size="68" font-weight="700" fill="#ffffff">'
        . $pmInitialEscaped . '</text></svg>';
    $pmAvatarUrl = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($pmSvg);
}


$pmDepartment = trim((string) $pmProfile['department_name']) ?: 'ยังไม่ระบุภาควิชา';
$pmRole = trim((string) $pmProfile['role_name']) ?: 'ยังไม่ระบุสิทธิ์';
$pmEmail = trim((string) $pmProfile['email']) ?: '-';
$pmLoginTypeText = ($pmProfile['login_type'] ?? 'normal') === 'google' ? 'เข้าสู่ระบบด้วย Google' : 'บัญชีผู้ใช้ของระบบ';
$pmStatusText = ($pmProfile['status'] ?? '') === 'active' ? 'ใช้งานอยู่' : 'บัญชีไม่พร้อมใช้งาน';
$pmStatusClass = ($pmProfile['status'] ?? '') === 'active' ? 'is-active' : 'is-inactive';
?>



<div class="p-modal-backdrop" id="profileModal" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle"
    aria-hidden="true">
    <div class="p-modal-card">
        <div class="p-modal-header">
            <div class="p-modal-heading">
                <div class="p-modal-heading-icon"><i data-lucide="user-cog" style="width:20px;height:20px;"></i></div>
                <div>
                    <h3 class="p-modal-title" id="profileModalTitle">ข้อมูลโปรไฟล์</h3>
                    <p class="p-modal-subtitle">ตรวจสอบข้อมูลที่ใช้ในคำเชิญและรายงานการประชุม</p>
                </div>
            </div>
            <button type="button" class="p-close-btn" onclick="closeProfileModal()" aria-label="ปิดหน้าต่าง">
                <i data-lucide="x" style="width:18px;height:18px;"></i>
            </button>
        </div>

        <div class="p-modal-body">
            <form id="profileForm" onsubmit="submitProfileForm(event)" enctype="multipart/form-data" novalidate>
                <div class="p-profile-top">
                    <div class="p-avatar-wrapper">
                        <div class="p-avatar-circle">
                            <img id="pPreviewImg" src="<?= htmlspecialchars($pmAvatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                                alt="รูปโปรไฟล์ของ <?= htmlspecialchars((string) $pmProfile['name'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <label for="p_file_input" class="p-upload-btn">
                            <i data-lucide="camera" style="width:14px;height:14px;"></i>
                            <span>เปลี่ยนรูป</span>
                        </label>
                        <input type="file" id="p_file_input" name="profile_image"
                            accept="image/jpeg,image/png,image/webp" hidden>
                    </div>

                    <div class="p-profile-summary">
                        <h4 class="p-profile-name">
                            <?= htmlspecialchars((string) $pmProfile['name'], ENT_QUOTES, 'UTF-8') ?>
                        </h4>
                        <p class="p-profile-meta">
                       
                            <?= htmlspecialchars($pmDepartment, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <div class="p-status-row">
                            <span
                                class="p-chip <?= $pmStatusClass ?>"><?= htmlspecialchars($pmStatusText, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="p-chip"><?= htmlspecialchars($pmRole, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="p-chip"><?= htmlspecialchars($pmLoginTypeText, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                </div>

                <div id="pFormMessage" class="p-form-message" role="status" aria-live="polite"></div>

                <section class="p-section">
                    <h4 class="p-section-title"><i data-lucide="pencil-line"
                            style="width:16px;height:16px;color:#0284c7;"></i> ข้อมูลที่แก้ไขได้</h4>
                    <div class="p-form-grid">
                        <div class="p-group p-full">
                            <label for="p_name_input">ชื่อ–นามสกุล</label>
                            <div class="p-input-wrap">
                                <i data-lucide="user-round" class="p-input-icon"></i>
                                <input type="text" id="p_name_input" name="name" class="p-input" maxlength="150"
                                    value="<?= htmlspecialchars((string) $pmProfile['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    autocomplete="name" required>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="p-section">
                    <h4 class="p-section-title"><i data-lucide="badge-info"
                            style="width:16px;height:16px;color:#0284c7;"></i> ข้อมูลบัญชีและสังกัด</h4>
                    <div class="p-form-grid">
                        <div class="p-group">
                            <label>ชื่อผู้ใช้งาน</label>
                            <div class="p-input-wrap">
                                <i data-lucide="at-sign" class="p-input-icon"></i>
                                <input type="text" class="p-input"
                                    value="<?= htmlspecialchars((string) $pmProfile['username'], ENT_QUOTES, 'UTF-8') ?>"
                                    readonly>
                            </div>
                        </div>
                        <div class="p-group">
                            <label>อีเมล</label>
                            <div class="p-input-wrap">
                                <i data-lucide="mail" class="p-input-icon"></i>
                                <input type="text" class="p-input"
                                    value="<?= htmlspecialchars($pmEmail, ENT_QUOTES, 'UTF-8') ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="p-group">
                            <label>ภาควิชา</label>
                            <div class="p-input-wrap">
                                <i data-lucide="building-2" class="p-input-icon"></i>
                                <input type="text" class="p-input"
                                    value="<?= htmlspecialchars($pmDepartment, ENT_QUOTES, 'UTF-8') ?>" readonly>
                            </div>
                        </div>
                        <div class="p-group">
                            <label>สิทธิ์การใช้งานระบบ</label>
                            <div class="p-input-wrap">
                                <i data-lucide="shield-check" class="p-input-icon"></i>
                                <input type="text" class="p-input"
                                    value="<?= htmlspecialchars($pmRole, ENT_QUOTES, 'UTF-8') ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="p-note">
                        <i data-lucide="info"
                            style="width:16px;height:16px;flex:0 0 auto;margin-top:1px;color:#0284c7;"></i>
                        <span>ตำแหน่ง ภาควิชา อีเมล และสิทธิ์ระบบถูกใช้ในรายชื่อผู้ได้รับเชิญและรายงานการประชุม
                            หากข้อมูลไม่ถูกต้องให้ติดต่อผู้ดูแลระบบเพื่อแก้ไข</span>
                    </div>
                </section>

                <div class="p-actions">
                    <button type="button" class="p-btn p-btn-secondary" onclick="closeProfileModal()">
                        <i data-lucide="x" style="width:16px;height:16px;"></i> ยกเลิก
                    </button>
                    <button type="submit" class="p-btn p-btn-submit" id="pSubmitButton">
                        <i data-lucide="save" style="width:16px;height:16px;"></i>
                        <span id="pSubmitText">บันทึกการเปลี่ยนแปลง</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.profileUpdateUrl = <?= json_encode(
        $pmUpdateUrl,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;
</script>