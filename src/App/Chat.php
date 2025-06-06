<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class Chat
{
    protected string $chatid;
    protected string $creatorid;
    protected ?string $image;
    protected ?string $name;
    protected int $ispublic;
    protected ?string $createdat;
    protected ?string $updatedat;
    protected ?array $chatmessages = [];
    protected ?array $chatparticipants = [];

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->chatid = $data['chatid'] ?? '';
        $this->creatorid = $data['creatorid'] ?? '';
        $this->image = $data['image'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->ispublic = isset($data['ispublic']) ? (int)$data['ispublic'] : 1;
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->updatedat = $data['updatedat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->chatmessages = isset($data['chatmessages']) && is_array($data['chatmessages']) ? $data['chatmessages'] : [];
        $this->chatparticipants = isset($data['chatparticipants']) && is_array($data['chatparticipants']) ? $data['chatparticipants'] : [];
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'chatid' => $this->chatid,
            'creatorid' => $this->creatorid,
            'image' => $this->image,
            'name' => $this->name,
            'ispublic' => $this->ispublic,
            'createdat' => $this->createdat,
            'updatedat' => (new DateTime())->format('Y-m-d H:i:s.u'),
            'chatmessages' => $this->chatmessages,
            'chatparticipants' => $this->chatparticipants,
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
    public function getChatId(): string
    {
        return $this->chatid;
    }

    public function setChatId(string $chatid): void
    {
        $this->chatid = $chatid;
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

    public function getIsPublic(): int
    {
        return $this->ispublic;
    }

    public function setIsPublic(int $ispublic): void
    {
        $this->ispublic = $ispublic;
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

    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $specification = [
            'chatid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'creatorid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'image' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 30,
                        'max' => 100,
                    ]],
                    ['name' => 'validateImage'],
                ],
            ],
            'name' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 3,
                        'max' => 53,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'ispublic' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => 0, 'max' => 10]],
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
            'chatmessages' => [
                'required' => false,
                'validators' => [
                    [
                        'name' => 'ValidateChatMessages',
                        'options' => [],
                    ],
                ],
            ],
            'chatparticipants' => [
                'required' => false,
                'validators' => [
                    [
                        'name' => 'ValidateParticipants',
                        'options' => [],
                    ],
                ],
            ],
        ];

        if ($elements) {
            $specification = array_filter($specification, fn($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return (new PeerInputFilter($specification));
    }
}
