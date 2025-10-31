<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Replacers;
use Fawaz\App\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;

class ContentReplacer
{
    /**
     * Returns a new Profile object with placeholdered content, leaving the original unchanged.
     */
    public static function placeholderProfile(
        ProfileReplaceable &$profile, 
        array $specs
    ){
        $replacerSpecs = array_values(
        array_filter(
            array_map(
                fn(Specification $spec) => $spec->toReplacer($profile), $specs
            ),
            fn($v) => $v !== null
            )
        );
        
        if (empty($replacerSpecs)) {
            return;
        }
        $pattern = $replacerSpecs[0];

        $profile->setBiography($pattern->userBiography());
        $profile->setName($pattern->username());
        $profile->setImg($pattern->profilePicturePath());
    }

    public static function placeholderPost(
        PostReplaceable &$post, 
        array $specs
    ){
        $replacerSpecs = array_values(
        array_filter(
            array_map(
                fn(Specification $spec) => $spec->toReplacer($post), $specs
            ),
            fn($v) => $v !== null
            )
        );
        if (empty($replacerSpecs)) {
            return;
        }
        $pattern = $replacerSpecs[0];

        $post->setTitle($pattern->postTitle());
        $post->setDescription($pattern->postDescription());
        $post->setMedia($pattern->postMedia());
    }

    public static function placeholderComments(
        CommentReplaceable &$comment, 
        array $specs
    ){
        $replacerSpecs = array_values(
        array_filter(
            array_map(
                fn(Specification $spec) => $spec->toReplacer($comment), $specs
            ),
            fn($v) => $v !== null
            )
        );
        if (empty($replacerSpecs)) {
            return;
        }
        $pattern = $replacerSpecs[0];

        $comment->setContent($pattern->commentContent());
    }
}
