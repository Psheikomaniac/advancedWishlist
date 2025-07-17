<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Enum;

/**
 * Repräsentiert die verschiedenen Benachrichtigungstypen für Wunschlisten.
 */
enum NotificationType: string
{
    /**
     * Benachrichtigung über Preisänderung eines Produkts.
     */
    case PRICE_DROP = 'price_drop';

    /**
     * Benachrichtigung über Wiederverfügbarkeit eines Produkts.
     */
    case BACK_IN_STOCK = 'back_in_stock';

    /**
     * Benachrichtigung über das Teilen einer Wunschliste.
     */
    case WISHLIST_SHARED = 'wishlist_shared';

    /**
     * Benachrichtigung über eine Genehmigungsanfrage.
     */
    case APPROVAL_REQUESTED = 'approval_requested';

    /**
     * Benachrichtigung über eine erteilte Genehmigung.
     */
    case APPROVAL_GRANTED = 'approval_granted';

    /**
     * Benachrichtigung über eine abgelehnte Genehmigung.
     */
    case APPROVAL_REJECTED = 'approval_rejected';

    /**
     * Gibt einen benutzerfreundlichen Label für den Benachrichtigungstyp zurück.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PRICE_DROP => 'Preissenkung',
            self::BACK_IN_STOCK => 'Wieder verfügbar',
            self::WISHLIST_SHARED => 'Wunschliste geteilt',
            self::APPROVAL_REQUESTED => 'Genehmigung angefragt',
            self::APPROVAL_GRANTED => 'Genehmigung erteilt',
            self::APPROVAL_REJECTED => 'Genehmigung abgelehnt',
        };
    }

    /**
     * Gibt die CSS-Klasse für die Benachrichtigungsanzeige zurück.
     */
    public function getCssClass(): string
    {
        return match ($this) {
            self::PRICE_DROP => 'success',
            self::BACK_IN_STOCK => 'info',
            self::WISHLIST_SHARED => 'primary',
            self::APPROVAL_REQUESTED => 'warning',
            self::APPROVAL_GRANTED => 'success',
            self::APPROVAL_REJECTED => 'danger',
        };
    }

    /**
     * Gibt das Icon für den Benachrichtigungstyp zurück.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::PRICE_DROP => 'tag',
            self::BACK_IN_STOCK => 'box',
            self::WISHLIST_SHARED => 'share',
            self::APPROVAL_REQUESTED => 'question-circle',
            self::APPROVAL_GRANTED => 'check-circle',
            self::APPROVAL_REJECTED => 'times-circle',
        };
    }

    /**
     * Prüft, ob der Benachrichtigungstyp mit Preisbenachrichtigungen zusammenhängt.
     */
    public function isPriceRelated(): bool
    {
        return self::PRICE_DROP === $this;
    }

    /**
     * Prüft, ob der Benachrichtigungstyp mit Verfügbarkeitsbenachrichtigungen zusammenhängt.
     */
    public function isStockRelated(): bool
    {
        return self::BACK_IN_STOCK === $this;
    }

    /**
     * Prüft, ob der Benachrichtigungstyp mit Genehmigungen zusammenhängt.
     */
    public function isApprovalRelated(): bool
    {
        return in_array($this, [self::APPROVAL_REQUESTED, self::APPROVAL_GRANTED, self::APPROVAL_REJECTED], true);
    }

    /**
     * Gibt alle verfügbaren Benachrichtigungstypen als assoziatives Array zurück.
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
