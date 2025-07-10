<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Query\Wishlist;

use JsonSerializable;

/**
 * Response model for the GetWishlistsQuery
 * Contains the wishlists and pagination information
 */
final readonly class GetWishlistsQueryResponse implements JsonSerializable
{
    /**
     * @param int $total Total number of wishlists
     * @param int $page Current page number
     * @param int $limit Items per page
     * @param int $pages Total number of pages
     * @param array $wishlists Array of wishlist data
     * @param string|null $error Error message if any
     */
    public function __construct(
        public int $total,
        public int $page,
        public int $limit,
        public int $pages,
        public array $wishlists,
        public ?string $error = null
    ) {}

    /**
     * Create a response from an error
     */
    public static function fromError(string $error, int $limit = 10): self
    {
        return new self(
            total: 0,
            page: 1,
            limit: $limit,
            pages: 0,
            wishlists: [],
            error: $error
        );
    }

    /**
     * Implement JsonSerializable interface
     */
    public function jsonSerialize(): array
    {
        return [
            'total' => $this->total,
            'page' => $this->page,
            'limit' => $this->limit,
            'pages' => $this->pages,
            'wishlists' => $this->wishlists,
            'error' => $this->error,
        ];
    }
}