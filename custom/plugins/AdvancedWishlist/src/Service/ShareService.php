<?php declare(strict_types=1);

namespace AdvancedWishlist\Service;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare\WishlistShareDefinition;
use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare\WishlistShareEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ShareService
{
    private EntityRepository $wishlistShareRepository;
    private EncryptionService $encryptionService;

    public function __construct(
        EntityRepository $wishlistShareRepository,
        EncryptionService $encryptionService
    )
    {
        $this->wishlistShareRepository = $wishlistShareRepository;
        $this->encryptionService = $encryptionService;
    }

    public function createShare(string $wishlistId, Context $context): WishlistShareEntity
    {
        $token = $this->encryptionService->generateToken();
        $encryptedToken = $this->encryptionService->encrypt($token);

        $data = [
            'wishlistId' => $wishlistId,
            'token' => $encryptedToken,
            'type' => 'link',
            'active' => true,
            'views' => 0,
            'uniqueViews' => 0,
            'conversions' => 0,
        ];

        $this->wishlistShareRepository->create([$data], $context);

        return $this->getShareByToken($token, $context);
    }

    public function getShareByToken(string $token, Context $context): ?WishlistShareEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('token', $this->encryptionService->encrypt($token)));

        return $this->wishlistShareRepository->search($criteria, $context)->first();
    }

    public function updateShare(string $shareId, array $data, Context $context): void
    {
        if (isset($data['token'])) {
            $data['token'] = $this->encryptionService->encrypt($data['token']);
        }

        $this->wishlistShareRepository->update([array_merge(['id' => $shareId], $data)], $context);
    }

    public function deleteShare(string $shareId, Context $context): void
    {
        $this->wishlistShareRepository->delete([['id' => $shareId]], $context);
    }
}
