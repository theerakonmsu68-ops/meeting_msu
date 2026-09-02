<?php
/* ============================================================
 * API สำหรับหน้าผู้ใช้งานระดับภาควิชา
 * Path แนะนำ: /Meeting_msu/app/controllers/department_meeting_api.php
 * ============================================================ */

require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow([3,4]);

require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$db = (new Database())->connect();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$action = (string) ($_GET['action'] ?? '');

function jsonResponse(string $status, string $message = '', array $extra = [], int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestData(): array
{
    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (str_contains(strtolower($contentType), 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function requireCsrf(): void
{
    $sessionToken = (string) ($_SESSION['department_meeting_csrf'] ?? '');
    $requestToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        jsonResponse('error', 'โทเคนความปลอดภัยไม่ถูกต้อง กรุณาโหลดหน้าใหม่', [], 419);
    }
}

function notifyMeetingOwner(PDO $db, int $meetingId, string $title, string $message): void
{
    $stmt = $db->prepare('SELECT user_id FROM meeting WHERE meeting_id = ? LIMIT 1');
    $stmt->execute([$meetingId]);
    $ownerId = (int) $stmt->fetchColumn();
    if ($ownerId <= 0) {
        return;
    }

    $stmtNotify = $db->prepare(
        'INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)'
    );
    $stmtNotify->execute([$ownerId, $title, $message]);
}

if ($userId <= 0) {
    jsonResponse('error', 'กรุณาเข้าสู่ระบบใหม่', [], 401);
}

if ($action === 'detail') {
    $meetingId = filter_input(INPUT_GET, 'meeting_id', FILTER_VALIDATE_INT) ?: 0;
    if ($meetingId <= 0) {
        jsonResponse('error', 'รหัสการประชุมไม่ถูกต้อง', [], 422);
    }

    // ต้องมีรายชื่ออยู่ใน meeting_attendance เท่านั้นจึงจะเปิดดูได้
    $stmtMeeting = $db->prepare(
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
            ma.is_present,
            ma.checkin_time
         FROM meeting_attendance ma
         INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
         WHERE ma.meeting_id = ? AND ma.user_id = ?
         LIMIT 1"
    );
    $stmtMeeting->execute([$meetingId, $userId]);
    $meeting = $stmtMeeting->fetch(PDO::FETCH_ASSOC);

    if (!$meeting) {
        jsonResponse('error', 'คุณไม่มีรายชื่ออยู่ในคำเชิญของการประชุมนี้', [], 403);
    }

    $stmtDocuments = $db->prepare(
        "SELECT document_id, document_name, file_path, upload_date
         FROM documents
         WHERE meeting_id = ?
         ORDER BY upload_date ASC, document_id ASC"
    );
    $stmtDocuments->execute([$meetingId]);
    $meetingDocuments = $stmtDocuments->fetchAll(PDO::FETCH_ASSOC);

    $stmtAgendas = $db->prepare(
        "SELECT agenda_id, agenda_title, agenda_detail, order_index, agenda_type, agenda_status
         FROM agenda
         WHERE meeting_id = ?
         ORDER BY order_index ASC, agenda_id ASC"
    );
    $stmtAgendas->execute([$meetingId]);
    $agendas = $stmtAgendas->fetchAll(PDO::FETCH_ASSOC);

    if ($agendas) {
        $agendaIds = array_map('intval', array_column($agendas, 'agenda_id'));
        $placeholders = implode(',', array_fill(0, count($agendaIds), '?'));
        $stmtAgendaDocuments = $db->prepare(
            "SELECT agenda_document_id, agenda_id, document_name, file_path, file_size, mime_type, upload_date
             FROM agenda_documents
             WHERE agenda_id IN ({$placeholders})
             ORDER BY upload_date ASC, agenda_document_id ASC"
        );
        $stmtAgendaDocuments->execute($agendaIds);

        $documentsByAgenda = [];
        foreach ($stmtAgendaDocuments->fetchAll(PDO::FETCH_ASSOC) as $document) {
            $documentsByAgenda[(int) $document['agenda_id']][] = $document;
        }

        foreach ($agendas as &$agenda) {
            $agenda['documents'] = $documentsByAgenda[(int) $agenda['agenda_id']] ?? [];
        }
        unset($agenda);
    }


    // โหลดความคิดเห็นของแต่ละวาระ
    $commentsByAgenda = [];
    if ($agendas) {
        $agendaIds = array_map('intval', array_column($agendas, 'agenda_id'));
        $placeholders = implode(',', array_fill(0, count($agendaIds), '?'));

        $stmtComments = $db->prepare(
            "SELECT c.comment_id, c.comment_detail, c.comment_date,
                    c.user_id, c.agenda_id, u.name AS user_name
             FROM comment c
             LEFT JOIN user u ON u.user_id = c.user_id
             WHERE c.agenda_id IN ({$placeholders})
             ORDER BY c.comment_id ASC"
        );
        $stmtComments->execute($agendaIds);

        foreach ($stmtComments->fetchAll(PDO::FETCH_ASSOC) as $comment) {
            $comment['is_owner'] = (int) $comment['user_id'] === $userId;
            $commentsByAgenda[(int)$comment['agenda_id']][] = $comment;
        }

        foreach ($agendas as &$agenda) {
            $agenda['comments'] = $commentsByAgenda[(int)$agenda['agenda_id']] ?? [];
        }
        unset($agenda);

        $stmtVotes = $db->prepare(
            "SELECT agenda_id, vote_type, COUNT(*) AS vote_count
             FROM agenda_votes
             WHERE agenda_id IN ({$placeholders})
             GROUP BY agenda_id, vote_type"
        );
        $stmtVotes->execute($agendaIds);
        $voteCounts = [];
        foreach ($stmtVotes->fetchAll(PDO::FETCH_ASSOC) as $vote) {
            $voteCounts[(int) $vote['agenda_id']][(string) $vote['vote_type']] = (int) $vote['vote_count'];
        }

        $stmtMyVotes = $db->prepare(
            "SELECT agenda_id, vote_type
             FROM agenda_votes
             WHERE user_id = ? AND agenda_id IN ({$placeholders})"
        );
        $stmtMyVotes->execute(array_merge([$userId], $agendaIds));
        $myVotes = [];
        foreach ($stmtMyVotes->fetchAll(PDO::FETCH_ASSOC) as $vote) {
            $myVotes[(int) $vote['agenda_id']] = (string) $vote['vote_type'];
        }

        foreach ($agendas as &$agenda) {
            $agendaId = (int) $agenda['agenda_id'];
            $agenda['vote_counts'] = [
                'approve' => $voteCounts[$agendaId]['approve'] ?? 0,
                'reject' => $voteCounts[$agendaId]['reject'] ?? 0,
                'abstain' => $voteCounts[$agendaId]['abstain'] ?? 0,
            ];
            $agenda['my_vote'] = $myVotes[$agendaId] ?? null;
        }
        unset($agenda);
    }

    // เมื่อเปิดรายละเอียด ให้ถือว่าอ่านข้อความแจ้งเตือนคำเชิญแล้ว
    $stmtRead = $db->prepare(
        "UPDATE notifications
         SET is_read = 1
         WHERE user_id = ?
           AND is_read = 0
           AND title = 'คำเชิญเข้าร่วมประชุม'
           AND message LIKE ?"
    );
    $stmtRead->execute([$userId, '%' . $meeting['meeting_title'] . '%']);

    // ส่ง meeting_link เป็น null จริง หากไม่มีการแนบลิงค์ประชุมออนไลน์
    if (isset($meeting['meeting_link'])) {
        $meeting['meeting_link'] = trim((string) $meeting['meeting_link']) !== ''
            ? trim((string) $meeting['meeting_link'])
            : null;
    }

    jsonResponse('success', '', [
        'meeting' => $meeting,
        'meeting_documents' => $meetingDocuments,
        'agendas' => $agendas,
    ]);
}

if ($action === 'respond') {
    requireCsrf();
    $data = requestData();
    $meetingId = (int) ($data['meeting_id'] ?? 0);
    $response = (string) ($data['response'] ?? '');
    $remark = trim((string) ($data['remark'] ?? ''));
    $representativeName = trim((string) ($data['representative_name'] ?? ''));
    $representativePosition = trim((string) ($data['representative_position'] ?? ''));

    if ($meetingId <= 0 || !in_array($response, ['attending', 'declined', 'representative'], true)) {
        jsonResponse('error', 'ข้อมูลการตอบรับไม่ถูกต้อง', [], 422);
    }
    if (mb_strlen($remark) > 1000 || mb_strlen($representativeName) > 150 || mb_strlen($representativePosition) > 150) {
        jsonResponse('error', 'ข้อมูลยาวเกินกว่าที่ระบบกำหนด', [], 422);
    }
    if ($response === 'representative' && $representativeName === '') {
        jsonResponse('error', 'กรุณาระบุชื่อผู้เข้าร่วมแทน', [], 422);
    }

    try {
        $db->beginTransaction();

        $stmtLock = $db->prepare(
            "SELECT ma.attendance_id, ma.attendance_status, m.meeting_title, m.meeting_status
             FROM meeting_attendance ma
             INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
             WHERE ma.meeting_id = ? AND ma.user_id = ?
             FOR UPDATE"
        );
        $stmtLock->execute([$meetingId, $userId]);
        $invitation = $stmtLock->fetch(PDO::FETCH_ASSOC);

        if (!$invitation) {
            throw new RuntimeException('คุณไม่มีรายชื่ออยู่ในคำเชิญของการประชุมนี้');
        }
        if ($invitation['meeting_status'] === 'closed') {
            throw new RuntimeException('การประชุมนี้สิ้นสุดแล้ว ไม่สามารถแก้ไขการตอบรับได้');
        }
        if ($invitation['attendance_status'] === 'present') {
            throw new RuntimeException('คุณเช็กชื่อเข้าร่วมการประชุมนี้แล้ว');
        }

        $stmtUpdate = $db->prepare(
            "UPDATE meeting_attendance
             SET rsvp_status = ?,
                 attendance_status = ?,
                 representative_name = ?,
                 representative_position = ?,
                 attendance_remark = ?,
                 is_present = 0,
                 checkin_time = NULL
             WHERE meeting_id = ? AND user_id = ?"
        );

        if ($response === 'attending') {
            $stmtUpdate->execute([
                'attending',
                'pending',
                null,
                null,
                $remark !== '' ? $remark : null,
                $meetingId,
                $userId,
            ]);
            $message = 'บันทึกการยืนยันเข้าร่วมประชุมเรียบร้อยแล้ว';
            $ownerMessage = 'ตอบรับว่าจะเข้าร่วมประชุม';
        } elseif ($response === 'declined') {
            $stmtUpdate->execute([
                'declined',
                'absent',
                null,
                null,
                $remark !== '' ? $remark : null,
                $meetingId,
                $userId,
            ]);
            $message = 'บันทึกการไม่เข้าร่วมประชุมเรียบร้อยแล้ว';
            $ownerMessage = 'แจ้งว่าไม่สามารถเข้าร่วมประชุมได้';
        } else {
            $stmtUpdate->execute([
                'attending',
                'representative',
                $representativeName,
                $representativePosition !== '' ? $representativePosition : null,
                $remark !== '' ? $remark : null,
                $meetingId,
                $userId,
            ]);
            $message = 'บันทึกข้อมูลผู้เข้าร่วมแทนเรียบร้อยแล้ว';
            $ownerMessage = 'ส่งผู้แทนเข้าร่วมประชุม: ' . $representativeName;
        }

        $stmtUser = $db->prepare('SELECT name FROM user WHERE user_id = ? LIMIT 1');
        $stmtUser->execute([$userId]);
        $userName = (string) ($stmtUser->fetchColumn() ?: 'ผู้ได้รับเชิญ');

        notifyMeetingOwner(
            $db,
            $meetingId,
            'การตอบรับคำเชิญประชุม',
            $userName . ' ' . $ownerMessage . ' — ' . $invitation['meeting_title']
        );

        $db->commit();
        jsonResponse('success', $message);
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $code = $error instanceof RuntimeException ? 409 : 500;
        jsonResponse('error', $error->getMessage(), [], $code);
    }
}

if ($action === 'checkin') {
    requireCsrf();
    $data = requestData();
    $meetingId = (int) ($data['meeting_id'] ?? 0);
    if ($meetingId <= 0) {
        jsonResponse('error', 'รหัสการประชุมไม่ถูกต้อง', [], 422);
    }


    try {
        $db->beginTransaction();

        $stmtLock = $db->prepare(
            "SELECT
                ma.attendance_id,
                ma.rsvp_status,
                ma.attendance_status,
                ma.is_present,
                m.meeting_title,
                m.meeting_status
             FROM meeting_attendance ma
             INNER JOIN meeting m ON m.meeting_id = ma.meeting_id
             WHERE ma.meeting_id = ? AND ma.user_id = ?
             FOR UPDATE"
        );
        $stmtLock->execute([$meetingId, $userId]);
        $row = $stmtLock->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException('คุณไม่มีรายชื่ออยู่ในคำเชิญของการประชุมนี้');
        }
        if ($row['meeting_status'] !== 'ongoing') {
            throw new RuntimeException('สามารถเช็กชื่อได้เฉพาะระหว่างที่การประชุมกำลังดำเนินอยู่');
        }
        if ($row['rsvp_status'] !== 'attending') {
            throw new RuntimeException('กรุณายืนยันเข้าร่วมประชุมก่อนเช็กชื่อ');
        }
        if ($row['attendance_status'] === 'representative') {
            throw new RuntimeException('รายการนี้ถูกกำหนดให้ผู้แทนเข้าร่วมแล้ว');
        }
        if ($row['attendance_status'] === 'absent') {
            throw new RuntimeException('รายการนี้ถูกบันทึกว่าไม่เข้าร่วมประชุม');
        }
        if ((int) $row['is_present'] === 1 || $row['attendance_status'] === 'present') {
            throw new RuntimeException('คุณเช็กชื่อการประชุมนี้แล้ว');
        }

        $stmtUpdate = $db->prepare(
            "UPDATE meeting_attendance
             SET attendance_status = 'present',
                 rsvp_status = 'attending',
                 is_present = 1,
                 checkin_time = NOW(),
                 representative_name = NULL,
                 representative_position = NULL
             WHERE meeting_id = ? AND user_id = ?"
        );
        $stmtUpdate->execute([$meetingId, $userId]);

        $stmtUser = $db->prepare('SELECT name FROM user WHERE user_id = ? LIMIT 1');
        $stmtUser->execute([$userId]);
        $userName = (string) ($stmtUser->fetchColumn() ?: 'ผู้ได้รับเชิญ');

        notifyMeetingOwner(
            $db,
            $meetingId,
            'มีผู้เช็กชื่อเข้าประชุม',
            $userName . ' เช็กชื่อเข้าร่วม — ' . $row['meeting_title']
        );

        $db->commit();
        jsonResponse('success', 'เช็กชื่อเข้าร่วมประชุมเรียบร้อยแล้ว');
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $code = $error instanceof RuntimeException ? 409 : 500;
        jsonResponse('error', $error->getMessage(), [], $code);
    }
}

if ($action === 'cast_vote') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'ไม่รองรับวิธีการร้องขอนี้', [], 405);
    }

    requireCsrf();
    $data = requestData();
    $agendaId = (int) ($data['agenda_id'] ?? 0);
    $voteType = (string) ($data['vote_type'] ?? '');

    if ($agendaId <= 0 || !in_array($voteType, ['approve', 'reject', 'abstain'], true)) {
        jsonResponse('error', 'ข้อมูลการลงมติไม่ถูกต้อง', [], 422);
    }

    try {
        $db->beginTransaction();
        $stmtAgenda = $db->prepare(
            "SELECT a.agenda_status, m.meeting_status
             FROM agenda a
             INNER JOIN meeting m ON m.meeting_id = a.meeting_id
             INNER JOIN meeting_attendance ma ON ma.meeting_id = m.meeting_id
             WHERE a.agenda_id = ?
               AND ma.user_id = ?
               AND ma.rsvp_status = 'attending'
               AND ma.attendance_status = 'present'
             FOR UPDATE"
        );
        $stmtAgenda->execute([$agendaId, $userId]);
        $agenda = $stmtAgenda->fetch(PDO::FETCH_ASSOC);

        if (!$agenda) {
            throw new RuntimeException('คุณไม่มีสิทธิ์ลงมติในวาระนี้');
        }
        if ($agenda['meeting_status'] !== 'ongoing') {
            throw new RuntimeException('สามารถลงมติได้เฉพาะระหว่างการประชุม');
        }
        if ($agenda['agenda_status'] !== 'voting') {
            throw new RuntimeException('วาระนี้ยังไม่เปิดให้ลงมติ');
        }

        $stmtVote = $db->prepare(
            'INSERT INTO agenda_votes (agenda_id, user_id, vote_type)
             VALUES (?, ?, ?)'
        );
        try {
            $stmtVote->execute([$agendaId, $userId, $voteType]);
        } catch (PDOException $error) {
            if ((int) $error->errorInfo[1] === 1062) {
                throw new RuntimeException('คุณลงมติในวาระนี้แล้ว');
            }
            throw $error;
        }

        $db->commit();
        jsonResponse('success', 'บันทึกการลงมติเรียบร้อยแล้ว');
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $code = $error instanceof RuntimeException ? 409 : 500;
        jsonResponse('error', $error->getMessage(), [], $code);
    }
}


