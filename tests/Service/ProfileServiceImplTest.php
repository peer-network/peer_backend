<?php

declare(strict_types=1);

namespace Tests\Service;

use Fawaz\App\Interfaces\ProfileServiceImpl;
use Fawaz\App\Profile;
use Fawaz\App\Specs\ContentFilteringSpecsFactory;
use Fawaz\Database\Interfaces\ProfileRepository;
use Fawaz\Utils\ErrorResponse;
use Fawaz\Utils\PeerNullLogger;
use PHPUnit\Framework\TestCase;

final class ProfileServiceImplTest extends TestCase
{
    private static function makeProfile(
        string $uid,
        string $username,
        int $status = 0,
        int $reports = 0,
        string $visibility = 'normal'
    ): Profile {
        return new Profile([
            'uid' => $uid,
            'username' => $username,
            'status' => $status,
            'user_reports' => $reports,
            'visibility_status' => $visibility,
        ], validate: false);
    }

    private function makeService(ProfileRepository $repo): ProfileServiceImpl
    {
        return new ProfileServiceImpl(
            new PeerNullLogger(),
            $repo,
            new ContentFilteringSpecsFactory()
        );
    }

    public function testReturnsErrorWhenProfileNotFound(): void
    {
        $repo = new class implements ProfileRepository {
            public function fetchAll(string $currentUserId, array $args = []): array { return []; }
            public function fetchAllAdvance(array $args = [], ?string $currentUserId = null, ?string $contentFilterBy = null): array { return []; }
            public function fetchProfileData(string $userid, string $currentUserId, array $specifications): ?Profile { return null; }
            public function fetchByIds(array $userIds, string $currentUserId, array $specifications = []): array { return []; }
        };

        $service = $this->makeService($repo);
        $service->setCurrentUserId('me');

        $result = $service->profile(['userid' => 'someone-else']);

        $this->assertInstanceOf(ErrorResponse::class, $result);
        $this->assertSame('error', $result->response['status']);
        $this->assertSame('31007', $result->response['ResponseCode']);
    }

    public function testReturnsProfileWhenFoundAndVisible(): void
    {
        $expected = self::makeProfile('u1', 'alice', status: 0, reports: 0, visibility: 'normal');

        $repo = new class($expected) implements ProfileRepository {
            public function __construct(private Profile $profile) {}
            public function fetchAll(string $currentUserId, array $args = []): array { return []; }
            public function fetchAllAdvance(array $args = [], ?string $currentUserId = null, ?string $contentFilterBy = null): array { return []; }
            public function fetchProfileData(string $userid, string $currentUserId, array $specifications): Profile { return $this->profile; }
            public function fetchByIds(array $userIds, string $currentUserId, array $specifications = []): array { return []; }
        };

        $service = $this->makeService($repo);
        $service->setCurrentUserId('me');

        $result = $service->profile(['userid' => 'u1']);

        $this->assertInstanceOf(Profile::class, $result);
        $this->assertSame('u1', $result->getUserId());
        $this->assertSame('alice', $result->getName());
        $this->assertSame(0, $result->getReports());
        $this->assertSame('normal', $result->visibilityStatus());
    }

    public function testHiddenProfileGetsPlaceholdered(): void
    {
        // Profile that should be placeholdered by HiddenContentFilterSpec
        $profile = self::makeProfile('u2', 'bob', status: 0, reports: 5, visibility: 'hidden');

        $repo = new class($profile) implements ProfileRepository {
            public function __construct(private Profile $profile) {}
            public function fetchAll(string $currentUserId, array $args = []): array { return []; }
            public function fetchAllAdvance(array $args = [], ?string $currentUserId = null, ?string $contentFilterBy = null): array { return []; }
            public function fetchProfileData(string $userid, string $currentUserId, array $specifications): Profile { return $this->profile; }
            public function fetchByIds(array $userIds, string $currentUserId, array $specifications = []): array { return []; }
        };

        $service = $this->makeService($repo);
        $service->setCurrentUserId('viewer');

        // Pass contentFilterBy to enable replacement logic path used in app
        $result = $service->profile(['userid' => 'u2', 'contentFilterBy' => 'MYGRANDMALIKES']);

        $this->assertInstanceOf(Profile::class, $result);

        // After placeholdering, username should be replaced to hidden_account
        $this->assertSame('hidden_account', $result->getName());
        // Biography and image should also be replaced to non-empty placeholder values
        $this->assertNotEmpty($result->getBiography());
        $this->assertNotEmpty($result->getImg());
    }
}
