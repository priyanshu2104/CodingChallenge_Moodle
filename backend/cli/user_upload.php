#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * user_upload.php — CLI for importing users from a CSV file into PostgreSQL.
 *
 * Usage:
 *   php user_upload.php --file <path>                Import users from <path>
 *   php user_upload.php --file <path> --dry-run       Validate only, no DB writes
 *   php user_upload.php --file <path> --strict        Reject unexpected columns/row shape
 *   php user_upload.php --create-table               Create/rebuild the users table
 *   php user_upload.php --help                       Show help
 */

// Locate the backend root (this script lives in backend/cli/)
$backendRoot = dirname(__DIR__);

require $backendRoot . '/vendor/autoload.php';

use App\Database\Connection;
use App\Database\Schema;
use App\Service\CsvParser;
use App\Service\UserImporter;
use App\Service\UserValidator;
use Dotenv\Dotenv;

// ─── Load environment ────────────────────────────────────────────────────────

$dotenv = Dotenv::createImmutable($backendRoot);
$dotenv->safeLoad(); // won't throw if .env doesn't exist (env vars may be set externally)

// ─── Parse CLI arguments ─────────────────────────────────────────────────────

$opts = getopt('', ['file:', 'dry-run', 'create-table', 'help', 'strict'], $restIndex);

if (isset($opts['help']) || $opts === false) {
    printHelp();
    exit(0);
}

// Detect unknown flags (getopt silently ignores them; we should not)
$knownFlags = ['--file', '--dry-run', '--create-table', '--help', '--strict'];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--') && !in_array(strtok($arg, '='), $knownFlags, true)) {
        err("Unknown option: {$arg}");
        err('Run with --help to see available options.');
        exit(1);
    }
}

// ─── --create-table ───────────────────────────────────────────────────────────

if (isset($opts['create-table'])) {
    try {
        $pdo = Connection::get();
        Schema::createUsersTable($pdo, drop: true);
        out('✔  Users table created (or recreated) successfully.');
    } catch (Throwable $e) {
        err("Failed to create table: {$e->getMessage()}");
        exit(1);
    }

    // Allow --create-table to be combined with --file
    if (!isset($opts['file'])) {
        exit(0);
    }
}

// ─── --file (required for import) ────────────────────────────────────────────

if (!isset($opts['file'])) {
    err('Error: --file <path> is required. Use --help for usage information.');
    exit(1);
}

$filePath = $opts['file'];
$dryRun   = isset($opts['dry-run']);
$strict   = isset($opts['strict']);

// ─── Parse ───────────────────────────────────────────────────────────────────

$parser = new CsvParser($strict);

if ($strict) {
    out('[Strict mode] Unexpected columns and row shape mismatches will be rejected.');
}

try {
    $rows = $parser->parseFile($filePath);
} catch (Throwable $e) {
    err("Failed to parse CSV: {$e->getMessage()}");
    exit(1);
}

out(sprintf('Parsed %d row(s) from %s', count($rows), basename($filePath)));

// ─── Validate ────────────────────────────────────────────────────────────────

$validator = new UserValidator();
$validated = $validator->validate($rows);

$valid   = array_filter($validated, fn($u) => $u->isValid());
$invalid = array_filter($validated, fn($u) => !$u->isValid());

out(sprintf(
    "\nUsers found: %d\nValid:       %d\nInvalid:     %d",
    count($validated),
    count($valid),
    count($invalid)
));

// Print preview table
out("\n" . str_repeat('-', 72));
out(sprintf('%-4s %-15s %-15s %-30s %s', 'Line', 'Name', 'Surname', 'Email', 'Status'));
out(str_repeat('-', 72));

foreach ($validated as $user) {
    $statusLabel = $user->isValid() ? 'Valid' : 'Invalid';
    out(sprintf(
        '%-4s %-15s %-15s %-30s %s',
        $user->line,
        truncate($user->name, 14),
        truncate($user->surname, 14),
        truncate($user->email, 29),
        $statusLabel
    ));

    if (!$user->isValid()) {
        foreach ($user->errors as $error) {
            out(sprintf('     → %s', $error));
        }
    }
}

out(str_repeat('-', 72));

// ─── Dry-run stops here ───────────────────────────────────────────────────────

if ($dryRun) {
    out("\n[Dry run] No changes made to the database.");
    exit(0);
}

// ─── Import ───────────────────────────────────────────────────────────────────

if (count($valid) === 0) {
    out("\nNo valid users to import. Exiting.");
    exit(0);
}

try {
    $pdo      = Connection::get();
    $importer = new UserImporter($pdo);
    $summary  = $importer->import($validated);
} catch (Throwable $e) {
    err("\nImport failed: {$e->getMessage()}");
    exit(1);
}

$inBatch    = count($invalid);
$inDb       = $summary['skipped'];
$totalSkipped = $inBatch + $inDb;

out('');
out('Import Summary');
out(str_repeat('─', 40));
out(sprintf('  Total records:    %4d', count($validated)));
out(sprintf('  Valid records:    %4d', count($valid)));
out(sprintf('  Invalid records:  %4d', $inBatch));
out(sprintf('  Inserted:         %4d', $summary['inserted']));
out(sprintf('  Already in DB:    %4d', $inDb));
out(sprintf('  Total skipped:    %4d', $totalSkipped));
out(str_repeat('─', 40));

if ($summary['errors'] !== []) {
    out("\nDatabase-level skips (email already existed):");
    foreach ($summary['errors'] as $e) {
        out(sprintf('  Line %d — %s: %s', $e['line'], $e['email'], $e['reason']));
    }
}

exit(0);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function printHelp(): void
{
    echo <<<HELP

    User Import CLI
    ───────────────
    Imports users from a CSV file into the PostgreSQL database.

    Usage:
      php cli/user_upload.php --file <path> [--dry-run] [--create-table]
      php cli/user_upload.php --help

    Options:
      --file <filename>    CSV file to process (required for import)
      --dry-run            Parse and validate without writing to the database
      --strict             Reject unexpected CSV columns or column-count mismatches
      --create-table       Create (or rebuild) the users table before importing
      --help               Display this help message

    Examples:
      php cli/user_upload.php --create-table
      php cli/user_upload.php --file users.csv --dry-run
      php cli/user_upload.php --file users.csv
      php cli/user_upload.php --file users.csv --create-table
      php cli/user_upload.php --file users.csv --strict --dry-run

    CSV format:
      name,surname,email
      John,Smith,john@example.com

    HELP;
}

function out(string $message): void
{
    echo $message . PHP_EOL;
}

function err(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
}

function truncate(string $str, int $max): string
{
    return mb_strlen($str) > $max ? mb_substr($str, 0, $max - 1) . '…' : $str;
}
