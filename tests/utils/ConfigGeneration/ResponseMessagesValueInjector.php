<?php
declare(strict_types=1);

namespace Tests\utils\ConfigGeneration;

use Exception;
use Fawaz\config\constants\ConstantsConfig;
use Tests\utils\ConfigGeneration\MessageEntry;

use function PHPUnit\Framework\isArray;
use function PHPUnit\Framework\isNumeric;
use function PHPUnit\Framework\isString;

require __DIR__ . '../../../../vendor/autoload.php';

class ResponseMessagesValueInjector
{
    private array $constants;

    public function __construct()
    {
        $constantsObject = new ConstantsConfig();
        $this->constants = $constantsObject->getData();
    }

    /**
    **  @param array<string, MessageEntry> 
    **/
    public function injectConstants(array $data): array
    {
        echo("ConfigGeneration: ResponseMessagesValueInjector: injectConstants: start \n");
            
        /** @var array<string, MessageEntry> */
        $injectedData = [];

        foreach ($data as $code => $entry) {
            $injectedData[$code] = $data[$code];
            if (empty($entry->comment) || empty($entry->userFriendlyComment)) {
                throw new Exception("Error: ResponseMessagesValueInjector: Empty Message found for code " . $code);
            }

            $injectedData[$code]->userFriendlyComment = $this->replacePlaceholders($data[$code]->userFriendlyComment);
            $injectedData[$code]->comment = $this->replacePlaceholders($data[$code]->comment);
        }

        return $injectedData;
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
            throw new Exception("Error: ResponseMessagesValueInjector: getValueFromPath: invalid input arguments ");
        }
        foreach ($path as $key) {
            if (!is_array($constants) || !array_key_exists($key, $constants)) {
                throw new Exception("Error: ResponseMessagesValueInjector: getValueFromPath: invalid CONSTANTS: " . implode(",",$constants));
            }
            $constants = $constants[$key];
        }


        if (is_array($constants)) {
            throw new Exception("Error: ResponseMessagesValueInjector: getValueFromPath: invalid path or contants: constant value is not found by path:" . implode(" ",$path) . ", Faulty result: " . implode(",",$constants));
        }
        return $constants;
    }
}
