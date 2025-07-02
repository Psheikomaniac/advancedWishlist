# Wishlist Management Feature

## Overview

Das Wishlist Management ist das Kernfeature des Systems. Es ermöglicht Kunden das Erstellen, Verwalten und Organisieren von Wunschlisten mit verschiedenen Produkten.

## User Stories

### Als Kunde möchte ich...
1. **Wishlists erstellen** mit eigenem Namen und Beschreibung
2. **Produkte hinzufügen** mit einem Klick vom Produktdetail
3. **Mehrere Listen verwalten** für verschiedene Anlässe
4. **Produkte verschieben** zwischen verschiedenen Listen
5. **Listen priorisieren** mit einer Standard-Liste
6. **Notizen hinzufügen** zu einzelnen Produkten

### Als Shop-Betreiber möchte ich...
1. **Limits setzen** für maximale Anzahl von Listen/Produkten
2. **Analytics einsehen** über populäre Wishlist-Produkte
3. **Benachrichtigungen** bei bestimmten Events
4. **Integration** in bestehende Shop-Prozesse

## Technical Implementation

### Service Layer

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\AddItemRequest;
use AdvancedWishlist\Core\DTO\Response\WishlistResponse;
use AdvancedWishlist\Core\Event\WishlistCreatedEvent;
use AdvancedWishlist\Core\Exception\WishlistLimitExceededException;
use AdvancedWishlist\Core\Exception\DuplicateWishlistItemException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\EventDispatcherInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Psr\Log\LoggerInterface;

class WishlistService
{
    private const DEFAULT_WISHLIST_LIMIT = 10;
    private const DEFAULT_ITEM_LIMIT = 100;
    
