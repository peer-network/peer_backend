<?php

namespace Fawaz\App\Models;

use Fawaz\App\Models\Core\Model;

/**
 * UserDeviceToken stores device tokens for users to enable push notifications.
 *
 * Table: user_device_tokens
 * Has Foreign Keys:
 *  1. userid -> users(uid)
 */
class UserDeviceToken extends Model
{
    // Table name for the model
    protected static function table(): string
    {
        return 'user_device_tokens';
    }
}
