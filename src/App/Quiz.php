<?php
namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class Quiz
{
    protected int $quizid;
    protected string $userid;
    protected string $question;
    protected string $option1;
    protected string $option2;
    protected string $option3;
    protected string $option4;
    protected string $iscorrect;

    // Constructor
    public function __construct(array $data = [])
    {
        $data = $this->validate($data);

        $this->quizid = $data['quizid'] ?? 0;
        $this->userid = $data['userid'] ?? '';
        $this->question = $data['question'] ?? '';
        $this->option1 = $data['option1'] ?? '';
        $this->option2 = $data['option2'] ?? '';
        $this->option3 = $data['option3'] ?? '';
        $this->option4 = $data['option4'] ?? '';
        $this->iscorrect = $data['iscorrect'] ?? '';
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'quizid' => $this->quizid,
            'userid' => $this->userid,
            'question' => $this->question,
            'option1' => $this->option1,
            'option2' => $this->option2,
            'option3' => $this->option3,
            'option4' => $this->option4,
            'iscorrect' => $this->iscorrect,
        ];
		return $att;
    }

    // Array Update methods
    public function update(array $data): void
    {
        $data = $this->validate($data, ['question', 'option1', 'option2', 'option3', 'option4', 'iscorrect']);

        $this->question = $data['question'] ?? $this->question;
        $this->option1 = $data['option1'] ?? $this->option1;
        $this->option2 = $data['option2'] ?? $this->option2;
        $this->option3 = $data['option3'] ?? $this->option3;
        $this->option4 = $data['option4'] ?? $this->option4;
        $this->iscorrect = $data['iscorrect'] ?? $this->iscorrect;
    }

    // Getter and Setter methods
    public function getQuizId(): int
    {
        return $this->quizid;
    }

    public function setQuizId(int $quizid): void
    {
        $this->quizid = $quizid;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(?string $question): void
    {
        $this->question = $question;
    }

    public function getIscorrect(): ?string
    {
        return $this->iscorrect;
    }

    public function setIscorrect(?string $iscorrect): void
    {
        $this->iscorrect = $iscorrect;
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
            'quizid' => [
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
            'iscorrect' => [
                'required' => false,
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
