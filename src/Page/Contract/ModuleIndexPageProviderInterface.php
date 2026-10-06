<?php

declare(strict_types=1);

namespace Lemonade\Admin\Page\Contract;

use Lemonade\Admin\Page\ModulePage;

/**
 * Urcuje indexovou stranku poskytovanou admin modulem
 */
interface ModuleIndexPageProviderInterface
{
    /**
     * Vraci opravneni potrebne pro otevreni indexove stranky modulu
     */
    public function indexPermission(): string;

    /**
     * Vytvari indexovou stranku modulu pro zvolene UI locale
     */
    public function index(string $locale): ModulePage;
}
