<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/middleware/AuthMiddleware.php';

AuthMiddleware::allow(4);


require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/view_helper.php';


$db = (new Database())->connect();


$userId = (int)($_SESSION['user_id'] ?? 0);

$departmentId = (int)($_SESSION['department_id'] ?? 0);



if ($userId <= 0) {

    http_response_code(401);

    exit('ไม่พบข้อมูลผู้ใช้งาน');

}



 
function typeText(string $v): string
{
    return [
        'info'=>'เพื่อทราบ',
        'consider'=>'เพื่อพิจารณา',
        'approve'=>'เพื่ออนุมัติ'
    ][$v] ?? 'เพื่อทราบ';
}



function statusText(string $v): string
{
    return [
        'pending'=>'รอดำเนินการ',
        'discussing'=>'กำลังอภิปราย',
        'closed'=>'ปิดวาระแล้ว'
    ][$v] ?? 'รอดำเนินการ';
}




/*
|--------------------------------------------------------------------------
| Filter
|--------------------------------------------------------------------------
*/


$filter = (string)($_GET['status'] ?? 'all');


if(!in_array(
    $filter,
    [
        'all',
        'pending',
        'discussing',
        'closed'
    ],
    true
)){

    $filter='all';

}



$q = trim(
    (string)($_GET['q'] ?? '')
);




/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/


$allowedPerPage=[
    8,
    12,
    20,
    30
];


$perPage =
(int)($_GET['per_page'] ?? 12);



if(!in_array(
    $perPage,
    $allowedPerPage,
    true
)){

    $perPage=12;

}



$page=max(
    1,
    (int)($_GET['page'] ?? 1)
);






/*
|--------------------------------------------------------------------------
| Query เฉพาะวาระของ User / หน่วยงาน
|--------------------------------------------------------------------------
*/


$where = [

    "(a.submitted_by=:uid OR a.department_id=:department_id)"

];


$params = [

    ':uid'=>$userId,

    ':department_id'=>$departmentId

];




if($filter !== 'all'){

    $where[] =
    "a.agenda_status=:status";


    $params[':status']=$filter;

}





if($q !== ''){


    $where[] =
    "
    (
        a.agenda_title LIKE :q

        OR

        a.agenda_detail LIKE :q

        OR

        m.meeting_title LIKE :q
    )
    ";


    $params[':q'] =
    '%'.$q.'%';

}



$whereSql =
implode(
    ' AND ',
    $where
);







/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/


$countStmt=$db->prepare(

"
SELECT COUNT(DISTINCT a.agenda_id)

FROM agenda a


INNER JOIN meeting m

ON m.meeting_id=a.meeting_id


WHERE {$whereSql}

"

);



$countStmt->execute($params);


$totalRows =
(int)$countStmt->fetchColumn();



$totalPages =
max(
    1,
    (int)ceil($totalRows/$perPage)
);



if($page>$totalPages){

    $page=$totalPages;

}



$offset =
($page-1)*$perPage;







/*
|--------------------------------------------------------------------------
| Data
|--------------------------------------------------------------------------
*/


$stmt=$db->prepare(

"
SELECT DISTINCT


a.agenda_id,

a.agenda_title,

a.agenda_detail,

a.order_index,

a.agenda_type,

a.agenda_status,

a.admin_status,

a.created_at,


m.meeting_id,

m.meeting_title,

m.meeting_date,

m.meeting_time,

m.meeting_status,



(
    SELECT COUNT(*)

    FROM agenda_documents ad

    WHERE ad.agenda_id=a.agenda_id

) AS document_count



FROM agenda a



INNER JOIN meeting m

ON m.meeting_id=a.meeting_id



WHERE {$whereSql}



ORDER BY

a.created_at DESC,

a.order_index ASC



LIMIT :limit OFFSET :offset

"

);



foreach($params as $k=>$v){

    $stmt->bindValue(
        $k,
        $v,
        $k===':uid' || $k===':department_id'
        ?
        PDO::PARAM_INT
        :
        PDO::PARAM_STR
    );

}



$stmt->bindValue(
    ':limit',
    $perPage,
    PDO::PARAM_INT
);



$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);



$stmt->execute();


$agendas =
$stmt->fetchAll(PDO::FETCH_ASSOC);







/*
|--------------------------------------------------------------------------
| Stats
|--------------------------------------------------------------------------
*/


$stats=[

    'all'=>0,

    'pending'=>0,

    'discussing'=>0,

    'closed'=>0

];



$s=$db->prepare(

"
SELECT

a.agenda_status,

COUNT(DISTINCT a.agenda_id) cnt


FROM agenda a


WHERE

(
    a.submitted_by=?

    OR

    a.department_id=?

)


GROUP BY a.agenda_status

"

);



$s->execute([

    $userId,

    $departmentId

]);



foreach(
    $s->fetchAll(PDO::FETCH_ASSOC)
    as $r
){

    $stats[$r['agenda_status']]
    =
    (int)$r['cnt'];


    $stats['all']
    +=
    (int)$r['cnt'];

}






$page_title = 'ติดตามวาระที่เสนอ';

$page_css = "department-track.css";

$page_js = "department-track.js";



include_once __DIR__
. '/../../app/views/layouts/header.php';


$current_page = 'track_agendas';


