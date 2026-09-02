<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(1);


require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/helpers/view_helper.php';



$db = (new Database())->connect();



$limit = 9;


$page = isset($_GET['page']) && is_numeric($_GET['page'])
    ? (int) $_GET['page']
    : 1;


if ($page < 1) {
    $page = 1;
}


$offset = ($page - 1) * $limit;



$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';


// --- ส่วนที่เพิ่ม: รับค่าจาก Form ตัวกรอง ---
$filter_meeting = isset($_GET['meeting_id']) ? $_GET['meeting_id'] : '';
$filter_month = isset($_GET['month']) ? $_GET['month'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// --- ส่วนที่เพิ่ม: ดึงข้อมูลการประชุมมาแสดงใน Dropdown ---
$meeting_stmt = $db->query("SELECT meeting_id, meeting_title FROM meeting ORDER BY meeting_id DESC");
$meeting_list = $meeting_stmt->fetchAll(PDO::FETCH_ASSOC);



// --- ส่วนที่ปรับปรุง: เพิ่มพารามิเตอร์ให้ฟังก์ชันและต่อ string เงื่อนไข ---
function buildSearchQuery($search, $filter_meeting, $filter_month, $filter_date)
{
    $where_clause = "

WHERE

(
    a.department_id IS NOT NULL

    AND

    a.submitted_by IS NOT NULL
)

";
    $params = [];


    if ($search !== '') {

        $where_clause .= "

            AND (
                a.agenda_title LIKE :search
                OR a.agenda_detail LIKE :search
                OR m.meeting_title LIKE :search
                OR u.name LIKE :search
                OR d.department_name LIKE :search
            )

        ";


        $params[':search'] =
            '%' . $search . '%';
    }


    if ($filter_meeting !== '') {
        $where_clause .= " AND a.meeting_id = :meeting_id ";
        $params[':meeting_id'] = $filter_meeting;
    }


    if ($filter_month !== '') {
        $where_clause .= " AND MONTH(a.created_at) = :month ";
        $params[':month'] = $filter_month;
    }


    if ($filter_date !== '') {
        $where_clause .= " AND DATE(a.created_at) = :date ";
        $params[':date'] = $filter_date;
    }


    return [
        $where_clause,
        $params
    ];
}



[
    $where_clause,
    $params
] = buildSearchQuery($search, $filter_meeting, $filter_month, $filter_date);


// --- ส่วนที่เพิ่ม: เตรียม Query String สำหรับ Pagination ให้จำค่าที่ค้นหา ---
$query_string_array = [
    'search' => $search,
    'meeting_id' => $filter_meeting,
    'month' => $filter_month,
    'date' => $filter_date
];
$filter_query_string = http_build_query(array_filter($query_string_array));


if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {


    $count_query = "

SELECT COUNT(*)

FROM agenda a


LEFT JOIN meeting m
ON a.meeting_id = m.meeting_id


LEFT JOIN user u
ON a.submitted_by = u.user_id


LEFT JOIN departments d
ON a.department_id = d.department_id


$where_clause

";



    $count_stmt = $db->prepare($count_query);

    $count_stmt->execute($params);


    $total_rows =
        $count_stmt->fetchColumn();



    $total_pages =
        ceil($total_rows / $limit);



    if ($page > $total_pages && $total_pages > 0) {

        $page = $total_pages;

        $offset = ($page - 1) * $limit;
    }





    $query = "

        SELECT

            a.*,

            m.meeting_title,

            u.name AS submitter_name,

            d.department_name


        FROM agenda a


        LEFT JOIN meeting m
            ON a.meeting_id = m.meeting_id


        LEFT JOIN user u
            ON a.submitted_by = u.user_id


        LEFT JOIN departments d
            ON a.department_id = d.department_id



        $where_clause



        ORDER BY a.agenda_id DESC



        LIMIT :limit OFFSET :offset

    ";




    $stmt = $db->prepare($query);



    foreach ($params as $key => $val) {

        $stmt->bindValue(
            $key,
            $val
        );
    }



    $stmt->bindValue(
        ':limit',
        $limit,
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



    exit;
}





$count_query = "

    SELECT COUNT(*)

    FROM agenda a


    LEFT JOIN meeting m
        ON a.meeting_id = m.meeting_id


    LEFT JOIN user u
        ON a.submitted_by = u.user_id


    LEFT JOIN departments d
        ON a.department_id = d.department_id



    $where_clause

";




$count_stmt = $db->prepare($count_query);


$count_stmt->execute($params);



$total_rows =
    $count_stmt->fetchColumn();



$total_pages =
    max(
        1,
        ceil($total_rows / $limit)
    );



if ($page > $total_pages) {

    $page = $total_pages;

    $offset = ($page - 1) * $limit;
}





$query = "

    SELECT

        a.*,

        m.meeting_title,

        u.name AS submitter_name,

        d.department_name


    FROM agenda a


    LEFT JOIN meeting m
        ON a.meeting_id = m.meeting_id


    LEFT JOIN user u
        ON a.submitted_by = u.user_id


    LEFT JOIN departments d
        ON a.department_id = d.department_id



    $where_clause



    ORDER BY a.agenda_id DESC



    LIMIT :limit OFFSET :offset

";




$stmt = $db->prepare($query);



foreach ($params as $key => $val) {

    $stmt->bindValue(
        $key,
        $val
    );
}



$stmt->bindValue(
    ':limit',
    $limit,
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





$page_title = "Dashboard - Admin";

$page_css = "agenda-management.css";

$page_js = [
    "sweetalert2.all.min.js",
    "agenda-management.js"
];



include_once __DIR__ . '/../../../app/views/layouts/header.php';



$current_page = 'agendas';


include_once __DIR__ . '/../../../app/views/layouts/sidebar_admin.php';

?>




<div class="main-content" id="mainContent">
    <header class="header">
        <div class="header-left">
            <button class="toggle-btn" id="toggle-sidebar"><i data-lucide="menu"></i></button>
            <h2>จัดการวาระ</h2>
        </div>
    </header>

    <main class="content-wrapper">

        <!-- แทรกส่วนของ Form ตัวกรองที่นี่ -->
        <div class="filter-card">
            <h3><i data-lucide="filter" style="width: 18px; height: 18px; color: #64748b;"></i> ค้นหาและตัวกรอง</h3>

            <form method="GET" action="agendas.php" class="filter-form">

                <div class="form-group search-group">
                    <label for="search">คำค้นหา</label>
                    <input type="text" id="search" name="search" class="form-control" value="<?= h($search) ?>" placeholder="ชื่อวาระ, ผู้เสนอ, หน่วยงาน...">
                </div>

                <div class="form-group">
                    <label for="meeting_id">การประชุม</label>
                    <select id="meeting_id" name="meeting_id" class="form-control">
                        <option value="">-- ทุกการประชุม --</option>
                        <?php foreach ($meeting_list as $mtg): ?>
                            <option value="<?= $mtg['meeting_id'] ?>" <?= $filter_meeting == $mtg['meeting_id'] ? 'selected' : '' ?>>
                                <?= h($mtg['meeting_title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="month">เดือนที่เสนอ</label>
                    <select id="month" name="month" class="form-control">
                        <option value="">-- ทุกเดือน --</option>
                        <?php
                        $months = [
                            '01' => 'มกราคม',
                            '02' => 'กุมภาพันธ์',
                            '03' => 'มีนาคม',
                            '04' => 'เมษายน',
                            '05' => 'พฤษภาคม',
                            '06' => 'มิถุนายน',
                            '07' => 'กรกฎาคม',
                            '08' => 'สิงหาคม',
                            '09' => 'กันยายน',
                            '10' => 'ตุลาคม',
                            '11' => 'พฤศจิกายน',
                            '12' => 'ธันวาคม'
                        ];
                        foreach ($months as $num => $name): ?>
                            <option value="<?= $num ?>" <?= $filter_month === (string)$num ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date">วันที่เฉพาะเจาะจง</label>
                    <input type="date" id="date" name="date" class="form-control" value="<?= h($filter_date) ?>">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-search">
                        <i data-lucide="search" style="width: 16px; height: 16px;"></i> ค้นหา
                    </button>
                    <a href="agendas.php" class="btn-clear">
                        <i data-lucide="rotate-ccw" style="width: 16px; height: 16px;"></i> ล้างค่า
                    </a>
                </div>
            </form>
        </div>


        <div class="table-card" id="tableContainer">

            <table>

                <thead>
                    <tr>
                        <th style="width:70px;">ลำดับ</th>
                        <th>หัวข้อวาระ</th>
                        <th>ประเภทวาระ</th>
                        <th>ผู้เสนอ</th>
                        <th>หน่วยงาน</th>
                        <th>วันที่เสนอ</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>


                <tbody>

                    <?php if (!empty($agendas)): ?>

                        <?php $i = $offset + 1; ?>

                        <?php foreach ($agendas as $agenda): ?>

                            <tr>

                                <!-- ลำดับ -->
                                <td>
                                    <?= $i++ ?>
                                </td>


                                <!-- หัวข้อวาระ -->
                                <td>

                                    <b>
                                        <?= h($agenda['agenda_title']) ?>
                                    </b>

                                    <?php if (!empty($agenda['agenda_detail'])): ?>

                                        <div class="table-subtext">
                                            <?= h(
                                                mb_strimwidth(
                                                    $agenda['agenda_detail'],
                                                    0,
                                                    80,
                                                    '...'
                                                )
                                            ) ?>
                                        </div>

                                    <?php endif; ?>

                                </td>





                                <!-- ประเภทวาระ -->
                                <td>

                                    <?php

                                    $type = $agenda['agenda_type'] ?? '';

                                    $typeText = match ($type) {

                                        'info' =>
                                        'แจ้งเพื่อทราบ',

                                        'consider' =>
                                        'พิจารณา',

                                        'approve' =>
                                        'อนุมัติ',

                                        default =>
                                        'ทั่วไป'
                                    };

                                    ?>

                                    <span class="role-badge">
                                        <?= $typeText ?>
                                    </span>

                                </td>


                                <!-- ผู้เสนอ -->
                                <td>
                                    <?= h(
                                        $agenda['submitter_name']
                                            ?? '-'
                                    ) ?>
                                </td>


                                <!-- หน่วยงาน -->
                                <td>
                                    <?= h(
                                        $agenda['department_name']
                                            ?? 'ไม่มีสังกัด'
                                    ) ?>
                                </td>


                                <!-- วันที่เสนอ -->
                                <td>

                                    <?= !empty($agenda['created_at'])

                                        ? date(
                                            'd/m/Y',
                                            strtotime($agenda['created_at'])
                                        )

                                        : '-'
                                    ?>

                                </td>


                                <!-- สถานะ -->
                                <td>

                                    <?php

                                    $status =
                                        $agenda['admin_status']
                                        ?? 'pending';


                                    switch ($status) {

                                        case 'approved':

                                            echo '
                                    <span class="status-badge status-active">
                                        อนุมัติแล้ว
                                    </span>';

                                            break;


                                        case 'rejected':

                                            echo '
                                    <span class="status-badge status-inactive">
                                        ไม่อนุมัติ
                                    </span>';

                                            break;


                                        default:

                                            echo '
                                    <span class="status-badge status-pending">
                                        รอตรวจสอบ
                                    </span>';
                                    }

                                    ?>

                                </td>


                                <td>

                                    <div class="action-buttons">

                                        <button class="btn-edit" onclick="viewAgenda(<?= (int) $agenda['agenda_id'] ?>)">
                                            ดูรายละเอียด
                                        </button>


                                        <?php if (($agenda['admin_status'] ?? 'pending') === 'pending'): ?>

                                            <button class="btn-success" onclick="approveAgenda(<?= (int) $agenda['agenda_id'] ?>)">
                                                อนุมัติ
                                            </button>


                                            <button class="btn-delete" onclick="rejectAgenda(<?= (int) $agenda['agenda_id'] ?>)">
                                                ไม่อนุมัติ
                                            </button>


                                        <?php endif; ?>


                                        <?php if (($agenda['admin_status'] ?? '') === 'approved'): ?>

                                            <span class="status-badge status-active">
                                                อนุมัติแล้ว
                                            </span>

                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td colspan="10" style="
                        text-align:center;
                        padding:30px;
                        color:#94a3b8;
                        ">

                                ไม่พบวาระที่ถูกเสนอมา

                            </td>

                        </tr>


                    <?php endif; ?>


                </tbody>

            </table>



            <!-- Pagination -->

            <?php if ($total_pages > 1): ?>

                <div class="pagination-container">

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>

                        <!-- ส่วนที่ปรับปรุง: แนบ filter_query_string ไปกับการเปลี่ยนหน้า -->
                        <a href="?page=<?= $i ?><?= !empty($filter_query_string) ? '&' . $filter_query_string : '' ?>"
                            class="pagination-link <?= ($page == $i) ? 'active' : '' ?>">

                            <?= $i ?>

                        </a>

                    <?php endfor; ?>

                </div>

            <?php endif; ?>


        </div>

    </main>
</div>





<?php
include_once __DIR__ . '/../../../app/views/components/profile_modal.php';
include_once __DIR__ . '/../../../app/views/components/agenda_modal.php';
include_once __DIR__ . '/../../../app/views/layouts/footer.php';
?>