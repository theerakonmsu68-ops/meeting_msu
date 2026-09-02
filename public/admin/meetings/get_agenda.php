<?php
/* ===============================
🔐 AUTH & SECURITY
=============================== */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(1);

require_once __DIR__ . '/../../../app/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$db = (new Database())->connect();
$meetingId = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare(
    'SELECT 
        a.agenda_id,
        a.agenda_title,
        a.agenda_detail,
        a.order_index,
        a.agenda_type,
        a.agenda_status,
        a.admin_status
     FROM agenda a 
     WHERE 
        a.meeting_id = ?
        AND a.admin_status = "approved"
     ORDER BY 
        a.order_index ASC,
        a.agenda_id ASC'
);
$stmt->execute([$meetingId]);
$agendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$agendas) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$agendaIds = array_map('intval', array_column($agendas, 'agenda_id'));
$placeholders = implode(',', array_fill(0, count($agendaIds), '?'));

$stmtDocuments = $db->prepare(
    "SELECT
        agenda_document_id,
        agenda_id,
        document_name,
        file_path,
        file_size,
        mime_type,
        upload_date
     FROM agenda_documents
     WHERE agenda_id IN ($placeholders)
     ORDER BY upload_date ASC, agenda_document_id ASC"
);
$stmtDocuments->execute($agendaIds);

$documentsByAgenda = [];
foreach ($stmtDocuments->fetchAll(PDO::FETCH_ASSOC) as $document) {
    $documentsByAgenda[(int) $document['agenda_id']][] = $document;
}

foreach ($agendas as &$agenda) {
    $agenda['documents'] = $documentsByAgenda[(int) $agenda['agenda_id']] ?? [];
}
unset($agenda);

echo json_encode($agendas, JSON_UNESCAPED_UNICODE);
