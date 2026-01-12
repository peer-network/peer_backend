<?php

declare(strict_types=1);

namespace Fawaz\config\constants;

use Fawaz\config\constants\ConstantsModeration;


/*
 


DONT CHANGE/REMOVE ANY EXISING TYPES/NAMES  

all changes MUST be BACKWARDS COMPATITABLE 
 otherwise front will crash💥



all changes through adding new fields

*/

class ConstantsConfig implements ConstantsConfigInterface
{
    public function getData()
    {
        return [
            "POST" => self::post(),
            "COMMENT" => self::comment(),
            "USER" => self::user(),
            "CHAT" => self::chat(),
            "CONTACT" => self::contact(),
            "PAGING" => self::paging(),
            "WALLET" => self::wallet(),
            "WALLETT" => self::wallett(),
            "ONBOARDING" => self::onboarding(),
            "DAILY_FREE" => self::dailyFree(),
            "TOKENOMICS" => self::tokenomics(),
            "MINTING" => self::minting(),
            "INPUT" => self::input(),
        ];
    }
    /**
     * @return array{
     *     AVAILABLE_ONBOARDINGS: string[]
     * }
     */
    public static function onboarding(): array
    {
        return ConstantsConfig::ONBOARDING;
    }
    /**
     * @return array{
     *     CONTENT: array{MIN_LENGTH: int, MAX_LENGTH: int}
     * }
     */
    public static function comment()
    {
        return ConstantsConfig::COMMENT;
    }
    /**
     * @return array{
     *     TITLE: array{MIN_LENGTH: int, MAX_LENGTH: int},
     *     MEDIADESCRIPTION: array{MIN_LENGTH: int, MAX_LENGTH: int},
     *     COVER: array{MAX_COUNT: int, MIN_LENGTH: int, MAX_LENGTH: int},
     *     MEDIA: array{MIN_LENGTH: int, MAX_LENGTH: int},
     *     OPTIONS: array{MIN_LENGTH: int, MAX_LENGTH: int},
     *     MEDIALIMIT: array{AUDIO: int, IMAGE: int, TEXT: int, VIDEO: int},
     *     COVERLIMIT: array{AUDIO: int, IMAGE: int, TEXT: int, VIDEO: int},
     *     TAG: array{
     *         MIN_LENGTH: int,
     *         MAX_LENGTH: int,
     *         PATTERN: string,
     *         MAX_COUNT: array{
     *             CREATE: int,
     *             SEARCH: int
     *         }
     *     },
     *     STATUS: array{PUBLISHED: int, ADVERTISED: int, ILLEGAL: int}
     * }
     */
    public static function post(): array
    {
        return ConstantsConfig::POST;
    }

    /**
     * @return array{
     *     SOLANA_PUBKEY: array{MIN_LENGTH: int, MAX_LENGTH: int, PATTERN: string},
     *     TOKEN: array{LENGTH: int},
     *     NUMBERS: array{MIN: float, MAX: float},
     *     NUMBERSQ: array{MIN: int, MAX: float},
     *     WHEREBY: array{MIN: int, MAX: int},
     *     ACTIONS: array<string, int>
     * }
     */
    public static function wallet()
    {
        return ConstantsConfig::WALLET;
    }

    /**
     * @return array{
     *     LIQUIDITY: array{MIN: float, MAX: float},
     *     LIQUIDITQ: array{MIN: int, MAX: float}
     * }
     */
    public static function wallett()
    {
        return ConstantsConfig::WALLETT;
    }

    /**
     * @return array{
     *     PASSWORD: array{MIN_LENGTH: int, MAX_LENGTH: int, PATTERN: string},
     *     USERNAME: array{MIN_LENGTH: int, MAX_LENGTH: int, PATTERN: string},
     *     BIOGRAPHY: array{MIN_LENGTH: int, MAX_LENGTH: int},
     *     PHONENUMBER: array{MIN_LENGTH: int, MAX_LENGTH: int, PATTERN: string},
     *     IMAGE: array{MIN_LENGTH: int, MAX_LENGTH: int},
     *     SLUG: array{MIN_LENGTH: int, MAX_LENGTH: int},
     *     LIQUIDITY: array{MIN_LENGTH: int, MAX_LENGTH: int},
     *     AVATAR: array{MAX_SIZE_MB: int},
     *     TRANSFER_MESSAGE: array{MIN_LENGTH: int, MAX_LENGTH: int, PATTERN_URL: string},
     *     TRANSACTION: array{MIN_AMOUNT: float, MAX_DECIMALS: int}
     * }
     */
    public static function user()
    {
        return ConstantsConfig::USER;
    }

