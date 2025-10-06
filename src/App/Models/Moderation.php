<?php

declare(strict_types=1);

namespace Fawaz\App\Models;

use Fawaz\App\Models\Core\Model;

/**
 * Moderation represents a moderation action taken on a user, post, or comment.
 * 
 * Table: moderations
 * Has Foreign Keys:
 * 1. moderationticketid -> moderation_tickets(uid)
 */
class Moderation extends Model {

    // Table name for the model
    protected static function table(): string
    {
        return 'moderations';
    }
}