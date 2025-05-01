<?php 
declare(strict_types=1);

namespace Fawaz\Services;

use Psr\Log\LoggerInterface;

class BrevoMailer
{

    public function sendViaAPI(array $mailData): array
    {
        $mailApiLink = $_ENV['MAIL_API_LINK'];
        $mailApiKey = $_ENV['MAIL_API_KEY'];

        $payload = json_encode($mailData);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $mailApiLink,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array(
                'accept: application/json',
                'api-key: ' . $mailApiKey,
                'content-type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return [
            'status' => $httpCode === 201 ? 'success' : 'error',
            'http_code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }
}
