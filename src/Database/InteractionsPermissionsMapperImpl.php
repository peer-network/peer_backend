<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\Database\Interfaces\InteractionsPermissionsMapper;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\Utils\PeerLoggerInterface;

class InteractionsPermissionsMapperImpl implements InteractionsPermissionsMapper
{
    public function __construct(protected PeerLoggerInterface $logger, protected \PDO $db)
    {
    }

    public function isInteractionAllowed(array $specs, string $targetContentId): bool
    {
        $this->logger->debug('InteractionsPermissionsMapper.isInteractionAllowed started');

        // Build SQL fragments from specs, ignoring nulls
        $specsSQL = array_filter(
            array_map(
                fn (Specification $spec) => $spec->forbidInteractions($targetContentId),
                $specs
            )
        );

        // If no specs contribute SQL, interaction is allowed
        if (empty($specsSQL)) {
            return true;
        }

        $allSpecs     = SpecificationSQLData::merge($specsSQL);
        $whereClauses = $allSpecs->whereClauses;
        $params       = $allSpecs->paramsToPrepare;

        $whereClausesString = implode(' AND ', $whereClauses);

        // Combine all spec SQL fragments into a single boolean expression.
        // Default to TRUE (allowed) if no clauses are present (handled above).
        $booleanExpression = '' !== $whereClausesString ? $whereClausesString : '1=1';

        // Evaluate the combined boolean expression without needing a FROM clause.
        $sql  = 'SELECT ('.$booleanExpression.') as allowed';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $isAllowed = $stmt->fetchColumn();

        // fetchColumn returns string|int|false; normalize to strict bool
        return (bool) ((int) (false !== $isAllowed ? $isAllowed : 0));
    }
}
