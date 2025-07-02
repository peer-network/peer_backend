<?php

namespace Fawaz\config\constants;

class ConstantsConfig
{
    public function getData() {
        return [
            "POST" => $this::post(),
            "COMMENT" => $this::comment(),
            "USER" => $this::user(),
            "CHAT" => $this::chat(),
            "CONTACT" => $this::contact(),
            "PAGING" => $this::paging(),
        ];
    }

    public static function comment() {
        return ConstantsConfig::COMMENT;
    }

    public static function post() {
        return ConstantsConfig::POST;
    }
    public static function user() {
        return ConstantsConfig::USER;
    }
    public static function chat() {
        return ConstantsConfig::CHAT;
    }
    public static function contact() {
        return ConstantsConfig::CONTACT;
    }

    public static function paging() {
        return ConstantsConfig::PAGING;
    }

    private const COMMENT = [
        'CONTENT' => [
            'MIN_LENGTH' => 2,
            'MAX_LENGTH' => 200,
        ], 
    ];

    private const POST = [
        'TITLE' => [
            'MIN_LENGTH' => 2,
            'MAX_LENGTH' => 63,
        ],
        'MEDIADESCRIPTION' => [
            'MIN_LENGTH' => 3,
            'MAX_LENGTH' => 500,
        ],
        'COVER' => [
            'MAX_COUNT' => 1,
        ],
    ];

    private const CHAT = [
        'MESSAGE' => [
            'MIN_LENGTH' => 1,
            'MAX_LENGTH' => 500,
        ],
        'NAME' => [
            'MIN_LENGTH' => 3,
            'MAX_LENGTH' => 53,
        ],
        'IMAGE' => [
            'MIN_LENGTH' => 30,
            'MAX_LENGTH' => 100,
        ],
        'IS_PUBLIC' => [
            'MIN' => 0,
            'MAX' => 10,
            'SUSPENDED' => 9,
        ],
        'ACCESS_LEVEL' => [
            'MIN' => 0,
            'MAX' => 10,
            'USER' => 0,
            'ADMIN' => 10,
        ],
    ];

    private const CONTACT = [
        'NAME' => [
            'MIN_LENGTH' => 3,
            'MAX_LENGTH' => 53,
            'PATTERN' => '^[a-zA-Z]+$',
        ],
        'MESSAGE' => [
            'MIN_LENGTH' => 3,
            'MAX_LENGTH' => 500,
        ],
    ];   

    private const USER = [
        'AVATAR' => [
            'MAX_SIZE_MB' => 5,
        ],
        'TRANSACTION' => [
            'MIN_TOKENS' => 10,
        ],
        'PASSWORD' => [
            'MIN_LENGTH' => 8,
            'MAX_LENGTH' => 128,
            'PATTERN' => '^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$',
        ],
        'BIOGRAPHY' => [
            'MIN_LENGTH' => 3,
            'MAX_LENGTH' => 5000,
        ],
        'PHONENUMBER' => [
            'MIN_LENGTH' => 9,
            'MAX_LENGTH' => 21,
            'PATTERN' => '^\+?[1-9]\d{0,2}[\s.-]?\(?\d{1,4}\)?[\s.-]?\d{1,4}[\s.-]?\d{1,9}$',
        ],
    ];
    private const PAGING = [
        'OFFSET' => [
            'MIN' => 0,
            'MAX' => 2147483647,
        ],
        'LIMIT' => [
            'MIN' => 1,
            'MAX' => 20,
        ],
    ];  
}