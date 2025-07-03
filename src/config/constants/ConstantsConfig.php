<?php

namespace Fawaz\config\constants;
use Fawaz\config\constants\ConstantsModeration;

class ConstantsConfig
{
    public function getData() {
        return [
            "POST" => $this::post(),
            "COMMENT" => $this::comment(),
            "USER" => $this::user(),
            "PAGING" => $this::paging(),
            "CHAT" => $this::chat(),
            "CONTACT" => $this::contact(),
        ];
    }

    public static function comment() {
        return ConstantsConfig::COMMENT;
    }

    public static function post() {
        return ConstantsConfig::POST;
    }

    public static function contentFiltering() {
        return ConstantsModeration::contentFiltering();
    }
    public static function paging() {
        return ConstantsConfig::PAGING;
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
        'MEDIALIMIT' => [
            'AUDIO' => 1,
            'IMAGE' => 5,
            'TEXT' => 1,
            'VIDEO' => 2,
        ],
        'COVERLIMIT' => [
            'AUDIO' => 1,
            'IMAGE' => 1,
            'TEXT' => 1,
            'VIDEO' => 1,
        ],
        'TAG' => [
            'MIN_LENGTH' => 2,
            'MAX_LENGTH' => 53,
            'PATTERN' => '[a-zA-Z]+',
            'MAX_COUNT' => [
                'CREATE' => 10,
                'SEARCH' => 5,
            ],
        ],
        'COVER' => [
            'MAX_COUNT' => 1,
            'MIN_LENGTH' => 0,
            'MAX_LENGTH' => 1000,
        ],
        'MEDIA' => [
            'MIN_LENGTH' => 30,
            'MAX_LENGTH' => 1000,
        ],
        'OPTIONS' => [
            'MIN_LENGTH' => 0,
            'MAX_LENGTH' => 1000,
        ]
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
    private const USER = [
        'USERNAME' => [
            'MIN_LENGTH' => 3,
            'MAX_LENGTH' => 23,
            'PATTERN' => '[a-zA-Z0-9]+',
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
        'IMAGE' => [
            'MIN_LENGTH' => 30,
            'MAX_LENGTH' => 100,
        ],
        'SLUG' => [
            'MIN_LENGTH' => 00001,
            'MAX_LENGTH' => 99999,
        ],
        'LIQUIDITY' => [
            'MIN_LENGTH' => -18250000,
            'MAX_LENGTH' => 18250000,
        ],
        'AVATAR' => [
            'MAX_SIZE_MB' => 5,
        ],
        'TRANSACTION' => [
            'MIN_TOKENS' => 10,
        ],
        'SLUG' => [
            'MIN_LENGTH' => 00001,
            'MAX_LENGTH' => 99999,
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
}