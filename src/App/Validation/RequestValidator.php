<?php

declare(strict_types=1);

namespace Fawaz\App\Validation;

use Fawaz\Filter\PeerInputGenericValidator;

class RequestValidator
{
    /**
     * Validates input using a Laminas-style specification compatible with PeerInputFilter.
     */
    public static function validate(array $input): array|ValidatorErrors
    {
        $spec = ValidationSpec::auto($input);
        $inputFilter = new PeerInputGenericValidator($spec);
        $inputFilter->setData($input);

        if ($inputFilter->isValid()) {
            return $inputFilter->getValues();
        }

        return new ValidatorErrors($inputFilter->getErrorCodes());
    }
}
