<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Doctrine;

use AdvancedWishlist\Core\Content\Wishlist\Enum\WishlistType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine-Typ für die Konvertierung zwischen WishlistType-Enum und Datenbank.
 */
class WishlistTypeType extends Type
{
    public const NAME = 'wishlist_type';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getVarcharTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof WishlistType ? $value->value : null;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?WishlistType
    {
        if ($value === null) {
            return null;
        }

        return WishlistType::tryFrom($value) ?? null;
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
