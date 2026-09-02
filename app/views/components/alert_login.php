<style>
/* =========================================================
   FULLSCREEN WELCOME
========================================================= */

.welcome-popup {

    position: fixed;

    inset: 0;

    width: 100%;
    height: 100%;

    min-height: 100dvh;

    z-index: 999999;

    display: flex;

    align-items: center;
    justify-content: center;

    overflow: hidden;

    background: #ffffff;

    opacity: 1;

    visibility: visible;

    transform: scale(1);

    transition:
        opacity 0.45s cubic-bezier(.4, 0, .2, 1),
        visibility 0.45s,
        transform 0.55s cubic-bezier(.4, 0, .2, 1);

}


/* =========================================================
   ตอนปิด
========================================================= */

.welcome-popup.is-closing {

    opacity: 0;

    visibility: hidden;

    transform: scale(1.025);

}


/* =========================================================
   BACKGROUND
========================================================= */

.welcome-bg {

    position: absolute;

    inset: 0;

    pointer-events: none;

    background:

        radial-gradient(circle at 15% 20%,
            rgba(14, 165, 233, 0.08),
            transparent 30%),

        radial-gradient(circle at 85% 80%,
            rgba(56, 189, 248, 0.08),
            transparent 30%),

        #ffffff;

}


/* เส้นตกแต่งบาง ๆ */

.welcome-bg::before {

    content: "";

    position: absolute;

    width: 600px;
    height: 600px;

    top: -300px;
    right: -250px;

    border-radius: 50%;

    border: 1px solid rgba(14, 165, 233, 0.08);

    box-shadow:
        0 0 0 80px rgba(14, 165, 233, 0.025),
        0 0 0 160px rgba(14, 165, 233, 0.018);

}


/* =========================================================
   CONTENT
========================================================= */

.welcome-container {

    position: relative;

    z-index: 2;

    width: min(700px, 90%);

    text-align: center;

    display: flex;

    flex-direction: column;

    align-items: center;

    animation:
        welcomeContentIn 0.65s cubic-bezier(.22, 1, .36, 1) both;

}


/* =========================================================
   CLOSE BUTTON
========================================================= */

.welcome-close {

    position: fixed;

    top: 30px;

    right: 35px;

    width: 48px;

    height: 48px;

    border: 1px solid #e2e8f0;

    border-radius: 50%;

    background: #ffffff;

    cursor: pointer;

    z-index: 10;

    display: flex;

    align-items: center;

    justify-content: center;

    transition:

        background 0.25s ease,

        border-color 0.25s ease,

        transform 0.35s cubic-bezier(.22, 1, .36, 1);

}

.welcome-close:hover {

    background: #f8fafc;

    border-color: #bae6fd;

    transform: rotate(90deg);

}


/* X */

.welcome-close span {

    position: absolute;

    width: 17px;

    height: 1.5px;

    background: #64748b;

    border-radius: 10px;

}

.welcome-close span:first-child {

    transform: rotate(45deg);

}

.welcome-close span:last-child {

    transform: rotate(-45deg);

}


/* =========================================================
   LOGO
========================================================= */

.welcome-logo {

    width: 250px;

    height: 250px;

    margin-bottom: 28px;

    padding: 17px;

    border-radius: 30px;


    display: flex;

    align-items: center;

    justify-content: center;

    animation:
        logoIn 0.8s cubic-bezier(.22, 1, .36, 1) 0.1s both;

}

.welcome-logo img {

    width: 100%;

    height: 100%;

    object-fit: contain;

}

/* =========================================================
   TITLE
========================================================= */

.welcome-container h1 {

    margin: 0;

    color: #0f172a;

    font-size: clamp(44px, 6vw, 72px);

    font-weight: 700;

    line-height: 1.1;

    letter-spacing: -1.5px;

}

.welcome-container h2 {

    margin: 10px 0 22px;

    color: #0284c7;

    font-size: clamp(22px, 3vw, 30px);

    font-weight: 600;

}


/* =========================================================
   DESCRIPTION
========================================================= */

.welcome-container p {

    max-width: 520px;

    margin: 0 auto 35px;

    color: #64748b;

    font-size: 16px;

    line-height: 1.9;

}


/* =========================================================
   BUTTON
========================================================= */

.welcome-button {

    min-width: 220px;

    height: 54px;

    padding: 0 28px;

    border: none;

    border-radius: 14px;

    background: #0284c7;

    color: #ffffff;

    font-size: 16px;

    font-weight: 600;

    cursor: pointer;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 18px;

    box-shadow:
        0 10px 25px rgba(2, 132, 199, 0.20);

    transition:

        background 0.25s ease,

        transform 0.3s cubic-bezier(.22, 1, .36, 1),

        box-shadow 0.3s ease;

}

.welcome-button:hover {

    background: #0369a1;

    transform: translateY(-3px);

    box-shadow:
        0 15px 35px rgba(2, 132, 199, 0.25);

}

.welcome-button:active {

    transform: translateY(0);

}

.welcome-button b {

    font-size: 21px;

    font-weight: 400;

    transition:
        transform 0.3s cubic-bezier(.22, 1, .36, 1);

}

.welcome-button:hover b {

    transform: translateX(5px);

}


/* =========================================================
   FOOTER
========================================================= */

