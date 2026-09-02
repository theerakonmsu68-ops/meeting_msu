<?php

class Meeting
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAllMeetings()
    {
        // ใช้ GROUP_CONCAT เพื่อรวบรวมไฟล์แนบทั้งหมดจากตาราง documents มารวมเป็นก้อนเดียวกันต่อ 1 การประชุม
        $stmt = $this->conn->prepare("
            SELECT 
                m.*,
                u.username AS creator_name,
                COUNT(DISTINCT a.agenda_id) AS agenda_count,
                GROUP_CONCAT(DISTINCT CONCAT(d.document_name, '::', d.file_path) SEPARATOR '||') AS attached_files
            FROM meeting m
            LEFT JOIN user u ON m.user_id = u.user_id
            LEFT JOIN agenda a 
            ON m.meeting_id = a.meeting_id
            AND a.admin_status = 'approved'
            LEFT JOIN documents d ON m.meeting_id = d.meeting_id
            GROUP BY m.meeting_id
            ORDER BY m.meeting_date DESC
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMeetingById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM meeting WHERE meeting_id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        // เพิ่มคอลัมน์ meeting_link เข้าไปตามที่เรา ALTER TABLE เพิ่มไว้
        $stmt = $this->conn->prepare("
            INSERT INTO meeting
            (meeting_title, meeting_date, meeting_time, meeting_location, user_id, meeting_link)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['meeting_title'],
            $data['meeting_date'],
            $data['meeting_time'],
            $data['meeting_location'],
            $data['user_id'],
            $data['meeting_link']
        ]);

        return $this->conn->lastInsertId();
    }

    public function update($id, $data)
    {
        // ปรับแต่งคำสั่ง SQL SET ให้รองรับการแก้ไขคอลัมน์ meeting_link ด้วย
        $stmt = $this->conn->prepare("
            UPDATE meeting SET
                meeting_title=?,
                meeting_date=?,
                meeting_time=?,
                meeting_location=?,
                meeting_link=?
            WHERE meeting_id=?
        ");

        return $stmt->execute([
            $data['meeting_title'],
            $data['meeting_date'],
            $data['meeting_time'],
            $data['meeting_location'],
            $data['meeting_link'],
            $id
        ]);
    }

    public function delete($id)
    {
        // ปล่อยให้ api.php ทำหน้าที่จัดการ transaction เคลียร์ไฟล์และตารางย่อยอื่นๆ 
        // ฟังก์ชันนี้ลบเฉพาะแถวของตาราง meeting หลัก
        $stmt = $this->conn->prepare("DELETE FROM meeting WHERE meeting_id=?");
        return $stmt->execute([$id]);
    }
}