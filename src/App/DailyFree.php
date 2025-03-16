<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class DailyFree
{
    protected string $userid;
    protected int $liken;
    protected int $comments;
    protected int $posten;
    protected ?string $createdat;

    // Constructor
    public function __construct(array $data = [])
    {
        $data = $this->validate($data);

        $this->userid = $data['userid'] ?? '';
        $this->liken = $data['liken'] ?? 0;
        $this->comments = $data['comments'] ?? 0;
        $this->posten = $data['posten'] ?? 0;
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'userid' => $this->userid,
            'liken' => $this->liken,
            'comments' => $this->comments,
            'posten' => $this->posten,
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

    public function getLiken(): int
    {
        return $this->liken;
    }

    public function setLiken(int $liken): void
    {
        $this->liken = $liken;
    }

    public function getComments(): int
    {
        return $this->comments;
    }

    public function setComments(int $comments): void
    {
        $this->comments = $comments;
    }

    public function getPosten(): int
    {
        return $this->posten;
    }

    public function setPosten(int $posten): void
    {
        $this->posten = $posten;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdat;
    }

    public function setCreatedAt(?string $createdat): void
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
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'liken' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'comments' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'posten' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
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
