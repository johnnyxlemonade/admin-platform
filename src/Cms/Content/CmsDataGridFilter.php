<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Content;

/**
 * Deklaruje vyctovy filtr nad mapovanou domenovou vlastnosti
 */
final readonly class CmsDataGridFilter
{
    /**
     * Nastavuje klic vlastnosti a povolene hodnoty filtru
     *
     * @param list<string> $values
     */
    public function __construct(public string $property, public array $values) {}
}
