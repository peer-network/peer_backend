<?php

declare(strict_types=1);

namespace Fawaz\config;

enum ContentReplacementPattern: string
{
    case normal  = 'normal';
    case deleted = 'deleted';
    case hidden  = 'hidden';
    case illegal = 'illegal';

    // we are setting visibilityStatus as HIDDEN for the case when contetnt is NORMAL but activeReports > X(normally 5)
    public function visibilityStatus(): ?string
    {
        return match ($this) {
            self::normal  => 'normal',
            self::hidden  => 'hidden',
            self::deleted => 'normal',
            self::illegal => null,
        };
    }

    public function postTitle(): ?string
    {
        return match ($this) {
            self::normal  => null,
            self::hidden  => null,
            self::deleted => 'this post is deleted',
            self::illegal => 'this post is illegal',
        };
    }

    public function postDescription(): ?string
    {
        return match ($this) {
            self::normal  => null,
            self::hidden  => null,
            self::deleted => '',
            self::illegal => '',
        };
    }

    public function postMedia(): ?string
    {
        return match ($this) {
            self::normal  => null,
            self::hidden  => null,
            self::deleted => null,
            self::illegal => '[{"path":"\\/image\\/default.jpg","options":{"size":"69.89 KB","resolution":"1500x1500"}}]',
        };
    }

    public function postCover(): ?string
    {
        return match ($this) {
            self::normal  => null,
            self::hidden  => null,
            self::deleted => null,
            self::illegal => '[{"path":"\\/image\\/default.jpg","options":{"size":"69.89 KB","resolution":"1500x1500"}}]',
        };
    }

    public function postContentType(): ?string
    {
        return match ($this) {
            self::normal  => null,
            self::hidden  => null,
            self::deleted => null,
            self::illegal => 'image',
        };
    }

    public function commentContent(): ?string
    {
        return match ($this) {
            self::normal  => null,
            self::hidden  => null,
            self::deleted => 'this comment is deleted',
            self::illegal => 'this comment is illegal',
        };
    }

    public function username(): ?string
    {
        return match ($this) {
            self::normal  => null,
            self::hidden  => null,
            self::deleted => 'Deleted_Account',
            self::illegal => 'illegal_account',
        };
    }

    public function userBiography(): ?string
    {
        return match ($this) {
            self::normal  => null,
            self::hidden  => null,
            self::deleted => '/userData/00000000-0000-0000-0000-000000000000.txt',
            self::illegal => '/userData/00000000-0000-0000-0000-000000000000.txt',
        };
    }

    public function profilePicturePath(): ?string
    {
        return match ($this) {
            self::normal  => null,
            self::hidden  => null,
            self::deleted => '[{"path":"\\/image\\/default.jpg","options":{"size":"69.89 KB","resolution":"1500x1500"}}]',
            self::illegal => '[{"path":"\\/image\\/default.jpg","options":{"size":"69.89 KB","resolution":"1500x1500"}}]',
        };
    }
}
