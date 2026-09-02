<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';


try {

// รับข้อมูลจาก JSON หรือ Form POST

if (!empty($_POST)) {

    // กรณีส่งจาก Form เดิม
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

} else {

    // กรณีส่งแบบ JSON (Vercel/API)
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

}
    

    // Database
    $db = (new Database())->connect();


    // Model
    $userModel = new User($db);


    // Controller
    $auth = new AuthController($userModel);


    // Login
    $result = $auth->login(
        $username,
        $password
    );


    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE
    );


} catch(Exception $e){

    http_response_code(500);

    echo json_encode([
        "status"=>"error",
        "message"=>$e->getMessage()
    ]);

}