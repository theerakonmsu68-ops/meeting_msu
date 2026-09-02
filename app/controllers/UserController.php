<?php

class UserController {

    private $userModel;
    private $uploadDir;

  // ⬇️ ตรวจสอบและแก้ไขช่วงบรรทัดนี้ใน UserController.php ครับ
    public function __construct($userModel) {
        $this->userModel = $userModel; // ✨ ต้องเพิ่มบรรทัดนี้ไว้บนสุด ห้ามลืมเด็ดขาด!
        
        $this->uploadDir = realpath(__DIR__ . '/../../') . '/public/uploads/avatars/';
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    private function handleImageUpload($file, $oldPicture = null) {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return $oldPicture; 
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedMimeTypes)) {
            return 'invalid_type';
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            return 'invalid_size';
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = 'avatar_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $targetPath = $this->uploadDir . $newFileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // ⚡️ ตรวจสอบเงื่อนไข: จะลบไฟล์บนดิสก์ก็ต่อเมื่อรูปภาพเดิมไม่ใช่ URL สตริงจากเซิร์ฟเวอร์ Google
            if ($oldPicture && !str_starts_with($oldPicture, 'http://') && !str_starts_with($oldPicture, 'https://')) {
                if (file_exists($this->uploadDir . $oldPicture)) {
                    $oldFileRealPath = realpath($this->uploadDir . $oldPicture);
                    if ($oldFileRealPath && str_starts_with($oldFileRealPath, $this->uploadDir)) {
                        @unlink($oldFileRealPath);
                    }
                }
            }
            return $newFileName;
        }

        return $oldPicture;
    }

    public function getAllUsers() {
        return $this->userModel->getAll();
    }

    public function getUserById($id) {
        if (!$id) return null;
        return $this->userModel->getById($id);
    }

    public function updateUser($data, $file = null) {
        $id             = $data['user_id'] ?? null;
        $username       = trim($data['username'] ?? '');
        $name           = trim($data['name'] ?? '');
        $email          = trim($data['email'] ?? '');
        $role_id        = $data['role_id'] ?? 2;
        $status         = $data['status'] ?? 'active';
        $password       = trim($data['password'] ?? '');
        $department_id  = $data['department_id'] ?? null; 

        if (!$id || $username === '' || $name === '' || $email === '') {
            return ["status" => "error", "message" => "ข้อมูลไม่ครบถ้วน"];
        }

        $duplicate = $this->userModel->checkDuplicate($username, $email, $id);
        if ($duplicate === "username") return ["status" => "error", "message" => "ชื่อผู้ใช้งานนี้มีผู้ใช้อื่นใช้งานแล้ว"];
        if ($duplicate === "email") return ["status" => "error", "message" => "อีเมลนี้มีผู้ใช้อื่นใช้งานแล้ว"];

        $currentUser = $this->userModel->getById($id);
        $oldPicture = $currentUser['picture'] ?? null;

        $picture = $this->handleImageUpload($file, $oldPicture);
        if ($picture === 'invalid_type') return ["status" => "error", "message" => "ประเภทไฟล์ไม่ถูกต้อง (อนุญาตเฉพาะ JPG, PNG, WEBP)"];
        if ($picture === 'invalid_size') return ["status" => "error", "message" => "ขนาดรูปภาพห้ามเกิน 2MB"];

        $hashedPassword = $password ? password_hash($password, PASSWORD_DEFAULT) : null;

        $result = $this->userModel->update(
            $id,
            $username,
            $name,
            $email,
            $role_id,
            $status,
            $hashedPassword,
            $department_id,
            $picture
        );

        return $result
            ? ["status" => "success", "message" => "อัปเดตข้อมูลสำเร็จ"]
            : ["status" => "error", "message" => "อัปเดตไม่สำเร็จ"];
    }

    public function createUser($data, $file = null) {
        $username      = trim($data['username'] ?? '');
        $password      = trim($data['password'] ?? '');
        $name          = trim($data['name'] ?? '');
        $email         = trim($data['email'] ?? '');
        $role_id       = $data['role_id'] ?? 2;
        $status        = $data['status'] ?? 'active';      
        $department_id = $data['department_id'] ?? null; 

        if ($username === '' || $password === '' || $name === '' || $email === '') {
            return ["status" => "error", "message" => "กรุณากรอกข้อมูลให้ครบทุกช่อง"];
        }

        $duplicate = $this->userModel->checkDuplicate($username, $email);
        if ($duplicate === "username") return ["status" => "error", "message" => "ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว"];
        if ($duplicate === "email") return ["status" => "error", "message" => "อีเมลนี้มีอยู่ในระบบแล้ว"];

        $picture = $this->handleImageUpload($file, null);
        if ($picture === 'invalid_type') return ["status" => "error", "message" => "ประเภทไฟล์ไม่ถูกต้อง (อนุญาตเฉพาะ JPG, PNG, WEBP)"];
        if ($picture === 'invalid_size') return ["status" => "error", "message" => "ขนาดรูปภาพห้ามเกิน 2MB"];

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $result = $this->userModel->create(
            $username,
            $hash,
            $name,
            $email,
            $role_id,
            $status,
            $department_id,
            $picture
        );

        return $result
            ? ["status" => "success", "message" => "เพิ่มผู้ใช้ใหม่สำเร็จ"]
            : ["status" => "error", "message" => "สร้างผู้ใช้ไม่สำเร็จ"];
    }

    public function deleteUser($id) {
        if (!$id) return ["status" => "error", "message" => "ไม่พบ ID ที่ต้องการลบ"];
        
        try {
            $currentUser = $this->userModel->getById($id);
            $oldPicture = $currentUser['picture'] ?? null;

            $result = $this->userModel->delete($id);
            
            // ⚡️ ตรวจสอบเงื่อนไข: สั่งลบรูปภาพเฉพาะกรณีที่เป็นภาพอัปโหลดปกติเท่านั้น ไม่ลบลิงก์ Google URL
            if ($result && $oldPicture && !str_starts_with($oldPicture, 'http://') && !str_starts_with($oldPicture, 'https://')) {
                if (file_exists($this->uploadDir . $oldPicture)) {
                    $oldFileRealPath = realpath($this->uploadDir . $oldPicture);
                    if ($oldFileRealPath && str_starts_with($oldFileRealPath, $this->uploadDir)) {
                        @unlink($oldFileRealPath);
                    }
                }
            }

            return $result
                ? ["status" => "success", "message" => "ลบข้อมูลสำเร็จ"]
                : ["status" => "error", "message" => "ลบไม่สำเร็จ"];
                
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                return ["status" => "error", "message" => "ไม่สามารถลบผู้ใช้นี้ได้ เนื่องจากมีประวัติงานประชุมผูกค้างไว้ในระบบ"];
            }
            return ["status" => "error", "message" => "เกิดข้อผิดพลาดในการเข้าถึงฐานข้อมูล"];
        }
    }
}