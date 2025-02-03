<?php
namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class ProfilUser
{
    protected string $uid;
    protected string $username;
    protected ?string $img;
    protected ?bool $isfollowed;
    protected ?bool $isfollowing;

    // Constructor
    public function __construct(array $data = [])
    {
        $data = $this->validate($data);

        $this->uid = $data['uid'] ?? '';
        $this->username = $data['username'] ?? '';
        $this->img = $data['img'] ?? '';
        $this->isfollowed = $data['isfollowed'] ?? false;
        $this->isfollowing = $data['isfollowing'] ?? false;
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'uid' => $this->uid,
            'username' => $this->username,
            'img' => $this->img,
            'isfollowed' => $this->isfollowed,
            'isfollowing' => $this->isfollowing,
        ];
		return $att;
    }

    // Getter and Setter methods
    public function getUserId(): string
    {
        return $this->uid;
    }

    public function setUserId(string $uid): void
    {
        $this->uid = $uid;
    }

    public function getName(): string
    {
        return $this->username;
    }

    public function setName(string $name): void
    {
        $this->username = $name;
    }

    public function getImg(): ?string
    {
        return $this->img;
    }

    public function setImg(?string $img): void
    {
        $this->img = $img;
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
            'uid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
			'username' => [
				'required' => true,
				'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities'], ['name' => 'SqlSanitize']],
				'validators' => [
					['name' => 'StringLength', 'options' => [
						'min' => 3,
						'max' => 23,
					]],
					['name' => 'isString'],
				],
			],
            'img' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 30,
                        'max' => 100,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'isfollowed' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
            'isfollowing' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
        ];

        if ($elements) {
            $specification = array_filter($specification, fn($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

		return (new PeerInputFilter($specification));
    }
}
