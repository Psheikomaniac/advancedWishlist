# Frontend Components Documentation - Advanced Wishlist System

## Overview

The frontend components are developed with Vue.js 3 and the Composition API. They follow a modular design system and are fully TypeScript typed.

## Component Architecture

```
/src/components/
├── wishlist/
│   ├── core/
│   │   ├── WishlistButton.vue
│   │   ├── WishlistManager.vue
│   │   ├── WishlistItem.vue
│   │   └── WishlistSelector.vue
│   ├── modals/
│   │   ├── CreateWishlistModal.vue
│   │   ├── ShareWishlistModal.vue
│   │   └── MoveItemModal.vue
│   ├── sharing/
│   │   ├── ShareOptions.vue
│   │   ├── ShareLink.vue
│   │   └── SocialShare.vue
│   ├── analytics/
│   │   ├── WishlistStats.vue
│   │   ├── PriceHistory.vue
│   │   └── ConversionBadge.vue
│   └── guest/
│       ├── GuestWishlist.vue
│       └── GuestReminder.vue
├── common/
│   ├── BaseButton.vue
│   ├── BaseModal.vue
│   └── BaseTooltip.vue
└── icons/
    └── WishlistIcons.vue
```

## Core Components

### WishlistButton

The central button for adding/removing products.

