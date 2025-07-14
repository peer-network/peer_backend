<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class ChatParticipants
{
    protected string $userid;
    protected string $chatid;
    protected int $hasaccess;
    protected string $createdat;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->userid = $data['userid'] ?? '';
        $this->chatid = $data['chatid'] ?? '';
        $this->hasaccess = $data['hasaccess'] ?? 0;
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'userid' => $this->userid,
            'chatid' => $this->chatid,
            'hasaccess' => $this->hasaccess,
            'createdat' => $this->createdat,
        ];
        return $att;
    }

    // Getter and Setter methods
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

    public function getHasAccess(): int
    {
        return $this->hasaccess;
    }

    public function setHasAccess(int $hasaccess): void
    {
        $this->hasaccess = $hasaccess;
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
        $chatConfig = constants()::chat();
        $specification = [
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'chatid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
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
