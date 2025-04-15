<?php

namespace Fawaz\App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Psr\Log\LoggerInterface;

class MailService {

    public function sendPasswordResetEmail(string $email, string $mailSubject, string $mailBody)
    {
        try {
            $mail = new PHPMailer;
    
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'];
            $mail->Port = $_ENV['MAIL_PORT'];
            $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
            $mail->SMTPAuth = $_ENV['MAIL_SMTPAUTH'];
            $mail->Username = $_ENV['MAIL_USERNAME'];
            $mail->Password = $_ENV['MAIL_PASSWORD'];
            $mail->isHTML(true);
            $mail->From = $_ENV['MAIL_FROM_ADDRESS'];
            $mail->FromName =  $_ENV['MAIL_FROM_NAME'];
    
            $mail->addAddress($email);
            $mail->Subject = $mailSubject;
            $mail->Body = $mailBody;
    
            return $mail->send();
    
        } catch (\Exception $e) {
            return false;
        }
    }
    
}