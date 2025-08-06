<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class Tokenize
{
    protected string $token;
    protected string $userid;
    protected int $attempt;
    protected int $expiresat;
    protected string $updatedat;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->token = $data['token'] ?? '';
        $this->userid = $data['userid'] ?? '';
        $this->attempt = $data['attempt'] ?? 0;
        $this->expiresat = $data['expiresat'] ?? 0;
        $this->updatedat = $data['updatedat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'token' => $this->token,
            'userid' => $this->userid,
            'attempt' => $this->attempt,
            'expiresat' => $this->expiresat,
            'updatedat' => $this->updatedat
        ];
        return $att;
    }

    // Getter and Setter
    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getUserId(): string
    {
        return $this->userid;
    }

    public function setUserId(string $userid): void
    {
        $this->userid = $userid;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    public function setAttempt(int $attempt): void
    {
        $this->attempt = $attempt;
    }

    public function getExpires(): int
    {
        return $this->expiresat;
    }

    public function setExpires(int $expiresat): void
    {
        $this->expiresat = $expiresat;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedat;
    }

    public function setUpdatedAt(?string $updatedat): void
    {
        $this->updatedat = $updatedat;
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
			$errorMessages[] = "Validation errors for $field";
            foreach ($errors as $error) {
                //$errorMessages[] = $error;
				$errorMessages[] = ": $error";
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
                'filters' => [['name' => 'HtmlEntities']],
                'validators' => [['name' => 'validateActivationToken']],
            ],
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'attempt' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => 0, 'max' => 3]],
                ],
            ],
            'expiresat' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => \time(), 'max' => \time()+1800]],
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
