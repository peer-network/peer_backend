<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Replacers;

use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Specs\Specification;

class ContentReplacer
{
    /**
     * Returns a new Profile object with placeholdered content, leaving the original unchanged.
     */
    public static function placeholderProfile(
        ProfileReplaceable $profile,
        array $specs,
    ) {
        $replacerSpecs = array_values(
            array_filter(
                array_map(
                    fn (Specification $spec) => $spec->toReplacer($profile),
                    $specs
                ),
                fn ($v) => null !== $v
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

        if ('illegal' !== $profile->visibilityStatus()) {
            $profile->setVisibilityStatus($pattern->visibilityStatus());
        }
    }

    public static function placeholderPost(
        PostReplaceable $post,
        array $specs,
    ) {
        $replacerSpecs = array_values(
            array_filter(
                array_map(
                    fn (Specification $spec) => $spec->toReplacer($post),
                    $specs
                ),
                fn ($v) => null !== $v
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

        if ($pattern->postCover()) {
            $post->setCover($pattern->postCover());
        }

        if ($pattern->postContentType()) {
            $post->setContentType($pattern->postContentType());
        }

        if ('illegal' !== $post->visibilityStatus()) {
            $post->setVisibilityStatus($pattern->visibilityStatus());
        }
    }

    public static function placeholderComments(
        CommentReplaceable $comment,
        array $specs,
    ) {
        $replacerSpecs = array_values(
            array_filter(
                array_map(
                    fn (Specification $spec) => $spec->toReplacer($comment),
                    $specs
                ),
                fn ($v) => null !== $v
            )
        );

        if (empty($replacerSpecs)) {
            return;
        }
        $pattern = $replacerSpecs[0];

        if ($pattern->commentContent()) {
            $comment->setContent($pattern->commentContent());
        }

        if ('illegal' !== $comment->visibilityStatus()) {
            $comment->setVisibilityStatus($pattern->visibilityStatus());
        }
    }
}
