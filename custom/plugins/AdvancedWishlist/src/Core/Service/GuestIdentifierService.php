<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Cookie;

class GuestIdentifierService
{
    private const string COOKIE_NAME = 'guest_id';
    private const int ID_LENGTH = 32;
    private const int COOKIE_LIFETIME = 2592000; // 30 days

    public function __construct(
        private RequestStack $requestStack,
        private string $secret
    ) {}

    /**
     * Get or create guest identifier
     */
    public function getOrCreateGuestId(SalesChannelContext $context): string
    {
        // Try to get from cookie first
        $guestId = $this->getGuestIdFromCookie();

        if ($guestId && $this->validateGuestId($guestId)) {
            return $guestId;
        }

        // Generate new guest ID
        $guestId = $this->generateGuestId($context);
        $this->setGuestIdCookie($guestId);

        return $guestId;
    }

    /**
     * Generate unique guest identifier
     */
    private function generateGuestId(SalesChannelContext $context): string
    {
        $data = [
            'session' => $context->getToken(),
            'salesChannel' => $context->getSalesChannelId(),
            'language' => $context->getLanguageId(),
            'timestamp' => microtime(true),
            'random' => random_bytes(16),
        ];

        $hash = hash_hmac('sha256', json_encode($data), $this->secret);

        return substr($hash, 0, self::ID_LENGTH);
    }

    /**
     * Validate guest ID format and signature
     */
    private function validateGuestId(string $guestId): bool
    {
        // Check length
        if (strlen($guestId) !== self::ID_LENGTH) {
            return false;
        }

        // Check format (alphanumeric)
        if (!ctype_alnum($guestId)) {
            return false;
        }

        return true;
    }

    /**
     * Get guest ID from cookie
     */
    public function getGuestIdFromCookie(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return null;
        }

        return $request->cookies->get(self::COOKIE_NAME);
    }

    /**
     * Set guest ID cookie
     */
    private function setGuestIdCookie(string $guestId): void
    {
        $response = $this->requestStack->getCurrentRequest()?->attributes->get('_response');

        if (!$response) {
            return;
        }

        $cookie = Cookie::create(self::COOKIE_NAME)
            ->withValue($guestId)
            ->withExpires(time() + self::COOKIE_LIFETIME)
            ->withPath('/')
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite('Lax');

        $response->headers->setCookie($cookie);
    }

    /**
     * Clear guest ID cookie
     */
    public function clearGuestIdCookie(): void
    {
        $response = $this->requestStack->getCurrentRequest()?->attributes->get('_response');

        if (!$response) {
            return;
        }

        $response->headers->clearCookie(self::COOKIE_NAME, '/');
    }
}
