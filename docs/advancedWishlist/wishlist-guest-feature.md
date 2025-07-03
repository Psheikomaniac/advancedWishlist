# Guest Wishlist Feature

## Overview

The Guest Wishlist Feature allows unregistered visitors to create and manage temporary wishlists. These are automatically converted to permanent wishlists after registration.

## User Stories

### As a guest, I want to...
1. **Save products** without registration requirement
2. **Keep wishlist** after later registration
3. **Get notifications** about saved products
4. **Share wishlist** even as a guest
5. **Continue seamlessly** after login/registration

### As a shop owner, I want to...
1. **Increase conversion** through low entry barriers
2. **Motivate guests** to register
3. **Collect data** about guest interests
4. **Work GDPR-compliant**

## Technical Implementation

### GuestWishlistService

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\DTO\Request\CreateGuestWishlistRequest;
use AdvancedWishlist\Core\DTO\Response\WishlistResponse;
use AdvancedWishlist\Core\Content\GuestWishlist\GuestWishlistEntity;
use AdvancedWishlist\Core\Event\GuestWishlistCreatedEvent;
use AdvancedWishlist\Core\Event\GuestWishlistMergedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;

class GuestWishlistService
{
    private const COOKIE_NAME = 'guest_wishlist_id';
    private const DEFAULT_TTL = 2592000; // 30 days
    private const MAX_ITEMS_GUEST = 50;
    private const CLEANUP_BATCH_SIZE = 100;
    
    public function __construct(
        private EntityRepository $guestWishlistRepository,
        private EntityRepository $wishlistRepository,
        private WishlistService $wishlistService,
        private GuestIdentifierService $identifierService,
        private RequestStack $requestStack,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private int $guestWishlistTtl = self::DEFAULT_TTL
    ) {}
    
    /**
     * Get or create guest wishlist
     */
    public function getOrCreateGuestWishlist(
        SalesChannelContext $context
    ): GuestWishlistEntity {
        // 1. Check if user is logged in
        if ($context->getCustomer()) {
            throw new \LogicException('Guest wishlist not available for logged in users');
        }
        
        // 2. Get guest identifier
        $guestId = $this->identifierService->getOrCreateGuestId($context);
        
        // 3. Try to load existing guest wishlist
        $existingWishlist = $this->findGuestWishlistByIdentifier($guestId, $context);
        
        if ($existingWishlist) {
            // Extend TTL
            $this->extendGuestWishlistTtl($existingWishlist, $context);
            return $existingWishlist;
        }
        
        // 4. Create new guest wishlist
        return $this->createGuestWishlist($guestId, $context);
    }
    
    /**
     * Create new guest wishlist
     */
    private function createGuestWishlist(
        string $guestId,
        SalesChannelContext $context
    ): GuestWishlistEntity {
        $wishlistId = Uuid::randomHex();
        
        $data = [
            'id' => $wishlistId,
            'guestId' => $guestId,
            'sessionId' => $context->getToken(),
            'salesChannelId' => $context->getSalesChannelId(),
            'languageId' => $context->getLanguageId(),
            'currencyId' => $context->getCurrencyId(),
            'name' => 'My Wishlist',
            'items' => [],
            'expiresAt' => $this->calculateExpiryDate(),
            'ipAddress' => $this->getIpAddress(),
            'userAgent' => $this->getUserAgent(),
        ];
        
        $this->guestWishlistRepository->create([$data], $context->getContext());
        
        // Set cookie
        $this->setGuestWishlistCookie($wishlistId);
        
        // Dispatch event
        $event = new GuestWishlistCreatedEvent($wishlistId, $guestId, $context);
        $this->eventDispatcher->dispatch($event);
        
        $this->logger->info('Guest wishlist created', [
            'wishlistId' => $wishlistId,
            'guestId' => $guestId,
        ]);
        
        return $this->loadGuestWishlist($wishlistId, $context->getContext());
    }
    
