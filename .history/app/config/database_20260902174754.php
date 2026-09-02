<?php

class Database
{

    private $host;
    private $db_name;
    private $username;
    private $password;

    public $conn;


    public function __construct()
    {
        // ถ้ามี Environment Variable ให้ใช้ค่าจาก Server
        // ถ้าไม่มี ให้ใช้ค่า Local XAMPP

        $this->host =
            getenv('DB_HOST') ?: 'localhost';

        $this->db_name =
            getenv('DB_NAME') ?: 'meeting_msu';

        $this->username =
            getenv('DB_USER') ?: 'root';

        $this->password =
            getenv('DB_PASS') ?: '';
    }


    public function connect()
    {
        $this->conn = null;


        try {

            $this->conn = new PDO(

                "mysql:host="
                    . $this->host .
                    ";dbname="
                    . $this->db_name .
                    ";charset=utf8mb4",

                $this->username,

                $this->password

            );


            $this->conn->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );


            $this->conn->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );
        } catch (PDOException $e) {


            error_log(
                "Database Connection Error: "
                    . $e->getMessage()
            );


            die("ระบบขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้งในภายหลัง");
        }


        return $this->conn;
    }
}
