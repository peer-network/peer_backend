<?php
declare(strict_types=1);

namespace Fawaz\Database\Interfaces;

use PDO;
use Fawaz\Database\Interfaces\RollbackableDatabase;
use Psr\Log\LoggerInterface;

class PeerMapper implements RollbackableDatabase
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function beginTransaction(): bool {
        return $this->db->beginTransaction();
    }

    public function commit(): bool {
        return $this->db->commit();
    }

    public function rollback(): bool {
        return $this->db->rollBack();
    }
}