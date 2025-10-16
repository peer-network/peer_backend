<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/config/constants/ResponseCodes.php';

use Fawaz\config\constants\ResponseCodes;

$codes = ResponseCodes::$codes;
ksort($codes);

$cases = [];
$arms = [];
foreach ($codes as $code => $entry) {
    if (!is_int($code)) {
        continue;
    }
    $name = $entry['name'] ?? ('CODE_' . $code);
    $name = preg_replace('/[^A-Z0-9_]/', '_', strtoupper($name));

    $cases[] = "    case {$name} = {$code};";

    $comment = isset($entry['comment']) && is_string($entry['comment']) ? $entry['comment'] : '';
    $ufc = isset($entry['userFriendlyComment']) && is_string($entry['userFriendlyComment']) ? $entry['userFriendlyComment'] : '';

    $comment = str_replace(["\\", "'"], ["\\\\", "\\'"], $comment);
    $ufc = str_replace(["\\", "'"], ["\\\\", "\\'"], $ufc);

    $arms[] = "            self::{$name} => [\n" .
              "                'comment' => '{$comment}',\n" .
              "                'userFriendlyComment' => '{$ufc}',\n" .
              "                'name' => '{$name}',\n" .
              "            ],";
}

$casesStr = implode("\n", $cases);
$armsStr = implode("\n", $arms);

$enum = <<<'PHP'
<?php

declare(strict_types=1);

namespace Fawaz\config\constants;

enum ResponseCodesEnum: int
{
PHP;

$enum .= "\n{$casesStr}\n\n";

$enum .= <<<'PHP'
    /**
     * Centralized response catalog from ResponseCodes::$codes
     *
     * @return array<int, array{comment: string, userFriendlyComment: string, name?: string}>
     */
    public static function all(): array
    {
        return ResponseCodes::$codes;
    }

    /**
     * Accessor for per-case metadata from the centralized catalog.
     *
     * @return array{comment: string, userFriendlyComment: string, name: string}
     */
    public function meta(): array
    {
        return match ($this) {
PHP;

$enum .= "\n{$armsStr}\n";

$enum .= <<<'PHP'
        };
    }
}
PHP;

file_put_contents(__DIR__ . '/../src/config/constants/ResponseCodesEnum.php', $enum);
echo "Updated ResponseCodesEnum.php with " . count($codes) . " cases\n";

