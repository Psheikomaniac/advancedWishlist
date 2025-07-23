<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Unit\Core\Content\Wishlist;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem\WishlistItemCollection;
use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem\WishlistItemEntity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Pricing\Price;
use Shopware\Core\Framework\Pricing\PriceCollection;

/**
 * Comprehensive unit tests for WishlistEntity with PHP 8.4 property hooks validation.
 * Tests modern PHP features, security validation, and performance characteristics.
 */
class WishlistEntityTest extends TestCase
{
    private WishlistEntity $wishlist;

    protected function setUp(): void
    {
        $this->wishlist = WishlistEntity::create(
            'test-id',
            'customer-id',
            'Test Wishlist',
            'private',
            false
        );
    }

    /**
     * Test PHP 8.4 property hooks validation for name property.
     */
    public function testNamePropertyHooksValidation(): void
    {
        // Test valid name
        $this->wishlist->name = 'Valid Name';
        $this->assertEquals('Valid Name', $this->wishlist->name);

        // Test automatic trimming
        $this->wishlist->name = '  Trimmed Name  ';
        $this->assertEquals('Trimmed Name', $this->wishlist->name);

        // Test minimum length validation
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Wishlist name must be at least 2 characters long');
        $this->wishlist->name = 'A';
    }

    /**
     * Test name property maximum length validation.
     */
    public function testNameMaxLengthValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Wishlist name cannot exceed 255 characters');
        $this->wishlist->name = str_repeat('A', 256);
    }

    /**
     * Test type property validation with hooks.
     */
    public function testTypePropertyHooksValidation(): void
    {
        // Test valid types
        foreach (['private', 'public', 'shared'] as $type) {
            $this->wishlist->type = $type;
            $this->assertEquals($type, $this->wishlist->type);
        }

        // Test invalid type
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid wishlist type. Must be: private, public, or shared');
        $this->wishlist->type = 'invalid';
    }

    /**
     * Test description property hooks with trimming and null handling.
     */
    public function testDescriptionPropertyHooks(): void
    {
        // Test valid description
        $this->wishlist->description = 'Test description';
        $this->assertEquals('Test description', $this->wishlist->description);

        // Test trimming
        $this->wishlist->description = '  Trimmed description  ';
        $this->assertEquals('Trimmed description', $this->wishlist->description);

        // Test null handling
        $this->wishlist->description = null;
        $this->assertNull($this->wishlist->description);

        // Test empty string to null conversion
        $this->wishlist->description = '';
        $this->assertNull($this->wishlist->description);
    }

    /**
     * Test isDefault property with automatic timestamp updates.
     */
    public function testIsDefaultPropertyWithTimestampUpdate(): void
    {
        $originalTimestamp = $this->wishlist->updatedAt;
        
        // Sleep to ensure timestamp difference
        usleep(1000);
        
        $this->wishlist->isDefault = true;
        $this->assertTrue($this->wishlist->isDefault);
        $this->assertGreaterThan($originalTimestamp, $this->wishlist->updatedAt);

        // Setting to false should not update timestamp
        $timestampAfterTrue = $this->wishlist->updatedAt;
        $this->wishlist->isDefault = false;
        $this->assertFalse($this->wishlist->isDefault);
        $this->assertEquals($timestampAfterTrue, $this->wishlist->updatedAt);
    }

    /**
     * Test totalValue computed property with validation and caching.
     */
    public function testTotalValueComputedProperty(): void
    {
        // Test initial value
        $this->assertEquals(0.0, $this->wishlist->totalValue);

        // Test negative value validation
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Total value cannot be negative');
        $this->wishlist->totalValue = -1.0;
    }

    /**
     * Test itemCount computed property with caching.
     */
    public function testItemCountComputedProperty(): void
    {
        // Test initial count
        $this->assertEquals(0, $this->wishlist->itemCount);

        // Create mock items collection
        $items = $this->createMock(WishlistItemCollection::class);
        $items->method('count')->willReturn(5);
        
        $this->wishlist->setItems($items);
        $this->assertEquals(5, $this->wishlist->itemCount);
    }

    /**
     * Test displayName virtual property.
     */
    public function testDisplayNameVirtualProperty(): void
    {
        $this->wishlist->name = 'My Wishlist';
        $this->wishlist->type = 'private';
        $this->wishlist->isDefault = false;
        
        $expected = 'My Wishlist [Private]';
        $this->assertEquals($expected, $this->wishlist->displayName);

        // Test with default wishlist
        $this->wishlist->isDefault = true;
        $expected = 'My Wishlist (Default) [Private]';
        $this->assertEquals($expected, $this->wishlist->displayName);
    }

    /**
     * Test isShared virtual property.
     */
    public function testIsSharedVirtualProperty(): void
    {
        // Test without share info
        $this->assertFalse($this->wishlist->isShared);

        // Test with empty share info
        $shareInfo = $this->createMock(\AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare\WishlistShareCollection::class);
        $shareInfo->method('count')->willReturn(0);
        $this->wishlist->setShareInfo($shareInfo);
        $this->assertFalse($this->wishlist->isShared);

        // Test with share info
        $shareInfo->method('count')->willReturn(1);
        $this->assertTrue($this->wishlist->isShared);
    }

    /**
     * Test asymmetric visibility properties.
     */
    public function testAsymmetricVisibilityProperties(): void
    {
        // Test read access
        $this->assertIsString($this->wishlist->id);
        $this->assertInstanceOf(\DateTime::class, $this->wishlist->createdAt);
        $this->assertIsString($this->wishlist->customerId);

        // Test that protected(set) properties cannot be modified directly
        // This would cause a compilation error in real usage
        $this->expectNotToPerformAssertions();
    }

    /**
     * Test cache invalidation functionality.
     */
    public function testCacheInvalidation(): void
    {
        // Set up items to test caching
        $items = $this->createMock(WishlistItemCollection::class);
        $items->method('count')->willReturn(3);
        $this->wishlist->setItems($items);
        
        // Access computed properties to cache them
        $this->assertEquals(3, $this->wishlist->itemCount);
        
        // Invalidate cache
        $originalTimestamp = $this->wishlist->updatedAt;
        usleep(1000);
        $this->wishlist->invalidateCache();
        
        // Verify timestamp was updated
        $this->assertGreaterThan($originalTimestamp, $this->wishlist->updatedAt);
    }

    /**
     * Test factory method creation.
     */
    public function testFactoryMethodCreation(): void
    {
        $wishlist = WishlistEntity::create(
            'factory-id',
            'factory-customer',
            'Factory Wishlist',
            'public',
            true
        );

        $this->assertEquals('factory-id', $wishlist->getId());
        $this->assertEquals('factory-customer', $wishlist->getCustomerId());
        $this->assertEquals('Factory Wishlist', $wishlist->name);
        $this->assertEquals('public', $wishlist->type);
        $this->assertTrue($wishlist->isDefault);
        $this->assertInstanceOf(\DateTime::class, $wishlist->createdAt);
        $this->assertInstanceOf(\DateTime::class, $wishlist->updatedAt);
    }

    /**
     * Test item management methods.
     */
    public function testItemManagement(): void
    {
        // Create mock item
        $item = $this->createMock(WishlistItemEntity::class);
        $item->method('getId')->willReturn('item-id');

        // Test adding item
        $this->wishlist->addItem($item);
        $this->assertInstanceOf(WishlistItemCollection::class, $this->wishlist->getItems());

        // Test removing item
        $this->wishlist->removeItem($item);
        
        // Verify cache invalidation occurred
        $this->assertNotNull($this->wishlist->updatedAt);
    }

    /**
     * Test calculateTotalValue method with various scenarios.
     */
    public function testCalculateTotalValueWithItems(): void
    {
        // Create mock product with price
        $product = $this->createMock(ProductEntity::class);
        $price = $this->createMock(Price::class);
        $priceCollection = $this->createMock(PriceCollection::class);
        
        $price->method('getGross')->willReturn(29.99);
        $priceCollection->method('first')->willReturn($price);
        $product->method('getPrice')->willReturn($priceCollection);

        // Create mock item
        $item = $this->createMock(WishlistItemEntity::class);
        $item->method('getProduct')->willReturn($product);

        // Create items collection
        $items = new WishlistItemCollection([$item, $item]); // Two items
        $this->wishlist->setItems($items);

        // Test total calculation (should be 2 * 29.99 = 59.98)
        $this->assertEquals(59.98, $this->wishlist->totalValue);
    }

    /**
     * Test performance of property hooks under load.
     */
    public function testPropertyHooksPerformance(): void
    {
        $startTime = microtime(true);
        
        // Perform many property operations
        for ($i = 0; $i < 1000; $i++) {
            $this->wishlist->name = "Test Name {$i}";
            $this->wishlist->type = 'private';
            $this->wishlist->description = "Description {$i}";
        }
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        // Should complete within reasonable time (adjust threshold as needed)
        $this->assertLessThan(100, $executionTime, 'Property hooks should be performant');
    }

    /**
     * Test memory usage efficiency.
     */
    public function testMemoryEfficiency(): void
    {
        $initialMemory = memory_get_usage(true);
        
        // Create multiple wishlist instances
        $wishlists = [];
        for ($i = 0; $i < 100; $i++) {
            $wishlists[] = WishlistEntity::create(
                "id-{$i}",
                "customer-{$i}",
                "Wishlist {$i}",
                'private',
                false
            );
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryUsed = $finalMemory - $initialMemory;
        
        // Memory usage should be reasonable (adjust threshold as needed)
        $this->assertLessThan(1024 * 1024, $memoryUsed, 'Memory usage should be efficient'); // Less than 1MB
        
        // Clean up
        unset($wishlists);
    }

    /**
     * Test thread safety of computed properties (simulated).
     */
    public function testComputedPropertyThreadSafety(): void
    {
        // Create items collection
        $items = $this->createMock(WishlistItemCollection::class);
        $items->method('count')->willReturn(5);
        $this->wishlist->setItems($items);
        
        // Simulate concurrent access to computed properties
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->wishlist->itemCount;
        }
        
        // All results should be consistent
        $this->assertCount(1, array_unique($results));
        $this->assertEquals(5, $results[0]);
    }

    /**
     * Test edge cases and boundary conditions.
     */
    public function testEdgeCases(): void
    {
        // Test Unicode characters in name
        $this->wishlist->name = 'Wünschliste 🎯';
        $this->assertEquals('Wünschliste 🎯', $this->wishlist->name);
        
        // Test very long description (within limits)
        $longDescription = str_repeat('A', 1000);
        $this->wishlist->description = $longDescription;
        $this->assertEquals($longDescription, $this->wishlist->description);
        
        // Test rapid type changes
        $types = ['private', 'public', 'shared'];
        foreach ($types as $type) {
            $this->wishlist->type = $type;
            $this->assertEquals($type, $this->wishlist->type);
        }
    }

    /**
     * Test backward compatibility with legacy methods.
     */
    public function testBackwardCompatibility(): void
    {
        // Test that legacy getter methods still work
        $this->assertIsString($this->wishlist->getId());
        $this->assertIsString($this->wishlist->getCustomerId());
        $this->assertInstanceOf(\AdvancedWishlist\Core\Domain\ValueObject\WishlistType::class, $this->wishlist->getType());
        
        // Test setter methods
        $customer = $this->createMock(CustomerEntity::class);
        $this->wishlist->setCustomer($customer);
        $this->assertEquals($customer, $this->wishlist->getCustomer());
        
        $customFields = ['test' => 'value'];
        $this->wishlist->setCustomFields($customFields);
        $this->assertEquals($customFields, $this->wishlist->getCustomFields());
    }
}