    public function __construct(
        private EntityRepository $wishlistRepository,
        private EntityRepository $wishlistItemRepository,
        private WishlistValidator $validator,
        private WishlistLimitService $limitService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Create a new wishlist
     */
    public function createWishlist(
        CreateWishlistRequest $request,
        Context $context
    ): WishlistResponse {
        // 1. Validate request
        $this->validator->validateCreateRequest($request, $context);
        
        // 2. Check limits
        if (!$this->limitService->canCreateWishlist($request->getCustomerId(), $context)) {
            throw new WishlistLimitExceededException(
                'Customer has reached the maximum number of wishlists',
                ['limit' => self::DEFAULT_WISHLIST_LIMIT]
            );
        }
        
        // 3. Handle default wishlist
        if ($request->isDefault()) {
            $this->unsetCurrentDefaultWishlist($request->getCustomerId(), $context);
        }
        
        // 4. Prepare data
        $wishlistId = Uuid::randomHex();
        $data = [
            'id' => $wishlistId,
            'customerId' => $request->getCustomerId(),
            'name' => $request->getName(),
            'description' => $request->getDescription(),
            'type' => $request->getType(),
            'isDefault' => $request->isDefault(),
            'salesChannelId' => $context->getSource()->getSalesChannelId(),
        ];
        
        // 5. Create wishlist
        $this->wishlistRepository->create([$data], $context);
        
        // 6. Load created wishlist
        $wishlist = $this->loadWishlist($wishlistId, $context);
        
        // 7. Dispatch event
        $event = new WishlistCreatedEvent($wishlist, $context);
        $this->eventDispatcher->dispatch($event);
        
        // 8. Log
        $this->logger->info('Wishlist created', [
            'wishlistId' => $wishlistId,
            'customerId' => $request->getCustomerId(),
            'name' => $request->getName(),
        ]);
        
        // 9. Return response
        return WishlistResponse::fromEntity($wishlist);
    }
    
    /**
     * Update existing wishlist
     */
    public function updateWishlist(
        UpdateWishlistRequest $request,
        Context $context
    ): WishlistResponse {
        // 1. Load and validate ownership
        $wishlist = $this->loadWishlist($request->getWishlistId(), $context);
        $this->validator->validateOwnership($wishlist, $context);
        
        // 2. Check if there are changes
        if (!$request->hasChanges()) {
            return WishlistResponse::fromEntity($wishlist);
        }
        
        // 3. Handle default wishlist change
        if ($request->isDefault() === true) {
            $this->unsetCurrentDefaultWishlist($wishlist->getCustomerId(), $context);
        }
        
        // 4. Prepare update data
        $updateData = array_merge(
            ['id' => $request->getWishlistId()],
            $request->toArray()
        );
        
        // 5. Update
        $this->wishlistRepository->update([$updateData], $context);
        
        // 6. Reload and return
        $updatedWishlist = $this->loadWishlist($request->getWishlistId(), $context);
        
        return WishlistResponse::fromEntity($updatedWishlist);
    }
    
    /**
     * Add item to wishlist
     */
    public function addItem(
        AddItemRequest $request,
        Context $context
    ): WishlistItemResponse {
        // 1. Validate wishlist and ownership
        $wishlist = $this->loadWishlist($request->getWishlistId(), $context);
        $this->validator->validateOwnership($wishlist, $context);
        
        // 2. Check item limit
        if ($wishlist->getItems()->count() >= self::DEFAULT_ITEM_LIMIT) {
            throw new WishlistLimitExceededException(
                'Wishlist has reached the maximum number of items',
                ['limit' => self::DEFAULT_ITEM_LIMIT]
            );
        }
        
        // 3. Check for duplicates
        if ($this->isDuplicateItem($wishlist, $request->getProductId())) {
            throw new DuplicateWishlistItemException(
                'Product is already in the wishlist',
                ['productId' => $request->getProductId()]
            );
        }
        
        // 4. Validate product availability
        $product = $this->validateProduct($request->getProductId(), $context);
        
        // 5. Create item
        $itemId = Uuid::randomHex();
        $itemData = [
            'id' => $itemId,
            'wishlistId' => $request->getWishlistId(),
            'productId' => $request->getProductId(),
            'quantity' => $request->getQuantity(),
            'note' => $request->getNote(),
            'priority' => $request->getPriority() ?? $this->getNextPriority($wishlist),
            'priceAlertThreshold' => $request->getPriceAlertThreshold(),
            'priceAlertActive' => $request->getPriceAlertThreshold() !== null,
            'productVersionId' => $product->getVersionId(),
        ];
        
        $this->wishlistItemRepository->create([$itemData], $context);
        
        // 6. Dispatch event
        $event = new WishlistItemAddedEvent($wishlist, $itemId, $context);
        $this->eventDispatcher->dispatch($event);
        
        // 7. Load and return
        $item = $this->loadWishlistItem($itemId, $context);
        
        return WishlistItemResponse::fromEntity($item);
    }
    
    /**
     * Remove item from wishlist
     */
    public function removeItem(
        string $wishlistId,
        string $itemId,
        Context $context
    ): void {
        // 1. Validate
        $wishlist = $this->loadWishlist($wishlistId, $context);
        $this->validator->validateOwnership($wishlist, $context);
        
        // 2. Check item belongs to wishlist
        $item = $wishlist->getItems()->get($itemId);
        if (!$item) {
            throw new WishlistItemNotFoundException(
                'Item not found in wishlist',
                ['itemId' => $itemId]
            );
        }
        
        // 3. Delete
        $this->wishlistItemRepository->delete([['id' => $itemId]], $context);
        
        // 4. Log
        $this->logger->info('Item removed from wishlist', [
            'wishlistId' => $wishlistId,
            'itemId' => $itemId,
            'productId' => $item->getProductId(),
        ]);
    }
    
    /**
     * Move item between wishlists
     */
    public function moveItem(
        string $sourceWishlistId,
        string $targetWishlistId,
        string $itemId,
        Context $context
    ): void {
        // 1. Validate both wishlists
        $sourceWishlist = $this->loadWishlist($sourceWishlistId, $context);
        $targetWishlist = $this->loadWishlist($targetWishlistId, $context);
        
        $this->validator->validateOwnership($sourceWishlist, $context);
        $this->validator->validateOwnership($targetWishlist, $context);
        
        // 2. Get item
        $item = $sourceWishlist->getItems()->get($itemId);
        if (!$item) {
            throw new WishlistItemNotFoundException('Item not found');
        }
        
        // 3. Check for duplicate in target
        if ($this->isDuplicateItem($targetWishlist, $item->getProductId())) {
            throw new DuplicateWishlistItemException('Product already in target wishlist');
        }
        
        // 4. Update item's wishlist
        $this->wishlistItemRepository->update([
            [
                'id' => $itemId,
                'wishlistId' => $targetWishlistId,
            ]
        ], $context);
    }
    
    /**
     * Get customer's wishlists
     */
    public function getCustomerWishlists(
        string $customerId,
        Context $context
    ): WishlistCollectionResponse {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addAssociation('items.product');
        $criteria->addSorting(new FieldSorting('isDefault', 'DESC'));
        $criteria->addSorting(new FieldSorting('createdAt', 'DESC'));
        
        $result = $this->wishlistRepository->search($criteria, $context);
        
        return WishlistCollectionResponse::fromSearchResult($result);
    }
    
    /**
     * Helper: Load wishlist with associations
     */
    private function loadWishlist(string $wishlistId, Context $context): WishlistEntity
    {
        $criteria = new Criteria([$wishlistId]);
        $criteria->addAssociation('items.product.cover');
        $criteria->addAssociation('items.product.prices');
        $criteria->addAssociation('customer');
        
        $wishlist = $this->wishlistRepository->search($criteria, $context)->first();
        
        if (!$wishlist) {
            throw new WishlistNotFoundException(
                'Wishlist not found',
                ['wishlistId' => $wishlistId]
            );
        }
        
        return $wishlist;
    }
    
    /**
     * Helper: Unset current default wishlist
     */
    private function unsetCurrentDefaultWishlist(
        string $customerId,
        Context $context
    ): void {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addFilter(new EqualsFilter('isDefault', true));
        
        $defaultWishlists = $this->wishlistRepository->searchIds($criteria, $context);
        
        if ($defaultWishlists->getTotal() > 0) {
            $updates = array_map(
                fn($id) => ['id' => $id, 'isDefault' => false],
                $defaultWishlists->getIds()
            );
            
            $this->wishlistRepository->update($updates, $context);
        }
    }
    
    /**
     * Helper: Check if product already in wishlist
     */
    private function isDuplicateItem(
        WishlistEntity $wishlist,
        string $productId
    ): bool {
        return $wishlist->getItems()->filter(
            fn($item) => $item->getProductId() === $productId
        )->count() > 0;
    }
    
    /**
     * Helper: Get next priority number
     */
    private function getNextPriority(WishlistEntity $wishlist): int
    {
        $maxPriority = 0;
        
        foreach ($wishlist->getItems() as $item) {
            if ($item->getPriority() > $maxPriority) {
                $maxPriority = $item->getPriority();
            }
        }
        
        return $maxPriority + 1;
    }
}
```

### Validator Service

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Exception\UnauthorizedException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class WishlistValidator
{
    /**
     * Validate create wishlist request
     */
    public function validateCreateRequest(
        CreateWishlistRequest $request,
        Context $context
    ): void {
        // Validate customer context
        if ($context->getSource() instanceof SalesChannelContext) {
            $customer = $context->getSource()->getCustomer();
            
            if (!$customer) {
                throw new UnauthorizedException('Customer not logged in');
            }
            
            if ($customer->getId() !== $request->getCustomerId()) {
                throw new UnauthorizedException('Cannot create wishlist for another customer');
            }
        }
        
        // Additional business validations
        $this->validateWishlistName($request->getName());
        $this->validateWishlistType($request->getType());
    }
    
    /**
     * Validate wishlist ownership
     */
    public function validateOwnership(
        WishlistEntity $wishlist,
        Context $context
    ): void {
        if ($context->getSource() instanceof SalesChannelContext) {
            $customer = $context->getSource()->getCustomer();
            
            if (!$customer || $customer->getId() !== $wishlist->getCustomerId()) {
                throw new UnauthorizedException(
                    'You do not have permission to access this wishlist'
                );
            }
        }
    }
    
    /**
     * Validate wishlist name
     */
    private function validateWishlistName(string $name): void
    {
        // Check for reserved names
        $reservedNames = ['admin', 'system', 'default', 'test'];
        
        if (in_array(strtolower($name), $reservedNames)) {
            throw new InvalidWishlistNameException(
                'This name is reserved and cannot be used'
            );
        }
        
        // Check for special characters
        if (!preg_match('/^[\p{L}\p{N}\s\-_.]+$/u', $name)) {
            throw new InvalidWishlistNameException(
                'Wishlist name contains invalid characters'
            );
        }
    }
    
    /**
     * Validate wishlist type
     */
    private function validateWishlistType(string $type): void
    {
        $allowedTypes = ['private', 'public', 'shared'];
        
        if (!in_array($type, $allowedTypes)) {
            throw new InvalidWishlistTypeException(
                'Invalid wishlist type. Allowed types: ' . implode(', ', $allowedTypes)
            );
        }
    }
}
```

### Repository Extension

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Context;

class WishlistRepository extends EntityRepository
{
    /**
     * Find customer's default wishlist
     */
    public function findDefaultByCustomerId(
        string $customerId,
        Context $context
    ): ?WishlistEntity {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addFilter(new EqualsFilter('isDefault', true));
        $criteria->setLimit(1);
        
        return $this->search($criteria, $context)->first();
    }
    
