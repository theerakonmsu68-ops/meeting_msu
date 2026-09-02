<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/config/database.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/models/Agenda.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/controllers/AgendaController.php';


header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !AuthMiddleware::verifyCsrf()) {
    http_response_code(419);
    echo json_encode(['status' => 'error', 'message' => 'โทเคนความปลอดภัยไม่ถูกต้อง']);
    exit;
}



try {


    $db = (new Database())->connect();


    $agendaModel = new Agenda($db);


    $agendaController = new AgendaController($agendaModel);



    $action = $_GET['action'] ?? '';





    switch ($action) {



        case 'detail':


            $agenda_id = $_GET['agenda_id'] ?? null;



            if (!$agenda_id) {

                echo json_encode([

                    'status' => 'error',

                    'message' => 'ไม่พบรหัสวาระ'

                ], JSON_UNESCAPED_UNICODE);

                exit;

            }




            $agenda =
                $agendaController->getAgendaById($agenda_id);





            if (!$agenda) {


                echo json_encode([

                    'status' => 'error',

                    'message' => 'ไม่พบข้อมูลวาระ'

                ], JSON_UNESCAPED_UNICODE);


                exit;


            }




            echo json_encode([

                'status' => 'success',

                'agenda' => $agenda

            ], JSON_UNESCAPED_UNICODE);



            break;






        case 'approve':


            $agenda_id = $_POST['agenda_id'] ?? null;



            if (!$agenda_id){

                throw new Exception(
                    'ไม่พบรหัสวาระ'
                );

            }





            $result =
                $agendaController->updateAdminStatus(
                    $agenda_id,
                    'approved'
                );





            echo json_encode([

                'status' =>
                    $result ? 'success' : 'error',

                'message' =>
                    $result
                    ? 'อนุมัติวาระเรียบร้อย'
                    : 'ไม่สามารถอนุมัติได้'


            ], JSON_UNESCAPED_UNICODE);



            break;







        case 'reject':


            $agenda_id = $_POST['agenda_id'] ?? null;



            if (!$agenda_id){

                throw new Exception(
                    'ไม่พบรหัสวาระ'
                );

            }





            $result =
                $agendaController->updateAdminStatus(
                    $agenda_id,
                    'rejected'
                );





            echo json_encode([

                'status' =>
                    $result ? 'success' : 'error',

                'message' =>
                    $result
                    ? 'ไม่อนุมัติวาระเรียบร้อย'
                    : 'ไม่สามารถเปลี่ยนสถานะได้'


            ], JSON_UNESCAPED_UNICODE);



            break;








        case 'delete':


            $agenda_id = $_POST['agenda_id'] ?? null;



            if (!$agenda_id){

                throw new Exception(
                    'ไม่พบรหัสวาระ'
                );

            }





            $result =
                $agendaController->deleteAgenda(
                    $agenda_id
                );





            echo json_encode([

                'status' =>
                    $result ? 'success' : 'error',

                'message' =>
                    $result
                    ? 'ลบวาระเรียบร้อย'
                    : 'ไม่สามารถลบวาระได้'


            ], JSON_UNESCAPED_UNICODE);



            break;








        default:


            echo json_encode([

                'status'=>'error',

                'message'=>'Invalid action'

            ], JSON_UNESCAPED_UNICODE);


            break;


    }




}

catch(Exception $e){


    echo json_encode([

        'status'=>'error',

        'message'=>$e->getMessage()

    ], JSON_UNESCAPED_UNICODE);


}
