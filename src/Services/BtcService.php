<?php

namespace Fawaz\Services;
use Fawaz\App\Models\TokenEuroPrice;
use Fawaz\App\Repositories\TokenEuroPriceRepository;
use Psr\Log\LoggerInterface;
use PDO;

class BtcService
{

     /**
     * Returns the current BTC/EUR price, updating the DB if needed.
     */
    public static function getOrUpdateBitcoinPrice(LoggerInterface $logger, PDO $db): string
    {
        $tokenPriceRepo = new TokenEuroPriceRepository($logger, $db);
        $btcTokenObj = new TokenEuroPrice(['token' => 'BTC']);

        $tokenPrice = $tokenPriceRepo->getTokenEuroPrice($btcTokenObj);

        if (!$tokenPrice) {
            $btcPrice = self::getBitcoinPriceWithPeer();
            $btcTokenObj = new TokenEuroPrice([
                'token' => 'BTC',
                'europrice' => $btcPrice
            ]);
            $tokenPrice = $tokenPriceRepo->saveTokenEuroPrice($btcTokenObj);
        } elseif (strtotime($tokenPrice->getUpdatedat()) <= time() - 5) {
            $btcPrice = self::getBitcoinPriceWithPeer();
            if ($btcPrice) {
                $btcTokenObj = new TokenEuroPrice([
                    'token' => 'BTC',
                    'europrice' => $btcPrice
                ]);
                $tokenPrice = $tokenPriceRepo->updateTokenEuroPrice($btcTokenObj);
            } else {
                $btcPrice = $tokenPrice->getEuroPrice();
            }
        } else {
            $btcPrice = $tokenPrice->getEuroPrice();
        }

        return $btcPrice;
    }
    /**
     * Fetches the current Bitcoin price in EUR from the CoinGecko API.
     *
     * @return float|NULL Returns the price in EUR, or NULL if the request fails.
     */
    public static function getBitcoinPriceWithPeer(): string
    {
        $url = "https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=eur";

        // Initialize cURL session
        $ch = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string

        // Execute the HTTP request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            error_log("Fehler beim Abrufen des Bitcoin-Kurses: " . curl_error($ch));
            curl_close($ch);
            return '0';
        }

        // Get HTTP status code
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check if the request was successful
        if ($httpStatus !== 200) {
            error_log("HTTP-Fehler beim Abrufen des Bitcoin-Kurses: $httpStatus");
            return '0';
        }

        // Decode the JSON response into an associative array
        $data = json_decode($response, true);

        // Validate and return the price if it exists

        if (isset($data['bitcoin']['eur']) && is_numeric($data['bitcoin']['eur'])) {
            $btcPrice = (float) $data['bitcoin']['eur'];
            return $btcPrice;
        }

        // Log unexpected format
        error_log("Unerwartetes Antwortformat vom CoinGecko API.");
        return '0';
    }
}
