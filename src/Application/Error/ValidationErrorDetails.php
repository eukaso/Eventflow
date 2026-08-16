<?php

namespace EventFlow\Application\Error;

use InvalidArgumentException;

final readonly class ValidationErrorDetails implements PublicErrorDetails
{
    /** @param array<string, list<string>> $fieldErrors */
    public function __construct(public array $fieldErrors)
    {
        if ($fieldErrors === [] || count($fieldErrors) > 20) {
            throw new InvalidArgumentException('invalid_validation_error_details');
        }
        foreach ($fieldErrors as $field => $codes) {
            if (!preg_match('/^[a-z][a-z0-9_.]{0,99}$/', $field) || $codes === [] || count($codes) > 5) {
                throw new InvalidArgumentException('invalid_validation_error_details');
            }
            foreach ($codes as $code) {
                if (!is_string($code) || !preg_match('/^[a-z][a-z0-9_]{2,99}$/', $code)) {
                    throw new InvalidArgumentException('invalid_validation_error_details');
                }
            }
        }
    }

    public function kind(): ErrorDetailKind
    {
        return ErrorDetailKind::VALIDATION;
    }

    public function toArray(): array
    {
        return ['fields' => $this->fieldErrors];
    }
}
