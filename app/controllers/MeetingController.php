<?php

class MeetingController
{
    private $model;

    public function __construct($model)
    {
        $this->model = $model;
    }

    public function getAllMeetings()
    {
        return $this->model->getAllMeetings();
    }

    public function get($id)
    {
        return $this->model->getMeetingById($id);
    }

    public function create($data)
    {
        return $this->model->create($data);
    }

    public function update($id, $data)
    {
        return $this->model->update($id, $data);
    }

    public function delete($id)
    {
        return $this->model->delete($id);
    }
}