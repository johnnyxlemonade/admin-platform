<?php

declare(strict_types=1);

namespace Lemonade\Admin\Select;

use InvalidArgumentException;

/**
 * Poskytuje predem deklarovane a jedinecne volby vyberu
 */
final readonly class StaticSelectOptionSource
{
    /**
     * Overuje seznam voleb s jedinecnymi hodnotami
     *
     * @param list<SelectOptionDefinition> $options
     */
    public function __construct(private array $options)
    {
        if (!array_is_list($options)) {
            throw new InvalidArgumentException('Static select options must be a list.');
        }

        $values = [];
        foreach ($options as $option) {
            if (!$option instanceof SelectOptionDefinition) {
                throw new InvalidArgumentException('Static select options must contain SelectOptionDefinition instances.');
            }

            if (isset($values[$option->value()])) {
                throw new InvalidArgumentException('Static select option values must be unique.');
            }

            $values[$option->value()] = true;
        }
    }

    /**
     * Vraci deklarovane volby ve stanovenem poradi
     *
     * @return list<SelectOptionDefinition>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * Vraci hodnoty vsech deklarovanych voleb
     *
     * @return list<string>
     */
    public function values(): array
    {
        return array_map(static fn(SelectOptionDefinition $option): string => $option->value(), $this->options);
    }
}
