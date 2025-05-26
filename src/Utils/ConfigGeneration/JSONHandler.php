<?php
declare(strict_types=1);

namespace Fawaz\Utils\ConfigGeneration;

require __DIR__ . '../../../../vendor/autoload.php';

class JSONHandler {
    private static function generateJson(array $data, string $name)
    {
        $jsonObj['createdAt'] = time();
        $jsonObj['name'] = $name;
        $jsonObj['data'] = $data;
        return json_encode($jsonObj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public static function generateJSONtoFile(string $outputPath,array $data,string $name) {
        $jsonString = JSONHandler::generateJson($data,$name);
        if (file_put_contents($outputPath, $jsonString) === false) {
            throw new \Exception("Failed to write JSON to file: $outputPath");
        }
    }

    public static function parseInputJson(string $filePath, bool $validateKeyUniqness = false): array {
        if (!file_exists($filePath)) {
            throw new \Exception("Response Code File is not found: $filePath");
        }
        
        $jsonContent = file_get_contents($filePath);
        if ($validateKeyUniqness == true) {
            $duplications = JSONHandler::findDuplicateJsonKeys($jsonContent);
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

    private static function findDuplicateJsonKeys(string $json): array {
        $pattern = '/\"(\d{5})\"\:/';
        if (preg_match($pattern, $json, $matches)) {
            $keys = $matches[1];

            $seen = [];
            $duplicates = [];

            foreach ($keys as $key) {
                echo $key;
                if (isset($seen[$key])) {
                    $duplicates[] = $key;
                } else {
                    $seen[$key] = true;
                }
            }

            return array_unique($duplicates);
        }

    return [];
    }
}
