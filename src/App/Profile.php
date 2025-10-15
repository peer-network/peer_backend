<?php

declare(strict_types=1);

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;
use Fawaz\config\constants\ConstantsConfig;

class Profile
{
    protected string $uid;
    protected string $username;
    protected int $status;
    protected int $slug;
    protected ?string $img;
    protected ?string $biography;
    protected ?int $amountposts;
    protected ?int $amounttrending;
    protected ?bool $isfollowed;
    protected ?bool $isfollowing;
    protected ?int $amountfollower;
    protected ?int $amountfollowed;
    protected ?int $amountfriends;
    protected ?int $amountblocked;
    protected ?array $imageposts = [];
    protected ?array $textposts = [];
    protected ?array $videoposts = [];
    protected ?array $audioposts = [];
    protected ?int $reports;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->uid = $data['uid'] ?? '';
        $this->username = $data['username'] ?? '';
        $this->status = $data['status'] ?? 0;
        $this->slug = $data['slug'] ?? 0;
        $this->img = $data['img'] ?? '';
        $this->biography = $data['biography'] ?? '';
        $this->amountposts = $data['amountposts'] ?? 0;
        $this->amounttrending = $data['amounttrending'] ?? 0;
        $this->isfollowed = $data['isfollowed'] ?? false;
        $this->isfollowing = $data['isfollowing'] ?? false;
        $this->amountfollower = $data['amountfollower'] ?? 0;
        $this->amountfollowed = $data['amountfollowed'] ?? 0;
        $this->amountfriends = $data['amountfriends'] ?? 0;
        $this->amountblocked = $data['amountblocked'] ?? 0;
        $this->imageposts = isset($data['imageposts']) && is_array($data['imageposts']) ? $data['imageposts'] : [];
        $this->textposts = isset($data['textposts']) && is_array($data['textposts']) ? $data['textposts'] : [];
        $this->videoposts = isset($data['videoposts']) && is_array($data['videoposts']) ? $data['videoposts'] : [];
        $this->audioposts = isset($data['audioposts']) && is_array($data['audioposts']) ? $data['audioposts'] : [];
        $this->reports = $data['user_reports'] ?? 0;

        if (($this->status == 6)) {
            $this->username = 'Deleted_Account';
            $this->img = '/profile/2e855a7b-2b88-47bc-b4dd-e110c14e9acf.jpeg';
            $this->biography = '/userData/fb08b055-511a-4f92-8bb4-eb8da9ddf746.txt';
        }
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'uid' => $this->uid,
            'username' => $this->username,
            'status' => $this->status,
            'slug' => $this->slug,
            'img' => $this->img,
            'biography' => $this->biography,
            'amountposts' => $this->amountposts,
            'amounttrending' => $this->amounttrending,
            'isfollowed' => $this->isfollowed,
            'isfollowing' => $this->isfollowing,
            'amountfollower' => $this->amountfollower,
            'amountfollowed' => $this->amountfollowed,
            'amountfriends' => $this->amountfriends,
            'amountblocked' => $this->amountblocked,
            'imageposts' => $this->imageposts,
            'textposts' => $this->textposts,
            'videoposts' => $this->videoposts,
            'audioposts' => $this->audioposts,
            'user_reports' => $this->reports
        ];
        return $att;
    }

    // Getter and Setter
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
        $userConfig = ConstantsConfig::user();
        $specification = [
            'uid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'username' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags']],
                'validators' => [
                    ['name' => 'validateUsername'],
                ],
            ],
            'status' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => 0, 'max' => 10]],
                ],
            ],
            'slug' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => [
                        'min' => $userConfig['SLUG']['MIN_LENGTH'],
                        'max' => $userConfig['SLUG']['MAX_LENGTH']
                        ]],
                ],
            ],
            'img' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => $userConfig['IMAGE']['MIN_LENGTH'],
                        'max' => $userConfig['IMAGE']['MAX_LENGTH'],
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'biography' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => $userConfig['BIOGRAPHY']['MIN_LENGTH'],
                        'max' => $userConfig['BIOGRAPHY']['MAX_LENGTH'],
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'amountposts' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'amounttrending' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'isfollowed' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
            'isfollowing' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
            'amountfollower' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'amountfollowed' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'amountfriends' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'amountblocked' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'imageposts' => [
                'required' => false,
                'validators' => [['name' => 'IsArray']],
            ],
            'textposts' => [
                'required' => false,
                'validators' => [['name' => 'IsArray']],
            ],
            'videoposts' => [
                'required' => false,
                'validators' => [['name' => 'IsArray']],
            ],
            'audioposts' => [
                'required' => false,
                'validators' => [['name' => 'IsArray']],
            ],
            'reports' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
        ];

        if ($elements) {
            $specification = array_filter($specification, fn ($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return (new PeerInputFilter($specification));
    }
}
