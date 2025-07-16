<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class UserInfo
{
    protected string $userid;
    protected float $liquidity;
    protected int $amountposts;
    protected int $amountfollower;
    protected int $amountfollowed;
    protected int $amountfriends;
    protected int $amountblocked;
    protected int $isprivate;
    protected int $reports;
    protected ?string $invited;
    protected ?string $phone;
    protected ?string $pkey;
    protected string $updatedat;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->userid = $data['userid'] ?? '';
        $this->liquidity = $data['liquidity'] ?? 0.0;
        $this->amountposts = $data['amountposts'] ?? 0;
        $this->amountfollower = $data['amountfollower'] ?? 0;
        $this->amountfollowed = $data['amountfollowed'] ?? 0;
        $this->amountfriends = $data['amountfriends'] ?? 0;
        $this->amountblocked = $data['amountblocked'] ?? 0;
        $this->isprivate = $data['isprivate'] ?? 0;
        $this->reports = $data['reports'] ?? 0;
        $this->invited = $data['invited'] ?? null;
        $this->phone = $data['phone'] ?? null;
        $this->pkey = $data['pkey'] ?? null;
        $this->updatedat = $data['updatedat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'userid' => $this->userid,
            'liquidity' => $this->liquidity,
            'amountposts' => $this->amountposts,
            'amountfollower' => $this->amountfollower,
            'amountfollowed' => $this->amountfollowed,
            'amountfriends' => $this->amountfriends,
            'amountblocked' => $this->amountblocked,
            'isprivate' => $this->isprivate,
            'reports' => $this->reports,
            'invited' => $this->invited,
            'phone' => $this->phone,
            'pkey' => $this->pkey,
            'updatedat' => $this->updatedat,
        ];
        return $att;
    }

    // Getter and Setter
    public function getUserId(): string
    {
        return $this->userid;
    }
    public function getLiquidity(): float
    {
        return $this->liquidity;
    }

    public function setLiquidity(float $liquidity): void
    {
        $this->liquidity = $liquidity;
    }

    public function getAmountPosts(): int
    {
        return $this->amountposts;
    }

    public function setAmountPosts(int $amountposts): void
    {
        $this->amountposts = $amountposts;
    }

    public function getAmountBlocked(): int
    {
        return $this->amountblocked;
    }

    public function setAmountBlocked(int $amountblocked): void
    {
        $this->amountblocked = $amountblocked;
    }

    public function getAmountFollowes(): int
    {
        return $this->amountfollower;
    }

    public function setAmountFollowers(int $amountfollower): void
    {
        $this->amountfollower = $amountfollower;
    }

    public function getAmountFollowed(): int
    {
        return $this->amountfollowed;
    }

    public function setAmountFollowed(int $amountfollowed): void
    {
        $this->amountfollowed = $amountfollowed;
    }

    public function getAmountFriends(): int|null
    {
        return $this->amountfriends;
    }

    public function setAmountFriends(int $amountfriends): void
    {
        $this->amountfriends = $amountfriends;
    }

    public function getIsPrivate(): int
    {
        return $this->isprivate;
    }

    public function setIsPrivate(int $isprivate): void
    {
        $this->isprivate = $isprivate;
    }

    public function getInvited(): string
    {
        return $this->invited;
    }

    public function setInvited(string $invited): void
    {
        $this->invited = $invited;
    }

    public function getpKey(): string
    {
        return $this->pkey;
    }

    public function setpKey(string $pkey): void
    {
        $this->pkey = $pkey;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedat;
    }

    public function setUpdatedAt(): void
    {
        $this->updatedat = (new DateTime())->format('Y-m-d H:i:s.u');
    }

    public function getReports(): int
    {
        return $this->reports;
    }

    public function setReports(int $reports): void
    {
        $this->reports = $reports;
    }
    
    // Validation and Array Filtering methods
    public function validate(array $data, array $elements = []): array
    {
        $inputFilter = $this->createInputFilter($elements);
        $inputFilter->setData($data);

        if ($inputFilter->isValid()) {
            return $inputFilter->getValues();
        }

        $validationErrors = $inputFilter->getMessages();

        foreach ($validationErrors as $field => $errors) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error;
            }
            $errorMessageString = implode("", $errorMessages);
            
            throw new ValidationException($errorMessageString);
        }
        return [];
    }

    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $userConfig = constants()::user();
        $specification = [
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'liquidity' => [
                'required' => false,
                'filters' => [['name' => 'FloatSanitize']],
                'validators' => [
                    ['name' => 'ValidateFloat', 'options' => [
                        'min' => $userConfig['LIQUIDITY']['MIN_LENGTH'], 
                        'max' => $userConfig['LIQUIDITY']['MAX_LENGTH']
                        ]],
                ],
            ],
            'amountposts' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
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
            'isprivate' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => 0, 'max' => 1]],
                ],
            ],
            'reports' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'invited' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
            ],
            'phone' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'validatePhoneNumber'],
                ],
            ],
            'pkey' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'validatePkey'],
                ],
            ],
            'updatedat' => [
                'required' => true,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                ],
            ],
        ];

        if ($elements) {
            $specification = array_filter($specification, fn($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return (new PeerInputFilter($specification));
    }
}
