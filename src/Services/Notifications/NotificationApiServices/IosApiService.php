<?php

namespace Fawaz\Services\Notifications\NotificationApiServices;

use Fawaz\App\Models\UserDeviceToken;
use Fawaz\Services\Notifications\Helpers\IosPayloadStructure;
use Fawaz\Services\Notifications\Interface\NotificationPayload;
use Fawaz\Utils\PeerLoggerInterface;

final class IosApiService
{
    public function __construct(protected PeerLoggerInterface $logger){}
    
    public function sendNotification(NotificationPayload $payload, UserDeviceToken $receiver): bool
    {
        $apnsPayload = (new IosPayloadStructure())->payload($payload);

        $deviceToken = $receiver->getDeviceToken();
        if (empty($deviceToken)) {
            return false;
        }

        return $this->triggerApi($apnsPayload, $deviceToken);
    }

    private function triggerApi($payload, $deviceToken): bool
    {
        try{
            // Ensure JSON string
            $json = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);

            if ($json === false) {
                return false;
            }

            $config = $this->apnsConfig();

            $jwt = $this->generateApnsJwt(
                $config['team_id'],
                $config['key_id'],
                $config['private_key_path']
            );

            if ($jwt === null) {
                return false;
            }

            $host = $config['use_sandbox'] ? 'https://api.sandbox.push.apple.com' :  'https://api.push.apple.com';

            // Device token must be hex, no spaces
            $deviceToken = preg_replace('/\s+/', '', $deviceToken);
            $url = $host . "/3/device/{$deviceToken}";

            $headers = [
                "authorization: bearer {$jwt}",
                "apns-topic: {$config['topic']}",
                "content-type: application/json",
            ];

            // Optional: if you carry an id to dedupe
            // $headers[] = "apns-id: " . $uuid;

            // Optional: immediate delivery (0) vs background (5)
            // If you send "content-available": 1 only, consider apns-push-type: background
            $headers[] = "apns-push-type: alert";

            // Optional: collapse id to collapse multiple notifications
            // $headers[] = "apns-collapse-id: some_key";

            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true, // so we can parse status + headers
                CURLOPT_TIMEOUT => (int)$config['timeout'],
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            ]);

            $response = curl_exec($ch);

            if ($response === false) {
                curl_close($ch);
                return false;
            }

            $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $body = substr($response, $headerSize);

            curl_close($ch);

            // APNs success is 200
            if ($statusCode === 200) {
                return true;
            }
            return false;
        
        }catch(\Exception $e){
            return true;
        }
    }

    private function apnsConfig(): array
    {
        // Adjust to your framework. This is Laravel-style; replace if needed.

        return [
            'team_id' => $_ENV['APNS_TEAM_ID'],
            'key_id' => $_ENV['APNS_KEY_ID'],
            'private_key_path' => __DIR__ . '/../../../ServerConfigKeys/' . $_ENV['APNS_AUTH'],
            'topic' => $_ENV['APNS_TOPIC'],
            'use_sandbox' => (bool) $_ENV['APNS_USE_SANDBOX'],
            'timeout' => (int)($_ENV['APNS_TIMEOUT'] ?? 10),
        ];
    }

    private function generateApnsJwt(string $teamId, string $keyId, string $privateKeyPath): ?string
    {
        $privateKey = @file_get_contents($privateKeyPath);
        if (!$privateKey) {
            return null;
        }

        $header = [
            'alg' => 'ES256',
            'kid' => $keyId,
        ];

        $now = time();
        $claims = [
            'iss' => $teamId,
            'iat' => $now,
        ];

        $base64UrlHeader = $this->base64UrlEncode(json_encode($header));
        $base64UrlClaims = $this->base64UrlEncode(json_encode($claims));
        $signingInput = $base64UrlHeader . '.' . $base64UrlClaims;

        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $privateKey, 'sha256');
        if (!$ok) {
            return null;
        }

        // openssl_sign returns DER-encoded signature; APNs JWT wants raw R|S (64 bytes)
        $rawSignature = $this->derToJose($signature, 64);
        if ($rawSignature === null) {
            return null;
        }

        return $signingInput . '.' . $this->base64UrlEncode($rawSignature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Convert DER ECDSA signature to JOSE (raw R|S).
     * @param string $der
     * @param int $partLength 64 for ES256 (32 bytes R + 32 bytes S)
     */
    private function derToJose(string $der, int $partLength): ?string
    {
        // Minimal DER parser for ECDSA signatures: 0x30 len 0x02 rlen r 0x02 slen s
        $hex = unpack('H*', $der)[1];

        // Quick sanity checks
        if (strlen($hex) < 16 || substr($hex, 0, 2) !== '30') {
            return null;
        }

        // Parse using binary for safety
        $bin = $der;
        $pos = 0;

        if (ord($bin[$pos++]) !== 0x30) return null;
        $seqLen = ord($bin[$pos++]);
        if ($seqLen & 0x80) { // long form length
            $bytes = $seqLen & 0x7F;
            $seqLen = 0;
            for ($i = 0; $i < $bytes; $i++) {
                $seqLen = ($seqLen << 8) | ord($bin[$pos++]);
            }
        }

        if (ord($bin[$pos++]) !== 0x02) return null;
        $rLen = ord($bin[$pos++]);
        $r = substr($bin, $pos, $rLen);
        $pos += $rLen;

        if (ord($bin[$pos++]) !== 0x02) return null;
        $sLen = ord($bin[$pos++]);
        $s = substr($bin, $pos, $sLen);

        // Strip leading zero padding and left-pad to 32 bytes each
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        $raw = $r . $s;

        // Expect 64 bytes for ES256
        if (strlen($raw) !== 64) {
            return null;
        }

        return $raw;
    }
}
