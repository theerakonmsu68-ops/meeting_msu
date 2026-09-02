<?php

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';

header('Cross-Origin-Opener-Policy: same-origin-allow-popups');

/*
|--------------------------------------------------------------------------
| Database / Authentication
|--------------------------------------------------------------------------
*/
$db = (new Database())->connect();

$userModel = new User($db);
$auth = new AuthController($userModel);

$page_title = 'เข้าสู่ระบบ';
$page_js    = 'sweetalert2.all.min.js';

/*
|--------------------------------------------------------------------------
| Redirect URL ตาม Role
|--------------------------------------------------------------------------
*/
function getRedirectUrlByRole(): string
{
    $roleId = (int) ($_SESSION['role_id'] ?? 0);

    switch ($roleId) {

        case 1:
            // Admin
            return '../admin/index.php';

        case 3:
            // Executive
            return '../executives/index.php';

        case 4:
            // Department
            return '../departments/index.php';

        case 2:
            // User
            return '../users/index.php';

        default:
            /*
             * Role ไม่ถูกต้อง
             */
            return './login.php';
    }
}

/*
|--------------------------------------------------------------------------
| Redirect ตาม Role
|--------------------------------------------------------------------------
*/
function redirectByRole(): void
{
    $roleId = (int) ($_SESSION['role_id'] ?? 0);

    switch ($roleId) {

        case 1:
            header('Location: ../admin/index.php');
            break;

        case 3:
            header('Location: ../executives/index.php');
            break;

        case 4:
            header('Location: ../departments/index.php');
            break;

        case 2:
            header('Location: ../users/index.php');
            break;

        default:
            /*
             * Role ไม่ถูกต้อง
             */
            session_unset();
            session_destroy();

            header('Location: ./login.php');
            break;
    }

    exit;
}

