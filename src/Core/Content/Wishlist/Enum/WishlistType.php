<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Enum;

/**
 * Repräsentiert die verschiedenen Typen einer Wunschliste.
 */
enum WishlistType: string
{
    /**
     * Private Wunschliste - nur für den Ersteller sichtbar.
     */
    case PRIVATE = 'private';

    /**
     * Öffentliche Wunschliste - für alle Benutzer sichtbar.
     */
    case PUBLIC = 'public';

    /**
     * Geteilte Wunschliste - nur für bestimmte Benutzer sichtbar.
     */
    case SHARED = 'shared';

    /**
     * Gibt einen benutzerfreundlichen Label für den Typ zurück.
     */
    public function getLabel(): string
    {
        return match($this) {
            self::PRIVATE => 'Privat',
            self::PUBLIC => 'Öffentlich',
            self::SHARED => 'Geteilt',
        };
    }

    /**
     * Prüft, ob der Typ öffentlich sichtbar ist.
     */
    public function isPubliclyVisible(): bool
    {
        return $this === self::PUBLIC;
    }

    /**
     * Prüft, ob der Typ eine Freigabe für andere Benutzer ermöglicht.
     */
    public function allowsSharing(): bool
    {
        return in_array($this, [self::PUBLIC, self::SHARED], true);
    }

    /**
     * Gibt alle verfügbaren Typen als assoziatives Array zurück.
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
