<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem;

use AdvancedWishlist\Core\Content\Wishlist\WishlistDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;

class WishlistItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'wishlist_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return WishlistItemCollection::class;
    }

    public function getEntityClass(): string
    {
        return WishlistItemEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('wishlist_id', 'wishlistId', WishlistDefinition::class))->addFlags(new Required()),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            (new StringField('product_version_id', 'productVersionId'))->addFlags(new Required()),
            (new IntField('quantity', 'quantity'))->addFlags(new Required()),
            new StringField('note', 'note', 500),
            new IntField('priority', 'priority'),
            new FloatField('price_at_addition', 'priceAtAddition'),
            new FloatField('price_alert_threshold', 'priceAlertThreshold'),
            new BoolField('price_alert_active', 'priceAlertActive'),
            new CustomFields(),
            (new DateTimeField('added_at', 'addedAt'))->addFlags(new Required()),
            new DateTimeField('updated_at', 'updatedAt'),

            new ManyToOneAssociationField('wishlist', 'wishlist_id', WishlistDefinition::class, 'id', false),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false)
        ]);
    }
}
