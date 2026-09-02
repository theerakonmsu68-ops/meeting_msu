<?php
// สตาร์ทเซสชันเพื่อใช้สำหรับเก็บความจำบทสนทนา (สำคัญมาก)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 💡 ทริคเสริม: ถ้าฝั่งหน้าบ้านมีการกดปุ่มเคลียร์แชท หรืออยากล้างสมอง AI ให้เริ่มเรื่องใหม่
if (isset($_GET['clear']) && $_GET['clear'] == '1') {
    unset($_SESSION['chat_history']);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'ล้างความจำเรียบร้อยแล้ว']);
    exit;
}

// 1. ตรวจสอบการส่งค่าโพสต์ข้อความจากหน้าบ้าน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $userMessage = trim($_POST['message']);
    
    if (empty($userMessage)) {
        header('Content-Type: application/json');
        echo json_encode(['reply' => 'โปรดพิมพ์ข้อความที่ต้องการถามเพิ่มได้เลยครับ']);
        exit;
    }

    // 2. ตรวจสอบหรือประกาศค่า API Key ของ Groq
    $groqApiKey = getenv('GROQ_API_KEY') ?: '';
    if ($groqApiKey === '') {
        header('Content-Type: application/json');
        echo json_encode(['reply' => 'ขออภัย ระบบ AI ยังไม่ได้ตั้งค่าให้พร้อมใช้งาน']);
        exit;
    }

    // 3. เตรียมโครงสร้างความจำ (Chat History) ใน Session
    if (!isset($_SESSION['chat_history']) || !is_array($_SESSION['chat_history'])) {
        // ถ้าเป็นคำถามแรกของเซสชัน ให้ตั้งค่าเริ่มต้นระบบ (System Prompt)
        $systemPrompt = "คุณคือ 'AI Assistant' ผู้ช่วยอัจฉริยะประจำระบบจัดการประชุมคณะ (Meeting MSU) "
                      . "คุณมีหน้าที่ตอบคำถามผู้ใช้ทั่วไป ข้าราชการ คณะกรรมการ และเจ้าหน้าที่ภาควิชาอย่างสุภาพ เป็นมิตร และมืออาชีพ "
                      . "ข้อบังคับในการตอบ:\n"
                      . "1. ตอบเป็นภาษาไทยที่กระชับ เข้าใจง่าย ไม่ยิ่นเย้อ\n"
                      . "2. หากต้องอธิบายเป็นขั้นตอน ให้ใช้การขึ้นบรรทัดใหม่หรือใช้เครื่องหมายพอยต์ (•) เพื่อความสะอาดตา\n"
                      . "3. แนะนำผู้ใช้เรื่องการเข้าสู่ระบบ การตรวจสอบวาระ หรือการดาวน์โหลดเอกสารการประชุมเบื้องต้นได้";
        
        $_SESSION['chat_history'] = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];
    }

    // 4. บันทึกคำถามล่าสุดของ User ลงไปในความจำ Session
    $_SESSION['chat_history'][] = ['role' => 'user', 'content' => $userMessage];

    // จำกัดความจำไม่ให้ยาวเกินไปจนระเบิด (เก็บย้อนหลังไว้ประมาณ 15 ประโยคล่าสุดรวม System)
    if (count($_SESSION['chat_history']) > 16) {
        // ตัดตัวเก่าออกแต่ยังคงรักษา System Prompt (ตำแหน่งที่ 0) ไว้
        $sys = $_SESSION['chat_history'][0];
        $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -15);
        array_unshift($_SESSION['chat_history'], $sys);
    }

    // 5. จัดโครงสร้างข้อมูลรวมประวัติความจำ ยิงไปที่ Groq
    $apiData = [
        'model' => 'openai/gpt-oss-120b',
        'messages' => $_SESSION['chat_history'], // ส่งก้อนความจำทั้งหมดไปให้ AI อ่าน
        'temperature' => 0.6,
        'max_tokens' => 800
    ];

    // 6. เริ่มกระบวนการส่งข้อมูลด้วย cURL
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groqApiKey
    ]);
    
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 7. ประมวลผลและคัดกรองข้อมูล
    if ($response) {
        $responseData = json_decode($response, true);
        
        if (isset($responseData['choices'][0]['message']['content'])) {
            $aiReply = $responseData['choices'][0]['message']['content'];
            
            // 💡 จุดสำคัญ: บันทึกคำตอบของ AI ลงในความจำ Session ด้วย เพื่อให้รอบหน้ามันจำคำตอบตัวเองได้
            $_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $aiReply];

        } elseif (isset($responseData['error']['message'])) {
            error_log('Groq API Error: ' . (string)$responseData['error']['message']);
            $aiReply = 'ขออภัย ระบบ AI ไม่สามารถประมวลผลคำขอได้ในขณะนี้';
        } else {
            $aiReply = "Response โครงสร้างผิดพลาดโปรดลองอีกครั้ง";
        }
    } else {
        error_log('Groq connection error: ' . $curlError);
        $aiReply = 'ขออภัย ไม่สามารถเชื่อมต่อระบบ AI ได้ในขณะนี้';
    }

    // 8. ส่งคำตอบกลับไปหาหน้าบ้าน
    header('Content-Type: application/json');
    echo json_encode(['reply' => $aiReply]);
    exit;
}