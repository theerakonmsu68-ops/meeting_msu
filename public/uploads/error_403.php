<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Forbidden</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <link rel="icon" type="image/png" href="../../public/assets/images/favicon.png"> 

    <style>
        * {
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        body {
            background-color: #0f172a; /* เปลี่ยนเป็น Dark Mode เพื่อความลึกลับและปลอดภัย */
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            /* ลายตาข่ายจุดพรีเมียมสีจาง */
            background-image: radial-gradient(rgba(255, 255, 255, 0.05) 1.5px, transparent 1.5px);
            background-size: 24px 24px;
        }
        .error-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px); /* กระจกฝ้าล้ำๆ */
            width: 100%;
            max-width: 460px;
            padding: 48px 32px;
            border-radius: 28px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }
        .icon-container {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px auto;
            border-radius: 50%;
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444; /* ไอคอนแม่กุญแจสีแดงเตือนภัยแบบนุ่มตา */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-code {
            font-size: 14px;
            font-weight: 600;
            color: #ef4444;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }
        .error-title {
            font-size: 22px;
            font-weight: 600;
            color: #f8fafc;
            margin: 0 0 12px 0;
            letter-spacing: -0.5px;
        }
        .error-message {
            font-size: 14.5px;
            color: #94a3b8;
            line-height: 1.6;
            margin: 0 0 36px 0;
            font-weight: 400;
        }
        .btn-home {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 20px;
            background-color: #ffffff;
            color: #0f172a;
            border: none;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(255, 255, 255, 0.1);
        }
        .btn-home:hover {
            background-color: #f1f5f9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.15);
        }
        .btn-home:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="icon-container">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>
        
        <div class="error-code">Error 403</div>
        <h1 class="error-title">พื้นที่จัดเก็บเอกสารส่วนบุคคล</h1>
        <p class="error-message">ระบบได้จำกัดการเข้าถึงโฟลเดอร์นี้โดยตรง เพื่อความปลอดภัยของข้อมูลวาระการประชุมคณะ ไม่อนุญาตให้เข้าถึงผ่าน URL สาธารณะ</p>
        
        <a href="/Meeting_msu/public/users/meeting_history.php" class="btn-home">
            <i data-lucide="home" style="width: 16px; height: 16px;"></i>
            <span>กลับสู่หน้าหลักของระบบ</span>
        </a>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>