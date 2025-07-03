<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Enum;

/**
 * Repräsentiert die verschiedenen Berechtigungen für Wunschlisten.
 */
enum WishlistPermission: string
{
    /**
     * Berechtigung zum Hinzufügen von Produkten zur Wunschliste.
     */
    case ADD_PRODUCT = 'add_product';

    /**
     * Berechtigung zum Entfernen von Produkten aus der Wunschliste.
     */
    case REMOVE_PRODUCT = 'remove_product';

    /**
     * Berechtigung zur Verwaltung von Mitgliedern in der Wunschliste.
     */
    case MANAGE_MEMBERS = 'manage_members';

    /**
     * Berechtigung zum Auslösen einer Bestellung.
     */
    case TRIGGER_ORDER = 'trigger_order';

    /**
     * Berechtigung zum Hinzufügen von Kommentaren.
     */
    case ADD_COMMENT = 'add_comment';

    /**
     * Berechtigung zum Exportieren der Wunschliste.
     */
    case EXPORT_WISHLIST = 'export_wishlist';

    /**
     * Gibt einen benutzerfreundlichen Label für die Berechtigung zurück.
     */
    public function getLabel(): string
    {
        return match($this) {
            self::ADD_PRODUCT => 'Produkte hinzufügen',
            self::REMOVE_PRODUCT => 'Produkte entfernen',
            self::MANAGE_MEMBERS => 'Mitglieder verwalten',
            self::TRIGGER_ORDER => 'Bestellung auslösen',
            self::ADD_COMMENT => 'Kommentare hinzufügen',
            self::EXPORT_WISHLIST => 'Wunschliste exportieren',
        };
    }

    /**
     * Gibt die Beschreibung der Berechtigung zurück.
     */
    public function getDescription(): string
    {
        return match($this) {
            self::ADD_PRODUCT => 'Erlaubt das Hinzufügen von Produkten zur Wunschliste',
            self::REMOVE_PRODUCT => 'Erlaubt das Entfernen von Produkten aus der Wunschliste',
            self::MANAGE_MEMBERS => 'Erlaubt die Verwaltung von Teammitgliedern',
            self::TRIGGER_ORDER => 'Erlaubt das Auslösen einer Bestellung aus der Wunschliste',
            self::ADD_COMMENT => 'Erlaubt das Hinzufügen von Kommentaren zu Produkten',
            self::EXPORT_WISHLIST => 'Erlaubt den Export der Wunschliste in verschiedene Formate',
        };
    }

    /**
     * Prüft, ob die Berechtigung administrative Rechte erfordert.
     */
    public function isAdminPermission(): bool
    {
        return in_array($this, [self::MANAGE_MEMBERS, self::TRIGGER_ORDER], true);
    }

    /**
     * Gibt alle Berechtigungen für die Bearbeitung zurück.
     * 
     * @return array<self>
     */
    public static function getEditPermissions(): array
    {
        return [self::ADD_PRODUCT, self::REMOVE_PRODUCT];
    }

    /**
     * Gibt alle verfügbaren Berechtigungen als assoziatives Array zurück.
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
