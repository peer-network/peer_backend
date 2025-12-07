<?php

declare(strict_types=1);

namespace Fawaz\GraphQL\Support;

use Fawaz\GraphQL\Context;
use Fawaz\App\Validation\RequestValidator;
use Fawaz\App\Validation\ValidatorErrors;

trait ResolverHelpers
{
    /**
     * Wrap a resolver with simple auth check using Context token.
     * If missing/invalid, returns a standard error payload.
     *
     * @param array<int,string>|null $roles Reserved for future role checks
     * @param callable $resolver function(mixed $root, array $args, Context $ctx): mixed
     * @return callable
     */
    protected function withAuth(?array $roles, callable $resolver): callable
    {
        return function (mixed $root, array $args, Context $context) use ($resolver) {
            if ($context->token === null || $context->token === '') {
                return [
                    'status' => 'error',
                    'ResponseCode' => 60501, // not authenticated
                ];
            }
            return $resolver($root, $args, $context);
        };
    }

    /**
     * Wrap a resolver with RequestValidator validation.
     * Passes validated args to the inner resolver.
     *
     * @param array<string,mixed>|null $rules
     * @param callable $resolver function(mixed $root, array $validatedArgs, Context $ctx): mixed
     * @return callable
     */
    protected function withValidation(?array $rules, callable $resolver): callable
    {
        return function (mixed $root, array $args, Context $context) use ($rules, $resolver) {
            $validated = RequestValidator::validate($args, $rules ?? []);
            if ($validated instanceof ValidatorErrors) {
                return [
                    'status' => 'error',
                    'ResponseCode' => $validated->errors[0] ?? 30101,
                ];
            }
            return $resolver($root, $validated, $context);
        };
    }
}
