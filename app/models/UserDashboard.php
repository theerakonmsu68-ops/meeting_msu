<?php

class UserDashboard
{

    private PDO $conn;


    public function __construct($db)
    {
        $this->conn = $db;
    }





    /**
     * รายการประชุมล่าสุดสำหรับ User
     */
    public function getMeetings($limit = 5, $offset = 0)
    {

        $sql = "

            SELECT

                m.*,

                u.name AS creator_name,


                (
                    SELECT d.file_path
                    FROM documents d
                    WHERE d.meeting_id = m.meeting_id
                    ORDER BY d.document_id DESC
                    LIMIT 1
                ) AS file_path,


                (
                    SELECT COUNT(*)
                    FROM agenda a
                    WHERE a.meeting_id = m.meeting_id
                ) AS agenda_count



            FROM meeting m


            LEFT JOIN user u

                ON m.user_id = u.user_id



            ORDER BY

                m.meeting_date DESC,

                m.meeting_time DESC



            LIMIT :limit OFFSET :offset

        ";



        $stmt = $this->conn->prepare($sql);


        $stmt->bindValue(
            ':limit',
            (int)$limit,
            PDO::PARAM_INT
        );


        $stmt->bindValue(
            ':offset',
            (int)$offset,
            PDO::PARAM_INT
        );


        $stmt->execute();


        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }








    /**
     * จำนวนประชุมทั้งหมด
     */
    public function countMeetings()
    {

        $stmt = $this->conn->prepare(

            "SELECT COUNT(*) FROM meeting"

        );


        $stmt->execute();


        return (int)$stmt->fetchColumn();

    }








    /**
     * สรุปสถานะ Dashboard
     */
    public function getMeetingStats()
    {

        $today = date('Y-m-d');


        $sql = "

        SELECT


        COUNT(*) AS total,


        SUM(

            CASE

            WHEN meeting_date = :today

            THEN 1

            ELSE 0

            END

        ) AS today_meetings,



        SUM(

            CASE

            WHEN meeting_status='ongoing'

            THEN 1

            ELSE 0

            END

        ) AS ongoing_meetings,



        SUM(

            CASE

            WHEN meeting_status='closed'

            THEN 1

            ELSE 0

            END

        ) AS finished_meetings



        FROM meeting

        ";



        $stmt = $this->conn->prepare($sql);


        $stmt->bindValue(
            ':today',
            $today
        );


        $stmt->execute();


        return $stmt->fetch(PDO::FETCH_ASSOC);

    }








    /**
     * Notification ล่าสุด
     */
    public function getNotifications($limit = 3)
    {

        $stmt = $this->conn->prepare(

            "

            SELECT

                meeting_title,

                created_at


            FROM meeting


            ORDER BY created_at DESC


            LIMIT :limit

            "

        );


        $stmt->bindValue(
            ':limit',
            (int)$limit,
            PDO::PARAM_INT
        );


        $stmt->execute();


        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }








    /**
     * ดึงวาระประชุม
     */
    public function getAgenda($meetingId)
    {

        $stmt = $this->conn->prepare(

            "

            SELECT *

            FROM agenda

            WHERE meeting_id = :meeting_id

            ORDER BY agenda_id ASC

            "

        );


        $stmt->bindValue(
            ':meeting_id',
            (int)$meetingId,
            PDO::PARAM_INT
        );


        $stmt->execute();


        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }


}