```vue
<template>
  <button
    :class="[
      'wishlist-button',
      {
        'wishlist-button--active': isInWishlist,
        'wishlist-button--loading': isLoading,
        'wishlist-button--guest': !isLoggedIn
      }
    ]"
    :disabled="isLoading"
    :aria-label="ariaLabel"
    :aria-pressed="isInWishlist"
    @click="handleClick"
  >
    <transition name="heart-flip" mode="out-in">
      <wishlist-icon
        :key="iconKey"
        :filled="isInWishlist"
        :class="['wishlist-button__icon', { 'pulse': justAdded }]"
      />
    </transition>
    
    <span v-if="showLabel" class="wishlist-button__label">
      {{ buttonLabel }}
    </span>
    
    <base-tooltip v-if="showTooltip" :content="tooltipContent" />
  </button>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useWishlistStore } from '@/stores/wishlist'
import { useAuthStore } from '@/stores/auth'
import { useNotification } from '@/composables/useNotification'
import { useAnalytics } from '@/composables/useAnalytics'
import WishlistIcon from '@/components/icons/WishlistIcon.vue'
import BaseTooltip from '@/components/common/BaseTooltip.vue'

interface Props {
  productId: string
  productName?: string
  productPrice?: number
  showLabel?: boolean
  size?: 'sm' | 'md' | 'lg'
  variant?: 'default' | 'minimal' | 'floating'
  position?: 'inline' | 'absolute'
}

const props = withDefaults(defineProps<Props>(), {
  showLabel: false,
  size: 'md',
  variant: 'default',
  position: 'inline'
})

const emit = defineEmits<{
  added: [wishlistId: string]
  removed: [wishlistId: string]
  toggle: [isInWishlist: boolean]
}>()

const wishlistStore = useWishlistStore()
const authStore = useAuthStore()
const notification = useNotification()
const analytics = useAnalytics()

const isLoading = ref(false)
const justAdded = ref(false)

const isLoggedIn = computed(() => authStore.isLoggedIn)
const isInWishlist = computed(() => 
  wishlistStore.hasProduct(props.productId)
)

const selectedWishlist = computed(() => 
  wishlistStore.getProductWishlist(props.productId) || wishlistStore.defaultWishlist
)

const buttonLabel = computed(() => {
  if (isLoading.value) return 'Loading...'
  return isInWishlist.value ? 'Remove from Wishlist' : 'Add to Wishlist'
})

const ariaLabel = computed(() => {
  return `${buttonLabel.value} - ${props.productName || 'Product'}`
})

const tooltipContent = computed(() => {
  if (!isLoggedIn.value) {
    return 'Sign in to save items'
  }
  if (isInWishlist.value && selectedWishlist.value) {
    return `In "${selectedWishlist.value.name}"`
  }
  return buttonLabel.value
})

const showTooltip = computed(() => 
  !props.showLabel || !isLoggedIn.value
)

const iconKey = computed(() => 
  `${isInWishlist.value}-${isLoading.value}`
)

async function handleClick() {
  if (!isLoggedIn.value) {
    handleGuestClick()
    return
  }
  
  if (isLoading.value) return
  
  isLoading.value = true
  
  try {
    if (isInWishlist.value) {
      await removeFromWishlist()
    } else {
      await addToWishlist()
    }
  } catch (error) {
    notification.error('An error occurred. Please try again.')
  } finally {
    isLoading.value = false
  }
}

async function addToWishlist() {
  const wishlist = wishlistStore.defaultWishlist || wishlistStore.wishlists[0]
  
  if (!wishlist) {
    notification.error('No wishlist available')
    return
  }
  
  await wishlistStore.addItem(wishlist.id, props.productId, {
    productName: props.productName,
    productPrice: props.productPrice
  })
  
  justAdded.value = true
  setTimeout(() => { justAdded.value = false }, 1000)
  
  notification.success(`Added to ${wishlist.name}`)
  
  // Track analytics
  analytics.trackWishlistItemAdded({
    wishlistId: wishlist.id,
    productId: props.productId,
    productPrice: props.productPrice
  })
  
  emit('added', wishlist.id)
  emit('toggle', true)
}

async function removeFromWishlist() {
  if (!selectedWishlist.value) return
  
  const item = selectedWishlist.value.items.find(
    item => item.productId === props.productId
  )
  
  if (!item) return
  
  await wishlistStore.removeItem(selectedWishlist.value.id, item.id)
  
  notification.info('Removed from wishlist')
  
  // Track analytics
  analytics.trackWishlistItemRemoved({
    wishlistId: selectedWishlist.value.id,
    productId: props.productId
  })
  
  emit('removed', selectedWishlist.value.id)
  emit('toggle', false)
}

function handleGuestClick() {
  // Handle guest wishlist
  const added = wishlistStore.toggleGuestWishlist(props.productId)
  
  if (added) {
    notification.info('Added to your wishlist. Sign in to save it permanently.')
  } else {
    notification.info('Removed from wishlist')
  }
  
  emit('toggle', added)
}

// Keyboard support
function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    handleClick()
  }
}
</script>

<style scoped>
.wishlist-button {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: var(--wishlist-button-padding, 0.5rem);
  background: var(--wishlist-button-bg, transparent);
  border: var(--wishlist-button-border, 1px solid #ddd);
  border-radius: var(--wishlist-button-radius, 4px);
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
  font-family: inherit;
  font-size: var(--wishlist-button-font-size, 1rem);
  color: var(--wishlist-button-color, inherit);
}

/* Size variants */
.wishlist-button--sm {
  --wishlist-button-padding: 0.25rem;
  --wishlist-button-font-size: 0.875rem;
}

.wishlist-button--lg {
  --wishlist-button-padding: 0.75rem 1rem;
  --wishlist-button-font-size: 1.125rem;
}

/* State modifiers */
.wishlist-button:hover:not(:disabled) {
  background: var(--wishlist-button-hover-bg, #f5f5f5);
  border-color: var(--wishlist-button-hover-border, #999);
}

.wishlist-button--active {
  background: var(--wishlist-button-active-bg, #fff);
  border-color: var(--wishlist-button-active-border, #e91e63);
}

.wishlist-button--active .wishlist-button__icon {
  color: var(--wishlist-button-active-color, #e91e63);
}

.wishlist-button--loading {
  opacity: 0.7;
  cursor: not-allowed;
}

.wishlist-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Icon animations */
.wishlist-button__icon {
  width: 1.25em;
  height: 1.25em;
  transition: transform 0.2s ease;
}

.wishlist-button:hover:not(:disabled) .wishlist-button__icon {
  transform: scale(1.1);
}

.wishlist-button__icon.pulse {
  animation: pulse 0.5s ease;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.3); }
}

/* Heart flip transition */
.heart-flip-enter-active,
.heart-flip-leave-active {
  transition: transform 0.3s ease;
}

.heart-flip-enter-from {
  transform: rotateY(90deg);
}

.heart-flip-leave-to {
  transform: rotateY(-90deg);
}

/* Variant: Minimal */
.wishlist-button--minimal {
  --wishlist-button-bg: transparent;
  --wishlist-button-border: none;
  --wishlist-button-padding: 0.25rem;
}

/* Variant: Floating */
.wishlist-button--floating {
  position: absolute;
  top: 1rem;
  right: 1rem;
  background: white;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  border-radius: 50%;
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  justify-content: center;
}

/* Guest state */
.wishlist-button--guest {
  border-style: dashed;
}

/* Focus styles */
.wishlist-button:focus-visible {
  outline: 2px solid var(--wishlist-focus-color, #007bff);
  outline-offset: 2px;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
  .wishlist-button {
    --wishlist-button-bg: rgba(255,255,255,0.1);
    --wishlist-button-border: 1px solid rgba(255,255,255,0.2);
    --wishlist-button-color: #fff;
  }
}
</style>
```

### WishlistManager

Central management component for all wishlists.

