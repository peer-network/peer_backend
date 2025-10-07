<?php

declare(strict_types=1);

namespace Fawaz\Mail;

use Fawaz\Mail\Interface\EmailInterface;
use Fawaz\Services\SmtpMailer;

class PasswordRestMail implements EmailInterface
{
    public function __construct(public $data)
    {

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
        $subject = "Reset Your Password";

        $mailer = new SmtpMailer();
        return $mailer->sendEmail($email, $subject, $this->content());
    }
}
