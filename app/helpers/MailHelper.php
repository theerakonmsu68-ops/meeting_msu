<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require_once __DIR__ . '/../../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';


class MailHelper
{

    public static function send(
        string $email,
        string $name,
        string $subject,
        string $body
    ): bool {


        $config = require __DIR__ . '/../config/mail.php';


        $mail = new PHPMailer(true);


        try {


            $mail->isSMTP();

            $mail->Host = $config['host'];

            $mail->SMTPAuth = true;

            $mail->Username = $config['username'];

            $mail->Password = $config['password'];

            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_STARTTLS;

            $mail->Port =
                $config['port'];


            $mail->CharSet = 'UTF-8';


            $mail->setFrom(
                $config['from_email'],
                $config['from_name']
            );


            $mail->addAddress(
                $email,
                $name
            );


            $mail->isHTML(true);

            $mail->Subject = $subject;

            $mail->Body = $body;


            $mail->send();


            return true;


        } catch(Exception $e){

            error_log(
                'MAIL ERROR : '.$mail->ErrorInfo
            );

            return false;
        }

    }


}