```vue
<template>
  <div class="wishlist-manager">
    <!-- Header -->
    <div class="wishlist-manager__header">
      <h2 class="wishlist-manager__title">My Wishlists</h2>
      
      <div class="wishlist-manager__actions">
        <base-button
          variant="primary"
          size="sm"
          @click="showCreateModal = true"
        >
          <icon-plus />
          Create Wishlist
        </base-button>
        
        <wishlist-view-toggle v-model="viewMode" />
      </div>
    </div>
    
    <!-- Wishlist Selector -->
    <wishlist-selector
      v-model="selectedWishlistId"
      :wishlists="wishlists"
      @change="handleWishlistChange"
    />
    
    <!-- Loading State -->
    <div v-if="loading" class="wishlist-manager__loading">
      <base-spinner size="lg" />
      <p>Loading your wishlists...</p>
    </div>
    
    <!-- Empty State -->
    <div v-else-if="!wishlists.length" class="wishlist-manager__empty">
      <empty-state
        icon="heart"
        title="No wishlists yet"
        description="Create your first wishlist to start saving items"
      >
        <base-button variant="primary" @click="showCreateModal = true">
          Create Your First Wishlist
        </base-button>
      </empty-state>
    </div>
    
    <!-- Wishlist Content -->
    <div v-else class="wishlist-manager__content">
      <!-- Wishlist Info -->
      <wishlist-info
        v-if="currentWishlist"
        :wishlist="currentWishlist"
        @edit="handleEdit"
        @share="handleShare"
        @delete="handleDelete"
      />
      
      <!-- Search and Filters -->
      <div class="wishlist-manager__controls">
        <search-input
          v-model="searchQuery"
          placeholder="Search items..."
          @search="handleSearch"
        />
        
        <filter-dropdown
          v-model="filters"
          :options="filterOptions"
          @change="handleFilterChange"
        />
        
        <sort-dropdown
          v-model="sortBy"
          :options="sortOptions"
          @change="handleSortChange"
        />
      </div>
      
      <!-- Items View -->
      <transition name="fade" mode="out-in">
        <component
          :is="viewComponent"
          :items="filteredItems"
          :view-mode="viewMode"
          @item-click="handleItemClick"
          @item-remove="handleItemRemove"
          @item-update="handleItemUpdate"
          @item-move="handleItemMove"
        />
      </transition>
      
      <!-- Bulk Actions -->
      <bulk-actions
        v-if="selectedItems.length > 0"
        :selected-count="selectedItems.length"
        @move="handleBulkMove"
        @delete="handleBulkDelete"
        @clear="clearSelection"
      />
      
      <!-- Pagination -->
      <base-pagination
        v-if="totalPages > 1"
        v-model="currentPage"
        :total-pages="totalPages"
        :per-page="itemsPerPage"
        @change="handlePageChange"
      />
    </div>
    
    <!-- Modals -->
    <create-wishlist-modal
      v-model="showCreateModal"
      @created="handleWishlistCreated"
    />
    
    <share-wishlist-modal
      v-if="wishlistToShare"
      v-model="showShareModal"
      :wishlist="wishlistToShare"
      @shared="handleWishlistShared"
    />
    
    <move-item-modal
      v-if="itemsToMove.length > 0"
      v-model="showMoveModal"
      :items="itemsToMove"
      :current-wishlist="currentWishlist"
      :wishlists="moveTargetWishlists"
      @moved="handleItemsMoved"
    />
    
    <confirm-dialog
      v-model="showDeleteConfirm"
      title="Delete Wishlist?"
      :message="deleteConfirmMessage"
      confirm-text="Delete"
      confirm-variant="danger"
      @confirm="confirmDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useWishlistStore } from '@/stores/wishlist'
import { useRouter } from 'vue-router'
import { useNotification } from '@/composables/useNotification'
import { useDebounce } from '@/composables/useDebounce'
import { usePagination } from '@/composables/usePagination'

// Components
import WishlistSelector from './WishlistSelector.vue'
import WishlistInfo from './WishlistInfo.vue'
import WishlistItemsGrid from './WishlistItemsGrid.vue'
import WishlistItemsList from './WishlistItemsList.vue'
import CreateWishlistModal from '@/components/modals/CreateWishlistModal.vue'
import ShareWishlistModal from '@/components/modals/ShareWishlistModal.vue'
import MoveItemModal from '@/components/modals/MoveItemModal.vue'
import BulkActions from './BulkActions.vue'
import EmptyState from '@/components/common/EmptyState.vue'

// Types
interface FilterOptions {
  availability?: 'all' | 'in-stock' | 'out-of-stock'
  priceRange?: { min: number; max: number }
  categories?: string[]
  hasAlert?: boolean
}

interface SortOption {
  value: string
  label: string
  direction: 'asc' | 'desc'
}

const router = useRouter()
const notification = useNotification()
const wishlistStore = useWishlistStore()

const { wishlists, loading } = storeToRefs(wishlistStore)

// State
const selectedWishlistId = ref<string>('')
const viewMode = ref<'grid' | 'list'>('grid')
const searchQuery = ref('')
const filters = ref<FilterOptions>({})
const sortBy = ref<SortOption>({ value: 'addedAt', label: 'Date Added', direction: 'desc' })
const selectedItems = ref<string[]>([])
const showCreateModal = ref(false)
const showShareModal = ref(false)
const showMoveModal = ref(false)
const showDeleteConfirm = ref(false)
const wishlistToShare = ref<Wishlist | null>(null)
const wishlistToDelete = ref<Wishlist | null>(null)
const itemsToMove = ref<WishlistItem[]>([])

// Computed
const currentWishlist = computed(() =>
  wishlists.value.find(w => w.id === selectedWishlistId.value)
)

const viewComponent = computed(() =>
  viewMode.value === 'grid' ? WishlistItemsGrid : WishlistItemsList
)

const filteredItems = computed(() => {
  if (!currentWishlist.value) return []
  
  let items = [...currentWishlist.value.items]
  
  // Apply search
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    items = items.filter(item =>
      item.product.name.toLowerCase().includes(query) ||
      item.product.productNumber.toLowerCase().includes(query) ||
      item.note?.toLowerCase().includes(query)
    )
  }
  
  // Apply filters
  if (filters.value.availability && filters.value.availability !== 'all') {
    items = items.filter(item =>
      filters.value.availability === 'in-stock' 
        ? item.product.available 
        : !item.product.available
    )
  }
  
  if (filters.value.priceRange) {
    items = items.filter(item =>
      item.product.price.gross >= filters.value.priceRange!.min &&
      item.product.price.gross <= filters.value.priceRange!.max
    )
  }
  
  if (filters.value.hasAlert) {
    items = items.filter(item => item.priceAlertActive)
  }
  
  // Apply sorting
  items.sort((a, b) => {
    const direction = sortBy.value.direction === 'asc' ? 1 : -1
    
    switch (sortBy.value.value) {
      case 'name':
        return a.product.name.localeCompare(b.product.name) * direction
      case 'price':
        return (a.product.price.gross - b.product.price.gross) * direction
      case 'addedAt':
        return (new Date(a.addedAt).getTime() - new Date(b.addedAt).getTime()) * direction
      case 'priority':
        return ((a.priority || 0) - (b.priority || 0)) * direction
      default:
        return 0
    }
  })
  
  return items
})

const filterOptions = [
  {
    key: 'availability',
    label: 'Availability',
    type: 'select',
    options: [
      { value: 'all', label: 'All Items' },
      { value: 'in-stock', label: 'In Stock' },
      { value: 'out-of-stock', label: 'Out of Stock' }
    ]
  },
  {
    key: 'priceRange',
    label: 'Price Range',
    type: 'range',
    min: 0,
    max: 1000
  },
  {
    key: 'hasAlert',
    label: 'Price Alerts',
    type: 'checkbox'
  }
]

const sortOptions: SortOption[] = [
  { value: 'addedAt', label: 'Date Added', direction: 'desc' },
  { value: 'name', label: 'Product Name', direction: 'asc' },
  { value: 'price', label: 'Price', direction: 'asc' },
  { value: 'priority', label: 'Priority', direction: 'desc' }
]

const moveTargetWishlists = computed(() =>
  wishlists.value.filter(w => w.id !== selectedWishlistId.value)
)

const deleteConfirmMessage = computed(() =>
  `Are you sure you want to delete "${wishlistToDelete.value?.name}"? This action cannot be undone.`
)

// Pagination
const {
  currentPage,
  totalPages,
  itemsPerPage,
  paginatedItems,
  goToPage
} = usePagination(filteredItems, 24)

// Methods
async function loadWishlists() {
  await wishlistStore.loadWishlists()
  
  // Select first wishlist or from route
  if (router.currentRoute.value.params.wishlistId) {
    selectedWishlistId.value = router.currentRoute.value.params.wishlistId as string
  } else if (wishlists.value.length > 0) {
    selectedWishlistId.value = wishlists.value[0].id
  }
}

function handleWishlistChange(wishlistId: string) {
  selectedWishlistId.value = wishlistId
  router.push({ params: { wishlistId } })
  clearSelection()
}

function handleEdit(wishlist: Wishlist) {
  router.push({
    name: 'wishlist-edit',
    params: { wishlistId: wishlist.id }
  })
}

function handleShare(wishlist: Wishlist) {
  wishlistToShare.value = wishlist
  showShareModal.value = true
}

function handleDelete(wishlist: Wishlist) {
  wishlistToDelete.value = wishlist
  showDeleteConfirm.value = true
}

async function confirmDelete() {
  if (!wishlistToDelete.value) return
  
  try {
    await wishlistStore.deleteWishlist(wishlistToDelete.value.id)
    notification.success('Wishlist deleted successfully')
    
    // Select another wishlist
    if (wishlists.value.length > 0) {
      selectedWishlistId.value = wishlists.value[0].id
    }
  } catch (error) {
    notification.error('Failed to delete wishlist')
  }
}

function handleItemClick(item: WishlistItem) {
  router.push({
    name: 'product-detail',
    params: { productId: item.productId }
  })
}

async function handleItemRemove(item: WishlistItem) {
  try {
    await wishlistStore.removeItem(currentWishlist.value!.id, item.id)
    notification.success('Item removed from wishlist')
  } catch (error) {
    notification.error('Failed to remove item')
  }
}

async function handleItemUpdate(item: WishlistItem, updates: Partial<WishlistItem>) {
  try {
    await wishlistStore.updateItem(currentWishlist.value!.id, item.id, updates)
    notification.success('Item updated')
  } catch (error) {
    notification.error('Failed to update item')
  }
}

function handleItemMove(item: WishlistItem) {
  itemsToMove.value = [item]
  showMoveModal.value = true
}

function handleBulkMove() {
  itemsToMove.value = selectedItems.value.map(id =>
    currentWishlist.value!.items.find(item => item.id === id)!
  )
  showMoveModal.value = true
}

async function handleBulkDelete() {
  try {
    await wishlistStore.bulkRemoveItems(
      currentWishlist.value!.id,
      selectedItems.value
    )
    notification.success(`${selectedItems.value.length} items removed`)
    clearSelection()
  } catch (error) {
    notification.error('Failed to remove items')
  }
}

function handleItemsMoved() {
  clearSelection()
  notification.success('Items moved successfully')
}

function handleWishlistCreated(wishlist: Wishlist) {
  selectedWishlistId.value = wishlist.id
  notification.success('Wishlist created successfully')
}

function handleWishlistShared() {
  notification.success('Wishlist shared successfully')
}

function clearSelection() {
  selectedItems.value = []
}

// Search debouncing
const debouncedSearch = useDebounce(searchQuery, 300)

watch(debouncedSearch, () => {
  currentPage.value = 1
})

// Initialize
onMounted(() => {
  loadWishlists()
})
</script>

<style scoped>
.wishlist-manager {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

.wishlist-manager__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.wishlist-manager__title {
  font-size: 2rem;
  font-weight: 600;
  color: var(--text-primary);
}

.wishlist-manager__actions {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.wishlist-manager__loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  gap: 1rem;
  color: var(--text-secondary);
}

.wishlist-manager__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 400px;
}

.wishlist-manager__content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.wishlist-manager__controls {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  align-items: center;
}

.wishlist-manager__controls > * {
  flex: 1;
  min-width: 200px;
}

@media (max-width: 768px) {
  .wishlist-manager {
    padding: 1rem;
  }
  
  .wishlist-manager__header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .wishlist-manager__controls {
    flex-direction: column;
  }
  
  .wishlist-manager__controls > * {
    width: 100%;
  }
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
```

