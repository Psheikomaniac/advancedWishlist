import template from './wishlist-card.html.twig';
import './wishlist-card.scss';

const { Component, Mixin } = Shopware;

Component.register('advanced-wishlist-card', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        wishlist: {
            type: Object,
            required: true
        },
        showActions: {
            type: Boolean,
            required: false,
            default: true
        },
        compact: {
            type: Boolean,
            required: false,
            default: false
        },
        selectable: {
            type: Boolean,
            required: false,
            default: false
        },
        selected: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        customerName() {
            if (!this.wishlist.customer) {
                return this.$tc('advanced-wishlist-main.wishlistCard.unknownCustomer');
            }
            return `${this.wishlist.customer.firstName} ${this.wishlist.customer.lastName}`;
        },

        itemCount() {
            return this.wishlist.items?.length || 0;
        },

        shareCount() {
            return this.wishlist.shares?.length || 0;
        },

        totalValue() {
            if (!this.wishlist.items) return 0;
            
            return this.wishlist.items.reduce((total, item) => {
                const price = item.product?.price?.[0]?.gross || 0;
                return total + (price * item.quantity);
            }, 0);
        },

        typeVariant() {
            const variants = {
                private: 'info',
                public: 'success',
                shared: 'warning'
            };
            return variants[this.wishlist.type] || 'neutral';
        },

        typeLabel() {
            const labels = {
                private: this.$tc('advanced-wishlist-main.list.typePrivate'),
                public: this.$tc('advanced-wishlist-main.list.typePublic'),
                shared: this.$tc('advanced-wishlist-main.list.typeShared')
            };
            return labels[this.wishlist.type] || this.wishlist.type;
        },

        statusVariant() {
            if (!this.wishlist.isActive) return 'danger';
            if (this.itemCount === 0) return 'warning';
            return 'success';
        },

        statusLabel() {
            if (!this.wishlist.isActive) {
                return this.$tc('advanced-wishlist-main.wishlistCard.statusInactive');
            }
            if (this.itemCount === 0) {
                return this.$tc('advanced-wishlist-main.wishlistCard.statusEmpty');
            }
            return this.$tc('advanced-wishlist-main.wishlistCard.statusActive');
        },

        formattedCreatedAt() {
            return this.$options.filters.date(this.wishlist.createdAt);
        },

        formattedUpdatedAt() {
            return this.$options.filters.date(this.wishlist.updatedAt);
        },

        detailRoute() {
            return {
                name: 'advanced.wishlist.main.detail',
                params: { id: this.wishlist.id }
            };
        },

        cardClass() {
            return {
                'advanced-wishlist-card': true,
                'advanced-wishlist-card--compact': this.compact,
                'advanced-wishlist-card--selectable': this.selectable,
                'advanced-wishlist-card--selected': this.selected,
                'advanced-wishlist-card--inactive': !this.wishlist.isActive,
                'advanced-wishlist-card--empty': this.itemCount === 0
            };
        }
    },

    methods: {
        onCardClick() {
            if (this.selectable) {
                this.$emit('selection-change', !this.selected);
                return;
            }
            
            this.$router.push(this.detailRoute);
        },

        onEditClick(event) {
            event.stopPropagation();
            this.$router.push(this.detailRoute);
        },

        onDuplicateClick(event) {
            event.stopPropagation();
            this.$router.push({
                name: 'advanced.wishlist.main.create',
                params: { duplicateId: this.wishlist.id }
            });
        },

        onDeleteClick(event) {
            event.stopPropagation();
            this.$emit('delete', this.wishlist.id);
        },

        onCustomerClick(event) {
            if (!this.wishlist.customer) return;
            
            event.stopPropagation();
            this.$router.push({
                name: 'sw.customer.detail',
                params: { id: this.wishlist.customerId }
            });
        },

        formatPrice(price) {
            return this.$options.filters.currency(price);
        }
    }
});