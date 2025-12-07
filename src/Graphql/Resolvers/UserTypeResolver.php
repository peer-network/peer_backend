<?php

declare(strict_types=1);

namespace Fawaz\GraphQL\Resolvers;

use Fawaz\GraphQL\ResolverProvider;
use Fawaz\Utils\PeerLoggerInterface;

/**
 * Field resolvers for user-related object types.
 */
class UserTypeResolver implements ResolverProvider
{
    public function __construct(private readonly PeerLoggerInterface $logger) {}

    /** @return array<string, array<string, callable>> */
    public function getResolvers(): array
    {
        return [
            'ProfileUser' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Type.ProfileUser.id');
                    return $root['uid'] ?? '';
                },
                'visibilityStatus' => fn(array $root): string => strtoupper($root['visibility_status'] ?? 'NORMAL'),
                'hasActiveReports' => function (array $root): bool {
                    $reports = $root['reports'] ?? 0;
                    return (int)$reports > 0;
                },
                'isHiddenForUsers' => fn(array $root): bool => isset($root['isHiddenForUsers']) ? (bool)$root['isHiddenForUsers'] : false,
                'username' => fn(array $root): string => $root['username'] ?? '',
                'slug' => fn(array $root): int => $root['slug'] ?? 0,
                'img' => fn(array $root): string => $root['img'] ?? '',
                'isfollowed' => fn(array $root): bool => $root['isfollowed'] ?? false,
                'isfollowing' => fn(array $root): bool => $root['isfollowing'] ?? false,
                'isfriend' => fn(array $root): bool => $root['isfriend'] ?? false,
                'isreported' => fn(array $root): bool => $root['isreported'] ?? false,
            ],
            'BasicUserInfo' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Type.BasicUserInfo.userid');
                    return $root['uid'] ?? '';
                },
                'visibilityStatus' => fn(array $root): string => strtoupper($root['visibility_status'] ?? 'NORMAL'),
                'hasActiveReports' => function (array $root): bool {
                    $reports = $root['reports'] ?? 0;
                    return (int)$reports > 0;
                },
                'isHiddenForUsers' => fn(array $root): bool => isset($root['isHiddenForUsers']) ? (bool)$root['isHiddenForUsers'] : false,
                'img' => fn(array $root): string => $root['img'] ?? '',
                'username' => fn(array $root): string => $root['username'] ?? '',
                'slug' => fn(array $root): int => $root['slug'] ?? 0,
                'biography' => fn(array $root): string => $root['biography'] ?? '',
                'updatedat' => fn(array $root): string => $root['updatedat'] ?? '',
            ],
        ];
    }
}

