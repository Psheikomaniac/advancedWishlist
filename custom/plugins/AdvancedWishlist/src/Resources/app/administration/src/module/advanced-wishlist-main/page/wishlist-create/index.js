import template from './wishlist-create.html.twig';
import './wishlist-create.scss';

const { Component, Mixin, Data: { Criteria } } = Shopware;

Component.register('advanced-wishlist-create', {
    template,

    inject: [
        'wishlistAdminService',
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            wishlist: null,
            duplicateWishlist: null,
            wishlistRepository: null,
            customerRepository: null,
            productRepository: null,
            isLoading: false,
            isSaveLoading: false,
            processSuccess: false,
            isDuplicating: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(
                this.isDuplicating ? 
                    'advanced-wishlist-main.create.textTitleDuplicate' : 
                    'advanced-wishlist-main.create.textTitle'
            )
        };
    },

    computed: {
        duplicateId() {
            return this.$route.params.duplicateId || this.$route.query.duplicateId;
        },

        isDuplicateMode() {
            return !!this.duplicateId;
        },

        tooltipSave() {
            if (!this.acl.can('advanced_wishlist.creator')) {
                return this.$tc('sw-privileges.tooltip.warning');
            }

            const systemKey = this.$device.getSystemKey();
            return `${systemKey} + S`;
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

        canSave() {
            return this.wishlist && 
                   this.wishlist.name && 
                   this.wishlist.name.trim().length > 0 &&
                   this.wishlist.customerId &&
                   this.wishlist.type;
        },

        pageTitle() {
            if (this.isDuplicating) {
                return this.$tc('advanced-wishlist-main.create.textTitleDuplicate');
            }
            return this.$tc('advanced-wishlist-main.create.textTitle');
        },

        saveButtonText() {
            if (this.isDuplicating) {
                return this.$tc('advanced-wishlist-main.create.saveActionDuplicate');
            }
            return this.$tc('advanced-wishlist-main.create.saveAction');
        },

        formValidation() {
            const validation = {
                isValid: true,
                errors: {}
            };

            if (!this.wishlist?.name?.trim()) {
                validation.isValid = false;
                validation.errors.name = this.$tc('advanced-wishlist-main.create.errorNameRequired');
            }

            if (this.wishlist?.name && this.wishlist.name.length > 255) {
                validation.isValid = false;
                validation.errors.name = this.$tc('advanced-wishlist-main.create.errorNameTooLong');
            }

            if (!this.wishlist?.customerId) {
                validation.isValid = false;
                validation.errors.customerId = this.$tc('advanced-wishlist-main.create.errorCustomerRequired');
            }

            if (!this.wishlist?.type) {
                validation.isValid = false;
                validation.errors.type = this.$tc('advanced-wishlist-main.create.errorTypeRequired');
            }

            return validation;
        }
    },

    async created() {
        this.wishlistRepository = this.repositoryFactory.create('advanced_wishlist');
        this.customerRepository = this.repositoryFactory.create('customer');
        this.productRepository = this.repositoryFactory.create('product');
        
        await this.createWishlist();
    },

    methods: {
        async createWishlist() {
            this.isLoading = true;

            try {
                // Create new wishlist entity
                this.wishlist = this.wishlistRepository.create(Shopware.Context.api);
                
                // Set defaults
                this.wishlist.type = 'private';
                this.wishlist.isActive = true;
                this.wishlist.items = [];
                this.wishlist.shares = [];

                // Handle duplication
                if (this.isDuplicateMode) {
                    await this.loadAndDuplicateWishlist();
                }

                this.isLoading = false;
            } catch (error) {
                this.isLoading = false;
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.create.errorTitle'),
                    message: error.message
                });
                
                // Navigate back on error
                this.$router.push({ name: 'advanced.wishlist.main.overview' });
            }
        },

        async loadAndDuplicateWishlist() {
            try {
                this.isDuplicating = true;
                
                // Load original wishlist with all associations
                const criteria = new Criteria();
                criteria.addAssociation('customer');
                criteria.addAssociation('items.product');
                criteria.addAssociation('shares');

                this.duplicateWishlist = await this.wishlistRepository.get(
                    this.duplicateId,
                    Shopware.Context.api,
                    criteria
                );

                // Copy data from original wishlist
                this.wishlist.name = `${this.duplicateWishlist.name} (Copy)`;
                this.wishlist.description = this.duplicateWishlist.description;
                this.wishlist.type = this.duplicateWishlist.type;
                this.wishlist.customerId = this.duplicateWishlist.customerId;
                this.wishlist.customer = this.duplicateWishlist.customer;
                this.wishlist.isActive = this.duplicateWishlist.isActive;

                // Duplicate items (without IDs)
                if (this.duplicateWishlist.items && this.duplicateWishlist.items.length > 0) {
                    this.wishlist.items = this.duplicateWishlist.items.map(item => ({
                        id: this.createId(),
                        productId: item.productId,
                        quantity: item.quantity,
                        product: item.product,
                        createdAt: new Date().toISOString()
                    }));
                }

                // Note: Shares are not duplicated for security reasons

                this.createNotificationInfo({
                    title: this.$tc('advanced-wishlist-main.create.duplicateInfoTitle'),
                    message: this.$tc('advanced-wishlist-main.create.duplicateInfoMessage')
                });
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.create.duplicateErrorTitle'),
                    message: error.message
                });
            }
        },

        async onSave() {
            const validation = this.formValidation;
            
            if (!validation.isValid) {
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.create.saveErrorTitle'),
                    message: this.$tc('advanced-wishlist-main.create.saveErrorValidation')
                });
                return;
            }

            if (!this.acl.can('advanced_wishlist.creator')) {
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.create.saveErrorTitle'),
                    message: this.$tc('sw-privileges.tooltip.warning')
                });
                return;
            }

            this.isSaveLoading = true;

            try {
                // Set created timestamp
                this.wishlist.createdAt = new Date().toISOString();
                
                await this.wishlistRepository.save(this.wishlist, Shopware.Context.api);
                
                this.createNotificationSuccess({
                    title: this.$tc('advanced-wishlist-main.create.saveSuccessTitle'),
                    message: this.isDuplicating ? 
                        this.$tc('advanced-wishlist-main.create.duplicateSuccessMessage') :
                        this.$tc('advanced-wishlist-main.create.saveSuccessMessage')
                });

                // Navigate to detail view
                this.$router.push({
                    name: 'advanced.wishlist.main.detail',
                    params: { id: this.wishlist.id }
                });

                this.isSaveLoading = false;
                this.processSuccess = true;
            } catch (error) {
                this.isSaveLoading = false;
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.create.saveErrorTitle'),
                    message: error.message
                });
            }
        },

        onCancel() {
            this.$router.push({ name: 'advanced.wishlist.main.overview' });
        },

        onTypeChange(newType) {
            this.wishlist.type = newType;
            
            // Clear shares if changing from shared to private
            if (newType === 'private' && this.wishlist.shares?.length > 0) {
                this.wishlist.shares = [];
                this.createNotificationInfo({
                    title: this.$tc('advanced-wishlist-main.create.typeChangeTitle'),
                    message: this.$tc('advanced-wishlist-main.create.typeChangePrivateMessage')
                });
            }
        },

        onCustomerChange(customerId) {
            this.wishlist.customerId = customerId;
        },

        onAddProduct(productId) {
            if (!productId) return;

            // Check if product already exists
            const existingItem = this.wishlist.items.find(item => item.productId === productId);
            
            if (existingItem) {
                existingItem.quantity += 1;
                this.createNotificationInfo({
                    title: this.$tc('advanced-wishlist-main.create.productExistsTitle'),
                    message: this.$tc('advanced-wishlist-main.create.productExistsMessage')
                });
            } else {
                // Add new item
                const newItem = {
                    id: this.createId(),
                    productId: productId,
                    quantity: 1,
                    createdAt: new Date().toISOString()
                };
                
                this.wishlist.items.push(newItem);
                
                this.createNotificationSuccess({
                    title: this.$tc('advanced-wishlist-main.create.productAddSuccessTitle'),
                    message: this.$tc('advanced-wishlist-main.create.productAddSuccessMessage')
                });
            }
        },

        onRemoveItem(itemId) {
            const index = this.wishlist.items.findIndex(item => item.id === itemId);
            if (index !== -1) {
                this.wishlist.items.splice(index, 1);
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

        createId() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }
    }
});