    /**
     * Add item to guest wishlist
     */
    public function addItemToGuestWishlist(
        string $productId,
        array $options,
        SalesChannelContext $context
    ): void {
        $guestWishlist = $this->getOrCreateGuestWishlist($context);
        
        // Check item limit
        if (count($guestWishlist->getItems()) >= self::MAX_ITEMS_GUEST) {
            throw new GuestWishlistLimitException(
                'Maximum number of items reached for guest wishlist',
                ['limit' => self::MAX_ITEMS_GUEST]
            );
        }
        
        // Check for duplicate
        $existingItem = $this->findItemInGuestWishlist($guestWishlist, $productId);
        if ($existingItem) {
            // Update quantity instead
            $this->updateGuestWishlistItem(
                $guestWishlist->getId(),
                $existingItem['id'],
                ['quantity' => $existingItem['quantity'] + 1],
                $context->getContext()
            );
            return;
        }
        
        // Add new item
        $itemId = Uuid::randomHex();
        $items = $guestWishlist->getItems();
        $items[] = [
            'id' => $itemId,
            'productId' => $productId,
            'quantity' => $options['quantity'] ?? 1,
            'note' => $options['note'] ?? null,
            'addedAt' => new \DateTime(),
            'productSnapshot' => $this->createProductSnapshot($productId, $context),
        ];
        
        $this->guestWishlistRepository->update([
            [
                'id' => $guestWishlist->getId(),
                'items' => $items,
                'updatedAt' => new \DateTime(),
            ]
        ], $context->getContext());
        
        // Track analytics
        $this->trackGuestActivity('item_added', [
            'productId' => $productId,
            'wishlistId' => $guestWishlist->getId(),
        ]);
    }
    
    /**
     * Remove item from guest wishlist
     */
    public function removeItemFromGuestWishlist(
        string $itemId,
        SalesChannelContext $context
    ): void {
        $guestWishlist = $this->getOrCreateGuestWishlist($context);
        
        $items = array_filter(
            $guestWishlist->getItems(),
            fn($item) => $item['id'] !== $itemId
        );
        
        $this->guestWishlistRepository->update([
            [
                'id' => $guestWishlist->getId(),
                'items' => array_values($items),
                'updatedAt' => new \DateTime(),
            ]
        ], $context->getContext());
    }
    