.welcome-footer {

    margin-top: 28px;

    color: #94a3b8;

    font-size: 12px;

}


/* =========================================================
   OPEN ANIMATION
========================================================= */

@keyframes welcomeContentIn {

    from {

        opacity: 0;

        transform:
            translateY(30px) scale(0.97);

    }

    to {

        opacity: 1;

        transform:
            translateY(0) scale(1);

    }

}


@keyframes logoIn {

    from {

        opacity: 0;

        transform:
            translateY(20px) scale(0.9);

    }

    to {

        opacity: 1;

        transform:
            translateY(0) scale(1);

    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 600px) {

    .welcome-container {

        width: calc(100% - 40px);

    }

    .welcome-close {

        top: 18px;

        right: 18px;

        width: 43px;

        height: 43px;

    }

    .welcome-logo {

        width: 95px;

        height: 95px;

        padding: 13px;

        border-radius: 24px;

        margin-bottom: 22px;

    }

    .welcome-container h1 {

        font-size: 43px;

    }

    .welcome-container h2 {

        font-size: 20px;

        margin-bottom: 18px;

    }

    .welcome-container p {

        font-size: 14px;

        line-height: 1.75;

        margin-bottom: 28px;

    }

    .welcome-button {

        width: 100%;

        height: 52px;

    }

}
</style>

<!-- =========================================================
     FULLSCREEN CLEAN WELCOME POPUP
========================================================= -->
<div
    id="sg-popup-builder-content"
    class="welcome-popup"
    style="display: none;"
>

    <div class="welcome-bg"></div>

    <div class="welcome-container">

        <!-- Close -->
        <button
            type="button"
            class="welcome-close"
            onclick="closeWelcomePopup()"
            aria-label="ปิด"
        >
            <span></span>
            <span></span>
        </button>

        <!-- Logo -->
        <div class="welcome-logo">

            <img
                src="<?= htmlspecialchars(
                    BASE_URL,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>assets/image/logo.svg"
                alt="ระบบงานประชุม"
            >

        </div>

        <!-- Title -->
        <h1>
            ยินดีต้อนรับ
        </h1>

        <h2>
            สู่ระบบงานประชุม
        </h2>

        <p>
            ระบบบริหารจัดการงานประชุม
            เพื่อสนับสนุนการทำงานให้สะดวก รวดเร็ว
            และเป็นระบบ
        </p>

        <!-- Button -->
        <button
            type="button"
            class="welcome-button"
            onclick="closeWelcomePopup()"
        >
            <span>เข้าสู่ระบบ</span>
            <b>→</b>
        </button>

        <div class="welcome-footer">
            กรุณากดเข้าสู่ระบบเพื่อเริ่มใช้งาน
        </div>

    </div>

</div>


<script>
document.addEventListener('DOMContentLoaded', function() {

    const popup = document.getElementById(
        'sg-popup-builder-content'
    );

    /*
     * ถ้าไม่มี Popup
     * ไม่ต้องทำอะไร
     */
    if (!popup) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | ตรวจสอบ Request
    |--------------------------------------------------------------------------
    |
    | GET  = เข้าหน้า Login / Refresh
    | POST = กด Login ด้วย Username + Password
    |
    */
    const isLoginSubmit =
        <?= $_SERVER['REQUEST_METHOD'] === 'POST' ? 'true' : 'false' ?>;


    /*
    |--------------------------------------------------------------------------
    | ถ้าเป็น POST
    |--------------------------------------------------------------------------
    |
    | ไม่แสดง Welcome Popup
    | เพราะอาจมี SweetAlert2 แสดงอยู่
    |
    */
    if (isLoginSubmit) {

        popup.style.display = 'none';

        popup.classList.remove('is-closing');

        document.body.style.overflow = '';

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | ถ้าเป็น GET
    |--------------------------------------------------------------------------
    |
    | แสดง Welcome Popup
    |
    */
    popup.style.display = 'flex';

    /*
     * เอา Animation ปิดออก
     * เผื่อผู้ใช้เคยเปิด/ปิด Popup
     */
    popup.classList.remove('is-closing');

    /*
     * ป้องกัน Scroll ด้านหลัง
     */
    document.body.style.overflow = 'hidden';

});


/*
|--------------------------------------------------------------------------
| ปิด Welcome Popup
|--------------------------------------------------------------------------
*/
function closeWelcomePopup() {

    const popup = document.getElementById(
        'sg-popup-builder-content'
    );

    /*
     * ถ้าไม่มี Popup
     */
    if (!popup) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | ป้องกันการกดซ้ำ
    |--------------------------------------------------------------------------
    */
    if (popup.classList.contains('is-closing')) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | เริ่ม Animation ปิด
    |--------------------------------------------------------------------------
    */
    popup.classList.add('is-closing');


    /*
    |--------------------------------------------------------------------------
    | เปิด Scroll กลับ
    |--------------------------------------------------------------------------
    */
    document.body.style.overflow = '';


    /*
    |--------------------------------------------------------------------------
    | รอ Animation จบ
    |--------------------------------------------------------------------------
    */
    setTimeout(function() {

        popup.style.display = 'none';

        /*
         * ลบ Class ไว้สำหรับการเปิดครั้งถัดไป
         */
        popup.classList.remove('is-closing');

    }, 550);

}
</script>