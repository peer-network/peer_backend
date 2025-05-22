<?php
declare(strict_types=1);

namespace Fawaz\Utils\ConfigGeneration;
use Fawaz\Utils\ConfigGeneration\ResponseCodesStore;

require __DIR__ . '../../../../vendor/autoload.php';

try {
    $store = new ResponseCodesStore(Constants::$pathResponseCodesFileForEditing . Constants::$inputResponseCodesFileName);
    $store->generateJSONtoFile(Constants::$pathResponseCodesFileToAssets . Constants::$outputResponseCodesFileName);
} catch (\Exception $e) {
    echo $e->getMessage();
}