<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/database.php';

$db = (new Database())->connect();
$meetingId = (int) ($_GET['id'] ?? 0);

if ($meetingId <= 0) {
    http_response_code(400);
    exit('รหัสการประชุมไม่ถูกต้อง');
}

$stmtMeeting = $db->prepare(
    'SELECT meeting_id, meeting_title, report_header, meeting_number,
            meeting_date, meeting_time, meeting_location
     FROM meeting
     WHERE meeting_id = ?'
);
$stmtMeeting->execute([$meetingId]);
$meeting = $stmtMeeting->fetch(PDO::FETCH_ASSOC);

if (!$meeting) {
    http_response_code(404);
    exit('ไม่พบข้อมูลการประชุม');
}

$stmtAttendance = $db->prepare(
    "SELECT
        ma.attendance_role,
        ma.rsvp_status,
        ma.attendance_status,
        ma.representative_name,
        ma.representative_position,
        ma.attendance_remark,
        u.name,
        COALESCE(p.position_name, '') AS position_name,
        COALESCE(d.department_name, '') AS department_name
     FROM meeting_attendance ma
     INNER JOIN user u ON u.user_id = ma.user_id
     LEFT JOIN positions p ON p.position_id = u.position_id
     LEFT JOIN departments d ON d.department_id = u.department_id
     WHERE ma.meeting_id = ?
     ORDER BY
        CASE ma.attendance_role
            WHEN 'chairman' THEN 1
            WHEN 'member' THEN 2
            WHEN 'secretary' THEN 3
            ELSE 4
        END,
        u.name ASC"
);
$stmtAttendance->execute([$meetingId]);
$attendanceRows = $stmtAttendance->fetchAll(PDO::FETCH_ASSOC);

$presentRows = [];
$absentRows = [];
$pendingRows = [];

