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
        $injector = new ConstantValuesInjectorImpl;
        

        foreach ($schemaFiles as $in) {
            if (!is_file($in)) {
                continue;
            }

            $sdl = file_get_contents($in);

            if ($sdl === false) {
                throw new \RuntimeException("Cannot read schema: {$in}");
            }

            $patched = $injector->injectConstants($sdl);

            preg_replace_callback(
                '/"""(.*?)"""/s',
                static function (array $m) use (&$errors) {
                    $inner = $m[1];
                    // Optional: validate no empty placeholders {}
                    if (preg_match('/\{([A-Z0-9_.]+)\}/',, $inner)) {
                        $errors[] = "Empty placeholder found in block: " . substr($inner, 0, 50) . '...';
                    }

                    return $m[0]; // no changes to the original string
                },
                $patched
            );

            if ($errors) {
                echo "Validation errors found:\n";
                foreach ($errors as $err) {
                    echo "- $err\n";
                }
                throw new \RuntimeException("Unresolved placeholders");
            } 

            $out = $in . ".generated";
            if (file_put_contents($out, $patched) === false) {
                throw new \RuntimeException("Cannot write schema: {$out}");
            }
        }
    }
    echo("ConfigGeneration: Done! \n");
} catch (\Exception $e) {
    echo "ConfigGeneration: Error: " . $e->getMessage();
}