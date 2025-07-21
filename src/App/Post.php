<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\Interfaces\Hashable;
use Fawaz\Utils\HashObject;

class Post implements Hashable
{
    use HashObject;

    protected string $postid;
    protected string $userid;
    protected ?string $feedid;
    protected string $title;
    protected string $contenttype;
    protected string $media;
    protected ?string $cover;
    protected string $mediadescription;
    protected string $createdat;

    // Constructor
    public function __construct(
        array $data = [], 
        array $elements = [], 
        bool $validate = true
    )
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->postid = $data['postid'] ?? '';
        $this->userid = $data['userid'] ?? '';
        $this->feedid = $data['feedid'] ?? null;
        $this->title = $data['title'] ?? '';
        $this->contenttype = $data['contenttype'] ?? 'text';
        $this->media = $data['media'] ?? '';
        $this->cover = $data['cover'] ?? null;
        $this->mediadescription = $data['mediadescription'] ?? '';
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'postid' => $this->postid,
            'userid' => $this->userid,
            'feedid' => $this->feedid,
            'title' => $this->title,
            'contenttype' => $this->contenttype,
            'media' => $this->media,
            'cover' => $this->cover,
            'mediadescription' => $this->mediadescription,
            'createdat' => $this->createdat,
        ];
        return $att;
    }

    // Getter and Setter
    public function getPostId(): string
    {
        return $this->postid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUserId(): string
    {
        return $this->userid;
    }

    public function getFeedId(): string
    {
        return $this->feedid;
    }

    public function getMedia(): string
    {
        return $this->media;
    }

    public function getContentType(): string
    {
        return $this->contenttype;
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
            'postid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'feedid' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
            ],
            'title' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => ConstantsConfig::post()['TITLE']['MIN_LENGTH'],
                        'max' => ConstantsConfig::post()['TITLE']['MAX_LENGTH'],
                        'errorCode' => 30210
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'contenttype' => [
                'required' => true,
                'validators' => [
                    ['name' => 'InArray', 'options' => [
                        'haystack' => ['image', 'text', 'video', 'audio', 'imagegallery', 'videogallery', 'audiogallery'],
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'media' => [
                'required' => false,
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 30,
                        'max' => 1000,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'cover' => [
                'required' => false,
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 0,
                        'max' => 1000,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'mediadescription' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => ConstantsConfig::post()['MEDIADESCRIPTION']['MIN_LENGTH'],
                        'max' => ConstantsConfig::post()['MEDIADESCRIPTION']['MAX_LENGTH'],
                        'errorCode' => 30263
                    ]],
                    ['name' => 'isString'],
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

    public function getHashableContent(): string {
        return implode('|', [
            $this->title,
            $this->contenttype,
            $this->media,
            $this->cover ?? '',
            $this->mediadescription,
        ]);
    }

    public function hashValue(): string {
        return $this->hashObject($this);
    }
}
