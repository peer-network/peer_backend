<?php

declare(strict_types=1);

namespace Fawaz\GraphQL\Resolvers;

use Fawaz\App\Status;
use Fawaz\GraphQL\ResolverProvider;
use Fawaz\GraphQL\Response\MetaBuilder;
use Fawaz\Utils\PeerLoggerInterface;

/**
 * Field resolvers for user-related object types.
 */
class UserTypeResolver implements ResolverProvider
{
    public function __construct(
        private readonly PeerLoggerInterface $logger,
        protected MetaBuilder $metaBuilder
    ) {}

    protected function getStatusNameByID(int $status): ?string
    {
        $statusCode = $status;
        $statusMap = Status::getMap();

        return $statusMap[$statusCode] ?? null;
    }
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
            'UserPreferencesResponse' => [
                'meta' => fn(array $root): array => $this->metaBuilder->build($root),
                'status' => function (array $root): string {
                    $this->logger->debug('Query.DefaultResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn(array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn(array $root): array => $root['affectedRows'] ?? [],
            ],
            'UserPreferences' => [
                'contentFilteringSeverityLevel' => function (array $root): ?string {
                    $this->logger->debug('Query.UserPreferences Resolvers');
                    return $root['contentFilteringSeverityLevel'];
                },
                'onboardingsWereShown' => function (array $root): array {
                    $this->logger->info('Query.UserPreferences.onboardingsWereShown Resolver');
                    return $root['onboardingsWereShown'] ?? [];
                },
            ],
            'User' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.User Resolvers');
                    return $root['uid'] ?? '';
                },
                'visibilityStatus' => fn(array $root): string => strtoupper($root['visibility_status'] ?? 'NORMAL'),
                'hasActiveReports' => function (array $root): bool {
                    $reports = $root['reports'] ?? 0;
                    return (int)$reports > 0;
                },
                'isHiddenForUsers' => fn(array $root): bool => isset($root['isHiddenForUsers']) ? (bool)$root['isHiddenForUsers'] : false,
                'situation' => function (array $root): string {
                    $status = $root['status'] ?? 0;
                    return $this->getStatusNameByID($status) ?? '';
                },
                'email' => fn(array $root): string => $root['email'] ?? '',
                'username' => fn(array $root): string => $root['username'] ?? '',
                'password' => fn(array $root): string => $root['password'] ?? '',
                'status' => fn(array $root): int => $root['status'] ?? 0,
                'verified' => fn(array $root): int => $root['verified'] ?? 0,
                'slug' => fn(array $root): int => $root['slug'] ?? 0,
                'roles_mask' => fn(array $root): int => $root['roles_mask'] ?? 0,
                'ip' => fn(array $root): string => $root['ip'] ?? '',
                'img' => fn(array $root): string => $root['img'] ?? '',
                'biography' => fn(array $root): string => $root['biography'] ?? '',
                'liquidity' => fn(array $root): float => $root['liquidity'] ?? 0.0,
                'createdat' => fn(array $root): string => $root['createdat'] ?? '',
                'updatedat' => fn(array $root): string => $root['updatedat'] ?? '',
            ],
            'UserInfoResponse' => [
                'meta' => fn(array $root): array => $this->metaBuilder->build($root),
                'status' => function (array $root): string {
                    $this->logger->debug('Query.UserInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn(array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn(array $root): array => $root['affectedRows'] ?? [],
            ],
            'UserListResponse' => [
                'meta' => fn(array $root): array => $this->metaBuilder->build($root),
                'status' => function (array $root): string {
                    $this->logger->debug('Query.UserListResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn(array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn(array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn(array $root): array => $root['affectedRows'] ?? [],
            ],
            'Profile' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.User Resolvers');
                    return $root['uid'] ?? '';
                },
                'visibilityStatus' => fn(array $root): string => strtoupper($root['visibility_status'] ?? 'NORMAL'),
                'hasActiveReports' => function (array $root): bool {
                    $reports = $root['reports'] ?? 0;
                    return (int)$reports > 0;
                },
                'isHiddenForUsers' => fn(array $root): bool => isset($root['isHiddenForUsers']) ? (bool)$root['isHiddenForUsers'] : false,
                'situation' => function (array $root): string {
                    $status = $root['status'] ?? 0;
                    return $this->getStatusNameByID(0) ?? '';
                },
                'username' => fn(array $root): string => $root['username'] ?? '',
                'status' => fn(array $root): int => $root['status'] ?? 0,
                'slug' => fn(array $root): int => $root['slug'] ?? 0,
                'img' => fn(array $root): string => $root['img'] ?? '',
                'biography' => fn(array $root): string => $root['biography'] ?? '',
                'amountposts' => fn(array $root): int => $root['amountposts'] ?? 0,
                'amounttrending' => fn(array $root): int => $root['amounttrending'] ?? 0,
                'amountfollower' => fn(array $root): int => $root['amountfollower'] ?? 0,
                'amountfollowed' => fn(array $root): int => $root['amountfollowed'] ?? 0,
                'amountfriends' => fn(array $root): int => $root['amountfriends'] ?? 0,
                'amountblocked' => fn(array $root): int => $root['amountblocked'] ?? 0,
                'amountreports' => fn(array $root): int => $root['amountreports'] ?? 0,
                'isfollowed' => fn(array $root): bool => $root['isfollowed'] ?? false,
                'isfollowing' => fn(array $root): bool => $root['isfollowing'] ?? false,
                'isreported' => fn(array $root): bool => $root['isreported'] ?? false,
                'imageposts' => fn(array $root): array => [],
                'textposts' => fn(array $root): array => [],
                'videoposts' => fn(array $root): array => [],
                'audioposts' => fn(array $root): array => [],
            ],
            'ProfileInfo' => [
                'meta' => fn(array $root): array => $this->metaBuilder->build($root),
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ProfileInfo Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn(array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn(array $root): array => $root['affectedRows'] ?? [],
            ],
            'ProfilePostMedia' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.ProfilePostMedia Resolvers');
                    return $root['postid'] ?? '';
                },
                'title' => fn(array $root): string => $root['title'] ?? '',
                'contenttype' => fn(array $root): string => $root['contenttype'] ?? '',
                'media' => fn(array $root): string => $root['media'] ?? '',
                'createdat' => fn(array $root): string => $root['createdat'] ?? '',
            ],
            'BlockedUsers' => [
                'iBlocked' => function (array $root): array {
                    $this->logger->debug('Query.BlockedUsers Resolvers');
                    return $root['iBlocked'] ?? [];
                },
                'blockedBy' => fn(array $root): array => $root['blockedBy'] ?? [],
            ],
            'BlockedUser' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Type.BlockedUser.userid');
                    return $root['uid'] ?? '';
                },
                'img' => fn(array $root): string => $root['img'] ?? '',
                'username' => fn(array $root): string => $root['username'] ?? '',
                'slug' => fn(array $root): int => $root['slug'] ?? 0,
                'visibilityStatus' => fn(array $root): string => strtoupper($root['visibility_status'] ?? 'NORMAL'),
                'hasActiveReports' => function (array $root): bool {
                    $reports = $root['reports'] ?? 0;
                    return (int)$reports > 0;
                },
                'isHiddenForUsers' => fn(array $root): bool => isset($root['isHiddenForUsers']) ? (bool)$root['isHiddenForUsers'] : false,
            ],
            'BlockedUsersResponse' => [
                'meta' => fn(array $root): array => $this->metaBuilder->build($root),
                'status' => function (array $root): string {
                    $this->logger->debug('Query.BlockedUsersResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn(array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn(array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn(array $root): array => $root['affectedRows'] ?? [],
            ],
            'FollowRelations' => [
                'followers' => function (array $root): array {
                    $this->logger->debug('Query.FollowRelations Resolvers');
                    return $root['followers'] ?? [];
                },
                'following' => fn(array $root): array => $root['following'] ?? [],
            ],
            'FollowRelationsResponse' => [
                'meta' => fn(array $root): array => $this->metaBuilder->build($root),
                'status' => function (array $root): string {
                    $this->logger->debug('Query.FollowRelationsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn(array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn(array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn(array $root): array => $root['affectedRows'] ?? [],
            ],
            'UserFriendsResponse' => [
                'meta' => fn(array $root): array => $this->metaBuilder->build($root),
                'status' => function (array $root): string {
                    $this->logger->debug('Query.UserFriendsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn(array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn(array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn(array $root): array => $root['affectedRows'] ?? [],
            ],
            'BasicUserInfoResponse' => [
                'meta' => fn(array $root): array => $this->metaBuilder->build($root),
                'status' => function (array $root): string {
                    $this->logger->debug('Query.BasicUserInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn(array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn(array $root): array => $root['affectedRows'] ?? [],
            ],
            'FollowStatusResponse' => [
                'meta' => fn(array $root): array => $this->metaBuilder->build($root),
                'status' => function (array $root): string {
                    $this->logger->debug('Query.FollowStatusResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn(array $root): string => $root['ResponseCode'] ?? "",
                'isfollowing' => fn(array $root): bool => $root['isfollowing'] ?? false,
            ],
            'UserInfo' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Query.UserInfo Resolvers');
                    return $root['userid'] ?? '';
                },
                'liquidity' => fn(array $root): float => $root['liquidity'] ?? 0.0,
                'isfollowed' => fn(array $root): bool => $root['isfollowed'] ?? false,
                'isfollowing' => fn(array $root): bool => $root['isfollowing'] ?? false,
                'isreported' => fn(array $root): bool => $root['isreported'] ?? false,
                'amountreports' => fn(array $root): int => $root['reports'] ?? 0,
                'amountposts' => fn(array $root): int => $root['amountposts'] ?? 0,
                'amountblocked' => fn(array $root): int => $root['amountblocked'] ?? 0,
                'amountfollowed' => fn(array $root): int => $root['amountfollowed'] ?? 0,
                'amountfollower' => fn(array $root): int => $root['amountfollower'] ?? 0,
                'amountfriends' => fn(array $root): int => $root['amountfriends'] ?? 0,
                'invited' => fn(array $root): string => $root['invited'] ?? '',
                'updatedat' => fn(array $root): string => $root['updatedat'] ?? '',
                'userPreferences' => fn(array $root): array => $root['userPreferences'] ?? [],
            ],
        ];
    }
}

