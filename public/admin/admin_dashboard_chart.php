<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/Meeting_msu/app/middleware/AuthMiddleware.php';
AuthMiddleware::allow(1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';

header('Content-Type: application/json; charset=utf-8');


try {


    $db = (new Database())->connect();



    /*
    |--------------------------------------------------------------------------
    | จำนวนประชุมรายเดือน
    |--------------------------------------------------------------------------
    */


    $monthly = array_fill(1,12,0);


    $sql = "

        SELECT 
            MONTH(meeting_date) AS month_no,
            COUNT(*) AS total

        FROM meeting

        GROUP BY MONTH(meeting_date)

        ORDER BY month_no

    ";


    $stmt = $db->prepare($sql);

    $stmt->execute();


    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){


        $monthly[(int)$row['month_no']] = (int)$row['total'];


    }





    /*
    |--------------------------------------------------------------------------
    | สถานะประชุม
    |--------------------------------------------------------------------------
    */


    $status = [

        'upcoming'=>0,

        'ongoing'=>0,

        'closed'=>0

    ];



    $sql2 = "

        SELECT

            meeting_status,

            COUNT(*) AS total


        FROM meeting


        GROUP BY meeting_status


    ";



    $stmt2 = $db->prepare($sql2);

    $stmt2->execute();



    while($row = $stmt2->fetch(PDO::FETCH_ASSOC)){


        if(isset($status[$row['meeting_status']])){


            $status[$row['meeting_status']]
            =
            (int)$row['total'];


        }


    }




    echo json_encode([


        "success"=>true,


        "monthly"=>array_values($monthly),


        "status"=>$status


    ],JSON_UNESCAPED_UNICODE);



}
catch(Exception $e){


    echo json_encode([

        "success"=>false,

        "message"=>$e->getMessage()

    ]);


}