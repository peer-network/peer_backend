<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use PDO;
use Fawaz\App\CommentInfo;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\App\Status;
use function DI\string;

class InteractionsPermissionsMapper
{

    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db) {}

    // public function fetchSpecsREstrictions(array $specs,ContentType $targetContentType, string $targetContentId): array
    // {
    //     $this->logger->debug("InteractionsPermissionsMapper.addUserActivity started");

    //     $specsSQL = array_map(fn(Specification $spec) => $spec->forbidInteractions($targetContentType, $targetContentId), $specs);
    //     $allSpecs = SpecificationSQLData::merge($specsSQL);
    //     $sql = $allSpecs->whereClauses;
    //     $params = $allSpecs->paramsToPrepare;

        

    //     $stmt = $this->db->prepare($sql);
    //     $stmt->execute($params);
    //     $exists = $stmt->fetchColumn();

    
    //     return $exists;
    // }
}
