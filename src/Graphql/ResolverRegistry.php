<?php

declare(strict_types=1);

namespace Fawaz\GraphQL;

/**
 * Contract for domain-specific resolver providers.
 * Each provider returns a map compatible with webonyx/graphql-php
 * where the top-level keys are type names (e.g., Query, Mutation, and object types),
 * and the values are arrays of field resolvers or type field maps.
 */
interface ResolverProvider
{
    /**
     * @return array<string, array<string, callable>>
     */
    public function getResolvers(): array;
}

/**
 * Aggregates resolver maps from multiple domain modules into a single map
 * suitable for passing to webonyx/graphql-php Executor.
 */
class ResolverRegistry
{
    /** @var array<string, array<string, callable>> */
    private array $resolvers = [];

    /**
     * Add a raw resolver map.
     * Later additions override earlier ones for the same type/field.
     *
     * @param array<string, array<string, callable>> $map
     */
    public function add(array $map): void
    {
        foreach ($map as $type => $fields) {
            if (!isset($this->resolvers[$type])) {
                $this->resolvers[$type] = [];
            }
            // Later maps override same field names
            $this->resolvers[$type] = array_merge($this->resolvers[$type], $fields);
        }
    }

    /** Register a provider and merge its resolvers. */
    public function addProvider(ResolverProvider $provider): void
    {
        $this->add($provider->getResolvers());
    }

    /**
     * Build the aggregated resolver map.
     *
     * @return array<string, array<string, callable>>
     */
    public function build(): array
    {
        return $this->resolvers;
    }
}

