<?php
namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class PostAdvanced
{
    protected string $postid;
    protected string $userid;
    protected ?string $feedid;
    protected string $title;
    protected string $media;
    protected string $cover;
    protected string $mediadescription;
    protected string $contenttype;
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
    protected string $options;
    protected string $createdat;
    protected ?array $tags = []; // Changed to array
    protected ?array $user = [];
    protected ?array $comments = [];

    // Constructor
    public function __construct(array $data = [])
    {
        $data = $this->validate($data);

        $this->postid = $data['postid'] ?? '';
        $this->userid = $data['userid'] ?? '';
        $this->feedid = $data['feedid'] ?? null;
        $this->title = $data['title'] ?? '';
        $this->media = $data['media'] ?? '';
        $this->cover = $data['cover'] ?? '';
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
        $this->options = $data['options'] ?? '';
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->tags = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];
        $this->user = isset($data['user']) && is_array($data['user']) ? $data['user'] : [];
        $this->comments = isset($data['comments']) && is_array($data['comments']) ? $data['comments'] : [];
    }

    // Getter and Setter for Tags
    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
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
            'options' => $this->options,
            'createdat' => $this->createdat,
            'tags' => $this->tags, // Include tags
            'user' => $this->user,
            'comments' => $this->comments,
        ];
        return $att;
    }

    // Other Methods (Unchanged)
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

    public function getOptions(): string
    {
        return $this->options;
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
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 3,
                        'max' => 96,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'media' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 30,
                        'max' => 244,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'cover' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'HtmlEntities'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 30,
                        'max' => 244,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'mediadescription' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags'], ['name' => 'EscapeHtml'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 3,
                        'max' => 440,
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
            'options' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'SqlSanitize']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 4,
                        'max' => 250,
                    ]],
                    ['name' => 'isString'],
                ],
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
                'validators' => [['name' => 'IsArray']],
            ],
            'comments' => [
                'required' => false,
                'validators' => [['name' => 'IsArray']],
            ],
        ];

        if ($elements) {
            $specification = array_filter($specification, fn($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return (new PeerInputFilter($specification));
    }
}
