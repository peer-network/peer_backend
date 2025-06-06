<?php

namespace Fawaz\Services;

class BtcService
{
    /**
     * Fetches the current Bitcoin price in EUR from the CoinGecko API.
     *
     * @return float|NULL Returns the price in EUR, or NULL if the request fails.
     */
    public static function getBitcoinPriceWithPeer(): ?float
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
            return NULL;
        }

        // Get HTTP status code
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check if the request was successful
        if ($httpStatus !== 200) {
            error_log("HTTP-Fehler beim Abrufen des Bitcoin-Kurses: $httpStatus");
            return NULL;
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
        return NULL;
    }
}