    /**
     * @return array{
     *     MESSAGE: array{MIN_LENGTH: int, MAX_LENGTH: int},
     *     NAME: array{MIN_LENGTH: int, MAX_LENGTH: int},
     *     IMAGE: array{MIN_LENGTH: int, MAX_LENGTH: int},
     *     IS_PUBLIC: array{MIN: int, MAX: int, SUSPENDED: int},
     *     ACCESS_LEVEL: array{MIN: int, MAX: int, USER: int, ADMIN: int}
     * }
     */
    public static function chat()
    {
        return ConstantsConfig::CHAT;
    }

    /**
     * @return array{
     *     NAME: array{MIN_LENGTH: int, MAX_LENGTH: int},
     *     MESSAGE: array{MIN_LENGTH: int, MAX_LENGTH: int}
     * }
     */
    public static function contact()
    {
        return ConstantsConfig::CONTACT;
    }

    /**
     * @return array{
     *     OFFSET: array{MIN: int, MAX: int},
     *     POST_OFFSET: array{MIN: int, MAX: int},
     *     POST_LIMIT: array{MIN: int, MAX: int},
     *     LIMIT: array{MIN: int, MAX: int}
     * }
     */
    public static function paging(): array
    {
        return ConstantsConfig::PAGING;
    }

    /**
     * @return array{
     *     ACTIONTYPE: array{MIN_LENGTH: int, MAX_LENGTH: int},
     *     TYPE: array{MIN_LENGTH: int, MAX_LENGTH: int}
     * }
     */
    public static function transaction()
    {
        return ConstantsConfig::TRANSACTION;
    }

    public static function contentFiltering()
    {
        return ConstantsModeration::contentFiltering();
    }

    /**
     * @return array{
     *     DAILY_FREE_ACTIONS: array{ post: int, like: int, comment: int, dislike: int }
     * }
     */
    public static function dailyFree(): array
    {
        return ConstantsConfig::DAILY_FREE;
    }


    /**
     * @return array{
     *     ACTION_TOKEN_PRICES: array{
     *         post: float,
     *         like: float,
     *         dislike: float,
     *         comment: float,
     *         advertisementBasic: float,
     *         advertisementPinned: float
     *     },
     *     ACTION_GEMS_RETURNS: array{
     *         view: float,
     *         like: float,
     *         dislike: float,
     *         comment: float
     *     },
     *     FEES: array{
     *         INVITATION: float,
     *         POOL: float,
     *         PEER: float,
     *         BURN: float
     *     },
     *     FEES_STRING: array{
     *         INVITATION: string,
     *         PEER: string,
     *         BURN: string
     *     },
     * }
     */
    public static function tokenomics(): array
    {
        return ConstantsConfig::TOKENOMICS;
    }
    /**
     * @return array{ DAILY_NUMBER_TOKEN: float }
     */
    public static function minting(): array
    {
        return ConstantsConfig::MINTING;
    }
    /**
     * @return array{
     *     FORBID_CONTROL_CHARS_PATTERN: string
     * }
     */
    public static function input(): array
    {
        return ConstantsConfig::INPUT;
    }

    private const ONBOARDING = [
        'AVAILABLE_ONBOARDINGS' => [
            'INTROONBOARDING',
        ],
    ];
    private const DAILY_FREE = [
        'DAILY_FREE_ACTIONS' => [
            'post'    => 1,
            'like'    => 3,
            'comment' => 4,
            'dislike' => 0,
        ],
    ];
    private const MINTING = [
        'DAILY_NUMBER_TOKEN' => 5000.0,
    ];

    private const TOKENOMICS = [
        'ACTION_TOKEN_PRICES' => [
            'post'    => 20.0,
            'like'    => 3.0,
            'dislike' => 3.0,
            'comment' => 1.0,
            'advertisementBasic' => 50.0,
            'advertisementPinned' => 200.0,
        ],
        'ACTION_GEMS_RETURNS' => [
            'view'    => 0.25,
            'like'    => 5.0,
            'dislike' => -3.0,
            'comment' => 2.0,
        ],
        'FEES' => [
            'INVITATION' => 0.01,
            'POOL'       => 0.01,
            'PEER'       => 0.02,
            'BURN'       => 0.01,
        ],
        'FEES_STRING' => [
            'INVITATION' => '0.01',
            'PEER'       => '0.02',
            'BURN'       => '0.01',
        ],
    ];

    private const COMMENT = [
        'CONTENT' => [
            'MIN_LENGTH' => 2,
            'MAX_LENGTH' => 200,
        ],
    ];

    private const TRANSACTION = [
        'ACTIONTYPE' => [
            'MIN_LENGTH' => 0,
            'MAX_LENGTH' => 200,
        ],
        'TYPE' => [
            'MIN_LENGTH' => 1,
            'MAX_LENGTH' => 63,
        ],
    ];


