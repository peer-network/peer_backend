<?php

namespace Fawaz\App\Models;

enum TransferAction {
    case DEDUCT;
    case CREDIT;
    case FEE;
    case BURN;
}

use DateTime;
use Fawaz\App\ValidationException;
use Fawaz\Filter\PeerInputFilter;

class TokenEuroPrice
{
    protected string $token;
    protected string $europrice;
    protected ?string $updatedat;

    /**
     * Assign Notification object while instantiated
     */
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        $this->token = $data['token'] ?? '';
        $this->europrice = $data['europrice'] ?? '0.0';
        $this->updatedat = $data['updatedat'] ?? date('Y-m-d H:i:s.u');

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
            'token' => [
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
            'europrice' => [
                'required' => false,
                'validators' => [['name' => 'ToFloat'], ['name' => 'FloatSanitize']],
            ],
            // 'updatedat' => [
            //     'required' => false,
            //     'validators' => [
            //         ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
            //         ['name' => 'LessThan', 'options' => ['max' => (new DateTime())->format('Y-m-d H:i:s.u'), 'inclusive' => true]],
            //     ],
            // ],
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
     * Getter and Setter methods for token
     */
    public function getToken(): string
    {
        return $this->token;
    }
    public function setToken(string $token): void
    {
        $this->token = $token;
    }


    /**
     * Getter and Setter methods for europrice
     */
    public function getEuroPrice(): string
    {
        return $this->europrice;
    }
    public function setTokenAmount(string $europrice): void
    {
        $this->europrice = $europrice;
    }
    
    
    /**
     * Getter and Setter methods for updatedat
     */
    public function getUpdatedat(): string
    {
        return $this->updatedat;
    }
    public function setUpdatedat(string $updatedat): void
    {
        $this->updatedat = $updatedat;
    }
}