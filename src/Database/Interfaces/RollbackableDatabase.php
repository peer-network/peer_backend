<?php

namespace Fawaz\Database\Interfaces;

interface RollbackableDatabase
{
    public function beginTransaction(): bool;
    public function commit(): bool;
    public function rollback(): bool;
}
