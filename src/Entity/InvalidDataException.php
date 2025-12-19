<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use Exception;
use Throwable;

/**
 * Exception thrown when entity data validation fails.
 *
 * Contains an array of validation errors keyed by field name.
 */
class InvalidDataException extends Exception {

    public function __construct(
        protected array $errors,
        $message = "",
        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the validation errors.
     */
    public function getErrors(): array {
        return $this->errors;
    }
}
