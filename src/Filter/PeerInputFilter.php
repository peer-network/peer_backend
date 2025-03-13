<?php
declare(strict_types=1);

namespace Fawaz\Filter;

use Exception;
use DateTime;
use function trim;
use function strip_tags;
use function htmlspecialchars;
use function addslashes;
use function htmlentities;
use function preg_match;
use function ctype_digit;
use function strlen;
use function get_debug_type;
use function filter_var;
use function in_array;
use function is_array;
use function is_callable;
use function is_numeric;
use function is_int;
use function is_object;
use function is_string;
use function sprintf;
use function method_exists;

class ValidationException extends Exception {}

const CUSTOM_FILTER_FLAG_ALLOW_INT = 1;
const CUSTOM_FILTER_FLAG_ALLOW_ZERO = 2;
const CUSTOM_FILTER_FLAG_ALLOW_STR = 4;

class PeerInputFilter
{
    protected array $specification;
    protected array $data = [];
    protected array $errors = [];

    public function __construct(array $specification)
    {
        $this->specification = $specification;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function isValid(): bool
    {
        $this->errors = [];

        foreach ($this->specification as $field => $rules) {

            //echo "Validating field: $field\n";

            if (!isset($this->data[$field]) && empty($rules['required'])) {
                //$this->errors[$field][] = "$field is required";
                continue;
            }

            if (isset($this->data[$field]) && empty($this->data[$field]) && empty($rules['required'])) {
                //$this->errors[$field][] = "$field is empty";
                continue;
            }

            if (!isset($this->data[$field]) && !empty($rules['required'])) {
                $this->errors[$field][] = "$field is required";
                continue;
            }

            if (isset($this->data[$field])) {
                foreach ($rules['filters'] ?? [] as $filter) {
                    $filterName = $filter['name'];
                    $options = $filter['options'] ?? [];
                    if (method_exists($this, $filterName)) {
                        $this->data[$field] = $this->$filterName($this->data[$field], $options);
                    } else {
                        //$this->errors['filterName'][] = "Filter method $filterName does not exist.";
                        throw new ValidationException("Filter method $filterName does not exist.");
                    }
                }

                foreach ($rules['validators'] ?? [] as $validator) {
                    $validatorName = $validator['name'];
                    $options = $validator['options'] ?? [];
                    if (method_exists($this, $validatorName)) {
                        if (!$this->$validatorName($this->data[$field], $options)) {
                            $this->errors[$field][] = "$field failed validation for $validatorName";
                            if (!empty($validator['break_chain_on_failure'])) {
                                break;
                            }
                        }
                    } else {
                        //$this->errors['validatorName'][] = "Validator method $validatorName does not exist.";
                        throw new ValidationException("Validator method $validatorName does not exist.");
                    }
                }
            }
        }

        //echo "Validation errors: " . json_encode($this->errors) . "\n";

        return empty($this->errors);
    }

    public function getValues(): array
    {
        return $this->data;
    }

    public function getMessages(): array
    {
        return $this->errors;
    }

    // Filters

    protected function StringTrim(string $value, array $options = []): string
    {
        return trim($value);
    }

    protected function StripTags(string $value, array $options = []): string
    {
        return strip_tags($value);
    }

    protected function Boolean(mixed $value, array $options = []): bool
    {
        $filterOptions = [
            'flags' => FILTER_NULL_ON_FAILURE
        ];

        if (isset($options['type'])) {
            $filterOptions['flags'] |= $this->getBooleanFlags($options['type']);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, $filterOptions);
    }

    protected function EscapeHtml(string $value, array $options = []): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    protected function SqlSanitize(string $value, array $options = []): string
    {
        return addslashes($value);
    }

    protected function HtmlEntities(string $value, array $options = []): string
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8');
    }

    protected function ToInt(mixed $value, array $options = []): int
    {
        return (int)$value;
    }

    protected function ToFloat(mixed $value, array $options = []): float
    {
        return (float)$value;
    }

