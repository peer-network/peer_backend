<?php
declare(strict_types=1);

namespace Fawaz\Utils\ConfigGeneration;
use Fawaz\Utils\ConfigGeneration\ResponseCodesStore;
use Fawaz\Utils\ConfigGeneration\JSONHandler;

require __DIR__ . '../../../../vendor/autoload.php';

try {
    $responsCodes = new ResponseCodesStore(Constants::$pathResponseCodesFileForEditing . Constants::$inputResponseCodesFileName);
    JSONHandler::generateJSONtoFile(Constants::$pathResponseCodesFileToAssets . Constants::$outputResponseCodesFileName, $responsCodes->getData(), "Response Codes List");
} catch (\Exception $e) {
    echo $e->getMessage();
}