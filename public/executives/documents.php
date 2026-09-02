<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(3);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/view_helper.php';
$db = (new Database())->connect();
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    exit('ไม่พบข้อมูลผู้ใช้งาน');
}
$type = (string) ($_GET['type'] ?? 'all');
if (!in_array($type, ['all', 'meeting', 'agenda'], true))
    $type = 'all';
$q = trim((string) ($_GET['q'] ?? ''));

$allowedPerPage = [12, 20, 30, 50];
$perPage = (int) ($_GET['per_page'] ?? 12);
if (!in_array($perPage, $allowedPerPage, true))
    $perPage = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));

$unionSql = "
SELECT
 'meeting' source_type,d.document_id id,d.document_name,d.file_path,d.upload_date,
 m.meeting_id,m.meeting_title,m.meeting_date,NULL agenda_title
FROM documents d
INNER JOIN meeting m ON m.meeting_id=d.meeting_id
INNER JOIN meeting_attendance ma ON ma.meeting_id=m.meeting_id
WHERE ma.user_id=:uid1

UNION ALL

SELECT
 'agenda' source_type,ad.agenda_document_id id,ad.document_name,ad.file_path,ad.upload_date,
 m.meeting_id,m.meeting_title,m.meeting_date,a.agenda_title
FROM agenda_documents ad
INNER JOIN agenda a ON a.agenda_id=ad.agenda_id
INNER JOIN meeting m ON m.meeting_id=a.meeting_id
INNER JOIN meeting_attendance ma ON ma.meeting_id=m.meeting_id
WHERE ma.user_id=:uid2
";

