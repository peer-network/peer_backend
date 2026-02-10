<?php

namespace Fawaz\App\Models;

use Fawaz\App\Models\Core\Model;
use Fawaz\App\User;

/**
 * UserDeviceToken stores device tokens for users to enable push notifications.
 *
 * Table: user_device_tokens
 * Has Foreign Keys:
 *  1. userid -> users(uid)
 */
class UserDeviceToken extends Model
{
    protected string $userid;
    protected string $token;
    protected string $platform;
    protected string $language;
    protected bool $debug;
    protected string $createdat;
    protected User $userObj;

    public function __construct(array $data = [])
    {

        $this->userid = $data['userid'] ?? '';
        $this->token = $data['token'] ?? '';
        $this->platform = $data['platform'] ?? '';
        $this->language = $data['language'] ?? '';
        $this->debug = $data['debug'] ?? false;
        $this->createdat = $data['createdat'] ?? '';

        $this->userObj = new User($data, [], false);
    }

    // Table name for the model
    protected static function table(): string
    {
        return 'user_device_tokens';
    }

    public function getArrayCopy(): array
    {
        return [
            'userid' => $this->userid,
            'token' => $this->token,
            'platform' => $this->platform,
            'language' => $this->language,
            'debug' => $this->debug,
            'createdat' => $this->createdat,
            'userObj' => $this->userObj->getArrayCopy()
        ];
    }

    public function getDeviceToken(): string
    {
        return $this->token;
    }


    public function getPlatform(): string
    {
        return $this->platform;
    }


    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getDebug(): bool
    {
        return $this->debug;
    }

}
