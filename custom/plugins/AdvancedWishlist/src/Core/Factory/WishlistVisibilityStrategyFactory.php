<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Factory;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Domain\Strategy\PrivateWishlistVisibilityStrategy;
use AdvancedWishlist\Core\Domain\Strategy\PublicWishlistVisibilityStrategy;
use AdvancedWishlist\Core\Domain\Strategy\SharedWishlistVisibilityStrategy;
use AdvancedWishlist\Core\Domain\Strategy\WishlistVisibilityStrategy;
use AdvancedWishlist\Core\Domain\ValueObject\WishlistType;
use AdvancedWishlist\Core\Exception\InvalidWishlistTypeException;

/**
 * Factory for creating wishlist visibility strategies
 */
class WishlistVisibilityStrategyFactory
{
    /**
     * @var WishlistVisibilityStrategy[]
     */
    private array $strategies = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Register strategies
        $this->registerStrategy(new PrivateWishlistVisibilityStrategy());
        $this->registerStrategy(new PublicWishlistVisibilityStrategy());
        $this->registerStrategy(new SharedWishlistVisibilityStrategy());
    }

    /**
     * Get the appropriate strategy for a wishlist
     * 
     * @param WishlistEntity $wishlist The wishlist
     * @return WishlistVisibilityStrategy The appropriate strategy
     * @throws \InvalidArgumentException If the wishlist type is invalid
     */
    public function getStrategy(WishlistEntity $wishlist): WishlistVisibilityStrategy
    {
        return $this->getStrategyForType($wishlist->getType());
    }

    /**
     * Get the appropriate strategy for a wishlist type
     * 
     * @param string $type The wishlist type
     * @return WishlistVisibilityStrategy The appropriate strategy
     * @throws \InvalidArgumentException If the wishlist type is invalid
     */
    public function getStrategyForType(string $type): WishlistVisibilityStrategy
    {
        if (!isset($this->strategies[$type])) {
            throw new \InvalidArgumentException(sprintf(
                'No strategy found for wishlist type "%s". Valid types are: %s',
                $type,
                implode(', ', array_keys($this->strategies))
            ));
        }

        return $this->strategies[$type];
    }

    /**
     * Get the appropriate strategy for a wishlist type value object
     * 
     * @param WishlistType $type The wishlist type
     * @return WishlistVisibilityStrategy The appropriate strategy
     * @throws \InvalidArgumentException If the wishlist type is invalid
     */
    public function getStrategyForTypeObject(WishlistType $type): WishlistVisibilityStrategy
    {
        return $this->getStrategyForType($type->toString());
    }

    /**
     * Register a strategy
     * 
     * @param WishlistVisibilityStrategy $strategy The strategy to register
     */
    private function registerStrategy(WishlistVisibilityStrategy $strategy): void
    {
        $this->strategies[$strategy->getSupportedType()] = $strategy;
    }
}