    /**
     * Find wishlists with items about to expire
     */
    public function findExpiringWishlists(
        \DateTimeInterface $expiryDate,
        Context $context
    ): WishlistCollection {
        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('expiresAt', [
            RangeFilter::GTE => (new \DateTime())->format('c'),
            RangeFilter::LTE => $expiryDate->format('c'),
        ]));
        
        return $this->search($criteria, $context)->getEntities();
    }
    
    /**
     * Count customer wishlists
     */
    public function countByCustomerId(
        string $customerId,
        Context $context
    ): int {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        
        return $this->searchIds($criteria, $context)->getTotal();
    }
}
```

## Frontend Implementation

### Vue.js Component

```vue
<template>
  <div class="wishlist-manager">
    <!-- Wishlist Selector -->
    <div class="wishlist-selector">
      <select v-model="selectedWishlistId" @change="loadWishlist">
        <option 
          v-for="wishlist in wishlists" 
          :key="wishlist.id"
          :value="wishlist.id"
        >
          {{ wishlist.name }} ({{ wishlist.itemCount }})
        </option>
      </select>
      
      <button @click="showCreateModal = true" class="btn-create">
        + Neue Wunschliste
      </button>
    </div>
    
    <!-- Wishlist Items -->
    <div v-if="currentWishlist" class="wishlist-items">
      <h2>{{ currentWishlist.name }}</h2>
      <p v-if="currentWishlist.description">{{ currentWishlist.description }}</p>
      
      <div class="wishlist-actions">
        <button @click="editWishlist" class="btn-edit">Bearbeiten</button>
        <button @click="shareWishlist" class="btn-share">Teilen</button>
        <button @click="deleteWishlist" class="btn-delete">Löschen</button>
      </div>
      
      <div class="items-grid">
        <wishlist-item
          v-for="item in currentWishlist.items"
          :key="item.id"
          :item="item"
          @remove="removeItem"
          @update="updateItem"
          @move="moveItem"
        />
      </div>
      
      <div v-if="!currentWishlist.items.length" class="empty-state">
        <p>Diese Wunschliste ist noch leer.</p>
        <router-link to="/search" class="btn-primary">
          Produkte entdecken
        </router-link>
      </div>
    </div>
    
    <!-- Create Modal -->
    <modal v-if="showCreateModal" @close="showCreateModal = false">
      <create-wishlist-form
        @created="onWishlistCreated"
        @cancel="showCreateModal = false"
      />
    </modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useWishlistStore } from '@/stores/wishlist'
