<?php
declare(strict_types=1);

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class Pool
{
    protected string $token;
    protected string $userid;
    protected string $postid;
    protected string $fromid;
    protected float $numbers;
    protected int $numbersq;
    protected int $whereby;
    protected string $createdat;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->token = $data['token'] ?? '';
        $this->userid = $data['userid'] ?? '';
        $this->postid = $data['postid'] ?? '';
        $this->fromid = $data['fromid'] ?? '';
        $this->numbers = $data['numbers'] ?? 0.0;
        $this->numbersq = $data['numbersq'] ?? 0;
        $this->whereby = $data['whereby'] ?? 0;
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'token' => $this->token,
            'userid' => $this->userid,
            'postid' => $this->postid,
            'fromid' => $this->fromid,
            'numbers' => $this->numbers,
            'numbersq' => $this->numbersq,
            'whereby' => $this->whereby,
            'createdat' => $this->createdat,
        ];
        return $att;
    }

    // Getter and Setter
    public function getTokenId(): string
    {
        return $this->token;
    }

    public function setTokenId(string $token): void
    {
        $this->token = $token;
    }

    public function getPostId(): string
    {
        return $this->postid;
    }

    public function setPostId(string $postid): void
    {
        $this->postid = $postid;
    }

    public function getUserId(): string
    {
        return $this->userid;
    }

    public function setUserId(string $userid): void
    {
        $this->userid = $userid;
    }

    public function getFromId(): string
    {
        return $this->fromid;
    }

    public function setFromId(string $fromid): void
    {
        $this->fromid = $fromid;
    }

    public function getNumbers(): float
    {
        return $this->numbers;
    }

    public function setNumbers(float $numbers): void
    {
        $this->numbers = $numbers;
    }

    public function getNumbersq(): int
    {
        return $this->numbersq;
    }

    public function setNumbersq(int $numbersq): void
    {
        $this->numbersq = $numbersq;
    }

    public function getWhereby(): int
    {
        return $this->whereby;
    }

    public function setWhereby(int $whereby): void
    {
        $this->whereby = $whereby;
    }

    public function getCreatedAt(): string
    {
        return $this->createdat;
    }

    public function setCreatedAt(?string $createdat): void
    {
        $this->createdat = $createdat;
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
        $specification = [
            'token' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 12,
                        'max' => 12,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'postid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'fromid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'numbers' => [
                'required' => true,
                'filters' => [['name' => 'FloatSanitize']],
                'validators' => [
                    ['name' => 'ValidateFloat', 'options' => ['min' => -5000.0, 'max' => 5000.0]],
                ],
            ],
            'numbersq' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => 0, 'max' => 99999999999999999999999999999]],
                ],
            ],
            'whereby' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => 1, 'max' => 100]],
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
