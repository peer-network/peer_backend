<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class UserInfo
{
    protected string $userid;
    protected float $liquidity;
    protected int $amountposts;
    protected int $amountblocked;
    protected int $amountfollower;
    protected int $amountfollowed;
    protected int $isprivate;
    protected ?string $invited;
    protected string $updatedat;

    // Constructor
    public function __construct(array $data)
    {
        $data = $this->validate($data);

        $this->userid = $data['userid'] ?? '';
        $this->liquidity = $data['liquidity'] ?? 0.0;
        $this->amountposts = $data['amountposts'] ?? 0;
        $this->amountblocked = $data['amountblocked'] ?? 0;
        $this->amountfollower = $data['amountfollower'] ?? 0;
        $this->amountfollowed = $data['amountfollowed'] ?? 0;
        $this->isprivate = $data['isprivate'] ?? 0;
        $this->invited = $data['invited'] ?? null;
        $this->updatedat = $data['updatedat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'userid' => $this->userid,
            'liquidity' => $this->liquidity,
            'amountposts' => $this->amountposts,
            'amountblocked' => $this->amountblocked,
            'amountfollower' => $this->amountfollower,
            'amountfollowed' => $this->amountfollowed,
            'isprivate' => $this->isprivate,
            'invited' => $this->invited,
            'updatedat' => $this->updatedat,
        ];
        return $att;
    }

    // Getter and Setter methods
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

    public function getUpdatedAt(): ?string
    {
        return $this->updatedat;
    }

    public function setUpdatedAt(): void
    {
        $this->updatedat = (new DateTime())->format('Y-m-d H:i:s.u');
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
            $errorMessages[] = "Validation errors for $field";
            foreach ($errors as $error) {
                $errorMessages[] = ": $error";
            }
            $errorMessageString = implode("", $errorMessages);
            
            throw new ValidationException($errorMessageString);
        }
    }

    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $specification = [
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'liquidity' => [
                'required' => false,
                'filters' => [['name' => 'FloatSanitize']],
                'validators' => [
                    ['name' => 'ValidateFloat', 'options' => ['min' => -18250000, 'max' => 18250000]],
                ],
            ],
            'amountposts' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'amountblocked' => [
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
            'isprivate' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => 0, 'max' => 1]],
                ],
            ],
            'invited' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
            ],
            'updatedat' => [
                'required' => true,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                    ['name' => 'LessThan', 'options' => ['max' => (new DateTime())->format('Y-m-d H:i:s.u'), 'inclusive' => true]],
                ],
            ],
        ];

        if ($elements) {
            $specification = array_filter($specification, fn($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return (new PeerInputFilter($specification));
    }
}
