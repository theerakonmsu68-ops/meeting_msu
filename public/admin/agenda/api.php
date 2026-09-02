<?php

/* ===============================
🔐 AUTH
=============================== */

require_once $_SERVER['DOCUMENT_ROOT']
    . '/Meeting_msu/app/middleware/AuthMiddleware.php';

AuthMiddleware::allow(1);



require_once __DIR__ . '/../../../app/bootstrap.php';

require_once __DIR__ . '/../../../app/config/database.php';

require_once __DIR__ . '/../../../app/models/Agenda.php';

require_once __DIR__ . '/../../../app/controllers/AgendaController.php';



header(
    'Content-Type: application/json; charset=utf-8'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !AuthMiddleware::verifyCsrf()) {
    http_response_code(419);
    echo json_encode(['status' => 'error', 'message' => 'โทเคนความปลอดภัยไม่ถูกต้อง']);
    exit;
}



$db = (new Database())->connect();



$model = new Agenda($db);


$controller = new AgendaController($model);



$action =
    $_POST['action']
    ?? $_GET['action']
    ?? '';





function jsonResponse(
    string $status,
    string $message,
    array $extra = []
): void
{

    echo json_encode(
        array_merge(
            [
                'status'=>$status,
                'message'=>$message
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;

}





/* =================================
👁️ GET AGENDA DETAIL
================================= */

if($action === 'get')
{

    $id =
        (int)($_GET['id'] ?? 0);



    if($id <= 0)
    {
        jsonResponse(
            'error',
            'รหัสวาระไม่ถูกต้อง'
        );
    }



    $agenda =
        $controller->getAgendaById($id);



    if(!$agenda)
    {
        jsonResponse(
            'error',
            'ไม่พบข้อมูลวาระ'
        );
    }



    jsonResponse(
        'success',
        'success',
        [
            'data'=>$agenda
        ]
    );

}





/* =================================
✅ APPROVE
================================= */

if($action === 'approve')
{

    updateStatus(
        $controller,
        'approved'
    );

}





/* =================================
🔄 REVISION
================================= */

if($action === 'revision')
{

    updateStatus(
        $controller,
        'revision'
    );

}





/* =================================
❌ REJECT
================================= */

if($action === 'reject')
{

    updateStatus(
        $controller,
        'rejected'
    );

}





jsonResponse(
    'error',
    'ไม่พบคำสั่ง'
);







function updateStatus(
    AgendaController $controller,
    string $status
): void
{


    $agendaId =
        (int)(
            $_POST['agenda_id']
            ?? 0
        );



    if($agendaId <= 0)
    {
        jsonResponse(
            'error',
            'ไม่พบรหัสวาระ'
        );
    }



    $result =
        $controller->updateAdminStatus(
            $agendaId,
            $status
        );



    if($result)
    {

        $message = match($status)
        {

            'approved'
                =>
                'รับรองวาระเรียบร้อยแล้ว',


            'revision'
                =>
                'ส่งกลับแก้ไขเรียบร้อยแล้ว',


            'rejected'
                =>
                'ไม่อนุมัติวาระเรียบร้อยแล้ว',


            default
                =>
                'อัปเดตสถานะเรียบร้อยแล้ว'

        };



        jsonResponse(
            'success',
            $message
        );

    }



    jsonResponse(
        'error',
        'ไม่สามารถเปลี่ยนสถานะได้'
    );

}