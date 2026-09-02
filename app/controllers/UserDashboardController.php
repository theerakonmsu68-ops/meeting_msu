<?php

class UserDashboardController
{

    private $model;



    public function __construct($model)
    {
        $this->model = $model;
    }





    /**
     * Dashboard Data
     */
    public function getDashboard($limit = 5, $offset = 0)
    {

        return [

            'meetings' => 
                $this->model->getMeetings(
                    $limit,
                    $offset
                ),


            'total_meetings' =>
                $this->model->countMeetings(),


            'stats' =>
                $this->model->getMeetingStats(),


            'notifications' =>
                $this->model->getNotifications()

        ];

    }





    /**
     * ดึงวาระประชุม
     */
    public function getAgenda($meetingId)
    {

        if(!$meetingId)
        {
            return [];
        }


        return $this->model->getAgenda(
            $meetingId
        );

    }


}