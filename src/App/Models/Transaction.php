<?php

namespace Fawaz\App\Models;


use DateTime;
use Fawaz\App\ValidationException;
use Fawaz\Filter\PeerInputFilter;

class Transaction
{
    protected string $transactionId;
    protected string $transUniqueId;
    protected string $senderId;
    protected string|null $recipientId;
    protected string $transactionType;
    protected string $tokenAmount;
    protected $transferAction;
    protected ?string $message;
    protected ?string $createdat;

    /**
     * Assign Notification object while instantiated
     */
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        $this->transactionId = $data['transactionId'] ?? self::generateUUID();
        $this->transUniqueId = $data['transUniqueId'] ?? null;
        $this->senderId = $data['senderId'] ?? null;
        $this->recipientId = $data['recipientId'] ?? null;
        $this->transactionType = $data['transactionType'] ?? null;
        $this->tokenAmount = $data['tokenAmount'] ?? null;
        $this->transferAction = $data['transferAction'] ?? 'DEDUCT';
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
            'transactionId' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
            ],
            'transUniqueId' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'senderId' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'recipientId' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
            ],
            'transactionType' => [
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
            'tokenAmount' => [
                'required' => true
            ],
            'transferAction' => [
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
     * Getter and Setter methods for transactionId
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }
    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    
    /**
     * Getter and Setter methods for transUniqueId
     */
    public function getTransUniqueId(): string
    {
        return $this->transUniqueId;
    }
    public function setTransUniqueId(string $transUniqueId): void
    {
        $this->transUniqueId = $transUniqueId;
    }


    /**
     * Getter and Setter methods for senderId
     */
    public function getSenderId(): string
    {
        return $this->senderId;
    }
    public function setSenderId(string $senderId): void
    {
        $this->senderId = $senderId;
    }


    /**
     * Getter and Setter methods for recipientId
     */
    public function getRecipientId(): string|null
    {
        return $this->recipientId;
    }
    public function setRecipientId(string $recipientId): void
    {
        $this->recipientId = $recipientId;
    }


    /**
     * Getter and Setter methods for transactionType
     */
    public function getTransactionType(): string|null
    {
        return $this->transactionType;
    }
    public function setTransactionType(string $transactionType): void
    {
        $this->transactionType = $transactionType;
    }

    
    /**
     * Getter and Setter methods for tokenAmount
     */
    public function getTokenAmount(): string
    {
        return $this->tokenAmount;
    }
    public function setTokenAmount(string $tokenAmount): void
    {
        $this->tokenAmount = $tokenAmount;
    }
    
    
    /**
     * Getter and Setter methods for transferAction
     */
    public function getTransferAction(): string
    {
        return $this->transferAction;
    }
    public function setTransferAction(string $transferAction): void
    {
        $this->transferAction = $transferAction;
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