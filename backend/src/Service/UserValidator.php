<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Validates and normalises raw CSV rows into ValidatedUser objects.
 *
 * Normalisation rules applied to every row:
 *  - name    → ucfirst (capitalised)
 *  - surname → ucfirst (capitalised)
 *  - email   → strtolower
 *
 * Validation rules:
 *  - name, surname, and email must be non-empty
 *  - email must pass filter_var(FILTER_VALIDATE_EMAIL)
 *  - email must be unique within the batch (first occurrence wins)
 */
final class UserValidator
{
    /**
     * Validates a collection of raw rows from CsvParser.
     *
     * @param  array<int, array<string, string>> $rows
     * @return ValidatedUser[]
     */
    public function validate(array $rows): array
    {
        $seenEmails  = [];
        $validated   = [];

        foreach ($rows as $row) {
            $line    = (int) ($row['_line'] ?? 0);
            $name    = $row['name']    ?? '';
            $surname = $row['surname'] ?? '';
            $email   = $row['email']   ?? '';

            // Normalise
            $name    = $this->capitaliseName($name);
            $surname = $this->capitaliseName($surname);
            $email   = strtolower($email);

            // Collect errors
            $errors = [];

            if ($name === '') {
                $errors[] = 'name is required';
            }

            if ($surname === '') {
                $errors[] = 'surname is required';
            }

            if ($email === '') {
                $errors[] = 'email is required';
            } elseif (!$this->isValidEmail($email)) {
                $errors[] = "invalid email address: \"{$email}\"";
            } elseif (isset($seenEmails[$email])) {
                $errors[] = "duplicate email: \"{$email}\" (first seen on line {$seenEmails[$email]})";
            }

            if ($errors === []) {
                $seenEmails[$email] = $line;
                $validated[] = new ValidatedUser($name, $surname, $email, 'valid', [], $line);
            } else {
                $validated[] = new ValidatedUser($name, $surname, $email, 'invalid', $errors, $line);
            }
        }

        return $validated;
    }

    /**
     * Capitalises the first letter of a name, lowercases the rest.
     * Handles hyphenated names (e.g. "anne-marie" → "Anne-Marie").
     */
    private function capitaliseName(string $name): string
    {
        if ($name === '') {
            return '';
        }

        return implode('-', array_map(
            fn(string $part): string => ucfirst(strtolower($part)),
            explode('-', $name)
        ));
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
