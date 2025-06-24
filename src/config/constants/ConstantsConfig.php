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

    public static function contentFiltering() {
        return ConstantsModeration::contentFiltering();
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
            'MAX_LIMIT' => 5,
            'PATTERN' => '[a-zA-Z]+',        
        ],
    ];   
    private const USER = [
        'USERNAME' => [
            'MIN_LENGTH' => 3,
            'MAX_LENGTH' => 23,
            'PATTERN' => '[a-zA-Z0-9]+',
        ],
    ]; 
}