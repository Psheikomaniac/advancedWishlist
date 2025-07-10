<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Factory;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Domain\ValueObject\WishlistType;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Factory for creating wishlist entities and related objects
 */
class WishlistFactory
{
    /**
     * Create a wishlist entity from a request
     * 
     * @param CreateWishlistRequest $request The request containing wishlist data
     * @param Context $context The context
     * @return array The wishlist data for repository creation
     */
    public function createFromRequest(CreateWishlistRequest $request, Context $context): array
    {
        // Convert string type to WishlistType value object
        $type = WishlistType::fromString($request->getType());
        
        return $this->createWishlistData(
            Uuid::randomHex(),
            $request->getCustomerId(),
            $request->getName(),
            $request->getDescription(),
            $type,
            $request->isDefault(),
            $context
        );
    }
    
    /**
     * Create a private wishlist
     * 
     * @param string $customerId The customer ID
     * @param string $name The wishlist name
     * @param string|null $description The wishlist description
     * @param bool $isDefault Whether this is the default wishlist
     * @param Context $context The context
     * @return array The wishlist data for repository creation
     */
    public function createPrivateWishlist(
        string $customerId,
        string $name,
        ?string $description = null,
        bool $isDefault = false,
        Context $context = null
    ): array {
        return $this->createWishlistData(
            Uuid::randomHex(),
            $customerId,
            $name,
            $description,
            WishlistType::private(),
            $isDefault,
            $context
        );
    }
    
    /**
     * Create a public wishlist
     * 
     * @param string $customerId The customer ID
     * @param string $name The wishlist name
     * @param string|null $description The wishlist description
     * @param bool $isDefault Whether this is the default wishlist
     * @param Context $context The context
     * @return array The wishlist data for repository creation
     */
    public function createPublicWishlist(
        string $customerId,
        string $name,
        ?string $description = null,
        bool $isDefault = false,
        Context $context = null
    ): array {
        return $this->createWishlistData(
            Uuid::randomHex(),
            $customerId,
            $name,
            $description,
            WishlistType::public(),
            $isDefault,
            $context
        );
    }
    
    /**
     * Create a shared wishlist
     * 
     * @param string $customerId The customer ID
     * @param string $name The wishlist name
     * @param string|null $description The wishlist description
     * @param bool $isDefault Whether this is the default wishlist
     * @param Context $context The context
     * @return array The wishlist data for repository creation
     */
    public function createSharedWishlist(
        string $customerId,
        string $name,
        ?string $description = null,
        bool $isDefault = false,
        Context $context = null
    ): array {
        return $this->createWishlistData(
            Uuid::randomHex(),
            $customerId,
            $name,
            $description,
            WishlistType::shared(),
            $isDefault,
            $context
        );
    }
    
    /**
     * Create a default wishlist
     * 
     * @param string $customerId The customer ID
     * @param Context $context The context
     * @return array The wishlist data for repository creation
     */
    public function createDefaultWishlist(string $customerId, Context $context = null): array
    {
        return $this->createPrivateWishlist(
            $customerId,
            'My Wishlist',
            null,
            true,
            $context
        );
    }
    
    /**
     * Create wishlist data for repository creation
     * 
     * @param string $id The wishlist ID
     * @param string $customerId The customer ID
     * @param string $name The wishlist name
     * @param string|null $description The wishlist description
     * @param WishlistType $type The wishlist type
     * @param bool $isDefault Whether this is the default wishlist
     * @param Context|null $context The context
     * @return array The wishlist data for repository creation
     */
    private function createWishlistData(
        string $id,
        string $customerId,
        string $name,
        ?string $description,
        WishlistType $type,
        bool $isDefault,
        ?Context $context
    ): array {
        return [
            'id' => $id,
            'customerId' => $customerId,
            'name' => $name,
            'description' => $description,
            'type' => $type->toString(),
            'isDefault' => $isDefault,
            'itemCount' => 0,
            'totalValue' => 0.0,
            'salesChannelId' => $context?->getSource()?->getSalesChannelId(),
            'languageId' => $context?->getLanguageId(),
        ];
    }
}