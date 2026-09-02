<?php

class User
{
    private PDO $conn;
    private string $table = 'user';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function login($username, $password)
    {
        $sql = "SELECT
                    u.*,
                    COALESCE(d.department_name, '') AS department_name,
                    COALESCE(p.position_name, '') AS position_name,
                    COALESCE(r.role_name, '') AS role_name
                FROM {$this->table} u
                LEFT JOIN departments d ON d.department_id = u.department_id
                LEFT JOIN positions p ON p.position_id = u.position_id
                LEFT JOIN role r ON r.role_id = u.role_id
                WHERE u.username = :username
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':username', trim((string)$username));
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify((string)$password, (string)$user['password'])) {
            return $user;
        }

        return false;
    }

    public function getAll()
    {
        $sql = "SELECT
                    u.*,
                    COALESCE(d.department_name, '') AS department_name,
                    COALESCE(p.position_name, '') AS position_name,
                    COALESCE(r.role_name, '') AS role_name
                FROM {$this->table} u
                LEFT JOIN departments d ON d.department_id = u.department_id
                LEFT JOIN positions p ON p.position_id = u.position_id
                LEFT JOIN role r ON r.role_id = u.role_id
                ORDER BY u.user_id ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $sql = "SELECT
                    u.*,
                    COALESCE(d.department_name, '') AS department_name,
                    COALESCE(p.position_name, '') AS position_name,
                    COALESCE(r.role_name, '') AS role_name
                FROM {$this->table} u
                LEFT JOIN departments d ON d.department_id = u.department_id
                LEFT JOIN positions p ON p.position_id = u.position_id
                LEFT JOIN role r ON r.role_id = u.role_id
                WHERE u.user_id = :id
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function checkDuplicate($username, $email, $exclude_id = null)
    {
        $sql = "SELECT user_id FROM {$this->table} WHERE username = :username";
        if ($exclude_id) $sql .= ' AND user_id != :exclude_id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':username', $username);
        if ($exclude_id) $stmt->bindValue(':exclude_id', (int)$exclude_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) return 'username';

        $sql = "SELECT user_id FROM {$this->table} WHERE email = :email";
        if ($exclude_id) $sql .= ' AND user_id != :exclude_id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':email', $email);
        if ($exclude_id) $stmt->bindValue(':exclude_id', (int)$exclude_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) return 'email';

        return false;
    }

    public function update($id, $username, $name, $email, $role_id, $status, $password = null, $department_id = null, $picture = null)
    {
        $passwordSql = $password ? ', password = :password' : '';
        $sql = "UPDATE {$this->table}
                SET username = :username,
                    name = :name,
                    email = :email,
                    role_id = :role_id,
                    status = :status,
                    department_id = :department_id,
                    picture = :picture
                    {$passwordSql}
                WHERE user_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':username', $username);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':role_id', (int)$role_id, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $stmt->bindValue(':picture', $picture);
        if ($password) $stmt->bindValue(':password', $password);

        if ($department_id === null || $department_id === '') {
            $stmt->bindValue(':department_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':department_id', (int)$department_id, PDO::PARAM_INT);
        }

        return $stmt->execute();
    }

    public function delete($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE user_id = :id");
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function create($username, $password, $name, $email, $role_id, $status = 'active', $department_id = null, $picture = null)
    {
        $sql = "INSERT INTO {$this->table}
                (username, password, name, email, role_id, status, login_type, department_id, picture)
                VALUES
                (:username, :password, :name, :email, :role_id, :status, 'normal', :department_id, :picture)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':username', $username);
        $stmt->bindValue(':password', $password);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':role_id', (int)$role_id, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':picture', $picture);

        if ($department_id === null || $department_id === '') {
            $stmt->bindValue(':department_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':department_id', (int)$department_id, PDO::PARAM_INT);
        }

        return $stmt->execute();
    }
}