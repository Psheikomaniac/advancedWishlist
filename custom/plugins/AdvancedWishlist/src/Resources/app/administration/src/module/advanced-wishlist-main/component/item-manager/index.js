import template from './item-manager.html.twig';
import './item-manager.scss';

const { Component, Mixin, Data: { Criteria } } = Shopware;

Component.register('advanced-wishlist-item-manager', {
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
            productRepository: null,
            isLoading: false,
            selection: {},
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            showAddProductModal: false,
            searchTerm: '',
            selectedProducts: []
        };
    },

    computed: {
        items() {
            if (!this.wishlist?.items) return [];
            
            return this.wishlist.items.map(item => ({
                ...item,
                productName: item.product?.name || 'Unknown Product',
                productNumber: item.product?.productNumber || '-',
                unitPrice: item.product?.price?.[0]?.gross || 0,
                totalPrice: (item.product?.price?.[0]?.gross || 0) * item.quantity,
                stock: item.product?.stock || 0,
                available: item.product?.available || false
            }));
        },

        itemColumns() {
            return [
                {
                    property: 'product.name',
                    dataIndex: 'productName',
                    label: this.$tc('advanced-wishlist-main.itemManager.columnProductName'),
                    routerLink: 'sw.product.detail',
                    allowResize: true,
                    primary: true
                },
                {
                    property: 'product.productNumber',
                    dataIndex: 'productNumber',
                    label: this.$tc('advanced-wishlist-main.itemManager.columnProductNumber'),
                    allowResize: true
                },
                {
                    property: 'quantity',
                    dataIndex: 'quantity',
                    label: this.$tc('advanced-wishlist-main.itemManager.columnQuantity'),
                    allowResize: true,
                    align: 'right',
                    inlineEdit: 'number'
                },
                {
                    property: 'unitPrice',
                    dataIndex: 'unitPrice',
                    label: this.$tc('advanced-wishlist-main.itemManager.columnUnitPrice'),
                    allowResize: true,
                    align: 'right'
                },
                {
                    property: 'totalPrice',
                    dataIndex: 'totalPrice',
                    label: this.$tc('advanced-wishlist-main.itemManager.columnTotalPrice'),
                    allowResize: true,
                    align: 'right'
                },
                {
                    property: 'stock',
                    dataIndex: 'stock',
                    label: this.$tc('advanced-wishlist-main.itemManager.columnStock'),
                    allowResize: true,
                    align: 'right'
                },
                {
                    property: 'available',
                    dataIndex: 'available',
                    label: this.$tc('advanced-wishlist-main.itemManager.columnAvailable'),
                    allowResize: true,
                    align: 'center'
                },
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: this.$tc('advanced-wishlist-main.itemManager.columnAdded'),
                    allowResize: true
                }
            ];
        },

        productCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('media');
            criteria.addAssociation('options.group');
            criteria.addAssociation('categories');
            
            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }
            
            return criteria;
        },

        selectedItemsCount() {
            return Object.keys(this.selection).length;
        },

        canRemoveSelected() {
            return this.selectedItemsCount > 0 && !this.disabled;
        },

        totalValue() {
            return this.items.reduce((sum, item) => sum + item.totalPrice, 0);
        },

        totalItems() {
            return this.items.reduce((sum, item) => sum + item.quantity, 0);
        },

        hasItems() {
            return this.items.length > 0;
        }
    },

    created() {
        this.productRepository = this.repositoryFactory.create('product');
    },

    methods: {
        onAddItem() {
            this.showAddProductModal = true;
        },

        async onAddProducts() {
            if (!this.selectedProducts.length) return;

            for (const product of this.selectedProducts) {
                this.$emit('add-item', product.id, 1);
            }

            this.selectedProducts = [];
            this.showAddProductModal = false;
            
            this.createNotificationSuccess({
                title: this.$tc('advanced-wishlist-main.itemManager.addSuccessTitle'),
                message: this.$tc('advanced-wishlist-main.itemManager.addSuccessMessage', this.selectedProducts.length)
            });
        },

        onRemoveItem(item) {
            this.$emit('remove-item', item.id);
        },

        onRemoveSelectedItems() {
            const selectedIds = Object.keys(this.selection);
            selectedIds.forEach(id => {
                this.$emit('remove-item', id);
            });
            
            this.selection = {};
            
            this.createNotificationSuccess({
                title: this.$tc('advanced-wishlist-main.itemManager.removeSuccessTitle'),
                message: this.$tc('advanced-wishlist-main.itemManager.removeSuccessMessage', selectedIds.length)
            });
        },

        onInlineEditSave(item) {
            this.$emit('update-quantity', item.id, item.quantity);
        },

        onInlineEditCancel(item) {
            // Reset quantity to original value
            const originalItem = this.wishlist.items.find(original => original.id === item.id);
            if (originalItem) {
                item.quantity = originalItem.quantity;
            }
        },

        onSelectionChanged(selection) {
            this.selection = selection;
            this.$emit('selection-change', selection);
        },

        onSortColumn(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.sortBy = column.dataIndex;
                this.sortDirection = 'ASC';
            }
        },

        onProductSearch(searchTerm) {
            this.searchTerm = searchTerm;
        },

        getAvailabilityVariant(available) {
            return available ? 'success' : 'danger';
        },

        getStockVariant(stock) {
            if (stock > 10) return 'success';
            if (stock > 0) return 'warning';
            return 'danger';
        },

        formatPrice(price) {
            return this.$options.filters.currency(price);
        }
    }
});