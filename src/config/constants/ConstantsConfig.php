<?php

namespace Fawaz\config\constants;

class ConstantsConfig
{
    public function getData() {
        return [
            "POST" => $this::POST,
            "COMMENT" => $this::COMMENT,
        ];
    }

    public const COMMENT = [
        'CONTENT' => [
            'MIN_LENGTH' => 2,
            'MAX_LENGTH' => 200,
        ], 
    ];

    public const POST = [
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