<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare;

use AdvancedWishlist\Core\Content\Wishlist\WishlistDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class WishlistShareDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'wishlist_share';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return WishlistShareCollection::class;
    }

    public function getEntityClass(): string
    {
        return WishlistShareEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('wishlist_id', 'wishlistId', WishlistDefinition::class))->addFlags(new Required()),
            (new LongTextField('token', 'token'))->addFlags(new Required()),
            (new StringField('type', 'type'))->addFlags(new Required()),
            new StringField('platform', 'platform'),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            new StringField('password', 'password'),
            new DateTimeField('expires_at', 'expiresAt'),
            new JsonField('settings', 'settings'),
            (new IntField('views', 'views'))->addFlags(new Required()),
            (new IntField('unique_views', 'uniqueViews'))->addFlags(new Required()),
            (new IntField('conversions', 'conversions'))->addFlags(new Required()),
            new DateTimeField('last_viewed_at', 'lastViewedAt'),
            new FkField('created_by', 'createdBy', WishlistDefinition::class), // Assuming created_by refers to a customer or admin
            (new DateTimeField('created_at', 'createdAt'))->addFlags(new Required()),
            new DateTimeField('revoked_at', 'revokedAt'),

            new ManyToOneAssociationField('wishlist', 'wishlist_id', WishlistDefinition::class, 'id', false)
        ]);
    }
}
