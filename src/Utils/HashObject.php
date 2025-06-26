<?php

namespace Fawaz\Utils;

use Fawaz\Database\Interfaces\Hashable;

trait HashObject {
    private function hashObject(Hashable $object): string {
        return hash('sha256', $object->getHashableContent());
    }
}