$whereOuter = [];
$params = [':uid1' => $userId, ':uid2' => $userId];
if ($type !== 'all') {
    $whereOuter[] = 'x.source_type=:type';
    $params[':type'] = $type;
}
if ($q !== '') {
    $whereOuter[] = '(x.document_name LIKE :q OR x.meeting_title LIKE :q OR COALESCE(x.agenda_title,\'\') LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$whereOuterSql = $whereOuter ? ' WHERE ' . implode(' AND ', $whereOuter) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM ({$unionSql}) x{$whereOuterSql}");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages)
    $page = $totalPages;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT * FROM ({$unionSql}) x{$whereOuterSql} ORDER BY x.upload_date DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, in_array($k, [':uid1', ':uid2'], true) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statsStmt = $db->prepare(
    "SELECT
 SUM(source_type='meeting') AS meeting_count,
 SUM(source_type='agenda') AS agenda_count,
 COUNT(*) AS total_count
 FROM ({$unionSql}) s"
);
$statsStmt->execute([':uid1' => $userId, ':uid2' => $userId]);
$docStats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$meetingCount = (int) ($docStats['meeting_count'] ?? 0);
$agendaCount = (int) ($docStats['agenda_count'] ?? 0);
$allDocumentsCount = (int) ($docStats['total_count'] ?? 0);

$page_title = 'คลังเอกสารภาควิชา';
$page_css = "executive-document.css";
$page_js = "executive-document.js";
include_once __DIR__ . '/../../app/views/layouts/header.php';
$current_page = 'documents';
include_once __DIR__ . '/../../app/views/layouts/sidebar_executive.php';
?>

<div class="main-content" id="mainContent">
    <header class="head"><button id="toggle-sidebar" class="toggle"><i data-lucide="menu"></i></button>
        <div>
            <h2 style="margin:0;font-size:20px;color:#1e293b">คลังเอกสารการประชุม</h2>
            <p style="margin:4px 0 0;color:#64748b;font-size:12.5px">เอกสารจากการประชุมที่บัญชีของคุณได้รับเชิญ</p>
        </div>
    </header>
    <main class="body">
        <div class="stats">
            <div class="stat"><strong><?= $allDocumentsCount ?></strong><span>เอกสารทั้งหมด</span></div>
            <div class="stat"><strong><?= $meetingCount ?></strong><span>เอกสารระดับการประชุม</span></div>
            <div class="stat"><strong><?= $agendaCount ?></strong><span>เอกสารแนบวาระ</span></div>
        </div>
        <div class="toolbar">
            <form class="search-form" method="get">
                <input type="hidden" name="type" value="<?= h($type) ?>">
                <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
                <input class="search" name="q" value="<?= h($q) ?>" placeholder="ค้นหาชื่อเอกสาร / การประชุม / วาระ...">
                <button class="search-btn" type="submit"><i data-lucide="search"></i></button>
            </form>
            <div class="tabs">
                <a class="tab <?= $type === 'all' ? 'active' : '' ?>"
                    href="?<?= h(http_build_query(['type' => 'all', 'q' => $q, 'per_page' => $perPage])) ?>">ทั้งหมด</a>
                <a class="tab <?= $type === 'meeting' ? 'active' : '' ?>"
                    href="?<?= h(http_build_query(['type' => 'meeting', 'q' => $q, 'per_page' => $perPage])) ?>">เอกสารประชุม</a>
                <a class="tab <?= $type === 'agenda' ? 'active' : '' ?>"
                    href="?<?= h(http_build_query(['type' => 'agenda', 'q' => $q, 'per_page' => $perPage])) ?>">เอกสารวาระ</a>
            </div>
        </div>
        <div class="grid" id="docGrid">
            <?php foreach ($documents as $d):
                $search = mb_strtolower(($d['document_name'] ?? '') . ' ' . ($d['meeting_title'] ?? '') . ' ' . ($d['agenda_title'] ?? ''), 'UTF-8'); ?>
                <article class="doc" data-type="<?= h($d['source_type']) ?>" data-search="<?= h($search) ?>">
                    <div class="icon"><i data-lucide="<?= $d['source_type'] === 'agenda' ? 'files' : 'file-text' ?>"></i>
                    </div>
                    <h3><?= h($d['document_name']) ?></h3>
                    <p><?= h($d['meeting_title']) ?></p>
                    <?php if ($d['agenda_title']): ?>
                        <p>วาระ: <?= h($d['agenda_title']) ?></p><?php endif; ?>
                    <p>อัปโหลด: <?= date('d/m/Y H:i', strtotime($d['upload_date'])) ?> น.</p>
                    <span class="chip"><?= $d['source_type'] === 'agenda' ? 'เอกสารวาระ' : 'เอกสารการประชุม' ?></span><br>
                    <a class="download"
                        href="/app/controllers/download.php?file=<?= urlencode($d['file_path']) ?>"><i
                            data-lucide="download"></i>ดาวน์โหลด</a>
                </article>
            <?php endforeach; ?>
            <?php if (!$documents): ?>
                <div class="empty">ยังไม่มีเอกสารจากการประชุมที่คุณได้รับเชิญ</div><?php endif; ?>
        </div>
        <?php if ($totalRows > 0): ?>
            <div class="pager">
                <div class="pager-info">แสดง <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalRows) ?> จาก
                    <?= $totalRows ?> รายการ
                </div>
                <div class="pagination">
                    <?php
                    $base = ['type' => $type, 'q' => $q, 'per_page' => $perPage];
                    $prev = http_build_query(array_merge($base, ['page' => max(1, $page - 1)]));
                    $next = http_build_query(array_merge($base, ['page' => min($totalPages, $page + 1)]));
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    ?>
                    <a class="page-link" href="?<?= h($prev) ?>">ก่อนหน้า</a>
                    <?php if ($start > 1): ?><a class="page-link"
                            href="?<?= h(http_build_query(array_merge($base, ['page' => 1]))) ?>">1</a><?php if ($start > 2): ?><span
                                class="page-link" style="pointer-events:none">…</span><?php endif; ?><?php endif; ?>
                    <?php for ($i = $start; $i <= $end; $i++): ?><a class="page-link <?= $i === $page ? 'active' : '' ?>"
                            href="?<?= h(http_build_query(array_merge($base, ['page' => $i]))) ?>"><?= $i ?></a><?php endfor; ?>
                    <?php if ($end < $totalPages): ?>         <?php if ($end < $totalPages - 1): ?><span class="page-link"
                                style="pointer-events:none">…</span><?php endif; ?><a class="page-link"
                            href="?<?= h(http_build_query(array_merge($base, ['page' => $totalPages]))) ?>"><?= $totalPages ?></a><?php endif; ?>
                    <a class="page-link" href="?<?= h($next) ?>">ถัดไป</a>
                </div>
                <form class="per-page" method="get">
                    <input type="hidden" name="type" value="<?= h($type) ?>">
                    <input type="hidden" name="q" value="<?= h($q) ?>">
                    <span>ต่อหน้า</span>
                    <select name="per_page" onchange="this.form.submit()"><?php foreach ($allowedPerPage as $n): ?>
                            <option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>