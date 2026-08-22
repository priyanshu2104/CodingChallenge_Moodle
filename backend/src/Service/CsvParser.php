<?php

declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;
use RuntimeException;

/**
 * Parses a CSV file into an array of raw row arrays.
 *
 * Expected CSV format:
 *   name,surname,email
 *   John,Smith,john@example.com
 *
 * The parser handles:
 *  - UTF-8 BOM stripping
 *  - Windows (\r\n) and classic Mac (\r) line endings
 *  - Whitespace trimming of every cell value
 *  - Missing or incomplete header rows
 *  - Completely empty rows (skipped silently)
 *  - Strict mode: rejects unexpected columns and column-count mismatches
 */
final class CsvParser
{
    /** Expected column names in the CSV header (order-independent). */
    private const REQUIRED_COLUMNS = ['name', 'surname', 'email'];

    /**
     * @param bool $strict When true, unexpected header columns and row column-count
     *                     mismatches are treated as errors rather than silently ignored.
     */
    public function __construct(private readonly bool $strict = false) {}

    /**
     * Parses a CSV file by path and returns an array of associative rows.
     *
     * @param  string $filePath Absolute or relative path to the CSV file.
     * @return array<int, array{name: string, surname: string, email: string}>
     *
     * @throws RuntimeException         if the file cannot be opened.
     * @throws InvalidArgumentException if the CSV header is missing or malformed.
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("CSV file not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException("CSV file is not readable: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Could not open CSV file: {$filePath}");
        }

        try {
            return $this->parseHandle($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Parses CSV content from a string (useful for uploaded file contents).
     *
     * @return array<int, array{name: string, surname: string, email: string}>
     *
     * @throws InvalidArgumentException if the CSV header is missing or malformed.
     */
    public function parseString(string $csvContent): array
    {
        // Strip UTF-8 BOM if present
        $csvContent = ltrim($csvContent, "\xEF\xBB\xBF");

        // Normalise line endings
        $csvContent = str_replace(["\r\n", "\r"], "\n", $csvContent);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csvContent);
        rewind($handle);

        try {
            return $this->parseHandle($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Core parsing logic shared by parseFile() and parseString().
     *
     * @param  resource $handle
     * @return array<int, array{name: string, surname: string, email: string}>
     */
    private function parseHandle($handle): array
    {
        // Read and validate the header row
        $header = fgetcsv($handle);

        if ($header === false || $header === null) {
            throw new InvalidArgumentException('CSV file is empty or header row is missing.');
        }

        // Strip BOM from the very first cell and normalise to lowercase
        $header = array_map(
            fn(string $col): string => strtolower(trim(ltrim($col, "\xEF\xBB\xBF"))),
            $header
        );

        // Validate required columns
        foreach (self::REQUIRED_COLUMNS as $required) {
            if (!in_array($required, $header, true)) {
                throw new InvalidArgumentException(
                    "CSV header is missing required column: \"{$required}\". "
                    . "Found columns: " . implode(', ', $header)
                );
            }
        }

        // In strict mode, reject any columns beyond the required set
        $extraColumns = array_diff($header, self::REQUIRED_COLUMNS);
        if ($this->strict && $extraColumns !== []) {
            throw new InvalidArgumentException(
                'Strict mode: unexpected column(s) in CSV header: '
                . implode(', ', $extraColumns)
                . '. Expected only: ' . implode(', ', self::REQUIRED_COLUMNS)
            );
        }

        $expectedColumnCount = count($header);
        $rows = [];
        $lineNumber = 1; // 1-based (header is line 1)

        while (($rawRow = fgetcsv($handle)) !== false) {
            $lineNumber++;

            // Skip entirely empty rows
            if ($rawRow === [null] || array_filter($rawRow, fn($v) => $v !== null && $v !== '') === []) {
                continue;
            }

            // In strict mode, reject rows with a different number of columns than the header
            if ($this->strict && count($rawRow) !== $expectedColumnCount) {
                $rows[] = [
                    'name'    => '',
                    'surname' => '',
                    'email'   => '',
                    '_line'   => $lineNumber,
                    '_strict_error' => sprintf(
                        'column count mismatch: expected %d, got %d',
                        $expectedColumnCount,
                        count($rawRow)
                    ),
                ];
                continue;
            }

            // Map by header column name
            $row = [];
            foreach ($header as $index => $col) {
                $row[$col] = isset($rawRow[$index]) ? trim($rawRow[$index]) : '';
            }

            // Attach the original line number for error reporting
            $row['_line'] = $lineNumber;

            $rows[] = $row;
        }

        return $rows;
    }
}
