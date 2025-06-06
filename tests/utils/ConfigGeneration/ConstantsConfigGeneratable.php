<?php
declare(strict_types=1);

namespace Tests\Utils\ConfigGeneration;
use Fawaz\config\constants\ConstantsConfig;

class ConstantsConfigGeneratable implements DataGeneratable {
    /** @var array<string, MessageEntry> */
    private array $data = [];


    public function __construct() {  

        $constants = new ConstantsConfig();
        $this->data = $constants->getData();
    }

    public function getData(): array {
        return $this->data;
    }
}