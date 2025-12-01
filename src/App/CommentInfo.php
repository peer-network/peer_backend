<?php

declare(strict_types=1);

namespace Fawaz\App;

use DateTime;
use Fawaz\App\Models\Core\Model;
use Fawaz\Filter\PeerInputFilter;

class CommentInfo extends Model
{
    protected string $commentid;
    protected string $userid;
    protected int $likes;
    protected int $activeReports;
    protected int $totalreports;
    protected int $comments;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->commentid = $data['commentid'] ?? '';
        $this->userid = $data['userid'] ?? '';
        $this->likes = $data['likes'] ?? 0;
        $this->activeReports = $data['reports'] ?? 0;
        $this->totalreports = $data['totalreports'] ?? 0;
        $this->comments = $data['comments'] ?? 0;
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'commentid' => $this->commentid,
            'userid' => $this->userid,
            'likes' => $this->likes,
            'reports' => $this->activeReports,
            'totalreports' => $this->totalreports,
            'comments' => $this->comments,
        ];
        return $att;
    }

    // Getter and Setter methods
    public function getCommentId(): string
    {
        return $this->commentid;
    }

    public function setCommentId(string $commentid): void
    {
        $this->commentid = $commentid;
    }

    public function getOwnerId(): string
    {
        return $this->userid;
    }

    public function setOwnerId(string $userid): void
    {
        $this->userid = $userid;
    }

    public function getLikes(): int
    {
        return $this->likes;
    }

    public function setLikes(int $likes): void
    {
        $this->likes = $likes;
    }

    public function getActiveReports(): int
    {
        return $this->activeReports;
    }

    public function setReports(int $reports): void
    {
        $this->activeReports = $reports;
    }

    public function getTotalReports(): int
    {
        return $this->totalreports;
    }
    public function setTotalReports(int $totalreports): void
    {
        $this->totalreports = $totalreports;
    }
    public function getComments(): int
    {
        return $this->comments;
    }

    public function setComments(int $comments): void
    {
        $this->comments = $comments;
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
        $specification = [
            'commentid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'likes' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'reports' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'comments' => [
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

    // Table name
    public static function table(): string
    {
        return 'comment_info';
    }
}
