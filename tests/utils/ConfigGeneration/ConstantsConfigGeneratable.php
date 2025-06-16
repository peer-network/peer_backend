<?php
declare(strict_types=1);

namespace Tests\utils\ConfigGeneration;
use Fawaz\config\constants\ConstantsConfig;

class ConstantsConfigGeneratable implements DataGeneratable {
    private array $data = [];


    public function __construct() {  

        $constants = new ConstantsConfig();
        $this->data = $constants->getData();
    }

    public function getData(): array {
        return $this->data;
    }
}