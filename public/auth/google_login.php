<?php

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| START SESSION
|--------------------------------------------------------------------------
*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| DB CONNECT
|--------------------------------------------------------------------------
*/
$db = (new Database())->connect();

/*
|--------------------------------------------------------------------------
| GET JSON INPUT
|--------------------------------------------------------------------------
*/
$rawData = file_get_contents('php://input');

$data = json_decode($rawData, true);

if (!is_array($data)) {

    echo json_encode([
        'status'  => 'error',
        'message' => 'ข้อมูลที่ส่งมาไม่ถูกต้อง'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
|--------------------------------------------------------------------------
| รับข้อมูล Google
|--------------------------------------------------------------------------
*/
$credential = trim((string) ($data['credential'] ?? ''));

/*
|--------------------------------------------------------------------------
| ตรวจสอบข้อมูลที่จำเป็น
|--------------------------------------------------------------------------
*/
if ($credential === '') {

    echo json_encode([
        'status'  => 'error',
        'message' => 'ข้อมูล Google ไม่ครบถ้วน'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$tokenInfoUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential);
$tokenResponse = file_get_contents($tokenInfoUrl);
$tokenData = is_string($tokenResponse) ? json_decode($tokenResponse, true) : null;

if (!is_array($tokenData)
    || ($tokenData['aud'] ?? '') !== GOOGLE_CLIENT_ID
    || ($tokenData['email_verified'] ?? '') !== 'true'
    || empty($tokenData['sub'])
    || empty($tokenData['email'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่สามารถยืนยันข้อมูล Google ได้'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$email = trim((string)$tokenData['email']);
$name = trim((string)($tokenData['name'] ?? ''));
$googleId = trim((string)$tokenData['sub']);
$picture = trim((string)($tokenData['picture'] ?? ''));

/*
|--------------------------------------------------------------------------
| FIND USER
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("
    SELECT *
    FROM `user`
    WHERE email = ?
    LIMIT 1
");

$stmt->execute([$email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| USER NOT FOUND → REGISTER
|--------------------------------------------------------------------------
*/
if (!$user) {

    /*
     * สร้าง Username จาก Email
     */
    $username = explode('@', $email)[0];

    /*
     * ป้องกัน Username ซ้ำ
     */
    $checkUsername = $db->prepare("
        SELECT user_id
        FROM `user`
        WHERE username = ?
        LIMIT 1
    ");

    $checkUsername->execute([$username]);

    if ($checkUsername->fetch()) {

        $username .= '_' . substr(
            md5($googleId),
            0,
            6
        );
    }

    /*
     * สมัครสมาชิก Google
     *
     * สถานะเริ่มต้น = pending
     * รอ Admin อนุมัติ
     */
    $insert = $db->prepare("
        INSERT INTO `user`
        (
            username,
            password,
            name,
            email,
            google_id,
            picture,
            role_id,
            department_id,
            status,
            login_type
        )
        VALUES (
            ?,
            '',
            ?,
            ?,
            ?,
            ?,
            2,
            1,
            'pending',
            'google'
        )
    ");

    $insert->execute([
        $username,
        $name,
        $email,
        $googleId,
        $picture
    ]);

    echo json_encode([
        'status'  => 'pending',
        'message' => 'สมัครสำเร็จ กรุณารอผู้ดูแลระบบอนุมัติ'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK STATUS
|--------------------------------------------------------------------------
*/

/*
 * Pending
 */
if ($user['status'] === 'pending') {

    echo json_encode([
        'status'  => 'pending',
        'message' => 'บัญชีกำลังรอการอนุมัติจากผู้ดูแลระบบ'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
 * Inactive / Blocked
 */
if ($user['status'] === 'inactive') {

    echo json_encode([
        'status'  => 'blocked',
        'message' => 'บัญชีของคุณถูกระงับการใช้งาน'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| UPDATE GOOGLE DATA
|--------------------------------------------------------------------------
|
| อัปเดตข้อมูลทุกครั้งที่ Login ด้วย Google
|--------------------------------------------------------------------------
*/
$update = $db->prepare("
    UPDATE `user`
    SET
        name = ?,
        google_id = ?,
        picture = ?
    WHERE user_id = ?
");

$update->execute([
    $name,
    $googleId,
    $picture,
    $user['user_id']
]);


/*
|--------------------------------------------------------------------------
| SET SESSION
|--------------------------------------------------------------------------
*/
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['name']    = $name ?: $user['name'];
$_SESSION['email']   = $email;
$_SESSION['role_id'] = $user['role_id'];
$_SESSION['picture'] = $picture ?: ($user['picture'] ?? '');

/*
|--------------------------------------------------------------------------
| RESPONSE SUCCESS
|--------------------------------------------------------------------------
*/
echo json_encode([
    'status'  => 'success',
    'message' => 'ยินดีต้อนรับเข้าสู่ระบบ',
    'role_id' => (int) $user['role_id']
], JSON_UNESCAPED_UNICODE);

exit;