import template from './wishlist-list.html.twig';
import './wishlist-list.scss';

const { Component, Mixin, Data: { Criteria } } = Shopware;

Component.register('advanced-wishlist-list', {
    template,

    inject: [
        'wishlistAdminService',
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing')
    ],

    data() {
        return {
            wishlists: [],
            wishlistRepository: null,
            isLoading: false,
            selection: {},
            showBulkEditModal: false,
            showDeleteModal: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            naturalSorting: false,
            searchTerm: '',
            filterCriteria: {
                type: null,
                customerId: null,
                dateFrom: null,
                dateTo: null
            },
            total: 0,
            page: 1,
            limit: 25
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        wishlistColumns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: this.$tc('advanced-wishlist-main.list.columnName'),
                    routerLink: 'advanced.wishlist.main.detail',
                    inlineEdit: 'string',
                    allowResize: true,
                    primary: true
                },
                {
                    property: 'customer.firstName,customer.lastName',
                    dataIndex: 'customer.firstName,customer.lastName',
                    label: this.$tc('advanced-wishlist-main.list.columnCustomer'),
                    allowResize: true
                },
                {
                    property: 'type',
                    dataIndex: 'type',
                    label: this.$tc('advanced-wishlist-main.list.columnType'),
                    allowResize: true
                },
                {
                    property: 'itemCount',
                    dataIndex: 'itemCount',
                    label: this.$tc('advanced-wishlist-main.list.columnItemCount'),
                    align: 'right',
                    allowResize: true
                },
                {
                    property: 'shareCount',
                    dataIndex: 'shareCount',
                    label: this.$tc('advanced-wishlist-main.list.columnShares'),
                    align: 'right',
                    allowResize: true
                },
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: this.$tc('advanced-wishlist-main.list.columnCreatedAt'),
                    allowResize: true
                },
                {
                    property: 'updatedAt',
                    dataIndex: 'updatedAt',
                    label: this.$tc('advanced-wishlist-main.list.columnUpdatedAt'),
                    allowResize: true
                }
            ];
        },

        wishlistCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            // Associations
            criteria.addAssociation('customer');
            criteria.addAssociation('items');
            criteria.addAssociation('shares');

            // Sorting
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            // Search
            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            // Filters
            if (this.filterCriteria.type) {
                criteria.addFilter(Criteria.equals('type', this.filterCriteria.type));
            }

            if (this.filterCriteria.customerId) {
                criteria.addFilter(Criteria.equals('customerId', this.filterCriteria.customerId));
            }

            if (this.filterCriteria.dateFrom) {
                criteria.addFilter(Criteria.range('createdAt', {
                    gte: this.filterCriteria.dateFrom
                }));
            }

            if (this.filterCriteria.dateTo) {
                criteria.addFilter(Criteria.range('createdAt', {
                    lte: this.filterCriteria.dateTo
                }));
            }

            return criteria;
        },

        selectedWishlistsCount() {
            return Object.keys(this.selection).length;
        },

        canDelete() {
            return this.selectedWishlistsCount > 0;
        },

        canExport() {
            return this.total > 0;
        }
    },

    created() {
        this.wishlistRepository = this.repositoryFactory.create('advanced_wishlist');
        this.getList();
    },

    methods: {
        async getList() {
            this.isLoading = true;

            try {
                const result = await this.wishlistRepository.search(this.wishlistCriteria);
                
                this.wishlists = result.map(wishlist => ({
                    ...wishlist,
                    itemCount: wishlist.items?.length || 0,
                    shareCount: wishlist.shares?.length || 0,
                    customerName: wishlist.customer ? 
                        `${wishlist.customer.firstName} ${wishlist.customer.lastName}` : 
                        this.$tc('advanced-wishlist-main.list.unknownCustomer'),
                    typeLabel: this.getTypeLabel(wishlist.type)
                }));

                this.total = result.total;
                this.isLoading = false;
            } catch (error) {
                this.isLoading = false;
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.list.errorTitle'),
                    message: error.message
                });
            }
        },

        onChangeLanguage() {
            this.getList();
        },

        onSearch(searchTerm) {
            this.searchTerm = searchTerm;
            this.onRefresh();
        },

        onRefresh() {
            this.page = 1;
            this.getList();
        },

        onSortColumn(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.sortBy = column.dataIndex;
                this.sortDirection = 'ASC';
            }

            this.onRefresh();
        },

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.getList();
        },

        onSelectionChanged(selection) {
            this.selection = selection;
        },

        onFilterChanged(filterCriteria) {
            this.filterCriteria = { ...filterCriteria };
            this.onRefresh();
        },

        onInlineEditSave(wishlist) {
            this.wishlistRepository.save(wishlist).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('advanced-wishlist-main.list.saveSuccessTitle'),
                    message: this.$tc('advanced-wishlist-main.list.saveSuccessMessage')
                });
            }).catch((error) => {
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.list.saveErrorTitle'),
                    message: error.message
                });
                this.getList();
            });
        },

        onInlineEditCancel(wishlist) {
            wishlist.discardChanges();
        },

        onCreateWishlist() {
            this.$router.push({ name: 'advanced.wishlist.main.create' });
        },

        onDuplicateWishlist(wishlist) {
            this.$router.push({ 
                name: 'advanced.wishlist.main.create', 
                params: { duplicateId: wishlist.id } 
            });
        },

        onDeleteWishlist(wishlistId) {
            this.wishlistRepository.delete(wishlistId).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('advanced-wishlist-main.list.deleteSuccessTitle'),
                    message: this.$tc('advanced-wishlist-main.list.deleteSuccessMessage')
                });
                this.getList();
            }).catch((error) => {
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.list.deleteErrorTitle'),
                    message: error.message
                });
            });
        },

        onBulkDelete() {
            const selectedIds = Object.keys(this.selection);
            
            this.wishlistAdminService.bulkDelete(selectedIds).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('advanced-wishlist-main.list.bulkDeleteSuccessTitle'),
                    message: this.$tc('advanced-wishlist-main.list.bulkDeleteSuccessMessage', selectedIds.length)
                });
                this.selection = {};
                this.getList();
            }).catch((error) => {
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.list.bulkDeleteErrorTitle'),
                    message: error.message
                });
            });

            this.showDeleteModal = false;
        },

        onExport(format = 'csv') {
            const selectedIds = Object.keys(this.selection);
            const exportIds = selectedIds.length > 0 ? selectedIds : [];

            this.wishlistAdminService.bulkExport(exportIds, format).then((blob) => {
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `wishlists-export-${new Date().toISOString().split('T')[0]}.${format}`;
                link.click();
                window.URL.revokeObjectURL(url);

                this.createNotificationSuccess({
                    title: this.$tc('advanced-wishlist-main.list.exportSuccessTitle'),
                    message: this.$tc('advanced-wishlist-main.list.exportSuccessMessage')
                });
            }).catch((error) => {
                this.createNotificationError({
                    title: this.$tc('advanced-wishlist-main.list.exportErrorTitle'),
                    message: error.message
                });
            });
        },

        getTypeLabel(type) {
            const labels = {
                private: this.$tc('advanced-wishlist-main.list.typePrivate'),
                public: this.$tc('advanced-wishlist-main.list.typePublic'),
                shared: this.$tc('advanced-wishlist-main.list.typeShared')
            };
            return labels[type] || type;
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