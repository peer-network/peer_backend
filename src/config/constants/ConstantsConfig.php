<?php

namespace Fawaz\config\constants;

class ConstantsConfig
{
    public function getData() {
        return [
            "POST" => $this::post(),
            "COMMENT" => $this::comment(),
            "WALLET" => $this::wallet(),
            "WALLETT" => $this::wallett(),        
        ];
    }

    public static function comment() {
        return ConstantsConfig::COMMENT;
    }

    public static function post() {
        return ConstantsConfig::POST;
    }
    public static function wallet() {
        return ConstantsConfig::WALLET;
    }
    public static function wallett() {
        return ConstantsConfig::WALLETT;
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
}