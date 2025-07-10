import template from './advanced-wishlist-analytics-overview.html.twig';

const {Component, Mixin} = Shopware;

Component.register('advanced-wishlist-analytics-overview', {
    template,

    inject: [
        'AnalyticsService'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            totalWishlists: 0,
            totalItems: 0,
            totalShares: 0,
            totalConversions: 0,
            isLoading: false,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadAnalyticsSummary();
        },

        loadAnalyticsSummary() {
            this.isLoading = true;
            this.AnalyticsService.getAnalyticsSummary().then((response) => {
                this.totalWishlists = response.totalWishlists;
                this.totalItems = response.totalItems;
                this.totalShares = response.totalShares;
                this.totalConversions = response.totalConversions;
                this.isLoading = false;
            }).catch((error) => {
                this.isLoading = false;
                this.showNotificationError(
                    this.$tc('advanced-wishlist-analytics.overview.errorTitle'),
                    error.message
                );
            });
        },
    },
});