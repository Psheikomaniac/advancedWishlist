<?php declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AdvancedWishlist\Core\Adapter\Repository\WishlistRepositoryAdapter;
use AdvancedWishlist\Core\Builder\WishlistBuilder;
use AdvancedWishlist\Core\Cache\CacheConfiguration;
use AdvancedWishlist\Core\CQRS\Query\Wishlist\GetWishlistsQueryHandler;
use AdvancedWishlist\Core\Message\Handler\WishlistCreatedHandler;
use AdvancedWishlist\Core\Port\WishlistRepositoryInterface;
use AdvancedWishlist\Core\Service\WishlistCrudService;
use AdvancedWishlist\Core\Service\WishlistCacheService;
use AdvancedWishlist\Core\Service\WishlistValidator;
use AdvancedWishlist\Core\Service\WishlistLimitService;
use AdvancedWishlist\Storefront\Controller\WishlistController;
use AdvancedWishlist\Administration\Controller\AnalyticsController;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Attribute-based service configuration for Symfony 7 compatibility
 */
return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()      // Automatically injects dependencies
        ->autoconfigure() // Automatically registers services based on attributes
    ;

    // Register the cache configuration
    $services->set(CacheConfiguration::class)
        ->public();

    // Register the message handler
    $services->set(WishlistCreatedHandler::class)
        ->tag('messenger.message_handler');

    // Register controllers with autowiring
    $services->set(WishlistController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);

    $services->set(AnalyticsController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);

    // Register core services with autowiring
    $services->set(WishlistCrudService::class)
        ->public();

    $services->set(WishlistCacheService::class)
        ->public();

    $services->set(WishlistValidator::class)
        ->public();

    $services->set(WishlistLimitService::class)
        ->public();

    // Register CQRS query handlers
    $services->set(GetWishlistsQueryHandler::class)
        ->public();

    // Register builders
    $services->set(WishlistBuilder::class)
        ->public()
        ->args([
            service('wishlist.repository')
        ]);
};
