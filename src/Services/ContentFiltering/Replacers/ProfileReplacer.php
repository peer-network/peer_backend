<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Replacers;
use Fawaz\App\Specs\Specification;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;

class ProfileReplacer
{
    /**
     * Returns a new Profile object with placeholdered content, leaving the original unchanged.
     */
    public static function placeholderProfile(ProfileReplaceable &$profile, array $specs){

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
}
