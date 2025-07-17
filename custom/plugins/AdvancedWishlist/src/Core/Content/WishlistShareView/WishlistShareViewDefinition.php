<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\WishlistShareView;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare\WishlistShareDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class WishlistShareViewDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'wishlist_share_view';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return WishlistShareViewCollection::class;
    }

    public function getEntityClass(): string
    {
        return WishlistShareViewEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('share_id', 'shareId', WishlistShareDefinition::class))->addFlags(new Required()),
            (new StringField('visitor_id', 'visitorId'))->addFlags(new Required()),
            new FkField('customer_id', 'customerId', 'customer'),
            new StringField('ip_address', 'ipAddress'),
            new LongTextField('user_agent', 'userAgent'),
            new LongTextField('referrer', 'referrer'),
            new StringField('country_code', 'countryCode'),
            new StringField('device_type', 'deviceType'),
            new BoolField('purchased', 'purchased'),
            new FloatField('purchase_value', 'purchaseValue'),
            (new DateTimeField('viewed_at', 'viewedAt'))->addFlags(new Required()),
            new CustomFields(),

            new ManyToOneAssociationField('share', 'share_id', WishlistShareDefinition::class, 'id', false),
        ]);
    }
}
