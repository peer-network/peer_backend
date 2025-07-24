<?php

namespace Fawaz\App\Models;


use DateTime;
use Fawaz\App\ValidationException;
use Fawaz\Filter\PeerInputFilter;
use Fawaz\Utils\ResponseHelper;

class Transaction
{
    use ResponseHelper;

    protected string $transactionid;
    protected string $transuniqueid;
    protected string $senderid;
    protected string|null $recipientid;
    protected string $transactiontype;
    protected string $tokenamount;
    protected $transferaction;
    protected ?string $message;
    protected ?string $createdat;

    /**
     * Assign Notification object while instantiated
     */
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        $this->transactionid = $data['transactionid'] ?? self::generateUUID();
        $this->transuniqueid = $data['transuniqueid'] ?? null;
        $this->senderid = $data['senderid'] ?? null;
        $this->recipientid = $data['recipientid'] ?? null;
        $this->transactiontype = $data['transactiontype'] ?? null;
        $this->tokenamount = $data['tokenamount'] ?? null;
        $this->transferaction = $data['transferaction'] ?? 'DEDUCT';
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
            'transactionid' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
            ],
            'transuniqueid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'senderid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'recipientid' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
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
                'required' => true
            ],
            'transferaction' => [
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
     * Getter and Setter methods for transactionid
     */
    public function getTransactionId(): string
    {
        return $this->transactionid;
    }
    public function setTransactionId(string $transactionid): void
    {
        $this->transactionid = $transactionid;
    }

    
    /**
     * Getter and Setter methods for transuniqueid
     */
    public function getTransUniqueId(): string
    {
        return $this->transuniqueid;
    }
    public function setTransUniqueId(string $transuniqueid): void
    {
        $this->transuniqueid = $transuniqueid;
    }


    /**
     * Getter and Setter methods for senderid
     */
    public function getSenderId(): string
    {
        return $this->senderid;
    }
    public function setSenderId(string $senderid): void
    {
        $this->senderid = $senderid;
    }


    /**
     * Getter and Setter methods for recipientid
     */
    public function getRecipientId(): string|null
    {
        return $this->recipientid;
    }
    public function setRecipientId(string $recipientid): void
    {
        $this->recipientid = $recipientid;
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
     * Getter and Setter methods for transferaction
     */
    public function getTransferAction(): string
    {
        return $this->transferaction;
    }
    public function setTransferAction(string $transferaction): void
    {
        $this->transferaction = $transferaction;
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