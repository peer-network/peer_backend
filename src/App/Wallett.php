<?php
namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class Wallett
{
    protected string $userid;
    protected float $liquidity;
    protected int $liquiditq;
    protected string $updatedat;
    protected string $createdat;

    // Constructor
    public function __construct(array $data = [])
    {
        $data = $this->validate($data);

        $this->userid = $data['userid'] ?? '';
        $this->liquidity = $data['liquidity'] ?? 0.0;
        $this->liquiditq = $data['liquiditq'] ?? 0;
        $this->updatedat = $data['updatedat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'userid' => $this->userid,
            'liquidity' => $this->liquidity,
            'liquiditq' => $this->liquiditq,
            'updatedat' => $this->updatedat,
            'createdat' => $this->createdat,
        ];
        return $att;
    }

    // Getter and Setter
    public function getUserId(): string
    {
        return $this->userid;
    }

    public function setUserId(string $userid): void
    {
        $this->userid = $userid;
    }

    public function getLiquidity(): float
    {
        return $this->liquidity;
    }

    public function setLiquidity(float $liquidity): void
    {
        $this->liquidity = $liquidity;
    }

    public function getLiquiditq(): int
    {
        return $this->liquiditq;
    }

    public function setLiquiditq(int $liquiditq): void
    {
        $this->liquiditq = $liquiditq;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdat;
    }

    public function setCreatedAt(?string $createdat): void
    {
        $this->createdat = $createdat;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedat;
    }

    public function setUpdatedAt(?string $updatedat): void
    {
        $this->updatedat = $updatedat;
    }

    // Validation and Array Filtering methods (Unchanged)
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
            'liquidity' => [
                'required' => true,
                'filters' => [['name' => 'FloatSanitize']],
                'validators' => [
                    ['name' => 'ValidateFloat', 'options' => ['min' => -5000.0, 'max' => 18250000.0]],
                ],
            ],
            'liquiditq' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => 0, 'max' => 99999999999999999999999999999]],
                ],
            ],
            'updatedat' => [
                'required' => true,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                    ['name' => 'LessThan', 'options' => ['max' => (new DateTime())->format('Y-m-d H:i:s.u'), 'inclusive' => true]],
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