include_once __DIR__
. '/../../app/views/layouts/sidebar_department.php';

?>
<div class="main-content" id="mainContent">
    <header class="head"><button id="toggle-sidebar" class="toggle"><i data-lucide="menu"></i></button>
        <div>
            <h2 style="margin:0;font-size:20px;color:#1e293b">ติดตามวาระที่เสนอ</h2>
            <p style="margin:4px 0 0;color:#64748b;font-size:12.5px">
                ตรวจสอบวาระและเอกสารในการประชุมที่บัญชีของคุณมีสิทธิ์เข้าถึง</p>
        </div>
    </header>
    <main class="body">
        <?php if (($_GET['created'] ?? '') === '1'): ?>
            <div class="success">บันทึกวาระและเอกสารเรียบร้อยแล้ว</div><?php endif; ?>
        <div class="stats">
            <?php foreach (['all' => 'ทั้งหมด', 'pending' => 'รอดำเนินการ', 'discussing' => 'กำลังอภิปราย', 'closed' => 'ปิดวาระแล้ว'] as $k => $label): ?>
                <a class="stat <?= $filter === $k ? 'active' : '' ?>"
                    href="?status=<?= h($k) ?>"><strong><?= $stats[$k] ?></strong><span><?= h($label) ?></span></a>
            <?php endforeach; ?>
        </div>
        <div class="toolbar">
            <form class="search" method="get"><input type="hidden" name="status" value="<?= h($filter) ?>"><input
                    type="hidden" name="per_page" value="<?= (int) $perPage ?>"><input class="control" name="q"
                    value="<?= h($q) ?>" placeholder="ค้นหาวาระหรือการประชุม"><button class="btn" type="submit"><i
                        data-lucide="search"></i>ค้นหา</button></form>
            <a class="btn primary" href="submit_agenda.php"><i data-lucide="file-plus-2"></i>เสนอวาระใหม่</a>
        </div>
        <div class="grid">
            <?php foreach ($agendas as $a): ?>
                <article class="item">
                    <div class="top">
                        <div>
                            <h3 class="title"><?= h($a['agenda_title']) ?></h3>
                            <div class="meeting"><?= h($a['meeting_title']) ?> ·
                                <?= date('d/m/Y', strtotime($a['meeting_date'])) ?>
                                <?= substr((string) $a['meeting_time'], 0, 5) ?>
                                น.
                            </div>
                        </div>
                        <?php
                        $adminStatus = $a['admin_status'] ?? 'pending';

                        $statusLabel = match ($adminStatus) {
                            'approved' => 'อนุมัติแล้ว',
                            'rejected' => 'ไม่อนุมัติ',
                            default => 'รอตรวจสอบ'
                        };
                        ?>

                        <span class="chip <?= h($adminStatus) ?>">
                            <?= h($statusLabel) ?>
                        </span>
                    </div>
                    <p class="detail"><?= nl2br(h($a['agenda_detail'])) ?></p>
                    <div class="meta"><span class="chip type"><?= h(typeText($a['agenda_type'])) ?></span><span>ลำดับวาระ
                            <?= (int) $a['order_index'] ?></span><span>เอกสาร <?= (int) $a['document_count'] ?>
                            ไฟล์</span><span>สร้างเมื่อ <?= date('d/m/Y H:i', strtotime($a['created_at'])) ?> น.</span>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$agendas): ?>
                <div class="empty"><i data-lucide="clipboard-x"></i><br>ไม่พบวาระตามเงื่อนไขที่เลือก</div><?php endif; ?>
        </div>

        <?php if ($totalRows > 0): ?>
            <div class="pager">
                <div class="pager-info">
                    แสดง <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalRows) ?> จาก <?= $totalRows ?> รายการ
                </div>
                <div class="pagination">
                    <?php
                    $base = ['status' => $filter, 'q' => $q, 'per_page' => $perPage];
                    $prev = http_build_query(array_merge($base, ['page' => max(1, $page - 1)]));
                    $next = http_build_query(array_merge($base, ['page' => min($totalPages, $page + 1)]));
                    ?>
                    <a class="page-link" href="?<?= h($prev) ?>">ก่อนหน้า</a>
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    if ($start > 1): ?>
                        <a class="page-link" href="?<?= h(http_build_query(array_merge($base, ['page' => 1]))) ?>">1</a>
                        <?php if ($start > 2): ?><span class="page-link" style="pointer-events:none">…</span><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a class="page-link <?= $i === $page ? 'active' : '' ?>"
                            href="?<?= h(http_build_query(array_merge($base, ['page' => $i]))) ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?><span class="page-link"
                                style="pointer-events:none">…</span><?php endif; ?>
                        <a class="page-link"
                            href="?<?= h(http_build_query(array_merge($base, ['page' => $totalPages]))) ?>"><?= $totalPages ?></a>
                    <?php endif; ?>
                    <a class="page-link" href="?<?= h($next) ?>">ถัดไป</a>
                </div>
                <form class="per-page" method="get">
                    <input type="hidden" name="status" value="<?= h($filter) ?>">
                    <input type="hidden" name="q" value="<?= h($q) ?>">
                    <span>ต่อหน้า</span>
                    <select name="per_page" onchange="this.form.submit()">
                        <?php foreach ($allowedPerPage as $n): ?>
                            <option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php include_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>