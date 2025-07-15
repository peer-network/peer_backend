<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class Contactus
{
    protected ?int $msgid;
    protected string $email;
    protected string $name;
    protected string $message;
    protected string $ip;
    protected string $createdat;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->msgid = $data['msgid'] ?? 0;
        $this->email = $data['email'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->message = $data['message'] ?? '';
        $this->ip = $data['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'msgid' => $this->msgid,
            'email' => $this->email,
            'name' => $this->name,
            'message' => $this->message,
            'ip' => $this->ip,
            'createdat' => $this->createdat,
        ];
        return $att;
    }

    // Getter and Setter methods
    public function getMsgId(): int
    {
        return $this->msgid;
    }

    public function setMsgId(int $msgid): void
    {
        $this->msgid = $msgid;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getMsg(): ?string
    {
        return $this->message;
    }

    public function setMsg(string $message): void
    {
        $this->message = $message;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(?string $ip = null): void
    {
        $this->ip = filter_var($ip, FILTER_VALIDATE_IP) ?: ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function getCreatedAt(): string
    {
        return $this->createdat;
    }

    public function setCreatedAt(string $createdat): void
    {
        $this->createdat = $createdat;
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
            'msgid' => [
                'required' => false,
                'validators' => [['name' => 'ToInt']],
            ],
            'email' => [
                'required' => true,
                'filters' => [['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'EmailAddress'],
                    ['name' => 'isString'],
                ],
            ],
            'name' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 3,
                        'max' => 53,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'message' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 3,
                        'max' => 500,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'ip' => [
                'required' => true,
                'validators' => [
                    ['name' => 'IsIp', 'options' => ['ipv4' => true, 'ipv6' => true]],
                ],
            ],
            'createdat' => [
                'required' => true,
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
