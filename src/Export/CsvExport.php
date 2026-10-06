<?php

declare(strict_types=1);

namespace Lemonade\Admin\Export;

use Generator;
use RuntimeException;

/**
 * Prevadi tabulkova data na bezpecny UTF-8 CSV stream
 */
final class CsvExport
{
    public const CONTENT_TYPE = 'text/csv; charset=UTF-8';

    /**
     * Vytvari CSV chunky s hlavickou a jednotlivymi radky
     *
     * @param list<string> $header
     * @param iterable<array<int, bool|float|int|string|null>> $rows
     * @return Generator<int, string>
     */
    public function stream(array $header, iterable $rows): Generator
    {
        yield "\xEF\xBB\xBF";
        yield $this->line($header);

        foreach ($rows as $row) {
            yield $this->line($row);
        }
    }

    /**
     * Vrati hlavicky pro privatni CSV download
     *
     * @return array<string, string>
     */
    public function downloadHeaders(string $filename): array
    {
        $safeFilename = str_replace(["\r", "\n", '"'], '', $filename);

        return [
            'Content-Disposition' => 'attachment; filename="' . $safeFilename . '"',
            'Cache-Control' => 'private, no-store',
        ];
    }

    /**
     * Zapise jeden CSV radek bez sdileneho bufferu celeho exportu
     *
     * @param array<int, bool|float|int|string|null> $values
     */
    private function line(array $values): string
    {
        $stream = fopen('php://memory', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to create CSV row stream.');
        }

        try {
            $written = fputcsv(
                stream: $stream,
                fields: array_map($this->escapeFormula(...), $values),
                separator: ';',
                enclosure: '"',
                escape: '',
                eol: "\r\n",
            );
            if ($written === false) {
                throw new RuntimeException('Unable to write CSV row.');
            }

            rewind($stream);
            $line = stream_get_contents($stream);
            if ($line === false) {
                throw new RuntimeException('Unable to read CSV row.');
            }

            return $line;
        } finally {
            fclose($stream);
        }
    }

    /**
     * Neutralizuje hodnotu, kterou by tabulkovy editor mohl vyhodnotit jako vzorec
     */
    private function escapeFormula(bool|float|int|string|null $value): string
    {
        $text = match (true) {
            $value === null => '',
            $value === true => '1',
            $value === false => '0',
            default => (string) $value,
        };
        $trimmed = ltrim($text, " \t\r\n");

        return $trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)
            ? "'" . $text
            : $text;
    }
}
