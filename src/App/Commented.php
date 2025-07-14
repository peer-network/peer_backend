<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class Commented
{
    protected string $commentid;
    protected string $userid;
    protected string $postid;
    protected ?string $parentid;
    protected string $content;
    protected string $createdat;
    protected ?int $amountlikes;
    protected ?bool $isliked;
    protected ?array $user = [];
    protected ?array $subcomments = [];

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->commentid = $data['commentid'] ?? '';
        $this->userid = $data['userid'] ?? '';
        $this->postid = $data['postid'] ?? '';
        $this->parentid = $data['parentid'] ?? null;
        $this->content = $data['content'] ?? '';
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->amountlikes = $data['amountlikes'] ?? 0;
        $this->isliked = $data['isliked'] ?? false;
        $this->user = isset($data['user']) && is_array($data['user']) ? $data['user'] : [];
        $this->subcomments = isset($data['subcomments']) && is_array($data['subcomments']) ? $data['subcomments'] : [];
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'commentid' => $this->commentid,
            'userid' => $this->userid,
            'postid' => $this->postid,
            'parentid' => $this->parentid,
            'content' => $this->content,
            'createdat' => $this->createdat,
            'amountlikes' => $this->amountlikes,
            'isliked' => $this->isliked,
            'user' => $this->user,
            'subcomments' => $this->subcomments,
        ];
        return $att;
    }

    // Getter and Setter methods
    public function getId(): string
    {
        return $this->commentid;
    }

    public function getPostId(): string
    {
        return $this->postid;
    }

    public function setPostId(string $postid): void
    {
        $this->postid = $postid;
    }

    public function getParentId(): ?string
    {
        return $this->parentid;
    }

    public function setParentId(?string $parentid): void
    {
        $this->parentid = $parentid;
    }

    public function getUserId(): string
    {
        return $this->userid;
    }

    public function setUserId(string $userid): void
    {
        $this->userid = $userid;
    }

    public function getOwnerId(): string
    {
        return $this->userid;
    }

    public function setOwnerId(string $userid): void
    {
        $this->userid = $userid;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getSubComments(): string
    {
        return $this->subcomments;
    }

    public function setSubComments(string $subcomments): void
    {
        $this->subcomments = $subcomments;
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
            foreach ($errors as $error) {
                $errorMessages[] = $error;
            }
            $errorMessageString = implode("", $errorMessages);
            
            throw new ValidationException($errorMessageString);
        }
    }

    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $commentConfig = constants()::comment();
        $specification = [
            'commentid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'postid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'parentid' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
            ],
            'content' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim']],
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => $commentConfig['CONTENT']['MIN_LENGTH'],
                        'max' => $commentConfig['CONTENT']['MAX_LENGTH'],
                    ]],
                    ['name' => 'IsString'],
                ],
            ],
            'createdat' => [
                'required' => false,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                    ['name' => 'LessThan', 'options' => ['max' => (new DateTime())->format('Y-m-d H:i:s.u'), 'inclusive' => true]],
                ],
            ],
            'amountlikes' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'isliked' => [
                'required' => false,
                'filters' => [['name' => 'Boolean']],
            ],
            'user' => [
                'required' => false,
                'validators' => [['name' => 'IsArray']],
            ],
            'subcomments' => [
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
