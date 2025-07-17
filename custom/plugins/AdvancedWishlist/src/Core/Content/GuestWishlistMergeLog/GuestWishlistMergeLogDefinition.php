<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\GuestWishlistMergeLog;

use AdvancedWishlist\Core\Content\GuestWishlist\GuestWishlistDefinition;
use AdvancedWishlist\Core\Content\Wishlist\WishlistDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class GuestWishlistMergeLogDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'guest_wishlist_merge_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return GuestWishlistMergeLogCollection::class;
    }

    public function getEntityClass(): string
    {
        return GuestWishlistMergeLogEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('guest_wishlist_id', 'guestWishlistId', GuestWishlistDefinition::class))->addFlags(new Required()),
            (new FkField('customer_wishlist_id', 'customerWishlistId', WishlistDefinition::class))->addFlags(new Required()),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required()),
            (new StringField('guest_id', 'guestId'))->addFlags(new Required()),
            (new IntField('items_merged', 'itemsMerged'))->addFlags(new Required()),
            (new IntField('items_skipped', 'itemsSkipped'))->addFlags(new Required()),
            new StringField('merge_strategy', 'mergeStrategy'),
            new JsonField('merge_data', 'mergeData'),
            (new DateTimeField('merged_at', 'mergedAt'))->addFlags(new Required()),

            new ManyToOneAssociationField('guestWishlist', 'guest_wishlist_id', GuestWishlistDefinition::class, 'id', false),
            new ManyToOneAssociationField('customerWishlist', 'customer_wishlist_id', WishlistDefinition::class, 'id', false),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
        ]);
    }
}