foreach ($attendanceRows as $row) {
    if ($row['attendance_status'] === 'present' || $row['attendance_status'] === 'representative') {
        $presentRows[] = $row;
    } elseif ($row['attendance_status'] === 'absent') {
        $absentRows[] = $row;
    } else {
        $pendingRows[] = $row;
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function thaiMeetingDate(string $date): string
{
    $weekdays = [
        0 => 'วันอาทิตย์',
        1 => 'วันจันทร์',
        2 => 'วันอังคาร',
        3 => 'วันพุธ',
        4 => 'วันพฤหัสบดี',
        5 => 'วันศุกร์',
        6 => 'วันเสาร์',
    ];
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม',
        4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
        10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    $dt = new DateTime($date);
    $weekday = $weekdays[(int) $dt->format('w')];
    $day = (int) $dt->format('j');
    $month = $months[(int) $dt->format('n')];
    $year = (int) $dt->format('Y') + 543;

    return sprintf('%sที่ %d %s %d', $weekday, $day, $month, $year);
}

function thaiMeetingTime(string $time): string
{
    $time = substr($time, 0, 5);
    return str_replace(':', '.', $time);
}

function roleLabel(string $role): string
{
    return match ($role) {
        'chairman' => 'ประธานกรรมการ',
        'secretary' => 'เลขานุการ',
        'observer' => 'ผู้เข้าร่วมประชุม',
        default => 'กรรมการ',
    };
}

function positionLine(array $row): string
{
    $parts = [];
    $position = trim((string) ($row['position_name'] ?? ''));
    $department = trim((string) ($row['department_name'] ?? ''));

    if ($position !== '' && $position !== '-') {
        $parts[] = $position;
    }
    if ($department !== '' && $department !== '-') {
        $parts[] = $department;
    }

    return implode(' สังกัด ', $parts);
}

function reportPersonData(array $row): array
{
    if (($row['attendance_status'] ?? '') === 'representative') {
        $name = trim((string) ($row['representative_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['name'] ?? ''));
        }

        $subLines = [];
        $representativePosition = trim((string) ($row['representative_position'] ?? ''));
        if ($representativePosition !== '') {
            $subLines[] = $representativePosition;
        }
        $subLines[] = 'เข้าร่วมประชุมแทน ' . trim((string) ($row['name'] ?? ''));

        return [$name, implode(' / ', $subLines)];
    }

    return [
        trim((string) ($row['name'] ?? '')),
        positionLine($row),
    ];
}

$reportHeader = trim((string) ($meeting['report_header'] ?? ''));
if ($reportHeader === '') {
    $reportHeader = trim((string) $meeting['meeting_title']);
}
$meetingNumber = trim((string) ($meeting['meeting_number'] ?? ''));
$autoPrint = ($_GET['print'] ?? '') === '1';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานการประชุม - <?= e($meeting['meeting_title']) ?></title>
    <style>
        /*
         * กำหนดขอบกระดาษเป็น 0 เพื่อไม่ให้ Chrome/Edge แสดง
         * วันที่ ชื่อหน้าเว็บ และ URL ที่หัว/ท้ายกระดาษ
         * ระยะขอบของเนื้อหาจริงจะกำหนดใน .paper ตอนพิมพ์แทน
         */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef2f7;
            color: #111;
            font-family: "TH Sarabun New", "Sarabun", Tahoma, sans-serif;
            font-size: 16pt;
            line-height: 1.2;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            background: #0f172a;
        }

        .toolbar button {
            border: 0;
            border-radius: 8px;
            padding: 9px 18px;
            cursor: pointer;
            font-size: 14px;
        }

        .print-btn {
            background: #2563eb;
            color: #fff;
        }

        .close-btn {
            background: #fff;
            color: #0f172a;
        }

        .paper {
            width: 210mm;
            min-height: 297mm;
            margin: 18px auto;
            padding: 18mm 18mm 20mm 22mm;
            background: #fff;
            box-shadow: 0 12px 35px rgba(15, 23, 42, .16);
        }

        .report-header {
            text-align: center;
            margin-bottom: 22mm;
        }

        .report-header p {
            margin: 0;
        }

        .report-header .title {
            font-weight: 700;
        }

        .report-header .organization {
            font-weight: 700;
        }

        .report-section {
            display: grid;
            grid-template-columns: 30mm 1fr;
            column-gap: 4mm;
            margin-bottom: 8mm;
            break-inside: avoid;
        }

        .section-title {
            white-space: nowrap;
            padding-top: 1mm;
        }

        .person-list {
            min-width: 0;
        }

        .person-row {
            display: grid;
            grid-template-columns: 9mm minmax(0, 1fr) 38mm;
            column-gap: 3mm;
            align-items: start;
            margin-bottom: 2.5mm;
            break-inside: avoid;
        }

        .person-number {
            text-align: right;
            padding-right: 1mm;
        }

        .person-name {
            min-width: 0;
        }

        .person-role {
            text-align: left;
            white-space: normal;
        }

        .person-subline {
            grid-column: 2 / 3;
            margin-top: .5mm;
            font-size: 15pt;
        }

        .empty-row {
            padding: 1mm 0 4mm;
        }

        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none !important;
            }

            .paper {
                width: 210mm;
                min-height: 297mm;
                margin: 0;
                padding: 18mm 18mm 20mm 22mm;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" class="print-btn" onclick="window.print()">พิมพ์รายงาน</button>
    <button type="button" class="close-btn" onclick="window.close()">ปิดหน้าต่าง</button>
</div>

<main class="paper">
    <header class="report-header">
        <p class="title">รายงานการประชุม</p>
        <p class="organization"><?= e($reportHeader) ?></p>
        <p>
            ครั้งที่ <?= e($meetingNumber !== '' ? $meetingNumber : '-') ?>
            ใน<?= e(thaiMeetingDate($meeting['meeting_date'])) ?>
            เวลา <?= e(thaiMeetingTime($meeting['meeting_time'])) ?> น. เป็นต้นไป
        </p>
        <p><?= e($meeting['meeting_location']) ?></p>
    </header>

    <section class="report-section">
        <div class="section-title">ผู้มาประชุม</div>
        <div class="person-list">
            <?php if ($presentRows): ?>
                <?php foreach ($presentRows as $index => $row): ?>
                    <?php [$displayName, $subLine] = reportPersonData($row); ?>
                    <div class="person-row">
                        <div class="person-number"><?= $index + 1 ?>.</div>
                        <div class="person-name"><?= e($displayName) ?></div>
                        <div class="person-role"><?= e(roleLabel($row['attendance_role'])) ?></div>
                        <?php if ($subLine !== ''): ?>
                            <div class="person-subline">(<?= e($subLine) ?>)</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-row">- ไม่มีข้อมูล -</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="report-section">
        <div class="section-title">ผู้ไม่มาประชุม</div>
        <div class="person-list">
            <?php if ($absentRows): ?>
                <?php foreach ($absentRows as $index => $row): ?>
                    <?php $subLine = positionLine($row); ?>
                    <div class="person-row">
                        <div class="person-number"><?= $index + 1 ?>.</div>
                        <div class="person-name"><?= e($row['name']) ?></div>
                        <div class="person-role"><?= e($row['attendance_remark'] ?: 'ไม่เข้าร่วมประชุม') ?></div>
                        <?php if ($subLine !== ''): ?>
                            <div class="person-subline">(<?= e($subLine) ?>)</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-row">- ไม่มี -</div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($pendingRows): ?>
        <section class="report-section">
            <div class="section-title">ยังไม่ระบุสถานะ</div>
            <div class="person-list">
                <?php foreach ($pendingRows as $index => $row): ?>
                    <?php $subLine = positionLine($row); ?>
                    <div class="person-row">
                        <div class="person-number"><?= $index + 1 ?>.</div>
                        <div class="person-name"><?= e($row['name']) ?></div>
                        <div class="person-role">รอยืนยัน</div>
                        <?php if ($subLine !== ''): ?>
                            <div class="person-subline">(<?= e($subLine) ?>)</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php if ($autoPrint): ?>
<script>
    window.addEventListener('load', () => {
        setTimeout(() => window.print(), 350);
    });
</script>
<?php endif; ?>
</body>
</html>
