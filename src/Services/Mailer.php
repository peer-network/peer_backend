<?php 
declare(strict_types=1);

namespace Fawaz\Services;

use Psr\Log\LoggerInterface;

class Mailer
{
    private array $envi;
    private LoggerInterface $logger;

    public function __construct(
		array $envi,
        LoggerInterface $logger
    ) {
        $this->envi = $envi;
        $this->logger = $logger;
    }

    public function sendViaAPI(array $payload): array
    {

		$mailApiLink = $this->envi['mailapilink'];
		$mailApiKey = $this->envi['mailapikey'];
		$this->logger->info("Payload:", ['payload' => $payload]);

        $ch = curl_init($mailApiLink);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'api-key: ' . $mailApiKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            $this->logger->error("Brevo API Error: $error");
            curl_close($ch);
            return ['status' => 'error', 'message' => $error];
        }

        curl_close($ch);
        return ['status' => $httpCode == 201 ? 'success' : 'error', 'response' => json_decode($response, true)];
    }
}
