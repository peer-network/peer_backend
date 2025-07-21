<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class NewsFeed
{
    protected string $feedid;
    protected string $creatorid;
    protected ?string $image;
    protected ?string $name;
    protected ?string $createdat;
    protected ?string $updatedat;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->feedid = $data['feedid'] ?? '';
        $this->creatorid = $data['creatorid'] ?? '';
        $this->image = $data['image'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->updatedat = $data['updatedat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'feedid' => $this->feedid,
            'creatorid' => $this->creatorid,
            'image' => $this->image,
            'name' => $this->name,
            'createdat' => $this->createdat,
            'updatedat' => (new DateTime())->format('Y-m-d H:i:s.u'),
        ];
        return $att;
    }

    // Array Update methods
    public function update(array $data): void
    {
        $data = $this->validate($data, ['image', 'name']);

        $this->image = $data['image'] ?? $this->image;
        $this->name = $data['name'] ?? $this->name;
    }

    // Getter and Setter methods
    public function getFeedId(): string
    {
        return $this->feedid;
    }

    public function setFeedId(string $feedid): void
    {
        $this->feedid = $feedid;
    }

    public function getCreatorId(): string
    {
        return $this->creatorid;
    }

    public function setCreatorId(string $creatorid): void
    {
        $this->creatorid = $creatorid;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdat;
    }

    public function setCreatedAt(?string $createdat): void
    {
        $this->createdat = $createdat;
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

    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $chatConfig = constants()::chat();
        $specification = [
            'feedid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'creatorid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'image' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => $chatConfig['IMAGE']['MIN_LENGTH'],
                        'max' => $chatConfig['IMAGE']['MAX_LENGTH'],
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'name' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => $chatConfig['NAME']['MIN_LENGTH'],
                        'max' => $chatConfig['NAME']['MAX_LENGTH'],
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
            'updatedat' => [
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
}
