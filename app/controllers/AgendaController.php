<?php

class AgendaController
{

    private $model;



    public function __construct($model)
    {
        $this->model = $model;
    }





    /**
     * ดึงรายการวาระ
     */
    public function getAllAgendas($limit = 10, $offset = 0)
    {
        return $this->model->getAllAgendas(
            $limit,
            $offset
        );
    }





    /**
     * จำนวนวาระทั้งหมด
     */
    public function countAgendas()
    {
        return $this->model->countAgendas();
    }





    /**
     * สรุปสถานะวาระ
     */
    public function getAgendaStats()
    {
        return $this->model->getAgendaStats();
    }





    /**
     * รายละเอียดวาระ
     */
    public function getAgendaById($id)
    {
        return $this->model->getAgendaById(
            $id
        );
    }





    /**
     * เปลี่ยนสถานะ Admin
     */
    public function updateAdminStatus(
        $agendaId,
        $status
    )
    {

        return $this->model->updateAdminStatus(
            $agendaId,
            $status
        );

    }





    /**
     * ลบวาระ
     */
    public function deleteAgenda(
        $agendaId
    )
    {

        return $this->model->deleteAgenda(
            $agendaId
        );

    }


}