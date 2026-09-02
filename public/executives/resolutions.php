<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(3);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';
$db = (new Database())->connect();

$stmt = $db->prepare(
  "SELECT r.resolution_id,r.resolution_detail,r.resolution_date,r.status,r.due_date,
         a.agenda_title,m.meeting_id,m.meeting_title,m.meeting_date,
         COALESCE(u.name,'ยังไม่ระบุผู้รับผิดชอบ') AS responsible_name
  FROM resolution r
  LEFT JOIN agenda a ON a.agenda_id=r.agenda_id
  LEFT JOIN meeting m ON m.meeting_id=a.meeting_id
  LEFT JOIN user u ON u.user_id=r.responsible_user
  ORDER BY r.resolution_date DESC,r.resolution_id DESC"
);
$stmt->execute();
$resolutions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$page_title = "Executive Resolutions";
$page_js = ["sweetalert2.all.min.js"];
include_once __DIR__ . '/../../app/views/layouts/header.php';
$current_page = 'resolutions';
include_once __DIR__ . '/../../app/views/layouts/sidebar_executive.php';
?>
<style>
  .main-content {
    margin-left: 268px;
    width: calc(100% - 268px);
    min-height: 100vh;
    background: #f8fafc;
    transition: .26s
  }

  .main-content.expanded {
    margin-left: 74px;
    width: calc(100% - 74px)
  }

  .head {
    padding: 20px 24px;
    display: flex;
    gap: 12px;
    align-items: center;
    background: #fff;
    border-bottom: 1px solid #e2e8f0
  }

  .toggle {
    border: 0;
    background: none;
    cursor: pointer
  }

  .body {
    padding: 24px
  }

  .summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 14px;
    margin-bottom: 20px
  }

  .sum {
    padding: 16px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px
  }

  .sum strong {
    font-size: 23px;
    color: #7c3aed
  }

  .sum span {
    display: block;
    margin-top: 3px;
    color: #64748b;
    font-size: 12px
  }

  .search {
    width: 100%;
    max-width: 340px;
    padding: 9px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    margin-bottom: 14px
  }

  .grid {
    display: grid;
    gap: 13px
  }

  .resolution {
    padding: 17px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 15px
  }

  .top {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: flex-start
  }

  .title {
    margin: 0;
    color: #1e293b;
    font-size: 14px
  }

  .detail {
    margin: 10px 0;
    color: #475569;
    font-size: 13px;
    line-height: 1.65
  }

  .meta {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    color: #64748b;
    font-size: 11px
  }

  .chip {
    padding: 4px 8px;
    border-radius: 999px;
    font-weight: 700
  }

  .pending {
    color: #92400e;
    background: #fef3c7
  }

  .in_progress {
    color: #1d4ed8;
    background: #dbeafe
  }

  .completed {
    color: #166534;
    background: #dcfce7
  }

  @media(max-width:768px) {

    .main-content,
    .main-content.expanded {
      margin-left: 0;
      width: 100%
    }

    .body {
      padding: 16px
    }
  }
</style>
<?php
$pending = 0;
$progress = 0;
$completed = 0;
foreach ($resolutions as $r) {
  if ($r['status'] === 'completed')
    $completed++;
  elseif ($r['status'] === 'in_progress')
    $progress++;
  else
    $pending++;
}
?>
<div class="main-content" id="mainContent">
  <header class="head"><button class="toggle" id="toggle-sidebar"><i data-lucide="menu"></i></button>
    <div>
      <h2 style="margin:0;font-size:20px;color:#1e293b">มติและผลการประชุม</h2>
      <p style="margin:4px 0 0;font-size:13px;color:#64748b">ติดตามมติ ผู้รับผิดชอบ และสถานะการดำเนินงาน</p>
    </div>
  </header>
  <main class="body">
    <div class="summary">
      <div class="sum"><strong><?= count($resolutions) ?></strong><span>มติทั้งหมด</span></div>
      <div class="sum"><strong><?= $pending ?></strong><span>รอดำเนินการ</span></div>
      <div class="sum"><strong><?= $progress ?></strong><span>กำลังดำเนินการ</span></div>
      <div class="sum"><strong><?= $completed ?></strong><span>ดำเนินการเสร็จแล้ว</span></div>
    </div>
    <input id="search" class="search" placeholder="ค้นหามติ / การประชุม / ผู้รับผิดชอบ..."
      oninput="filterResolutions()">
    <div class="grid" id="resolutionList">
      <?php foreach ($resolutions as $r):
        $status = (string) ($r['status'] ?? 'pending');
        $statusText = $status === 'completed' ? 'เสร็จแล้ว' : ($status === 'in_progress' ? 'กำลังดำเนินการ' : 'รอดำเนินการ');
        ?>
        <article class="resolution"
          data-search="<?= htmlspecialchars(mb_strtolower(($r['meeting_title'] ?? '') . ' ' . ($r['agenda_title'] ?? '') . ' ' . ($r['resolution_detail'] ?? '') . ' ' . ($r['responsible_name'] ?? ''), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
          <div class="top">
            <div>
              <h3 class="title">
                <?= htmlspecialchars((string) ($r['meeting_title'] ?? 'ไม่พบชื่อการประชุม'), ENT_QUOTES, 'UTF-8') ?></h3>
              <div style="margin-top:4px;color:#7c3aed;font-size:12px">
                <?= htmlspecialchars((string) ($r['agenda_title'] ?? 'ไม่ระบุวาระ'), ENT_QUOTES, 'UTF-8') ?></div>
            </div><span class="chip <?= $status ?>"><?= $statusText ?></span>
          </div>
          <p class="detail"><?= nl2br(htmlspecialchars((string) $r['resolution_detail'], ENT_QUOTES, 'UTF-8')) ?></p>
          <div class="meta"><span>วันที่มติ: <?= date('d/m/Y', strtotime((string) $r['resolution_date'])) ?></span><span>•
              ผู้รับผิดชอบ:
              <?= htmlspecialchars((string) $r['responsible_name'], ENT_QUOTES, 'UTF-8') ?></span><?php if (!empty($r['due_date'])): ?><span>•
                กำหนดเสร็จ: <?= date('d/m/Y', strtotime((string) $r['due_date'])) ?></span><?php endif; ?></div>
        </article>
      <?php endforeach; ?>
      <?php if (!$resolutions): ?>
        <div style="padding:30px;text-align:center;color:#94a3b8;background:#fff;border-radius:14px">
          ยังไม่มีข้อมูลมติในระบบ</div><?php endif; ?>
    </div>
  </main>
</div>
<script>
  function filterResolutions() { const q = (document.getElementById('search').value || '').toLowerCase(); document.querySelectorAll('.resolution').forEach(x => x.style.display = (x.dataset.search || '').includes(q) ? '' : 'none') }
  document.getElementById('toggle-sidebar')?.addEventListener('click', () => { const s = document.getElementById('sidebar'), m = document.getElementById('mainContent'); s?.classList.toggle('collapsed'); if (!matchMedia('(max-width:768px)').matches) m?.classList.toggle('expanded'); else m?.classList.remove('expanded') });
  if (window.lucide) lucide.createIcons();
</script>
<?php include_once __DIR__ . '/../../app/views/components/profile_modal.php';
include_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>