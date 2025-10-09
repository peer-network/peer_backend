<?php

declare(strict_types=1);

namespace Tests\utils\ConfigGeneration;

require __DIR__ . '../../../../vendor/autoload.php';

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\utils\ConfigGeneration\JSONHandler;
use Tests\utils\ConfigGeneration\ConfigUrl;
use Tests\utils\ConfigGeneration\ConfigGenerationConstants;
use Tests\utils\ConstantsInjection\ConstantValuesInjectorImpl;

try {
    $files = ConfigGenerationConstants::cases();

    foreach ($files as $file) {
        JSONHandler::generateJSONtoFile(Constants::$pathToAssets . $file->outputFileName(), $file->getData(), $file->getName());
    }

    $pathsConfig = new ConfigUrl();
    JSONHandler::generateJSONtoFile(Constants::$pathToAssets . "config.json", $pathsConfig->getData(), "config", false);

    // generateSchema();
    echo("ConfigGeneration: Done! \n");
    exit(0);
} catch (\Exception $e) {
    echo "ConfigGeneration: Error: " . $e->getMessage();
    exit(1);
}

function generateSchema()
{
    $schemaDir = __DIR__ . '/../../../src/Graphql/schema/';

    // Recursively find all `.graphql` files in the directory and its subfolders
    $schemaFiles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($schemaDir)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'graphql') {
            $schemaFiles[] = $file->getRealPath();
        }
    }
    if (!empty($schemaFiles)) {
        $injector = new ConstantValuesInjectorImpl();


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
                    if (preg_match('/\{([A-Z0-9_.]+)\}/', $inner)) {
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
}
