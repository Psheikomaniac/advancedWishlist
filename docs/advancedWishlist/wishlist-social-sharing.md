# Social Sharing Feature

## Overview

The Social Sharing Feature allows customers to share their wishlists with friends, family or publicly. This promotes viral spread and increases conversion rate through social buying impulses.

## User Stories

### As a wishlist owner, I want to...
1. **Generate links** to share my wishlist
2. **Control privacy** with various visibility options
3. **Password protection** for privately shared lists
4. **Set expiry dates** for temporary shares
5. **View statistics** about views and interactions

### As a wishlist viewer, I want to...
1. **View lists** without an account
2. **Purchase products** directly from the shared list
3. **Get notifications** about changes
4. **Leave comments** (optional)

## Technical Implementation

### ShareService

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\DTO\Request\ShareWishlistRequest;
use AdvancedWishlist\Core\DTO\Response\ShareInfoResponse;
use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare\WishlistShareEntity;
use AdvancedWishlist\Core\Event\WishlistSharedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class WishlistShareService
{
    private const TOKEN_LENGTH = 32;
    private const DEFAULT_EXPIRY_DAYS = 90;
    private const MAX_PASSWORD_ATTEMPTS = 5;
    
    public function __construct(
        private EntityRepository $wishlistRepository,
        private EntityRepository $shareRepository,
        private WishlistValidator $validator,
        private ShareTokenGenerator $tokenGenerator,
        private ShareNotificationService $notificationService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private string $appUrl
    ) {}
    
    /**
     * Create a share link for wishlist
     */
    public function createShare(
        ShareWishlistRequest $request,
        Context $context
    ): ShareInfoResponse {
        // 1. Load and validate wishlist
        $wishlist = $this->loadWishlist($request->getWishlistId(), $context);
        $this->validator->validateOwnership($wishlist, $context);
        
        // 2. Check if share already exists
        $existingShare = $this->findActiveShare($wishlist->getId());
        if ($existingShare && !$request->isForceNew()) {
            return $this->updateShare($existingShare, $request, $context);
        }
        
        // 3. Generate share token
        $token = $this->tokenGenerator->generateToken(self::TOKEN_LENGTH);
        
        // 4. Prepare share data
        $shareId = Uuid::randomHex();
        $shareData = [
            'id' => $shareId,
            'wishlistId' => $wishlist->getId(),
            'token' => $token,
            'type' => $request->getShareMethod(),
            'active' => true,
            'expiresAt' => $this->calculateExpiryDate($request->getExpiresAt()),
            'password' => $request->getPassword() ? 
                password_hash($request->getPassword(), PASSWORD_BCRYPT) : null,
            'settings' => $request->getShareSettings()->toArray(),
            'views' => 0,
            'uniqueViews' => 0,
            'createdBy' => $context->getSource()->getUserId(),
        ];
        
        // 5. Create share
        $this->shareRepository->create([$shareData], $context);
        
        // 6. Handle share method specific logic
        $this->handleShareMethod($request, $wishlist, $token, $context);
        
        // 7. Dispatch event
        $event = new WishlistSharedEvent($wishlist, $shareId, $request->getShareMethod(), $context);
        $this->eventDispatcher->dispatch($event);
        
        // 8. Log
        $this->logger->info('Wishlist shared', [
            'wishlistId' => $wishlist->getId(),
            'shareId' => $shareId,
            'method' => $request->getShareMethod(),
        ]);
        
        // 9. Load and return
        $share = $this->loadShare($shareId, $context);
        return ShareInfoResponse::fromEntity($share);
    }
    
    /**
     * Handle different share methods
     */
    private function handleShareMethod(
        ShareWishlistRequest $request,
        WishlistEntity $wishlist,
        string $token,
        Context $context
    ): void {
        switch ($request->getShareMethod()) {
            case 'email':
                $this->handleEmailShare($request, $wishlist, $token, $context);
                break;
                
            case 'social':
                $this->handleSocialShare($request, $wishlist, $token, $context);
                break;
                
            case 'link':
            default:
                // Just generate link, no additional action needed
                break;
        }
    }
    
    /**
     * Handle email sharing
     */
    private function handleEmailShare(
        ShareWishlistRequest $request,
        WishlistEntity $wishlist,
        string $token,
        Context $context
    ): void {
        if (!$request->getRecipientEmail()) {
            throw new \InvalidArgumentException('Recipient email is required for email sharing');
        }
        
        $shareUrl = $this->generateShareUrl($token);
        
        $this->notificationService->sendShareEmail(
            $request->getRecipientEmail(),
            $wishlist,
            $shareUrl,
            $request->getMessage(),
            $context
        );
    }
    
    /**
     * Handle social platform sharing
     */
    private function handleSocialShare(
        ShareWishlistRequest $request,
        WishlistEntity $wishlist,
        string $token,
        Context $context
    ): void {
        $platform = $request->getPlatform();
        $shareUrl = $this->generateShareUrl($token);
        
        // Generate platform-specific share data
        $socialData = match($platform) {
            'facebook' => [
                'url' => $shareUrl,
                'title' => sprintf('%s - Wishlist', $wishlist->getName()),
                'description' => $wishlist->getDescription() ?? 'Check out my wishlist!',
            ],
            'whatsapp' => [
                'text' => sprintf(
                    "Check out my wishlist '%s': %s",
                    $wishlist->getName(),
                    $shareUrl
                ),
            ],
            'twitter' => [
                'text' => sprintf('My wishlist: %s', $wishlist->getName()),
                'url' => $shareUrl,
                'hashtags' => 'wishlist,shopping',
            ],
            default => throw new \InvalidArgumentException('Unsupported platform: ' . $platform)
        };
        
        // Store social share data for analytics
        $this->storeSocialShareData($platform, $socialData, $context);
    }
    
    /**
     * Access shared wishlist
     */
    public function accessSharedWishlist(
        string $token,
        ?string $password,
        Context $context
    ): WishlistEntity {
        // 1. Find share by token
        $share = $this->findShareByToken($token);
        
        if (!$share) {
            throw new ShareNotFoundException('Invalid share token');
        }
        
        // 2. Check if active
        if (!$share->isActive()) {
            throw new ShareExpiredException('This share link is no longer active');
        }
        
        // 3. Check expiry
        if ($share->getExpiresAt() && $share->getExpiresAt() < new \DateTime()) {
            $this->deactivateShare($share->getId(), $context);
            throw new ShareExpiredException('This share link has expired');
        }
        
        // 4. Check password
        if ($share->getPassword()) {
            $this->validateSharePassword($share, $password);
        }
        
        // 5. Track view
        $this->trackShareView($share, $context);
        
        // 6. Load wishlist with filtered data based on settings
        return $this->loadSharedWishlist($share, $context);
    }
    
    /**
     * Validate share password
     */
    private function validateSharePassword(
        WishlistShareEntity $share,
        ?string $password
    ): void {
        if (!$password) {
            throw new InvalidSharePasswordException('Password required');
        }
        
        // Check rate limiting
        $attempts = $this->getPasswordAttempts($share->getId());
        if ($attempts >= self::MAX_PASSWORD_ATTEMPTS) {
            throw new TooManyAttemptsException('Too many password attempts');
        }
        
        if (!password_verify($password, $share->getPassword())) {
            $this->incrementPasswordAttempts($share->getId());
            throw new InvalidSharePasswordException('Invalid password');
        }
        
        // Reset attempts on success
        $this->resetPasswordAttempts($share->getId());
    }
    
    /**
     * Track share view
     */
    private function trackShareView(
        WishlistShareEntity $share,
        Context $context
    ): void {
        // Update view count
        $this->shareRepository->update([
            [
                'id' => $share->getId(),
                'views' => $share->getViews() + 1,
                'lastViewedAt' => new \DateTime(),
            ]
        ], $context);
        
        // Track unique views
        $visitorId = $this->getVisitorId();
        if (!$this->hasVisitorViewed($share->getId(), $visitorId)) {
            $this->trackUniqueView($share->getId(), $visitorId, $context);
            
            $this->shareRepository->update([
                [
                    'id' => $share->getId(),
                    'uniqueViews' => $share->getUniqueViews() + 1,
                ]
            ], $context);
        }
        
        // Analytics event
        $this->trackAnalyticsEvent('share_view', [
            'shareId' => $share->getId(),
            'wishlistId' => $share->getWishlistId(),
            'shareType' => $share->getType(),
        ]);
    }
    
    /**
     * Generate share URL
     */
    private function generateShareUrl(string $token): string
    {
        return sprintf('%s/wishlist/shared/%s', $this->appUrl, $token);
    }
    
    /**
     * Generate short URL (optional URL shortener integration)
     */
    public function generateShortUrl(string $token): string
    {
        // Could integrate with bit.ly, custom shortener, etc.
        return sprintf('%s/w/%s', $this->appUrl, substr($token, 0, 8));
    }
    
    /**
     * Revoke share access
     */
    public function revokeShare(
        string $shareId,
        Context $context
    ): void {
        $share = $this->loadShare($shareId, $context);
        
        // Validate ownership through wishlist
        $wishlist = $this->loadWishlist($share->getWishlistId(), $context);
        $this->validator->validateOwnership($wishlist, $context);
        
        // Deactivate
        $this->shareRepository->update([
            [
                'id' => $shareId,
                'active' => false,
                'revokedAt' => new \DateTime(),
            ]
        ], $context);
        
        $this->logger->info('Share revoked', [
            'shareId' => $shareId,
            'wishlistId' => $share->getWishlistId(),
        ]);
    }
}
```

### Share Token Generator

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

class ShareTokenGenerator
{
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    
    /**
     * Generate cryptographically secure token
     */
    public function generateToken(int $length = 32): string
    {
        $token = '';
        $alphabetLength = strlen(self::ALPHABET);
        
        for ($i = 0; $i < $length; $i++) {
            $randomIndex = random_int(0, $alphabetLength - 1);
            $token .= self::ALPHABET[$randomIndex];
        }
        
        return $token;
    }
    
    /**
     * Generate QR code for share URL
     */
    public function generateQrCode(string $url): string
    {
        // Using endroid/qr-code or similar library
        $qrCode = QrCode::create($url)
            ->setSize(300)
            ->setMargin(10)
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255));
            
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        
        return $result->getDataUri();
    }
}
```