import { useNotification } from '@/composables/useNotification'
import WishlistItem from './WishlistItem.vue'
import CreateWishlistForm from './CreateWishlistForm.vue'
import Modal from '@/components/Modal.vue'

const wishlistStore = useWishlistStore()
const notification = useNotification()

const selectedWishlistId = ref(null)
const showCreateModal = ref(false)

const wishlists = computed(() => wishlistStore.wishlists)
const currentWishlist = computed(() => 
  wishlistStore.wishlists.find(w => w.id === selectedWishlistId.value)
)

onMounted(async () => {
  await wishlistStore.loadWishlists()
  
  // Select default or first wishlist
  const defaultWishlist = wishlists.value.find(w => w.isDefault)
  selectedWishlistId.value = defaultWishlist?.id || wishlists.value[0]?.id
})

async function loadWishlist() {
  if (selectedWishlistId.value) {
    await wishlistStore.loadWishlistDetails(selectedWishlistId.value)
  }
}

async function removeItem(itemId) {
  try {
    await wishlistStore.removeItem(selectedWishlistId.value, itemId)
    notification.success('Produkt entfernt')
  } catch (error) {
    notification.error('Fehler beim Entfernen')
  }
}

async function onWishlistCreated(wishlist) {
  showCreateModal.value = false
  selectedWishlistId.value = wishlist.id
  await wishlistStore.loadWishlists()
  notification.success('Wunschliste erstellt')
}
</script>
```

### Pinia Store

```javascript
import { defineStore } from 'pinia'
import { wishlistApi } from '@/api/wishlist'

