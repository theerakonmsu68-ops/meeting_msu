<?php

/* =======================================================
🔐 AUTH & SECURITY
======================================================= */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/models/Meeting.php';
require_once __DIR__ . '/../../../app/controllers/MeetingController.php';
require_once __DIR__ . '/../../../app/helpers/MailHelper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !AuthMiddleware::verifyCsrf()) {
    http_response_code(419);
    echo json_encode(['status' => 'error', 'message' => 'โทเคนความปลอดภัยไม่ถูกต้อง']);
    exit;
}

$db = (new Database())->connect();

$model = new Meeting($db);
$controller = new MeetingController($model);

$action = $_POST['action'] ?? $_GET['action'] ?? '';


/* =======================================================
⚙️ UPLOAD CONFIG
======================================================= */

const MAX_UPLOAD_BYTES = 20 * 1024 * 1024;

const ALLOWED_EXTENSIONS = [
    'pdf',
    'doc',
    'docx',
    'xls',
    'xlsx',
    'ppt',
    'pptx'
];

const ALLOWED_MIME_TYPES = [
    'application/pdf',

    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation'
];


/* =======================================================
📤 JSON RESPONSE
======================================================= */

function jsonResponse(
    string $status,
    string $message,
    array $extra = []
): void {

    echo json_encode(
        array_merge(
            [
                'status' => $status,
                'message' => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/* =======================================================
🔒 HTML ESCAPE
======================================================= */

function escapeHtml(?string $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
        'UTF-8'
    );
}


/* =======================================================
🧹 SANITIZE PATH
======================================================= */

function sanitizePathPart(
    string $value,
    string $fallback = 'file'
): string {

    $value = trim($value);

    $value = str_replace(
        [' ', '/', '\\'],
        '_',
        $value
    );

    $value = preg_replace(
        '/[^A-Za-z0-9\x{0E00}-\x{0E7F}._-]/u',
        '',
        $value
    );

    $value = trim(
        (string) $value,
        '._-'
    );

    return $value !== ''
        ? $value
        : $fallback;
}


/* =======================================================
📁 CREATE DIRECTORY
======================================================= */

function createDirectory(string $directory): void
{
    if (
        !is_dir($directory) &&
        !mkdir($directory, 0775, true) &&
        !is_dir($directory)
    ) {
        throw new RuntimeException(
            'ไม่สามารถสร้างโฟลเดอร์อัปโหลดได้'
        );
    }
}


/* =======================================================
🗑️ DELETE DIRECTORY RECURSIVE
======================================================= */

function deleteDirectoryRecursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = array_diff(
        scandir($dir) ?: [],
        ['.', '..']
    );

    foreach ($items as $item) {

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            deleteDirectoryRecursive($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}


/* =======================================================
🔐 UNIQUE DESTINATION
======================================================= */

function uniqueDestination(
    string $directory,
    string $filename
): array {

    $extension = strtolower(
        pathinfo($filename, PATHINFO_EXTENSION)
    );

    $basename = pathinfo(
        $filename,
        PATHINFO_FILENAME
    );

    $basename = sanitizePathPart(
        $basename,
        'file'
    );

    do {

        $uniqueSuffix = '_' . bin2hex(
            random_bytes(8)
        );

        $candidate =
            $basename .
            $uniqueSuffix .
            (
                $extension !== ''
                    ? '.' . $extension
                    : ''
            );

        $destination =
            rtrim(
                $directory,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . $candidate;

    } while (file_exists($destination));

    return [
        $destination,
        $candidate
    ];
}


/* =======================================================
📤 GENERIC FILE UPLOAD
======================================================= */

function uploadFileInput(
    PDO $db,
    string $inputName,
    string $uploadDirectory,
    string $relativeDirectory,
    callable $insertCallback
): array {

    if (
        !isset($_FILES[$inputName]) ||
        !is_array($_FILES[$inputName])
    ) {
        return [];
    }

    $files = $_FILES[$inputName];

    $names = is_array($files['name'] ?? null)
        ? $files['name']
        : [$files['name'] ?? ''];

    $tmpNames = is_array($files['tmp_name'] ?? null)
        ? $files['tmp_name']
        : [$files['tmp_name'] ?? ''];

    $errors = is_array($files['error'] ?? null)
        ? $files['error']
        : [$files['error'] ?? UPLOAD_ERR_NO_FILE];

    $sizes = is_array($files['size'] ?? null)
        ? $files['size']
        : [$files['size'] ?? 0];

    createDirectory($uploadDirectory);

    $uploadedPaths = [];
    $uploadedAbsolutePaths = [];

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    try {

        foreach ($names as $index => $originalName) {

            $originalName = (string) $originalName;

            $error = (int) (
                $errors[$index] ?? UPLOAD_ERR_NO_FILE
            );

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {

                throw new RuntimeException(
                    'อัปโหลดไฟล์ไม่สำเร็จ: ' .
                    $originalName
                );
            }

            $tmpName = (string) (
                $tmpNames[$index] ?? ''
            );

            if (
                $tmpName === '' ||
                !is_uploaded_file($tmpName)
            ) {

                throw new RuntimeException(
                    'ไฟล์อัปโหลดไม่ถูกต้อง: ' .
                    $originalName
                );
            }

            $size = (int) (
                $sizes[$index] ?? 0
            );

            if (
                $size <= 0 ||
                $size > MAX_UPLOAD_BYTES
            ) {

                throw new RuntimeException(
                    'ไฟล์ ' .
                    $originalName .
                    ' มีขนาดเกิน 20 MB'
                );
            }

            $extension = strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            );

            if (
                $extension === '' ||
                !in_array(
                    $extension,
                    ALLOWED_EXTENSIONS,
                    true
                )
            ) {

                throw new RuntimeException(
                    'ไม่รองรับไฟล์นามสกุล .' .
                    $extension
                );
            }

            $detectedMime = $finfo->file(
                $tmpName
            );

            if (
                $detectedMime === false ||
                !in_array(
                    $detectedMime,
                    ALLOWED_MIME_TYPES,
                    true
                )
            ) {

                throw new RuntimeException(
                    'รูปแบบไฟล์ไม่ถูกต้อง: ' .
                    $originalName
                );
            }

            $safeName = sanitizePathPart(
                $originalName,
                'document.' . $extension
            );

            [
                $destination,
                $storedName
            ] = uniqueDestination(
                $uploadDirectory,
                $safeName
            );

            if (
                !move_uploaded_file(
                    $tmpName,
                    $destination
                )
            ) {

                throw new RuntimeException(
                    'ไม่สามารถบันทึกไฟล์ ' .
                    $originalName .
                    ' ได้'
                );
            }

            $uploadedAbsolutePaths[] =
                $destination;

            $relativePath =
                rtrim(
                    $relativeDirectory,
                    '/'
                )
                . '/'
                . $storedName;

            /*
             * บันทึก DB
             */
            $insertCallback(
                $originalName,
                $relativePath,
                $size,
                $detectedMime
            );

            $uploadedPaths[] =
                $relativePath;
        }

    } catch (Throwable $e) {

        foreach (
            $uploadedAbsolutePaths
            as $absolutePath
        ) {

            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        throw $e;
    }

    return $uploadedPaths;
}


/* =======================================================
📄 UPLOAD MEETING DOCUMENTS

documents table:
- document_id
- meeting_id
- document_name
- file_path
- upload_date

ไม่มี file_size / mime_type
======================================================= */

function uploadMeetingDocuments(
    PDO $db,
    int $meetingId,
    string $meetingTitle
): array {

    $folderName =
        'meeting_' . $meetingId;

    $relativeDirectory =
        'public/uploads/meetings/' .
        $folderName;

    $uploadDirectory =
        __DIR__ .
        '/../../../' .
        $relativeDirectory .
        '/';

    return uploadFileInput(
        $db,
        'meeting_documents',
        $uploadDirectory,
        $relativeDirectory,

        function (
            string $originalName,
            string $relativePath,
            int $size,
            string $mimeType
        ) use ($db, $meetingId): void {

            /*
             * documents ไม่มี file_size / mime_type
             */
            $stmt = $db->prepare(
                'INSERT INTO documents
                    (
                        document_name,
                        file_path,
                        meeting_id
                    )
                 VALUES (?, ?, ?)'
            );

            $stmt->execute([
                $originalName,
                $relativePath,
                $meetingId
            ]);
        }
    );
}


/* =======================================================
📑 UPLOAD AGENDA DOCUMENTS

agenda_documents มี:
- file_size
- mime_type
======================================================= */

function uploadAgendaDocuments(
    PDO $db,
    int $meetingId,
    int $agendaId,
    string $inputName
): array {

    $relativeDirectory =
        'public/uploads/meetings/' .
        'meeting_' .
        $meetingId .
        '/agendas/agenda_' .
        $agendaId;

    $uploadDirectory =
        __DIR__ .
        '/../../../' .
        $relativeDirectory .
        '/';

    return uploadFileInput(
        $db,
        $inputName,
        $uploadDirectory,
        $relativeDirectory,

        function (
            string $originalName,
            string $relativePath,
            int $size,
            string $mimeType
        ) use ($db, $agendaId): void {

            $stmt = $db->prepare(
                'INSERT INTO agenda_documents
                    (
                        agenda_id,
                        document_name,
                        file_path,
                        file_size,
                        mime_type
                    )
                 VALUES (?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $agendaId,
                $originalName,
                $relativePath,
                $size,
                $mimeType
            ]);
        }
    );
}


/* =======================================================
🗑️ DELETE STORED FILES
======================================================= */

function unlinkStoredFiles(
    array $relativePaths
): void {

    foreach (
        array_unique($relativePaths)
        as $relativePath
    ) {

        if (
            !is_string($relativePath) ||
            trim($relativePath) === ''
        ) {
            continue;
        }

        /*
         * ป้องกัน path traversal
         */
        $relativePath = ltrim(
            $relativePath,
            '/\\'
        );

        if (
            str_contains($relativePath, '..')
        ) {
            continue;
        }

        $absolutePath =
            __DIR__ .
            '/../../../' .
            $relativePath;

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}


/* =======================================================
📋 PARSE AGENDAS
======================================================= */

function parseAgendas(): array
{
    $agendas = json_decode(
        $_POST['agendas'] ?? '[]',
        true
    );

    return is_array($agendas)
        ? $agendas
        : [];
}


/* =======================================================
🔑 CLEAN AGENDA KEY
======================================================= */

function cleanAgendaKey(
    string $key
): string {

    return preg_replace(
        '/[^A-Za-z0-9_-]/',
        '',
        $key
    ) ?: 'agenda';
}


/* =======================================================
👥 PARSE INVITED USERS
======================================================= */

function parseInvitedUserIds(): array
{
    $ids = json_decode(
        $_POST['invited_user_ids'] ?? '[]',
        true
    );

    if (!is_array($ids)) {
        return [];
    }

    $ids = array_map(
        'intval',
        $ids
    );

    $ids = array_filter(
        $ids,
        static fn(int $id): bool => $id > 0
    );

    return array_values(
        array_unique($ids)
    );
}


/* =======================================================
📨 SYNC MEETING INVITATIONS
======================================================= */

function syncMeetingInvitations(
    PDO $db,
    int $meetingId,
    array $requestedUserIds,
    string $meetingTitle,
    string $meetingDate,
    string $meetingTime,
    string $meetingLocation,
    array &$pendingEmails = []
): void {

    /*
     * Admin = Chairman อัตโนมัติ
     */
    $stmtAdmins = $db->query(
        "SELECT user_id
         FROM user
         WHERE status = 'active'
         AND role_id = 1"
    );

    $adminIds = array_map(
        'intval',
        $stmtAdmins->fetchAll(
            PDO::FETCH_COLUMN
        )
    );

    /*
     * รวม Admin + คนที่เลือก
     */
    $allTargetUserIds =
        array_values(
            array_unique(
                array_merge(
                    $adminIds,
                    $requestedUserIds
                )
            )
        );

    $validUserIds = [];

    if (!empty($allTargetUserIds)) {

        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($allTargetUserIds),
                '?'
            )
        );

        $stmtUsers = $db->prepare(
            "SELECT user_id
             FROM user
             WHERE status = 'active'
             AND role_id != 2
             AND user_id IN ($placeholders)"
        );

        $stmtUsers->execute(
            $allTargetUserIds
        );

        $validUserIds = array_map(
            'intval',
            $stmtUsers->fetchAll(
                PDO::FETCH_COLUMN
            )
        );
    }

    /*
     * Existing attendance
     */
    $stmtExisting = $db->prepare(
        'SELECT user_id
         FROM meeting_attendance
         WHERE meeting_id = ?'
    );

    $stmtExisting->execute([
        $meetingId
    ]);

    $existingUserIds = array_map(
        'intval',
        $stmtExisting->fetchAll(
            PDO::FETCH_COLUMN
        )
    );

    /*
     * New users
     */
    $newUserIds =
        array_values(
            array_diff(
                $validUserIds,
                $existingUserIds
            )
        );

    /*
     * ลบเฉพาะสมาชิกทั่วไป
     */
    $removedUserIds =
        array_values(
            array_diff(
                $existingUserIds,
                $validUserIds,
                $adminIds
            )
        );

    /*
     * สมาชิกเดิมที่ยังอยู่
     */
    $keptUserIds =
        array_values(
            array_intersect(
                $validUserIds,
                $existingUserIds
            )
        );

    $displayTime =
        substr(
            $meetingTime,
            0,
            5
        );

    $newMessage = sprintf(
        'ขอเชิญเข้าร่วมประชุม "%s" วันที่ %s เวลา %s น. ณ %s',
        $meetingTitle,
        $meetingDate,
        $displayTime,
        $meetingLocation
    );


    /* ---------------------------------------------------
     * REMOVE USERS
     * --------------------------------------------------- */

    if (!empty($removedUserIds)) {

        $stmtRemove = $db->prepare(
            "DELETE FROM meeting_attendance
             WHERE meeting_id = ?
             AND user_id = ?
             AND attendance_status = 'pending'
             AND rsvp_status = 'pending'
             AND is_present = 0"
        );

        $stmtRemoveNotification = $db->prepare(
            'DELETE FROM notifications
             WHERE user_id = ?
             AND meeting_id = ?'
        );

        foreach ($removedUserIds as $userId) {

            $stmtRemove->execute([
                $meetingId,
                $userId
            ]);

            $stmtRemoveNotification->execute([
                $userId,
                $meetingId
            ]);
        }
    }


    /* ---------------------------------------------------
     * INSERT NEW USERS
     * --------------------------------------------------- */

    if (!empty($newUserIds)) {

        $stmtInvite = $db->prepare(
            "INSERT INTO meeting_attendance
                (
                    meeting_id,
                    user_id,
                    attendance_role,
                    rsvp_status,
                    attendance_status,
                    is_present
                )
             VALUES (?, ?, ?, 'pending', 'pending', 0)
             ON DUPLICATE KEY UPDATE
                attendance_role = VALUES(attendance_role)"
        );

        $stmtNotification = $db->prepare(
            'INSERT INTO notifications
                (
                    user_id,
                    meeting_id,
                    title,
                    message
                )
             VALUES (?, ?, ?, ?)'
        );

        $stmtCheckNotification = $db->prepare(
            'SELECT COUNT(*)
             FROM notifications
             WHERE user_id = ?
             AND meeting_id = ?'
        );

        $stmtEmail = $db->prepare(
            'SELECT name, email
             FROM user
             WHERE user_id = ?'
        );

        foreach ($newUserIds as $userId) {

            $role =
                in_array(
                    $userId,
                    $adminIds,
                    true
                )
                    ? 'chairman'
                    : 'member';

            $stmtInvite->execute([
                $meetingId,
                $userId,
                $role
            ]);


            /*
             * Email
             */
            $stmtEmail->execute([
                $userId
            ]);

            $user = $stmtEmail->fetch(
                PDO::FETCH_ASSOC
            );

            if (
                $user &&
                !empty($user['email'])
            ) {

                $safeUserName =
                    escapeHtml(
                        $user['name']
                    );

                $safeTitle =
                    escapeHtml(
                        $meetingTitle
                    );

                $safeDate =
                    escapeHtml(
                        $meetingDate
                    );

                $safeTime =
                    escapeHtml(
                        $displayTime
                    );

                $safeLocation =
                    escapeHtml(
                        $meetingLocation
                    );

                $pendingEmails[] = [

                    'email' =>
                        $user['email'],

                    'name' =>
                        $user['name'],

                    'subject' =>
                        'เชิญเข้าร่วมประชุม',

                    'body' => "
                        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                            <h3>เรียน {$safeUserName}</h3>

                            <p>
                                ท่านได้รับเชิญเข้าร่วมประชุม
                            </p>

                            <hr style='border: 0; border-top: 1px solid #eee;'>

                            <p>
                                <b>หัวข้อ:</b>
                                {$safeTitle}
                            </p>

                            <p>
                                <b>วันที่:</b>
                                {$safeDate}
                            </p>

                            <p>
                                <b>เวลา:</b>
                                {$safeTime} น.
                            </p>

                            <p>
                                <b>สถานที่:</b>
                                {$safeLocation}
                            </p>

                            <br>

                            <p>
                                กรุณาเข้าสู่ระบบ Meeting MSU
                                เพื่อยืนยันการเข้าร่วมประชุม
                            </p>
                        </div>
                    "
                ];
            }


            /*
             * Notification
             */
            $stmtCheckNotification->execute([
                $userId,
                $meetingId
            ]);

            $notificationExists =
                (int) $stmtCheckNotification->fetchColumn();

            if ($notificationExists === 0) {

                $stmtNotification->execute([
                    $userId,
                    $meetingId,
                    'คำเชิญเข้าร่วมประชุม',
                    $newMessage
                ]);
            }
        }
    }


    /* ---------------------------------------------------
     * UPDATE EXISTING NOTIFICATIONS
     * --------------------------------------------------- */

    if (!empty($keptUserIds)) {

        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($keptUserIds),
                '?'
            )
        );

        $params = array_merge(
            [
                $newMessage,
                $meetingId
            ],
            $keptUserIds
        );

        $stmtUpdateNotification =
            $db->prepare(
                "UPDATE notifications
                 SET message = ?
                 WHERE meeting_id = ?
                 AND user_id IN ($placeholders)"
            );

        $stmtUpdateNotification->execute(
            $params
        );
    }
}


/* =======================================================
📧 SEND EMAIL
======================================================= */

function sendPendingEmails(
    array $emails
): void {

    foreach ($emails as $item) {

        try {

            MailHelper::send(
                $item['email'],
                $item['name'],
                $item['subject'],
                $item['body']
            );

        } catch (Throwable $e) {

            error_log(
                "Failed to send mail to " .
                $item['email'] .
                ": " .
                $e->getMessage()
            );
        }
    }
}


/* =======================================================
⚙️ ROUTER
======================================================= */


/* =======================================================
📨 SAVE INVITATIONS
======================================================= */

if ($action === 'save_invitations') {

    $pendingEmails = [];

    try {

        $meetingId =
            (int) ($_POST['meeting_id'] ?? 0);

        $invitedUserIds =
            parseInvitedUserIds();

        if ($meetingId <= 0) {

            throw new InvalidArgumentException(
                'รหัสการประชุมไม่ถูกต้อง'
            );
        }

        $stmtMeeting = $db->prepare(
            'SELECT
                meeting_title,
                meeting_date,
                meeting_time,
                meeting_location
             FROM meeting
             WHERE meeting_id = ?'
        );

        $stmtMeeting->execute([
            $meetingId
        ]);

        $meeting =
            $stmtMeeting->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$meeting) {

            throw new RuntimeException(
                'ไม่พบข้อมูลการประชุม'
            );
        }

        $db->beginTransaction();

        syncMeetingInvitations(
            $db,
            $meetingId,
            $invitedUserIds,
            (string) $meeting['meeting_title'],
            (string) $meeting['meeting_date'],
            (string) $meeting['meeting_time'],
            (string) $meeting['meeting_location'],
            $pendingEmails
        );

        $db->commit();

        sendPendingEmails(
            $pendingEmails
        );

        jsonResponse(
            'success',
            sprintf(
                'บันทึกคำเชิญเรียบร้อยแล้ว จำนวน %d คน',
                count($invitedUserIds)
            ),
            [
                'invited_count' =>
                    count($invitedUserIds)
            ]
        );

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        jsonResponse(
            'error',
            'บันทึกคำเชิญไม่สำเร็จ: ' .
            $e->getMessage()
        );
    }
}


/* =======================================================
👥 GET INVITABLE USERS
======================================================= */

if ($action === 'get_invitable_users') {

    $meetingId =
        (int) ($_GET['meeting_id'] ?? 0);

    if ($meetingId <= 0) {

        jsonResponse(
            'error',
            'รหัสการประชุมไม่ถูกต้อง'
        );
    }

    $stmt = $db->prepare(
        "SELECT
            u.user_id,
            u.name,
            u.email,

            COALESCE(
                p.position_name,
                '-'
            ) AS position_name,

            COALESCE(
                d.department_name,
                '-'
            ) AS department_name,

            CASE
                WHEN ma.attendance_id IS NULL
                THEN 0
                ELSE 1
            END AS is_invited,

            COALESCE(
                ma.rsvp_status,
                'pending'
            ) AS rsvp_status

         FROM user u

         LEFT JOIN positions p
            ON p.position_id = u.position_id

         LEFT JOIN departments d
            ON d.department_id = u.department_id

         LEFT JOIN meeting_attendance ma
            ON ma.user_id = u.user_id
            AND ma.meeting_id = ?

         WHERE u.status = 'active'
         AND u.role_id NOT IN (1, 2)

         ORDER BY
            d.department_name ASC,
            u.name ASC"
    );

    $stmt->execute([
        $meetingId
    ]);

    echo json_encode(
        [
            'status' => 'success',
            'rows' =>
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                )
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/* =======================================================
🔎 GET MEETING
======================================================= */

if ($action === 'get') {

    $id =
        (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {

        jsonResponse(
            'error',
            'รหัสการประชุมไม่ถูกต้อง'
        );
    }

    $stmt = $db->prepare(
        'SELECT
            meeting_id,
            meeting_title,
            report_header,
            meeting_number,
            meeting_date,
            meeting_time,
            meeting_location,
            meeting_link,
            meeting_status
         FROM meeting
         WHERE meeting_id = ?'
    );

    $stmt->execute([
        $id
    ]);

    $meeting =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$meeting) {

        jsonResponse(
            'error',
            'ไม่พบข้อมูลการประชุม'
        );
    }

    echo json_encode(
        $meeting,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/* =======================================================
👤 GET ATTENDANCE
======================================================= */

if ($action === 'get_attendance') {

    $meetingId =
        (int) ($_GET['id'] ?? 0);

    if ($meetingId <= 0) {

        jsonResponse(
            'error',
            'รหัสการประชุมไม่ถูกต้อง'
        );
    }

    $stmtMeeting = $db->prepare(
        'SELECT
            meeting_id,
            meeting_title,
            meeting_date,
            meeting_time,
            meeting_location
         FROM meeting
         WHERE meeting_id = ?'
    );

    $stmtMeeting->execute([
        $meetingId
    ]);

    $meeting =
        $stmtMeeting->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$meeting) {

        jsonResponse(
            'error',
            'ไม่พบข้อมูลการประชุม'
        );
    }


    $stmt = $db->prepare(
        "SELECT
            u.user_id,
            u.name,
            u.email,

            COALESCE(
                p.position_name,
                '-'
            ) AS position_name,

            COALESCE(
                d.department_name,
                '-'
            ) AS department_name,

            COALESCE(
                ma.attendance_role,
                CASE
                    WHEN u.role_id = 1
                    THEN 'chairman'
                    ELSE 'member'
                END
            ) AS attendance_role,

            COALESCE(
                ma.attendance_status,
                'pending'
            ) AS attendance_status,

            ma.representative_name,
            ma.representative_position,
            ma.attendance_remark,
            ma.checkin_time,

            CASE
                WHEN ma.attendance_id IS NULL
                     AND u.role_id != 1
                THEN 0
                ELSE 1
            END AS is_included

         FROM user u

         LEFT JOIN positions p
            ON p.position_id = u.position_id

         LEFT JOIN departments d
            ON d.department_id = u.department_id

         LEFT JOIN meeting_attendance ma
            ON ma.user_id = u.user_id
            AND ma.meeting_id = ?

         WHERE u.status = 'active'
         AND u.role_id != 2

         ORDER BY

            CASE COALESCE(
                ma.attendance_role,
                CASE
                    WHEN u.role_id = 1
                    THEN 'chairman'
                    ELSE 'member'
                END
            )

                WHEN 'chairman'
                THEN 1

                WHEN 'secretary'
                THEN 2

                WHEN 'member'
                THEN 3

                ELSE 4

            END,

            u.name ASC"
    );

    $stmt->execute([
        $meetingId
    ]);

    echo json_encode(
        [
            'status' => 'success',
            'meeting' => $meeting,
            'rows' =>
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                )
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/* =======================================================
📝 SAVE ATTENDANCE
======================================================= */

if ($action === 'save_attendance') {

    try {

        $meetingId =
            (int) ($_POST['meeting_id'] ?? 0);

        $rows = json_decode(
            $_POST['attendance_rows'] ?? '[]',
            true
        );

        if (
            $meetingId <= 0 ||
            !is_array($rows)
        ) {

            jsonResponse(
                'error',
                'ข้อมูลรายงานการประชุมไม่ถูกต้อง'
            );
        }


        /*
         * ตรวจ Meeting
         */
        $stmtCheckMeeting =
            $db->prepare(
                'SELECT meeting_id
                 FROM meeting
                 WHERE meeting_id = ?'
            );

        $stmtCheckMeeting->execute([
            $meetingId
        ]);

        if (!$stmtCheckMeeting->fetchColumn()) {

            throw new RuntimeException(
                'ไม่พบข้อมูลการประชุม'
            );
        }


        /*
         * Admin
         */
        $stmtAdmins = $db->query(
            'SELECT user_id
             FROM user
             WHERE role_id = 1'
        );

        $adminIds = array_map(
            'intval',
            $stmtAdmins->fetchAll(
                PDO::FETCH_COLUMN
            )
        );


        $allowedRoles = [
            'chairman',
            'member',
            'secretary',
            'observer'
        ];

        $allowedStatuses = [
            'pending',
            'present',
            'absent',
            'representative'
        ];


        $db->beginTransaction();


        $stmt = $db->prepare(
            "INSERT INTO meeting_attendance
                (
                    meeting_id,
                    user_id,
                    attendance_role,
                    rsvp_status,
                    attendance_status,
                    representative_name,
                    representative_position,
                    attendance_remark,
                    is_present,
                    checkin_time
                )

             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)

             ON DUPLICATE KEY UPDATE

                attendance_role =
                    VALUES(attendance_role),

                rsvp_status =
                    VALUES(rsvp_status),

                attendance_status =
                    VALUES(attendance_status),

                representative_name =
                    VALUES(representative_name),

                representative_position =
                    VALUES(representative_position),

                attendance_remark =
                    VALUES(attendance_remark),

                is_present =
                    VALUES(is_present),

                checkin_time =
                    VALUES(checkin_time)"
        );


        $stmtDeleteAttendance =
            $db->prepare(
                'DELETE FROM meeting_attendance
                 WHERE meeting_id = ?
                 AND user_id = ?'
            );


        foreach ($rows as $row) {

            $userId =
                (int) ($row['user_id'] ?? 0);

            if ($userId <= 0) {
                continue;
            }

            $isAdmin =
                in_array(
                    $userId,
                    $adminIds,
                    true
                );

            $included =
                !empty($row['included']);


            /*
             * ห้ามลบ Admin
             */
            if (
                !$included &&
                !$isAdmin
            ) {

                $stmtDeleteAttendance->execute([
                    $meetingId,
                    $userId
                ]);

                continue;
            }


            /*
             * Admin = Chairman เสมอ
             */
            $role =
                $isAdmin
                    ? 'chairman'
                    : (
                        in_array(
                            $row['attendance_role'] ?? '',
                            $allowedRoles,
                            true
                        )
                            ? $row['attendance_role']
                            : 'member'
                    );


            $status =
                in_array(
                    $row['attendance_status'] ?? '',
                    $allowedStatuses,
                    true
                )
                    ? $row['attendance_status']
                    : 'pending';


            $representativeName =
                trim(
                    (string) (
                        $row['representative_name']
                        ?? ''
                    )
                );

            $representativePosition =
                trim(
                    (string) (
                        $row['representative_position']
                        ?? ''
                    )
                );

            $remark =
                trim(
                    (string) (
                        $row['attendance_remark']
                        ?? ''
                    )
                );


            if (
                $status !== 'representative'
            ) {

                $representativeName = '';
                $representativePosition = '';
            }


            $rsvpStatus = match ($status) {

                'present',
                'representative'
                    => 'attending',

                'absent'
                    => 'declined',

                default
                    => 'pending'
            };


            $isPresent =
                $status === 'present'
                    ? 1
                    : 0;


            $checkinTime =
                $status === 'present'
                    ? date('Y-m-d H:i:s')
                    : null;


            $stmt->execute([
                $meetingId,
                $userId,
                $role,
                $rsvpStatus,
                $status,

                $representativeName !== ''
                    ? $representativeName
                    : null,

                $representativePosition !== ''
                    ? $representativePosition
                    : null,

                $remark !== ''
                    ? $remark
                    : null,

                $isPresent,
                $checkinTime
            ]);
        }


        $db->commit();


        jsonResponse(
            'success',
            'บันทึกรายงานผู้เข้าร่วมประชุมเรียบร้อยแล้ว'
        );

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        jsonResponse(
            'error',
            'บันทึกรายงานไม่สำเร็จ: ' .
            $e->getMessage()
        );
    }
}


/* =======================================================
➕ CREATE MEETING
======================================================= */

if ($action === 'create') {

    $uploadedPaths = [];
    $pendingEmails = [];

    try {

        $db->beginTransaction();


        $title =
            trim(
                (string) (
                    $_POST['meeting_title']
                    ?? ''
                )
            );

        $reportHeader =
            trim(
                (string) (
                    $_POST['report_header']
                    ?? ''
                )
            );

        $meetingNumber =
            trim(
                (string) (
                    $_POST['meeting_number']
                    ?? ''
                )
            );

        $date =
            trim(
                (string) (
                    $_POST['meeting_date']
                    ?? ''
                )
            );

        $time =
            trim(
                (string) (
                    $_POST['meeting_time']
                    ?? ''
                )
            );

        $location =
            trim(
                (string) (
                    $_POST['meeting_location']
                    ?? ''
                )
            );

        $link =
            trim(
                (string) (
                    $_POST['meeting_link']
                    ?? ''
                )
            );

        $invitedUserIds =
            parseInvitedUserIds();

        $userId =
            (int) (
                $_SESSION['user_id']
                ?? 0
            );


        if (
            $title === '' ||
            $date === '' ||
            $time === '' ||
            $location === ''
        ) {

            throw new InvalidArgumentException(
                'กรุณากรอกข้อมูลการประชุมที่จำเป็นให้ครบ'
            );
        }


        if ($userId <= 0) {

            throw new RuntimeException(
                'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่'
            );
        }


        /*
         * INSERT MEETING
         */
        $stmt = $db->prepare(
            "INSERT INTO meeting
                (
                    meeting_title,
                    report_header,
                    meeting_number,
                    meeting_date,
                    meeting_time,
                    meeting_location,
                    meeting_link,
                    meeting_status,
                    user_id
                )

             VALUES (?, ?, ?, ?, ?, ?, ?, 'upcoming', ?)"
        );

        $stmt->execute([
            $title,

            $reportHeader !== ''
                ? $reportHeader
                : null,

            $meetingNumber !== ''
                ? $meetingNumber
                : null,

            $date,
            $time,
            $location,

            $link !== ''
                ? $link
                : null,

            $userId
        ]);


        $meetingId =
            (int) $db->lastInsertId();


        /*
         * AGENDA
         */
        $stmtAgenda = $db->prepare(
            'INSERT INTO agenda
                (
                    agenda_title,
                    agenda_detail,
                    meeting_id,
                    order_index,
                    admin_status,
                    submitted_by,
                    department_id
                )
             VALUES (?, ?, ?, ?, "approved", NULL, NULL)'
        );


        foreach (
            parseAgendas()
            as $index => $agenda
        ) {

            $agendaTitle =
                trim(
                    (string) (
                        $agenda['title']
                        ?? ''
                    )
                );

            if ($agendaTitle === '') {
                continue;
            }


            $key =
                cleanAgendaKey(
                    (string) (
                        $agenda['key']
                        ?? ('new_' . $index)
                    )
                );

            $detail =
                trim(
                    (string) (
                        $agenda['detail']
                        ?? ''
                    )
                );

            $orderIndex =
                max(
                    1,
                    (int) (
                        $agenda['order_index']
                        ?? ($index + 1)
                    )
                );


            $stmtAgenda->execute([
                $agendaTitle,
                $detail,
                $meetingId,
                $orderIndex
            ]);


            $agendaId =
                (int) $db->lastInsertId();


            $uploadedPaths = array_merge(
                $uploadedPaths,

                uploadAgendaDocuments(
                    $db,
                    $meetingId,
                    $agendaId,
                    'agenda_files_' . $key
                )
            );
        }


        /*
         * MEETING DOCUMENTS
         */
        $uploadedPaths = array_merge(
            $uploadedPaths,

            uploadMeetingDocuments(
                $db,
                $meetingId,
                $title
            )
        );


        /*
         * INVITATIONS
         */
        syncMeetingInvitations(
            $db,
            $meetingId,
            $invitedUserIds,
            $title,
            $date,
            $time,
            $location,
            $pendingEmails
        );


        $db->commit();


        sendPendingEmails(
            $pendingEmails
        );


        jsonResponse(
            'success',
            'เพิ่มข้อมูลการประชุมเรียบร้อยแล้ว',
            [
                'meeting_id' =>
                    $meetingId
            ]
        );

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        unlinkStoredFiles(
            $uploadedPaths
        );

        jsonResponse(
            'error',
            'เกิดข้อผิดพลาด: ' .
            $e->getMessage()
        );
    }
}


/* =======================================================
✏️ UPDATE MEETING
======================================================= */

if ($action === 'vote_result') {
    $agendaId = (int) ($_GET['agenda_id'] ?? 0);
    if ($agendaId <= 0) {
        jsonResponse('error', 'รหัสวาระไม่ถูกต้อง');
    }

    $stmtAgenda = $db->prepare(
        'SELECT agenda_id
         FROM agenda
         WHERE agenda_id = ?
         LIMIT 1'
    );
    $stmtAgenda->execute([$agendaId]);
    if (!$stmtAgenda->fetchColumn()) {
        jsonResponse('error', 'ไม่พบวาระการประชุม');
    }

    $stmtVotes = $db->prepare(
        'SELECT vote_type, COUNT(*) AS vote_count
         FROM agenda_votes
         WHERE agenda_id = ?
         GROUP BY vote_type'
    );
    $stmtVotes->execute([$agendaId]);
    $counts = ['approve' => 0, 'reject' => 0, 'abstain' => 0];
    foreach ($stmtVotes->fetchAll(PDO::FETCH_ASSOC) as $vote) {
        $counts[(string) $vote['vote_type']] = (int) $vote['vote_count'];
    }

    jsonResponse('success', '', [
        'approve' => $counts['approve'],
        'reject' => $counts['reject'],
        'abstain' => $counts['abstain'],
        'my_vote' => null,
    ]);
}

if ($action === 'open_voting' || $action === 'close_voting') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'ไม่รองรับวิธีการร้องขอนี้');
    }

    $agendaId = (int) ($_POST['agenda_id'] ?? 0);
    $targetStatus = $action === 'open_voting' ? 'voting' : 'closed';
    $requiredStatus = $action === 'open_voting' ? 'pending' : 'voting';

    if ($agendaId <= 0) {
        jsonResponse('error', 'รหัสวาระไม่ถูกต้อง');
    }

    try {
        $db->beginTransaction();
        $stmtAgenda = $db->prepare(
            'SELECT a.agenda_status, m.meeting_status
             FROM agenda a
             INNER JOIN meeting m ON m.meeting_id = a.meeting_id
             WHERE a.agenda_id = ?
             FOR UPDATE'
        );
        $stmtAgenda->execute([$agendaId]);
        $agenda = $stmtAgenda->fetch(PDO::FETCH_ASSOC);

        if (!$agenda) {
            throw new RuntimeException('ไม่พบวาระการประชุม');
        }
        if ($agenda['meeting_status'] !== 'ongoing') {
            throw new RuntimeException('สามารถจัดการลงมติได้เฉพาะระหว่างการประชุม');
        }
        if ($agenda['agenda_status'] !== $requiredStatus) {
            throw new RuntimeException('สถานะวาระไม่ถูกต้องสำหรับการดำเนินการนี้');
        }

        $stmtUpdateAgenda = $db->prepare(
            'UPDATE agenda
             SET agenda_status = ?
             WHERE agenda_id = ?
               AND agenda_status = ?'
        );
        $stmtUpdateAgenda->execute([$targetStatus, $agendaId, $requiredStatus]);
        $db->commit();
        jsonResponse('success', $action === 'open_voting' ? 'เปิดลงมติเรียบร้อยแล้ว' : 'ปิดการลงมติเรียบร้อยแล้ว');
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $code = $error instanceof RuntimeException ? 409 : 500;
        jsonResponse('error', $error->getMessage(), []);
    }
}

if ($action === 'update') {

    $uploadedPaths = [];
    $filesToDeleteAfterCommit = [];
    $pendingEmails = [];

    try {

        $db->beginTransaction();


        $meetingId =
            (int) (
                $_POST['meeting_id']
                ?? 0
            );

        $title =
            trim(
                (string) (
                    $_POST['meeting_title']
                    ?? ''
                )
            );

        $reportHeader =
            trim(
                (string) (
                    $_POST['report_header']
                    ?? ''
                )
            );

        $meetingNumber =
            trim(
                (string) (
                    $_POST['meeting_number']
                    ?? ''
                )
            );

        $date =
            trim(
                (string) (
                    $_POST['meeting_date']
                    ?? ''
                )
            );

        $time =
            trim(
                (string) (
                    $_POST['meeting_time']
                    ?? ''
                )
            );

        $location =
            trim(
                (string) (
                    $_POST['meeting_location']
                    ?? ''
                )
            );

        $link =
            trim(
                (string) (
                    $_POST['meeting_link']
                    ?? ''
                )
            );

        $status =
            (string) (
                $_POST['meeting_status']
                ?? 'upcoming'
            );

        $invitedUserIds =
            parseInvitedUserIds();


        if (
            !in_array(
                $status,
                [
                    'upcoming',
                    'ongoing',
                    'closed'
                ],
                true
            )
        ) {

            $status = 'upcoming';
        }


        if (
            $meetingId <= 0 ||
            $title === '' ||
            $date === '' ||
            $time === '' ||
            $location === ''
        ) {

            throw new InvalidArgumentException(
                'ข้อมูลการประชุมไม่ครบหรือไม่ถูกต้อง'
            );
        }


        /*
         * CHECK MEETING
         */
        $stmtCheckMeeting =
            $db->prepare(
                'SELECT meeting_id
                 FROM meeting
                 WHERE meeting_id = ?'
            );

        $stmtCheckMeeting->execute([
            $meetingId
        ]);

        if (!$stmtCheckMeeting->fetchColumn()) {

            throw new RuntimeException(
                'ไม่พบข้อมูลการประชุม'
            );
        }


        /*
         * UPDATE INVITATIONS
         */
        syncMeetingInvitations(
            $db,
            $meetingId,
            $invitedUserIds,
            $title,
            $date,
            $time,
            $location,
            $pendingEmails
        );


        /*
         * UPDATE MEETING
         */
        $stmt = $db->prepare(
            'UPDATE meeting
             SET
                meeting_title = ?,
                report_header = ?,
                meeting_number = ?,
                meeting_date = ?,
                meeting_time = ?,
                meeting_location = ?,
                meeting_link = ?,
                meeting_status = ?
             WHERE meeting_id = ?'
        );

        $stmt->execute([
            $title,

            $reportHeader !== ''
                ? $reportHeader
                : null,

            $meetingNumber !== ''
                ? $meetingNumber
                : null,

            $date,
            $time,
            $location,

            $link !== ''
                ? $link
                : null,

            $status,
            $meetingId
        ]);


        /*
         * EXISTING AGENDAS
         */
        $stmtExisting =
            $db->prepare(
                'SELECT agenda_id
                 FROM agenda
                 WHERE meeting_id = ?
                 AND (
                    submitted_by IS NULL
                    OR admin_status = "approved"
                 )'
            );

        $stmtExisting->execute([
            $meetingId
        ]);

        $existingAgendaIds =
            array_map(
                'intval',
                $stmtExisting->fetchAll(
                    PDO::FETCH_COLUMN
                )
            );

        $existingLookup =
            array_fill_keys(
                $existingAgendaIds,
                true
            );

        $keptAgendaIds = [];


        $stmtUpdateAgenda =
            $db->prepare(
                'UPDATE agenda
                 SET
                    agenda_title = ?,
                    agenda_detail = ?,
                    order_index = ?
                 WHERE agenda_id = ?
                 AND meeting_id = ?'
            );


        $stmtInsertAgenda =
            $db->prepare(
                'INSERT INTO agenda
                    (
                        agenda_title,
                        agenda_detail,
                        meeting_id,
                        order_index,
                        admin_status,
                        submitted_by,
                        department_id
                    )
                 VALUES (
                    ?, ?, ?, ?, "approved", NULL, NULL
                 )'
            );


        /*
         * PROCESS AGENDAS
         */
        foreach (
            parseAgendas()
            as $index => $agenda
        ) {

            $agendaTitle =
                trim(
                    (string) (
                        $agenda['title']
                        ?? ''
                    )
                );

            if ($agendaTitle === '') {
                continue;
            }


            $agendaId =
                (int) (
                    $agenda['agenda_id']
                    ?? 0
                );


            $key =
                cleanAgendaKey(
                    (string) (
                        $agenda['key']
                        ?? ('agenda_' . $index)
                    )
                );


            $detail =
                trim(
                    (string) (
                        $agenda['detail']
                        ?? ''
                    )
                );


            $orderIndex =
                max(
                    1,
                    (int) (
                        $agenda['order_index']
                        ?? ($index + 1)
                    )
                );


            /*
             * UPDATE EXISTING
             */
            if (
                $agendaId > 0 &&
                isset(
                    $existingLookup[$agendaId]
                )
            ) {

                $stmtUpdateAgenda->execute([
                    $agendaTitle,
                    $detail,
                    $orderIndex,
                    $agendaId,
                    $meetingId
                ]);

            } else {

                /*
                 * INSERT NEW
                 */
                $stmtInsertAgenda->execute([
                    $agendaTitle,
                    $detail,
                    $meetingId,
                    $orderIndex
                ]);

                $agendaId =
                    (int) $db->lastInsertId();
            }


            $keptAgendaIds[] =
                $agendaId;


            /*
             * Upload Agenda Files
             */
            $uploadedPaths = array_merge(
                $uploadedPaths,

                uploadAgendaDocuments(
                    $db,
                    $meetingId,
                    $agendaId,
                    'agenda_files_' . $key
                )
            );
        }


        /*
         * DELETE SELECTED AGENDA DOCUMENTS
         */
        $deletedDocumentIds =
            json_decode(
                $_POST[
                    'deleted_agenda_document_ids'
                ] ?? '[]',
                true
            );

        if (
            is_array(
                $deletedDocumentIds
            )
        ) {

            $stmtFindDocument =
                $db->prepare(
                    'SELECT ad.file_path
                     FROM agenda_documents ad
                     INNER JOIN agenda a
                        ON a.agenda_id = ad.agenda_id
                     WHERE
                        ad.agenda_document_id = ?
                        AND a.meeting_id = ?'
                );


            $stmtDeleteDocument =
                $db->prepare(
                    'DELETE ad
                     FROM agenda_documents ad
                     INNER JOIN agenda a
                        ON a.agenda_id = ad.agenda_id
                     WHERE
                        ad.agenda_document_id = ?
                        AND a.meeting_id = ?'
                );


            foreach (
                $deletedDocumentIds
                as $documentId
            ) {

                $documentId =
                    (int) $documentId;

                if ($documentId <= 0) {
                    continue;
                }


                $stmtFindDocument->execute([
                    $documentId,
                    $meetingId
                ]);

                $path =
                    $stmtFindDocument->fetchColumn();


                if ($path) {

                    $filesToDeleteAfterCommit[] =
                        $path;

                    $stmtDeleteDocument->execute([
                        $documentId,
                        $meetingId
                    ]);
                }
            }
        }


        /*
         * DELETE REMOVED AGENDAS
         */
        $agendaIdsToDelete =
            array_values(
                array_diff(
                    $existingAgendaIds,
                    $keptAgendaIds
                )
            );


        if (!empty($agendaIdsToDelete)) {

            $stmtAgendaDocs =
                $db->prepare(
                    'SELECT file_path
                     FROM agenda_documents
                     WHERE agenda_id = ?'
                );


            $stmtDeleteAgendaDocs =
                $db->prepare(
                    'DELETE FROM agenda_documents
                     WHERE agenda_id = ?'
                );


            $stmtDeleteAgenda =
                $db->prepare(
                    'DELETE FROM agenda
                     WHERE agenda_id = ?
                     AND meeting_id = ?
                     AND (
                        submitted_by IS NULL
                        OR admin_status = "approved"
                     )'
                );


            foreach (
                $agendaIdsToDelete
                as $agendaId
            ) {

                /*
                 * Files
                 */
                $stmtAgendaDocs->execute([
                    $agendaId
                ]);

                $agendaFiles =
                    $stmtAgendaDocs->fetchAll(
                        PDO::FETCH_COLUMN
                    );

                $filesToDeleteAfterCommit =
                    array_merge(
                        $filesToDeleteAfterCommit,
                        $agendaFiles
                    );


                /*
                 * User Resolution
                 */
                $db->prepare(
                    'DELETE ur
                     FROM user_resolution ur
                     INNER JOIN resolution r
                        ON r.resolution_id =
                           ur.resolution_id
                     WHERE r.agenda_id = ?'
                )->execute([
                    $agendaId
                ]);


                /*
                 * Resolution
                 */
                $db->prepare(
                    'DELETE FROM resolution
                     WHERE agenda_id = ?'
                )->execute([
                    $agendaId
                ]);


                /*
                 * Comment
                 */
                $db->prepare(
                    'DELETE FROM comment
                     WHERE agenda_id = ?'
                )->execute([
                    $agendaId
                ]);


                /*
                 * Agenda Documents
                 */
                $stmtDeleteAgendaDocs->execute([
                    $agendaId
                ]);


                /*
                 * Agenda
                 */
                $stmtDeleteAgenda->execute([
                    $agendaId,
                    $meetingId
                ]);
            }
        }


        /*
         * MEETING DOCUMENTS
         *
         * ถ้ามีไฟล์ใหม่เข้ามา
         * จะลบไฟล์เก่าทิ้ง
         */
        if (
            isset($_FILES['meeting_documents']) &&
            is_array(
                $_FILES['meeting_documents']
            )
        ) {

            $errors =
                $_FILES['meeting_documents']['error']
                ?? [];

            $hasUpload =
                is_array($errors)
                    ? in_array(
                        UPLOAD_ERR_OK,
                        $errors,
                        true
                    )
                    : (
                        (int) $errors ===
                        UPLOAD_ERR_OK
                    );


            if ($hasUpload) {

                $newDocs =
                    uploadMeetingDocuments(
                        $db,
                        $meetingId,
                        $title
                    );


                if (!empty($newDocs)) {

                    $placeholders =
                        implode(
                            ',',
                            array_fill(
                                0,
                                count($newDocs),
                                '?'
                            )
                        );


                    /*
                     * หาไฟล์เก่า
                     */
                    $stmtGetDocs =
                        $db->prepare(
                            "SELECT file_path
                             FROM documents
                             WHERE meeting_id = ?
                             AND file_path NOT IN (
                                $placeholders
                             )"
                        );


                    $stmtGetDocs->execute(
                        array_merge(
                            [$meetingId],
                            $newDocs
                        )
                    );


                    $oldFiles =
                        $stmtGetDocs->fetchAll(
                            PDO::FETCH_COLUMN
                        );


                    $filesToDeleteAfterCommit =
                        array_merge(
                            $filesToDeleteAfterCommit,
                            $oldFiles
                        );


                    /*
                     * ลบ DB records เก่า
                     */
                    $stmtDeleteOldDocs =
                        $db->prepare(
                            "DELETE FROM documents
                             WHERE meeting_id = ?
                             AND file_path NOT IN (
                                $placeholders
                             )"
                        );


                    $stmtDeleteOldDocs->execute(
                        array_merge(
                            [$meetingId],
                            $newDocs
                        )
                    );


                    $uploadedPaths =
                        array_merge(
                            $uploadedPaths,
                            $newDocs
                        );
                }
            }
        }


        $db->commit();


        sendPendingEmails(
            $pendingEmails
        );


        /*
         * ลบไฟล์จริงหลัง commit
         */
        unlinkStoredFiles(
            $filesToDeleteAfterCommit
        );


        jsonResponse(
            'success',
            'แก้ไขข้อมูลการประชุมและรายชื่อผู้ได้รับเชิญเรียบร้อยแล้ว'
        );

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        /*
         * ลบเฉพาะไฟล์ที่ upload ใหม่
         */
        unlinkStoredFiles(
            $uploadedPaths
        );


        jsonResponse(
            'error',
            'เกิดข้อผิดพลาดในการแก้ไข: ' .
            $e->getMessage()
        );
    }
}


/* =======================================================
🗑️ DELETE MEETING
======================================================= */

if ($action === 'delete') {

    $foldersToDeleteAfterCommit = [];
    $filesToDeleteAfterCommit = [];

    try {

        $meetingId =
            (int) (
                $_POST['id']
                ?? 0
            );


        if ($meetingId <= 0) {

            throw new InvalidArgumentException(
                'รหัสการประชุมไม่ถูกต้อง'
            );
        }


        /*
         * Meeting
         */
        $stmtMeetingInfo =
            $db->prepare(
                'SELECT meeting_title
                 FROM meeting
                 WHERE meeting_id = ?'
            );

        $stmtMeetingInfo->execute([
            $meetingId
        ]);

        $meetingInfo =
            $stmtMeetingInfo->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$meetingInfo) {

            throw new RuntimeException(
                'ไม่พบข้อมูลการประชุม'
            );
        }


        $db->beginTransaction();


        /*
         * Meeting folder
         */
        $uploadsBase =
            __DIR__ .
            '/../../../public/uploads/meetings/';


        $meetingFolder =
            $uploadsBase .
            'meeting_' .
            $meetingId;


        if (is_dir($meetingFolder)) {

            $foldersToDeleteAfterCommit[] =
                $meetingFolder;
        }


        /*
         * Meeting documents
         */
        $stmtMeetingDocs =
            $db->prepare(
                'SELECT file_path
                 FROM documents
                 WHERE meeting_id = ?'
            );

        $stmtMeetingDocs->execute([
            $meetingId
        ]);


        $filesToDeleteAfterCommit =
            array_merge(
                $filesToDeleteAfterCommit,
                $stmtMeetingDocs->fetchAll(
                    PDO::FETCH_COLUMN
                )
            );


        /*
         * Agenda documents
         */
        $stmtAgendaDocs =
            $db->prepare(
                'SELECT ad.file_path
                 FROM agenda_documents ad
                 INNER JOIN agenda a
                    ON a.agenda_id = ad.agenda_id
                 WHERE a.meeting_id = ?'
            );

        $stmtAgendaDocs->execute([
            $meetingId
        ]);


        $filesToDeleteAfterCommit =
            array_merge(
                $filesToDeleteAfterCommit,
                $stmtAgendaDocs->fetchAll(
                    PDO::FETCH_COLUMN
                )
            );


        /*
         * User Resolution
         */
        $db->prepare(
            'DELETE ur
             FROM user_resolution ur
             INNER JOIN resolution r
                ON r.resolution_id =
                   ur.resolution_id
             INNER JOIN agenda a
                ON a.agenda_id = r.agenda_id
             WHERE a.meeting_id = ?'
        )->execute([
            $meetingId
        ]);


        /*
         * Resolution
         */
        $db->prepare(
            'DELETE r
             FROM resolution r
             INNER JOIN agenda a
                ON a.agenda_id = r.agenda_id
             WHERE a.meeting_id = ?'
        )->execute([
            $meetingId
        ]);


        /*
         * Comments
         */
        $db->prepare(
            'DELETE c
             FROM comment c
             INNER JOIN agenda a
                ON a.agenda_id = c.agenda_id
             WHERE a.meeting_id = ?'
        )->execute([
            $meetingId
        ]);


        /*
         * Agenda documents
         */
        $db->prepare(
            'DELETE ad
             FROM agenda_documents ad
             INNER JOIN agenda a
                ON a.agenda_id = ad.agenda_id
             WHERE a.meeting_id = ?'
        )->execute([
            $meetingId
        ]);


        /*
         * Meeting documents
         */
        $db->prepare(
            'DELETE FROM documents
             WHERE meeting_id = ?'
        )->execute([
            $meetingId
        ]);


        /*
         * Notifications
         */
        $db->prepare(
            'DELETE FROM notifications
             WHERE meeting_id = ?'
        )->execute([
            $meetingId
        ]);


        /*
         * Attendance
         */
        $db->prepare(
            'DELETE FROM meeting_attendance
             WHERE meeting_id = ?'
        )->execute([
            $meetingId
        ]);


        /*
         * Agenda
         */
        $db->prepare(
            'DELETE FROM agenda
             WHERE meeting_id = ?'
        )->execute([
            $meetingId
        ]);


        /*
         * Meeting
         */
        $controller->delete(
            $meetingId
        );


        $db->commit();


        /*
         * Delete physical files
         */
        foreach (
            array_unique(
                $foldersToDeleteAfterCommit
            )
            as $folder
        ) {

            deleteDirectoryRecursive(
                $folder
            );
        }


        unlinkStoredFiles(
            $filesToDeleteAfterCommit
        );


        jsonResponse(
            'success',
            'ลบการประชุม รายงานผู้เข้าร่วม และไฟล์แนบ/โฟลเดอร์ทั้งหมดแล้ว'
        );

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        jsonResponse(
            'error',
            'ไม่สามารถลบข้อมูลได้: ' .
            $e->getMessage()
        );
    }
}


/* =======================================================
❌ UNKNOWN ACTION
======================================================= */

jsonResponse(
    'error',
    'ไม่พบคำสั่งที่ต้องการ'
);