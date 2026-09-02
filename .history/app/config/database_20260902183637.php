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

        $this->host =
            getenv('DB_HOST');

        $this->db_name =
            getenv('DB_NAME');

        $this->username =
            getenv('DB_USER');

        $this->password =
            getenv('DB_PASS');
    }


    public function connect()
    {

        $this->conn = null;


        try {


            $this->conn = new PDO(

                "mysql:host="
                    . $this->host .
                    ";port=4000" .
                    ";dbname="
                    . $this->db_name .
                    ";charset=utf8mb4",

                $this->username,

                $this->password,

                [
                    PDO::MYSQL_ATTR_SSL_CA => __DIR__ . '/isrgrootx1.pem'
                ]

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