export const useWishlistStore = defineStore('wishlist', {
  state: () => ({
    wishlists: [],
    currentWishlist: null,
    loading: false,
    error: null,
  }),
  
  getters: {
    defaultWishlist: (state) => 
      state.wishlists.find(w => w.isDefault),
    
    totalItems: (state) =>
      state.wishlists.reduce((sum, w) => sum + w.itemCount, 0),
    
    hasWishlist: (state) => (productId) =>
      state.wishlists.some(w => 
        w.items?.some(i => i.productId === productId)
      ),
  },
  
  actions: {
    async loadWishlists() {
      this.loading = true
      try {
        const response = await wishlistApi.getWishlists()
        this.wishlists = response.data.items
      } catch (error) {
        this.error = error.message
        throw error
      } finally {
        this.loading = false
      }
    },
    
    async createWishlist(data) {
      const response = await wishlistApi.createWishlist(data)
      this.wishlists.push(response.data)
      return response.data
    },
    
    async addItem(wishlistId, productId, data = {}) {
      const response = await wishlistApi.addItem(wishlistId, {
        productId,
        quantity: 1,
        ...data
      })
      
      // Update local state
      const wishlist = this.wishlists.find(w => w.id === wishlistId)
      if (wishlist) {
        wishlist.itemCount++
        if (wishlist.items) {
          wishlist.items.push(response.data)
        }
      }
      
      return response.data
    },
    
    async removeItem(wishlistId, itemId) {
      await wishlistApi.removeItem(wishlistId, itemId)
      
      // Update local state
      const wishlist = this.wishlists.find(w => w.id === wishlistId)
      if (wishlist) {
        wishlist.itemCount--
        if (wishlist.items) {
          wishlist.items = wishlist.items.filter(i => i.id !== itemId)
        }
      }
    },
    
    async toggleProductInWishlist(productId) {
      const wishlistWithProduct = this.wishlists.find(w =>
        w.items?.some(i => i.productId === productId)
      )
      
      if (wishlistWithProduct) {
        const item = wishlistWithProduct.items.find(i => i.productId === productId)
        await this.removeItem(wishlistWithProduct.id, item.id)
        return false
      } else {
        const targetWishlist = this.defaultWishlist || this.wishlists[0]
        if (!targetWishlist) {
          throw new Error('No wishlist available')
        }
        await this.addItem(targetWishlist.id, productId)
        return true
      }
    },
  },
})
```

## API Endpoints

### Store API Routes

```yaml
# Wishlist endpoints
GET    /store-api/wishlist
POST   /store-api/wishlist
GET    /store-api/wishlist/{id}
PUT    /store-api/wishlist/{id}
DELETE /store-api/wishlist/{id}

# Wishlist item endpoints  
POST   /store-api/wishlist/{id}/items
PUT    /store-api/wishlist/{id}/items/{itemId}
DELETE /store-api/wishlist/{id}/items/{itemId}
POST   /store-api/wishlist/{id}/items/{itemId}/move

# Utility endpoints
GET    /store-api/wishlist/check/{productId}
POST   /store-api/wishlist/merge
```

### Example API Calls

```javascript
// Create wishlist
POST /store-api/wishlist
{
  "name": "Geburtstag 2024",
  "description": "Meine Geburtstagswünsche",
  "type": "private",
  "isDefault": false
}

// Add item
POST /store-api/wishlist/abc123/items
{
  "productId": "prod123",
  "quantity": 1,
  "note": "Größe M, Farbe Blau",
  "priceAlertThreshold": 29.99
}

// Update item
PUT /store-api/wishlist/abc123/items/item456
{
  "quantity": 2,
  "priority": 1
}

// Move item
POST /store-api/wishlist/abc123/items/item456/move
{
  "targetWishlistId": "def456"
}
```

## Database Schema

```sql
-- Main wishlist table
CREATE TABLE `wishlist` (
  `id` BINARY(16) NOT NULL,
  `customer_id` BINARY(16) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `type` ENUM('private','public','shared') DEFAULT 'private',
  `is_default` TINYINT(1) DEFAULT 0,
  `sales_channel_id` BINARY(16),
  `custom_fields` JSON,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3),
  PRIMARY KEY (`id`),
  KEY `idx.wishlist.customer` (`customer_id`),
  KEY `idx.wishlist.default` (`customer_id`, `is_default`),
  CONSTRAINT `fk.wishlist.customer_id` FOREIGN KEY (`customer_id`) 
    REFERENCES `customer` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wishlist items table
