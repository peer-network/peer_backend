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
use Fawaz\config\constants\ConstantsConfig;


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

            if (!isset($this->data[$field]) && empty($rules['required'])) {
                continue;
            }

            if (isset($this->data[$field]) && empty($this->data[$field]) && empty($rules['required'])) {
                continue;
            }

            if (!isset($this->data[$field]) && !empty($rules['required'])) {
                $this->errors[$field][] = $field;
                continue;
            }

            if (isset($this->data[$field])) {
                foreach ($rules['filters'] ?? [] as $filter) {
                    $filterName = $filter['name'];
                    $options = $filter['options'] ?? [];
                    if (method_exists($this, $filterName)) {
                        $this->data[$field] = $this->$filterName($this->data[$field], $options);
                    } else {
                        throw new ValidationException("Filter method $filterName does not exist.");
                    }
                }

                foreach ($rules['validators'] ?? [] as $validator) {
                    $validatorName = $validator['name'];
                    $options = $validator['options'] ?? [];
                    if (method_exists($this, $validatorName)) {
                        if (!$this->$validatorName($this->data[$field], $options)) {
                            if (isset($this->errors[$field]) && is_array($this->errors[$field])){
                                if (!empty($this->errors[$field][0])){
                                    continue;
                                }
                            } else {
                                if (!empty($this->errors[$field])){    
                                    continue;
                                }
                            }
                            $this->errors[$field][] = $field;
                            if (!empty($validator['break_chain_on_failure'])) {
                                break;
                            }
                        }
                    } else {
                        throw new ValidationException("Validator method $validatorName does not exist.");
                    }
                }
            }
        }

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
            $this->errors['value'][] = 30247;
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

        $dateTime = DateTime::createFromFormat($format, $value);

        if ($dateTime) {
            $formatted = $dateTime->format($format);

            $formatted = preg_replace_callback('/\.(\d{1,6})(0*)$/', function ($matches) {
                return '.' . str_pad($matches[1], 6, '0');
            }, $formatted);

            $value = trim($value);
            $formatted = trim($formatted);

            if ($formatted === $value) {
                return true;
            }
        }

        $this->errors['Date'][] = 30258;
        return false;
    }

    protected function LessThan(string $value, array $options = []): bool
    {
        $max = $options['max'] ?? null;
        $inclusive = $options['inclusive'] ?? false;

        if ($max === null) {
            $this->errors['Max'][] = 30248;
            return false;
        }

        $valueDateTime = DateTime::createFromFormat('Y-m-d H:i:s.u', $value);
        $maxDateTime = DateTime::createFromFormat('Y-m-d H:i:s.u', $max);

        if ($valueDateTime === false) {
            $this->errors['valueDateTime'][] = 30249;
            return false;
        }

        if ($maxDateTime === false) {
            $this->errors['maxDateTime'][] = 30250;
            return false;
        }

        $valueTimestamp = (float)$valueDateTime->format('U.u');
        $maxTimestamp = (float)$maxDateTime->format('U.u');

        return $inclusive ? $valueTimestamp <= $maxTimestamp : $valueTimestamp < $maxTimestamp;
    }

    protected function validateIntRange(mixed $value, array $options = []): bool
    {
        if (!is_numeric($value) || (int)$value != $value) {
            $this->errors['int_range'][] = 30244;
            return false;
        }

        $value = (int)$value;
        $min = $options['min'] ?? PHP_INT_MIN;
        $max = $options['max'] ?? PHP_INT_MAX;

        if ($value < $min) {
            $this->errors['int_range'][] = 30245;
            return false;
        }

        if ($value > $max) {
            $this->errors['int_range'][] = 30246;
            return false;
        }

        return true;
    }

    protected function EmailAddress(string $value, array $options = []): bool
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) == false) {
            $this->errors['email'][] = 30224;
            return false;
        }
        return true;
    }

    protected function Digits(string $value, array $options = []): bool
    {
        return ctype_digit($value);
    }

    protected function StringLength(string $value, array $options = []): bool
    {
        $min =  (int)$options['min'] ?? 0;
        $max = (int)$options['max'] ?? PHP_INT_MAX;
        $errorCode = $options['errorCode'] ?? 40301;
        $length = strlen($value);   

        $validationResult = ($length >= $min && $length <= $max);

        if ($validationResult == false) {
            $this->errors['content'][] = $errorCode;
        }
        return $validationResult;
    }

    protected function ArrayValues(mixed $value, array $options = []): bool
    {
        if (!is_array($value)) {
            return false;
        }

        $validator = $options['validator'] ?? null;
        if (!$validator || !isset($validator['name'])) {
            $this->errors['ArrayValues'][] = 40301;
        }

        $validatorName = $validator['name'];
        $validatorOptions = $validator['options'] ?? [];

        foreach ($value as $item) {
            if (!method_exists($this, $validatorName)) {
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

    protected function getBooleanFlags(array $types): int
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
               isset($chatmessage->createdat) && $this->StringLength($chatmessage->createdat, ['min' => 1,'max' => 40]);
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
               isset($profilepost->createdat) && $this->LessThan($profilepost->createdat, ['max' => (new DateTime())->format('Y-m-d H:i:s.u'), 'inclusive' => true]);
    }

    protected function validatePassword(string $value, array $options = []): bool
    {
        $passwordConfig = ConstantsConfig::user()['PASSWORD'];

        if ($value === '') {
            $this->errors['password'][] = 30101;
            return false;
        }

        if (strlen($value) < $passwordConfig['MIN_LENGTH'] || strlen($value) > $passwordConfig['MAX_LENGTH']) {
            $this->errors['password'][] = 30226;
            return false;
        }

        if (!preg_match('/' . $passwordConfig['PATTERN'] . '/', $value)) {
            $this->errors['password'][] = 30226;
            return false;
        }

        return true;
    }

    protected function validateUsername(string $value, array $options = []): bool
    {
        $forbiddenUsernames = ['moderator', 'admin', 'owner', 'superuser', 'root', 'master', 'publisher', 'manager', 'developer']; 

        if ($value === '') {
            $this->errors['username'][] = 30202;
            return false;
        }

        if (strlen($value) < 3 || strlen($value) > 23) {
            $this->errors['username'][] = 30202;
            return false;
        }

        if (!preg_match('/^[a-zA-Z0-9_]{3,23}$/', $value)) {
            $this->errors['username'][] = 30202;
            return false;
        }

        if (!preg_match('/[a-zA-Z]/', $value)) {
            $this->errors['username'][] = 30202;
            return false;
        }

        if (in_array(strtolower($value), $forbiddenUsernames, true)) {
            $this->errors['username'][] = 31002;
            return false;
        }

        return true;
    }

    protected function validateTagName(string $value, array $options = []): bool
    {
        if ($value === '') {
            $this->errors['tag'][] = 30101;
            return false;
        }

        $value = trim($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        if (strlen($value) < 2 || strlen($value) > 53) {
            $this->errors['tag'][] = 30103;
            return false;
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
            $this->errors['tag'][] = 30103;
            return false;
        }

        return true;
    }

    protected function validatePkey(string $value, array $options = []): bool
    {
        if ($value === '') {
            $this->errors['pkey'][] = 30103;
            return false;
        }

        $value = trim($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        if (strlen($value) < 43 || strlen($value) > 44) {
            $this->errors['pkey'][] = 30254;
            return false;
        }

        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{43,44}$/', $value)) {
            $this->errors['pkey'][] = 30254;
            return false;
        }

        return true;
    }

    protected function validatePhoneNumber(string $value, array $options = []): bool
    {
        $phoneConfig = ConstantsConfig::user()['PHONENUMBER'];

        if ($value === '') {
            $this->errors['phone'][] = 30103;
            return false;
        }

        $value = trim($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');


        if (!preg_match('/' . $phoneConfig['PATTERN'] . '/', $value)) {
            $this->errors['phone'][] = 30253;
            return false;
        }

        return true;
    }

	protected function validateResetToken(string $value, array $options = []): bool
	{
		if ($value === '') {
			$this->errors['reset_token'][] = 'Reset token is required.';
			return false;
		}

		$value = trim($value);
		$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

		if (strlen($value) !== 64) {
			$this->errors['reset_token'][] = 'Reset token must be exactly 64 characters.';
			return false;
		}

		if (!preg_match('/^[a-f0-9]{64}$/i', $value)) {
			$this->errors['reset_token'][] = 'Invalid reset token format.';
			return false;
		}

		return true;
	}

	protected function validateActivationToken(string $value, array $options = []): bool
	{
		if ($value === '') {
			$this->errors['activation_token'][] = 'Activation token is required.';
			return false;
		}

		$value = trim($value);
		$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

		if (strlen($value) !== 64) {
			$this->errors['activation_token'][] = 'Activation token must be exactly 64 characters.';
			return false;
		}

		if (!preg_match('/^[a-f0-9]{64}$/i', $value)) {
			$this->errors['activation_token'][] = 'Invalid activation token format.';
			return false;
		}

		return true;
	}

    protected function validateImage(string $imagePath, array $options = []): bool
    {
        $allowedExtensions = $options['extensions'] ?? ['webp', 'jpeg', 'jpg', 'png', 'gif', 'heic', 'heif', 'tiff'];
        $allowedMimeTypes = $options['mime_types'] ?? ['image/webp', 'image/jpeg', 'image/png', 'image/gif', 'image/heic', 'image/heif', 'image/tiff'];
        $maxFileSize = $options['max_size'] ?? 5 * 1024 * 1024;
        $maxWidth = $options['max_width'] ?? null;
        $maxHeight = $options['max_height'] ?? null;
        $imagePath = __DIR__ . '/../../runtime-data/media' . $imagePath;

        if (!is_readable($imagePath)) {
            $this->errors['image'][] = 30263;
            return false;
        }

        if (filesize($imagePath) > $maxFileSize) {
            $this->errors['image'][] = 21517;
            return false;
        }

        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            $this->errors['image'][] = 21518;
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $imagePath);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $this->errors['image'][] = 21519;
            return false;
        }

        if ($maxWidth !== null || $maxHeight !== null) {
            [$width, $height] = getimagesize($imagePath);
            if (!$width || !$height) {
                $this->errors['image'][] = 25120;
                return false;
            }
            
            if (($maxWidth !== null && $width > $maxWidth) || ($maxHeight !== null && $height > $maxHeight)) {
                $this->errors['image'][] = 21521;
                return false;
            }
        }

        return true;
    }
}