    /**
     * Merge guest wishlist with customer wishlist after login/registration
     */
    public function mergeGuestWishlistToCustomer(
        string $customerId,
        SalesChannelContext $context
    ): void {
        // 1. Get guest wishlist
        $guestId = $this->identifierService->getGuestIdFromCookie();
        if (!$guestId) {
            return; // No guest wishlist to merge
        }
        
        $guestWishlist = $this->findGuestWishlistByIdentifier($guestId, $context);
        if (!$guestWishlist || empty($guestWishlist->getItems())) {
            return; // Nothing to merge
        }
        
        // 2. Get or create customer's default wishlist
        $customerWishlist = $this->wishlistService->getOrCreateDefaultWishlist(
            $customerId,
            $context->getContext()
        );
        
        // 3. Merge items
        $mergedCount = 0;
        $skippedCount = 0;
        
        foreach ($guestWishlist->getItems() as $guestItem) {
            try {
                // Check if product already in customer wishlist
                if ($this->wishlistService->hasProduct($customerWishlist->getId(), $guestItem['productId'])) {
                    $skippedCount++;
                    continue;
                }
                
                // Add to customer wishlist
                $this->wishlistService->addItem(
                    new AddItemRequest([
                        'wishlistId' => $customerWishlist->getId(),
                        'productId' => $guestItem['productId'],
                        'quantity' => $guestItem['quantity'],
                        'note' => $guestItem['note'],
                    ]),
                    $context->getContext()
                );
                
                $mergedCount++;
            } catch (\Exception $e) {
                $this->logger->warning('Failed to merge guest wishlist item', [
                    'productId' => $guestItem['productId'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // 4. Store merge record for analytics
        $this->recordMergeAction($guestWishlist, $customerWishlist, [
            'mergedItems' => $mergedCount,
            'skippedItems' => $skippedCount,
        ]);
        
        // 5. Delete guest wishlist
        $this->deleteGuestWishlist($guestWishlist->getId(), $context->getContext());
        
        // 6. Clear guest cookie
        $this->clearGuestWishlistCookie();
        
        // 7. Dispatch event
        $event = new GuestWishlistMergedEvent(
            $guestWishlist->getId(),
            $customerWishlist->getId(),
            $mergedCount,
            $context
        );
        $this->eventDispatcher->dispatch($event);
        
        $this->logger->info('Guest wishlist merged', [
            'guestWishlistId' => $guestWishlist->getId(),
            'customerWishlistId' => $customerWishlist->getId(),
            'mergedItems' => $mergedCount,
            'skippedItems' => $skippedCount,
        ]);
    }
    
    /**
     * Send reminder email to guest
     */
    public function sendGuestReminder(
        string $guestWishlistId,
        string $email,
        Context $context
    ): void {
        $guestWishlist = $this->loadGuestWishlist($guestWishlistId, $context);
        
        if (!$guestWishlist || empty($guestWishlist->getItems())) {
            throw new \InvalidArgumentException('Guest wishlist not found or empty');
        }
        
        // Generate secure link for guest to access wishlist
        $token = $this->generateGuestAccessToken($guestWishlistId);
        $accessUrl = $this->generateGuestAccessUrl($token);
        
        // Send email
        $this->emailService->sendGuestWishlistReminder(
            $email,
            $guestWishlist,
            $accessUrl,
            $context
        );
        
        // Record email sent
        $this->guestWishlistRepository->update([
            [
                'id' => $guestWishlistId,
                'reminderSentAt' => new \DateTime(),
                'reminderEmail' => $email,
            ]
        ], $context);
    }
    
    /**
     * Clean up expired guest wishlists
     */
    public function cleanupExpiredGuestWishlists(Context $context): int
    {
        $deletedCount = 0;
        $offset = 0;
        
        do {
            $criteria = new Criteria();
            $criteria->addFilter(new RangeFilter('expiresAt', [
                RangeFilter::LTE => (new \DateTime())->format('c'),
            ]));
            $criteria->setLimit(self::CLEANUP_BATCH_SIZE);
            $criteria->setOffset($offset);
            
            $expiredWishlists = $this->guestWishlistRepository->search($criteria, $context);
            
            if ($expiredWishlists->count() === 0) {
                break;
            }
            
            $ids = array_map(
                fn($wishlist) => ['id' => $wishlist->getId()],
                $expiredWishlists->getElements()
            );
            
            $this->guestWishlistRepository->delete($ids, $context);
            $deletedCount += count($ids);
            
            $offset += self::CLEANUP_BATCH_SIZE;
            
        } while ($expiredWishlists->count() === self::CLEANUP_BATCH_SIZE);
        
        $this->logger->info('Cleaned up expired guest wishlists', [
            'count' => $deletedCount,
        ]);
        
        return $deletedCount;
    }
    
    /**
     * Helper: Set guest wishlist cookie
     */
    private function setGuestWishlistCookie(string $wishlistId): void
    {
        $response = $this->requestStack->getCurrentRequest()->attributes->get('_response');
        
        if (!$response) {
            return;
        }
        
        $cookie = Cookie::create(self::COOKIE_NAME)
            ->withValue($wishlistId)
            ->withExpires(time() + $this->guestWishlistTtl)
            ->withPath('/')
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite('Lax');
            
        $response->headers->setCookie($cookie);
    }
    
    /**
     * Helper: Calculate expiry date
     */
    private function calculateExpiryDate(): \DateTime
    {
        return (new \DateTime())->add(
            new \DateInterval('PT' . $this->guestWishlistTtl . 'S')
        );
    }
    
    /**
     * Helper: Create product snapshot for offline viewing
     */
    private function createProductSnapshot(
        string $productId,
        SalesChannelContext $context
    ): array {
        $product = $this->productRepository->search(
            new Criteria([$productId]),
            $context
        )->first();
        
        if (!$product) {
            return [];
        }
        
        return [
            'name' => $product->getTranslated()['name'],
            'productNumber' => $product->getProductNumber(),
            'price' => $product->getCheapestPrice()->getGross(),
            'image' => $product->getCover()?->getMedia()?->getUrl(),
            'manufacturer' => $product->getManufacturer()?->getTranslated()['name'],
        ];
    }
}
```

### Guest Identifier Service

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

class GuestIdentifierService
{
    private const COOKIE_NAME = 'guest_id';
    private const ID_LENGTH = 32;
    private const COOKIE_LIFETIME = 2592000; // 30 days
    
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
```

### Frontend Implementation

```vue
<template>
  <div class="guest-wishlist">
    <!-- Guest Wishlist Notice -->
    <div v-if="!isLoggedIn && hasItems" class="guest-notice">
      <div class="notice-content">
        <i class="icon-info"></i>
        <p>
          Your wishlist is temporarily saved. 
          <router-link to="/account/register">Register</router-link> 
          to save it permanently.
        </p>
      </div>
      
      <button @click="showEmailReminder = true" class="btn-reminder">
        <i class="icon-email"></i>
        Send reminder
      </button>
    </div>
    
    <!-- Guest Wishlist Items -->
    <div class="wishlist-items">
      <h2>Your Wishlist ({{ itemCount }} items)</h2>
      
      <div v-if="loading" class="loading">
        <spinner />
      </div>
      
      <div v-else-if="items.length" class="items-grid">
        <guest-wishlist-item
          v-for="item in items"
          :key="item.id"
          :item="item"
          @remove="removeItem"
          @update="updateItem"
        />
      </div>
      
      <div v-else class="empty-state">
        <i class="icon-heart-empty"></i>
        <h3>Your wishlist is empty</h3>
        <p>Save your favorite products for later</p>
        <router-link to="/products" class="btn-primary">
          Discover products
        </router-link>
      </div>
    </div>
    
    <!-- Email Reminder Modal -->
    <modal v-if="showEmailReminder" @close="showEmailReminder = false">
      <div class="email-reminder-form">
        <h3>Save wishlist via email</h3>
        <p>
          Receive a link to your wishlist via email, 
          so you can access it later.
        </p>
        
        <form @submit.prevent="sendReminder">
          <div class="form-group">
            <label>Email address:</label>
            <input 
              type="email" 
              v-model="reminderEmail"
              required
              placeholder="your@email.com"
            >
          </div>
          
          <div class="form-group checkbox">
            <label>
              <input 
                type="checkbox" 
                v-model="consentNewsletter"
              >
              I want to be informed about offers and news
            </label>
          </div>
          
          <div class="form-actions">
            <button type="button" @click="showEmailReminder = false">
              Cancel
            </button>
            <button type="submit" class="btn-primary">
              Send reminder
            </button>
          </div>
        </form>
      </div>
    </modal>
    
    <!-- Conversion Prompt -->
    <div v-if="shouldShowConversionPrompt" class="conversion-prompt">
      <h3>Don't miss any offers!</h3>
      <p>
        Register and receive notifications 
        when your wishlist products go on sale.
      </p>
      <button @click="registerWithWishlist" class="btn-primary">
        Register now & save wishlist
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useGuestWishlistStore } from '@/stores/guestWishlist'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { useNotification } from '@/composables/useNotification'
import GuestWishlistItem from './GuestWishlistItem.vue'
import Modal from '@/components/Modal.vue'
import Spinner from '@/components/Spinner.vue'

const guestWishlistStore = useGuestWishlistStore()
const authStore = useAuthStore()
const router = useRouter()
const notification = useNotification()

const showEmailReminder = ref(false)
const reminderEmail = ref('')
const consentNewsletter = ref(false)

const isLoggedIn = computed(() => authStore.isLoggedIn)
const items = computed(() => guestWishlistStore.items)
const itemCount = computed(() => items.value.length)
const loading = computed(() => guestWishlistStore.loading)
const hasItems = computed(() => itemCount.value > 0)

const shouldShowConversionPrompt = computed(() => {
  return !isLoggedIn.value && itemCount.value >= 3
})

onMounted(async () => {
  if (!isLoggedIn.value) {
    await guestWishlistStore.loadGuestWishlist()
  }
})

// Watch for login to trigger merge
watch(isLoggedIn, async (newValue, oldValue) => {
  if (newValue && !oldValue && hasItems.value) {
    // User just logged in, merge wishlist
    await guestWishlistStore.mergeToCustomer()
    notification.success('Your wishlist has been transferred!')
  }
})

async function removeItem(itemId) {
  try {
    await guestWishlistStore.removeItem(itemId)
    notification.success('Item removed')
  } catch (error) {
    notification.error('Error removing item')
  }
}

async function updateItem(itemId, data) {
  try {
    await guestWishlistStore.updateItem(itemId, data)
  } catch (error) {
    notification.error('Error updating item')
  }
}

async function sendReminder() {
  try {
    await guestWishlistStore.sendEmailReminder({
      email: reminderEmail.value,
      consentNewsletter: consentNewsletter.value
    })
    
    showEmailReminder.value = false
    notification.success('Reminder has been sent!')
    
    // Track conversion opportunity
    trackEvent('guest_wishlist_reminder_sent', {
      itemCount: itemCount.value,
      newsletter: consentNewsletter.value
    })
  } catch (error) {
    notification.error('Error sending reminder')
  }
}

function registerWithWishlist() {
  // Store current wishlist state
  sessionStorage.setItem('redirect_after_register', '/account/wishlist')
  
  router.push({
    name: 'account-register',
    query: { wishlist: 'true' }
  })
}

// Auto-save functionality
const autoSaveDebounced = debounce(() => {
  guestWishlistStore.saveToLocal()
}, 1000)

watch(items, () => {
  autoSaveDebounced()
}, { deep: true })
</script>

<style scoped>
.guest-notice {
  background: #f0f8ff;
  border: 1px solid #b8daff;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.notice-content {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.notice-content i {
  color: #0056b3;
  font-size: 1.5rem;
}

.btn-reminder {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: white;
  border: 1px solid #0056b3;
  color: #0056b3;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
}

.btn-reminder:hover {
  background: #0056b3;
  color: white;
}

.conversion-prompt {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 2rem;
  border-radius: 12px;
  text-align: center;
  margin-top: 3rem;
}

.conversion-prompt h3 {
  margin-bottom: 1rem;
}

.conversion-prompt .btn-primary {
  background: white;
  color: #667eea;
  font-weight: bold;
  padding: 0.75rem 2rem;
}

.email-reminder-form {
  padding: 2rem;
  max-width: 400px;
}

.email-reminder-form h3 {
  margin-bottom: 1rem;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.form-group input[type="email"] {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.form-group.checkbox label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: normal;
}

.form-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

.items-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-top: 2rem;
}

.empty-state {
  text-align: center;
  padding: 4rem 2rem;
}

.empty-state i {
  font-size: 4rem;
  color: #ddd;
  margin-bottom: 1rem;
}
</style>
```

### Pinia Store for Guest Wishlist

```javascript
import { defineStore } from 'pinia'
import { guestWishlistApi } from '@/api/guestWishlist'
import { useAuthStore } from './auth'

export const useGuestWishlistStore = defineStore('guestWishlist', {
  state: () => ({
    items: [],
    loading: false,
    error: null,
    lastSync: null,
  }),
  
  getters: {
    itemCount: (state) => state.items.length,
    
    totalValue: (state) => 
      state.items.reduce((sum, item) => sum + (item.price * item.quantity), 0),
    
    hasProduct: (state) => (productId) =>
      state.items.some(item => item.productId === productId),
  },
  
  actions: {
    async loadGuestWishlist() {
      this.loading = true
      try {
        // Try to load from server
        const response = await guestWishlistApi.getGuestWishlist()
        this.items = response.data.items || []
        this.lastSync = new Date()
        
        // Save to local storage as backup
        this.saveToLocal()
      } catch (error) {
        // Fallback to local storage
        this.loadFromLocal()
      } finally {
        this.loading = false
      }
    },
    
    async addItem(productId, options = {}) {
      try {
        const response = await guestWishlistApi.addItem({
          productId,
          ...options
        })
        
        this.items.push(response.data)
        this.saveToLocal()
        
        // Track event
        this.trackEvent('guest_item_added', { productId })
        
        return response.data
      } catch (error) {
        this.error = error.message
        throw error
      }
    },
    
    async removeItem(itemId) {
      try {
        await guestWishlistApi.removeItem(itemId)
        
        this.items = this.items.filter(item => item.id !== itemId)
        this.saveToLocal()
        
        // Track event
        this.trackEvent('guest_item_removed', { itemId })
      } catch (error) {
        this.error = error.message
        throw error
      }
    },
    
    async updateItem(itemId, data) {
      try {
        const response = await guestWishlistApi.updateItem(itemId, data)
        
        const index = this.items.findIndex(item => item.id === itemId)
        if (index !== -1) {
          this.items[index] = response.data
        }
        
        this.saveToLocal()
      } catch (error) {
        this.error = error.message
        throw error
      }
    },
    
    async sendEmailReminder(data) {
      try {
        await guestWishlistApi.sendReminder(data)
        
        // Track conversion opportunity
        this.trackEvent('guest_reminder_sent', {
          itemCount: this.items.length,
          newsletter: data.consentNewsletter
        })
      } catch (error) {
        this.error = error.message
        throw error
      }
    },
    
    async mergeToCustomer() {
      const authStore = useAuthStore()
      
      if (!authStore.isLoggedIn || this.items.length === 0) {
        return
      }
      
      try {
        const response = await guestWishlistApi.mergeToCustomer()
        
        // Clear guest wishlist
        this.items = []
        this.clearLocal()
        
        // Track successful merge
        this.trackEvent('guest_wishlist_merged', {
          mergedItems: response.data.mergedItems,
          skippedItems: response.data.skippedItems
        })
        
        return response.data
      } catch (error) {
        console.error('Failed to merge wishlist:', error)
      }
    },
    
    // Local storage methods
    saveToLocal() {
      try {
        const data = {
          items: this.items,
          lastSync: this.lastSync,
          version: '1.0'
        }
        
        localStorage.setItem('guest_wishlist', JSON.stringify(data))
      } catch (error) {
        console.error('Failed to save to local storage:', error)
      }
    },
    
    loadFromLocal() {
      try {
        const stored = localStorage.getItem('guest_wishlist')
        
        if (stored) {
          const data = JSON.parse(stored)
          
          // Check version compatibility
          if (data.version === '1.0') {
            this.items = data.items || []
            this.lastSync = data.lastSync ? new Date(data.lastSync) : null
          }
        }
      } catch (error) {
        console.error('Failed to load from local storage:', error)
      }
    },
    
    clearLocal() {
      try {
        localStorage.removeItem('guest_wishlist')
      } catch (error) {
        console.error('Failed to clear local storage:', error)
      }
    },
    
    // Analytics
    trackEvent(event, data = {}) {
      if (window.gtag) {
        window.gtag('event', event, {
          event_category: 'guest_wishlist',
          ...data
        })
      }
    }
  },
})
```

## Database Schema

```sql
-- Guest wishlist table
CREATE TABLE `guest_wishlist` (
  `id` BINARY(16) NOT NULL,
  `guest_id` VARCHAR(64) NOT NULL,
  `session_id` VARCHAR(128),
  `sales_channel_id` BINARY(16) NOT NULL,
  `language_id` BINARY(16) NOT NULL,
  `currency_id` BINARY(16) NOT NULL,
  `name` VARCHAR(255) DEFAULT 'Guest Wishlist',
  `items` JSON NOT NULL,
  `expires_at` DATETIME(3) NOT NULL,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(500),
  `reminder_sent_at` DATETIME(3),
  `reminder_email` VARCHAR(255),
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3),
  PRIMARY KEY (`id`),
  KEY `idx.guest_wishlist.guest_id` (`guest_id`),
  KEY `idx.guest_wishlist.expires` (`expires_at`),
  KEY `idx.guest_wishlist.session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guest wishlist merge log
CREATE TABLE `guest_wishlist_merge_log` (
  `id` BINARY(16) NOT NULL,
  `guest_wishlist_id` BINARY(16) NOT NULL,
  `customer_wishlist_id` BINARY(16) NOT NULL,
  `customer_id` BINARY(16) NOT NULL,
  `items_merged` INT DEFAULT 0,
  `items_skipped` INT DEFAULT 0,
  `merge_data` JSON,
  `merged_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx.merge_log.customer` (`customer_id`),
  KEY `idx.merge_log.date` (`merged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Conversion Strategies

### Progressive Engagement

```php
class GuestConversionService
{
    private const ENGAGEMENT_THRESHOLDS = [
        'low' => 1,      // 1 item
        'medium' => 3,   // 3 items  
        'high' => 5,     // 5+ items
    ];
    
    public function getEngagementLevel(GuestWishlistEntity $wishlist): string
    {
        $itemCount = count($wishlist->getItems());
        
        if ($itemCount >= self::ENGAGEMENT_THRESHOLDS['high']) {
            return 'high';
        } elseif ($itemCount >= self::ENGAGEMENT_THRESHOLDS['medium']) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    public function getConversionMessage(string $level): array
    {
        return match($level) {
            'high' => [
                'title' => 'You\'ve selected great products!',
                'message' => 'Register now and get 10% off your first order.',
                'cta' => 'Register now & save',
                'incentive' => '10% discount'
            ],
            'medium' => [
                'title' => 'Don\'t miss any offers!',
                'message' => 'As a registered customer you receive price alert notifications.',
                'cta' => 'Register for free',
                'incentive' => 'Price alerts'
            ],
            'low' => [
                'title' => 'Save your selection',
                'message' => 'Save your wishlist permanently and access it from anywhere.',
                'cta' => 'Create account',
                'incentive' => 'Save permanently'
            ],
        };
    }
}
```

### Email Capture

```javascript
// Progressive email capture
export const useEmailCapture = () => {
    const showEmailCapture = ref(false)
    const capturedEmail = ref('')

    const shouldShowCapture = computed(() => {
        const guestStore = useGuestWishlistStore()
        const hasEmail = localStorage.getItem('guest_email')

        return guestStore.itemCount >= 2 && !hasEmail
    })

    const captureEmail = async (email) => {
        try {
            // Save email
            localStorage.setItem('guest_email', email)

            // Send to backend
            await api.post('/guest-wishlist/capture-email', { email })

            // Track conversion
            gtag('event', 'email_captured', {
                event_category: 'guest_conversion',
                method: 'wishlist'
            })

            return true
        } catch (error) {
            console.error('Email capture failed:', error)
            return false
        }
    }

    return {
        showEmailCapture,
        capturedEmail,
        shouldShowCapture,
        captureEmail
    }
}
```

## Security Considerations

### Cookie Security

```php
class GuestWishlistCookieService
{
    private const COOKIE_OPTIONS = [
        'expires' => 2592000, // 30 days
        'path' => '/',
        'domain' => null,
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    
    public function setSecureCookie(string $name, string $value): void
    {
        $options = self::COOKIE_OPTIONS;
        $options['expires'] = time() + $options['expires'];
        
        setcookie($name, $value, $options);
    }
}
```

### Rate Limiting

```php
class GuestWishlistRateLimiter
{
    private const LIMITS = [
        'add_item' => ['limit' => 50, 'window' => 3600],
        'create_wishlist' => ['limit' => 5, 'window' => 3600],
        'send_reminder' => ['limit' => 3, 'window' => 86400],
    ];
}
```

## GDPR Compliance

### Data Retention

```php
class GuestDataRetentionService
{
    public function getRetentionPolicy(): array
    {
        return [
            'wishlist_data' => 30, // days
            'email_address' => 90, // days if consent given
            'analytics_data' => 365, // days (anonymized)
        ];
    }
    
    public function anonymizeExpiredData(Context $context): void
    {
        // Anonymize guest data after retention period
        $this->guestWishlistRepository->anonymizeExpired($context);
    }
}
```