<?php

declare(strict_types=1);

namespace Zen\Validation;

use RuntimeException;

/**
 * Thrown by Validator::validate() when one or more field rules fail.
 */
class ValidationException extends RuntimeException
{
    /**
     * Stores the validation error messages keyed by field name.
     *
     * @param  array<string, string[]> $errors Field-to-messages map.
     *
     * @return void
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('The given data was invalid.');
    }

    /**
     * Returns the validation error messages keyed by field name.
     *
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
