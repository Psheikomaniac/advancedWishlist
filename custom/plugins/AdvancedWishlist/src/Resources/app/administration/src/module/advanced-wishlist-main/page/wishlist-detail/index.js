import template from './wishlist-detail.html.twig';
import './wishlist-detail.scss';

const { Component, Mixin, Data: { Criteria } } = Shopware;

Component.register('advanced-wishlist-detail', {
    template,

    inject: [
        'wishlistAdminService',
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            wishlist: null,
            originalWishlist: null,
            wishlistRepository: null,
            customerRepository: null,
            productRepository: null,
            isLoading: false,
            isSaveLoading: false,
            processSuccess: false,
            showItemModal: false,
            showShareModal: false,
            showDeleteModal: false,
            selectedItems: {},
            activeTab: 'general'
        };
    },

    metaInfo() {
        return {
            title: this.wishlist ? 
                `${this.wishlist.name} - ${this.$createTitle()}` : 
                this.$createTitle()
        };
    },

    computed: {
        wishlistId() {
            return this.$route.params.id;
        },

        isNew() {
            return this.wishlistId === 'new';
        },

        tooltipSave() {
            if (!this.acl.can('advanced_wishlist.editor')) {
                return this.$tc('sw-privileges.tooltip.warning');
            }

            const systemKey = this.$device.getSystemKey();
            return `${systemKey} + S`;
        },

        tooltipCancel() {
            return 'ESC';
        },

        wishlistCriteria() {
            const criteria = new Criteria();
            
            criteria.addAssociation('customer');
            criteria.addAssociation('items.product.media');
            criteria.addAssociation('items.product.options.group');
            criteria.addAssociation('shares');
            
            return criteria;
        },

        customerCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('defaultBillingAddress.country');
            criteria.addAssociation('defaultShippingAddress.country');
            return criteria;
        },

        productCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('media');
            criteria.addAssociation('options.group');
            criteria.addAssociation('categories');
            return criteria;
        },

        itemCount() {
            return this.wishlist?.items?.length || 0;
        },

        shareCount() {
            return this.wishlist?.shares?.length || 0;
        },

        totalValue() {
            if (!this.wishlist?.items) return 0;
            
            return this.wishlist.items.reduce((total, item) => {
                const price = item.product?.price?.[0]?.gross || 0;
                return total + (price * item.quantity);
            }, 0);
        },

        canSave() {
            return this.wishlist && 
                   this.wishlist.name && 
                   this.wishlist.name.trim().length > 0 &&
                   this.wishlist.customerId;
        },

        hasChanges() {
            if (!this.originalWishlist || !this.wishlist) return false;
            
            return JSON.stringify(this.originalWishlist) !== JSON.stringify(this.wishlist);
        }
    },

    created() {
        this.wishlistRepository = this.repositoryFactory.create('advanced_wishlist');
        this.customerRepository = this.repositoryFactory.create('customer');
        this.productRepository = this.repositoryFactory.create('product');
        
        this.loadWishlist();
    },

    beforeRouteLeave(to, from, next) {
        if (this.hasChanges) {
            this.$refs.router.openUnsavedDataModal(next);
        } else {
            next();
        }
    },

    methods: {
        async loadWishlist() {
            if (this.isNew) {
                this.createNewWishlist();
                return;
            }

            this.isLoading = true;

            try {
                this.wishlist = await this.wishlistRepository.get(
                    this.wishlistId, 
                    Shopware.Context.api, 
                    this.wishlistCriteria
                );
                
                this.originalWishlist = this.wishlistRepository.create(Shopware.Context.api);
                Object.assign(this.originalWishlist, this.wishlist);
                
                this.isLoading = false;
            } catch (error) {
                this.isLoading = false;
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.detail.errorTitle'),
                    message: error.message
                });
                
                // Navigate back on error
                this.$router.push({ name: 'advanced.wishlist.main.overview' });
            }
        },

        createNewWishlist() {
            this.wishlist = this.wishlistRepository.create(Shopware.Context.api);
            this.wishlist.type = 'private';
            this.wishlist.isActive = true;
            this.wishlist.items = [];
            this.wishlist.shares = [];
            
            this.originalWishlist = null;
        },

        async onSave() {
            if (!this.canSave) {
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.detail.saveErrorTitle'),
                    message: this.$tc('advanced-wishlist-main.detail.saveErrorMissingData')
                });
                return;
            }

            this.isSaveLoading = true;

            try {
                await this.wishlistRepository.save(this.wishlist, Shopware.Context.api);
                
                this.createNotificationSuccess({
                    title: this.$tc('advanced-wishlist-main.detail.saveSuccessTitle'),
                    message: this.$tc('advanced-wishlist-main.detail.saveSuccessMessage')
                });

                if (this.isNew) {
                    this.$router.push({
                        name: 'advanced.wishlist.main.detail',
                        params: { id: this.wishlist.id }
                    });
                } else {
                    // Update original data to reflect saved state
                    this.originalWishlist = this.wishlistRepository.create(Shopware.Context.api);
                    Object.assign(this.originalWishlist, this.wishlist);
                }

                this.isSaveLoading = false;
                this.processSuccess = true;
            } catch (error) {
                this.isSaveLoading = false;
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.detail.saveErrorTitle'),
                    message: error.message
                });
            }
        },

        onCancel() {
            this.$router.push({ name: 'advanced.wishlist.main.overview' });
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.loadWishlist();
        },

        onTabChange(activeTab) {
            this.activeTab = activeTab;
        },

        // Item management methods
        async onAddItem(productId, quantity = 1) {
            try {
                const product = await this.productRepository.get(
                    productId,
                    Shopware.Context.api,
                    this.productCriteria
                );

                // Check if item already exists
                const existingItem = this.wishlist.items.find(item => item.productId === productId);
                
                if (existingItem) {
                    existingItem.quantity += quantity;
                } else {
                    const newItem = {
                        id: this.createId(),
                        productId: productId,
                        quantity: quantity,
                        product: product,
                        createdAt: new Date().toISOString()
                    };
                    
                    this.wishlist.items.push(newItem);
                }

                this.createNotificationSuccess({
                    title: this.$tc('advanced-wishlist-main.detail.itemAddSuccessTitle'),
                    message: this.$tc('advanced-wishlist-main.detail.itemAddSuccessMessage')
                });
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.detail.itemAddErrorTitle'),
                    message: error.message
                });
            }
        },

        onRemoveItem(itemId) {
            const index = this.wishlist.items.findIndex(item => item.id === itemId);
            if (index !== -1) {
                this.wishlist.items.splice(index, 1);
                
                this.createNotificationSuccess({
                    title: this.$tc('advanced-wishlist-main.detail.itemRemoveSuccessTitle'),
                    message: this.$tc('advanced-wishlist-main.detail.itemRemoveSuccessMessage')
                });
            }
        },

        onUpdateItemQuantity(itemId, quantity) {
            const item = this.wishlist.items.find(item => item.id === itemId);
            if (item) {
                if (quantity > 0) {
                    item.quantity = quantity;
                } else {
                    this.onRemoveItem(itemId);
                }
            }
        },

        onItemSelectionChanged(selection) {
            this.selectedItems = selection;
        },

        onRemoveSelectedItems() {
            const selectedIds = Object.keys(this.selectedItems);
            selectedIds.forEach(id => this.onRemoveItem(id));
            this.selectedItems = {};
        },

        // Share management methods
        async onCreateShare(shareData) {
            try {
                const newShare = {
                    id: this.createId(),
                    ...shareData,
                    wishlistId: this.wishlist.id,
                    createdAt: new Date().toISOString()
                };
                
                this.wishlist.shares.push(newShare);
                
                this.createNotificationSuccess({
                    title: this.$tc('advanced-wishlist-main.detail.shareCreateSuccessTitle'),
                    message: this.$tc('advanced-wishlist-main.detail.shareCreateSuccessMessage')
                });
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.detail.shareCreateErrorTitle'),
                    message: error.message
                });
            }
        },

        onRevokeShare(shareId) {
            const index = this.wishlist.shares.findIndex(share => share.id === shareId);
            if (index !== -1) {
                this.wishlist.shares.splice(index, 1);
                
                this.createNotificationSuccess({
                    title: this.$tc('advanced-wishlist-main.detail.shareRevokeSuccessTitle'),
                    message: this.$tc('advanced-wishlist-main.detail.shareRevokeSuccessMessage')
                });
            }
        },

        // Delete wishlist
        async onDelete() {
            try {
                await this.wishlistRepository.delete(this.wishlist.id, Shopware.Context.api);
                
                this.createNotificationSuccess({
                    title: this.$tc('advanced-wishlist-main.detail.deleteSuccessTitle'),
                    message: this.$tc('advanced-wishlist-main.detail.deleteSuccessMessage')
                });

                this.$router.push({ name: 'advanced.wishlist.main.overview' });
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.detail.deleteErrorTitle'),
                    message: error.message
                });
            }
        },

        // Utility methods
        createId() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        },

        getVariantFromWishlistType(type) {
            const variants = {
                private: 'info',
                public: 'success',  
                shared: 'warning'
            };
            return variants[type] || 'neutral';
        }
    }
});