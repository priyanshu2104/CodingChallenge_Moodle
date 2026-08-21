<?php

declare(strict_types=1);

namespace Tests;

use App\Service\CsvParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CsvParserTest extends TestCase
{
    private CsvParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CsvParser();
    }

    // -------------------------------------------------------------------------
    // parseString() — happy path
    // -------------------------------------------------------------------------

    public function test_parse_valid_csv_string(): void
    {
        $csv = "name,surname,email\njohn,smith,john@example.com\njane,doe,jane@example.com";

        $rows = $this->parser->parseString($csv);

        self::assertCount(2, $rows);
        self::assertSame('john',             $rows[0]['name']);
        self::assertSame('smith',            $rows[0]['surname']);
        self::assertSame('john@example.com', $rows[0]['email']);
        self::assertSame('jane',             $rows[1]['name']);
    }

    public function test_handles_windows_line_endings(): void
    {
        $csv = "name,surname,email\r\njohn,smith,john@example.com\r\n";

        $rows = $this->parser->parseString($csv);

        self::assertCount(1, $rows);
        self::assertSame('john@example.com', $rows[0]['email']);
    }

    public function test_trims_whitespace_from_cells(): void
    {
        $csv = "name,surname,email\n  john  ,  smith  ,  john@example.com  ";

        $rows = $this->parser->parseString($csv);

        self::assertSame('john',             $rows[0]['name']);
        self::assertSame('john@example.com', $rows[0]['email']);
    }

    public function test_skips_completely_empty_rows(): void
    {
        $csv = "name,surname,email\njohn,smith,john@example.com\n\njane,doe,jane@example.com";

        $rows = $this->parser->parseString($csv);

        // The blank middle line should be skipped
        self::assertCount(2, $rows);
    }

    public function test_strips_utf8_bom(): void
    {
        $bom = "\xEF\xBB\xBF";
        $csv = "{$bom}name,surname,email\njohn,smith,john@example.com";

        $rows = $this->parser->parseString($csv);

        self::assertCount(1, $rows);
    }

    public function test_header_is_case_insensitive(): void
    {
        $csv = "Name,Surname,Email\njohn,smith,john@example.com";

        $rows = $this->parser->parseString($csv);

        self::assertCount(1, $rows);
        self::assertArrayHasKey('email', $rows[0]);
    }

    public function test_records_line_number(): void
    {
        $csv = "name,surname,email\njohn,smith,john@example.com\njane,doe,jane@example.com";

        $rows = $this->parser->parseString($csv);

        self::assertSame(2, $rows[0]['_line']);
        self::assertSame(3, $rows[1]['_line']);
    }

    // -------------------------------------------------------------------------
    // parseString() — error cases
    // -------------------------------------------------------------------------

    public function test_throws_on_empty_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/empty|header/i');

        $this->parser->parseString('');
    }

    public function test_throws_when_required_column_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/email/i');

        $this->parser->parseString("name,surname\njohn,smith");
    }

    // -------------------------------------------------------------------------
    // parseFile() — error cases
    // -------------------------------------------------------------------------

    public function test_throws_when_file_not_found(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->parser->parseFile('/tmp/definitely-does-not-exist.csv');
    }
}
