<?php

declare(strict_types=1);

namespace Fawaz\App\Models;

use DateTime;
use Fawaz\App\ValidationException;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Filter\PeerInputFilter;
use Fawaz\Utils\ResponseHelper;

class Transaction
{
    use ResponseHelper;

    protected string $transactionid;

    /**
     * Operation ID Refers to Bunch of All Fees transactions.
     *
     * `operationid` foreign key reference to logWins Table -> `token` field.
     */
    protected string $operationid;

    protected string $senderid;
    protected string $recipientid;
    protected string $transactiontype;
    protected string $tokenamount;
    protected string $transferaction;
    protected ?string $message;
    protected ?string $createdat;

    /**
     * Assign Notification object while instantiated
     */
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        $this->transactionid = $data['transactionid'] ?? self::generateUUID();
        $this->operationid = $data['operationid'] ?? null;
        $this->senderid = $data['senderid'] ?? null;
        $this->recipientid = $data['recipientid'] ?? null;
        $this->transactiontype = $data['transactiontype'] ?? null;
        $this->tokenamount = (string) $data['tokenamount'];
        $this->transferaction = $data['transferaction'] ?? 'DEDUCT';
        $this->message = $data['message'] ?? null;
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');

        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }
    }

    /**
     * Get Values of current state
     */
    public function getArrayCopy(): array
    {
        $att = [
            'transactionid' => $this->transactionid,
            'operationid' => $this->operationid,
            'transactiontype' => $this->transactiontype,
            'senderid' => $this->senderid,
            'recipientid' => $this->recipientid,
            'tokenamount' => $this->tokenamount,
            'transferaction' => $this->transferaction,
            'message' => $this->message,
            'createdat' => $this->createdat,
        ];
        return $att;
    }

    /**
     * Define Input filter
     */
    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $tranConfig = ConstantsConfig::transaction();

        $specification = [
            'transactionid' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
            ],
            'operationid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'senderid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'recipientid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'transactiontype' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => $tranConfig['ACTIONTYPE']['MIN_LENGTH'],
                        'max' => $tranConfig['ACTIONTYPE']['MIN_LENGTH'],
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
                        'min' => $tranConfig['ACTIONTYPE']['MIN_LENGTH'],
                        'max' => $tranConfig['ACTIONTYPE']['MAX_LENGTH'],
                        'errorCode' => 30210
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'message' => [
                'required' => false,
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => $tranConfig['ACTIONTYPE']['MIN_LENGTH'],
                        'max' => $tranConfig['ACTIONTYPE']['MAX_LENGTH'],
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
            $specification = array_filter($specification, fn ($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
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

    /**
     * Getter method for transactionid
     */
    public function getTransactionId(): string
    {
        return $this->transactionid;
    }


    /**
     * Getter method for operationid
     */
    public function getOperationId(): string
    {
        return $this->operationid;
    }


    /**
     * Getter method for senderid
     */
    public function getSenderId(): string
    {
        return $this->senderid;
    }


    /**
     * Getter method for recipientid
     */
    public function getRecipientId(): string|null
    {
        return $this->recipientid;
    }



    /**
     * Getter method for transactiontype
     */
    public function getTransactionType(): string|null
    {
        return $this->transactiontype;
    }



    /**
     * Getter method for tokenamount
     */
    public function getTokenAmount(): string
    {
        return $this->tokenamount;
    }



    /**
     * Getter method for transferaction
     */
    public function getTransferAction(): string
    {
        return $this->transferaction;
    }

    /**
     * Getter method for message
     */
    public function getMessage(): string|null
    {
        return $this->message;
    }


    /**
     * Getter method for createdat
     */
    public function getCreatedat(): string
    {
        return $this->createdat;
    }

}
