<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;
use Fawaz\config\constants\ConstantsConfig;

class PostAdvanced
{
    protected string $postid;
    protected string $userid;
    protected ?string $feedid;
    protected string $title;
    protected string $media;
    protected ?string $cover;
    protected string $mediadescription;
    protected string $contenttype;
    protected string $url;
    protected ?int $amountlikes;
    protected ?int $amountdislikes;
    protected ?int $amountviews;
    protected ?int $amountcomments;
    protected ?int $amountposts;
    protected ?int $amounttrending;
    protected ?bool $isliked;
    protected ?bool $isviewed;
    protected ?bool $isreported;
    protected ?bool $isdisliked;
    protected ?bool $issaved;
    protected ?bool $isfollowed;
    protected ?bool $isfollowing;
    protected string $createdat;
    protected ?array $tags = [];
    protected ?array $user = [];
    protected ?array $comments = [];

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->postid = $data['postid'] ?? '';
        $this->userid = $data['userid'] ?? '';
        $this->feedid = $data['feedid'] ?? null;
        $this->title = $data['title'] ?? '';
        $this->media = $data['media'] ?? '';
        $this->cover = $data['cover'] ?? null;
        $this->mediadescription = $data['mediadescription'] ?? '';
        $this->contenttype = $data['contenttype'] ?? 'text';
        $this->amountlikes = $data['amountlikes'] ?? 0;
        $this->amountdislikes = $data['amountdislikes'] ?? 0;
        $this->amountviews = $data['amountviews'] ?? 0;
        $this->amountcomments = $data['amountcomments'] ?? 0;
        $this->amountposts = $data['amountposts'] ?? 0;
        $this->amounttrending = $data['amounttrending'] ?? 0;
        $this->isliked = $data['isliked'] ?? false;
        $this->isviewed = $data['isviewed'] ?? false;
        $this->isreported = $data['isreported'] ?? false;
        $this->isdisliked = $data['isdisliked'] ?? false;
        $this->issaved = $data['issaved'] ?? false;
        $this->isfollowed = $data['isfollowed'] ?? false;
        $this->isfollowing = $data['isfollowing'] ?? false;
        $this->url = $this->getPostUrl();
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->tags = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];
        $this->user = isset($data['user']) && is_array($data['user']) ? $data['user'] : [];
        $this->comments = isset($data['comments']) && is_array($data['comments']) ? $data['comments'] : [];
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'postid' => $this->postid,
            'userid' => $this->userid,
            'feedid' => $this->feedid,
            'title' => $this->title,
            'media' => $this->media,
            'cover' => $this->cover,
            'url' => $this->url,
            'mediadescription' => $this->mediadescription,
            'contenttype' => $this->contenttype,
            'amountlikes' => $this->amountlikes,
            'amountdislikes' => $this->amountdislikes,
            'amountviews' => $this->amountviews,
            'amountcomments' => $this->amountcomments,
            'amountposts' => $this->amountposts,
            'amounttrending' => $this->amounttrending,
            'isliked' => $this->isliked,
            'isviewed' => $this->isviewed,
            'isreported' => $this->isreported,
            'isdisliked' => $this->isdisliked,
            'issaved' => $this->issaved,
            'isfollowed' => $this->isfollowed,
            'isfollowing' => $this->isfollowing,
            'createdat' => $this->createdat,
            'tags' => $this->tags, // Include tags
            'user' => $this->user,
            'comments' => $this->comments,
        ];
        return $att;
    }

    // Getter and Setter
    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function getPostId(): string
    {
        return $this->postid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
    
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
    public function getMediaDescription(): string
    {
        return $this->mediadescription;
    }
    public function setMediaDescription(string $mediadescription): void
    {
        $this->mediadescription = $mediadescription;
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
    public function setMedia(string $media): void
    {
        $this->media = $media;
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
            'media' => [
                'required' => true,
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
            'contenttype' => [
                'required' => true,
                'validators' => [
                    ['name' => 'InArray', 'options' => [
                        'haystack' => ['image', 'text', 'video', 'audio', 'imagegallery', 'videogallery', 'audiogallery'],
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'amountlikes' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'amountdislikes' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'amountviews' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'amountcomments' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
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
            'isliked' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
            'isviewed' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
            'isreported' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
            'isdisliked' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
            'issaved' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
            'isfollowed' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
            'isfollowing' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
            'tags' => [
                'required' => false,
                'validators' => [
                    ['name' => 'IsArray'],
                    [
                        'name' => 'ArrayValues',
                        'options' => [
                            'validator' => [
                                'name' => 'IsString',
                            ],
                        ],
                    ],
                ],
            ],
            'createdat' => [
                'required' => false,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                    ['name' => 'LessThan', 'options' => ['max' => (new DateTime())->format('Y-m-d H:i:s.u'), 'inclusive' => true]],
                ],
            ],
            'user' => [
                'required' => false,
                'validators' => [
                    ['name' => 'IsArray'],
                ],
            ],
            'comments' => [
                'required' => false,
                'validators' => [
                    ['name' => 'IsArray'],
                ],
            ],
        ];

        if ($elements) {
            $specification = array_filter($specification, fn($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return (new PeerInputFilter($specification));
    }
    
    public function getPostUrl(): string
    {
        if(empty($this->postid)) {
            return '';
        }
        return $_ENV['WEB_APP_URL'] . '/post/' . $this->postid;
    }
}
