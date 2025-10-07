<?php
declare(strict_types=1);

namespace Tests\utils\ConfigGeneration;

require __DIR__ . '../../../../vendor/autoload.php';

use Tests\utils\ConfigGeneration\JSONHandler;
use Tests\utils\ConfigGeneration\ConfigUrl;
use Tests\utils\ConfigGeneration\ConfigGenerationConstants;
use Tests\utils\ConstantsInjection\ConstantValuesInjectorImpl;

try {
    $files = ConfigGenerationConstants::cases();

    foreach($files as $file) {
        JSONHandler::generateJSONtoFile(Constants::$pathToAssets . $file->outputFileName(), $file->getData(), $file->getName());
    }

    $pathsConfig = new ConfigUrl();
    JSONHandler::generateJSONtoFile(Constants::$pathToAssets . "config.json", $pathsConfig->getData(), "config", false);

    $suffix = '.generated';

    $schemaFiles = [
        __DIR__ . '/../../../src/Graphql/schema/admin_schema.graphql',
        __DIR__ . '/../../../src/Graphql/schema/bridge_schema.graphql',
        __DIR__ . '/../../../src/Graphql/schema/schema.graphql',
        __DIR__ . '/../../../src/Graphql/schema/schemaguest.graphql',
        __DIR__ . '/../../../src/Graphql/schema/types/enums.graphql',
        __DIR__ . '/../../../src/Graphql/schema/types/inputs.graphql',
        __DIR__ . '/../../../src/Graphql/schema/types/response.graphql',
        __DIR__ . '/../../../src/Graphql/schema/types/scalars.graphql',
        __DIR__ . '/../../../src/Graphql/schema/types/types.graphql',
    ];

    if (!empty($schemaFiles)) {
        $report = ConstantValuesInjectorImpl::injectSchemaPlaceholders(
            $schemaFiles,
            $suffix
        );
        foreach ($report as $in => $out) {
            echo "Schema injected: {$in} -> {$out}\n";
        }
    }
    echo("ConfigGeneration: Done! \n");
} catch (\Exception $e) {
    echo "ConfigGeneration: Error: " . $e->getMessage();
}