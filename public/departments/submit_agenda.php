<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(4);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/view_helper.php';

$db = (new Database())->connect();
$userId = (int) ($_SESSION['user_id'] ?? 0);

// ดึงภาควิชาจากข้อมูลผู้ใช้งาน เพื่อบันทึกลง agenda
$deptStmt = $db->prepare("
    SELECT department_id
    FROM user
    WHERE user_id = ?
    LIMIT 1
");
$deptStmt->execute([$userId]);
$departmentId = $deptStmt->fetchColumn();

if ($userId <= 0) {
    http_response_code(401);
    exit('ไม่พบข้อมูลผู้ใช้งาน');
}

if (empty($_SESSION['department_agenda_csrf'])) {
    $_SESSION['department_agenda_csrf'] = bin2hex(random_bytes(32));
}
$departmentAgendaCsrfToken = $_SESSION['department_agenda_csrf'];

$stmtMeetings = $db->prepare(
    "SELECT DISTINCT
        m.meeting_id,
        m.meeting_title,
        m.meeting_date,
        m.meeting_time,
        m.meeting_location,
        m.meeting_status
     FROM meeting_attendance ma
     INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
     WHERE ma.user_id = ?
       AND m.meeting_status <> 'closed'
     ORDER BY m.meeting_date ASC, m.meeting_time ASC"
);
$stmtMeetings->execute([$userId]);
$availableMeetings = $stmtMeetings->fetchAll(PDO::FETCH_ASSOC);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    $meetingId = (int) ($_POST['meeting_id'] ?? 0);
    $agendaTitle = trim((string) ($_POST['agenda_title'] ?? ''));
    $agendaDetail = trim((string) ($_POST['agenda_detail'] ?? ''));
    $agendaType = (string) ($_POST['agenda_type'] ?? 'info');

    if (!hash_equals($departmentAgendaCsrfToken, $token)) {
        $message = 'คำขอไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่';
        $messageType = 'error';
    } elseif ($meetingId <= 0 || $agendaTitle === '' || $agendaDetail === '') {
        $message = 'กรุณากรอกข้อมูลการเสนอวาระให้ครบถ้วน';
        $messageType = 'error';
    } elseif (!in_array($agendaType, ['info', 'consider', 'approve'], true)) {
        $message = 'ประเภทวาระไม่ถูกต้อง';
        $messageType = 'error';
    } else {
        $check = $db->prepare(
            "SELECT m.meeting_id
             FROM meeting_attendance ma
             INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
             WHERE ma.user_id = ?
               AND m.meeting_id = ?
               AND m.meeting_status <> 'closed'
             LIMIT 1"
        );
        $check->execute([$userId, $meetingId]);

        if (!$check->fetchColumn()) {
            $message = 'คุณไม่มีสิทธิ์เสนอวาระสำหรับการประชุมนี้';
            $messageType = 'error';
        } else {
            try {
                $db->beginTransaction();

                $orderStmt = $db->prepare(
                    "SELECT COALESCE(MAX(order_index), 0) + 1
                     FROM agenda
                     WHERE meeting_id = ?"
                );
                $orderStmt->execute([$meetingId]);
                $nextOrder = (int) $orderStmt->fetchColumn();

                $insertAgenda = $db->prepare(
                    "INSERT INTO agenda
                        (agenda_title, agenda_detail, meeting_id, order_index, agenda_type, agenda_status, submitted_by, department_id, admin_status)
                     VALUES
                        (:title, :detail, :meeting_id, :order_index, :agenda_type, 'pending', :submitted_by, :department_id, 'pending')"
                );
                $insertAgenda->execute([
                    ':title' => $agendaTitle,
                    ':detail' => $agendaDetail,
                    ':meeting_id' => $meetingId,
                    ':order_index' => $nextOrder,
                    ':agenda_type' => $agendaType,
                    ':submitted_by' => $userId,
                    ':department_id' => $departmentId,
                ]);

                $agendaId = (int) $db->lastInsertId();

                if (
                    isset($_FILES['agenda_documents']) &&
                    is_array($_FILES['agenda_documents']['name'] ?? null)
                ) {
                    $allowedMime = [
                        'application/pdf' => 'pdf',
                        'application/msword' => 'doc',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                        'application/vnd.ms-excel' => 'xls',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                        'application/vnd.ms-powerpoint' => 'ppt',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                    ];

                    $uploadDir = __DIR__ . '/../../public/uploads/meetings/meeting_' . $meetingId . '/agendas/agenda_' . $agendaId . '/';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์สำหรับเอกสารแนบได้');
                    }

                    $files = $_FILES['agenda_documents'];
                    $fileCount = count($files['name']);

                    for ($i = 0; $i < $fileCount; $i++) {
                        $error = (int) $files['error'][$i];

                        if ($error === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }
                        if ($error !== UPLOAD_ERR_OK) {
                            throw new RuntimeException('มีไฟล์บางรายการอัปโหลดไม่สำเร็จ');
                        }
                        if ((int) $files['size'][$i] > 10 * 1024 * 1024) {
                            throw new RuntimeException('ไฟล์แนบแต่ละไฟล์ต้องมีขนาดไม่เกิน 10 MB');
                        }

                        $tmp = $files['tmp_name'][$i];
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime = (string) $finfo->file($tmp);

                        if (!isset($allowedMime[$mime])) {
                            throw new RuntimeException('พบประเภทไฟล์ที่ไม่รองรับ');
                        }

                        $originalName = trim((string) $files['name'][$i]) ?: 'document.' . $allowedMime[$mime];
                        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
                        $baseName = preg_replace('/[^\p{L}\p{N}_\-]+/u', '_', $baseName) ?: 'document';
                        $fileName = $baseName . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMime[$mime];
                        $target = $uploadDir . $fileName;

                        if (!move_uploaded_file($tmp, $target)) {
                            throw new RuntimeException('ไม่สามารถบันทึกไฟล์แนบได้');
                        }

                        $relativePath = 'public/uploads/meetings/meeting_' . $meetingId
                            . '/agendas/agenda_' . $agendaId . '/' . $fileName;

                        $docStmt = $db->prepare(
                            "INSERT INTO agenda_documents
                                (agenda_id, document_name, file_path, file_size, mime_type)
                             VALUES (?, ?, ?, ?, ?)"
                        );
                        $docStmt->execute([
                            $agendaId,
                            $originalName,
                            $relativePath,
                            (int) $files['size'][$i],
                            $mime
                        ]);
                    }
                }

                $db->commit();
                header('Location: track_agendas.php?created=1&agenda_id=' . $agendaId);
                exit;
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $message = 'ไม่สามารถบันทึกวาระได้: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

$page_title = 'เสนอวาระการประชุม';
$page_css = "department-submit.css";
$page_js = "department-submit.js";
include_once __DIR__ . '/../../app/views/layouts/header.php';
$current_page = 'submit_agenda';
include_once __DIR__ . '/../../app/views/layouts/sidebar_department.php';
?>



<div class="main-content" id="mainContent">
    <header class="page-header">
        <button class="toggle" id="toggle-sidebar"><i data-lucide="menu"></i></button>
        <div>
            <h2 style="margin:0;font-size:20px;color:#1e293b">เสนอวาระการประชุม</h2>
            <p style="margin:4px 0 0;color:#64748b;font-size:12.5px">เสนอหัวข้อและเอกสารเข้าสู่การประชุมที่คุณได้รับเชิญ
            </p>
        </div>
    </header>
    <main class="content">
        <div class="layout">
            <section class="card">
                <h3>ข้อมูลวาระ</h3>
                <p class="sub">กรอกข้อมูลให้ครบก่อนส่งเข้าสู่รายการวาระของการประชุม</p>

                <?php if ($message): ?>
                    <div class="message <?= h($messageType) ?>"><?= h($message) ?></div><?php endif; ?>

                <?php if (!$availableMeetings): ?>
                    <div class="note">ขณะนี้ไม่มีการประชุมที่เปิดให้คุณเสนอวาระ
                        ระบบจะแสดงเฉพาะการประชุมที่บัญชีของคุณได้รับเชิญและยังไม่ปิดการประชุม</div>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= h($departmentAgendaCsrfToken) ?>">
                        <div class="form-grid">
                            <div class="group full">
                                <label for="meeting_id">การประชุม *</label>
                                <select class="control" id="meeting_id" name="meeting_id" required>
                                    <option value="">-- เลือกการประชุม --</option>
                                    <?php foreach ($availableMeetings as $m): ?>
                                        <option value="<?= (int) $m['meeting_id'] ?>" <?= (int) ($_POST['meeting_id'] ?? 0) === (int) $m['meeting_id'] ? 'selected' : '' ?>>
                                            <?= h($m['meeting_title']) ?> — <?= date('d/m/Y', strtotime($m['meeting_date'])) ?>
                                            <?= substr((string) $m['meeting_time'], 0, 5) ?> น.
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="group full"><label for="agenda_title">หัวข้อวาระ *</label><input class="control"
                                    id="agenda_title" name="agenda_title" maxlength="255" required
                                    value="<?= h($_POST['agenda_title'] ?? '') ?>"
                                    placeholder="เช่น ขอพิจารณาแผนการดำเนินงานประจำปี"></div>

                            <div class="group"><label for="agenda_type">ประเภทวาระ *</label>
                                <select class="control" id="agenda_type" name="agenda_type" required>
                                    <option value="info" <?= ($_POST['agenda_type'] ?? '') === 'info' ? 'selected' : '' ?>>เพื่อทราบ
                                    </option>
                                    <option value="consider" <?= ($_POST['agenda_type'] ?? '') === 'consider' ? 'selected' : '' ?>>
                                        เพื่อพิจารณา</option>
                                    <option value="approve" <?= ($_POST['agenda_type'] ?? '') === 'approve' ? 'selected' : '' ?>>
                                        เพื่ออนุมัติ</option>
                                </select>
                            </div>

                            <div class="group"><label for="agenda_documents">เอกสารแนบ</label><input class="control"
                                    type="file" id="agenda_documents" name="agenda_documents[]" multiple
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png">
                                <div class="file-help">รองรับ PDF, Office, JPG, PNG สูงสุดไฟล์ละ 10 MB</div>
                            </div>

                            <div class="group full"><label for="agenda_detail">รายละเอียด / เหตุผล / ข้อเสนอ
                                    *</label><textarea class="control" id="agenda_detail" name="agenda_detail" required
                                    placeholder="อธิบายรายละเอียดของเรื่องที่ต้องการเสนอ..."><?= h($_POST['agenda_detail'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="actions"><a class="btn soft" href="track_agendas.php"><i
                                    data-lucide="clipboard-list"></i> ดูรายการวาระ</a><button class="btn primary"
                                type="submit"><i data-lucide="send"></i> ส่งวาระ</button></div>
                    </form>
                <?php endif; ?>
            </section>

            <aside class="card">
                <h3>ขั้นตอนการเสนอวาระ</h3>
                <p class="sub">วาระจะถูกบันทึกในสถานะรอดำเนินการ</p>
                <div class="steps">
                    <div class="step"><span class="num" style="font-size: 15px;">1.</span>
                        <div><strong>เลือกการประชุม</strong><span style="font-size: 13px;">เลือกได้เฉพาะรายการที่คุณได้รับเชิญและยังไม่ปิด</span>
                        </div>
                    </div>
                    <div class="step"><span class="num" style="font-size: 13px;">2.</span>
                        <div><strong>กรอกหัวข้อและรายละเอียด</strong><span style="font-size: 13px;">ระบุประเภทเพื่อทราบ / พิจารณา /
                                อนุมัติ</span></div>
                    </div>
                    <div class="step"><span class="num" style="font-size: 13px;">3.</span>
                        <div><strong>แนบเอกสาร</strong><span style="font-size: 13px;">ไฟล์จะถูกจัดเก็บภายใต้วาระที่สร้าง</span></div>
                    </div>
                    <div class="step"><span class="num" style="font-size: 13px;">4.</span>
                        <div><strong>ติดตามสถานะ</strong><span style="font-size: 13px;">ตรวจสอบสถานะวาระและเอกสารได้จากหน้าติดตาม</span></div>
                    </div>
                </div>
                <div class="note" style="margin-top:16px; font-size: 15px;">ระบบจะบันทึกผู้เสนอและภาควิชาจากข้อมูลบัญชีผู้ใช้งาน</div>
            </aside>
        </div>
    </main>
</div>


<?php
include_once __DIR__ . '/../../app/views/layouts/footer.php';
?>