if ($action === 'add_comment') {
    requireCsrf();
    $data = requestData();

    $agendaId = (int)($data['agenda_id'] ?? 0);
    $commentDetail = trim((string)($data['comment_detail'] ?? ''));

    if ($agendaId <= 0 || $commentDetail === '') {
        jsonResponse('error', 'ข้อมูลความคิดเห็นไม่ครบถ้วน', [], 422);
    }

    $stmt = $db->prepare(
        "INSERT INTO comment
        (comment_detail, comment_date, user_id, agenda_id)
        VALUES (?, NOW(), ?, ?)"
    );
    $stmt->execute([$commentDetail, $userId, $agendaId]);

    jsonResponse('success', 'เพิ่มความคิดเห็นเรียบร้อยแล้ว');
}

if ($action === 'delete_comment') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'ไม่รองรับวิธีการร้องขอนี้', [], 405);
    }

    requireCsrf();
    $data = requestData();
    $commentId = (int) ($data['comment_id'] ?? 0);

    if ($commentId <= 0) {
        jsonResponse('error', 'รหัสความคิดเห็นไม่ถูกต้อง', [], 422);
    }

    $stmtComment = $db->prepare(
        'SELECT comment_id, user_id
         FROM comment
         WHERE comment_id = ?
         LIMIT 1'
    );
    $stmtComment->execute([$commentId]);
    $comment = $stmtComment->fetch(PDO::FETCH_ASSOC);

    if (!$comment) {
        jsonResponse('error', 'ไม่พบความคิดเห็นที่ต้องการลบ', [], 404);
    }
    if ((int) $comment['user_id'] !== $userId) {
        jsonResponse('error', 'คุณไม่มีสิทธิ์ลบความคิดเห็นนี้', [], 403);
    }

    $stmtDeleteComment = $db->prepare(
        'DELETE FROM comment
         WHERE comment_id = ?
           AND user_id = ?'
    );
    $stmtDeleteComment->execute([$commentId, $userId]);

    if ($stmtDeleteComment->rowCount() !== 1) {
        jsonResponse('error', 'ไม่สามารถลบความคิดเห็นได้', [], 409);
    }

    jsonResponse('success', 'ลบความคิดเห็นเรียบร้อยแล้ว');
}

if ($action === 'comments') {
    $agendaId = filter_input(INPUT_GET, 'agenda_id', FILTER_VALIDATE_INT) ?: 0;

    $stmt = $db->prepare(
        "SELECT c.comment_id, c.comment_detail, c.comment_date,
                c.user_id, u.name AS user_name
         FROM comment c
         LEFT JOIN user u ON u.user_id = c.user_id
         WHERE c.agenda_id = ?
         ORDER BY c.comment_id ASC"
    );
    $stmt->execute([$agendaId]);

    jsonResponse('success', '', [
        'comments' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

jsonResponse('error', 'ไม่พบคำสั่งที่ร้องขอ', [], 404);