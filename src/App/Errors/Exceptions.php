<?php

declare(strict_types=1);

namespace Fawaz\App\Errors;

class ValidationException extends DomainException
{
}
class PermissionDeniedException extends DomainException
{
}
class NotFoundException extends DomainException
{
}
class RateLimitedException extends DomainException
{
}
class UnexpectedFailureException extends DomainException
{
}
