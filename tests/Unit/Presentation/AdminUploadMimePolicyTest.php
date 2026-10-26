<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Framework\Mime\MimeRisk;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Upload\UploadRiskPolicy;
use Lemonade\Framework\Upload\UploadVisibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Overuje public risk policy formatu deklarovanych v admin-file profilu Portalu
 */
final class AdminUploadMimePolicyTest extends TestCase
{
    /**
     * Overuje katalog, canonical MIME a povoleni public uloziste bez custom risk vyjimky
     */
    #[DataProvider('documentFormats')]
    public function testSupportedDocumentFormatRemainsAllowedInPublicStorage(
        string $extension,
        string $mimeType,
        MimeRisk $risk,
    ): void {
        $definition = MimeTypeCatalog::default()->definitionForExtension($extension);

        self::assertNotNull($definition);
        self::assertSame($mimeType, $definition->canonicalMime()->value());
        self::assertSame($risk, $definition->risk());
        self::assertTrue((new UploadRiskPolicy())->allows(
            $definition->risk(),
            UploadVisibility::Public,
        ));
    }

    /**
     * Vraci dokumentove formaty soucasneho Portal admin-file profilu
     *
     * @return iterable<string,array{string,string,MimeRisk}>
     */
    public static function documentFormats(): iterable
    {
        yield 'PDF' => ['pdf', 'application/pdf', MimeRisk::Passive];
        yield 'TXT' => ['txt', 'text/plain', MimeRisk::Passive];
        yield 'DOC' => ['doc', 'application/msword', MimeRisk::Passive];
        yield 'DOCX' => [
            'docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            MimeRisk::Container,
        ];
        yield 'XLS' => ['xls', 'application/vnd.ms-excel', MimeRisk::Passive];
        yield 'XLSX' => [
            'xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            MimeRisk::Container,
        ];
    }
}