    protected function FloatSanitize(mixed $value, array $options = []): float
    {
        if (!is_numeric($value)) {
            $this->errors['value'][] = "Value is not a valid float.";
        }

        return (float)$value;
    }

    // Validators

    protected function Uuid(mixed $value, array $options = []): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $value) === 1;
    }

    protected function Date(string $value, array $options = []): bool
    {
        $format = $options['format'] ?? 'Y-m-d H:i:s.u';

        if (preg_match('/\.\d+$/', $value, $matches)) {
            $microseconds = $matches[0];
            if (strlen($microseconds) < 7) {
                $value = str_replace($microseconds, str_pad($microseconds, 7, '0'), $value);
            }
        }

        $dateTime = \DateTime::createFromFormat($format, $value);

        if ($dateTime) {
            $formatted = $dateTime->format($format);
            //error_log("Formatted value: $formatted");

            $formatted = preg_replace_callback('/\.(\d{1,6})(0*)$/', function ($matches) {
                return '.' . str_pad($matches[1], 6, '0');
            }, $formatted);
            //error_log("Final formatted value after trimming: $formatted");

            $value = trim($value);
            $formatted = trim($formatted);

            if ($formatted === $value) {
                return true;
            }
        }

        $this->errors['Date'][] = "Invalid date format. Expected format: $format. Received: $value";
        return false;
    }

    protected function LessThan(string $value, array $options = []): bool
    {
        $max = $options['max'] ?? null;
        $inclusive = $options['inclusive'] ?? false;

        if ($max === null) {
            $this->errors['Max'][] = "The 'max' option is required for the LessThan validator.";
            return false;
        }

        $valueDateTime = DateTime::createFromFormat('Y-m-d H:i:s.u', $value);
        $maxDateTime = DateTime::createFromFormat('Y-m-d H:i:s.u', $max);

        if ($valueDateTime === false) {
            $this->errors['valueDateTime'][] = "Invalid date format (with milliseconds) for 'value' in LessThan validator.";
            return false;
        }

        if ($maxDateTime === false) {
            $this->errors['maxDateTime'][] = "Invalid date format (with milliseconds) for 'max' in LessThan validator.";
            return false;
        }

        $valueTimestamp = (float)$valueDateTime->format('U.u');
        $maxTimestamp = (float)$maxDateTime->format('U.u');

        return $inclusive ? $valueTimestamp <= $maxTimestamp : $valueTimestamp < $maxTimestamp;
    }

    protected function validateIntRange(mixed $value, array $options = []): bool
    {
        if (!is_numeric($value) || (int)$value != $value) {
            $this->errors['int_range'][] = 'Value must be an integer.';
            return false;
        }

        $value = (int)$value;
        $min = $options['min'] ?? PHP_INT_MIN;
        $max = $options['max'] ?? PHP_INT_MAX;

        if ($value < $min) {
            $this->errors['int_range'][] = "Value must be at least $min.";
            return false;
        }

        if ($value > $max) {
            $this->errors['int_range'][] = "Value must be at most $max.";
            return false;
        }

        return true;
    }

    protected function EmailAddress(string $value, array $options = []): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function Digits(string $value, array $options = []): bool
    {
        return ctype_digit($value);
    }

    protected function StringLength(string $value, array $options = []): bool
    {
        $min = $options['min'] ?? 0;
        $max = $options['max'] ?? PHP_INT_MAX;
        $length = strlen($value);
        return $length >= $min && $length <= $max;
    }

    protected function ArrayValues(mixed $value, array $options = []): bool
    {
        if (!is_array($value)) {
            return false;
        }

        $validator = $options['validator'] ?? null;
        if (!$validator || !isset($validator['name'])) {
            //throw new ValidationException("ArrayValues validator requires a sub-validator.");
            $this->errors['ArrayValues'][] = "ArrayValues validator requires a sub-validator.";
        }

        $validatorName = $validator['name'];
        $validatorOptions = $validator['options'] ?? [];

        foreach ($value as $item) {
            if (!method_exists($this, $validatorName)) {
                //throw new ValidationException("Validator method $validatorName does not exist.");
                $this->errors['ArrayValues'][] = "Validator method $validatorName does not exist.";
            }

            if (!$this->$validatorName($item, $validatorOptions)) {
                return false;
            }
        }

        return true;
    }

    protected function ArrayElementStringLength(array $value, array $options = []): bool
    {
        $min = $options['min'] ?? 0;
        $max = $options['max'] ?? PHP_INT_MAX;

        foreach ($value as $item) {
            if (!is_string($item) || strlen($item) < $min || strlen($item) > $max) {
                return false;
            }
        }

        return true;
    }

    protected function InArray(mixed $value, array $options = []): bool
    {
        $haystack = $options['haystack'] ?? [];
        return in_array($value, $haystack, true);
    }

    protected function IsArray(mixed $value, array $options = []): bool
    {
        return is_array($value);
    }

    protected function ValidateObjectProperties(object $value, array $options = []): bool
    {
        $requiredProperties = $options['required_properties'] ?? [];
        foreach ($requiredProperties as $property) {
            if (!property_exists($value, $property)) {
                return false;
            }
        }

        return true;
    }

    protected function IsObject(mixed $value, array $options = []): bool
    {
        return is_object($value);
    }

    protected function IsInt(mixed $value, array $options = []): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function IsNumeric(mixed $value, array $options = []): bool
    {
        return is_numeric($value);
    }

    protected function IsFloat(mixed $value, array $options = []): bool
    {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    protected function IsString(mixed $value, array $options = []): bool
    {
        return is_string($value);
    }

    protected function ValidateFloat(mixed $value, array $options = []): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $min = $options['min'] ?? -INF;
        $max = $options['max'] ?? INF;

        return $value >= $min && $value <= $max;
    }

    protected function IsIp(string $value, array $options = []): bool
    {
        $flags = 0;
        if (!empty($options['ipv4'])) {
            $flags |= FILTER_FLAG_IPV4;
        }
        if (!empty($options['ipv6'])) {
            $flags |= FILTER_FLAG_IPV6;
        }
        return filter_var($value, FILTER_VALIDATE_IP, $flags) !== false;
    }

    protected function isImage(string $value, array $options = []): bool
    {
        $allowedExtensions = $options['extensions'] ?? ['jpg', 'jpeg', 'png', 'gif', 'heic', 'heif', 'tiff', 'webp'];
        $allowedMimeTypes = $options['mime_types'] ?? ['image/jpeg', 'image/png', 'image/gif', 'image/heic', 'image/heif', 'image/tiff', 'image/webp'];

        $extension = pathinfo($value, PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), $allowedExtensions)) {
            return false;
        }

        if (file_exists($value)) {
            $mimeType = mime_content_type($value);
            if (!in_array($mimeType, $allowedMimeTypes)) {
                return false;
            }
        }

        return true;
    }

    private function getSearchStrings(string $contentType): array
    {
        return match ($contentType) {
            'image' => ['data:image/webp;base64,', 'data:image/jpeg;base64,', 'data:image/png;base64,', 'data:image/gif;base64,', 'data:image/heic;base64,', 'data:image/heif;base64,', 'data:image/tiff;base64,'],
            'video' => ['data:video/mp4;base64,', 'data:video/ogg;base64,', 'data:video/quicktime;base64,'],
            'audio' => ['data:audio/mpeg;base64,', 'data:audio/wav;base64,'],
            'text' => ['data:text/plain;base64,'],
            default => []
        };
    }

    protected function sanitizeBase64Input(string $input): string {
        // Remove everything before "data:"
        if (preg_match('/data:[a-zA-Z0-9+.-]+\/[a-zA-Z0-9+.-]+;base64,[A-Za-z0-9+\/=]+/', $input, $matches)) {
            return $matches[0]; // Return only the matched Base64 string
        }
        return $input; // Return as is if "data:" is not found
    }

    protected function isValidBase64Media(string $base64File, array $options = []): bool
    {
        $allowedExtensions = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'tiff'],
            'video' => ['mp4', 'ogg', 'mov'],
            'audio' => ['mp3', 'wav'],
            'text'  => ['txt'],
        ];

        $allowedMimeTypes = [
            'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif', 'image/tiff'],
            'video' => ['video/mp4', 'video/ogg', 'video/quicktime'],
            'audio' => ['audio/mpeg', 'audio/wav'],
            'text'  => ['text/plain'],
        ];

        $maxFileSize = $options['max_size'] ?? 10 * 1024 * 1024; // 10MB default
        $base64File = $this->sanitizeBase64Input($base64File);

        // 🔍 Log the raw input for debugging
        //error_log("Raw Input (first 100 chars): " . substr($base64File, 0, 100));

        // 🎯 Regex to extract MIME type & Base64 content
        $pattern = '#^data:(?<type>image|video|audio|text)/(?<extension>\w+);base64,(?<content>[A-Za-z0-9+/=\r\n]+)#i';

        if (!preg_match($pattern, $base64File, $matches)) {
            //error_log("❌ Regex did NOT match. Input may be incorrectly formatted.");
            $this->errors['unknown'][] = 'No valid Base64 media found in the input.';
            return false;
        }

        // 🏷 Extract details from regex match
        $mediaType = strtolower($matches['type']);
        $extension = strtolower($matches['extension']);
        $base64String = $matches['content'];

        // 🔄 Normalize extracted extension
        if ($mediaType === 'audio' && $extension === 'mpeg') {
            $extension = 'mp3';
        }
        if ($mediaType === 'text' && $extension === 'plain') {
            $extension = 'txt'; // ✅ Convert "plain" to "txt"
        }

        //error_log("✅ Regex matched. Extracted type: $mediaType | Normalized extension: $extension");

        // 🚨 Validate extension
        if (!in_array($extension, $allowedExtensions[$mediaType])) {
            //error_log("❌ Invalid extension: $extension. Allowed: " . implode(', ', $allowedExtensions[$mediaType]));
            $this->errors[$mediaType][] = "Invalid $mediaType extension ($extension). Allowed: " . implode(', ', $allowedExtensions[$mediaType]);
            return false;
        }

        // 🛠 Clean Base64 string (remove spaces, newlines)
        $base64String = preg_replace('/\s+/', '', $base64String);
        
        // 🔍 Log sanitized Base64
        //error_log("🛠 Sanitized Base64 (first 100 chars): " . substr($base64String, 0, 100));

        // 📥 Decode Base64 string
        $decodedFile = @base64_decode($base64String, true);

        if ($decodedFile === false) {
            //error_log("❌ Failed to decode Base64 media.");
            $this->errors[$mediaType][] = 'Failed to decode the Base64 media.';
            return false;
        }

        // 📏 Check file size
        if (strlen($decodedFile) > $maxFileSize) {
            //error_log("❌ $mediaType size exceeds max limit: " . ($maxFileSize / 1024 / 1024) . " MB");
            $this->errors[$mediaType][] = ucfirst($mediaType) . " size exceeds the max limit of " . ($maxFileSize / 1024 / 1024) . " MB.";
            return false;
        }

        // 🔍 Detect MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $decodedFile);
        finfo_close($finfo);

        //error_log("🧐 Detected MIME type: $mimeType");

        // 🛡 Validate MIME type
        if (!in_array($mimeType, $allowedMimeTypes[$mediaType])) {
            //error_log("❌ Invalid MIME type: $mimeType. Allowed: " . implode(', ', $allowedMimeTypes[$mediaType]));
            $this->errors[$mediaType][] = "Invalid MIME type ($mimeType). Allowed: " . implode(', ', $allowedMimeTypes[$mediaType]);
            return false;
        }

        error_log("✅ Base64 $mediaType is valid.");
        return true;
    }

    protected function isImage64(string $base64File, array $options = []): bool
    {
        $allowedExtensions = $options['extensions'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'tiff', 'bmp', 'svg'];
        $allowedMimeTypes = $options['mime_types'] ?? ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif', 'image/tiff', 'image/bmp', 'image/svg+xml'];
        $maxFileSize = $options['max_size'] ?? 5 * 1024 * 1024;

        //error_log("Raw Input (first 100 chars): " . substr($base64File, 0, 100));

        // Extract Base64 from an <img> tag if provided
        if (preg_match('/src="([^"]+)"/', $base64File, $match)) {
            $base64File = $match[1];
            //error_log("Extracted Base64 from <img>: " . substr($base64File, 0, 100));
        }

        // Clean up Base64 input
        $base64File = trim($base64File);
        $base64File = str_replace(["\r", "\n", " "], '', $base64File);

        //error_log("Sanitized Base64 (first 100 chars): " . substr($base64File, 0, 100));

        // More flexible regex pattern
        $pattern = '#^data:image/(?<extension>jpeg|jpg|png|gif|webp|heic|heif|tiff|bmp|svg);base64,(?<content>[A-Za-z0-9+/]+={0,2})#i';

        if (!preg_match($pattern, $base64File, $matches)) {
            //error_log("Regex did NOT match. Check formatting.");
            $this->errors['img'][] = 'No valid Base64 image found in the input.';
            return false;
        }

        //error_log("Regex matched. Extracted extension: " . $matches['extension']);

        $extension = strtolower($matches['extension']);
        $base64String = $matches['content'];

        if (!in_array($extension, $allowedExtensions)) {
            $this->errors['img'][] = "Invalid image extension ($extension). Allowed: " . implode(', ', $allowedExtensions);
            return false;
        }

        // Decode Base64 safely
        $decodedImage = @base64_decode($base64String, true);
        if ($decodedImage === false) {
            $this->errors['img'][] = 'Failed to decode the Base64 image.';
            return false;
        }

        // Check file size
        if (strlen($decodedImage) > $maxFileSize) {
            $this->errors['img'][] = 'Image size exceeds ' . ($maxFileSize / 1024 / 1024) . ' MB.';
            return false;
        }

        // Validate MIME type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $decodedImage);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimeTypes)) {
            $this->errors['img'][] = "Invalid MIME type ($mimeType). Allowed: " . implode(', ', $allowedMimeTypes);
            return false;
        }

        error_log("✅ Base64 img is valid.");
        return true;
    }

    private function getBooleanFlags(array $types): int
    {
        $flags = 0;

        foreach ($types as $type) {
            switch ($type) {
                case 'integer':
                    $flags |= CUSTOM_FILTER_FLAG_ALLOW_INT;
                    break;
                case 'zero':
                    $flags |= CUSTOM_FILTER_FLAG_ALLOW_ZERO;
                    break;
                case 'string':
                    $flags |= CUSTOM_FILTER_FLAG_ALLOW_STR;
                    break;
            }
        }

        return $flags;
    }

    protected function ValidateChatMessages(array $chatmessages, array $options = []): bool
    {
        foreach ($chatmessages as $message) {
            if (is_array($message)) {
                $message = (object)$message;
            }

            if (!$this->ValidateChatStructure($message, $options)) {
                return false;
            }
        }

        return true;
    }

    protected function ValidateChatStructure(mixed $chatmessage, array $options = []): bool
    {
        if (is_array($chatmessage)) {
            $chatmessage = (object)$chatmessage;
        }

        return isset($chatmessage->messid) && $this->IsNumeric($chatmessage->messid) &&
               isset($chatmessage->chatid) && $this->Uuid($chatmessage->chatid) &&
               isset($chatmessage->userid) && $this->Uuid($chatmessage->userid) &&
               isset($chatmessage->content) && $this->StringLength($chatmessage->content, ['min' => 1, 'max' => 100]) &&
               isset($chatmessage->createdat) && $this->StringLength($chatmessage->createdat, ['min' => 1]);
    }

    protected function ValidateParticipants(array $participants, array $options = []): bool
    {
        foreach ($participants as $participant) {
            if (is_array($participant)) {
                $participant = (object)$participant;
            }

            if (!$this->ValidateParticipantStructure($participant, $options)) {
                return false;
            }
        }

        return true;
    }

    protected function ValidateParticipantStructure(mixed $participant, array $options = []): bool
    {
        if (is_array($participant)) {
            $participant = (object)$participant;
        }

        return isset($participant->userid) && $this->Uuid($participant->userid) &&
               isset($participant->username) && $this->StringLength($participant->username, ['min' => 3, 'max' => 23]) &&
               isset($participant->img) && $this->StringLength($participant->img, ['min' => 0, 'max' => 100]) &&
               isset($participant->hasaccess) && $this->IsNumeric($participant->hasaccess);
    }

    protected function ValidateUsers(array $users, array $options = []): bool
    {
        foreach ($users as $user) {
            if (is_array($user)) {
                $user = (object)$user;
            }

            if (!$this->ValidateUserStructure($user, $options)) {
                return false;
            }
        }

        return true;
    }

    protected function ValidateUserStructure(mixed $user, array $options = []): bool
    {
        if (is_array($user)) {
            $user = (object)$user;
        }

        return isset($user->uid) && $this->Uuid($user->uid) &&
               isset($user->username) && $this->StringLength($user->username, ['min' => 3, 'max' => 23]) &&
               isset($user->img) && $this->StringLength($user->img, ['min' => 10, 'max' => 100]) &&
               isset($user->isfollowed) && is_bool($user->isfollowed) &&
               isset($user->isfollowing) && is_bool($user->isfollowing);
    }

    protected function ValidateProfilePost(array $profileposts, array $options = []): bool
    {
        foreach ($profileposts as $post) {
            if (is_array($post)) {
                $post = (object)$post;
            }

            if (!$this->ValidatePostStructure($post, $options)) {
                return false;
            }
        }

        return true;
    }

    protected function ValidatePostStructure(mixed $profilepost, array $options = []): bool
    {
        if (is_array($profilepost)) {
            $profilepost = (object)$profilepost;
        }

        return isset($profilepost->postid) && $this->Uuid($profilepost->postid) &&
               isset($profilepost->title) && $this->StringLength($profilepost->title, ['min' => 3, 'max' => 96]) &&
               isset($profilepost->contenttype) && $this->InArray($profilepost->contenttype, ['haystack' => ['image', 'text', 'video', 'audio', 'imagegallery', 'videogallery', 'audiogallery']]) &&
               isset($profilepost->media) && $this->StringLength($profilepost->media, ['min' => 10, 'max' => 100]) &&
               isset($profilepost->createdat) && $this->LessThan($profilepost->createdat, ['max' => \date('Y-m-d H:i:s.u'), 'inclusive' => true]);
    }

    protected function ValidatePostPure(array $profileposts, array $options = []): bool
    {
        foreach ($profileposts as $post) {
            if (is_array($post)) {
                $post = (object)$post;
            }

            if (!$this->ValidatePostPureStructure($post, $options)) {
                return false;
            }
        }

        return true;
    }

    protected function ValidatePostPureStructure(mixed $profilepost, array $options = []): bool
    {
        if (is_array($profilepost)) {
            $profilepost = (object)$profilepost;
        }

        return isset($profilepost->postid) && $this->Uuid($profilepost->postid) &&
               isset($profilepost->userid) && $this->Uuid($profilepost->userid) &&
               isset($profilepost->title) && $this->StringLength($profilepost->title, ['min' => 3, 'max' => 96]) &&
               isset($profilepost->contenttype) && $this->InArray($profilepost->contenttype, ['haystack' => ['image', 'text', 'video', 'audio', 'imagegallery', 'videogallery', 'audiogallery']]) &&
               isset($profilepost->media) && $this->StringLength($profilepost->media, ['min' => 10, 'max' => 100]) &&
               isset($profilepost->mediadescription) && $this->StringLength($profilepost->mediadescription, ['min' => 3, 'max' => 440]) &&
               isset($profilepost->amountlikes) && $this->IsNumeric($profilepost->amountlikes) &&
               isset($profilepost->amountdislikes) && $this->IsNumeric($profilepost->amountdislikes) &&
               isset($profilepost->amountviews) && $this->IsNumeric($profilepost->amountviews) &&
               isset($profilepost->amountcomments) && $this->IsNumeric($profilepost->amountcomments) &&
               isset($profilepost->createdat) && $this->LessThan($profilepost->createdat, ['max' => \date('Y-m-d H:i:s.u'), 'inclusive' => true]);
    }

    protected function validatePassword(string $value, array $options = []): bool
    {
        if ($value === '') {
            $this->errors['password'][] = 'Could not find mandatory password';
            return false;
        }

        if (strlen($value) < 8 || strlen($value) > 128) {
            $this->errors['password'][] = 'Password must be between 8 and 128 characters.';
            return false;
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d).{8,}$/', $value)) {
            $this->errors['password'][] = 'Password must be at least 8 characters long and contain at least one lowercase letter, one uppercase letter, and one number.';
            return false;
        }
        return true;
    }

	protected function validateUsername(string $value, array $options = []): bool
	{
		$forbiddenUsernames = ['moderator', 'admin', 'owner', 'superuser', 'root']; // Add more as needed

		if ($value === '') {
			$this->errors['username'][] = 'Could not find mandatory username';
			return false;
		}

		if (strlen($value) < 3 || strlen($value) > 23) {
			$this->errors['username'][] = 'Username must be between 3 and 23 characters.';
			return false;
		}

		if (!preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
			$this->errors['username'][] = 'Username must only contain letters, numbers, and underscores.';
			return false;
		}

		if (!preg_match('/[a-zA-Z]/', $value)) {
			$this->errors['username'][] = 'Username must contain at least one letter.';
			return false;
		}

		if (in_array(strtolower($value), $forbiddenUsernames, true)) {
			$this->errors['username'][] = 'This username is not allowed.';
			return false;
		}

		return true;
	}

    protected function validateTagName(string $value, array $options = []): bool
    {
        if ($value === '') {
            $this->errors['tag'][] = 'Tag is empty. It must have a value.';
            return false;
        }

        if (strlen($value) < 2 || strlen($value) > 53) {
            $this->errors['tag'][] = "Tag length must be between 2 and 53 characters. Given length: " . strlen($value);
            return false;
        }

        if (!preg_match('/^[a-zA-Z0-9-]+$/', $value)) {
            $this->errors['tag'][] = "Tag contains invalid characters. It must only contain letters (A-Z, a-z). Given value: $value";
            return false;
        }

        return true;
    }

    private function validateImage(string $imagePath, array $options = []): array
    {
        $allowedExtensions = $options['extensions'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedMimeTypes = $options['mime_types'] ?? ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = $options['max_size'] ?? 5 * 1024 * 1024; // 5 MB

        if (!file_exists($imagePath)) {
            $this->errors['image'][] = 'Image file does not exist.';
            return false;
        }

        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            $this->errors['image'][] = 'Invalid image extension. Allowed extensions: ' . implode(', ', $allowedExtensions);
            return false;
        }

        $mimeType = mime_content_type($imagePath);
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $this->errors['image'][] = 'Invalid image type. Allowed MIME types: ' . implode(', ', $allowedMimeTypes);
            return false;
        }

        $fileSize = filesize($imagePath);
        if ($fileSize > $maxFileSize) {
            $this->errors['image'][] = 'File size exceeds the maximum limit of ' . ($maxFileSize / 1024 / 1024) . ' MB.';
            return false;
        }

        $dimensions = getimagesize($imagePath);
        if (!$dimensions) {
            $this->errors['image'][] = 'Unable to read image dimensions.';
            return false;
        }

        return true;
    }
}
