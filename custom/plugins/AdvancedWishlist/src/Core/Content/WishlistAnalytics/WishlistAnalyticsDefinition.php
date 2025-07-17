<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\WishlistAnalytics;

use AdvancedWishlist\Core\Content\Wishlist\WishlistDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class WishlistAnalyticsDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'wishlist_analytics';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return WishlistAnalyticsCollection::class;
    }

    public function getEntityClass(): string
    {
        return WishlistAnalyticsEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('wishlist_id', 'wishlistId', WishlistDefinition::class))->addFlags(new Required()),
            (new DateField('date', 'date'))->addFlags(new Required()),
            (new IntField('views', 'views'))->addFlags(new Required()),
            (new IntField('shares', 'shares'))->addFlags(new Required()),
            (new IntField('items_added', 'itemsAdded'))->addFlags(new Required()),
            (new IntField('items_removed', 'itemsRemoved'))->addFlags(new Required()),
            (new IntField('conversions', 'conversions'))->addFlags(new Required()),
            new FloatField('conversion_value', 'conversionValue'),
            new CustomFields(),

            new ManyToOneAssociationField('wishlist', 'wishlist_id', WishlistDefinition::class, 'id', false),
        ]);
    }
}
