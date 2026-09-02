<?php

class Database {
    private $host = "localhost";
    private $db_name = "meeting_msu";
    private $username = "root";
    private $password = "";
    public $conn;

    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", // 1. แนะนำใช้ utf8mb4 รองรับภาษาไทยสมบูรณ์แบบและอิโมจิ
                $this->username,
                $this->password
            );

            // 2. ตั้งค่าแจ้งเตือน Error แบบ Exception (เปิดไว้ดีมาก)
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 3. เพิ่มบรรทัดนี้: ตั้งค่าให้ดึงข้อมูลออกมาเป็น Array แบบจับคู่ชื่อคอลัมน์ (Associative Array) เป็นค่าเริ่มต้น
            // ทำให้ตอนไปเขียน FETCH ข้อมูลในหน้าอื่นๆ โค้ดจะสั้นลงเยอะมากครับ
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            // 4. แก้ไขจุดนี้: ห้ามพ่น Error ละเอียดออกหน้าจอหลัก ให้บันทึกเป็น Log หรือแสดงข้อความกลางๆ เพื่อความปลอดภัย
            error_log("Database Connection Error: " . $e->getMessage()); // บันทึกเก็บไว้ดูเองหลังบ้าน
            die("ระบบขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้งในภายหลัง"); // ข้อความสุภาพที่แสดงให้ผู้ใช้ทั่วไปเห็น
        }

        return $this->conn;
    }
}