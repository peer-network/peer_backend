<?php
declare(strict_types=1);

namespace Fawaz\Filter;

use Fawaz\Filter\ExceptionInterface;

class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
}
