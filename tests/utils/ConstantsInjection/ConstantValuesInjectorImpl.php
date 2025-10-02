<?php
declare(strict_types=1);

namespace Tests\utils\ConstantsInjection;

use Exception;
use Fawaz\config\constants\ConstantsConfig;

require __DIR__ . '../../../../vendor/autoload.php';

class ConstantValuesInjectorImpl implements ConstantValuesInjector
{
    private array $constants;

    public function __construct()
    {
        $constantsObject = new ConstantsConfig();
        $this->constants = $constantsObject->getData();
    }

    /**
     * Recursively injects constants into all string fields of any array/object.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
    */
    public function injectConstants(array $data): array {
        echo("ConfigGeneration: ConstantValuesInjectorImpl: injectConstants: start \n");

        return $this->processValue($data);
    }

    private function processValue($value) {
        if (is_string($value)) {
            return $this->replacePlaceholders($value);
        }

        if (is_array($value)) {
            $processed = [];
            foreach ($value as $key => $subValue) {
                $processed[$key] = $this->processValue($subValue);
            }
            return $processed;
        }

        if (is_object($value)) {
            foreach ($value as $field => $subValue) {
                $value->$field = $this->processValue($subValue);
            }
            return $value;
        }

        // Non-string scalars (int, float, bool, null) stay untouched
        return $value;
    }

    private function replacePlaceholders(string $text): string
    {
        return preg_replace_callback(
            '/\{([A-Z0-9_.]+)\}/',
            function ($matches) {
                $path = explode('.', $matches[1]);
                return $this->getValueFromPath($this->constants, $path);
            }, 
            $text
        );
    }

    private function getValueFromPath(array $constants, array $path): string|int
    {
        if (empty($constants) || empty($path)) {
            throw new Exception("Error: ConstantValuesInjectorImpl: getValueFromPath: invalid input arguments ");
        }
        foreach ($path as $key) {
            if (!is_array($constants) || !array_key_exists($key, $constants)) {
                throw new Exception("Error: ConstantValuesInjectorImpl: getValueFromPath: invalid CONSTANTS: " . implode(",",$constants));
            }
            $constants = $constants[$key];
        }


        if (is_array($constants)) {
            throw new Exception(
                "Error: ConstantValuesInjectorImpl: getValueFromPath: invalid path or contants: constant value is not found by path:" . implode(" ",$path) . ", Faulty result: " . implode(",",$constants)
            );
        }
        return $constants;
    }

    public static function injectSchemaPlaceholders(array $schemaFiles, ?string $suffix = '.generated.graphql'): array
    {
        $constants = (new ConstantsConfig())->getData();
        $map = self::flattenConstantsMap($constants);

        $report = [];
        foreach ($schemaFiles as $in) {
            if (!is_file($in)) {
                continue;
            }

            $sdl = file_get_contents($in);
            if ($sdl === false) {
                throw new \RuntimeException("Cannot read schema: {$in}");
            }

            $patched = preg_replace_callback(
                '/\{([A-Z0-9_.]+)\}/',
                static fn(array $m) => $map[$m[1]] ?? $m[0],
                $sdl
            );

            $out = $suffix ? ($in . $suffix) : $in;
            if (file_put_contents($out, $patched) === false) {
                throw new \RuntimeException("Cannot write schema: {$out}");
            }

            $report[$in] = $out;
        }

        return $report;
    }

    private static function flattenConstantsMap(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string)$k : $prefix . '.' . (string)$k;

            if (is_array($v)) {
                $out += self::flattenConstantsMap($v, $key);
                continue;
            }

            if (is_bool($v)) {
                $out[$key] = $v ? 'true' : 'false';
            } else {
                $out[$key] = addcslashes((string)$v, "\\\"");
            }
        }
        return $out;
    }
}
