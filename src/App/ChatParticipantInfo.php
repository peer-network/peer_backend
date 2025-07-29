<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;
use Fawaz\config\constants\ConstantsConfig;

class ChatParticipantInfo
{
    protected string $userid;
    protected string $username;
    protected string $img;
    protected int $hasaccess;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->userid = $data['userid'] ?? '';
        $this->username = $data['username'] ?? '';
        $this->img = $data['img'] ?? '';
        $this->hasaccess = $data['hasaccess'] ?? 0;
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'userid' => $this->userid,
            'username' => $this->username,
            'img' => $this->img,
            'hasaccess' => $this->hasaccess,
        ];
        return $att;
    }

    // Getter and Setter methods
    public function getUserId(): string
    {
        return $this->userid;
    }

    public function getName(): string
    {
        return $this->username;
    }

    public function getImg(): ?string
    {
        return $this->img;
    }

    public function getHasAccess(): int
    {
        return $this->hasaccess;
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
        $chatConfig = ConstantsConfig::chat();
        $specification = [
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'username' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags']],
                'validators' => [
                    ['name' => 'validateUsername'],
                ],
            ],
            'img' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => $chatConfig['IMAGE']['MIN_LENGTH'],
                        'max' => $chatConfig['IMAGE']['MAX_LENGTH'],
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'hasaccess' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => [
                        'min' => $chatConfig['ACCESS_LEVEL']['MIN'],
                        'max' => $chatConfig['ACCESS_LEVEL']['MAX'],
                        ]],
                ],
            ],
        ];

        if ($elements) {
            $specification = array_filter($specification, fn($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return (new PeerInputFilter($specification));
    }
}
