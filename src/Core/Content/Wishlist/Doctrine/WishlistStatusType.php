<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Doctrine;

use AdvancedWishlist\Core\Content\Wishlist\Enum\WishlistStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine-Typ für die Konvertierung zwischen WishlistStatus-Enum und Datenbank.
 */
class WishlistStatusType extends Type
{
    public const NAME = 'wishlist_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getVarcharTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof WishlistStatus ? $value->value : null;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?WishlistStatus
    {
        if ($value === null) {
            return null;
        }

        return WishlistStatus::tryFrom($value) ?? null;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
