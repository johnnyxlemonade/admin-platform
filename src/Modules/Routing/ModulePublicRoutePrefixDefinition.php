<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Routing;

use InvalidArgumentException;

/**
 * Drzi definici verejneho prefixu routy modulu
 */
final readonly class ModulePublicRoutePrefixDefinition
{
    public function __construct(private string $locale, private string $prefix)
    {
        if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $this->locale) !== 1) {
            throw new InvalidArgumentException(sprintf('Public route prefix locale "%s" is invalid.', $this->locale));
        }
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $this->prefix) !== 1
            || $this->prefix === '.'
            || $this->prefix === '..') {
            throw new InvalidArgumentException(sprintf('Public route prefix "%s" is invalid.', $this->prefix));
        }
        if ($this->prefix === $this->locale) {
            throw new InvalidArgumentException(sprintf('Public route prefix "%s" must not match its locale.', $this->prefix));
        }
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }
}
