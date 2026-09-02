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
            getenv('DB_HOST') ?: 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';

        $this->db_name =
            getenv('DB_NAME') ?: 'meeting_msu';

        $this->username =
            getenv('DB_USER') ?: '3TTpYYVbypPCGc5.root';

        $this->password =
            getenv('DB_PASS') ?: '7h7ILagXhjoOs1iQ';

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


        } catch(PDOException $e) {


            error_log(
                "Database Connection Error: "
                . $e->getMessage()
            );


            die($e->getMessage());

        }


        return $this->conn;

    }

}