### WishlistItem Component

```vue
<template>
  <div
      :class="[
      'wishlist-item',
      {
        'wishlist-item--selected': isSelected,
        'wishlist-item--unavailable': !item.product.available,
        'wishlist-item--has-alert': item.priceAlertActive
      }
    ]"
      @click="handleClick"
  >
    <!-- Selection Checkbox -->
    <div v-if="selectable" class="wishlist-item__select">
      <base-checkbox
          v-model="isSelected"
          :aria-label="`Select ${item.product.name}`"
          @click.stop
      />
    </div>

    <!-- Product Image -->
    <div class="wishlist-item__image">
      <img
          :src="item.product.cover?.url || '/placeholder.jpg'"
          :alt="item.product.name"
          loading="lazy"
      >

      <div v-if="!item.product.available" class="wishlist-item__badge">
        Out of Stock
      </div>

      <div v-if="item.priority" class="wishlist-item__priority">
        <icon-star /> {{ item.priority }}
      </div>
    </div>

    <!-- Product Info -->
    <div class="wishlist-item__info">
      <h3 class="wishlist-item__name">
        {{ item.product.name }}
      </h3>

      <p class="wishlist-item__number">
        {{ item.product.productNumber }}
      </p>

      <div v-if="item.note" class="wishlist-item__note">
        <icon-note />
        {{ item.note }}
      </div>

      <div class="wishlist-item__meta">
        <span class="wishlist-item__added">
          Added {{ formatDate(item.addedAt) }}
        </span>

        <span v-if="item.priceAlertActive" class="wishlist-item__alert">
          <icon-bell />
          Alert at {{ formatPrice(item.priceAlertThreshold) }}
        </span>
      </div>
    </div>

    <!-- Price Info -->
    <div class="wishlist-item__price">
      <price-display
          :price="item.product.price"
          :show-discount="true"
      />

      <quantity-selector
          v-if="showQuantity"
          v-model="quantity"
          :min="1"
          :max="item.product.stock"
          @change="handleQuantityChange"
      />
    </div>

    <!-- Actions -->
    <div class="wishlist-item__actions">
      <base-dropdown>
        <template #trigger>
          <base-button variant="ghost" size="sm">
            <icon-more-vertical />
          </base-button>
        </template>

        <base-dropdown-item @click="handleEdit">
          <icon-edit /> Edit
        </base-dropdown-item>

        <base-dropdown-item @click="handleMove">
          <icon-move /> Move to...
        </base-dropdown-item>

        <base-dropdown-item @click="handleSetAlert">
          <icon-bell /> Set Price Alert
        </base-dropdown-item>

        <base-dropdown-divider />

        <base-dropdown-item variant="danger" @click="handleRemove">
          <icon-trash /> Remove
        </base-dropdown-item>
      </base-dropdown>

      <base-button
          variant="primary"
          size="sm"
          :disabled="!item.product.available"
          @click.stop="handleAddToCart"
      >
        <icon-shopping-cart />
        Add to Cart
      </base-button>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { ref, computed } from 'vue'
  import { formatDistanceToNow } from 'date-fns'
  import type { WishlistItem } from '@/types/wishlist'

  interface Props {
    item: WishlistItem
    selectable?: boolean
    selected?: boolean
    showQuantity?: boolean
  }

  const props = withDefaults(defineProps<Props>(), {
    selectable: false,
    selected: false,
    showQuantity: true
  })

  const emit = defineEmits<{
    click: [item: WishlistItem]
    select: [selected: boolean]
    remove: [item: WishlistItem]
    update: [item: WishlistItem, updates: Partial<WishlistItem>]
    move: [item: WishlistItem]
    'add-to-cart': [item: WishlistItem, quantity: number]
  }>()

  const quantity = ref(props.item.quantity || 1)

  const isSelected = computed({
    get: () => props.selected,
    set: (value) => emit('select', value)
  })

  function formatDate(date: string) {
    return formatDistanceToNow(new Date(date), { addSuffix: true })
  }

  function formatPrice(price: number) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(price)
  }

  function handleClick() {
    emit('click', props.item)
  }

  function handleQuantityChange(newQuantity: number) {
    quantity.value = newQuantity
    emit('update', props.item, { quantity: newQuantity })
  }

  function handleEdit() {
    // Open edit modal
  }

  function handleMove() {
    emit('move', props.item)
  }

  function handleSetAlert() {
    // Open price alert modal
  }

  function handleRemove() {
    emit('remove', props.item)
  }

  function handleAddToCart() {
    emit('add-to-cart', props.item, quantity.value)
  }
</script>

<style scoped>
  .wishlist-item {
    display: grid;
    grid-template-columns: auto 120px 1fr auto auto;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    transition: all 0.2s ease;
    cursor: pointer;
  }

  .wishlist-item:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
  }

  .wishlist-item--selected {
    background: var(--selection-bg);
    border-color: var(--primary-color);
  }

  .wishlist-item--unavailable {
    opacity: 0.7;
  }

  .wishlist-item__select {
    display: flex;
    align-items: center;
  }

  .wishlist-item__image {
    position: relative;
    width: 120px;
    height: 120px;
    border-radius: 4px;
    overflow: hidden;
  }

  .wishlist-item__image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .wishlist-item__badge {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    padding: 0.25rem 0.5rem;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    font-size: 0.75rem;
    border-radius: 4px;
  }

  .wishlist-item__priority {
    position: absolute;
    bottom: 0.5rem;
    right: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    background: var(--warning-bg);
    color: var(--warning-color);
    font-size: 0.75rem;
    border-radius: 4px;
  }

  .wishlist-item__info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .wishlist-item__name {
    font-size: 1.125rem;
    font-weight: 500;
    color: var(--text-primary);
    margin: 0;
  }

  .wishlist-item__number {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 0;
  }

  .wishlist-item__note {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-style: italic;
  }

  .wishlist-item__meta {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: var(--text-muted);
  }

  .wishlist-item__alert {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--primary-color);
  }

  .wishlist-item__price {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
  }

  .wishlist-item__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  /* Responsive */
  @media (max-width: 768px) {
    .wishlist-item {
      grid-template-columns: 80px 1fr;
      gap: 0.75rem;
    }

    .wishlist-item__select {
      grid-column: 1 / -1;
    }

    .wishlist-item__image {
      width: 80px;
      height: 80px;
    }

    .wishlist-item__info {
      grid-column: 2;
    }

    .wishlist-item__price {
      grid-column: 2;
      align-items: flex-start;
    }

    .wishlist-item__actions {
      grid-column: 1 / -1;
      justify-content: space-between;
    }
  }
</style>
```

