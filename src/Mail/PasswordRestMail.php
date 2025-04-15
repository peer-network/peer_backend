<?php

namespace Fawaz\Mail;

use Fawaz\App\MailService;
use Fawaz\Mail\Interface\EmailInterface;

class PasswordRestMail extends MailService implements EmailInterface
{
    protected $subject = 'Reset Your Password';

    public function __construct(public $data) {}

    /**
     * Calculate content of email
     */
    public function content(): string
    {
        return "
            <p>Hello,</p>
            <p>We received a request to reset the password for your <b>Peer App</b>.</p>
            <p>Please use the token below to reset your password:</p>
            <h3>{$this->data['code']}</h3>
            <p>If you didn't request this, you can safely ignore this email - no changes will be made to your account.</p>
            <p>Thank you,<br>The Peer Team</p>
        ";
    }

    /**
     * Should be calculate Content and send email to
     */
    public function send(string $email): bool
    {
        return $this->sendPasswordResetEmail($email, $this->subject, $this->content());
    }
}
