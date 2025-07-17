<?php

declare(strict_types=1);

namespace AdvancedWishlist\Routing;

use Shopware\Core\Framework\Routing\RouteCollectionBuilder;
use Shopware\Core\Framework\Routing\RoutingExtensionInterface;
use Symfony\Component\Config\Loader\LoaderInterface;

class AdvancedWishlistRouting implements RoutingExtensionInterface
{
    public function load(RouteCollectionBuilder $routes, LoaderInterface $loader): void
    {
        $routes->import(__DIR__.'/../Administration/Controller/', null, 'annotation');
    }
}
