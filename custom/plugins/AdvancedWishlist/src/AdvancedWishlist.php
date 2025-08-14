<?php

declare(strict_types=1);

namespace AdvancedWishlist;

use AdvancedWishlist\ScheduledTask\PriceMonitoringTask;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class AdvancedWishlist extends Plugin
{
    /**
     * Minimum required PHP version for this plugin
     * 
     * This plugin uses PHP 8.4 features including:
     * - Property hooks
     * - Enhanced readonly properties
     * - Improved type system
     * - Performance optimizations
     */
    private const REQUIRED_PHP_VERSION = '8.4.0';

    public function __construct(bool $boot, string $className, ?string $kernelClass = null)
    {
        // Check PHP version compatibility before any plugin initialization
        if (!$this->isPhpVersionCompatible()) {
            throw new \RuntimeException(
                sprintf(
                    'Advanced Wishlist Plugin requires PHP %s or higher. Current version: %s',
                    self::REQUIRED_PHP_VERSION,
                    PHP_VERSION
                )
            );
        }
        
        parent::__construct($boot, $className, $kernelClass);
    }

    /**
     * Check if the current PHP version meets the minimum requirements
     */
    private function isPhpVersionCompatible(): bool
    {
        return version_compare(PHP_VERSION, self::REQUIRED_PHP_VERSION, '>=');
    }
    #[\Override]
    public function configureRoutes(RoutingConfigurator $routes, string $environment): void
    {
        $routes->import(__DIR__.'/Resources/config/routes.yaml', 'yaml');
    }

    #[\Override]
    public function install(InstallContext $installContext): void
    {
        /** @var EntityRepository $scheduledTaskRepository */
        $scheduledTaskRepository = $this->container->get('scheduled_task.repository');
        
        // Check if scheduled task already exists
        $existingTask = $scheduledTaskRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('scheduledTaskClass', PriceMonitoringTask::class)),
            $installContext->getContext()
        )->first();

        if (!$existingTask) {
            $scheduledTaskRepository->create([
                [
                    'name' => 'advanced_wishlist.price_monitoring_task',
                    'scheduledTaskClass' => PriceMonitoringTask::class,
                    'runInterval' => 3600,
                    'defaultRunInterval' => 3600,
                    'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                ],
            ], $installContext->getContext());
        }
    }

    #[\Override]
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        /** @var EntityRepository $scheduledTaskRepository */
        $scheduledTaskRepository = $this->container->get('scheduled_task.repository');
        $scheduledTask = $scheduledTaskRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('name', 'advanced_wishlist.price_monitoring_task')),
            $uninstallContext->getContext()
        )->first();

        if ($scheduledTask) {
            $scheduledTaskRepository->delete([
                ['id' => $scheduledTask->getId()],
            ], $uninstallContext->getContext());
        }
    }

    #[\Override]
    public function activate(ActivateContext $activateContext): void
    {
        /** @var EntityRepository $scheduledTaskRepository */
        $scheduledTaskRepository = $this->container->get('scheduled_task.repository');
        $scheduledTask = $scheduledTaskRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('name', 'advanced_wishlist.price_monitoring_task')),
            $activateContext->getContext()
        )->first();

        if ($scheduledTask) {
            $scheduledTaskRepository->update([
                [
                    'id' => $scheduledTask->getId(),
                    'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                ],
            ], $activateContext->getContext());
        }
    }

    #[\Override]
    public function deactivate(DeactivateContext $deactivateContext): void
    {
        /** @var EntityRepository $scheduledTaskRepository */
        $scheduledTaskRepository = $this->container->get('scheduled_task.repository');
        $scheduledTask = $scheduledTaskRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('name', 'advanced_wishlist.price_monitoring_task')),
            $deactivateContext->getContext()
        )->first();

        if ($scheduledTask) {
            $scheduledTaskRepository->update([
                [
                    'id' => $scheduledTask->getId(),
                    'status' => ScheduledTaskDefinition::STATUS_INACTIVE,
                ],
            ], $deactivateContext->getContext());
        }
    }

    #[\Override]
    public function update(UpdateContext $updateContext): void
    {
        // Update necessary stuff, mostly non-database related
    }

    #[\Override]
    public function postInstall(InstallContext $installContext): void
    {
    }

    #[\Override]
    public function postUpdate(UpdateContext $updateContext): void
    {
    }

    /**
     * Build the plugin's container extension
     * Loads both XML and PHP-based service configurations.
     */
    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Load traditional XML configuration
        $xmlLoader = new XmlFileLoader($container, new FileLocator(__DIR__.'/Resources/config'));
        $xmlLoader->load('services.xml');

        // Load modern attribute-based configuration
        $phpLoader = new PhpFileLoader($container, new FileLocator(__DIR__.'/Resources/config'));
        $phpLoader->load('services_attributes.php');
    }
}
