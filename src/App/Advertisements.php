<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class Advertisements
{
    protected string $advertisementid;
    protected string $userid;
    protected string $status;
    protected string $timestart;
    protected string $timeend;
    protected string $createdat;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->advertisementid = $data['advertisementid'] ?? '';
        $this->userid = $data['userid'] ?? '';
        $this->status = $data['status'] ?? 'basic';
        $this->timestart = $data['timestart'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->timeend = $data['timeend'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'advertisementid' => $this->advertisementid,
            'userid' => $this->userid,
            'status' => $this->status,
            'timestart' => $this->timestart,
            'timeend' => $this->timeend,
            'createdat' => $this->createdat,
        ];
        return $att;
    }

    // Getter and Setter methods
    public function getAdvertisementId(): string
    {
        return $this->advertisementid;
    }

    public function getUserId(): string
    {
        return $this->userid;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getTimestart(): string
    {
        return $this->timestart;
    }

    public function setTimestart(string $timestart): void
    {
        $this->timestart = $timestart;
    }

    public function getTimeend(): string
    {
        return $this->timeend;
    }

    public function setTimeend(string $timeend): void
    {
        $this->timeend = $timeend;
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
            'advertisementid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'status' => [
                'required' => true,
                'validators' => [
                    ['name' => 'InArray', 'options' => [
                        'haystack' => ['basic', 'pinned'],
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'timestart' => [
                'required' => true,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                ],
            ],
            'timeend' => [
                'required' => true,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                ],
            ],
            'createdat' => [
                'required' => false,
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