    private const POST = [
        'TITLE' => [
            'MIN_LENGTH' => 1,
            'MAX_LENGTH' => 63,
        ],
        'MEDIADESCRIPTION' => [
            'MIN_LENGTH' => 1,
            'MAX_LENGTH' => 500,
        ],
        'COVER' => [
            'MAX_COUNT' => 1,
            'MIN_LENGTH' => 0,
            'MAX_LENGTH' => 1000,
        ],
        'MEDIA' => [
            'MIN_LENGTH' => 30,
            'MAX_LENGTH' => 3000, // because we are now allow 20 images files
        ],
        'OPTIONS' => [
            'MIN_LENGTH' => 0,
            'MAX_LENGTH' => 1000,
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
            'PATTERN' => '^[a-zA-Z0-9_]+$',
            'MAX_COUNT' => [
                'CREATE' => 10,
                'SEARCH' => 5,
            ],
        ],
        'STATUS' => [
            'PUBLISHED' => 0,
            'ADVERTISED' => 1,
            'ILLEGAL' => 2,
        ],
    ];

    private const WALLET = [
        'SOLANA_PUBKEY' => [
            'MIN_LENGTH' => 43,
            'MAX_LENGTH' => 44,
            'PATTERN' => '^[1-9A-HJ-NP-Za-km-z]{43,44}$',
        ],
        'TOKEN' => [
            'LENGTH' => 12,
        ],
        'NUMBERS' => [
            'MIN' => -5000.0,
            'MAX' => 5000.0,
        ],
        'NUMBERSQ' => [
            'MIN' => 0,
            'MAX' => 99999999999999999999999999999,
        ],
        'WHEREBY' => [
            'MIN' => 1,
            'MAX' => 100,
        ],
        // Mapping of human-readable action names to action codes (whereby)
        'ACTIONS' => [
            'VIEW' => 1,
            'LIKE' => 2,
            'DISLIKE' => 3,
            'COMMENT' => 4,
            'POST' => 5,
            'POSTINVESTBASIC' => 6,
            'POSTINVESTPREMIUM' => 7,
            'REPORT' => 8,
            'INVITATION' => 11,
            'OWNSHARED' => 12,
            'OTHERSHARED' => 13,
            'DIRECTDEBIT' => 14,
            'CREDIT' => 15,
            'TRANSFER' => 18,
            'FREELIKE' => 30,
            'FREECOMMENT' => 31,
            'FREEPOST' => 32,
        ],
    ];

    private const WALLETT = [
        'LIQUIDITY' => [
            'MIN' => -5000.0,
            'MAX' => 18250000.0,
        ],
        'LIQUIDITQ' => [
            'MIN' => 0,
            'MAX' => 99999999999999999999999999999,
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
        ],
        'MESSAGE' => [
            'MIN_LENGTH' => 3,
            'MAX_LENGTH' => 500,
        ],
    ];

    private const PAGING = [
        'OFFSET' => [
            'MIN' => 0,
            'MAX' => 2147483647,
        ],
        'POST_OFFSET' => [
            'MIN' => 0,
            'MAX' => 2147483647,
        ],
        'POST_LIMIT' => [
            'MIN' => 0,
            'MAX' => 20,
        ],
        'LIMIT' => [
            'MIN' => 1,
            'MAX' => 20,
        ],
    ];
    // ASCII control chars + zero-width Unicode chars
    private const CONTROL_CHARS_PATTERN = '[\x00-\x08\x0B-\x1F\x7F\x{200B}\x{200C}\x{200D}\x{FEFF}\x{00A0}]';

    private const INPUT = [
        'FORBID_CONTROL_CHARS_PATTERN' => self::CONTROL_CHARS_PATTERN,
    ];

    private const USER = [
        'PASSWORD' => [
            'MIN_LENGTH' => 8,
            'MAX_LENGTH' => 128,
            "PATTERN" => '^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$'
        ],
        'USERNAME' => [
            'MIN_LENGTH' => 3,
            'MAX_LENGTH' => 23,
            'PATTERN' => '^[a-zA-Z0-9_-]+$',
        ],
        'BIOGRAPHY' => [
            'MIN_LENGTH' => 1,
            'MAX_LENGTH' => 5000,
        ],
        'PHONENUMBER' => [
            'MIN_LENGTH' => 9,
            'MAX_LENGTH' => 21,
            'PATTERN' => '^\+?[1-9]\d{0,2}[\s.-]?\(?\d{1,4}\)?[\s.-]?\d{1,4}[\s.-]?\d{1,9}$',
        ],
        'IMAGE' => [
            'MIN_LENGTH' => 0,
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
        'TRANSFER_MESSAGE' => [
            'MIN_LENGTH' => 0,
            'MAX_LENGTH' => 500,
            'PATTERN_URL' => '(:\/\/|www\.)',
        ],
        'TRANSACTION' => [
            'MIN_AMOUNT' => 0.000001,
            'MAX_DECIMALS' => 8, 
        ],
    ];
}
