<?php
declare(strict_types=1);

namespace Tests\Utils\ConfigGeneration;

require __DIR__ . '../../../../vendor/autoload.php';

class JSONHandler {
    private static function generateJson(array $data, string $name, bool $addHash): string {
        echo("ConfigGeneration: JSONHandler: generating JSON: " . $name . "\n");

        $jsonObj = [];
        $jsonObj['createdAt'] = time();

        if ($addHash == true) {
            $jsonStringForHash = json_encode($data, JSON_UNESCAPED_UNICODE);
            $hash = hash('sha256', $jsonStringForHash);
            $jsonObj['hash'] = $hash;
        }
        $jsonObj['name'] = $name;
        $jsonObj['data'] = $data;
        $jsonString = json_encode($jsonObj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (!$jsonString) {
            throw new \Exception("JSONHandler: generateJson: Failed encode JSON");
        }
        return $jsonString;
    }

    public static function generateJSONtoFile(string $outputPath,array $data,string $name, bool $addHash = true): void {
        $jsonString = JSONHandler::generateJson($data,$name,$addHash);
        if (file_put_contents($outputPath, $jsonString) === false) {
            throw new \Exception("Failed to write JSON to file: $outputPath");
        }
    }

    public static function parseInputJson(string $filePath, bool $validateKeyUniqness = false): mixed {
        echo("ConfigGeneration: JSONHandler: parseInputJson: " . $filePath. "\n");

        if (!file_exists($filePath)) {
            throw new \Exception("Response Code File is not found: $filePath");
        }
        
        $jsonContent = file_get_contents($filePath);
        if (!$jsonContent) {
            throw new \Exception("Error: " . $filePath . ": duplication: " . $duplications[0]);
        }
        if ($validateKeyUniqness == true) {
            $duplications = JSONHandler::getDuplicatedNumericKeys($jsonContent);
            if ($duplications) {
                throw new \Exception("Error: Duplicated Keys: $duplications");
            }
        }
        
        if (!empty($duplications)) {
            throw new \Exception("Error: " . $filePath . ": duplication: " . $duplications[0]);
        }

        $decoded = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON decode error: " . json_last_error_msg());
        }
        return $decoded;
    }

    private static function getDuplicatedNumericKeys(string $json): string | false {
        echo("ConfigGeneration: JSONHandler: getDuplicatedNumericKeys start". "\n");

        preg_match_all('/"([^"]+)"\s*:/', $json, $matches);

        $keys = $matches[1];
        $numericKeys = array_filter($keys, fn($key) => is_numeric($key));

        $counts = array_count_values($numericKeys);
        $duplicatedKeys = array_keys(array_filter($counts, fn($count) => $count > 1));
        if ($duplicatedKeys) {
            return implode(',', $duplicatedKeys);
        } else {
            return false;
        }
    }
}
