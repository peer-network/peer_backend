<?php

namespace Fawaz\config\constants;
use Fawaz\config\constants\ConstantsModeration;

class ConstantsConfig
{
    public function getData() {
        return [
            "POST" => $this::post(),
            "COMMENT" => $this::comment(),
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
    ];
}