## Composables

### useWishlist Composable

```typescript
// composables/useWishlist.ts
import { ref, computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useWishlistStore } from '@/stores/wishlist'
import { useAuthStore } from '@/stores/auth'
import { useNotification } from './useNotification'
import type { Wishlist, WishlistItem } from '@/types/wishlist'

export function useWishlist(wishlistId?: Ref<string>) {
    const wishlistStore = useWishlistStore()
    const authStore = useAuthStore()
    const notification = useNotification()

    const { wishlists, loading, error } = storeToRefs(wishlistStore)
    const { isLoggedIn } = storeToRefs(authStore)

    // Local state
    const isProcessing = ref(false)

    // Current wishlist
    const currentWishlist = computed(() => {
        if (!wishlistId?.value) return null
        return wishlists.value.find(w => w.id === wishlistId.value)
    })

    // Check if product is in any wishlist
    const isInWishlist = (productId: string) => {
        return wishlistStore.hasProduct(productId)
    }

    // Get product's wishlist
    const getProductWishlist = (productId: string) => {
        return wishlistStore.getProductWishlist(productId)
    }

    // Add item to wishlist
    async function addItem(
        productId: string,
        wishlistIdParam?: string,
        options: Partial<WishlistItem> = {}
    ) {
        if (!isLoggedIn.value) {
            notification.warning('Please sign in to add items to your wishlist')
            return false
        }

        isProcessing.value = true

        try {
            const targetWishlistId = wishlistIdParam ||
                wishlistStore.defaultWishlist?.id ||
                wishlists.value[0]?.id

            if (!targetWishlistId) {
                throw new Error('No wishlist available')
            }

            await wishlistStore.addItem(targetWishlistId, productId, options)

            const wishlistName = wishlists.value.find(w => w.id === targetWishlistId)?.name
            notification.success(`Added to ${wishlistName || 'wishlist'}`)

            return true
        } catch (error) {
            notification.error('Failed to add item to wishlist')
            return false
        } finally {
            isProcessing.value = false
        }
    }

    // Remove item from wishlist
    async function removeItem(itemId: string, wishlistIdParam?: string) {
        if (!isLoggedIn.value) return false

        isProcessing.value = true

        try {
            const targetWishlistId = wishlistIdParam || currentWishlist.value?.id

            if (!targetWishlistId) {
                throw new Error('No wishlist specified')
            }

            await wishlistStore.removeItem(targetWishlistId, itemId)
            notification.info('Item removed from wishlist')

            return true
        } catch (error) {
            notification.error('Failed to remove item')
            return false
        } finally {
            isProcessing.value = false
        }
    }

    // Toggle product in wishlist
    async function toggleProduct(productId: string, options?: Partial<WishlistItem>) {
        const productWishlist = getProductWishlist(productId)

        if (productWishlist) {
            const item = productWishlist.items.find(i => i.productId === productId)
            if (item) {
                return await removeItem(item.id, productWishlist.id)
            }
        } else {
            return await addItem(productId, undefined, options)
        }
    }

    // Move item between wishlists
    async function moveItem(
        itemId: string,
        sourceWishlistId: string,
        targetWishlistId: string
    ) {
        isProcessing.value = true

        try {
            await wishlistStore.moveItem(itemId, sourceWishlistId, targetWishlistId)
            notification.success('Item moved successfully')
            return true
        } catch (error) {
            notification.error('Failed to move item')
            return false
        } finally {
            isProcessing.value = false
        }
    }

    // Create new wishlist
    async function createWishlist(data: Partial<Wishlist>) {
        isProcessing.value = true

        try {
            const wishlist = await wishlistStore.createWishlist(data)
            notification.success('Wishlist created successfully')
            return wishlist
        } catch (error) {
            notification.error('Failed to create wishlist')
            return null
        } finally {
            isProcessing.value = false
        }
    }

    // Update wishlist
    async function updateWishlist(wishlistIdParam: string, data: Partial<Wishlist>) {
        isProcessing.value = true

        try {
            await wishlistStore.updateWishlist(wishlistIdParam, data)
            notification.success('Wishlist updated successfully')
            return true
        } catch (error) {
            notification.error('Failed to update wishlist')
            return false
        } finally {
            isProcessing.value = false
        }
    }

    // Delete wishlist
    async function deleteWishlist(wishlistIdParam: string) {
        isProcessing.value = true

        try {
            await wishlistStore.deleteWishlist(wishlistIdParam)
            notification.success('Wishlist deleted successfully')
            return true
        } catch (error) {
            notification.error('Failed to delete wishlist')
            return false
        } finally {
            isProcessing.value = false
        }
    }

    // Share wishlist
    async function shareWishlist(wishlistIdParam: string, options: ShareOptions) {
        isProcessing.value = true

        try {
            const shareInfo = await wishlistStore.shareWishlist(wishlistIdParam, options)
            notification.success('Wishlist shared successfully')
            return shareInfo
        } catch (error) {
            notification.error('Failed to share wishlist')
            return null
        } finally {
            isProcessing.value = false
        }
    }

    return {
        // State
        wishlists,
        currentWishlist,
        loading,
        error,
        isProcessing,

        // Getters
        isInWishlist,
        getProductWishlist,

        // Actions
        addItem,
        removeItem,
        toggleProduct,
        moveItem,
        createWishlist,
        updateWishlist,
        deleteWishlist,
        shareWishlist
    }
}
```

