<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class ChatMessages
{
    protected int $messid;
    protected string $userid;
    protected string $chatid;
    protected string $content;
    protected string $createdat;

    // Constructor
    public function __construct(array $data)
    {
        $data = $this->validate($data);

        $this->messid = $data['messid'] ?? 0;
        $this->userid = $data['userid'] ?? '';
        $this->chatid = $data['chatid'] ?? '';
        $this->content = $data['content'] ?? '';
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'messid' => $this->messid,
            'chatid' => $this->chatid,
            'userid' => $this->userid,
            'content' => $this->content,
            'createdat' => $this->createdat,
        ];
        return $att;
    }

    // Getter and Setter methods
    public function getMessId(): int
    {
        return $this->messid;
    }

    public function setMessId(int $messid): void
    {
        $this->messid = $messid;
    }

    public function getUserId(): string
    {
        return $this->userid;
    }

    public function setUserId(string $userid): void
    {
        $this->userid = $userid;
    }

    public function getChatId(): string
    {
        return $this->chatid;
    }

    public function setChatId(string $chatid): void
    {
        $this->chatid = $chatid;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
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
            'messid' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
            ],
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'chatid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'content' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 1,
                        'max' => 500,
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
}