CREATE TABLE `wishlist_item` (
  `id` BINARY(16) NOT NULL,
  `wishlist_id` BINARY(16) NOT NULL,
  `product_id` BINARY(16) NOT NULL,
  `product_version_id` BINARY(16) NOT NULL,
  `quantity` INT(11) DEFAULT 1,
  `note` VARCHAR(500),
  `priority` INT(11),
  `price_alert_threshold` DECIMAL(10,2),
  `price_alert_active` TINYINT(1) DEFAULT 0,
  `custom_fields` JSON,
  `added_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.wishlist_item.wishlist_product` (`wishlist_id`, `product_id`),
  KEY `idx.wishlist_item.product` (`product_id`),
  CONSTRAINT `fk.wishlist_item.wishlist_id` FOREIGN KEY (`wishlist_id`) 
    REFERENCES `wishlist` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk.wishlist_item.product` FOREIGN KEY (`product_id`, `product_version_id`) 
    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Event System

### Events

```php
// Wishlist events
WishlistCreatedEvent
WishlistUpdatedEvent
WishlistDeletedEvent
WishlistSharedEvent

// Item events
WishlistItemAddedEvent
WishlistItemUpdatedEvent
WishlistItemRemovedEvent
WishlistItemMovedEvent

// System events
WishlistLimitReachedEvent
WishlistMergedEvent
```

### Event Subscribers

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Subscriber;

use AdvancedWishlist\Core\Event\WishlistItemAddedEvent;
use AdvancedWishlist\Core\Service\WishlistAnalyticsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WishlistAnalyticsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private WishlistAnalyticsService $analyticsService
    ) {}
    
    public static function getSubscribedEvents(): array
    {
        return [
            WishlistItemAddedEvent::class => 'onItemAdded',
        ];
    }
    
    public function onItemAdded(WishlistItemAddedEvent $event): void
    {
        $this->analyticsService->trackItemAdded(
            $event->getWishlist(),
            $event->getItemId(),
            $event->getContext()
        );
    }
}
```

## Testing

### Unit Tests

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Test\Core\Service;

use AdvancedWishlist\Core\Service\WishlistService;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use PHPUnit\Framework\TestCase;

class WishlistServiceTest extends TestCase
{
    private WishlistService $service;
    
    protected function setUp(): void
    {
        $this->service = $this->createWishlistService();
    }
    
    public function testCreateWishlist(): void
    {
        $request = new CreateWishlistRequest();
        $request->setName('Test Wishlist');
        $request->setCustomerId('customer123');
        
        $response = $this->service->createWishlist($request, $this->context);
        
        static::assertNotNull($response->getId());
        static::assertEquals('Test Wishlist', $response->getName());
        static::assertEquals('customer123', $response->getCustomerId());
    }
    
    public function testAddDuplicateItemThrowsException(): void
    {
        $this->expectException(DuplicateWishlistItemException::class);
        
        // Add same product twice
        $this->service->addItem($addRequest, $this->context);
        $this->service->addItem($addRequest, $this->context);
    }
}
```

## Performance Considerations

### Caching Strategy

```php
// Cache wishlist data
$cacheKey = sprintf('wishlist.%s', $wishlistId);
$cached = $this->cache->get($cacheKey);

if ($cached) {
    return $cached;
}

// Load and cache
$wishlist = $this->loadWishlist($wishlistId);
$this->cache->set($cacheKey, $wishlist, 3600);
```

### Query Optimization

```php
// Optimized loading with proper joins
$criteria = new Criteria();
$criteria->addAssociation('items.product.cover.media');
$criteria->addAssociation('items.product.prices');
$criteria->getAssociation('items')->addSorting(
    new FieldSorting('priority', 'ASC')
);
```

## Security Considerations

1. **Access Control**: Strict ownership validation
2. **Rate Limiting**: Max 100 requests per minute
3. **Input Validation**: DTO-based validation
4. **XSS Prevention**: Output escaping in templates
5. **CSRF Protection**: Token validation