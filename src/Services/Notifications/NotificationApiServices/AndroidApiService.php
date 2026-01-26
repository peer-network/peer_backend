<?php

namespace Fawaz\Services\Notifications\NotificationApiServices;

use Fawaz\App\Models\UserDeviceToken;
use Fawaz\Services\Notifications\Interface\ApiService;
use Fawaz\Services\Notifications\Helpers\AndroidPayloadStructure;
use Fawaz\Services\Notifications\Interface\NotificationPayload;
use Fawaz\Utils\PeerLoggerInterface;

class AndroidApiService implements ApiService
{
    public function __construct(protected PeerLoggerInterface $logger){}
    
    public static function sendNotification(NotificationPayload $payload, UserDeviceToken $receiver): bool
    {
        $payload = (new AndroidPayloadStructure())->payload($payload);

        $deviceToken = $receiver->getDeviceToken();

        return (new self())->send($payload, $deviceToken);
    }


    protected function send($payload, $deviceToken): bool
    {
        try{
            $projectIdNToken = $this->getAccessToken(); // OAuth 2.0 token

            if(empty($projectIdNToken['access_token']) || empty($projectIdNToken['project_id'])) {
                throw new \RuntimeException("Failed to get access token or project ID.");
            }

            $url = "https://fcm.googleapis.com/v1/projects/{$projectIdNToken['project_id']}/messages:send";

            // Inject device token
            $payload['message']['token'] = $deviceToken;

            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $projectIdNToken['access_token'],
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
            ]);

            $response = curl_exec($ch);

            if ($response === false) {
                curl_close($ch);
                return false;
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 200 = success
            return $httpCode === 200;
        
        }catch(\Exception $e){
            $this->logger->error("Error sending notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Returns OAuth 2.0 access token for Firebase
     * (Service Account based)
     */
    protected function getAccessToken(): array
    {
        try {

            // Put the real service account JSON here 
            $serviceAccountPath = __DIR__ . '/../../../ServerConfigKeys/' . $_ENV['ANDROID_SERVER_KEY_JSON'];

            if (!is_file($serviceAccountPath)) {
                throw new \RuntimeException("Service account file not found: {$serviceAccountPath}");
            }

            $serviceAccount = json_decode((string) file_get_contents($serviceAccountPath), true);
            if (!is_array($serviceAccount)) {
                throw new \RuntimeException("Service account JSON is invalid.");
            }

            if (empty($serviceAccount['client_email']) || empty($serviceAccount['private_key']) || empty($serviceAccount['project_id'])) {
                throw new \RuntimeException("Service account JSON missing client_email or private_key.");
            }

            $jwt = $this->generateJwt($serviceAccount);

            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_POSTFIELDS     => http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]),
                CURLOPT_TIMEOUT        => 15,
            ]);

            $response = curl_exec($ch);
            if ($response === false) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new \RuntimeException("Curl error calling token endpoint: {$err}");
            }

            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);
            if (!is_array($data)) {
                throw new \RuntimeException("Token endpoint returned non-JSON: {$response}");
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                // This is the real reason you currently get null
                $msg = $data['error_description'] ?? ($data['error'] ?? 'unknown_error');
                throw new \RuntimeException("Google token error ({$httpCode}): {$msg}");
            }

            if (empty($data['access_token'])) {
                throw new \RuntimeException("No access_token in response: " . json_encode($data));
            }

            return ['access_token' => $data['access_token'], 'project_id' => $serviceAccount['project_id'] ?? null ];
        } catch (\Exception $e) {
            $this->logger->error("Error sending notification: Fail to generate access token: " . $e->getMessage());
            return [];
        }
    }

    protected function generateJwt(array $serviceAccount): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];

        // Optional but recommended if present:
        if (!empty($serviceAccount['private_key_id'])) {
            $header['kid'] = $serviceAccount['private_key_id'];
        }

        $now = time();
        $claims = [
            'iss'   => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $base64Header = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $base64Claims = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
        $data = $base64Header . '.' . $base64Claims;

        $signature = '';
        $ok = openssl_sign($data, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256);

        if (!$ok) {
            throw new \RuntimeException("openssl_sign failed. Check that private_key is valid PEM.");
        }

        $base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        return $data . '.' . $base64Signature;
    }
}
