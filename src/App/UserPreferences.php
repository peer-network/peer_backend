<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class UserPreferences
{
    protected string $userid;
    protected ?int $contentFilteringSeverityLevel;
    protected string $updatedat;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }
        $this->userid = $data['userid'] ?? '';
        $this->contentFilteringSeverityLevel = $data['contentFilteringSeverityLevel'];
        $this->updatedat = $data['updatedat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'userid' => $this->userid,
            'contentFilteringSeverityLevel' => $this->contentFilteringSeverityLevel,
            'updatedat' => $this->updatedat,
        ];
        return $att;
    }

    // Getter and Setter
    public function getUserId(): string
    {
        return $this->userid;
    }

    public function getContentFilteringSeverityLevel(): ?int
    {
        return $this->contentFilteringSeverityLevel;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedat;
    }

    public function setUpdatedAt(): void
    {
        $this->updatedat = (new DateTime())->format('Y-m-d H:i:s.u');
    }

    public function setContentFilteringSeverityLevel(int $contentFilteringSeverityLevel): void
    {
        $this->contentFilteringSeverityLevel = $contentFilteringSeverityLevel;
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
        $specification = [
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'contentFilteringSeverityLevel' => [
                'required' => false,
                'filters' => [],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => 0, 'max' => 10]],
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
