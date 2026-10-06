<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Export;

use IteratorAggregate;
use Lemonade\Admin\Export\CsvExport;
use PHPUnit\Framework\TestCase;
use Traversable;

/**
 * Overuje CSV stream pro administracni exporty
 */
final class CsvExportTest extends TestCase
{
    /**
     * Overi format hlavicky, oddelovac a ukonceni radku
     */
    public function testStreamWritesBomHeaderAndCrLfDelimitedRows(): void
    {
        $chunks = iterator_to_array((new CsvExport())->stream(
            header: ['ID', 'Nadpis'],
            rows: [[1, 'Udrzba; noc']],
        ));

        self::assertSame("\xEF\xBB\xBF", $chunks[0]);
        self::assertSame("ID;Nadpis\r\n", $chunks[1]);
        self::assertSame("1;\"Udrzba; noc\"\r\n", $chunks[2]);
    }

    /**
     * Overi neutralizaci hodnot, ktere tabulkovy editor vyhodnocuje jako vzorec
     */
    public function testStreamNeutralizesSpreadsheetFormulas(): void
    {
        $chunks = iterator_to_array((new CsvExport())->stream(
            header: ['Value'],
            rows: [['=1+1'], ['+SUM(A1:A2)'], ['-10'], ['@value'], ['  =A1'], ['bezpecny text']],
        ));

        self::assertSame("'=1+1\r\n", $chunks[2]);
        self::assertSame("'+SUM(A1:A2)\r\n", $chunks[3]);
        self::assertSame("'-10\r\n", $chunks[4]);
        self::assertSame("'@value\r\n", $chunks[5]);
        self::assertSame("\"'  =A1\"\r\n", $chunks[6]);
        self::assertSame("\"bezpecny text\"\r\n", $chunks[7]);
    }

    /**
     * Overi, ze zdroj radku zacne bezet az po hlavicce
     */
    public function testStreamReadsRowsLazily(): void
    {
        $rows = new class implements IteratorAggregate {
            public bool $iterated = false;

            public function getIterator(): Traversable
            {
                $this->iterated = true;

                yield ['radek'];
            }
        };
        $stream = (new CsvExport())->stream(['Value'], $rows);

        self::assertFalse($rows->iterated);
        self::assertSame("\xEF\xBB\xBF", $stream->current());
        self::assertFalse($rows->iterated);
        $stream->next();
        self::assertSame("Value\r\n", $stream->current());
        self::assertFalse($rows->iterated);
        $stream->next();
        self::assertSame("radek\r\n", $stream->current());
        self::assertTrue($rows->iterated);
    }

    /**
     * Overi download hlavicky pro privatni CSV soubor
     */
    public function testDownloadHeadersUseAttachmentAndPrivateCache(): void
    {
        self::assertSame([
            'Content-Disposition' => 'attachment; filename="notifications-2026-10-02-120000.csv"',
            'Cache-Control' => 'private, no-store',
        ], (new CsvExport())->downloadHeaders('notifications-2026-10-02-120000.csv'));
    }
}
