<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Command;

use AdvancedWishlist\Core\Builder\WishlistBuilder;
use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Domain\ValueObject\WishlistType;
use AdvancedWishlist\Core\Event\WishlistCreatedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Handler for the CreateWishlistCommand.
 * 
 * This class is responsible for processing the CreateWishlistCommand and creating a new wishlist.
 * It follows the CQRS pattern by separating the command (intention to change state) from the
 * handler (actual state change implementation).
 */
class CreateWishlistCommandHandler implements CommandHandlerInterface
{
    /**
     * @param EntityRepository $wishlistRepository Repository for wishlist entities
     * @param WishlistBuilder $wishlistBuilder Builder for creating wishlist entities
     * @param EventDispatcherInterface $eventDispatcher For dispatching domain events
     */
    public function __construct(
        private readonly EntityRepository $wishlistRepository,
        private readonly WishlistBuilder $wishlistBuilder,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * Handle the CreateWishlistCommand by creating a new wishlist.
     * 
     * @param object $command The command to handle
     * @return string The ID of the created wishlist
     */
    public function handle(object $command): string
    {
        if (!$command instanceof CreateWishlistCommand) {
            throw new \InvalidArgumentException(sprintf(
                'Expected command of type %s, got %s',
                CreateWishlistCommand::class,
                get_class($command)
            ));
        }

        // Generate a new UUID for the wishlist
        $wishlistId = Uuid::randomHex();

        // Use the builder pattern to create the wishlist entity
        $wishlist = $this->wishlistBuilder
            ->create($wishlistId)
            ->withName($command->name)
            ->withCustomerId($command->customerId)
            ->withType($command->isPublic ? WishlistType::PUBLIC : WishlistType::PRIVATE)
            ->withDescription($command->description)
            ->build();

        // Persist the wishlist entity
        $this->wishlistRepository->create([$wishlist->getData()], $command->context);

        // Dispatch domain event
        $event = new WishlistCreatedEvent($wishlist, $command->context);
        $this->eventDispatcher->dispatch($event);

        return $wishlistId;
    }
}
