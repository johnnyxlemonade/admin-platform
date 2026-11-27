<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module;

use Lemonade\Framework\Container\ContainerInterface;

/**
 * Materializes deferred Admin module contributions once on first use
 */
final class AdminModuleContributionLoader
{
    /** @var list<\Closure(?ContainerInterface):void> */
    private array $loaders = [];

    private bool $loading = false;

    private bool $loaded = false;

    /**
     * Original failure retained to prevent repeating partial registrations
     */
    private ?\Throwable $failure = null;

    private ?ContainerInterface $container = null;

    /**
     * Copies pending bootstrap callbacks for one isolated runtime scope
     */
    public function forScope(ContainerInterface $container): self
    {
        if ($this->loading || $this->loaded || $this->failure !== null) {
            throw new \LogicException('Only pending bootstrap contributions can be copied to a scope.');
        }
        $copy = clone $this;
        $copy->container = $container;

        return $copy;
    }

    /**
     * Adds one deferred contribution loader during module bootstrap
     *
     * @param \Closure(?ContainerInterface):void $loader
     */
    public function defer(\Closure $loader): void
    {
        if ($this->loading || $this->loaded || $this->failure !== null) {
            throw new \LogicException('Admin module contribution loading has already started.');
        }

        $this->loaders[] = $loader;
    }

    /**
     * Loads every deferred contribution exactly once in this runtime scope; failures are terminal
     */
    public function load(): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        if ($this->loaded || $this->loading) {
            return;
        }

        $this->loading = true;
        try {
            foreach ($this->loaders as $loader) {
                $loader($this->container);
            }
            $this->loaded = true;
        } catch (\Throwable $failure) {
            $this->failure = $failure;
            throw $failure;
        } finally {
            $this->loading = false;
        }
    }
}