## Styling System

### Design Tokens

```scss
// tokens.scss
:root {
  // Colors
  --wishlist-primary: #e91e63;
  --wishlist-primary-dark: #c2185b;
  --wishlist-primary-light: #f8bbd0;
  --wishlist-accent: #ff4081;

  // Spacing
  --wishlist-spacing-xs: 0.25rem;
  --wishlist-spacing-sm: 0.5rem;
  --wishlist-spacing-md: 1rem;
  --wishlist-spacing-lg: 1.5rem;
  --wishlist-spacing-xl: 2rem;

  // Typography
  --wishlist-font-size-sm: 0.875rem;
  --wishlist-font-size-base: 1rem;
  --wishlist-font-size-lg: 1.125rem;
  --wishlist-font-size-xl: 1.5rem;

  // Borders
  --wishlist-border-radius: 4px;
  --wishlist-border-color: #e0e0e0;

  // Shadows
  --wishlist-shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
  --wishlist-shadow-md: 0 4px 6px rgba(0,0,0,0.1);
  --wishlist-shadow-lg: 0 10px 15px rgba(0,0,0,0.1);

  // Transitions
  --wishlist-transition-fast: 150ms ease;
  --wishlist-transition-base: 200ms ease;
  --wishlist-transition-slow: 300ms ease;

  // Z-index
  --wishlist-z-dropdown: 1000;
  --wishlist-z-modal: 1100;
  --wishlist-z-notification: 1200;
}

// Dark mode
@media (prefers-color-scheme: dark) {
  :root {
    --wishlist-primary: #f48fb1;
    --wishlist-border-color: #424242;
  }
}
```

