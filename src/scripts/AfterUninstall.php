<?php

declare(strict_types=1);

use Espo\Core\Container;

class AfterUninstall
{
    public function run(Container $container): void
    {
        $container->get('dataManager')->clearCache();
    }
}
