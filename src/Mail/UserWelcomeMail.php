<?php

namespace Fawaz\Mail;

use Fawaz\Mail\Interface\EmailInterface;
use Fawaz\Services\SmtpMailer;

class UserWelcomeMail implements EmailInterface
{
    public function __construct(public array $data){
        
    }

    /**
     * Calculate content of email
     */
    public function content(): string
    {
        return "
            <html lang='en'>
            <head>
            <meta charset='UTF-8' />
            <meta name='viewport' content='width=device-width, initial-scale=1' />
            </head>
            <body style='margin:0; padding:0; background-color:#fff; font-family: Arial, sans-serif;'>
                <div style='background-color: black; padding: 1.5rem; display: flex; justify-content: center;'>
                    <h1 style='color: #d1d5db; font-weight: 600; font-size: 1.125rem; margin: 0;'>Welcome to Peer Network!,</h1>
                </div>
                 <p style='font-size: 16px;'>Hi <strong>{$this->data['username']}</strong>,</p>
                 <p style='font-size: 16px;'>We are thrilled to have you as a part of our growing community. Peer Network is more than just a platform, it is a movement where your voice earns value.</p>
                 <p style='font-size: 16px;'>Here, every post, like, and interaction brings you closer to meaningful rewards through Peer Tokens.</p>
                 <p>Thank You for joining the Journey of Peer Network Login, Start Posting, Earn Tokens and spread the word that Peer is there!</p>
                <div style='font-size: 12px; color: #777; margin-top: 30px;'>
                    <p>Need help or have questions? Feel free to reach out to our team anytime.</p>
                    <p>The Peer Team</p>
                </div>
                <footer style='background-color:#000; color:#d1d5db; padding:22px 22px; display:flex; justify-content:space-between; align-items:flex-start; max-width:100%;'>
                    <div style='max-width:260px;'>
                        <a href='https://peerapp.de/'><p style='font-weight:700; font-size:14px; line-height:20px; margin-bottom:8px;'>Peer Network PSE UG</p></a>
                        <a href='https://maps.app.goo.gl/SsfydVPWMJ7HmHc3A'><p style='font-size:12px; line-height:20px; margin-bottom:4px;'>Mockernstrasse 68, 10965, Berlin</p></a>
                        <a href='mailto:peertoken@gmail.com'><p style='font-size:12px; line-height:20px; margin:0;'>peertoken@gmail.com</p></a>
                    </div>
                    <div style='text-align-last:right; font-size:12px; line-height:14px; color:#9ca3af;'>
                        <a href='https://www.freeprivacypolicy.com/live/02865c3a-79db-4baf-9ca1-7d91e2cf1724' style='color:#9ca3af; text-decoration:underline; display:block; margin-bottom:4px;'>Privacy</a><br />
                        <a href='https://peerapp.de/imprint.html' style='color:#9ca3af; text-decoration:underline; display:block; margin-bottom:4px;'>Imprint</a><br />
                        <a href='#' style='color:#9ca3af; text-decoration:underline; display:block;'>Abbestellen</a>
                    </div>
                </footer>
            </body>
            </html>
        ";
    }

    /**
     * Should be calculate Content and send email to
     */
    public function send(string $email): array
    {
        $subject = "Welcome to Peer Network - You just Registered an Account";

        $mailer = new SmtpMailer();
        return $mailer->sendEmail($email, $subject, $this->content());
    }
}
