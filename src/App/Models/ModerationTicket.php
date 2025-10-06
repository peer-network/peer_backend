<?php

namespace Fawaz\App\Models;
use Fawaz\App\Models\Core\Model;

/**
 * ModerationTicket represents a moderation action taken on a user, post, or comment.
 * 
 * Table: moderation_tickets
 * Has Foreign Keys:
 */
class ModerationTicket extends Model {

    /**
     * Table name in the database
     */
    protected static function table(): string
    {
        return 'moderation_tickets';
    }
}