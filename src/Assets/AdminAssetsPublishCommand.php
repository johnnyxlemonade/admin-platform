<?php

declare(strict_types=1);

namespace Lemonade\Admin\Assets;

use Lemonade\Framework\Cli\CommandInterface;

/**
 * Publikuje predpripravene Admin assety do public rootu aktualniho hosta
 */
final class AdminAssetsPublishCommand implements CommandInterface
{
    /**
     * Nastavuje publisher Admin distribuce
     */
    public function __construct(private readonly AdminAssetPublisher $publisher) {}

    /**
     * Vraci stabilni nazev deploy prikazu
     */
    public function name(): string
    {
        return 'admin:assets:publish';
    }

    /**
     * Vraci popis zobrazovany v konzolovem seznamu prikazu
     */
    public function description(): string
    {
        return 'Publishes prebuilt Admin assets into the host public root.';
    }

    /**
     * Publikuje assety bez spousteni Node nebo Vite buildu
     *
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        unset($args);
        $this->publisher->publish();
        fwrite(STDOUT, "Admin assets published.\n");

        return 0;
    }
}
