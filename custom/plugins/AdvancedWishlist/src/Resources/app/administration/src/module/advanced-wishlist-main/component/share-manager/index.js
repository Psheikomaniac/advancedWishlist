import template from './share-manager.html.twig';
import './share-manager.scss';

const { Component, Mixin, Utils } = Shopware;

Component.register('advanced-wishlist-share-manager', {
    template,

    inject: [
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        wishlist: {
            type: Object,
            required: true
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            customerRepository: null,
            isLoading: false,
            selection: {},
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            showCreateShareModal: false,
            newShare: {
                type: 'email',
                recipientEmail: '',
                recipientName: '',
                customerId: null,
                permissions: ['view'],
                message: '',
                expiresAt: null,
                isActive: true
            }
        };
    },

    computed: {
        shares() {
            if (!this.wishlist?.shares) return [];
            
            return this.wishlist.shares.map(share => ({
                ...share,
                isExpired: share.expiresAt && new Date(share.expiresAt) < new Date(),
                formattedExpiresAt: share.expiresAt ? 
                    this.$options.filters.date(share.expiresAt) : 
                    this.$tc('advanced-wishlist-main.shareManager.neverExpires'),
                permissionsList: this.formatPermissions(share.permissions || []),
                statusVariant: this.getShareStatusVariant(share)
            }));
        },

        shareColumns() {
            return [
                {
                    property: 'type',
                    dataIndex: 'type',
                    label: this.$tc('advanced-wishlist-main.shareManager.columnType'),
                    allowResize: true
                },
                {
                    property: 'recipientName',
                    dataIndex: 'recipientName',
                    label: this.$tc('advanced-wishlist-main.shareManager.columnRecipient'),
                    allowResize: true,
                    primary: true
                },
                {
                    property: 'recipientEmail',
                    dataIndex: 'recipientEmail',
                    label: this.$tc('advanced-wishlist-main.shareManager.columnEmail'),
                    allowResize: true
                },
                {
                    property: 'permissions',
                    dataIndex: 'permissionsList',
                    label: this.$tc('advanced-wishlist-main.shareManager.columnPermissions'),
                    allowResize: true
                },
                {
                    property: 'isActive',
                    dataIndex: 'isActive',
                    label: this.$tc('advanced-wishlist-main.shareManager.columnStatus'),
                    allowResize: true,
                    align: 'center'
                },
                {
                    property: 'expiresAt',
                    dataIndex: 'formattedExpiresAt',
                    label: this.$tc('advanced-wishlist-main.shareManager.columnExpiresAt'),
                    allowResize: true
                },
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: this.$tc('advanced-wishlist-main.shareManager.columnCreated'),
                    allowResize: true
                }
            ];
        },

        selectedSharesCount() {
            return Object.keys(this.selection).length;
        },

        canRevokeSelected() {
            return this.selectedSharesCount > 0 && !this.disabled;
        },

        hasShares() {
            return this.shares.length > 0;
        },

        shareTypes() {
            return [
                { value: 'email', label: this.$tc('advanced-wishlist-main.shareManager.typeEmail') },
                { value: 'link', label: this.$tc('advanced-wishlist-main.shareManager.typeLink') },
                { value: 'customer', label: this.$tc('advanced-wishlist-main.shareManager.typeCustomer') }
            ];
        },

        permissionOptions() {
            return [
                { value: 'view', label: this.$tc('advanced-wishlist-main.shareManager.permissionView') },
                { value: 'edit', label: this.$tc('advanced-wishlist-main.shareManager.permissionEdit') },
                { value: 'share', label: this.$tc('advanced-wishlist-main.shareManager.permissionShare') }
            ];
        },

        canCreateShare() {
            const share = this.newShare;
            
            if (!share.type) return false;
            
            if (share.type === 'email' || share.type === 'link') {
                return share.recipientEmail && 
                       share.recipientName && 
                       share.permissions.length > 0;
            }
            
            if (share.type === 'customer') {
                return share.customerId && share.permissions.length > 0;
            }
            
            return false;
        }
    },

    created() {
        this.customerRepository = this.repositoryFactory.create('customer');
    },

    methods: {
        onCreateShare() {
            this.resetNewShare();
            this.showCreateShareModal = true;
        },

        onCreateShareConfirm() {
            if (!this.canCreateShare) {
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.shareManager.createErrorTitle'),
                    message: this.$tc('advanced-wishlist-main.shareManager.createErrorValidation')
                });
                return;
            }

            const shareData = {
                ...this.newShare,
                id: this.createId(),
                wishlistId: this.wishlist.id,
                shareToken: this.generateShareToken(),
                createdAt: new Date().toISOString()
            };

            this.$emit('create-share', shareData);
            this.showCreateShareModal = false;
            this.resetNewShare();

            this.createNotificationSuccess({
                title: this.$tc('advanced-wishlist-main.shareManager.createSuccessTitle'),
                message: this.$tc('advanced-wishlist-main.shareManager.createSuccessMessage')
            });
        },

        onRevokeShare(share) {
            this.$emit('revoke-share', share.id);
            
            this.createNotificationSuccess({
                title: this.$tc('advanced-wishlist-main.shareManager.revokeSuccessTitle'),
                message: this.$tc('advanced-wishlist-main.shareManager.revokeSuccessMessage')
            });
        },

        onRevokeSelectedShares() {
            const selectedIds = Object.keys(this.selection);
            selectedIds.forEach(id => {
                this.$emit('revoke-share', id);
            });
            
            this.selection = {};
            
            this.createNotificationSuccess({
                title: this.$tc('advanced-wishlist-main.shareManager.revokeSelectedSuccessTitle'),
                message: this.$tc('advanced-wishlist-main.shareManager.revokeSelectedSuccessMessage', selectedIds.length)
            });
        },

        onToggleShareStatus(share) {
            share.isActive = !share.isActive;
            
            this.createNotificationInfo({
                title: share.isActive ? 
                    this.$tc('advanced-wishlist-main.shareManager.activateSuccessTitle') :
                    this.$tc('advanced-wishlist-main.shareManager.deactivateSuccessTitle'),
                message: share.isActive ?
                    this.$tc('advanced-wishlist-main.shareManager.activateSuccessMessage') :
                    this.$tc('advanced-wishlist-main.shareManager.deactivateSuccessMessage')
            });
        },

        onCopyShareLink(share) {
            const shareUrl = this.generateShareUrl(share);
            
            navigator.clipboard.writeText(shareUrl).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('advanced-wishlist-main.shareManager.copyLinkSuccessTitle'),
                    message: this.$tc('advanced-wishlist-main.shareManager.copyLinkSuccessMessage')
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.shareManager.copyLinkErrorTitle'),
                    message: this.$tc('advanced-wishlist-main.shareManager.copyLinkErrorMessage')
                });
            });
        },

        onSelectionChanged(selection) {
            this.selection = selection;
        },

        onSortColumn(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.sortBy = column.dataIndex;
                this.sortDirection = 'ASC';
            }
        },

        onShareTypeChange(type) {
            this.newShare.type = type;
            
            // Reset fields when changing type
            this.newShare.recipientEmail = '';
            this.newShare.recipientName = '';
            this.newShare.customerId = null;
            
            // Set default permissions based on type
            if (type === 'link') {
                this.newShare.permissions = ['view'];
            } else {
                this.newShare.permissions = ['view', 'edit'];
            }
        },

        resetNewShare() {
            this.newShare = {
                type: 'email',
                recipientEmail: '',
                recipientName: '',
                customerId: null,
                permissions: ['view'],
                message: '',
                expiresAt: null,
                isActive: true
            };
        },

        formatPermissions(permissions) {
            return permissions.map(permission => 
                this.$tc(`advanced-wishlist-main.shareManager.permission${permission.charAt(0).toUpperCase() + permission.slice(1)}`)
            ).join(', ');
        },

        getShareStatusVariant(share) {
            if (!share.isActive) return 'danger';
            if (share.isExpired) return 'warning';
            return 'success';
        },

        getShareTypeLabel(type) {
            const labels = {
                email: this.$tc('advanced-wishlist-main.shareManager.typeEmail'),
                link: this.$tc('advanced-wishlist-main.shareManager.typeLink'),
                customer: this.$tc('advanced-wishlist-main.shareManager.typeCustomer')
            };
            return labels[type] || type;
        },

        generateShareToken() {
            return Utils.createId();
        },

        generateShareUrl(share) {
            const baseUrl = window.location.origin;
            return `${baseUrl}/wishlist/shared/${share.shareToken}`;
        },

        createId() {
            return Utils.createId();
        }
    }
});