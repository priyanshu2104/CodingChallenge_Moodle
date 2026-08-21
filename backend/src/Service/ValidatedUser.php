<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Represents a single user row after validation.
 *
 * Status is either 'valid' or 'invalid'.
 * When invalid, $errors contains human-readable descriptions.
 */
final class ValidatedUser
{
    public function __construct(
        public readonly string $name,
        public readonly string $surname,
        public readonly string $email,
        public readonly string $status,       // 'valid' | 'invalid'
        public readonly array  $errors = [],  // [] when valid
        public readonly int    $line   = 0,   // original CSV line number
    ) {}

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    /** Returns a plain array suitable for JSON serialisation. */
    public function toArray(): array
    {
        return [
            'name'    => $this->name,
            'surname' => $this->surname,
            'email'   => $this->email,
            'status'  => $this->status,
            'errors'  => $this->errors,
            'line'    => $this->line,
        ];
    }
}
