<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;

// Still needs to be checked
class SystemUser implements ProfileReplaceable
{
    protected string $uid;
    protected string $username;
    protected int $status;
    protected int $slug;
    protected int $roles_mask;
    protected string $img;
    protected string $biography;
    protected string $updatedat;
    protected ?int $activeReports = null;
    protected string $visibilityStatus;
    protected string $visibilityStatusForUser;



    // Array Copy methods
    public function getArrayProfile(): array
    {
        $att = [
            'uid' => $this->uid,
            'username' => $this->username,
            'status' => $this->status,
            'slug' => $this->slug,
            'img' => $this->img,
        ];
        return $att;
    }

    public function getName(): string
    {
        return $this->username;
    }

    public function setName(string $name): void
    {
        $this->username = $name;
    }


    public function getStatus(): int
    {
        return $this->status;
    }

    // ProfileReplaceable: roles mask accessor with expected name
    public function getRolesmask(): int
    {
        return (int)$this->roles_mask;
    }


    public function getImg(): ?string
    {
        return $this->img;
    }

    public function setImg(?string $img): void
    {
        $this->img = $img;
    }

    public function getBiography(): ?string
    {
        return $this->biography;
    }

    public function setBiography(?string $biography): void
    {
        $this->biography = $biography;
    }


    public function visibilityStatus(): string
    {
        return '';
    }


    public function visibilityStatusForUser(): string
    {
        return '';
    }

    public function setVisibilityStatus(string $status): void
    {

    }

    public function getActiveReports(): ?int
    {
        return 0;
    }

    public function getUserId(): string
    {
        return $this->uid;
    }
}
