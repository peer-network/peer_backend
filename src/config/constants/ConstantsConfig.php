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
        'content' => [
            'min_length' => 2,
            'max_length' => 200,
        ], 
    ];

    public const POST = [
        'title' => [
            'min_length' => 2,
            'max_length' => 63,
        ],
        'mediadescription' => [
            'min_length' => 3,
            'max_length' => 500,
        ],
    ];
}