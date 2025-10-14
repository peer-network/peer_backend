<?php

declare(strict_types=1);

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;
use Fawaz\config\constants\ConstantsConfig;

class ChatMessages
{
    protected int $messid;
    protected string $userid;
    protected string $chatid;
    protected string $content;
    protected string $createdat;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

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

    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $chatConfig = ConstantsConfig::chat();
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
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => $chatConfig['MESSAGE']['MIN_LENGTH'],
                        'max' => $chatConfig['MESSAGE']['MAX_LENGTH'],
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
}
