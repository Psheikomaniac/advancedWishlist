<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Performance;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for performance monitoring.
 */
class PerformanceMonitoringSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PerformanceMonitoringService $performanceMonitoring,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256], // High priority to start as early as possible
            KernelEvents::RESPONSE => ['onKernelResponse', -256], // Low priority to end as late as possible
        ];
    }

    /**
     * Handle request event.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $this->performanceMonitoring->onKernelRequest($event);
    }

    /**
     * Handle response event.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $this->performanceMonitoring->onKernelResponse($event);
    }
}
