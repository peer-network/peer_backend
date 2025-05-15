<?php
declare(strict_types=1);

namespace Fawaz\Config\Constants;

class Constants {
    public static ChatConstants $chat = new ChatConstants(); 
    public static ChatMessagesConstants $chatMessages = new ChatMessagesConstants(); 
    public static CommentConstants $comment = new CommentConstants(); 
}

class ChatConstants {
    public static ValidationRange $nameStringLength = new ValidationRange(3, 53);
}

class ChatMessagesConstants {
    public static ValidationRange $contentStringLength = new ValidationRange(1, 500);
}

class CommentConstants {
    public static ValidationRange $contentStringLength = new ValidationRange(2, 200);
}