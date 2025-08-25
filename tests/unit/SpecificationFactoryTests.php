<?php

use Fawaz\App\Specs\ContentFilteringSpecificationFactory;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use PHPUnit\Framework\TestCase;

final class SpecificationFactoryTests extends TestCase
{
    private $contentFilterService;
    private ContentFilteringSpecificationFactory $factory;

    protected function setUp(): void
    {
        // Mock the ContentFilterServiceImpl
        $this->contentFilterService = $this->createMock(ContentFilterServiceImpl::class);

        // Configure return values for all types
        $this->contentFilterService
            ->method('getReportsAmountToHideContent')
            ->willReturnMap([
                [ContentType::user, 10],
                [ContentType::post, 20],
                [ContentType::comment, 30],
            ]);

        $this->contentFilterService
            ->method('moderationsDismissAmountToRestoreContent')
            ->willReturnMap([
                [ContentType::user, 1],
                [ContentType::post, 2],
                [ContentType::comment, 3],
            ]);

        $this->factory = new ContentFilteringSpecificationFactory($this->contentFilterService);
    }

    public function testReplaceWithPlaceholderReturnsNull(): void
    {
        $result = $this->factory->build(ContentType::user, ContentFilteringAction::replaceWithPlaceholder);
        $this->assertNull($result);
    }

    public function testHideContentUser(): void
    {
        $result = $this->factory->build(ContentType::user, ContentFilteringAction::hideContent);

        $this->assertInstanceOf(SpecificationSQLData::class, $result);
        $this->assertEquals(
            ['((ui.reports < :user_report_amount_to_hide OR ui.count_content_moderation_dismissed > :user_dismiss_moderation_amount) OR u.userid = :currentUserId)'],
            $result->whereClauses
        );
        $this->assertEquals(
            [
                'user_report_amount_to_hide' => 10,
                'user_dismiss_moderation_amount' => 1,
            ],
            $result->paramsToPrepare
        );
    }

    public function testHideContentPost(): void
    {
        $result = $this->factory->build(ContentType::post, ContentFilteringAction::hideContent);

        $this->assertInstanceOf(SpecificationSQLData::class, $result);
        $this->assertEquals(
            ['((pi.reports < :post_report_amount_to_hide OR pi.count_content_moderation_dismissed > :post_dismiss_moderation_amount) OR p.userid = :currentUserId)'],
            $result->whereClauses
        );
        $this->assertEquals(
            [
                'post_report_amount_to_hide' => 20,
                'post_dismiss_moderation_amount' => 2,
            ],
            $result->paramsToPrepare
        );
    }

    public function testHideContentComment(): void
    {
        $result = $this->factory->build(ContentType::comment, ContentFilteringAction::hideContent);

        $this->assertInstanceOf(SpecificationSQLData::class, $result);
        $this->assertEquals(
            ['((ci.reports < :comment_report_amount_to_hide OR ci.count_content_moderation_dismissed > :comment_dismiss_moderation_amount) OR c.userid = :currentUserId)'],
            $result->whereClauses
        );
        $this->assertEquals(
            [
                'comment_report_amount_to_hide' => 30,
                'comment_dismiss_moderation_amount' => 3,
            ],
            $result->paramsToPrepare
        );
    }
}
