<?php

namespace Fawaz\Services\ContentFiltering;
use Fawaz\Services\ContentFiltering\Types\ContentType;

class ContentFilterServiceActionRequset {
    public ContentType $contentTarget;
    public ContentType $showingContent;
    public ?int $showingContentReportAmount = null;
    public ?int $showingContentDismissModerationAmount = null;
    public ?string $currentUserId = null;
    public ?string $targetUserId = null;
}