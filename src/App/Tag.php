<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class Tag
{
    protected int $tagid;
    protected string $name;

    // Constructor
    public function __construct(array $data = [])
    {
        $data = $this->validate($data);

        $this->tagid = $data['tagid'] ?? 0;
        $this->name = $data['name'] ?? '';
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        return [
            'tagid' => $this->tagid,
            'name' => $this->name,
        ];
    }

    // Array Update methods
    public function update(array $data): void
    {
        $data = $this->validate($data, ['name']);
        $this->name = $data['name'] ?? $this->name;
    }

    // Getter and Setter methods
    public function getTagId(): int
    {
        return $this->tagid;
    }

    public function setTagId(int $tagId): void
    {
        $this->tagid = $tagId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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
            'tagid' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'name' => [
                'required' => true,
                'validators' => [
                    ['name' => 'validateTagName']
                ]
            ],
        ];

        if ($elements) {
            $specification = array_filter($specification, fn($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return (new PeerInputFilter($specification));
    }
}
