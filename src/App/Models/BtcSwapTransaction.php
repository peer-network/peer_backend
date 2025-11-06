<?php

namespace Fawaz\App\Models;

use DateTime;
use Fawaz\App\ValidationException;
use Fawaz\Filter\PeerInputFilter;

class BtcSwapTransaction
{
    protected string $swapId;
    protected string $operationid;
    protected string $userId;
    protected string $btcAddress;
    protected string $transactiontype;
    protected string $tokenamount;
    protected ?string $btcAmount;
    protected ?string $message;
    protected ?string $status;
    protected ?string $createdat;

    /**
     * Assign Notification object while instantiated
     */
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        $this->swapId = $data['swapId'] ?? self::generateUUID();
        $this->operationid = $data['operationid'] ?? null;
        $this->userId = $data['userId'] ?? null;
        $this->btcAddress = $data['btcAddress'] ?? null;
        $this->transactiontype = $data['transactiontype'] ?? null;
        $this->tokenamount = $data['tokenamount'] ?? '0';
        $this->btcAmount = $data['btcAmount'] ?? '0.0';
        $this->status = $data['status'] ?? 'PENDING';
        $this->message = $data['message'] ?? null;
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');

        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }
    }
    
    
    /**
     * Define Input filter
     */   
    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $specification = [
            'swapId' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
            ],
            'operationid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'userId' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'btcAddress' => [
                'required' => true,
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 2,
                        'max' => 256,
                        'errorCode' => 30210
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'transactiontype' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 2,
                        'max' => 63,
                        'errorCode' => 30210
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'tokenamount' => [
                'required' => true,
            ],
            'btcAmount' => [
                'required' => true,
            ],
            'status' => [
                'required' => false,
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 2,
                        'max' => 63,
                        'errorCode' => 30210
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'message' => [
                'required' => false,
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 0,
                        'max' => 200,
                        'errorCode' => 30210
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'createdat' => [
                'required' => false,
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

    /**
     * Apply Input filter
     */  
    public function validate(array $data, array $elements = []): array|false
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
        return false;
    }
    
    /**
     * Generate UUID
     */ 
    private static function generateUUID(): string
    {
        return \sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            \mt_rand(0, 0xffff), \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0x0fff) | 0x4000,
            \mt_rand(0, 0x3fff) | 0x8000,
            \mt_rand(0, 0xffff), \mt_rand(0, 0xffff), \mt_rand(0, 0xffff)
        );
    }

    /**
     * Getter and Setter methods for swapId
     */
    public function getSwapId(): string
    {
        return $this->swapId;
    }
    public function setSwapId(string $swapId): void
    {
        $this->swapId = $swapId;
    }

    
    /**
     * Getter and Setter methods for operationid
     */
    public function getTransUniqueId(): string
    {
        return $this->operationid;
    }
    public function setTransUniqueId(string $operationid): void
    {
        $this->operationid = $operationid;
    }


    /**
     * Getter and Setter methods for userId
     */
    public function getUserId(): string
    {
        return $this->userId;
    }
    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }


    
    /**
     * Getter and Setter methods for btcAddress
     */
    public function getBtcAddress(): string
    {
        return $this->btcAddress;
    }
    public function setBtcAddress(string $btcAddress): void
    {
        $this->btcAddress = $btcAddress;
    }


    /**
     * Getter and Setter methods for status
     */
    public function getStatus(): string|null
    {
        return $this->status;
    }
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }


    /**
     * Getter and Setter methods for transactiontype
     */
    public function getTransactionType(): string|null
    {
        return $this->transactiontype;
    }
    public function setTransactionType(string $transactiontype): void
    {
        $this->transactiontype = $transactiontype;
    }

    
    /**
     * Getter and Setter methods for tokenamount
     */
    public function getTokenAmount(): string
    {
        return $this->tokenamount;
    }
    public function setTokenAmount(string $tokenamount): void
    {
        $this->tokenamount = $tokenamount;
    }
    
        
    /**
     * Getter and Setter methods for btcAmount
     */
    public function getBtcAmount(): string
    {
        return $this->btcAmount;
    }
    public function setBtcAmount(string $btcAmount): void
    {
        $this->btcAmount = $btcAmount;
    }
    

    /**
     * Getter and Setter methods for message
     */
    public function getMessage(): string|null
    {
        return $this->message;
    }
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    /**
     * Getter and Setter methods for createdat
     */
    public function getCreatedat(): string
    {
        return $this->createdat;
    }
    public function setCreatedat(string $createdat): void
    {
        $this->createdat = $createdat;
    }

}