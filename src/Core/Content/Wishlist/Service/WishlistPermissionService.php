<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Service;

use AdvancedWishlist\Core\Content\Wishlist\Entity\WishlistEntity;
use AdvancedWishlist\Core\Content\Wishlist\Enum\WishlistPermission;
use AdvancedWishlist\Core\Content\Wishlist\Enum\WishlistRole;
use AdvancedWishlist\Core\Content\Wishlist\Enum\WishlistType;
use Psr\Log\LoggerInterface;

/**
 * Service zur Verwaltung von Berechtigungen für Wunschlisten.
 */
class WishlistPermissionService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Prüft, ob ein Benutzer eine bestimmte Berechtigung für eine Wunschliste hat.
     */
    public function hasPermission(WishlistEntity $wishlist, string $userId, WishlistPermission $permission): bool
    {
        // Besitzer hat immer alle Rechte
        if ($wishlist->getCustomerId() === $userId) {
            return true;
        }

        // Bei privaten Wunschlisten hat nur der Besitzer Rechte
        if ($wishlist->getType() === WishlistType::PRIVATE) {
            return false;
        }

        // Bei öffentlichen Wunschlisten sind bestimmte Rechte für alle verfügbar
        if ($wishlist->getType() === WishlistType::PUBLIC) {
            // Bei öffentlichen Listen kann jeder kommentieren und exportieren
            if (in_array($permission, [WishlistPermission::ADD_COMMENT, WishlistPermission::EXPORT_WISHLIST], true)) {
                return true;
            }

            // Andere Rechte sind für öffentliche Listen nicht verfügbar für Nicht-Besitzer
            return false;
        }

        // Bei geteilten Wunschlisten auf Rolle prüfen
        $members = $wishlist->getMembers();
        if (!isset($members[$userId])) {
            return false;
        }

        // Rolle aus Mitgliederdaten extrahieren
        $memberRole = WishlistRole::tryFrom($members[$userId]['role'] ?? '');
        if ($memberRole === null) {
            $this->logger->warning('Ungültige Rolle für Benutzer {userId} in Wunschliste {wishlistId}', [
                'userId' => $userId,
                'wishlistId' => $wishlist->getId(),
            ]);
            return false;
        }

        // Prüfen, ob die Rolle die angeforderte Berechtigung hat
        return in_array($permission->value, $memberRole->getPermissions(), true);
    }

    /**
     * Gibt alle Berechtigungen zurück, die ein Benutzer für eine Wunschliste hat.
     * 
     * @return array<WishlistPermission>
     */
    public function getUserPermissions(WishlistEntity $wishlist, string $userId): array
    {
        // Besitzer hat alle Berechtigungen
        if ($wishlist->getCustomerId() === $userId) {
            return WishlistPermission::cases();
        }

        // Bei privaten Wunschlisten hat nur der Besitzer Rechte
        if ($wishlist->getType() === WishlistType::PRIVATE) {
            return [];
        }

        // Bei öffentlichen Wunschlisten haben alle Benutzer bestimmte Rechte
        if ($wishlist->getType() === WishlistType::PUBLIC) {
            return [WishlistPermission::ADD_COMMENT, WishlistPermission::EXPORT_WISHLIST];
        }

        // Bei geteilten Wunschlisten auf Rolle prüfen
        $members = $wishlist->getMembers();
        if (!isset($members[$userId])) {
            return [];
        }

        // Rolle aus Mitgliederdaten extrahieren
        $memberRole = WishlistRole::tryFrom($members[$userId]['role'] ?? '');
        if ($memberRole === null) {
            $this->logger->warning('Ungültige Rolle für Benutzer {userId} in Wunschliste {wishlistId}', [
                'userId' => $userId,
                'wishlistId' => $wishlist->getId(),
            ]);
            return [];
        }

        // Berechtigungen aus Rolle ableiten
        $permissions = [];
        foreach (WishlistPermission::cases() as $permission) {
            if (in_array($permission->value, $memberRole->getPermissions(), true)) {
                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * Prüft, ob ein Benutzer eine Wunschliste bearbeiten kann.
     */
    public function canEditWishlist(WishlistEntity $wishlist, string $userId): bool
    {
        // Prüfe, ob der Benutzer die notwendigen Berechtigungen hat
        return $this->hasPermission($wishlist, $userId, WishlistPermission::ADD_PRODUCT) && 
               $this->hasPermission($wishlist, $userId, WishlistPermission::REMOVE_PRODUCT);
    }

    /**
     * Prüft, ob ein Benutzer eine Wunschliste verwalten kann (Admin-Rechte).
     */
    public function canManageWishlist(WishlistEntity $wishlist, string $userId): bool
    {
        // Nur Besitzer können verwalten
        return $wishlist->getCustomerId() === $userId;
    }
}