### Social Share Components

```vue
<template>
  <div class="share-wishlist-modal">
    <h2>Share wishlist</h2>

    <!-- Share Methods -->
    <div class="share-methods">
      <button
          v-for="method in shareMethods"
          :key="method.id"
          @click="selectMethod(method)"
          :class="{ active: selectedMethod === method.id }"
          class="share-method"
      >
        <i :class="method.icon"></i>
        {{ method.label }}
      </button>
    </div>

    <!-- Link Sharing -->
    <div v-if="selectedMethod === 'link'" class="share-link">
      <div class="share-options">
        <label>
          <input type="checkbox" v-model="shareSettings.passwordProtected">
          Password protection
        </label>

        <div v-if="shareSettings.passwordProtected" class="password-input">
          <input
              type="password"
              v-model="shareSettings.password"
              placeholder="Enter password"
          >
        </div>

        <label>
          <input type="checkbox" v-model="shareSettings.hasExpiry">
          Set expiry date
        </label>

        <div v-if="shareSettings.hasExpiry" class="expiry-input">
          <input
              type="date"
              v-model="shareSettings.expiryDate"
              :min="minDate"
          >
        </div>

        <label>
          <input type="checkbox" v-model="shareSettings.hidePrice">
          Hide prices
        </label>
      </div>

      <button @click="generateLink" class="btn-primary">
        Generate link
      </button>

      <div v-if="shareLink" class="share-result">
        <div class="link-display">
          <input
              type="text"
              :value="shareLink"
              readonly
              ref="linkInput"
          >
          <button @click="copyLink" class="btn-copy">
            <i class="icon-copy"></i>
          </button>
        </div>

        <div class="qr-code" v-if="qrCode">
          <img :src="qrCode" alt="QR Code">
          <button @click="downloadQr" class="btn-download">
            Download QR code
          </button>
        </div>

        <div class="share-stats" v-if="shareInfo">
          <p>Views: {{ shareInfo.views }}</p>
          <p>Unique visitors: {{ shareInfo.uniqueViews }}</p>
        </div>
      </div>
    </div>

    <!-- Email Sharing -->
    <div v-if="selectedMethod === 'email'" class="share-email">
      <form @submit.prevent="shareViaEmail">
        <div class="form-group">
          <label>Recipient email:</label>
          <input
              type="email"
              v-model="emailData.recipient"
              required
          >
        </div>

        <div class="form-group">
          <label>Message (optional):</label>
          <textarea
              v-model="emailData.message"
              rows="4"
              placeholder="Add a personal message..."
          ></textarea>
        </div>

        <button type="submit" class="btn-primary">
          Send email
        </button>
      </form>
    </div>

    <!-- Social Sharing -->
    <div v-if="selectedMethod === 'social'" class="share-social">
      <div class="social-platforms">
        <button
            v-for="platform in socialPlatforms"
            :key="platform.id"
            @click="shareOnPlatform(platform)"
            class="social-button"
            :style="{ backgroundColor: platform.color }"
        >
          <i :class="platform.icon"></i>
          {{ platform.name }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
  import { ref, computed } from 'vue'
  import { useWishlistStore } from '@/stores/wishlist'
  import { useShareService } from '@/services/shareService'
  import { useNotification } from '@/composables/useNotification'

  const props = defineProps({
    wishlistId: {
      type: String,
      required: true
    }
  })

  const wishlistStore = useWishlistStore()
  const shareService = useShareService()
  const notification = useNotification()

  const selectedMethod = ref('link')
  const shareLink = ref('')
  const qrCode = ref('')
  const shareInfo = ref(null)

  const shareSettings = ref({
    passwordProtected: false,
    password: '',
    hasExpiry: false,
    expiryDate: '',
    hidePrice: false,
    allowGuestPurchase: true
  })

  const emailData = ref({
    recipient: '',
    message: ''
  })

  const shareMethods = [
    { id: 'link', label: 'Link', icon: 'icon-link' },
    { id: 'email', label: 'Email', icon: 'icon-email' },
    { id: 'social', label: 'Social Media', icon: 'icon-share' }
  ]

  const socialPlatforms = [
    {
      id: 'facebook',
      name: 'Facebook',
      icon: 'icon-facebook',
      color: '#1877f2'
    },
    {
      id: 'whatsapp',
      name: 'WhatsApp',
      icon: 'icon-whatsapp',
      color: '#25d366'
    },
    {
      id: 'twitter',
      name: 'Twitter',
      icon: 'icon-twitter',
      color: '#1da1f2'
    },
    {
      id: 'pinterest',
      name: 'Pinterest',
      icon: 'icon-pinterest',
      color: '#bd081c'
    }
  ]

  const minDate = computed(() => {
    const tomorrow = new Date()
    tomorrow.setDate(tomorrow.getDate() + 1)
    return tomorrow.toISOString().split('T')[0]
  })

  async function generateLink() {
    try {
      const response = await shareService.createShare({
        wishlistId: props.wishlistId,
        shareMethod: 'link',
        shareSettings: {
          password: shareSettings.value.passwordProtected ?
              shareSettings.value.password : null,
          expiresAt: shareSettings.value.hasExpiry ?
              shareSettings.value.expiryDate : null,
          hidePrices: shareSettings.value.hidePrice,
          allowGuestPurchase: shareSettings.value.allowGuestPurchase
        }
      })

      shareLink.value = response.url
      qrCode.value = response.qrCode
      shareInfo.value = response

      notification.success('Share link created!')
    } catch (error) {
      notification.error('Error creating link')
    }
  }

  async function copyLink() {
    try {
      await navigator.clipboard.writeText(shareLink.value)
      notification.success('Link copied!')
    } catch (error) {
      // Fallback
      this.$refs.linkInput.select()
      document.execCommand('copy')
      notification.success('Link copied!')
    }
  }

  async function shareViaEmail() {
    try {
      await shareService.createShare({
        wishlistId: props.wishlistId,
        shareMethod: 'email',
        recipientEmail: emailData.value.recipient,
        message: emailData.value.message
      })

      notification.success('Email sent!')
      emailData.value = { recipient: '', message: '' }
    } catch (error) {
      notification.error('Error sending email')
    }
  }

  async function shareOnPlatform(platform) {
    try {
      const response = await shareService.createShare({
        wishlistId: props.wishlistId,
        shareMethod: 'social',
        platform: platform.id
      })

      // Open platform share dialog
      const shareUrl = buildPlatformShareUrl(platform, response)
      window.open(shareUrl, '_blank', 'width=600,height=400')

    } catch (error) {
      notification.error('Error sharing')
    }
  }

  function buildPlatformShareUrl(platform, shareData) {
    const encodedUrl = encodeURIComponent(shareData.url)
    const encodedText = encodeURIComponent(shareData.text || '')

    switch (platform.id) {
      case 'facebook':
        return `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`

      case 'whatsapp':
        return `https://wa.me/?text=${encodedText}%20${encodedUrl}`

      case 'twitter':
        return `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedText}`

      case 'pinterest':
        const media = encodeURIComponent(shareData.image || '')
        return `https://pinterest.com/pin/create/button/?url=${encodedUrl}&media=${media}&description=${encodedText}`

      default:
        return shareData.url
    }
  }

  function downloadQr() {
    const link = document.createElement('a')
    link.download = 'wishlist-qr-code.png'
    link.href = qrCode.value
    link.click()
  }
