<?php

return [

    'host' => 'smtp.gmail.com',

    'username' => getenv('MEETING_MAIL_USERNAME') ?: '',

    // ใช้ App Password ของ Gmail
    'password' => getenv('MEETING_MAIL_PASSWORD') ?: '',

    'port' => 587,

    'from_email' => getenv('MEETING_MAIL_FROM_EMAIL') ?: '',

    'from_name' => 'ระบบประชุม MSU'

];