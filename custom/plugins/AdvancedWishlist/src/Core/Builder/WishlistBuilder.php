<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Builder;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\Exception\WishlistNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Builder for creating wishlist entities
 * Implements the Builder pattern for complex object creation
 */
class WishlistBuilder
{
    private string $id;
    private string $customerId;
    private string $name;
    private ?string $description = null;
    private string $type = 'private';
    private bool $isDefault = false;
    private ?string $salesChannelId = null;
    private ?string $languageId = null;
    private array $customFields = [];

    public function __construct(
        private readonly EntityRepository $wishlistRepository
    ) {
        // Generate a new ID by default
        $this->id = Uuid::randomHex();
    }

    /**
     * Create a builder from a CreateWishlistRequest
     */
    public static function fromRequest(
        CreateWishlistRequest $request,
        Context $context,
        EntityRepository $wishlistRepository
    ): self {
        $builder = new self($wishlistRepository);
        
        $builder->withCustomerId($request->getCustomerId())
            ->withName($request->getName())
            ->withDescription($request->getDescription())
            ->withType($request->getType())
            ->withIsDefault($request->isDefault());
            
        // Add context-specific properties
        if ($context->getSource() && method_exists($context->getSource(), 'getSalesChannelId')) {
            $builder->withSalesChannelId($context->getSource()->getSalesChannelId());
        }
        
        if ($context->getLanguageId()) {
            $builder->withLanguageId($context->getLanguageId());
        }
        
        return $builder;
    }

    /**
     * Set the wishlist ID
     */
    public function withId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Set the customer ID
     */
    public function withCustomerId(string $customerId): self
    {
        $this->customerId = $customerId;
        return $this;
    }

    /**
     * Set the wishlist name
     */
    public function withName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the wishlist description
     */
    public function withDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set the wishlist type
     */
    public function withType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Set whether this is the default wishlist
     */
    public function withIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    /**
     * Set the sales channel ID
     */
    public function withSalesChannelId(?string $salesChannelId): self
    {
        $this->salesChannelId = $salesChannelId;
        return $this;
    }

    /**
     * Set the language ID
     */
    public function withLanguageId(?string $languageId): self
    {
        $this->languageId = $languageId;
        return $this;
    }

    /**
     * Set custom fields
     */
    public function withCustomFields(array $customFields): self
    {
        $this->customFields = $customFields;
        return $this;
    }

    /**
     * Build and persist the wishlist entity
     */
    public function build(Context $context): WishlistEntity
    {
        // Validate required fields
        if (!isset($this->customerId)) {
            throw new \InvalidArgumentException('Customer ID is required');
        }
        
        if (!isset($this->name)) {
            throw new \InvalidArgumentException('Name is required');
        }
        
        // Prepare data for repository
        $data = [
            'id' => $this->id,
            'customerId' => $this->customerId,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'isDefault' => $this->isDefault,
        ];
        
        // Add optional fields if set
        if ($this->salesChannelId) {
            $data['salesChannelId'] = $this->salesChannelId;
        }
        
        if ($this->languageId) {
            $data['languageId'] = $this->languageId;
        }
        
        if (!empty($this->customFields)) {
            $data['customFields'] = $this->customFields;
        }
        
        // Create the wishlist
        $this->wishlistRepository->create([$data], $context);
        
        // Load and return the created entity
        return $this->loadWishlist($this->id, $context);
    }
    
    /**
     * Load the wishlist entity
     */
    private function loadWishlist(string $wishlistId, Context $context): WishlistEntity
    {
        $criteria = new Criteria([$wishlistId]);
        $criteria->addAssociation('items.product.cover');
        $criteria->addAssociation('items.product.prices');
        $criteria->addAssociation('customer');
        $criteria->addAssociation('shareInfo');
        
        $wishlist = $this->wishlistRepository->search($criteria, $context)->first();
        
        if (!$wishlist) {
            throw new WishlistNotFoundException(
                'Wishlist not found',
                ['wishlistId' => $wishlistId]
            );
        }
        
        return $wishlist;
    }
}