<?php

class Agenda
{
    private $conn;


    public function __construct($db)
    {
        $this->conn = $db;
    }



    /**
     * ดึงรายการวาระทั้งหมดสำหรับ Admin
     */
    public function getAllAgendas($limit = 10, $offset = 0)
    {

        $stmt = $this->conn->prepare("

            SELECT

                a.*,

                u.name AS submitter_name,

                d.department_name,

                COUNT(ad.agenda_document_id)
                AS document_count


            FROM agenda a


            LEFT JOIN user u
                ON u.user_id = a.submitted_by


            LEFT JOIN departments d
                ON d.department_id = a.department_id


            LEFT JOIN agenda_documents ad
                ON ad.agenda_id = a.agenda_id


            WHERE

                a.department_id IS NOT NULL

                AND a.submitted_by IS NOT NULL


            GROUP BY

                a.agenda_id


            ORDER BY

                a.created_at DESC


            LIMIT :limit OFFSET :offset

        ");


        $stmt->bindValue(
            ':limit',
            (int) $limit,
            PDO::PARAM_INT
        );


        $stmt->bindValue(
            ':offset',
            (int) $offset,
            PDO::PARAM_INT
        );


        $stmt->execute();


        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }





    /**
     * จำนวนวาระทั้งหมด
     */
    public function countAgendas()
    {

        $stmt = $this->conn->prepare("

            SELECT COUNT(*)

            FROM agenda


            WHERE

                department_id IS NOT NULL

                AND submitted_by IS NOT NULL

        ");


        $stmt->execute();


        return (int) $stmt->fetchColumn();

    }





    /**
     * สรุปสถานะวาระ
     */
    public function getAgendaStats()
    {

        $stmt = $this->conn->prepare("

            SELECT


                COUNT(*) AS total_agendas,


                SUM(
                    CASE
                        WHEN admin_status='pending'
                        THEN 1
                        ELSE 0
                    END
                ) AS pending_agendas,


                SUM(
                    CASE
                        WHEN admin_status='approved'
                        THEN 1
                        ELSE 0
                    END
                ) AS approved_agendas,


                SUM(
                    CASE
                        WHEN admin_status='revision'
                        THEN 1
                        ELSE 0
                    END
                ) AS revision_agendas



            FROM agenda


            WHERE

                department_id IS NOT NULL

                AND submitted_by IS NOT NULL

        ");


        $stmt->execute();


        return $stmt->fetch(PDO::FETCH_ASSOC);

    }





    /**
     * รายละเอียดวาระ
     */
    public function getAgendaById($id)
    {

        $stmt = $this->conn->prepare("

        SELECT

            a.*,

            m.meeting_title,

            m.meeting_number,

            m.meeting_date,

            m.meeting_time,

            u.name AS submitter_name,

            d.department_name


        FROM agenda a


        LEFT JOIN meeting m
            ON m.meeting_id = a.meeting_id


        LEFT JOIN user u
            ON u.user_id = a.submitted_by


        LEFT JOIN departments d
            ON d.department_id = a.department_id


        WHERE a.agenda_id = ?


        LIMIT 1

    ");


        $stmt->execute([$id]);


        $agenda = $stmt->fetch(PDO::FETCH_ASSOC);



        if (!$agenda) {

            return null;

        }



        $docStmt = $this->conn->prepare("

        SELECT

            agenda_document_id,

            document_name,

            file_path,

            file_size,

            mime_type,

            uploaded_at,

            upload_date


        FROM agenda_documents


        WHERE agenda_id = ?


        ORDER BY uploaded_at DESC

    ");



        $docStmt->execute([$id]);



        $agenda['documents'] =
            $docStmt->fetchAll(PDO::FETCH_ASSOC);



        return $agenda;

    }





    /**
     * เปลี่ยนสถานะตรวจสอบโดย Admin
     */
    public function updateAdminStatus($agendaId, $status)
    {


        $allowed = [

            'pending',

            'approved',

            'rejected',

            'revision'

        ];



        if (!in_array($status, $allowed, true)) {
            return false;
        }




        $stmt = $this->conn->prepare("

            UPDATE agenda

            SET

                admin_status=?


            WHERE

                agenda_id=?

        ");



        return $stmt->execute([

            $status,

            $agendaId

        ]);

    }





    /**
     * ลบวาระ
     */
    public function deleteAgenda($agendaId)
    {

        try {


            $this->conn->beginTransaction();



            // ลบไฟล์แนบของวาระก่อน

            $stmt = $this->conn->prepare("

                DELETE FROM agenda_documents

                WHERE agenda_id = ?

            ");


            $stmt->execute([

                $agendaId

            ]);





            // ลบวาระ

            $stmt = $this->conn->prepare("

                DELETE FROM agenda

                WHERE agenda_id = ?

            ");



            $result = $stmt->execute([

                $agendaId

            ]);




            $this->conn->commit();



            return $result;



        } catch (Exception $e) {


            $this->conn->rollBack();


            return false;


        }

    }


}