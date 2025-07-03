<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Enum;

/**
 * Repräsentiert die verschiedenen Rollen in einer Team-Wunschliste.
 */
enum WishlistRole: string
{
    /**
     * Besitzer der Wunschliste mit vollen Rechten.
     */
    case OWNER = 'owner';

    /**
     * Bearbeiter mit eingeschränkten Rechten.
     */
    case EDITOR = 'editor';

    /**
     * Betrachter mit minimalen Rechten.
     */
    case VIEWER = 'viewer';

    /**
     * Gibt einen benutzerfreundlichen Label für die Rolle zurück.
     */
    public function getLabel(): string
    {
        return match($this) {
            self::OWNER => 'Besitzer',
            self::EDITOR => 'Bearbeiter',
            self::VIEWER => 'Betrachter',
        };
    }

    /**
     * Prüft, ob die Rolle administrative Rechte hat.
     */
    public function isAdmin(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * Prüft, ob die Rolle Bearbeitungsrechte hat.
     */
    public function canEdit(): bool
    {
        return in_array($this, [self::OWNER, self::EDITOR], true);
    }

    /**
     * Gibt die zugehörigen Berechtigungen für die Rolle zurück.
     * 
     * @return array<string>
     */
    public function getPermissions(): array
    {
        return match($this) {
            self::OWNER => [
                WishlistPermission::ADD_PRODUCT->value,
                WishlistPermission::REMOVE_PRODUCT->value,
                WishlistPermission::MANAGE_MEMBERS->value,
                WishlistPermission::TRIGGER_ORDER->value,
                WishlistPermission::ADD_COMMENT->value,
                WishlistPermission::EXPORT_WISHLIST->value,
            ],
            self::EDITOR => [
                WishlistPermission::ADD_PRODUCT->value,
                WishlistPermission::REMOVE_PRODUCT->value,
                WishlistPermission::ADD_COMMENT->value,
                WishlistPermission::EXPORT_WISHLIST->value,
            ],
            self::VIEWER => [
                WishlistPermission::ADD_COMMENT->value,
                WishlistPermission::EXPORT_WISHLIST->value,
            ],
        };
    }

    /**
     * Gibt alle verfügbaren Rollen als assoziatives Array zurück.
     * 
     * @return array<string, string> Key: Enum-Wert, Value: Label
     */
    public static function getOptions(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}
