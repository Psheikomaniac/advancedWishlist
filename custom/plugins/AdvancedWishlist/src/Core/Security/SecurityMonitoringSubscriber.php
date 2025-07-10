<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class SecurityMonitoringSubscriber implements EventSubscriberInterface
{
    private SecurityMonitoringService $securityMonitoring;

    /**
     * SecurityMonitoringSubscriber constructor.
     *
     * @param SecurityMonitoringService $securityMonitoring
     */
    public function __construct(SecurityMonitoringService $securityMonitoring)
    {
        $this->securityMonitoring = $securityMonitoring;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    /**
     * Handle the request event.
     *
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->securityMonitoring->monitorRequest();
    }

    /**
     * Handle the exception event.
     *
     * @param ExceptionEvent $event
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Log authentication exceptions
        if ($exception instanceof AuthenticationException) {
            $username = $request->getSession()->get('_security.last_username') ?? 'unknown';
            $this->securityMonitoring->logFailedAuthentication($username);
        }

        // Log access denied exceptions
        if ($exception instanceof AccessDeniedException) {
            $resource = $request->getPathInfo();
            $action = $request->getMethod();
            $this->securityMonitoring->logUnauthorizedAccess($resource, $action);
        }
    }
}