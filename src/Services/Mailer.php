<?php 
declare(strict_types=1);

namespace Fawaz\Services;

use Fawaz\Utils\PeerLoggerInterface;

class Mailer
{
    private array $envi;
    private PeerLoggerInterface $logger;

    public function __construct(
		array $envi,
        PeerLoggerInterface $logger
    ) {
        $this->envi = $envi;
        $this->logger = $logger;
    }

	public function sendViaAPI(array $payload): array
	{
		$mailApiLink = $this->envi['mailapilink'] ?? '';
		$mailApiKey = $this->envi['mailapikey'] ?? '';

		// Basic validation
		if (empty($mailApiLink) || empty($mailApiKey)) {
			$this->logger->error('Mailer config missing', ['link' => $mailApiLink, 'key' => $mailApiKey]);
			return ['status' => 'error', 'message' => 'Mailer config missing'];
		}

		$this->logger->info("Sending mail with payload:", ['payload' => $payload]);

		$ch = curl_init($mailApiLink);

		curl_setopt_array($ch, [
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => json_encode($payload),
			CURLOPT_HTTPHEADER => [
				'api-key: ' . $mailApiKey,
				'Accept: application/json',
				'Content-Type: application/json',
			],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 15,
		]);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		// Log full response
		$this->logger->info('Mailer response', [
			'httpCode' => $httpCode,
			'response' => $response,
			'error' => $curlError
		]);

		if ($response === false || $httpCode >= 400) {
			return [
				'status' => 'error',
				'message' => $curlError ?: "HTTP $httpCode",
				'response' => $response
			];
		}

		$decodedResponse = json_decode($response, true);

		return [
			'status' => ($httpCode === 201 || $httpCode === 202) ? 'success' : 'error',
			'response' => $decodedResponse
		];
	}
}
