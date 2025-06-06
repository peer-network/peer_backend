<?php

namespace Fawaz\config\constants;

class ConstantsConfig
{
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

    public function getData() {
        return [
            "POST" => $this::POST,
        ];
    }
}