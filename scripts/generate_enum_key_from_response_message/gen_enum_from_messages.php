<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/config/constants/ResponseMessages.php';

use Fawaz\config\constants\ResponseMessages;

$messages = ResponseMessages::$messages;
ksort($messages);

function slugify_name(string $text): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    // Remove common prefixes
    $text = preg_replace('/^(Success:|Failure:|Invalid Action:|Unexpected Error:|Information:|Info:|Warning:|Error:)/i', '', $text);
    // Normalize quotes and punctuation to spaces
    $text = str_replace(["\n", "\r", "\t"], ' ', $text);
    $text = preg_replace("/[^\p{L}\p{N}]+/u", ' ', $text); // non-alnum -> space
    $text = trim($text);

    // Tokenize and lightly filter only the most common filler words
    $words = preg_split('/\s+/', $text) ?: [];
    $stop = [
        'a','an','the','this','that','these','those','your','you','we','our','please','to','of','for','on','in','and','or','is','are','be','been','with','by','at','as','from',
        'successfully','success','unexpected','error','failure','invalid','information','info'
    ];
    $filtered = [];
    foreach ($words as $w) {
        $lw = mb_strtolower($w);
        if (in_array($lw, $stop, true)) {
            continue;
        }
        $filtered[] = $w;
    }
    if (!$filtered) {
        $filtered = $words;
    }
    // Slightly shorten: keep up to 6 significant tokens
    $filtered = array_slice($filtered, 0, 6);
    $slug = strtoupper(implode('_', $filtered));
    // Ensure starts with a letter
    if (!preg_match('/^[A-Z]/', $slug)) {
        $slug = 'NAME_' . $slug;
    }
    // Remove any remaining non [A-Z0-9_]
    $slug = preg_replace('/[^A-Z0-9_]/', '', $slug);
    // Collapse multiple underscores
    $slug = preg_replace('/_+/', '_', $slug);
    return $slug;
}

$cases = [];
$seen = [];
foreach ($messages as $code => $entry) {
    if (!is_int($code)) {
        continue;
    }
    $base = '';
    // Prefer the internal 'comment' message for naming
    if (isset($entry['comment']) && is_string($entry['comment'])) {
        $base = slugify_name($entry['comment']);
    }
    // Fallback to user-friendly comment if needed
    if ($base === '' && isset($entry['userFriendlyComment']) && is_string($entry['userFriendlyComment'])) {
        $base = slugify_name($entry['userFriendlyComment']);
    }
    if ($base === '') {
        $base = 'CODE_' . $code;
    }
    $name = $base;
    // Ensure uniqueness
    if (isset($seen[$name])) {
        $name = $base . '_' . $code;
    }
    $seen[$name] = true;
    $cases[] = "    case {$name} = {$code};";
}

$casesStr = implode("\n", $cases);

$enum = <<<'PHP'
<?php

declare(strict_types=1);

namespace Fawaz\config\constants;

enum ResponseCodesEnum: int
{
PHP;

$enum .= "\n{$casesStr}\n";

$enum .= <<<'PHP'
}
PHP;

file_put_contents(__DIR__ . '/../src/config/constants/ResponseCodesEnum.php', $enum);
echo "Updated ResponseCodesEnum.php with " . count($cases) . " cases from ResponseMessages\n";
