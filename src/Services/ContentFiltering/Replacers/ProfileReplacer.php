<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Replacers;
use Fawaz\App\Profile;
use Fawaz\Services\ContentFiltering\ContentReplacementPattern;

class ProfileReplacer
{
    /**
     * Returns a new Profile object with placeholdered content, leaving the original unchanged.
     */
    public static function replaceProfile(Profile $profile, ContentReplacementPattern $pattern): Profile
    {
        $data = $profile->getArrayCopy();
        $data['username'] = $pattern->username();
        $data['img'] = $pattern->profilePicturePath();
        $data['biography'] = $pattern->userBiography();

        return new Profile($data, [], false);
    }
}
