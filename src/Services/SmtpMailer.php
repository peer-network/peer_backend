<?php 
declare(strict_types=1);

namespace Fawaz\Services;


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class SmtpMailer
{
    
    /**
     * Create and configure a PHPMailer instance.
     *
     * @return PHPMailer
     * @throws Exception
     */
    private function createMailerInstance(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['MAIL_USERNAME'];     
        $mail->Password = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];    
        $mail->Port =  $_ENV['MAIL_PORT'];
        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
        $mail->isHTML(true);

        return $mail;
    }

    /**
     * Sends an email using SMTP.
     *
     * @param string $email
     * @param string $subject
     * @param string $body
     * @return array{status: string, message?: string}
     */
    public function sendEmail(string $email, string $subject, string $body){

        try {
            $mail = $this->createMailerInstance();
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();

            return ['status' => 'success'];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
    }
}