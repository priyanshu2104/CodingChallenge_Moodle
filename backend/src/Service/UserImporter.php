<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;

/**
 * Imports validated users into the PostgreSQL database.
 *
 * Only ValidatedUser objects with status 'valid' are inserted.
 * If the email already exists in the database (UNIQUE constraint), the row is
 * reported as skipped rather than treated as a fatal error.
 */
final class UserImporter
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Inserts all valid users and returns an import summary.
     *
     * @param  ValidatedUser[] $users
     * @return array{inserted: int, skipped: int, errors: array<int, array{line: int, email: string, reason: string}>}
     */
    public function import(array $users): array
    {
        $summary = [
            'inserted' => 0,
            'skipped'  => 0,
            'errors'   => [],
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, surname, email) VALUES (:name, :surname, :email)'
        );

        foreach ($users as $user) {
            // Skip invalid rows — they were already rejected by the validator
            if (!$user->isValid()) {
                continue;
            }

            try {
                $stmt->execute([
                    ':name'    => $user->name,
                    ':surname' => $user->surname,
                    ':email'   => $user->email,
                ]);
                $summary['inserted']++;
            } catch (PDOException $e) {
                // PostgreSQL unique_violation SQLSTATE = 23505
                if ($this->isUniqueViolation($e)) {
                    $summary['skipped']++;
                    $summary['errors'][] = [
                        'line'   => $user->line,
                        'email'  => $user->email,
                        'reason' => 'email already exists in the database',
                    ];
                } else {
                    // Re-throw unexpected DB errors
                    throw $e;
                }
            }
        }

        return $summary;
    }

    private function isUniqueViolation(PDOException $e): bool
    {
        return str_starts_with((string) $e->getCode(), '23505')
            || str_contains($e->getMessage(), 'unique constraint')
            || str_contains($e->getMessage(), 'duplicate key');
    }
}
