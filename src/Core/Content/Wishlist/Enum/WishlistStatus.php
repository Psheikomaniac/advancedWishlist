<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Enum;

/**
 * Repräsentiert die verschiedenen Status einer Wunschliste.
 */
enum WishlistStatus: string
{
    /**
     * Wunschliste ist im Entwurfsstatus.
     */
    case DRAFT = 'draft';

    /**
     * Wunschliste ist aktiv und nutzbar.
     */
    case ACTIVE = 'active';

    /**
     * Wunschliste wartet auf Genehmigung (für B2B-Workflows).
     */
    case PENDING_APPROVAL = 'pending_approval';

    /**
     * Wunschliste wurde genehmigt.
     */
    case APPROVED = 'approved';

    /**
     * Wunschliste wurde abgelehnt.
     */
    case REJECTED = 'rejected';

    /**
     * Wunschliste wurde archiviert.
     */
    case ARCHIVED = 'archived';

    /**
     * Gibt einen benutzerfreundlichen Label für den Status zurück.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Entwurf',
            self::ACTIVE => 'Aktiv',
            self::PENDING_APPROVAL => 'Warte auf Genehmigung',
            self::APPROVED => 'Genehmigt',
            self::REJECTED => 'Abgelehnt',
            self::ARCHIVED => 'Archiviert',
        };
    }

    /**
     * Gibt die CSS-Klasse für die Statusanzeige zurück.
     */
    public function getCssClass(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::ACTIVE => 'success',
            self::PENDING_APPROVAL => 'warning',
            self::APPROVED => 'primary',
            self::REJECTED => 'danger',
            self::ARCHIVED => 'dark',
        };
    }

    /**
     * Prüft, ob der Status editierbar ist.
     */
    public function isEditable(): bool
    {
        return in_array($this, self::getEditableStatuses(), true);
    }

    /**
     * Gibt alle editierbaren Status zurück.
     *
     * @return array<self>
     */
    public static function getEditableStatuses(): array
    {
        return [self::DRAFT, self::ACTIVE];
    }

    /**
     * Prüft, ob der Status das Teilen erlaubt.
     */
    public function allowsSharing(): bool
    {
        return in_array($this, [self::ACTIVE, self::APPROVED], true);
    }

    /**
     * Gibt alle Status zurück, die für Genehmigung relevant sind.
     *
     * @return array<self>
     */
    public static function getApprovalStatuses(): array
    {
        return [self::PENDING_APPROVAL, self::APPROVED, self::REJECTED];
    }

    /**
     * Gibt alle verfügbaren Status als assoziatives Array zurück.
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
