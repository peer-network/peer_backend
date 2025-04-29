<?php

namespace Fawaz\Mail;

use Fawaz\Mail\Interface\EmailInterface;
use Fawaz\Services\BrevoMailer;
use Fawaz\Services\Mailer;
use Psr\Log\LoggerInterface;

class PasswordRestMail implements EmailInterface
{
    protected $subject = 'Reset Your Password';
    protected  $mailer;

    public function __construct(public $data) {
        
    }

    /**
     * Calculate content of email
     */
    public function content(): string
    {
        return "
            <html><head></head><body>
            <p>Hello,</p>
            <p>We received a request to reset the password for your <b>Peer App</b>.</p>
            <p>Please use the token below to reset your password:</p>
            <h3>{$this->data['code']}</h3>
            <p>If you didn't request this, you can safely ignore this email - no changes will be made to your account.</p>
            <p>Thank you,<br>Peer Team</p>
            </body></html>
        ";
    }

    /**
     * Should be calculate Content and send email to
     */
    public function send(string $email): array
    {
        $mailData = [
            "sender" => [
                "name" => $_ENV['MAIL_FROM_NAME'],
                "email" => $_ENV['MAIL_FROM_ADDRESS']
            ],
            "to" => [
                [
                    "email" => $email,
                    "name" => "User"
                ]
            ],
            "subject" => "Reset Your Password",
            "htmlContent" => $this->content()
        ];
    
        $mailer = new BrevoMailer();
        return $mailer->sendViaAPI($mailData);
    }
}
