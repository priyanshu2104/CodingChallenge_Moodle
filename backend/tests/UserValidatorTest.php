<?php

declare(strict_types=1);

namespace Tests;

use App\Service\UserValidator;
use App\Service\ValidatedUser;
use PHPUnit\Framework\TestCase;

final class UserValidatorTest extends TestCase
{
    private UserValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UserValidator();
    }

    // -------------------------------------------------------------------------
    // Normalisation
    // -------------------------------------------------------------------------

    public function test_capitalises_name_and_surname(): void
    {
        $rows   = [['name' => 'john', 'surname' => 'smith', 'email' => 'john@example.com', '_line' => 2]];
        $result = $this->validator->validate($rows);

        self::assertSame('John',  $result[0]->name);
        self::assertSame('Smith', $result[0]->surname);
    }

    public function test_lowercases_email(): void
    {
        $rows   = [['name' => 'John', 'surname' => 'Smith', 'email' => 'JOHN@EXAMPLE.COM', '_line' => 2]];
        $result = $this->validator->validate($rows);

        self::assertSame('john@example.com', $result[0]->email);
    }

    public function test_capitalises_hyphenated_names(): void
    {
        $rows   = [['name' => 'anne-marie', 'surname' => 'smith', 'email' => 'anne@example.com', '_line' => 2]];
        $result = $this->validator->validate($rows);

        self::assertSame('Anne-Marie', $result[0]->name);
    }

    // -------------------------------------------------------------------------
    // Valid rows
    // -------------------------------------------------------------------------

    public function test_valid_row_has_valid_status(): void
    {
        $rows   = [['name' => 'John', 'surname' => 'Smith', 'email' => 'john@example.com', '_line' => 2]];
        $result = $this->validator->validate($rows);

        self::assertSame('valid', $result[0]->status);
        self::assertEmpty($result[0]->errors);
    }

    // -------------------------------------------------------------------------
    // Validation errors
    // -------------------------------------------------------------------------

    public function test_invalid_email_is_rejected(): void
    {
        $rows   = [['name' => 'John', 'surname' => 'Smith', 'email' => 'not-an-email', '_line' => 2]];
        $result = $this->validator->validate($rows);

        self::assertSame('invalid', $result[0]->status);
        self::assertNotEmpty($result[0]->errors);
    }

    public function test_missing_name_is_rejected(): void
    {
        $rows   = [['name' => '', 'surname' => 'Smith', 'email' => 'john@example.com', '_line' => 2]];
        $result = $this->validator->validate($rows);

        self::assertSame('invalid', $result[0]->status);
        $this->assertErrorContains('name is required', $result[0]);
    }

    public function test_missing_surname_is_rejected(): void
    {
        $rows   = [['name' => 'John', 'surname' => '', 'email' => 'john@example.com', '_line' => 2]];
        $result = $this->validator->validate($rows);

        self::assertSame('invalid', $result[0]->status);
        $this->assertErrorContains('surname is required', $result[0]);
    }

    public function test_missing_email_is_rejected(): void
    {
        $rows   = [['name' => 'John', 'surname' => 'Smith', 'email' => '', '_line' => 2]];
        $result = $this->validator->validate($rows);

        self::assertSame('invalid', $result[0]->status);
        $this->assertErrorContains('email is required', $result[0]);
    }

    // -------------------------------------------------------------------------
    // Deduplication within the batch
    // -------------------------------------------------------------------------

    public function test_duplicate_email_in_batch_is_rejected(): void
    {
        $rows = [
            ['name' => 'John', 'surname' => 'Smith', 'email' => 'john@example.com', '_line' => 2],
            ['name' => 'Jane', 'surname' => 'Smith', 'email' => 'john@example.com', '_line' => 3],
        ];
        $result = $this->validator->validate($rows);

        self::assertSame('valid',   $result[0]->status);
        self::assertSame('invalid', $result[1]->status);
        $this->assertErrorContains('duplicate email', $result[1]);
    }

    public function test_duplicate_detection_is_case_insensitive(): void
    {
        $rows = [
            ['name' => 'John', 'surname' => 'Smith', 'email' => 'john@example.com',  '_line' => 2],
            ['name' => 'Jane', 'surname' => 'Smith', 'email' => 'JOHN@EXAMPLE.COM',  '_line' => 3],
        ];
        $result = $this->validator->validate($rows);

        self::assertSame('valid',   $result[0]->status);
        self::assertSame('invalid', $result[1]->status);
    }

    // -------------------------------------------------------------------------
    // Edge cases from the provided users.csv
    // -------------------------------------------------------------------------

    public function test_bad_double_at_email(): void
    {
        $rows   = [['name' => 'bad', 'surname' => 'format', 'email' => 'bad@@example.com', '_line' => 49]];
        $result = $this->validator->validate($rows);

        self::assertSame('invalid', $result[0]->status);
    }

    public function test_missing_domain_email(): void
    {
        $rows   = [['name' => 'missing', 'surname' => 'domain', 'email' => 'missing@', '_line' => 43]];
        $result = $this->validator->validate($rows);

        self::assertSame('invalid', $result[0]->status);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertErrorContains(string $needle, ValidatedUser $user): void
    {
        $found = false;
        foreach ($user->errors as $error) {
            if (str_contains($error, $needle)) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found, "Expected error message containing \"{$needle}\", got: " . implode(', ', $user->errors));
    }
}
