<?php

declare(strict_types=1);

namespace Fawaz\App;

use DateTime;
use Fawaz\App\Models\Core\Model;
use Fawaz\Filter\PeerInputFilter;
use Fawaz\Database\Interfaces\Hashable;
use Fawaz\Utils\HashObject;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;

class User extends Model implements Hashable, ProfileReplaceable
{
    use HashObject;

    protected string $uid;
    protected string $email;
    protected string $referral_uuid;
    protected string $username;
    protected string $password;
    protected int $status;
    protected int $verified;
    protected int $slug;
    protected int $roles_mask;
    protected string $ip;
    protected string $img;
    protected string $biography;
    protected string $createdat;
    protected string $updatedat;
    protected ?int $activeReports = null;
    protected ?string $visibilityStatus = null;

    // Constructor
    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->uid = $data['uid'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->username = $data['username'] ?? '';
        $this->password = $data['password'] ?? '';
        $this->status = $data['status'] ?? 0;
        $this->verified = $data['verified'] ?? 0;
        $this->slug = $data['slug'] ?? 0;
        $this->roles_mask = $data['roles_mask'] ?? 0;
        $this->ip = $data['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $this->img = $data['img'] ?? '';
        $this->biography = $data['biography'] ?? '';
        $this->createdat = $data['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->updatedat = $data['updatedat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
        $this->referral_uuid = $data['referral_uuid'] ?? $this->uid;
        $this->activeReports= $data['user_reports'] ?? ($data['reports'] ?? null);
        $this->visibilityStatus = $data['visibility_status'] ?? null;
    }

    // Array Copy methods
    public function getArrayCopy(): array
    {
        $att = [
            'uid' => $this->uid,
            'email' => $this->email,
            'username' => $this->username,
            'password' => $this->password,
            'status' => $this->status,
            'verified' => $this->verified,
            'slug' => $this->slug,
            'roles_mask' => $this->roles_mask,
            'ip' => $this->ip,
            'img' => $this->img,
            'biography' => $this->biography,
            'createdat' => $this->createdat,
            'updatedat' => $this->updatedat,
        ];
        return $att;
    }

    // Array Copy methods
    public function getUpdatePass(): array
    {
        $att = [
            'uid' => $this->uid,
            'password' => $this->password,
            'ip' => $this->ip,
            'updatedat' => (new DateTime())->format('Y-m-d H:i:s.u'),
        ];
        return $att;
    }

    // Array Copy methods
    public function getArrayProfile(): array
    {
        $att = [
            'uid' => $this->uid,
            'username' => $this->username,
            'status' => $this->status,
            'slug' => $this->slug,
            'img' => $this->img,
            'biography' => $this->biography,
        ];
        return $att;
    }

    // Array Update methods
    public function updateProfil(array $data): void
    {
        $data = $this->validate($data, ['img', 'biography', 'isprivate']);

        $this->img = $data['img'] ?? $this->img;
        $this->biography = $data['biography'] ?? $this->biography;
    }

    // Array Update methods
    public function update(array $data): void
    {
        $data = $this->validate($data, ['username', 'email', 'password']);

        $this->username = $data['username'] ?? $this->username;
        $this->email = $data['email'] ?? $this->email;
        $this->password = $data['password'] ?? $this->password;
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

    public function getReferralUuid(): string
    {
        return $this->referral_uuid;
    }

    public function setReferralUuid(string $referral_uuid): void
    {
        $this->referral_uuid = $referral_uuid;
    }

    public function getSlug(): int
    {
        return $this->slug;
    }

    public function setSlug(int $slug): void
    {
        $this->slug = $slug;
    }

    public function getName(): string
    {
        return $this->username;
    }

    public function setName(string $name): void
    {
        $this->username = $name;
    }

    public function getMail(): string
    {
        return $this->email;
    }

    public function setMail(string $email): void
    {
        $this->email = $email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getVerified(): int
    {
        return $this->verified;
    }

    public function setVerified(int $verified): void
    {
        $this->verified = $verified;
    }

    // ProfileReplaceable: roles mask accessor with expected name
    public function getRolesmask(): int
    {
        return (int)$this->roles_mask;
    }

    public function getRoles(): int|null
    {
        return $this->roles_mask;
    }

    public function setRoles(int $roles): void
    {
        $this->roles_mask = $roles;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip = null): void
    {
        $this->ip = filter_var($ip, FILTER_VALIDATE_IP) ?: ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function getImg(): ?string
    {
        return $this->img;
    }

    public function setImg(?string $img): void
    {
        $this->img = $img;
    }

    public function getBiography(): ?string
    {
        return $this->biography;
    }

    public function setBiography(?string $biography): void
    {
        $this->biography = $biography;
    }

    // ContentFiltering capabilities
    public function getActiveReports(): ?int
    {
        return $this->activeReports;
    }

    public function visibilityStatus(): string
    {
        return (string)($this->visibilityStatus ?? '');
    }

    public function updateBio(string $biography): void
    {
        $this->biography = $biography;
    }

    public function setProfilePicture(string $imgPath): void
    {
        $this->img = $imgPath;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdat;
    }

    public function setCreatedAt(?string $createdat): void
    {
        $this->createdat = $createdat;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedat;
    }

    public function setUpdatedAt(): void
    {
        $this->updatedat = (new DateTime())->format('Y-m-d H:i:s.u');
    }

    // Password Verify methods
    public function verifyPassword(string $password): bool
    {
        if (\password_verify($password, $this->password)) {

            if (\password_needs_rehash($this->password, \PASSWORD_ARGON2ID, ['memory_cost' => 2048, 'time_cost' => 4, 'threads' => 1])) {

                $newHash = \password_hash($password, \PASSWORD_ARGON2ID, ['memory_cost' => 2048, 'time_cost' => 4, 'threads' => 1]);

                $this->password = $newHash;
            }

            return true;
        }

        return false;
    }

    // Password Update methods
    public function validatePass(array $data): void
    {
        $data = $this->validate($data, ['password']);

        $this->password = $data['password'] ?? $this->password;
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
            'email' => [
                'required' => true,
                'filters' => [['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'EmailAddress'],
                    ['name' => 'isString'],
                ],
            ],
            'username' => [
                'required' => true,
                'filters' => [['name' => 'EscapeHtml'], ['name' => 'HtmlEntities']],
                'validators' => [
                    ['name' => 'validateUsername'],
                ],
            ],
            'password' => [
                'required' => true,
                'validators' => [
                    ['name' => 'validatePassword'],
                ],
            ],
            'status' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => 0, 'max' => 10]],
                ],
            ],
            'verified' => [
                'required' => false,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => 0, 'max' => 1]],
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
            'roles_mask' => [
                'required' => true,
                'filters' => [['name' => 'ToInt']],
                'validators' => [
                    ['name' => 'validateIntRange', 'options' => ['min' => 0, 'max' => 1048576]],
                ],
            ],
            'ip' => [
                'required' => true,
                'validators' => [
                    ['name' => 'IsIp', 'options' => ['ipv4' => true, 'ipv6' => true]],
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
            'createdat' => [
                'required' => false,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                ],
            ],
            'updatedat' => [
                'required' => false,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
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
        $content = implode('|', [
            $this->email,
            $this->img,
            $this->username,
            $this->biography
        ]);
        return $content;
    }

    public function hashValue(): string
    {
        return $this->hashObject($this);
    }

    // Table name for the model
    protected static function table(): string
    {
        return 'users';
    }

}
