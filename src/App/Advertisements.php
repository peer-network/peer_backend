<?php
declare(strict_types=1);

namespace Fawaz\App;

use DateTime;
use Fawaz\Filter\PeerInputFilter;

class Advertisements
{
    protected string $advertisementid;
    protected string $postid;
    protected string $userid;
    protected string $status;
    protected string $timestart;
    protected string $timeend;
    protected float $tokencost;
    protected float $eurocost;
    protected float $gemsearned;
    protected int $amountlikes;
    protected int $amountviews;
    protected int $amountcomments;
    protected int $amountdislikes;
    protected int $amountreports;
    protected string $updatedat;
    protected string $createdat;
    protected array $user = [];
    protected array $post = [];
    protected string $timeBetween;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $this->timeBetween = $data['timestart'] ?? '';
            $data = $this->validate($data, $elements);
        }

        $this->advertisementid = $data['advertisementid'] ?? '';
        $this->postid = $data['postid'] ?? '';
        $this->userid = $data['userid'] ?? '';
        $this->status = $data['status'] ?? 'basic';
        $this->timestart = $data['timestart'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->timeend = $data['timeend'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->tokencost = $data['tokencost'] ?? 0.0;
        $this->eurocost = $data['eurocost'] ?? 0.0;
        $this->gemsearned = $data['gemsearned'] ?? 0.0;
        $this->amountlikes = $data['amountlikes'] ?? 0;
        $this->amountviews = $data['amountviews'] ?? 0;
        $this->amountcomments = $data['amountcomments'] ?? 0;
        $this->amountdislikes = $data['amountdislikes'] ?? 0;
        $this->amountreports = $data['amountreports'] ?? 0;
        $this->updatedat = $data['updatedat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->user = isset($data['user']) && is_array($data['user']) ? $data['user'] : [];
        $this->post = isset($data['post']) && is_array($data['post']) ? $data['post'] : [];
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'advertisementid' => $this->advertisementid,
            'postid' => $this->postid,
            'userid' => $this->userid,
            'status' => $this->status,
            'timestart' => $this->timestart,
            'timeend' => $this->timeend,
            'tokencost' => $this->tokencost,
            'eurocost' => $this->eurocost,
            'gemsearned' => $this->gemsearned,
            'amountlikes' => $this->amountlikes,
            'amountviews' => $this->amountviews,
            'amountcomments' => $this->amountcomments,
            'amountdislikes' => $this->amountdislikes,
            'amountreports' => $this->amountreports,
            'updatedat' => $this->updatedat,
            'createdat' => $this->createdat,
            'user' => $this->user,
            'post' => $this->post,
        ];
        return $att;
    }

    // Getter and Setter methods
    public function getAdvertisementId(): string
    {
        return $this->advertisementid;
    }

    public function getUserId(): string
    {
        return $this->userid;
    }

    public function setUserId(string $userid): void
    {
        $this->userid = $userid;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getTimestart(): string
    {
        return $this->timestart;
    }

    public function setTimestart(string $timestart): void
    {
        $this->timestart = $timestart;
    }

    public function getTimeend(): string
    {
        return $this->timeend;
    }

    public function setTimeend(string $timeend): void
    {
        $this->timeend = $timeend;
    }

    public function getTokencost(): float
    {
        return $this->tokencost;
    }

    public function setTokencost(float $tokencost): void
    {
        $this->tokencost = $tokencost;
    }

    public function getEurocost(): float
    {
        return $this->eurocost;
    }

    public function setEurocost(float $eurocost): void
    {
        $this->eurocost = $eurocost;
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
		$errorMessages = [];

		foreach ($validationErrors as $field => $errors) {
			foreach ($errors as $error) {
				$errorMessages[] = $error;
			}
		}

		throw new ValidationException(implode("", $errorMessages));

		return [];
	}

    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $specification = [
            'advertisementid' => [
                'required' => true,
                'validators' => [
                    ['name' => 'Uuid', 'options' => ['responsecode' => 30269]],
                    ['name' => 'isString'],
                ],
            ],
            'postid' => [
                'required' => true,
                'validators' => [
                    ['name' => 'Uuid', 'options' => ['responsecode' => 30209]],
                    ['name' => 'isString'],
                ],
            ],
            'userid' => [
                'required' => true,
                'validators' => [
                    ['name' => 'Uuid', 'options' => ['responsecode' => 30201]],
                    ['name' => 'isString'],
                ],
            ],
            'status' => [
                'required' => true,
                'validators' => [
                    ['name' => 'InArray', 'options' => [
                        'haystack' => ['basic', 'pinned'],
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'timestart' => [
                'required' => true,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                    ['name' => 'isString'],
                ],
            ],
            'timeend' => [
                'required' => true,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                    ['name' => 'TimeEndAfterTimeStart', 'options' => ['timestart' => $this->timeBetween]],
                    ['name' => 'isString'],
                ],
            ],
            'tokencost' => [
                'required' => false,
                'filters' => [['name' => 'FloatSanitize']],
                'validators' => [
                    ['name' => 'ValidateFloat', 'options' => ['min' => -5000000.0, 'max' => 5000000.0]],
                ],
            ],
            'eurocost' => [
                'required' => false,
                'filters' => [['name' => 'FloatSanitize']],
                'validators' => [
                    ['name' => 'ValidateFloat', 'options' => ['min' => -5000.0, 'max' => 5000.0]],
                ],
            ],
            'gemsearned' => [
                'required' => false,
                'filters' => [['name' => 'FloatSanitize']],
                'validators' => [
                    ['name' => 'ValidateFloat', 'options' => ['min' => 0.0, 'max' => 5000000.0]],
                ],
            ],
            'amountlikes' => [
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
            'amountdislikes' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'amountreports' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [['name' => 'IsInt']],
            ],
            'updatedat' => [
                'required' => false,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                    ['name' => 'LessThan', 'options' => ['max' => (new DateTime())->format('Y-m-d H:i:s.u'), 'inclusive' => true]],
                    ['name' => 'isString'],
                ],
            ],
            'createdat' => [
                'required' => false,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                    ['name' => 'LessThan', 'options' => ['max' => (new DateTime())->format('Y-m-d H:i:s.u'), 'inclusive' => true]],
                    ['name' => 'isString'],
                ],
            ],
            'user' => [
                'required' => false,
                'validators' => [
                    ['name' => 'IsArray'],
                ],
            ],
            'post' => [
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
}
