<?php
namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class Surveys
{
    protected int $survid;
    protected string $userid;
    protected string $question;
    protected string $option1;
    protected string $option2;
    protected string $option3;
    protected string $option4;
    protected string $option5;

    // Constructor
    public function __construct(array $data = [])
    {
        $data = $this->validate($data);

        $this->survid = $data['survid'] ?? 0;
        $this->userid = $data['userid'] ?? '';
        $this->question = $data['question'] ?? '';
        $this->option1 = $data['option1'] ?? '';
        $this->option2 = $data['option2'] ?? '';
        $this->option3 = $data['option3'] ?? '';
        $this->option4 = $data['option4'] ?? '';
        $this->option5 = $data['option5'] ?? '';
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'survid' => $this->survid,
            'userid' => $this->userid,
            'question' => $this->question,
            'option1' => $this->option1,
            'option2' => $this->option2,
            'option3' => $this->option3,
            'option4' => $this->option4,
            'option5' => $this->option5,
        ];
		return $att;
    }

    // Array Update methods
    public function update(array $data): void
    {
        $data = $this->validate($data, ['question', 'option1', 'option2', 'option3', 'option4', 'option5']);

        $this->question = $data['question'] ?? $this->question;
        $this->option1 = $data['option1'] ?? $this->option1;
        $this->option2 = $data['option2'] ?? $this->option2;
        $this->option3 = $data['option3'] ?? $this->option3;
        $this->option4 = $data['option4'] ?? $this->option4;
        $this->option5 = $data['option5'] ?? $this->option5;
    }

    // Getter and Setter methods
    public function getSurvId(): int
    {
        return $this->survid;
    }

    public function setSurvId(int $survid): void
    {
        $this->survid = $survid;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(?string $question): void
    {
        $this->question = $question;
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
            'survid' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'userid' => [
                'required' => true,
                'validators' => [
                    ['name' => 'Uuid'],
                ],
            ],
            'question' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 5,
                        'max' => 200,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'option1' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 1,
                        'max' => 100,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'option2' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 1,
                        'max' => 100,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'option3' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 1,
                        'max' => 100,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'option4' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 1,
                        'max' => 100,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'option5' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 1,
                        'max' => 100,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
        ];

        if ($elements) {
            $specification = array_filter($specification, fn($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return (new PeerInputFilter($specification));
    }
}