/*
|--------------------------------------------------------------------------
| ถ้า Login อยู่แล้ว → Redirect ตาม Role
|--------------------------------------------------------------------------
*/
if (!empty($_SESSION['user_id'])) {
    redirectByRole();
}

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/
$alertScript = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * รับค่าจาก Form
     */
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    /*
     * =========================================================
     * ตรวจสอบข้อมูลเบื้องต้น
     * =========================================================
     */
    if ($username === '' || $password === '') {

        $loginMessage = 'กรุณากรอกชื่อผู้ใช้งานและรหัสผ่าน';

        $alertMessage = json_encode(
            $loginMessage,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $alertScript = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {

    if (typeof Swal !== 'undefined') {

        Swal.fire({
            icon: 'warning',
            title: 'แจ้งเตือน',
            text: {$alertMessage},
            confirmButtonColor: '#0284c7',
            confirmButtonText: 'ตกลง'
        });

    }

    /*
     * ป้องกัน POST ซ้ำเมื่อ Refresh
     */
    if (window.history.replaceState) {

        window.history.replaceState(
            null,
            document.title,
            window.location.href
        );

    }

});
</script>
HTML;

    } else {

        /*
         * =====================================================
         * ตรวจสอบ Login ผ่าน AuthController
         * =====================================================
         */
        $loginResult = $auth->login($username, $password);

        /*
         * =====================================================
         * LOGIN สำเร็จ
         * =====================================================
         */
        if (($loginResult['status'] ?? 'error') === 'success') {

            /*
             * URL ปลายทางตาม Role
             */
            $redirectUrl = getRedirectUrlByRole();

            /*
             * ข้อความ Login สำเร็จ
             */
            $loginMessage = $loginResult['message']
                ?? 'เข้าสู่ระบบสำเร็จ';

            /*
             * แปลงข้อความให้ปลอดภัยสำหรับ JavaScript
             */
            $alertMessage = json_encode(
                $loginMessage,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            /*
             * แปลง URL ให้ปลอดภัยสำหรับ JavaScript
             */
            $redirectUrlJs = json_encode(
                $redirectUrl,
                JSON_UNESCAPED_SLASHES
            );

            /*
             * SweetAlert2 Success
             */
            $alertScript = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {

    if (typeof Swal !== 'undefined') {

        Swal.fire({
            icon: 'success',
            title: 'เข้าสู่ระบบสำเร็จ',
            text: {$alertMessage},
            confirmButtonColor: '#0284c7',
            confirmButtonText: 'เข้าสู่ระบบ',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(function () {

            /*
             * หลังจากกดปุ่ม
             * ค่อย Redirect ตาม Role
             */
            window.location.href = {$redirectUrlJs};

        });

    } else {

        /*
         * กรณี SweetAlert2 โหลดไม่ทัน
         * ให้ Redirect ไปเลย
         */
        window.location.href = {$redirectUrlJs};

    }

});
</script>
HTML;

        /*
         * =====================================================
         * LOGIN ไม่สำเร็จ
         * =====================================================
         */
        } else {

            $loginMessage = $loginResult['message']
                ?? 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง';

            $alertMessage = json_encode(
                $loginMessage,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            $alertScript = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {

    if (typeof Swal !== 'undefined') {

        Swal.fire({
            icon: 'error',
            title: 'เข้าสู่ระบบไม่สำเร็จ',
            text: {$alertMessage},
            confirmButtonColor: '#0284c7',
            confirmButtonText: 'ตกลง'
        });

    }

    /*
     * ป้องกัน POST ซ้ำเมื่อ Refresh
     */
    if (window.history.replaceState) {

        window.history.replaceState(
            null,
            document.title,
            window.location.href
        );

    }

});
</script>
HTML;

        }
    }
}

/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
|
| โหลด Header ก่อน เพื่อให้ SweetAlert2 ถูกโหลด
|--------------------------------------------------------------------------
*/
include_once __DIR__ . '/../../app/views/layouts/header.php';

/*
|--------------------------------------------------------------------------
| Welcome Popup
|--------------------------------------------------------------------------
|
| alert_login.php ต้องมี JavaScript ตรวจว่า:
|
| GET  → แสดง Welcome Popup
| POST → ไม่แสดง Welcome Popup
|
| เพื่อป้องกัน Popup ซ้อนกับ SweetAlert2
|--------------------------------------------------------------------------
*/
include_once __DIR__ . '/../../app/views/components/alert_login.php';

/*
|--------------------------------------------------------------------------
| แสดง SweetAlert2
|--------------------------------------------------------------------------
*/
echo $alertScript;

?>

<!-- =========================================================
     LOGIN
========================================================= -->
<div class="login-wrapper">

    <div class="login-card">

        <!-- Logo -->
        <div class="logo">

            <img
                src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>assets/image/logo.svg"
                width="95"
                height="95"
                alt="โลโก้ระบบงานประชุม"
            >

        </div>

        <!-- Title -->
        <h1>ระบบงานประชุม</h1>

        <p class="subtitle">
            กรุณาเข้าสู่ระบบด้วยบัญชีของคุณ
        </p>

        <!-- Login Form -->
        <form
            method="post"
            action=""
            autocomplete="on"
        >

            <!-- Username -->
            <div class="form-group">

                <label for="username">
                    ชื่อผู้ใช้งาน
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    class="inputu"
                    placeholder="กรอกชื่อผู้ใช้งาน"
                    autocomplete="username"
                    maxlength="100"
                    required
                    autofocus
                >

            </div>

            <!-- Password -->
            <div class="form-group">

                <label for="password">
                    รหัสผ่าน
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    class="inputp"
                    placeholder="กรอกรหัสผ่าน"
                    autocomplete="current-password"
                    maxlength="255"
                    required
                >

            </div>

            <!-- Forgot Password -->
            <div class="forgot-box">

                <a
                    href="#"
                    class="small-link"
                    onclick="forgotPassword(); return false;"
                >
                    ลืมรหัสผ่าน?
                </a>

            </div>

            <!-- Login Button -->
            <button
                class="buttonlogin"
                type="submit"
                id="loginBtn"
            >
                <span class="btn-text">
                    เข้าสู่ระบบ
                </span>
            </button>

            <!-- Divider -->
            <div class="divider">
                <span>หรือ</span>
            </div>

            <!-- =====================================================
                 GOOGLE LOGIN
            ====================================================== -->

            <div
                id="g_id_onload"
                data-client_id="218704445431-4p55g8sr01hj7oq1704hcd7prnv0udfk.apps.googleusercontent.com"
                data-callback="handleCredentialResponse"
                data-auto_prompt="false"
                data-use_fedcm_for_prompt="false"
                data-use_fedcm_for_button="false"
            >
            </div>

            <div class="google-wrap">

                <div
                    class="g_id_signin"
                    data-type="standard"
                    data-size="large"
                    data-theme="outline"
                    data-text="continue_with"
                    data-shape="pill"
                    data-logo_alignment="left"
                    data-width="350"
                >
                </div>

            </div>

        </form>

    </div>

</div>

<?php

/*
|--------------------------------------------------------------------------
| Footer
|--------------------------------------------------------------------------
*/
include_once __DIR__ . '/../../app/views/layouts/footer.php';

?>