<?php

namespace Fawaz\Utils;

use Fawaz\Database\Interfaces\Hashable;

trait HashObject {
    public function hashObject(Hashable $object): string {
        return hash('sha256', $object->getHashableContent());
    }
}
