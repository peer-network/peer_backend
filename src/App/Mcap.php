<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class Mcap
{
    protected int $capid;
    protected float $coverage;
    protected float $tokenprice;
    protected float $gemprice;
    protected float $daygems;
    protected float $daytokens;
    protected float $totaltokens;
    protected string $createdat;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->capid = $data['capid'] ?? 0;
        $this->coverage = $data['coverage'] ?? 0.0;
        $this->tokenprice = $data['tokenprice'] ?? 0.0;
        $this->gemprice = $data['gemprice'] ?? 0.0;
        $this->daygems = $data['daygems'] ?? 0.0;
        $this->daytokens = $data['daytokens'] ?? 0.0;
        $this->totaltokens = $data['totaltokens'] ?? 0.0;
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'capid' => $this->capid,
            'coverage' => $this->coverage,
            'tokenprice' => $this->tokenprice,
            'gemprice' => $this->gemprice,
            'daygems' => $this->daygems,
            'daytokens' => $this->daytokens,
            'totaltokens' => $this->totaltokens,
            'createdat' => $this->createdat,
        ];
        return $att;
    }

    // Getter and Setter methods
    public function getCapId(): int
    {
        return $this->capid;
    }

    public function setCapId(int $capid): void
    {
        $this->capid = $capid;
    }

    public function getBase(): float
    {
        return $this->coverage;
    }

    public function setBase(float $coverage): void
    {
        $this->coverage = $coverage;
    }

    public function getTokens(): float
    {
        return $this->tokenprice;
    }

    public function setTokens(float $tokenprice): void
    {
        $this->tokenprice = $tokenprice;
    }

    public function getGems(): float
    {
        return $this->gemprice;
    }

    public function setGems(float $gemprice): void
    {
        $this->gemprice = $gemprice;
    }

    public function getDailyGems(): float
    {
        return $this->daygems;
    }

    public function setDailyGems(float $daygems): void
    {
        $this->daygems = $daygems;
    }

    public function getTotalTokens(): float
    {
        return $this->totaltokens;
    }

    public function setTotalTokens(float $totaltokens): void
    {
        $this->totaltokens = $totaltokens;
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
        $specification = [
            'capid' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'coverage' => [
                'required' => true,
                'filters' => [
                    ['name' => 'FloatSanitize']
                ],
                'validators' => [
                    [
                        'name' => 'ValidateFloat',
                        'options' => [
                            'min' => 0.0,
                            'max' => 18250000.0
                        ]
                    ]
                ]
            ],
            'tokenprice' => [
                'required' => true,
                'filters' => [
                    ['name' => 'FloatSanitize']
                ],
                'validators' => [
                    [
                        'name' => 'ValidateFloat',
                        'options' => [
                            'min' => 0.0,
                            'max' => 18250000.0
                        ]
                    ]
                ]
            ],
            'gemprice' => [
                'required' => true,
                'filters' => [
                    ['name' => 'FloatSanitize']
                ],
                'validators' => [
                    [
                        'name' => 'ValidateFloat',
                        'options' => [
                            'min' => 0.0,
                            'max' => 18250000.0
                        ]
                    ]
                ]
            ],
            'daygems' => [
                'required' => true,
                'filters' => [
                    ['name' => 'FloatSanitize']
                ],
                'validators' => [
                    [
                        'name' => 'ValidateFloat',
                        'options' => [
                            'min' => 0.0,
                            'max' => 18250000.0
                        ]
                    ]
                ]
            ],
            'daytokens' => [
                'required' => true,
                'filters' => [
                    ['name' => 'FloatSanitize']
                ],
                'validators' => [
                    [
                        'name' => 'ValidateFloat',
                        'options' => [
                            'min' => 0.0,
                            'max' => 5000.0
                        ]
                    ]
                ]
            ],
            'totaltokens' => [
                'required' => true,
                'filters' => [
                    ['name' => 'FloatSanitize']
                ],
                'validators' => [
                    [
                        'name' => 'ValidateFloat',
                        'options' => [
                            'min' => 0.0,
                            'max' => 18250000.0
                        ]
                    ]
                ]
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
