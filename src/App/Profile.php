<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\Filter\PeerInputFilter;
use Fawaz\config\constants\ConstantsConfig;

class Profile
{
    protected string $uid;
    protected string $username;
    protected int $status;
    protected int $slug;
    protected int $rolesmask;
    protected ?string $img;
    protected ?string $biography;
    protected ?int $amountposts;
    protected ?int $amounttrending;
    protected ?bool $isfollowed;
    protected ?bool $isfollowing;
    protected ?int $amountfollower;
    protected ?int $amountfollowed;
    protected ?int $amountfriends;
    protected ?int $amountblocked;
    protected ?int $amountreports;
    protected ?int $reports;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->uid = $data['uid'] ?? '';
        $this->username = $data['username'] ?? '';
        $this->status = $data['status'] ?? 0;
        $this->slug = $data['slug'] ?? 0;
        $this->rolesmask = $data['roles_mask'] ?? 0;
        $this->img = $data['img'] ?? '';
        $this->biography = $data['biography'] ?? '';
        $this->amountposts = $data['amountposts'] ?? 0;
        $this->amounttrending = $data['amounttrending'] ?? 0;
        $this->isfollowed = $data['isfollowed'] ?? false;
        $this->isfollowing = $data['isfollowing'] ?? false;
        $this->amountfollower = $data['amountfollower'] ?? 0;
        $this->amountfollowed = $data['amountfollowed'] ?? 0;
        $this->amountfriends = $data['amountfriends'] ?? 0;
        $this->amountblocked = $data['amountblocked'] ?? 0;
        $this->amountreports = $data['amountreports'] ?? 0;
        $this->reports = $data['user_reports'] ?? 0;

        if (($this->status == 6)) {
            $this->username = 'Deleted_Account';
            $this->img = '/profile/2e855a7b-2b88-47bc-b4dd-e110c14e9acf.jpeg';
            $this->biography = '/userData/fb08b055-511a-4f92-8bb4-eb8da9ddf746.txt';
        }
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'uid' => $this->uid,
            'username' => $this->username,
            'status' => $this->status,
            'slug' => $this->slug,
            'img' => $this->img,
            'roles_mask' => $this->rolesmask,
            'biography' => $this->biography,
            'amountposts' => $this->amountposts,
            'amounttrending' => $this->amounttrending,
            'isfollowed' => $this->isfollowed,
            'isfollowing' => $this->isfollowing,
            'amountfollower' => $this->amountfollower,
            'amountfollowed' => $this->amountfollowed,
            'amountfriends' => $this->amountfriends,
            'amountblocked' => $this->amountblocked,
            'amountreports' => $this->amountreports,
            'user_reports' => $this->reports
        ];
        return $att;
    }

    // Getter and Setter
    public function getUserId(): string
    {
        return $this->uid;
    }

    public function setUserId(string $uid): void
    {
        $this->uid = $uid;
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

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getSlug(): int
    {
        return $this->slug;
    }

    public function setSlug(int $slug): void
    {
        $this->slug = $slug;
    }

    public function getRolesmask(): int
    {
        return $this->rolesmask;
    }

    public function setRolesmask(int $rolesmask): void
    {
        $this->rolesmask = $rolesmask;
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

    public function getAmountposts(): ?int
    {
        return $this->amountposts;
    }

    public function setAmountposts(?int $amountposts): void
    {
        $this->amountposts = $amountposts;
    }

    public function getAmounttrending(): ?int
    {
        return $this->amounttrending;
    }

    public function setAmounttrending(?int $amounttrending): void
    {
        $this->amounttrending = $amounttrending;
    }

    public function getIsfollowed(): ?bool
    {
        return $this->isfollowed;
    }

    public function setIsfollowed(?bool $isfollowed): void
    {
        $this->isfollowed = $isfollowed;
    }

    public function getIsfollowing(): ?bool
    {
        return $this->isfollowing;
    }

    public function setIsfollowing(?bool $isfollowing): void
    {
        $this->isfollowing = $isfollowing;
    }

    public function getAmountfollower(): ?int
    {
        return $this->amountfollower;
    }

    public function setAmountfollower(?int $amountfollower): void
    {
        $this->amountfollower = $amountfollower;
    }

    public function getAmountfollowed(): ?int
    {
        return $this->amountfollowed;
    }

    public function setAmountfollowed(?int $amountfollowed): void
    {
        $this->amountfollowed = $amountfollowed;
    }

    public function getAmountfriends(): ?int
    {
        return $this->amountfriends;
    }

    public function setAmountfriends(?int $amountfriends): void
    {
        $this->amountfriends = $amountfriends;
    }

    public function getAmountblocked(): ?int
    {
        return $this->amountblocked;
    }

    public function setAmountblocked(?int $amountblocked): void
    {
        $this->amountblocked = $amountblocked;
    }

    public function getAmountreports(): ?int
    {
        return $this->amountreports;
    }

    public function setAmountreports(?int $amountreports): void
    {
        $this->amountreports = $amountreports;
    }

    public function getReports(): ?int
    {
        return $this->reports;
    }

    public function setReports(?int $reports): void
    {
        $this->reports = $reports;
    }

    // Validation and Array Filtering methods
    public function validate(array $data, array $elements = []): array|false
    {
        $inputFilter = $this->createInputFilter($elements);
        $inputFilter->setData($data);

        if ($inputFilter->isValid()) {
            return $inputFilter->getValues();
        }

        $validationErrors = $inputFilter->getMessages();

        foreach ($validationErrors as $errors) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error;
            }
            $errorMessageString = implode("", $errorMessages);

            throw new ValidationException($errorMessageString);
        }
        return false;
    }

    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $userConfig = ConstantsConfig::user();
        $specification = [
            'uid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'username' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags']],
                'validators' => [
                    ['name' => 'validateUsername'],
                ],
            ],
            'status' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => 0, 'max' => 10]],
                ],
            ],
            'slug' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => [
                        'min' => $userConfig['SLUG']['MIN_LENGTH'],
                        'max' => $userConfig['SLUG']['MAX_LENGTH']
                        ]],
                ],
            ],
            'img' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => $userConfig['IMAGE']['MIN_LENGTH'],
                        'max' => $userConfig['IMAGE']['MAX_LENGTH'],
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'biography' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => $userConfig['BIOGRAPHY']['MIN_LENGTH'],
                        'max' => $userConfig['BIOGRAPHY']['MAX_LENGTH'],
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'amountposts' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'amounttrending' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'isfollowed' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
            'isfollowing' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
            'amountfollower' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'amountfollowed' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'amountfriends' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'amountblocked' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'reports' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
        ];

        if ($elements) {
            $specification = array_filter($specification, fn ($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return (new PeerInputFilter($specification));
    }
}
