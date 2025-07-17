<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem\WishlistItemDefinition;
use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare\WishlistShareDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class WishlistDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'wishlist';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return WishlistCollection::class;
    }

    public function getEntityClass(): string
    {
        return WishlistEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            (new StringField('type', 'type'))->addFlags(new Required()),
            (new BoolField('is_default', 'isDefault'))->addFlags(new Required()),
            new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class),
            new FkField('language_id', 'languageId', LanguageDefinition::class),
            (new IntField('item_count', 'itemCount'))->addFlags(new Required()),
            new FloatField('total_value', 'totalValue'),
            new CustomFields(),
            (new DateTimeField('created_at', 'createdAt'))->addFlags(new Required()),
            new DateTimeField('updated_at', 'updatedAt'),

            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
            new OneToManyAssociationField('items', WishlistItemDefinition::class, 'wishlist_id', 'id'),
            new OneToManyAssociationField('shareInfo', WishlistShareDefinition::class, 'wishlist_id', 'id'),
        ]);
    }
}
