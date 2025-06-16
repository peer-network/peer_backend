<?php
declare(strict_types=1);

namespace Tests\utils\ConfigGeneration;

require __DIR__ . '../../../../vendor/autoload.php';

interface DataGeneratable {
    public function getData(): array;
}
