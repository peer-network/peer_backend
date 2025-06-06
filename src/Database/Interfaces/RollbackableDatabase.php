<?php

namespace Fawaz\Database\Interfaces;

interface RollbackableDatabase {
    function beginTransaction() : bool;
    function commit() : bool;
    function rollback() : bool;
}
