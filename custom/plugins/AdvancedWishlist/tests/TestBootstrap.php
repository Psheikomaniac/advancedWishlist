<?php

declare(strict_types=1);

require_once __DIR__.'/../../../../vendor/autoload.php';

use Shopware\Core\TestBootstrapper;

$loader = (new TestBootstrapper())
    ->addCallingPlugin()
    ->addActivePlugins('AdvancedWishlist')
    ->setForceInstallPlugins(true)
    ->bootstrap()
    ->getClassLoader();

$loader->addPsr4('AdvancedWishlist\\Tests\\', __DIR__);