### Component Mixins

```scss
// mixins.scss
@mixin wishlist-button-base {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: var(--wishlist-spacing-sm) var(--wishlist-spacing-md);
  font-size: var(--wishlist-font-size-base);
  font-weight: 500;
  border-radius: var(--wishlist-border-radius);
  transition: all var(--wishlist-transition-base);
  cursor: pointer;

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  &:focus-visible {
    outline: 2px solid var(--wishlist-primary);
    outline-offset: 2px;
  }
}

@mixin wishlist-card {
  background: white;
  border: 1px solid var(--wishlist-border-color);
  border-radius: var(--wishlist-border-radius);
  padding: var(--wishlist-spacing-md);
  transition: box-shadow var(--wishlist-transition-base);

  &:hover {
    box-shadow: var(--wishlist-shadow-md);
  }
}

@mixin wishlist-grid($min-width: 250px, $gap: 1rem) {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax($min-width, 1fr));
  gap: $gap;
}
```

## Testing

### Component Tests

```typescript
// WishlistButton.spec.ts
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import WishlistButton from '@/components/wishlist/WishlistButton.vue'
import { useWishlistStore } from '@/stores/wishlist'
import { useAuthStore } from '@/stores/auth'

describe('WishlistButton', () => {
    let wrapper: any
    let wishlistStore: any
    let authStore: any

    beforeEach(() => {
        const pinia = createPinia()

        wrapper = mount(WishlistButton, {
            props: {
                productId: 'test-product-123',
                productName: 'Test Product',
                productPrice: 99.99
            },
            global: {
                plugins: [pinia]
            }
        })

        wishlistStore = useWishlistStore()
        authStore = useAuthStore()
    })

    it('renders correctly', () => {
        expect(wrapper.find('.wishlist-button').exists()).toBe(true)
        expect(wrapper.find('.wishlist-button__icon').exists()).toBe(true)
    })

    it('shows correct state when product is not in wishlist', () => {
        wishlistStore.hasProduct = vi.fn().mockReturnValue(false)

        expect(wrapper.classes()).not.toContain('wishlist-button--active')
    })

    it('shows correct state when product is in wishlist', async () => {
        wishlistStore.hasProduct = vi.fn().mockReturnValue(true)
        await wrapper.vm.$nextTick()

        expect(wrapper.classes()).toContain('wishlist-button--active')
    })

    it('handles click when logged in', async () => {
        authStore.isLoggedIn = true
        wishlistStore.hasProduct = vi.fn().mockReturnValue(false)
        wishlistStore.addItem = vi.fn().mockResolvedValue(true)

        await wrapper.trigger('click')

        expect(wishlistStore.addItem).toHaveBeenCalledWith(
            expect.any(String),
            'test-product-123',
            expect.objectContaining({
                productName: 'Test Product',
                productPrice: 99.99
            })
        )
    })

    it('handles guest click when not logged in', async () => {
        authStore.isLoggedIn = false
        wishlistStore.toggleGuestWishlist = vi.fn().mockReturnValue(true)

        await wrapper.trigger('click')

        expect(wishlistStore.toggleGuestWishlist).toHaveBeenCalledWith('test-product-123')
    })

    it('shows loading state during operation', async () => {
        authStore.isLoggedIn = true
        wishlistStore.addItem = vi.fn().mockImplementation(() => {
            return new Promise(resolve => setTimeout(resolve, 100))
        })

        const clickPromise = wrapper.trigger('click')
        await wrapper.vm.$nextTick()

        expect(wrapper.classes()).toContain('wishlist-button--loading')
        expect(wrapper.attributes('disabled')).toBeDefined()

        await clickPromise

        expect(wrapper.classes()).not.toContain('wishlist-button--loading')
    })

    it('emits correct events', async () => {
        authStore.isLoggedIn = true
        wishlistStore.hasProduct = vi.fn().mockReturnValue(false)
        wishlistStore.addItem = vi.fn().mockResolvedValue(true)
        wishlistStore.defaultWishlist = { id: 'wishlist-123', name: 'My Wishlist' }

        await wrapper.trigger('click')

        expect(wrapper.emitted('added')).toBeTruthy()
        expect(wrapper.emitted('added')[0]).toEqual(['wishlist-123'])
        expect(wrapper.emitted('toggle')).toBeTruthy()
        expect(wrapper.emitted('toggle')[0]).toEqual([true])
    })

    it('supports keyboard navigation', async () => {
        await wrapper.trigger('keydown', { key: 'Enter' })
        expect(wishlistStore.addItem).toHaveBeenCalled()

        await wrapper.trigger('keydown', { key: ' ' })
        expect(wishlistStore.addItem).toHaveBeenCalledTimes(2)
    })
})
```

## Performance Optimization

### Lazy Loading

```typescript
// Lazy load heavy components
const WishlistAnalytics = defineAsyncComponent(() =>
    import('./analytics/WishlistAnalytics.vue')
)

const ShareWishlistModal = defineAsyncComponent(() =>
    import('./modals/ShareWishlistModal.vue')
)
```

### Virtual Scrolling

```vue
<!-- For large lists -->
<virtual-list
    :items="wishlistItems"
    :item-height="120"
    :buffer="5"
>
  <template #default="{ item }">
    <wishlist-item :item="item" />
  </template>
</virtual-list>
```

### Image Optimization

```vue
<picture>
  <source
      :srcset="`${item.product.cover.url}?w=240&format=webp`"
      type="image/webp"
  >
  <img
      :src="`${item.product.cover.url}?w=240`"
      :alt="item.product.name"
      loading="lazy"
      decoding="async"
  >
</picture>
```