<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Replacers;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;

class ContentReplacer
{
    /**
     * Returns a new Profile object with placeholdered content, leaving the original unchanged.
     */
    public static function placeholderProfile(
        ProfileReplaceable $profile, 
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
        if ($pattern->userBiography()) {
            $profile->setBiography($pattern->userBiography());
        }
        if ($pattern->username()) {
            $profile->setName($pattern->username());
        }
        if ($pattern->profilePicturePath()) {
            $profile->setImg($pattern->profilePicturePath());
        }
        if ($profile->visibilityStatus() === "normal") {
            $profile->setVisibilityStatus($pattern->visibilityStatus());
        }
    }

    public static function placeholderPost(
        PostReplaceable $post, 
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

        if ($pattern->postTitle()) {
            $post->setTitle($pattern->postTitle());
        }
        if ($pattern->postDescription()) {
            $post->setDescription($pattern->postDescription());
        }
        if ($pattern->postMedia()) {
            $post->setMedia($pattern->postMedia());
        }
        if ($post->visibilityStatus() === "normal") {
            $post->setVisibilityStatus($pattern->visibilityStatus());
        }
    }

    public static function placeholderComments(
        CommentReplaceable $comment, 
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

        if ($pattern->commentContent()) {
            $comment->setContent($pattern->commentContent());
        }
        if ($comment->visibilityStatus() === "normal") {
            $comment->setVisibilityStatus($pattern->visibilityStatus());
        }
    }
}
