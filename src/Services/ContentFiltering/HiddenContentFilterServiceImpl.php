<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering;

use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategyFactory;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\HidePostsElsePlaceholder;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\PlaceholderEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\StrictlyHideEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;

class HiddenContentFilterServiceImpl  {
    /** @var string[] Severity levels in order of importance */
    private array $contentSeverityLevels;
    
    /** @var array<string,int> Map of content type => reports amount to hide */
    private array $reports_amount_to_hide_content;
    private ?string $contentFilterBy;

    public function __construct(
        private ContentType $targetContent,
        ?string $contentFilterBy = null
    ) {
        $contentFiltering = ConstantsConfig::contentFiltering();
        $this->contentSeverityLevels = $contentFiltering['CONTENT_SEVERITY_LEVELS'];
        $this->reports_amount_to_hide_content = $contentFiltering['REPORTS_COUNT_TO_HIDE_CONTENT'];
        $this->contentFilterBy = $contentFilterBy;
    }

    public static function getContentFilteringSeverityLevel(string $contentFilterBy): ?int
    {
        $contentFiltering = ConstantsConfig::contentFiltering();
        $contentSeverityLevels = $contentFiltering['CONTENT_SEVERITY_LEVELS'];

        $allowedTypes = $contentSeverityLevels;

        $key = array_search($contentFilterBy, $allowedTypes);
        return $key;
    }

    public static function getContentFilteringStringFromSeverityLevel(?int $contentFilterSeverityLevel): ?string
    {
        $contentFiltering = ConstantsConfig::contentFiltering();
        $contentSeverityLevels = $contentFiltering['CONTENT_SEVERITY_LEVELS'];
        $allowedTypes = $contentSeverityLevels;

        if ($contentFilterSeverityLevel !== null && isset($allowedTypes[$contentFilterSeverityLevel]) && !empty($allowedTypes[$contentFilterSeverityLevel])) {
            return $allowedTypes[$contentFilterSeverityLevel];
        }
        return null;
    }

    public function getReportsAmountToHideContent(ContentType $type): int {
        return $this->reports_amount_to_hide_content[$type->value];
    }

    /**
     * Decide which filtering action (if any) applies for the given content.
     *
     * Logic
     * - Uses the configured severity (via `$this->contentFilterBy`) to decide whether
     *   hidden-content rules are active. Only the strictest severity level
     *   (`$this->contentSeverityLevels[0]`) triggers actions.
     * - When `$visibilityStatus` is null, actions are decided purely by strategy
     *   if severity is active and `$showingContentReportAmount` is also null.
     * - When `$visibilityStatus` is provided, actions are decided if either:
     *   - visibility is `hidden`, or
     *   - visibility is `normal` AND report count is above the hide threshold for `$showingContent`.
     * - If a decision path requires a strategy and none is provided, returns null.
     *
     * Parameters
     * @param ContentType $showingContent              The type currently being rendered (user, post, comment).
     * @param ContentFilteringStrategy|null $strategy  Strategy that maps (target, showing) to an action.
     * @param int|null $showingContentReportAmount     Current report count for the shown content (if known).
     * @param string|null $visibilityStatus            Current visibility, e.g. 'normal' or 'hidden'.
     *
     * @return ContentFilteringAction|null             The action to take or null when no action applies.
     */
    public function getContentFilterAction(
        ContentType $showingContent,
        ?ContentFilteringStrategy $strategy,
        ?int $showingContentReportAmount = null,
        ?string $visibilityStatus = null,
    ): ?ContentFilteringAction {
        if ($visibilityStatus === null) {
            if ($showingContentReportAmount === null && $this->contentFilterBy === $this->contentSeverityLevels[0]) {
                if ($strategy === null) {
                    return null;
                }
                return $strategy::getAction($this->targetContent, $showingContent);
            }
        } else {
            if (( $visibilityStatus === "hidden" || ( $visibilityStatus === "normal" && $showingContentReportAmount >= $this->getReportsAmountToHideContent($showingContent))) && 
                $this->contentFilterBy === $this->contentSeverityLevels[0]
            ) { 
                if ($strategy === null) {
                    return null;
                }
                return $strategy::getAction($this->targetContent, $showingContent);
            }
        }
        return null;
    }

    public static function create(
        ContentFilteringCases $strategy
    ): ContentFilteringStrategy {
        return match ($strategy) {
            ContentFilteringCases::postFeed   => new HidePostsElsePlaceholder(),
            ContentFilteringCases::myprofile    => new PlaceholderEverythingContentFilteringStrategy(),
            ContentFilteringCases::searchById => new PlaceholderEverythingContentFilteringStrategy(),
            ContentFilteringCases::searchByMeta => new PlaceholderEverythingContentFilteringStrategy(),
            ContentFilteringCases::hideAll => new StrictlyHideEverythingContentFilteringStrategy()
        };
    }
}