</script>

<style scoped>
  .share-methods {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .share-method {
    flex: 1;
    padding: 1rem;
    border: 2px solid #e0e0e0;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
  }

  .share-method.active {
    border-color: var(--primary-color);
    background: var(--primary-light);
  }

  .share-options {
    margin-bottom: 1.5rem;
  }

  .share-options label {
    display: block;
    margin-bottom: 0.5rem;
  }

  .link-display {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
  }

  .link-display input {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
  }

  .qr-code {
    text-align: center;
    margin: 1rem 0;
  }

  .qr-code img {
    max-width: 200px;
    margin-bottom: 1rem;
  }

  .social-platforms {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
  }

  .social-button {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: opacity 0.2s;
  }

  .social-button:hover {
    opacity: 0.9;
  }
</style>
```

### Share Analytics

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\Repository\ShareAnalyticsRepository;
use Shopware\Core\Framework\Context;

class ShareAnalyticsService
{
    public function __construct(
        private ShareAnalyticsRepository $analyticsRepository
    ) {}
    
    /**
     * Get share performance metrics
     */
    public function getShareMetrics(
        string $wishlistId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        Context $context
    ): array {
        return [
            'totalShares' => $this->getTotalShares($wishlistId, $startDate, $endDate),
            'sharesByMethod' => $this->getSharesByMethod($wishlistId, $startDate, $endDate),
            'viewsOverTime' => $this->getViewsOverTime($wishlistId, $startDate, $endDate),
            'conversionRate' => $this->getShareConversionRate($wishlistId, $startDate, $endDate),
            'topReferrers' => $this->getTopReferrers($wishlistId, $startDate, $endDate),
            'socialEngagement' => $this->getSocialEngagement($wishlistId, $startDate, $endDate),
        ];
    }
    
    /**
     * Track social share engagement
     */
    public function trackSocialEngagement(
        string $shareId,
        string $platform,
        array $metrics,
        Context $context
    ): void {
        $data = [
            'shareId' => $shareId,
            'platform' => $platform,
            'clicks' => $metrics['clicks'] ?? 0,
            'likes' => $metrics['likes'] ?? 0,
            'shares' => $metrics['shares'] ?? 0,
            'comments' => $metrics['comments'] ?? 0,
            'reach' => $metrics['reach'] ?? 0,
            'trackedAt' => new \DateTime(),
        ];
        
        $this->analyticsRepository->upsertSocialMetrics($data, $context);
    }
    
    /**
     * Calculate viral coefficient
     */
    public function calculateViralCoefficient(
        string $wishlistId,
        \DateTimeInterface $period,
        Context $context
    ): float {
        $originalShares = $this->getDirectShares($wishlistId, $period);
        $secondaryShares = $this->getSecondaryShares($wishlistId, $period);
        
        if ($originalShares === 0) {
            return 0.0;
        }
        
        return round($secondaryShares / $originalShares, 2);
    }
}
```

## Database Schema

```sql
-- Share tracking table
CREATE TABLE `wishlist_share` (
                                  `id` BINARY(16) NOT NULL,
                                  `wishlist_id` BINARY(16) NOT NULL,
                                  `token` VARCHAR(64) NOT NULL,
                                  `type` ENUM('link','email','social') NOT NULL,
                                  `active` TINYINT(1) DEFAULT 1,
                                  `password` VARCHAR(255),
                                  `expires_at` DATETIME(3),
                                  `settings` JSON,
                                  `views` INT DEFAULT 0,
                                  `unique_views` INT DEFAULT 0,
                                  `last_viewed_at` DATETIME(3),
                                  `created_by` BINARY(16),
                                  `created_at` DATETIME(3) NOT NULL,
                                  `revoked_at` DATETIME(3),
                                  PRIMARY KEY (`id`),
                                  UNIQUE KEY `uniq.wishlist_share.token` (`token`),
                                  KEY `idx.wishlist_share.wishlist` (`wishlist_id`),
                                  KEY `idx.wishlist_share.active` (`active`, `expires_at`),
                                  CONSTRAINT `fk.wishlist_share.wishlist` FOREIGN KEY (`wishlist_id`)
                                      REFERENCES `wishlist` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Share views tracking
CREATE TABLE `wishlist_share_view` (
                                       `id` BINARY(16) NOT NULL,
                                       `share_id` BINARY(16) NOT NULL,
                                       `visitor_id` VARCHAR(64) NOT NULL,
                                       `ip_address` VARCHAR(45),
                                       `user_agent` VARCHAR(500),
                                       `referrer` VARCHAR(500),
                                       `viewed_at` DATETIME(3) NOT NULL,
                                       PRIMARY KEY (`id`),
                                       UNIQUE KEY `uniq.share_view.visitor` (`share_id`, `visitor_id`),
                                       KEY `idx.share_view.share` (`share_id`),
                                       CONSTRAINT `fk.share_view.share` FOREIGN KEY (`share_id`)
                                           REFERENCES `wishlist_share` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Social share metrics
CREATE TABLE `wishlist_share_social` (
                                         `id` BINARY(16) NOT NULL,
                                         `share_id` BINARY(16) NOT NULL,
                                         `platform` VARCHAR(50) NOT NULL,
                                         `clicks` INT DEFAULT 0,
                                         `likes` INT DEFAULT 0,
                                         `shares` INT DEFAULT 0,
                                         `comments` INT DEFAULT 0,
                                         `reach` INT DEFAULT 0,
                                         `tracked_at` DATETIME(3) NOT NULL,
                                         PRIMARY KEY (`id`),
                                         KEY `idx.share_social.share_platform` (`share_id`, `platform`),
                                         CONSTRAINT `fk.share_social.share` FOREIGN KEY (`share_id`)
                                             REFERENCES `wishlist_share` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Security Considerations

### Access Control

```php
class ShareAccessControl
{
    /**
     * Validate share access
     */
    public function validateAccess(
        WishlistShareEntity $share,
        ?string $password,
        Context $context
    ): bool {
        // Check active status
        if (!$share->isActive()) {
            return false;
        }
        
        // Check expiry
        if ($share->getExpiresAt() && $share->getExpiresAt() < new \DateTime()) {
            return false;
        }
        
        // Check password
        if ($share->getPassword() && !$this->verifyPassword($password, $share->getPassword())) {
            return false;
        }
        
        // Check IP restrictions (if configured)
        if ($share->getSettings()['ipRestrictions'] ?? false) {
            return $this->checkIpRestrictions($share, $context);
        }
        
        return true;
    }
}
```

### Rate Limiting

```php
class ShareRateLimiter
{
    private const LIMITS = [
        'create_share' => ['limit' => 10, 'window' => 3600], // 10 per hour
        'access_share' => ['limit' => 100, 'window' => 3600], // 100 per hour
        'password_attempt' => ['limit' => 5, 'window' => 900], // 5 per 15 min
    ];
    
    public function checkLimit(string $action, string $identifier): bool
    {
        $limit = self::LIMITS[$action] ?? null;
        
        if (!$limit) {
            return true;
        }
        
        $key = sprintf('rate_limit:%s:%s', $action, $identifier);
        $current = $this->redis->incr($key);
        
        if ($current === 1) {
            $this->redis->expire($key, $limit['window']);
        }
        
        return $current <= $limit['limit'];
    }
}
```

## Performance Optimization

### Caching Strategy

```php
// Cache shared wishlist data
$cacheKey = sprintf('wishlist.share.%s', $token);
$ttl = 3600; // 1 hour

$cached = $this->cache->get($cacheKey);
if ($cached) {
    return $cached;
}

// Load and cache
$wishlist = $this->loadSharedWishlist($token);
$this->cache->set($cacheKey, $wishlist, $ttl);
```

### CDN Integration

```yaml
# Cloudflare Page Rules for shared wishlists
  /wishlist/shared/*
    - Cache Level: Cache Everything
  - Edge Cache TTL: 1 hour
  - Browser Cache TTL: 30 minutes
```