<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\GuestWishlist;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFieldsField;
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
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;

class GuestWishlistDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'guest_wishlist';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return GuestWishlistCollection::class;
    }

    public function getEntityClass(): string
    {
        return GuestWishlistEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('guest_id', 'guestId'))->addFlags(new Required()),
            new StringField('session_id', 'sessionId'),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required()),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new Required()),
            new StringField('name', 'name'),
            (new JsonField('items', 'items'))->addFlags(new Required()),
            new IntField('item_count', 'itemCount'),
            (new DateTimeField('expires_at', 'expiresAt'))->addFlags(new Required()),
            new StringField('ip_address', 'ipAddress'),
            new StringField('user_agent', 'userAgent'),
            new StringField('device_fingerprint', 'deviceFingerprint'),
            new DateTimeField('reminder_sent_at', 'reminderSentAt'),
            new StringField('reminder_email', 'reminderEmail'),
            new JsonField('conversion_tracking', 'conversionTracking'),
            (new DateTimeField('created_at', 'createdAt'))->addFlags(new Required()),
            new DateTimeField('updated_at', 'updatedAt'),

            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false)
        ]);
    }
}
