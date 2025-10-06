<?php

namespace Fawaz\App\Models;

use Fawaz\App\Models\Core\Model;

/**
 * UserReport stores reports made by users against other users, posts, or comments.
 * 
 * Table: user_reports
 * Has Foreign Keys:
 *  1. moderationticketid -> moderation_tickets(uid)
 */
class UserReport extends Model
{

    // Table name for the model
    protected static function table(): string
    {
        return 'user_reports';
    }
}