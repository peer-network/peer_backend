<?php

namespace Fawaz\App\Specs;

use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;


/// add u and ui tables and currentuserid
final class IllegalContentFilteringSpecificationFactory {
    public static function build(
        ContentType $type, 
        ContentFilteringAction $action,
        string $targetId,
    ): ?SpecificationSQLData {
        $paramsToPrepare = [];
        $whereClauses = [];

        switch ($action) {
            case ContentFilteringAction::replaceWithPlaceholder:
                return null;
            case ContentFilteringAction::hideContent:
                switch ($type) {
                case ContentType::user:
                    $whereClauses[] = "
                    NOT EXISTS (
                        SELECT 1
                        FROM users IllegalContentFiltering_users
                        WHERE 
                            IllegalContentFiltering_users.userid = :targetId
                        AND 
                            IllegalContentFiltering_users.visibility_status = 'illegal'
                    )";
                    break;
                case ContentType::post:
                    $whereClauses[] = "
                    NOT EXISTS (
                        SELECT 1
                        FROM posts IllegalContentFiltering_posts
                        WHERE 
                            IllegalContentFiltering_posts.userid = :targetId
                        AND 
                            IllegalContentFiltering_posts.visibility_status = 'illegal'
                    )";
                    break;
                case ContentType::comment:
                    $whereClauses[] = "
                    NOT EXISTS (
                        SELECT 1
                        FROM comments IllegalContentFiltering_comments
                        WHERE 
                            IllegalContentFiltering_comments.userid = :targetId
                        AND 
                            IllegalContentFiltering_comments.visibility_status = 'illegal'
                    )";
                    break;
                default:
                    // Unsupported content type for this action
                    return null;
                }

                $paramsToPrepare["targetId"] = $targetId;
                break;
            default:
                // Unsupported action
                return null;
        }
        return new SpecificationSQLData(
            $whereClauses, 
            $paramsToPrepare
        );
    }
}
