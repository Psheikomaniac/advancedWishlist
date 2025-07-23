const { Application } = Shopware;

/**
 * Wishlist Admin Service
 * Provides comprehensive admin functionality for wishlist management
 */
class WishlistAdminService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.name = 'wishlistAdminService';
    }

    getApiBasePath() {
        return '/api/admin/wishlists';
    }

    getHeaders() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${this.loginService.getToken()}`
        };
    }

    // === CRUD Operations ===

    /**
     * Get paginated list of wishlists with filtering and sorting
     * @param {Object} criteria - Search criteria object
     * @returns {Promise<Object>} - Search result with data and meta information
     */
    getWishlists(criteria = {}) {
        const params = this.buildSearchParams(criteria);
        
        return this.httpClient.get(this.getApiBasePath(), {
            params,
            headers: this.getHeaders()
        }).then(response => {
            return {
                data: response.data.data || [],
                total: response.data.total || 0,
                page: params.page || 1,
                limit: params.limit || 25
            };
        });
    }

    /**
     * Get detailed wishlist information including items and shares
     * @param {string} wishlistId - Wishlist ID
     * @returns {Promise<Object>} - Detailed wishlist data
     */
    getWishlist(wishlistId) {
        return this.httpClient.get(`${this.getApiBasePath()}/${wishlistId}`, {
            headers: this.getHeaders(),
            params: {
                associations: {
                    'items': {
                        'associations': {
                            'product': {
                                'associations': {
                                    'media': {}
                                }
                            }
                        }
                    },
                    'shares': {},
                    'customer': {}
                }
            }
        }).then(response => response.data);
    }

    /**
     * Create new wishlist for a customer
     * @param {Object} wishlistData - Wishlist creation data
     * @returns {Promise<Object>} - Created wishlist
     */
    createWishlist(wishlistData) {
        const data = {
            ...wishlistData,
            _csrf_token: window.csrf.token
        };

        return this.httpClient.post(this.getApiBasePath(), data, {
            headers: this.getHeaders()
        }).then(response => response.data);
    }

    /**
     * Update existing wishlist
     * @param {string} wishlistId - Wishlist ID
     * @param {Object} updateData - Data to update
     * @returns {Promise<Object>} - Updated wishlist
     */
    updateWishlist(wishlistId, updateData) {
        const data = {
            ...updateData,
            _csrf_token: window.csrf.token
        };

        return this.httpClient.patch(`${this.getApiBasePath()}/${wishlistId}`, data, {
            headers: this.getHeaders()
        }).then(response => response.data);
    }

    /**
     * Delete wishlist
     * @param {string} wishlistId - Wishlist ID
     * @param {string} transferToId - Optional wishlist ID to transfer items to
     * @returns {Promise<void>}
     */
    deleteWishlist(wishlistId, transferToId = null) {
        const params = transferToId ? { transferTo: transferToId } : {};
        
        return this.httpClient.delete(`${this.getApiBasePath()}/${wishlistId}`, {
            headers: this.getHeaders(),
            params,
            data: {
                _csrf_token: window.csrf.token
            }
        });
    }

    // === Bulk Operations ===

    /**
     * Delete multiple wishlists
     * @param {string[]} wishlistIds - Array of wishlist IDs
     * @returns {Promise<Object>} - Operation result
     */
    bulkDelete(wishlistIds) {
        return this.httpClient.post(`${this.getApiBasePath()}/bulk/delete`, {
            ids: wishlistIds,
            _csrf_token: window.csrf.token
        }, {
            headers: this.getHeaders()
        }).then(response => response.data);
    }

    /**
     * Export wishlists to CSV/Excel
     * @param {string[]} wishlistIds - Array of wishlist IDs (empty for all)
     * @param {string} format - Export format ('csv' or 'excel')
     * @returns {Promise<Blob>} - Export file blob
     */
    bulkExport(wishlistIds = [], format = 'csv') {
        return this.httpClient.post(`${this.getApiBasePath()}/bulk/export`, {
            ids: wishlistIds,
            format: format,
            _csrf_token: window.csrf.token
        }, {
            headers: this.getHeaders(),
            responseType: 'blob'
        }).then(response => response.data);
    }

    /**
     * Merge multiple wishlists into target wishlist
     * @param {string[]} sourceIds - Source wishlist IDs
     * @param {string} targetId - Target wishlist ID
     * @returns {Promise<Object>} - Merge operation result
     */
    bulkMerge(sourceIds, targetId) {
        return this.httpClient.post(`${this.getApiBasePath()}/bulk/merge`, {
            sourceIds: sourceIds,
            targetId: targetId,
            _csrf_token: window.csrf.token
        }, {
            headers: this.getHeaders()
        }).then(response => response.data);
    }

    // === Item Management ===

    /**
     * Manage wishlist items (add, remove, update)
     * @param {string} wishlistId - Wishlist ID
     * @param {Object[]} operations - Array of operations
     * @returns {Promise<Object>} - Operation results
     */
    manageItems(wishlistId, operations) {
        return this.httpClient.post(`${this.getApiBasePath()}/${wishlistId}/items/manage`, {
            operations: operations,
            _csrf_token: window.csrf.token
        }, {
            headers: this.getHeaders()
        }).then(response => response.data);
    }

    /**
     * Add product to wishlist
     * @param {string} wishlistId - Wishlist ID
     * @param {string} productId - Product ID
     * @param {number} quantity - Quantity to add
     * @returns {Promise<Object>} - Added item
     */
    addItem(wishlistId, productId, quantity = 1) {
        return this.httpClient.post(`${this.getApiBasePath()}/${wishlistId}/items`, {
            productId: productId,
            quantity: quantity,
            _csrf_token: window.csrf.token
        }, {
            headers: this.getHeaders()
        }).then(response => response.data);
    }

    /**
     * Remove item from wishlist
     * @param {string} wishlistId - Wishlist ID
     * @param {string} itemId - Item ID
     * @returns {Promise<void>}
     */
    removeItem(wishlistId, itemId) {
        return this.httpClient.delete(`${this.getApiBasePath()}/${wishlistId}/items/${itemId}`, {
            headers: this.getHeaders(),
            data: {
                _csrf_token: window.csrf.token
            }
        });
    }

    /**
     * Update item quantity
     * @param {string} wishlistId - Wishlist ID
     * @param {string} itemId - Item ID
     * @param {number} quantity - New quantity
     * @returns {Promise<Object>} - Updated item
     */
    updateItemQuantity(wishlistId, itemId, quantity) {
        return this.httpClient.patch(`${this.getApiBasePath()}/${wishlistId}/items/${itemId}`, {
            quantity: quantity,
            _csrf_token: window.csrf.token
        }, {
            headers: this.getHeaders()
        }).then(response => response.data);
    }

    // === Share Management ===

    /**
     * Create wishlist share
     * @param {string} wishlistId - Wishlist ID
     * @param {Object} shareData - Share configuration
     * @returns {Promise<Object>} - Created share
     */
    createShare(wishlistId, shareData) {
        return this.httpClient.post(`${this.getApiBasePath()}/${wishlistId}/shares`, {
            ...shareData,
            _csrf_token: window.csrf.token
        }, {
            headers: this.getHeaders()
        }).then(response => response.data);
    }

    /**
     * Update share permissions or settings
     * @param {string} wishlistId - Wishlist ID
     * @param {string} shareId - Share ID
     * @param {Object} updateData - Update data
     * @returns {Promise<Object>} - Updated share
     */
    updateShare(wishlistId, shareId, updateData) {
        return this.httpClient.patch(`${this.getApiBasePath()}/${wishlistId}/shares/${shareId}`, {
            ...updateData,
            _csrf_token: window.csrf.token
        }, {
            headers: this.getHeaders()
        }).then(response => response.data);
    }

    /**
     * Revoke wishlist share
     * @param {string} wishlistId - Wishlist ID
     * @param {string} shareId - Share ID
     * @returns {Promise<void>}
     */
    revokeShare(wishlistId, shareId) {
        return this.httpClient.delete(`${this.getApiBasePath()}/${wishlistId}/shares/${shareId}`, {
            headers: this.getHeaders(),
            data: {
                _csrf_token: window.csrf.token
            }
        });
    }

    // === Activity & Analytics ===

    /**
     * Get wishlist activity log
     * @param {string} wishlistId - Wishlist ID
     * @param {Object} options - Filtering options
     * @returns {Promise<Object[]>} - Activity log entries
     */
    getActivity(wishlistId, options = {}) {
        return this.httpClient.get(`${this.getApiBasePath()}/${wishlistId}/activity`, {
            headers: this.getHeaders(),
            params: options
        }).then(response => response.data);
    }

    /**
     * Get wishlist statistics
     * @param {string} wishlistId - Wishlist ID
     * @returns {Promise<Object>} - Wishlist statistics
     */
    getWishlistStats(wishlistId) {
        return this.httpClient.get(`${this.getApiBasePath()}/${wishlistId}/stats`, {
            headers: this.getHeaders()
        }).then(response => response.data);
    }

    // === Customer Management ===

    /**
     * Get customer's wishlists
     * @param {string} customerId - Customer ID
     * @param {Object} criteria - Search criteria
     * @returns {Promise<Object>} - Customer's wishlists
     */
    getCustomerWishlists(customerId, criteria = {}) {
        const params = this.buildSearchParams({
            ...criteria,
            filter: {
                ...criteria.filter,
                customerId: customerId
            }
        });

        return this.httpClient.get(this.getApiBasePath(), {
            params,
            headers: this.getHeaders()
        }).then(response => response.data);
    }

    /**
     * Search customers for wishlist assignment
     * @param {string} searchTerm - Customer search term
     * @param {number} limit - Result limit
     * @returns {Promise<Object[]>} - Matching customers
     */
    searchCustomers(searchTerm, limit = 10) {
        return this.httpClient.get('/api/admin/customers', {
            headers: this.getHeaders(),
            params: {
                search: searchTerm,
                limit: limit,
                fields: ['id', 'customerNumber', 'firstName', 'lastName', 'email']
            }
        }).then(response => response.data);
    }

    // === Utility Methods ===

    /**
     * Build search parameters from criteria object
     * @param {Object} criteria - Search criteria
     * @returns {Object} - URL parameters
     */
    buildSearchParams(criteria) {
        const params = {};

        // Pagination
        if (criteria.page) params.page = criteria.page;
        if (criteria.limit) params.limit = criteria.limit;

        // Sorting
        if (criteria.sort) {
            params.sort = criteria.sort;
        }

        // Filtering
        if (criteria.filter) {
            Object.keys(criteria.filter).forEach(field => {
                const value = criteria.filter[field];
                if (value !== null && value !== undefined && value !== '') {
                    params[`filter[${field}]`] = value;
                }
            });
        }

        // Search
        if (criteria.search) {
            params.search = criteria.search;
        }

        // Associations
        if (criteria.associations) {
            params.associations = criteria.associations;
        }

        return params;
    }

    /**
     * Validate wishlist data
     * @param {Object} wishlistData - Wishlist data to validate
     * @returns {Object} - Validation result
     */
    validateWishlistData(wishlistData) {
        const errors = {};

        if (!wishlistData.name || wishlistData.name.trim().length === 0) {
            errors.name = 'Wishlist name is required';
        }

        if (wishlistData.name && wishlistData.name.length > 255) {
            errors.name = 'Wishlist name cannot exceed 255 characters';
        }

        if (!wishlistData.customerId) {
            errors.customerId = 'Customer is required';
        }

        if (wishlistData.type && !['private', 'public', 'shared'].includes(wishlistData.type)) {
            errors.type = 'Invalid wishlist type';
        }

        return {
            isValid: Object.keys(errors).length === 0,
            errors: errors
        };
    }

    /**
     * Format wishlist data for display
     * @param {Object} wishlist - Raw wishlist data
     * @returns {Object} - Formatted wishlist data
     */
    formatWishlistForDisplay(wishlist) {
        return {
            ...wishlist,
            itemCount: wishlist.items?.length || 0,
            shareCount: wishlist.shares?.length || 0,
            customerName: wishlist.customer ? 
                `${wishlist.customer.firstName} ${wishlist.customer.lastName}` : 
                'Unknown Customer',
            formattedCreatedAt: new Date(wishlist.createdAt).toLocaleDateString(),
            formattedUpdatedAt: new Date(wishlist.updatedAt).toLocaleDateString(),
            typeLabel: this.getWishlistTypeLabel(wishlist.type)
        };
    }

    /**
     * Get localized label for wishlist type
     * @param {string} type - Wishlist type
     * @returns {string} - Localized type label
     */
    getWishlistTypeLabel(type) {
        const labels = {
            'private': 'Private',
            'public': 'Public',
            'shared': 'Shared'
        };
        return labels[type] || type;
    }
}

// Register service
Application.addServiceProvider('wishlistAdminService', (container) => {
    const initContainer = Application.getContainer('init');
    return new WishlistAdminService(
        initContainer.httpClient,
        container.loginService
    );
});

export default WishlistAdminService;