<?php

declare(strict_types=1);

namespace Tests;

use App\Service\UserImporter;
use App\Service\ValidatedUser;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class UserImporterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(
        string $name,
        string $surname,
        string $email,
        string $status = 'valid',
        int    $line   = 2
    ): ValidatedUser {
        return new ValidatedUser($name, $surname, $email, $status, [], $line);
    }

    /** Returns a PDO mock whose prepare() returns a statement mock. */
    private function makePdo(PDOStatement&MockObject $stmt): PDO&MockObject
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        return $pdo;
    }

    /** Returns a statement mock where execute() succeeds (returns true). */
    private function makeStmtSuccess(): PDOStatement&MockObject
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        return $stmt;
    }

    /** Returns a statement mock where execute() throws a unique-violation PDOException. */
    private function makeStmtUniqueViolation(): PDOStatement&MockObject
    {
        $stmt = $this->createMock(PDOStatement::class);
        // PHP's PDOException surfaces the SQLSTATE in getMessage(); the numeric
        // constructor arg must be an int. We embed '23505' in the message to
        // mimic real PostgreSQL behaviour that UserImporter::isUniqueViolation() checks.
        $e = new PDOException('SQLSTATE[23505]: duplicate key value violates unique constraint');
        $stmt->method('execute')->willThrowException($e);
        return $stmt;
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_valid_users_are_inserted(): void
    {
        $users = [
            $this->makeUser('John', 'Smith', 'john@example.com'),
            $this->makeUser('Jane', 'Doe',   'jane@example.com'),
        ];

        $stmt = $this->makeStmtSuccess();
        $stmt->expects($this->exactly(2))->method('execute');

        $importer = new UserImporter($this->makePdo($stmt));
        $summary  = $importer->import($users);

        self::assertSame(2, $summary['inserted']);
        self::assertSame(0, $summary['skipped']);
        self::assertEmpty($summary['errors']);
    }

    public function test_invalid_users_are_skipped_without_db_call(): void
    {
        $users = [
            $this->makeUser('Invalid', 'User', 'not-an-email', 'invalid'),
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->never())->method('execute');

        $importer = new UserImporter($this->makePdo($stmt));
        $summary  = $importer->import($users);

        self::assertSame(0, $summary['inserted']);
        self::assertSame(0, $summary['skipped']);
    }

    public function test_unique_violation_is_reported_as_skipped_not_fatal(): void
    {
        $users = [
            $this->makeUser('John', 'Smith', 'john@example.com', 'valid', 2),
        ];

        $importer = new UserImporter($this->makePdo($this->makeStmtUniqueViolation()));
        $summary  = $importer->import($users);

        self::assertSame(0, $summary['inserted']);
        self::assertSame(1, $summary['skipped']);
        self::assertCount(1, $summary['errors']);
        self::assertSame('john@example.com', $summary['errors'][0]['email']);
        self::assertSame(2,                  $summary['errors'][0]['line']);
        self::assertStringContainsString('already exists', $summary['errors'][0]['reason']);
    }

    public function test_mixed_valid_and_invalid_users(): void
    {
        $users = [
            $this->makeUser('John',    'Smith',   'john@example.com',  'valid',   2),
            $this->makeUser('Invalid', 'User',    'bad-email',          'invalid', 3),
            $this->makeUser('Jane',    'Doe',     'jane@example.com',  'valid',   4),
        ];

        $stmt = $this->makeStmtSuccess();
        $stmt->expects($this->exactly(2))->method('execute'); // only 2 valid rows

        $importer = new UserImporter($this->makePdo($stmt));
        $summary  = $importer->import($users);

        self::assertSame(2, $summary['inserted']);
        self::assertSame(0, $summary['skipped']);
        self::assertEmpty($summary['errors']);
    }

    public function test_empty_user_list_returns_zeroed_summary(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->never())->method('execute');

        $importer = new UserImporter($this->makePdo($stmt));
        $summary  = $importer->import([]);

        self::assertSame(0, $summary['inserted']);
        self::assertSame(0, $summary['skipped']);
        self::assertEmpty($summary['errors']);
    }

    public function test_non_unique_violation_exception_is_rethrown(): void
    {
        $users = [
            $this->makeUser('John', 'Smith', 'john@example.com'),
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willThrowException(
            new PDOException('SQLSTATE[08006]: connection refused')
        );

        $this->expectException(PDOException::class);

        $importer = new UserImporter($this->makePdo($stmt));
        $importer->import($users);
    }
}
