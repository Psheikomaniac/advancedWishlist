<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\OAuth\Subscriber;

use AdvancedWishlist\Core\OAuth\Middleware\OAuth2Middleware;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class OAuth2Subscriber implements EventSubscriberInterface
{
    private OAuth2Middleware $middleware;

    /**
     * OAuth2Subscriber constructor.
     */
    public function __construct(OAuth2Middleware $middleware)
    {
        $this->middleware = $middleware;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // Must run before Shopware's authentication
            KernelEvents::REQUEST => ['onKernelRequest', 32],
        ];
    }

    /**
     * Handle the request event.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->middleware->onKernelRequest($event);
    }
}
