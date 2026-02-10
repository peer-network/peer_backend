<?php

declare(strict_types=1);

namespace Fawaz\App\Models;

use DateTime;
use Fawaz\App\ValidationException;
use Fawaz\Filter\PeerInputFilter;
use Fawaz\Services\ContentFiltering\Capabilities\HasWalletId;
use Fawaz\Utils\ResponseHelper;

class MintAccount implements HasWalletId
{
    use ResponseHelper;

    protected string $accountid;
    protected float $initial_balance;
    protected float $current_balance;
    protected string $createdat;
    protected string $updatedat;

    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        $now = (new DateTime())->format('Y-m-d H:i:s.u');
        $this->accountid = $data['accountid'];
        $this->initial_balance = (float)$data['initial_balance'];
        $this->current_balance = (float)$data['current_balance'];
        $this->createdat = $data['createdat'] ?? $now;
        $this->updatedat = $data['updatedat'] ?? $now;

        if ($validate && !empty($data)) {
            $this->validate($data, $elements);
        }
    }

    public function getArrayCopy(): array
    {
        return [
            'accountid' => $this->accountid,
            'initial_balance' => $this->initial_balance,
            'current_balance' => $this->current_balance,
            'createdat' => $this->createdat,
            'updatedat' => $this->updatedat,
        ];
    }

    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $specification = [
            'accountid' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
            ],
            'initial_balance' => [
                'required' => true,
            ],
            'current_balance' => [
                'required' => true,
            ],
            'createdat' => [
                'required' => false,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                ],
            ],
            'updatedat' => [
                'required' => false,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                ],
            ],
        ];

        if ($elements) {
            $specification = array_filter($specification, fn ($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return new PeerInputFilter($specification);
    }

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
            $errorMessageString = implode('', $errorMessages);
            throw new ValidationException($errorMessageString);
        }
        return false;
    }

    public function getWalletId(): string
    {
        return $this->accountid;
    }

    public function getAccountId(): string
    {
        return $this->accountid;
    }

    public function getInitialBalance(): float
    {
        return $this->initial_balance;
    }

    public function getCurrentBalance(): float
    {
        return $this->current_balance;
    }

    public function getCreatedAt(): string
    {
        return $this->createdat;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedat;
    }
}
