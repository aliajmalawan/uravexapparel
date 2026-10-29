<?php

declare(strict_types=1);

namespace App\Core;

/** Thrown by services for user-correctable input problems. */
final class ValidationException extends \RuntimeException
{
    /** @param array<string,string> $errors field => message */
    public function __construct(public readonly array $errors, string $message = 'Please correct the highlighted fields.')
    {
        parent::__construct($message);
    }

    public static function one(string $field, string $message): self
    {
        return new self([$field => $message], $message);
    }
}
