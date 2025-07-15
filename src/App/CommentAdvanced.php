<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class CommentAdvanced
{
    protected string $commentid;
    protected string $userid;
    protected string $postid;
    protected ?string $parentid;
    protected string $content;
    protected string $createdat;
    protected ?int $amountlikes;
    protected ?int $amountreplies;
    protected ?bool $isliked;
    protected ?int $userstatus;
    protected ?array $user = [];
    

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
        $this->amountreplies = $data['amountreplies'] ?? 0;
        $this->isliked = $data['isliked'] ?? false;
        $this->userstatus = $data['userstatus'] ?? 0;
        $this->user = isset($data['user']) && is_array($data['user']) ? $data['user'] : [];

        if($this->userstatus == 6){
            $this->content = "Comment by deleted Account";
        }
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
            'amountreplies' => $this->amountreplies,
            'isliked' => $this->isliked,
            'user' => $this->user,
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
        return [];
    }

    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
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
                        'min' => 2,
                        'max' => 200,
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
            'amountreplies' => [
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
        ];

        if ($elements) {
            $specification = array_filter($specification, fn($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return (new PeerInputFilter($specification));
    }
}
