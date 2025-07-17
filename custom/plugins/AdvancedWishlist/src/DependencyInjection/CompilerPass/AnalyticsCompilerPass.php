<?php

declare(strict_types=1);

namespace AdvancedWishlist\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AnalyticsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition('router.default')
            ->addMethodCall('addResource', [
                new Reference('routing.loader.annotation'),
                __DIR__.'/../../Administration/Controller/AnalyticsController.php',
                'annotation',
                'advanced_wishlist_analytics',
            ]);
    }
}
