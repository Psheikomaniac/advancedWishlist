import './page/advanced-wishlist-analytics-overview';

Shopware.Module.register('advanced-wishlist-analytics',
    {
        type: 'plugin',
        name: 'advanced-wishlist-analytics.general.mainMenuItem',
        title: 'advanced-wishlist-analytics.general.mainMenuItem',
        description: 'advanced-wishlist-analytics.general.description',
        color: '#ff3e67',
        icon: 'default-action-stats',

        routes: {
            overview: {
                component: 'advanced-wishlist-analytics-overview',
                path: 'overview',
                meta: {
                    parentPath: 'sw.settings.index',
                    privilege: 'advanced_wishlist.viewer'
                }
            }
        },

        navigation: [{
            label: 'advanced-wishlist-analytics.general.mainMenuItem',
            color: '#ff3e67',
            path: 'advanced.wishlist.analytics.overview',
            icon: 'default-action-stats',
            position: 100,
            parent: 'sw-marketing',
            privilege: 'advanced_wishlist.viewer'
        }]
    });