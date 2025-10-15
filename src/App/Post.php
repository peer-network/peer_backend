<?php

declare(strict_types=1);

namespace Fawaz\App;

use DateTime;
use Fawaz\App\Models\Core\Model;
use Fawaz\Filter\PeerInputFilter;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\Interfaces\Hashable;
use Fawaz\Utils\HashObject;

class Post extends Model implements Hashable
{
    use HashObject;

    protected string $postid;
    protected string $userid;
    protected ?string $feedid;
    protected string $title;
    protected string $contenttype;
    protected string $media;
    protected string $url;
    protected ?string $cover;
    protected string $mediadescription;
    protected string $createdat;

    // Constructor
    public function __construct(
        array $data = [],
        array $elements = [],
        bool $validate = true
    ) {
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
        $this->url = $this->getPostUrl();
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
            'url' => $this->url,
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
        $postConst = ConstantsConfig::post();
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
                        'min' => $postConst['MEDIA']['MIN_LENGTH'],
                        'max' => $postConst['MEDIA']['MAX_LENGTH'],
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'cover' => [
                'required' => false,
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => $postConst['COVER']['MIN_LENGTH'],
                        'max' => $postConst['COVER']['MAX_LENGTH'],
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
            $specification = array_filter($specification, fn ($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return (new PeerInputFilter($specification));
    }

    public function getHashableContent(): string
    {
        return implode('|', [
            $this->title,
            $this->contenttype,
            $this->media,
            $this->cover ?? '',
            $this->mediadescription,
        ]);
    }

    public function hashValue(): string
    {
        return $this->hashObject($this);
    }

    public function getPostUrl(): string
    {
        if (empty($this->postid)) {
            return '';
        }
        return $_ENV['WEB_APP_URL'] . '/post/' . $this->postid;
    }

    // Table name
    public static function table(): string
    {
        return 'posts';
    }

}
