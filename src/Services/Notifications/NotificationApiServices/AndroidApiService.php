<?php

use Fawaz\App\Models\UserDeviceToken;

class AndroidApiService implements ApiService
{
    public static function sendNotification(NotificationPayload $payload, UserDeviceToken $receiver): bool
    {
        $payload = (new AndroidPayloadStructure())->payload($payload);

        $deviceToken = $receiver->getDeviceToken();

        return (new self())->send($payload, $deviceToken);
    }


    protected function send($payload, $deviceToken): bool
    {
        $projectId   = 'peer-de113';
        $accessToken = $this->getAccessToken(); // OAuth 2.0 token

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        // Inject device token
        $payload['message']['token'] = $deviceToken;

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
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

    }

     /**
     * Returns OAuth 2.0 access token for Firebase
     * (Service Account based)
     */
    protected function getAccessToken(): string
    {
        $serviceAccountPath = __DIR__ . '/firebase-google-services.json';

        $jwt = $this->generateJwt(
            json_decode(file_get_contents($serviceAccountPath), true)
        );

        $ch = curl_init('https://oauth2.googleapis.com/token');

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        return $data['access_token'];
    }

    /**
     * Generates signed JWT for Google OAuth
     */
    protected function generateJwt(array $serviceAccount): string
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

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

        openssl_sign(
            $data,
            $signature,
            $serviceAccount['private_key'],
            OPENSSL_ALGO_SHA256
        );

        $base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $data . '.' . $base64Signature;
    }
}