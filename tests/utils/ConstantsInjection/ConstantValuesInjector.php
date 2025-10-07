<?php
declare(strict_types=1);

namespace Tests\utils\ConstantsInjection;

require __DIR__ . '../../../../vendor/autoload.php';

interface ConstantValuesInjector {
    public function injectConstants(array|string $data): array|string;
}
